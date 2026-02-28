<?php
namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmServiceRequest;
use App\Models\Crm\CrmServiceRequestComment;
use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmEvent;
use App\Models\User;
use App\Models\SystemEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class CrmServiceRequestController extends Controller
{
    /**
     * POST /crm/accounts/{id}/service-requests
     */
    public function store(Request $request, int $accountId)
    {
        $request->validate([
            'category'       => 'required|string|max:60',
            'subject'        => 'required|string|max:255',
            'description'    => 'required|string|max:3000',
            'priority'       => 'required|in:baixa,normal,alta,urgente',
            'assigned_to_user_id' => 'nullable|exists:users,id',
        ]);

        $account = CrmAccount::findOrFail($accountId);
        $requiresApproval = CrmServiceRequest::categoriaRequerAprovacao($request->category);

        $sr = CrmServiceRequest::create([
            'account_id'           => $accountId,
            'category'             => $request->category,
            'subject'              => $request->subject,
            'description'          => $request->description,
            'priority'             => $request->priority,
            'status'               => 'aberto',
            'requested_by_user_id' => auth()->id(),
            'assigned_to_user_id'  => $request->assigned_to_user_id,
            'requires_approval'    => $requiresApproval,
            'assigned_at'          => $request->assigned_to_user_id ? now() : null,
        ]);

        // Auto-aprovacao: se criador e admin/socio, aprova automaticamente
        if ($requiresApproval && in_array(auth()->user()->role, ['admin', 'socio'])) {
            $sr->update([
                'status'               => 'aprovado',
                'approved_by_user_id'  => auth()->id(),
                'approved_at'          => now(),
            ]);
        }

        // Evento CRM
        CrmEvent::create([
            'account_id'         => $accountId,
            'type'               => 'service_request_created',
            'payload'            => [
                'sr_id'    => $sr->id,
                'category' => $request->category,
                'subject'  => $request->subject,
                'priority' => $request->priority,
                'requires_approval' => $requiresApproval,
            ],
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);

        // Notificação por email ao atribuído
        if ($sr->assigned_to_user_id) {
            $this->notifyAssigned($sr);
        }

        // Notificação sistema (sininho)
        $this->createBellNotification($sr, 'Nova solicitação: ' . $sr->subject);

        return back()->with('success', 'Solicitação #' . $sr->id . ' criada com sucesso.')->withFragment('solicitacoes');
    }

    /**
     * GET /crm/service-requests/{id}
     */
    public function show(int $id)
    {
        $sr = CrmServiceRequest::with([
            'account', 'requestedBy', 'assignedTo', 'approvedBy',
            'comments' => fn($q) => $q->with('user')->oldest(),
        ])->findOrFail($id);

        $users = User::orderBy('name')->get(['id', 'name']);
        $categorias = CrmServiceRequest::categorias();

        // Timeline: events + comments
        $events = \DB::table('crm_events')
            ->where('type', 'LIKE', 'service_request%')
            ->whereRaw("JSON_EXTRACT(payload, '$.sr_id') = ?", [$id])
            ->get()
            ->map(fn($e) => (object)[
                'kind'       => 'event',
                'type'       => $e->type,
                'payload'    => json_decode($e->payload, true),
                'user_id'    => $e->created_by_user_id,
                'created_at' => $e->happened_at,
            ]);
        $comments = $sr->comments->map(fn($c) => (object)[
            'kind'        => 'comment',
            'type'        => $c->is_internal ? 'internal_note' : 'comment',
            'payload'     => ['body' => $c->body],
            'user_id'     => $c->user_id,
            'user_name'   => $c->user->name ?? '-',
            'created_at'  => $c->created_at->format('Y-m-d H:i:s'),
        ]);
        $timeline = $events->concat($comments)->sortBy('created_at')->values();
        $userNames = \App\Models\User::pluck('name', 'id');

        return view('crm.service-requests.show', compact('sr', 'users', 'categorias', 'timeline', 'userNames'));
    }

    /**
     * PUT /crm/service-requests/{id}
     */
    public function update(Request $request, int $id)
    {
        $sr = CrmServiceRequest::findOrFail($id);

        $request->validate([
            'status'              => 'nullable|in:aberto,em_andamento,aguardando_aprovacao,aprovado,rejeitado,concluido,cancelado',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'resolution_notes'    => 'nullable|string|max:3000',
            'priority'            => 'nullable|in:baixa,normal,alta,urgente',
        ]);

        $oldStatus = $sr->status;
        $updates = [];

        if ($request->filled('status')) {
            $updates['status'] = $request->status;

            if ($request->status === 'em_andamento' && !$sr->assigned_to_user_id) {
                $updates['assigned_to_user_id'] = auth()->id();
                $updates['assigned_at'] = now();
            }

            if ($request->status === 'concluido') {
                $updates['resolved_at'] = now();
                $updates['resolution_notes'] = $request->resolution_notes;
            }

            if ($request->status === 'aguardando_aprovacao') {
                // Notificar sócios/admin
                $this->notifyApprovers($sr);
            }

            if (in_array($request->status, ['aprovado', 'rejeitado'])) {
                $updates['approved_by_user_id'] = auth()->id();
                $updates['approved_at'] = now();
            }
        }

        if ($request->filled('assigned_to_user_id')) {
            $updates['assigned_to_user_id'] = $request->assigned_to_user_id;
            $updates['assigned_at'] = now();
            $this->notifyAssigned($sr, $request->assigned_to_user_id);
        }

        if ($request->filled('priority')) {
            $updates['priority'] = $request->priority;
        }

        if (!empty($updates)) {
            $sr->update($updates);

            CrmEvent::create([
                'account_id'         => $sr->account_id,
                'type'               => 'service_request_updated',
                'payload'            => array_merge(['sr_id' => $sr->id, 'from_status' => $oldStatus], $updates),
                'happened_at'        => now(),
                'created_by_user_id' => auth()->id(),
            ]);

            if (isset($updates['status']) && $updates['status'] !== $oldStatus) {
                $label = CrmServiceRequest::statusLabel($updates['status']);
                $this->createBellNotification($sr, "Solicitação #{$sr->id} → {$label}");
            }
        }

        return back()->with('success', 'Solicitação atualizada.');
    }

    /**
     * POST /crm/service-requests/{id}/comments
     */
    public function addComment(Request $request, int $id)
    {
        $request->validate([
            'body'        => 'required|string|max:3000',
            'is_internal' => 'nullable|boolean',
        ]);

        $sr = CrmServiceRequest::findOrFail($id);

        CrmServiceRequestComment::create([
            'service_request_id' => $id,
            'user_id'            => auth()->id(),
            'body'               => $request->body,
            'is_internal'        => $request->boolean('is_internal'),
        ]);

        return back()->with('success', 'Comentário adicionado.');
    }

    /**
     * Notificação por email ao atribuído
     */
    private function notifyAssigned(CrmServiceRequest $sr, ?int $userId = null)
    {
        try {
            $assignedUser = User::find($userId ?? $sr->assigned_to_user_id);
            if (!$assignedUser || !$assignedUser->email) return;

            $account = $sr->account ?? CrmAccount::find($sr->account_id);
            $requester = $sr->requestedBy ?? User::find($sr->requested_by_user_id);
            $categorias = CrmServiceRequest::categorias();
            $catLabel = $categorias[$sr->category]['label'] ?? $sr->category;

            Mail::raw(
                "Olá {$assignedUser->name},\n\n" .
                "Uma nova solicitação foi atribuída a você:\n\n" .
                "Solicitação: #{$sr->id}\n" .
                "Cliente: {$account->name}\n" .
                "Categoria: {$catLabel}\n" .
                "Assunto: {$sr->subject}\n" .
                "Prioridade: {$sr->priority}\n" .
                "Solicitante: {$requester->name}\n\n" .
                "Acesse o sistema para mais detalhes.\n\n" .
                "— RESULTADOS! Intranet",
                function ($message) use ($assignedUser, $sr) {
                    $message->to($assignedUser->email)
                            ->subject("[Solicitação #{$sr->id}] {$sr->subject}");
                }
            );
        } catch (\Exception $e) {
            Log::warning('[CRM] Falha ao notificar atribuído: ' . $e->getMessage());
        }
    }

    /**
     * Notificar sócios/admin para aprovação
     */
    private function notifyApprovers(CrmServiceRequest $sr)
    {
        try {
            $approvers = User::whereIn('role', ['admin', 'socio'])->get();
            foreach ($approvers as $approver) {
                if (!$approver->email) continue;
                $categorias = CrmServiceRequest::categorias();
                $catLabel = $categorias[$sr->category]['label'] ?? $sr->category;

                Mail::raw(
                    "Olá {$approver->name},\n\n" .
                    "A seguinte solicitação requer sua aprovação:\n\n" .
                    "Solicitação: #{$sr->id}\n" .
                    "Categoria: {$catLabel}\n" .
                    "Assunto: {$sr->subject}\n" .
                    "Prioridade: {$sr->priority}\n\n" .
                    "Acesse o sistema para aprovar ou rejeitar.\n\n" .
                    "— RESULTADOS! Intranet",
                    function ($message) use ($approver, $sr) {
                        $message->to($approver->email)
                                ->subject("[APROVAÇÃO] Solicitação #{$sr->id} — {$sr->subject}");
                    }
                );
            }
        } catch (\Exception $e) {
            Log::warning('[CRM] Falha ao notificar aprovadores: ' . $e->getMessage());
        }
    }

    /**
     * Criar notificação no sininho
     */
    private function createBellNotification(CrmServiceRequest $sr, string $message)
    {
        try {
            if (class_exists(SystemEvent::class)) {
                SystemEvent::crm('service_request', 'info', $message, null, [
                    'sr_id'      => $sr->id,
                    'account_id' => $sr->account_id,
                    'category'   => $sr->category,
                    'status'     => $sr->status,
                    'notify_user_id' => $sr->assigned_to_user_id,
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('[CRM] Falha ao criar notificação bell: ' . $e->getMessage());
        }
    }
}
