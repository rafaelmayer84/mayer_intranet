<?php

namespace App\Http\Controllers;

use App\Models\Eval180Form;
use App\Notifications\Eval180Notification;
use App\Models\Eval180Response;
use App\Models\GdpCiclo;
use App\Models\User;
use App\Services\Gdp\Eval180Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Eval180Controller extends Controller
{
    private Eval180Service $service;

    public function __construct(Eval180Service $service)
    {
        $this->service = $service;
    }

    // ══════════════════════════════════════════════════════════
    // ADVOGADO: Minhas avaliações
    // ══════════════════════════════════════════════════════════

    /**
     * GET /gdp/me/eval180
     * Lista avaliações do advogado logado.
     */
    public function meIndex()
    {
        $user = Auth::user();
        $ciclo = GdpCiclo::where('status', 'aberto')->first();

        if (!$ciclo) {
            return view('gdp.eval180.me-index', [
                'forms' => collect(),
                'ciclo' => null,
            ]);
        }

        $forms = Eval180Form::where('cycle_id', $ciclo->id)
            ->where('user_id', $user->id)
            ->with(['responses'])
            ->orderBy('period')
            ->get();

        return view('gdp.eval180.me-index', [
            'forms' => $forms,
            'ciclo' => $ciclo,
            'config' => $this->service->getConfig(),
        ]);
    }

    /**
     * GET /gdp/me/eval180/{cycle}/{period}
     * Formulário de autoavaliação.
     */
    public function meForm(int $cycleId, string $period)
    {
        $user = Auth::user();
        $ciclo = GdpCiclo::findOrFail($cycleId);

        $form = $this->service->getOrCreateForm($ciclo->id, $user->id, $period, $user->id);

        $selfResponse = $form->selfResponse;
        $answers = $selfResponse ? ($selfResponse->answers_json ?? []) : [];

        return view('gdp.eval180.me-form', [
            'form'              => $form,
            'ciclo'             => $ciclo,
            'period'            => $period,
            'questions'         => $this->service->getQuestions(),
            'sectionNames'      => $this->service->getSectionNames(),
            'answers'           => $answers,
            'response'          => $selfResponse,
            'isLocked'          => $form->isLocked(),
            'isSubmitted'       => $selfResponse && $selfResponse->isSubmitted(),
            'canSeeManagerNotes' => $form->canAvaliadorSeeManagerNotes(),
            'managerResponse'   => $form->canAvaliadorSeeManagerNotes() ? $form->managerResponse : null,
            'statusLabel'       => $this->getStatusLabel($form->status),
        ]);
    }

    /**
     * POST /gdp/me/eval180/{cycle}/{period}
     * Salva/submete autoavaliação.
     */
    public function meSave(Request $request, int $cycleId, string $period)
    {
        $user = Auth::user();
        $ciclo = GdpCiclo::findOrFail($cycleId);

        $form = $this->service->getOrCreateForm($ciclo->id, $user->id, $period, $user->id);

        if ($form->isLocked()) {
            return response()->json(['success' => false, 'errors' => ['Formulário travado.']], 422);
        }

        $data = $request->validate([
            'answers'      => 'required|array',
            'answers.*'    => 'required|integer|min:1|max:5',
            'comment_text' => 'nullable|string|max:2000',
            'evidence_text' => 'nullable|string|max:2000',
            'action'       => 'required|in:draft,submit',
        ]);

        if ($data['action'] === 'draft') {
            $this->service->saveDraft($form, 'self', $user->id, $data);
            return response()->json(['success' => true, 'message' => 'Rascunho salvo.']);
        }

        $result = $this->service->submitResponse($form, 'self', $user->id, $data);

        // Se submeteu com sucesso, atualizar status e notificar gestores
        if ($result['success'] ?? false) {
            $form->update(['status' => 'pending_manager']);

            $ciclo = $form->cycle ?? \App\Models\GdpCiclo::find($form->cycle_id);
            $periodoLabel = \Carbon\Carbon::createFromFormat('Y-m', $form->period)->translatedFormat('F/Y');

            // Notificar admin + coordenadores
            $gestores = User::whereIn('role', ['admin', 'coordenador'])
                ->where('ativo', true)
                ->whereNotIn('id', [2, 5, 6])
                ->get();

            foreach ($gestores as $gestor) {
                $gestor->notify(new Eval180Notification('autoavaliacao_concluida', [
                    'avaliado_nome' => $user->name,
                    'ciclo_nome'    => $ciclo->nome ?? '',
                    'periodo_label' => $periodoLabel,
                    'url'           => route('gdp.eval180.cycle', $form->cycle_id),
                ]));
            }
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    // ══════════════════════════════════════════════════════════
    // GESTOR/ADMIN: Lista de avaliados
    // ══════════════════════════════════════════════════════════

    /**
     * GET /gdp/cycles/{id}/eval180
     * Lista avaliados com semáforo de status.
     */
    public function cycleIndex(int $cycleId)
    {
        $this->authorizeManager();

        $ciclo = GdpCiclo::findOrFail($cycleId);

        // Usuários elegíveis (mesma lógica do GDP)
        $users = User::whereIn('role', ['advogado', 'socio', 'coordenador', 'admin'])
            ->where('ativo', true)
            ->whereNotIn('id', [2, 5, 6]) // excluir teste/sistema
            ->orderBy('name')
            ->get();

        // Períodos do ciclo
        $periods = $this->generatePeriods($ciclo);

        // Carregar forms existentes
        $forms = Eval180Form::where('cycle_id', $ciclo->id)
            ->with(['responses'])
            ->get()
            ->groupBy(fn($f) => $f->user_id . '_' . $f->period);

        return view('gdp.eval180.cycle-index', [
            'ciclo'   => $ciclo,
            'users'   => $users,
            'periods' => $periods,
            'forms'   => $forms,
        ]);
    }

    /**
     * GET /gdp/cycles/{id}/eval180/{user}/{period}
     * Formulário de avaliação do gestor + comparação com autoavaliação.
     */
    public function managerForm(int $cycleId, int $userId, string $period)
    {
        $this->authorizeManager();

        $ciclo = GdpCiclo::findOrFail($cycleId);
        $avaliado = User::findOrFail($userId);

        $form = $this->service->getOrCreateForm($ciclo->id, $userId, $period, Auth::id());

        $selfResponse = $form->selfResponse;
        $managerResponse = $form->managerResponse;
        $actionItems = $form->actionItems;

        return view('gdp.eval180.manager-form', [
            'form'            => $form,
            'ciclo'           => $ciclo,
            'avaliado'        => $avaliado,
            'period'          => $period,
            'questions'       => $this->service->getQuestions(),
            'sectionNames'    => $this->service->getSectionNames(),
            'sectionWeights'  => $this->service->getSectionWeights(),
            'selfAnswers'     => $selfResponse ? ($selfResponse->answers_json ?? []) : [],
            'selfScores'      => $selfResponse ? ($selfResponse->section_scores_json ?? []) : [],
            'selfTotal'       => $selfResponse->total_score ?? null,
            'selfSubmitted'   => $selfResponse && $selfResponse->isSubmitted(),
            'managerAnswers'  => $managerResponse ? ($managerResponse->answers_json ?? []) : [],
            'managerScores'   => $managerResponse ? ($managerResponse->section_scores_json ?? []) : [],
            'managerTotal'    => $managerResponse->total_score ?? null,
            'managerComment'  => $managerResponse->comment_text ?? '',
            'managerEvidence' => $managerResponse->evidence_text ?? '',
            'actionItems'     => $actionItems,
            'isLocked'        => $form->isLocked(),
            'isSubmitted'     => $managerResponse && $managerResponse->isSubmitted(),
            'config'          => $this->service->getConfig(),
        ]);
    }

    /**
     * POST /gdp/cycles/{id}/eval180/{user}/{period}
     * Salva/submete avaliação do gestor.
     */
    public function managerSave(Request $request, int $cycleId, int $userId, string $period)
    {
        $this->authorizeManager();

        $ciclo = GdpCiclo::findOrFail($cycleId);
        User::findOrFail($userId);

        $form = $this->service->getOrCreateForm($ciclo->id, $userId, $period, Auth::id());

        if ($form->isLocked()) {
            return response()->json(['success' => false, 'errors' => ['Formulário travado.']], 422);
        }

        $data = $request->validate([
            'answers'            => 'required|array',
            'answers.*'          => 'required|integer|min:1|max:5',
            'comment_text'       => 'nullable|string|max:5000',
            'evidence_text'      => 'nullable|string|max:5000',
            'action_items'       => 'nullable|array|max:3',
            'action_items.*.title'    => 'nullable|string|max:255',
            'action_items.*.due_date' => 'nullable|date',
            'action_items.*.notes'    => 'nullable|string|max:1000',
            'action'             => 'required|in:draft,submit',
        ]);

        if ($data['action'] === 'draft') {
            $this->service->saveDraft($form, 'manager', Auth::id(), $data);
            return response()->json(['success' => true, 'message' => 'Rascunho salvo.']);
        }

        $result = $this->service->submitResponse($form, 'manager', Auth::id(), $data);

        // Se submeteu com sucesso, mudar status para pending_feedback (oculto do avaliado)
        if ($result['success'] ?? false) {
            $form->update(['status' => 'pending_feedback']);
        }

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * POST /gdp/cycles/{id}/eval180/{user}/{period}/lock
     * Trava avaliação.
     */
    public function lock(int $cycleId, int $userId, string $period)
    {
        $this->authorizeAdmin();

        $form = Eval180Form::where('cycle_id', $cycleId)
            ->where('user_id', $userId)
            ->where('period', $period)
            ->firstOrFail();

        $this->service->lockForm($form, Auth::id());

        return response()->json(['success' => true, 'message' => 'Avaliação travada.']);
    }

    /**
     * POST /gdp/cycles/{id}/eval180/create
     * Cria avaliação avulsa e notifica avaliado.
     */
    public function createEval(Request $request, int $cycleId)
    {
        $this->authorizeManager();

        $data = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'period'  => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $ciclo = \App\Models\GdpCiclo::findOrFail($cycleId);
        $avaliado = User::findOrFail($data['user_id']);

        // Verificar se já existe
        $exists = Eval180Form::where('cycle_id', $ciclo->id)
            ->where('user_id', $avaliado->id)
            ->where('period', $data['period'])
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Avaliação já existe para este período.'], 422);
        }

        $form = Eval180Form::create([
            'cycle_id'   => $ciclo->id,
            'user_id'    => $avaliado->id,
            'period'     => $data['period'],
            'status'     => 'pending_self',
            'created_by' => Auth::id(),
        ]);

        // Audit log
        \App\Models\GdpAuditLog::create([
            'user_id'   => Auth::id(),
            'action'    => 'eval180_created',
            'entity'    => 'gdp_eval180_forms',
            'entity_id' => $form->id,
            'ip'        => $request->ip(),
            'payload'   => json_encode(['avaliado' => $avaliado->name, 'period' => $data['period']]),
        ]);

        // Notificar avaliado
        $periodoLabel = \Carbon\Carbon::createFromFormat('Y-m', $data['period'])->translatedFormat('F/Y');
        $avaliado->notify(new Eval180Notification('autoavaliacao_pendente', [
            'ciclo_nome'    => $ciclo->nome,
            'periodo_label' => $periodoLabel,
            'url'           => route('gdp.eval180.me.form', [$ciclo->id, $data['period']]),
        ]));

        return response()->json(['success' => true, 'message' => 'Avaliação criada. Notificação enviada para ' . $avaliado->name . '.']);
    }

    /**
     * POST /gdp/cycles/{id}/eval180/{user}/{period}/release-feedback
     * Libera resultado da avaliação para o avaliado ver.
     */
    public function releaseFeedback(Request $request, int $cycleId, int $userId, string $period)
    {
        $this->authorizeManager();

        $form = Eval180Form::where('cycle_id', $cycleId)
            ->where('user_id', $userId)
            ->where('period', $period)
            ->firstOrFail();

        if ($form->status !== 'pending_feedback') {
            return response()->json(['success' => false, 'message' => 'Avaliação não está aguardando feedback. Status atual: ' . $form->status], 422);
        }

        $form->update([
            'status'      => 'released',
            'feedback_at' => now(),
            'feedback_by' => Auth::id(),
        ]);

        // Audit log
        \App\Models\GdpAuditLog::create([
            'user_id'   => Auth::id(),
            'action'    => 'eval180_feedback_released',
            'entity'    => 'gdp_eval180_forms',
            'entity_id' => $form->id,
            'ip'        => $request->ip(),
            'payload'   => json_encode(['avaliado_id' => $userId]),
        ]);

        // Notificar avaliado
        $avaliado = User::findOrFail($userId);
        $ciclo = \App\Models\GdpCiclo::findOrFail($cycleId);
        $periodoLabel = \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F/Y');

        $avaliado->notify(new Eval180Notification('feedback_liberado', [
            'ciclo_nome'    => $ciclo->nome,
            'periodo_label' => $periodoLabel,
            'url'           => route('gdp.eval180.me.form', [$cycleId, $period]),
        ]));

        return response()->json(['success' => true, 'message' => 'Resultado liberado. Notificação enviada para ' . $avaliado->name . '.']);
    }

    /**
     * DELETE /gdp/cycles/{id}/eval180/{user}/{period}
     * Exclui avaliação permanentemente (hard delete). Admin only.
     */
    public function deleteEval(Request $request, int $cycleId, int $userId, string $period)
    {
        $this->authorizeAdmin();

        $form = Eval180Form::where('cycle_id', $cycleId)
            ->where('user_id', $userId)
            ->where('period', $period)
            ->firstOrFail();

        // Audit log antes de deletar
        \App\Models\GdpAuditLog::create([
            'user_id'   => Auth::id(),
            'action'    => 'eval180_deleted',
            'entity'    => 'gdp_eval180_forms',
            'entity_id' => $form->id,
            'ip'        => $request->ip(),
            'payload'   => json_encode([
                'avaliado_id' => $userId,
                'period'      => $period,
                'status'      => $form->status,
            ]),
        ]);

        // Hard delete (cascade: responses + action_items)
        $form->responses()->delete();
        $form->actionItems()->delete();
        $form->delete();

        return response()->json(['success' => true, 'message' => 'Avaliação excluída permanentemente.']);
    }

    /**
     * GET /gdp/cycles/{id}/eval180/report
     * Relatório consolidado.
     */
    public function report(int $cycleId)
    {
        $this->authorizeManager();

        $ciclo = GdpCiclo::findOrFail($cycleId);
        $report = $this->service->getConsolidatedReport($ciclo->id);
        $sectionNames = $this->service->getSectionNames();

        return view('gdp.eval180.report', [
            'ciclo'        => $ciclo,
            'report'       => $report,
            'sectionNames' => $sectionNames,
        ]);
    }

    // ══════════════════════════════════════════════════════════
    // HELPERS
    // ══════════════════════════════════════════════════════════

    private function getStatusLabel(string $status): string
    {
        return match($status) {
            'pending_self'     => 'Aguardando autoavaliação',
            'pending_manager'  => 'Aguardando avaliação do gestor',
            'pending_feedback' => 'Aguardando feedback',
            'released'         => 'Resultado liberado',
            'locked'           => 'Travada',
            default            => ucfirst($status),
        };
    }

    private function authorizeManager(): void
    {
        $role = Auth::user()->role ?? '';
        if (!in_array($role, ['admin', 'coordenador', 'socio'])) {
            abort(403, 'Acesso restrito a gestores.');
        }
    }

    private function authorizeAdmin(): void
    {
        if ((Auth::user()->role ?? '') !== 'admin') {
            abort(403, 'Acesso restrito a administradores.');
        }
    }

    /**
     * Gera lista de períodos para um ciclo (meses ou trimestres).
     */
    private function generatePeriods(GdpCiclo $ciclo): array
    {
        $inicio = \Carbon\Carbon::parse($ciclo->data_inicio);
        $fim = \Carbon\Carbon::parse($ciclo->data_fim);
        $periods = [];

        $current = $inicio->copy()->startOfMonth();
        while ($current->lte($fim)) {
            $periods[] = $current->format('Y-m');
            $current->addMonth();
        }

        return $periods;
    }
}
