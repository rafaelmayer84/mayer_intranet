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
        $this->createBellNotification($sr, 'Nova solicitação ' . $sr->protocolo . ': ' . $sr->subject);

        return back()->with('success', 'Solicitação ' . $sr->protocolo . ' criada com sucesso.')->withFragment('solicitacoes');
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
            'status'              => 'nullable|in:aberto,em_andamento,aguardando_aprovacao,aprovado,rejeitado,concluido,cancelado,devolvido',
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
            }

            // Salvar notas de resolução em qualquer mudança de status
            if ($request->filled('resolution_notes')) {
                $updates['resolution_notes'] = $request->resolution_notes;
            }

            if ($request->status === 'aguardando_aprovacao') {
                // Notificar sócios/admin
                $this->notifyApprovers($sr);
            }

            if ($request->status === 'devolvido') {
                $this->notifyDevolvido($sr, $request->resolution_notes);
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
                $this->createBellNotification($sr, "Solicitação {$sr->protocolo} → {$label}");
            }
        }

        // Se enviou anexos na ação, criar comentário automático com os arquivos
        if ($request->hasFile('action_attachments')) {
            $attachPaths = [];
            foreach ($request->file('action_attachments') as $file) {
                $attachPaths[] = $file->store('chamados/comments', 'public');
            }
            if (!empty($attachPaths)) {
                $statusLabel = isset($updates['status']) ? CrmServiceRequest::statusLabel($updates['status']) : 'atualização';
                CrmServiceRequestComment::create([
                    'service_request_id' => $id,
                    'user_id'            => auth()->id(),
                    'body'               => 'Anexo(s) adicionado(s) na ' . $statusLabel . '.' . ($request->filled('resolution_notes') ? "\n\n" . $request->resolution_notes : ''),
                    'is_internal'        => false,
                    'attachments'        => $attachPaths,
                ]);
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
            'body'           => 'required|string|max:3000',
            'is_internal'    => 'nullable|boolean',
            'attachments.*'  => 'nullable|file|max:10240',
        ]);

        $sr = CrmServiceRequest::findOrFail($id);

        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('chamados/comments', 'public');
            }
        }

        CrmServiceRequestComment::create([
            'service_request_id' => $id,
            'user_id'            => auth()->id(),
            'body'               => $request->body,
            'is_internal'        => $request->boolean('is_internal'),
            'attachments'        => !empty($attachmentPaths) ? $attachmentPaths : null,
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
            $prazo = $sr->sla_deadline ? \Carbon\Carbon::parse($sr->sla_deadline)->format('d/m/Y H:i') : 'A definir';
            $prazoDesejado = $sr->desired_deadline ? \Carbon\Carbon::parse($sr->desired_deadline)->format('d/m/Y') : '—';
            $descricao = nl2br(e($sr->description));
            $link = url('/chamados/' . $sr->id);

            $html = $this->buildEmailHtml(
                destinatario: $assignedUser->name,
                titulo: 'Chamado atribuído a você',
                subtitulo: 'Você recebeu uma nova solicitação para resolver.',
                cor: '#385776',
                protocolo: $sr->protocolo,
                campos: [
                    'Assunto'       => $sr->subject,
                    'Categoria'     => $catLabel,
                    'Prioridade'    => ucfirst($sr->priority),
                    'Impacto'       => $sr->impact ? ucfirst($sr->impact) : '—',
                    'Cliente'       => $account ? $account->name : 'Interno',
                    'Solicitante'   => $requester ? $requester->name : '—',
                    'Prazo SLA'     => $prazo,
                    'Prazo desejado'=> $prazoDesejado,
                ],
                descricao: $sr->description,
                link: $link,
                labelLink: 'Abrir Chamado'
            );

            Mail::html($html, function ($message) use ($assignedUser, $sr) {
                $message->to($assignedUser->email)
                        ->subject("[SIATE {$sr->protocolo}] Chamado atribuído: {$sr->subject}");
            });
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
            $categorias = CrmServiceRequest::categorias();
            $catLabel = $categorias[$sr->category]['label'] ?? $sr->category;
            $requester = $sr->requestedBy ?? User::find($sr->requested_by_user_id);
            $account = $sr->account ?? CrmAccount::find($sr->account_id);
            $link = url('/chamados/' . $sr->id);

            foreach ($approvers as $approver) {
                if (!$approver->email) continue;

                $html = $this->buildEmailHtml(
                    destinatario: $approver->name,
                    titulo: 'Aprovação necessária',
                    subtitulo: 'O chamado abaixo requer sua aprovação para prosseguir.',
                    cor: '#b45309',
                    protocolo: $sr->protocolo,
                    campos: [
                        'Assunto'     => $sr->subject,
                        'Categoria'   => $catLabel,
                        'Prioridade'  => ucfirst($sr->priority),
                        'Impacto'     => $sr->impact ? ucfirst($sr->impact) : '—',
                        'Cliente'     => $account ? $account->name : 'Interno',
                        'Solicitante' => $requester ? $requester->name : '—',
                        'Valor est.'  => $sr->estimated_value ? 'R$ ' . number_format($sr->estimated_value, 2, ',', '.') : '—',
                    ],
                    descricao: $sr->description,
                    link: $link,
                    labelLink: 'Aprovar / Rejeitar'
                );

                Mail::html($html, function ($message) use ($approver, $sr) {
                    $message->to($approver->email)
                            ->subject("[APROVAÇÃO SIATE] {$sr->protocolo} — {$sr->subject}");
                });
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
             $link = '/chamados/' . $sr->id;
             $recipients = collect();

             if ($sr->assigned_to_user_id) {
                 $recipients->push($sr->assigned_to_user_id);
             }
             if ($sr->requested_by_user_id && $sr->requested_by_user_id !== $sr->assigned_to_user_id) {
                 $recipients->push($sr->requested_by_user_id);
             }
             if ($recipients->isEmpty()) {
                 $adminIds = \App\Models\User::whereIn('role', ['admin', 'socio', 'coordenador'])->pluck('id');
                 $recipients = $adminIds;
             }

             foreach ($recipients->unique() as $userId) {
                 \App\Models\NotificationIntranet::enviar(
                     $userId,
                     'Chamado ' . $sr->protocolo,
                     $message,
                     $link,
                     'info',
                     'bell'
                 );
             }
         } catch (\Exception $e) {
             Log::warning('[CRM] Falha ao criar notificação bell: ' . $e->getMessage());
         }
     }

    /**
     * Notificar solicitante que o chamado foi devolvido para complementação
     */
    private function notifyDevolvido(CrmServiceRequest $sr, ?string $motivo = null)
    {
        try {
            $requester = User::find($sr->requested_by_user_id);
            if (!$requester || !$requester->email) return;

            $account = $sr->account ?? CrmAccount::find($sr->account_id);
            $categorias = CrmServiceRequest::categorias();
            $catLabel = $categorias[$sr->category]['label'] ?? $sr->category;
            $operador = auth()->user()->name;
            $link = url('/chamados/' . $sr->id);

            $html = $this->buildEmailHtml(
                destinatario: $requester->name,
                titulo: 'Chamado devolvido para complementação',
                subtitulo: 'Seu chamado foi devolvido. Acesse o sistema e adicione as informações solicitadas.',
                cor: '#dc2626',
                protocolo: $sr->protocolo,
                campos: [
                    'Assunto'      => $sr->subject,
                    'Categoria'    => $catLabel,
                    'Cliente'      => $account ? $account->name : 'Interno',
                    'Devolvido por'=> $operador,
                    'Motivo'       => $motivo ?: '—',
                ],
                descricao: null,
                link: $link,
                labelLink: 'Complementar Chamado'
            );

            Mail::html($html, function ($message) use ($requester, $sr) {
                $message->to($requester->email)
                        ->subject("[DEVOLVIDO] SIATE {$sr->protocolo} — {$sr->subject}");
            });

            $this->createBellNotification($sr, "Chamado {$sr->protocolo} devolvido para complementação por {$operador}");

        } catch (\Exception $e) {
            Log::warning('[CRM] Falha ao notificar devolução: ' . $e->getMessage());
        }
    }


    private function buildEmailHtml(
        string $destinatario,
        string $titulo,
        string $subtitulo,
        string $cor,
        string $protocolo,
        array $campos,
        ?string $descricao,
        string $link,
        string $labelLink
    ): string {
        $linhas = '';
        foreach ($campos as $label => $valor) {
            $linhas .= "<tr>
                <td style='padding:8px 12px;font-size:13px;color:#6b7280;white-space:nowrap;border-bottom:1px solid #f3f4f6;'>{$label}</td>
                <td style='padding:8px 12px;font-size:13px;color:#111827;font-weight:500;border-bottom:1px solid #f3f4f6;'>" . e($valor) . "</td>
            </tr>";
        }

        $descricaoHtml = '';
        if ($descricao) {
            $descricaoHtml = "<div style='margin-top:24px;'>
                <p style='margin:0 0 8px;font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;'>Descrição</p>
                <div style='background:#f9fafb;border-left:3px solid {$cor};padding:12px 16px;border-radius:0 8px 8px 0;font-size:13px;color:#374151;line-height:1.6;'>" . nl2br(e($descricao)) . "</div>
            </div>";
        }

        return "<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'></head>
<body style='margin:0;padding:0;background:#f3f4f6;font-family:Georgia,serif;'>
<table width='100%' cellpadding='0' cellspacing='0' style='background:#f3f4f6;padding:32px 16px;'>
  <tr><td align='center'>
    <table width='600' cellpadding='0' cellspacing='0' style='max-width:600px;width:100%;'>
<tr><td style='background:{$cor};border-radius:12px 12px 0 0;padding:28px 32px;'>
        <p style='margin:0 0 4px;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:rgba(255,255,255,.65);'>SIATE · Mayer Advogados</p>
        <h1 style='margin:0;font-size:22px;font-weight:700;color:#ffffff;line-height:1.3;'>{$titulo}</h1>
        <p style='margin:8px 0 0;font-size:13px;color:rgba(255,255,255,.8);'>{$subtitulo}</p>
      </td></tr>
<tr><td style='background:#ffffff;padding:28px 32px;'>
        <p style='margin:0 0 20px;font-size:14px;color:#374151;'>Olá, <strong>" . e($destinatario) . "</strong>.</p>
<div style='margin-bottom:20px;'>
          <span style='display:inline-block;background:" . $cor . "18;color:{$cor};font-size:12px;font-weight:700;letter-spacing:.08em;padding:4px 12px;border-radius:6px;border:1px solid " . $cor . "33;'>{$protocolo}</span>
        </div>
<table width='100%' cellpadding='0' cellspacing='0' style='border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;margin-bottom:8px;'>
          {$linhas}
        </table>

        {$descricaoHtml}
<div style='margin-top:28px;text-align:center;'>
          <a href='{$link}' style='display:inline-block;background:{$cor};color:#ffffff;font-size:14px;font-weight:600;padding:12px 28px;border-radius:8px;text-decoration:none;letter-spacing:.02em;'>{$labelLink} →</a>
        </div>
      </td></tr>
<tr><td style='background:#f9fafb;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 12px 12px;padding:16px 32px;text-align:center;'>
        <p style='margin:0;font-size:11px;color:#9ca3af;'>Este e-mail foi gerado automaticamente pelo <strong>RESULTADOS! Intranet</strong> · Mayer Sociedade de Advogados</p>
        <p style='margin:4px 0 0;font-size:11px;color:#9ca3af;'>Não responda este e-mail. Acesse o sistema para interagir com o chamado.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>";
    }
}
