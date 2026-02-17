<?php

namespace App\Services\Gdp;

use App\Models\Eval180Form;
use App\Models\Eval180Response;
use App\Models\Eval180ActionItem;
use App\Models\GdpCiclo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class Eval180Service
{
    private array $config;
    private array $questions;
    private array $sectionNames;
    private array $sectionWeights;

    public function __construct()
    {
        $this->loadConfig();
    }

    // ══════════════════════════════════════════════════════════
    // CONFIGURAÇÃO
    // ══════════════════════════════════════════════════════════

    private function loadConfig(): void
    {
        $raw = DB::table('configuracoes')
            ->where('chave', 'gdp_eval180_config')
            ->value('valor');

        $this->config = $raw ? json_decode($raw, true) : [];
        $this->questions = $this->config['questions'] ?? [];
        $this->sectionNames = $this->config['sections'] ?? [];
        $this->sectionWeights = $this->config['section_weights'] ?? [1 => 25, 2 => 25, 3 => 25, 4 => 25];
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function getQuestions(): array
    {
        return $this->questions;
    }

    public function getSectionNames(): array
    {
        return $this->sectionNames;
    }

    public function getSectionWeights(): array
    {
        return $this->sectionWeights;
    }

    public function getPeriodicidade(): string
    {
        return $this->config['periodicidade'] ?? 'configuravel';
    }

    public function getPisoQualidade(): float
    {
        return (float) ($this->config['piso_qualidade'] ?? 3.0);
    }

    public function getEvidenciaMin(): int
    {
        return (int) ($this->config['evidencia_trigger_min'] ?? 2);
    }

    public function getEvidenciaMax(): int
    {
        return (int) ($this->config['evidencia_trigger_max'] ?? 5);
    }

    public function getActionRequiredThreshold(): float
    {
        return (float) ($this->config['action_required_threshold'] ?? 3.0);
    }

    // ══════════════════════════════════════════════════════════
    // CÁLCULO DE SCORES (CORRIGIDO — não replica erros da planilha)
    // ══════════════════════════════════════════════════════════

    /**
     * Calcula médias por seção e total ponderado a partir das respostas.
     * Média da seção = MÉDIA EXATA das 5 questões da seção.
     * Total = média ponderada das seções pelos pesos configurados.
     */
    public function calculateScores(array $answers): array
    {
        $sectionScores = [];
        $questionsPerSection = [];

        // Agrupar respostas por seção
        foreach ($answers as $questionNumber => $score) {
            $section = (int) explode('.', (string) $questionNumber)[0];
            if ($section >= 1 && $section <= 4) {
                $questionsPerSection[$section][] = (float) $score;
            }
        }

        // Calcular média EXATA de cada seção (somente das 5 questões, nada mais)
        foreach ([1, 2, 3, 4] as $s) {
            $values = $questionsPerSection[$s] ?? [];
            $count = count($values);
            $sectionScores[$s] = $count > 0 ? round(array_sum($values) / $count, 2) : 0;
        }

        // Total = média ponderada
        $totalWeightedSum = 0;
        $totalWeight = 0;
        foreach ($sectionScores as $s => $avg) {
            $weight = $this->sectionWeights[$s] ?? 25;
            $totalWeightedSum += $avg * $weight;
            $totalWeight += $weight;
        }

        $totalScore = $totalWeight > 0 ? round($totalWeightedSum / $totalWeight, 2) : 0;

        return [
            'section_scores' => $sectionScores,
            'total_score'    => $totalScore,
        ];
    }

    // ══════════════════════════════════════════════════════════
    // FORMULÁRIO (CRUD)
    // ══════════════════════════════════════════════════════════

    /**
     * Obtém ou cria formulário para ciclo/user/período.
     */
    public function getOrCreateForm(int $cycleId, int $userId, string $period, int $createdBy): Eval180Form
    {
        return Eval180Form::firstOrCreate(
            [
                'cycle_id' => $cycleId,
                'user_id'  => $userId,
                'period'   => $period,
            ],
            [
                'status'     => 'draft',
                'created_by' => $createdBy,
            ]
        );
    }

    // ══════════════════════════════════════════════════════════
    // SALVAR / SUBMETER RESPOSTA
    // ══════════════════════════════════════════════════════════

    /**
     * Salva rascunho (draft) de resposta.
     */
    public function saveDraft(Eval180Form $form, string $raterType, int $raterUserId, array $data): Eval180Response
    {
        if ($form->isLocked()) {
            throw new \RuntimeException('Formulário travado, não é possível editar.');
        }

        $answers = $data['answers'] ?? [];
        $scores = $this->calculateScores($answers);

        return Eval180Response::updateOrCreate(
            [
                'form_id'    => $form->id,
                'rater_type' => $raterType,
            ],
            [
                'rater_user_id'      => $raterUserId,
                'answers_json'       => $answers,
                'section_scores_json' => $scores['section_scores'],
                'total_score'        => $scores['total_score'],
                'comment_text'       => $data['comment_text'] ?? null,
                'evidence_text'      => $data['evidence_text'] ?? null,
                // submitted_at permanece null em draft
            ]
        );
    }

    /**
     * Submete resposta com validações de negócio.
     */
    public function submitResponse(Eval180Form $form, string $raterType, int $raterUserId, array $data): array
    {
        if ($form->isLocked()) {
            return ['success' => false, 'errors' => ['Formulário travado.']];
        }

        $answers = $data['answers'] ?? [];
        $errors = $this->validateSubmission($raterType, $answers, $data);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $scores = $this->calculateScores($answers);

        DB::beginTransaction();
        try {
            // Salvar resposta
            $response = Eval180Response::updateOrCreate(
                [
                    'form_id'    => $form->id,
                    'rater_type' => $raterType,
                ],
                [
                    'rater_user_id'      => $raterUserId,
                    'answers_json'       => $answers,
                    'section_scores_json' => $scores['section_scores'],
                    'total_score'        => $scores['total_score'],
                    'comment_text'       => $data['comment_text'] ?? null,
                    'evidence_text'      => $data['evidence_text'] ?? null,
                    'submitted_at'       => now(),
                ]
            );

            // Se gestor: salvar action items + integrar com GDP
            if ($raterType === 'manager') {
                $this->saveActionItems($form, $data['action_items'] ?? []);
                $this->integrateWithGdp($form, $scores['total_score']);
            }

            // Audit log
            $this->audit('submit', $form->id, [
                'rater_type' => $raterType,
                'rater_user_id' => $raterUserId,
                'total_score' => $scores['total_score'],
            ]);

            DB::commit();
            return ['success' => true, 'response' => $response, 'scores' => $scores];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('[Eval180] Erro ao submeter: ' . $e->getMessage());
            return ['success' => false, 'errors' => ['Erro interno ao salvar.']];
        }
    }

    // ══════════════════════════════════════════════════════════
    // VALIDAÇÃO DE SUBMISSÃO
    // ══════════════════════════════════════════════════════════

    private function validateSubmission(string $raterType, array $answers, array $data): array
    {
        $errors = [];

        // Todas as 20 perguntas respondidas?
        for ($s = 1; $s <= 4; $s++) {
            for ($q = 1; $q <= 5; $q++) {
                $key = "$s.$q";
                if (!isset($answers[$key]) || !is_numeric($answers[$key])) {
                    $errors[] = "Pergunta $key não respondida.";
                } elseif ((int) $answers[$key] < 1 || (int) $answers[$key] > 5) {
                    $errors[] = "Pergunta $key: nota deve ser entre 1 e 5.";
                }
            }
        }

        // Gestor: comentário obrigatório
        if ($raterType === 'manager') {
            if (empty(trim($data['comment_text'] ?? ''))) {
                $errors[] = 'Comentário/observação é obrigatório para o gestor.';
            }
        }

        // Evidência obrigatória para notas extremas
        $needsEvidence = false;
        foreach ($answers as $q => $score) {
            $s = (int) $score;
            if ($s <= $this->getEvidenciaMin() || $s >= $this->getEvidenciaMax()) {
                $needsEvidence = true;
                break;
            }
        }
        if ($needsEvidence && empty(trim($data['evidence_text'] ?? ''))) {
            $errors[] = 'Evidência/justificativa obrigatória quando há nota ≤' . $this->getEvidenciaMin() . ' ou ≥' . $this->getEvidenciaMax() . '.';
        }

        // Gestor: plano de ação obrigatório se total < threshold
        if ($raterType === 'manager') {
            $scores = $this->calculateScores($answers);
            if ($scores['total_score'] < $this->getActionRequiredThreshold()) {
                $actionItems = $data['action_items'] ?? [];
                $validItems = array_filter($actionItems, fn($item) => !empty(trim($item['title'] ?? '')));
                if (count($validItems) < 1) {
                    $errors[] = 'Plano de ação obrigatório quando nota total < ' . $this->getActionRequiredThreshold() . '.';
                }
            }
        }

        return $errors;
    }

    // ══════════════════════════════════════════════════════════
    // ACTION ITEMS
    // ══════════════════════════════════════════════════════════

    private function saveActionItems(Eval180Form $form, array $items): void
    {
        // Remove antigos não-done
        Eval180ActionItem::where('form_id', $form->id)->where('status', 'open')->delete();

        $maxItems = (int) ($this->config['max_action_items'] ?? 3);
        $count = 0;

        foreach ($items as $item) {
            if ($count >= $maxItems) {
                break;
            }
            if (empty(trim($item['title'] ?? ''))) {
                continue;
            }
            Eval180ActionItem::create([
                'form_id'       => $form->id,
                'owner_user_id' => $form->user_id,
                'title'         => trim($item['title']),
                'due_date'      => $item['due_date'] ?? now()->addDays(30)->toDateString(),
                'status'        => 'open',
                'notes'         => $item['notes'] ?? null,
            ]);
            $count++;
        }
    }

    // ══════════════════════════════════════════════════════════
    // INTEGRAÇÃO COM GDP (Guardrail / Dimensão E)
    // ══════════════════════════════════════════════════════════

    private function integrateWithGdp(Eval180Form $form, float $totalScore): void
    {
        try {
            // Normalizar score 1-5 para 0-100
            $normalized = round(($totalScore - 1) / 4 * 100, 2);

            // Buscar/criar snapshot GDP para este mês
            $period = $form->period; // YYYY-MM ou YYYY-Q1
            $parts = explode('-', $period);
            $ano = (int) $parts[0];

            // Extrair mês do período
            if (preg_match('/Q(\d)/', $period, $m)) {
                $mes = ((int) $m[1] - 1) * 3 + 2; // Q1→2, Q2→5 (mês do meio)
            } else {
                $mes = (int) ($parts[1] ?? date('m'));
            }

            // Gravar no gdp_snapshots como explain
            DB::table('gdp_snapshots')
                ->where('ciclo_id', $form->cycle_id)
                ->where('user_id', $form->user_id)
                ->where('mes', $mes)
                ->where('ano', $ano)
                ->update([
                    'updated_at' => now(),
                ]);

            // Guardrail: se total < piso_qualidade, registrar alerta
            if ($totalScore < $this->getPisoQualidade()) {
                Log::warning("[GDP-Eval180] Guardrail ativado: user={$form->user_id} score={$totalScore} < piso={$this->getPisoQualidade()}");
            }

            // Audit
            $this->audit('gdp_integrate', $form->id, [
                'total_score'  => $totalScore,
                'normalized'   => $normalized,
                'guardrail'    => $totalScore < $this->getPisoQualidade() ? 'ativado' : 'ok',
            ]);
        } catch (\Throwable $e) {
            Log::error('[Eval180] Erro na integração GDP: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════
    // LOCK / UNLOCK
    // ══════════════════════════════════════════════════════════

    public function lockForm(Eval180Form $form, int $adminId, ?string $motivo = null): bool
    {
        $form->update(['status' => 'locked']);

        $this->audit('lock', $form->id, [
            'admin_id' => $adminId,
            'motivo'   => $motivo,
        ]);

        return true;
    }

    public function unlockForm(Eval180Form $form, int $adminId, string $motivo): bool
    {
        $form->update(['status' => 'submitted']);

        $this->audit('unlock_override', $form->id, [
            'admin_id' => $adminId,
            'motivo'   => $motivo,
        ]);

        return true;
    }

    /**
     * Lock automático de todas as avaliações de um período.
     */
    public function lockPeriod(int $cycleId, string $period, int $adminId): int
    {
        $forms = Eval180Form::where('cycle_id', $cycleId)
            ->where('period', $period)
            ->where('status', '!=', 'locked')
            ->get();

        $count = 0;
        foreach ($forms as $form) {
            $this->lockForm($form, $adminId, 'Fechamento automático do período');
            $count++;
        }

        return $count;
    }

    // ══════════════════════════════════════════════════════════
    // RELATÓRIO CONSOLIDADO
    // ══════════════════════════════════════════════════════════

    public function getConsolidatedReport(int $cycleId): array
    {
        $forms = Eval180Form::where('cycle_id', $cycleId)
            ->with(['avaliado', 'responses', 'actionItems'])
            ->get();

        $report = [];
        foreach ($forms as $form) {
            $managerResp = $form->responses->firstWhere('rater_type', 'manager');
            $selfResp = $form->responses->firstWhere('rater_type', 'self');

            $report[] = [
                'form_id'        => $form->id,
                'user_id'        => $form->user_id,
                'user_name'      => $form->avaliado->name ?? '—',
                'period'         => $form->period,
                'status'         => $form->status,
                'self_submitted'    => $selfResp && $selfResp->submitted_at ? true : false,
                'manager_submitted' => $managerResp && $managerResp->submitted_at ? true : false,
                'self_total'     => $selfResp->total_score ?? null,
                'manager_total'  => $managerResp->total_score ?? null,
                'self_sections'  => $selfResp->section_scores_json ?? [],
                'manager_sections' => $managerResp->section_scores_json ?? [],
                'action_items'   => $form->actionItems->count(),
                'action_done'    => $form->actionItems->where('status', 'done')->count(),
            ];
        }

        return $report;
    }

    // ══════════════════════════════════════════════════════════
    // AUDITORIA
    // ══════════════════════════════════════════════════════════

    private function audit(string $action, int $formId, array $payload): void
    {
        try {
            DB::table('gdp_audit_log')->insert([
                'user_id'    => auth()->id() ?? 0,
                'acao'       => "eval180_{$action}",
                'entidade'   => 'gdp_eval180_forms',
                'entidade_id' => $formId,
                'payload'    => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'ip'         => request()->ip() ?? '0.0.0.0',
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Eval180] Audit log falhou: ' . $e->getMessage());
        }
    }

    // ══════════════════════════════════════════════════════════
    // CONFIG UPDATE (Admin)
    // ══════════════════════════════════════════════════════════

    public function updateConfig(array $newConfig): void
    {
        $merged = array_merge($this->config, $newConfig);

        DB::table('configuracoes')
            ->where('chave', 'gdp_eval180_config')
            ->update([
                'valor'      => json_encode($merged, JSON_UNESCAPED_UNICODE),
                'updated_at' => now(),
            ]);

        $this->config = $merged;
    }
}
