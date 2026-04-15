<?php

namespace App\Http\Controllers;

use App\Models\WaConversation;
use App\Models\WaMessage;
use App\Models\WaEvent;
use App\Models\WaNote;
use App\Models\User;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\WaTag;
use App\Services\SendPulseWhatsAppService;
use App\Services\NexoConversationSyncService;
use App\Services\NexoDataJuriService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║  NEXO ATENDIMENTO CONTROLLER — VERSÃO ESTÁVEL v2.2         ║
 * ║  Data: 27/02/2026 | Hotfix: 15/04/2026                     ║
 * ║  Status: CONGELADO — NÃO EDITAR SEM AUTORIZAÇÃO            ║
 * ║                                                             ║
 * ║  Qualquer extensão deve ser feita em:                       ║
 * ║  - Novo Controller (ex: NexoAtendimentoExtController)       ║
 * ║  - Novo Service (ex: NexoXxxService)                        ║
 * ║  - NUNCA editar este arquivo diretamente                    ║
 * ╠══════════════════════════════════════════════════════════════╣
 * ║  HOTFIX 15/04/2026 — changeStatus()                        ║
 * ║  BUG: Fechar conversa manualmente não chamava               ║
 * ║  reativarAutomacao() no SendPulse. Variável                 ║
 * ║  atendimento_humano ficava "sim" indefinidamente,           ║
 * ║  causando envio repetido de "Aguarde a resposta do          ║
 * ║  advogado" (~20x/dia). Corrigido: agora ao fechar,         ║
 * ║  reseta bot_ativo, assigned_user_id e chama                 ║
 * ║  reativarAutomacao() no SendPulse.                          ║
 * ╚══════════════════════════════════════════════════════════════╝
 */
class NexoAtendimentoController extends Controller
{
    public function index()
    {
        $users = User::orderBy('name')->get();
        return view('nexo.atendimento.index', compact('users'));
    }

    public function conversas(Request $request)
    {
        $query = WaConversation::with('assignedUser')->orderByDesc('last_message_at');

        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('unread') && $request->unread == '1') $query->where('unread_count', '>', 0);
        if ($request->filled('responsavel')) $query->where('assigned_user_id', $request->responsavel);
        if ($request->filled('priority') && $request->priority !== 'all') $query->where('priority', $request->priority);
        if ($request->filled('minhas') && $request->minhas == '1') $query->where('assigned_user_id', auth()->id());
        if ($request->filled('tipo')) {
            switch ($request->tipo) {
                case 'lead': $query->whereNotNull('linked_lead_id'); break;
                case 'cliente': $query->whereNotNull('linked_cliente_id'); break;
                case 'indefinido': $query->whereNull('linked_lead_id')->whereNull('linked_cliente_id'); break;
            }
        }

        return response()->json($query->paginate(30));
    }

    public function buscarConversas(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) return response()->json(['data' => []]);
        $clean = preg_replace('/\D/', '', $q);
        $query = WaConversation::with('assignedUser')->orderByDesc('last_message_at')->limit(20);
        if ($clean && strlen($clean) >= 8) {
            $query->where('phone', 'like', "%{$clean}%");
        } else {
            $query->where(function ($qb) use ($q, $clean) {
                $qb->where('name', 'like', "%{$q}%");
                if ($clean) $qb->orWhere('phone', 'like', "%{$clean}%");
            });
        }
        return response()->json(['data' => $query->get()]);
    }

    public function conversa(int $id)
    {
        $conversation = WaConversation::with('assignedUser')->findOrFail($id);

        // Bug 1 fix: zerar unread ao abrir conversa
        if ($conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();
        }

        $messages = WaMessage::where('conversation_id', $id)->orderBy('sent_at', 'asc')->orderBy('id', 'asc')->get();
        $messages = $this->filterQaMessages($messages);
        return response()->json(['conversation' => $conversation, 'messages' => $messages, 'bot_ativo' => (bool) $conversation->bot_ativo]);
    }

    public function enviarMensagem(Request $request, int $id)
    {
        $request->validate([
            'text' => 'required|string|max:4096',
            'reply_to_message_id' => 'nullable|string|max:255',
        ]);
        $conversation = WaConversation::findOrFail($id);
        $replyTo = $request->input('reply_to_message_id');

        try {
            $spService = app(SendPulseWhatsAppService::class);

            // Prefixar nome do operador para o cliente ver no WhatsApp
            $user = auth()->user();
            $operatorName = ($user->role === "admin" && !empty($user->operator_alias)) ? $user->operator_alias : $user->name;
            $textToSend = "*" . $operatorName . "*
" . $request->text;

            // Envio com ou sem reply/quote
            if ($replyTo) {
                $result = $spService->sendMessageWithReply($conversation->contact_id, $textToSend, $replyTo);
                if (!($result['success'] ?? false) && $conversation->phone) {
                    Log::info('NEXO: fallback sendMessageByPhoneWithReply', ['conversa_id' => $id]);
                    $result = $spService->sendMessageByPhoneWithReply($conversation->phone, $textToSend, $replyTo);
                }
            } else {
                $result = $spService->sendMessage($conversation->contact_id, $textToSend);
                if (!($result['success'] ?? false) && $conversation->phone) {
                    Log::info('NEXO: fallback sendMessageByPhone', ['conversa_id' => $id, 'phone' => $conversation->phone]);
                    $result = $spService->sendMessageByPhone($conversation->phone, $textToSend);
                }
            }

            if (!($result['success'] ?? false)) {
                $errorDetail = $result['error'] ?? 'Resposta inesperada da API';
                Log::error('NEXO: envio falhou em ambos métodos', ['conversa_id' => $id, 'error' => $errorDetail]);
                WaEvent::log('error', $id, ['action' => 'send_message', 'error' => $errorDetail, 'method' => 'both_failed']);
                return response()->json(['success' => false, 'error' => 'Mensagem não pôde ser entregue. Tente novamente.'], 502);
            }

            $message = WaMessage::create([
                'conversation_id' => $id,
                'direction' => WaMessage::DIRECTION_OUTGOING,
                'is_human' => true,
                'message_type' => 'text',
                'body' => $request->text,
                'reply_to_message_id' => $replyTo,
                'user_id' => auth()->id(),
                'sent_at' => now(),
            ]);

            // Auto-assumir: desativar bot + atribuir operador ao enviar msg humana
            // v2.9: tratar bot_ativo NULL como true (conversas antigas sem campo setado)
            if ($conversation->bot_ativo || $conversation->bot_ativo === null) {
                $conversation->update([
                    'bot_ativo' => false,
                    'assigned_user_id' => auth()->id(),
                    'assigned_at' => now(),
                ]);
                if ($conversation->contact_id) {
                    try {
                        $spService->pausarAutomacao($conversation->contact_id);
                        \Log::info('NEXO: bot pausado automaticamente ao enviar mensagem', ['conv_id' => $id, 'user' => auth()->user()->name]);
                    } catch (\Throwable $e) {
                        \Log::warning('NEXO: falha ao pausar bot no auto-assumir', ['error' => $e->getMessage()]);
                    }
                }
            } elseif (empty($conversation->assigned_user_id)) {
                // Atribuir operador mesmo se bot ja estava desativado
                $conversation->update([
                    'assigned_user_id' => auth()->id(),
                    'assigned_at' => now(),
                ]);
            }

            if (!$conversation->first_response_at) $conversation->first_response_at = now();
            if (!$conversation->assigned_user_id) $conversation->assigned_user_id = auth()->id();
            $conversation->last_message_at = now();
            $conversation->unread_count = 0;

            // Auto-pausar bot quando operador responde
            if ($conversation->bot_ativo && $conversation->contact_id) {
                $conversation->bot_ativo = false;
                try {
                    $spService->pausarAutomacao($conversation->contact_id);
                    Log::info('Bot control: automacao pausada automaticamente ao responder', [
                        'conv_id' => $id, 'user' => auth()->user()->name,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('Bot control: falha ao pausar automacao ao responder', ['error' => $e->getMessage()]);
                }
            }

            $conversation->save();

            // Auto-update: lead vinculado -> contatado
            if ($conversation->linked_lead_id) {
                \App\Models\Lead::where('id', $conversation->linked_lead_id)
                    ->where('status', 'novo')
                    ->update(['status' => 'contatado', 'updated_at' => now()]);
                // Sync lead com CRM account
                try {
                    $lead = \App\Models\Lead::find($conversation->linked_lead_id);
                    if ($lead) {
                        (new \App\Services\Crm\CrmLeadSyncService())->syncLead($lead);
                    }
                } catch (\Exception $e) {
                    \Log::warning('[NEXO] CRM sync falhou lead #' . $conversation->linked_lead_id . ': ' . $e->getMessage());
                }
            }

            WaEvent::log('send_message', $id, ['text_length' => strlen($request->text), 'delivered' => true]);
            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar mensagem NEXO', ['conversa_id' => $id, 'error' => $e->getMessage()]);
            WaEvent::log('error', $id, ['action' => 'send_message', 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Falha ao enviar mensagem.'], 500);
        }
    }

    /**
     * Enviar reação emoji a uma mensagem WhatsApp.
     */
    public function reaction(Request $request, int $id)
    {
        $request->validate([
            'provider_message_id' => 'required|string|max:255',
            'emoji' => 'required|string|max:10',
        ]);

        $conversation = WaConversation::findOrFail($id);

        try {
            $spService = app(SendPulseWhatsAppService::class);
            $result = $spService->sendReaction(
                $conversation->contact_id,
                $request->input('provider_message_id'),
                $request->input('emoji')
            );

            if (!($result['success'] ?? false)) {
                Log::warning('NEXO: reaction falhou', [
                    'conversa_id' => $id,
                    'error' => $result['error'] ?? 'unknown',
                ]);
                return response()->json(['success' => false, 'error' => 'Não foi possível enviar a reação.'], 502);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::error('NEXO: erro ao enviar reaction', ['conversa_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Falha ao enviar reação.'], 500);
        }
    }

    /**
     * Poll mensagens — v2.2: sync leve a cada 30s + dados locais incrementais.
     * Webhook grava incoming. Sync leve captura outgoing do bot.
     * Suporta polling incremental via ?since_id=N
     */
    public function pollMessages(int $id, Request $request)
    {
        $conversation = WaConversation::findOrFail($id);
        $cid = $conversation->id;
        $sinceId = (int) $request->input('since_id', 0);

        // Sync leve: a cada 30s, captura mensagens outgoing do bot
        // Não bloqueia se falhar — serve dados locais de qualquer forma
        $syncKey = "nexo_light_sync_{$cid}";
        if (!Cache::has($syncKey) && !str_starts_with($conversation->contact_id ?? '', 'whatsapp_')) {
            Cache::put($syncKey, true, 30);
            try {
                app(NexoConversationSyncService::class)->syncConversation($conversation);
            } catch (\Throwable $e) {
                Log::warning('Poll light sync error', ['cid' => $cid, 'error' => $e->getMessage()]);
            }
        }

        if ($sinceId > 0) {
            $msgs = $this->filterQaMessages(
                WaMessage::where('conversation_id', $cid)
                    ->where('id', '>', $sinceId)
                    ->orderBy('sent_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get()
            );
            return response()->json([
                'incremental' => true,
                'new_count'   => $msgs->count(),
                'messages'    => $msgs,
                'last_id'     => $msgs->last()?->id ?? $sinceId,
            ]);
        }

        $msgs = $this->filterQaMessages(
            WaMessage::where('conversation_id', $cid)
                ->orderBy('sent_at', 'asc')
                ->orderBy('id', 'asc')
                ->get()
        );
        return response()->json([
            'incremental' => false,
            'new_count'   => $msgs->count(),
            'messages'    => $msgs,
            'last_id'     => $msgs->last()?->id ?? 0,
        ]);
    }

    /**
     * Force sync manual — operador clica botão quando suspeita que falta mensagem.
     * Este é o ÚNICO ponto que chama API SendPulse sob demanda.
     */
    public function forceSync(int $id)
    {
        $conversation = WaConversation::findOrFail($id);

        // Guard: não tentar sync de conversas legadas
        if (str_starts_with($conversation->contact_id ?? '', 'whatsapp_')) {
            return response()->json(['success' => false, 'reason' => 'Conversa legada sem contact_id válido']);
        }

        try {
            $newCount = app(NexoConversationSyncService::class)->syncConversation($conversation);
            $msgs = $this->filterQaMessages(
                WaMessage::where('conversation_id', $conversation->id)
                    ->orderBy('sent_at', 'asc')
                    ->orderBy('id', 'asc')
                    ->get()
            );
            return response()->json(['success' => true, 'new_messages' => $newCount, 'messages' => $msgs]);
        } catch (\Throwable $e) {
            Log::warning('ForceSync error', ['conversa_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'reason' => $e->getMessage()], 500);
        }
    }

    public function assignUser(Request $request, int $id)
    {
        $c = WaConversation::findOrFail($id);
        $oldUserId = $c->assigned_user_id;
        $newUserId = $request->input('user_id') ?: null;

        $c->assigned_user_id = $newUserId;
        $c->assigned_at = now();
        $c->save();

        // Notificar novo responsavel
        if ($newUserId) {
            $newUser = User::find($newUserId);
            $contactName = $c->name ?? $c->phone;
            $authorName2 = auth()->user()->name ?? 'Sistema';

            \App\Models\NotificationIntranet::enviar(
                $newUserId,
                'Nova conversa atribuida',
                "{$authorName2} transferiu a conversa de {$contactName} para voce.",
                route('nexo.atendimento') . '?conversa=' . $c->id,
                'transferencia',
                'chat'
            );

            if ($newUser && $newUser->email) {
                try {
                    \Illuminate\Support\Facades\Mail::raw(
                        "Ola {$newUser->name},\n\n"
                        . "{$authorName2} transferiu a conversa WhatsApp de {$contactName} para voce.\n"
                        . "Acesse o NEXO Atendimento para dar continuidade.\n\n"
                        . url(route('nexo.atendimento', [], false) . '?conversa=' . $c->id) . "\n\n"
                        . "Intranet Mayer Advogados",
                        function ($msg) use ($newUser, $contactName) {
                            $msg->to($newUser->email)
                                ->subject("NEXO: Conversa de {$contactName} transferida para voce");
                        }
                    );
                } catch (\Throwable $emailErr) {
                    \Illuminate\Support\Facades\Log::warning('Falha email transferencia', ['error' => $emailErr->getMessage()]);
                }
            }
        }

        // Bug 5: nota automática de transferência
        $oldName = $oldUserId ? (User::find($oldUserId)?->name ?? 'ID ' . $oldUserId) : 'Ninguém';
        $newName = $newUserId ? (User::find($newUserId)?->name ?? 'ID ' . $newUserId) : 'Ninguém';
        $authorName = auth()->user()?->name ?? 'Sistema';

        WaNote::create([
            'conversation_id' => $id,
            'user_id' => auth()->id(),
            'content' => "Transferência de responsável\nDe: {$oldName}\nPara: {$newName}\nPor: {$authorName}\nEm: " . now()->format('d/m/Y H:i'),
        ]);

        WaEvent::log('assign_changed', $id, ['from' => $oldUserId, 'to' => $newUserId]);
        return response()->json(['success' => true]);
    }

    public function changeStatus(Request $request, int $id)
    {
        $request->validate(['status' => 'required|in:open,closed']);
        $c = WaConversation::findOrFail($id);
        $c->status = $request->status;

        // Ao fechar: reativar bot no SendPulse para resetar variáveis e permitir fluxo normal
        if ($request->status === 'closed') {
            $c->bot_ativo = true;
            $c->assigned_user_id = null;
            $c->assigned_at = null;
            $c->lembrete_inatividade_at = null;

            if ($c->contact_id) {
                try {
                    $sp = app(SendPulseWhatsAppService::class);
                    $sp->reativarAutomacao($c->contact_id);
                    Log::info('changeStatus: bot reativado ao fechar conversa', [
                        'conv_id' => $id, 'contact_id' => $c->contact_id,
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('changeStatus: falha ao reativar bot no SendPulse', [
                        'conv_id' => $id, 'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        $c->save();
        return response()->json(['success' => true, 'status' => $c->status]);
    }

    public function linkLead(Request $request, int $id)
    {
        $request->validate(['lead_id' => 'required|integer|exists:leads,id']);
        $c = WaConversation::findOrFail($id);
        $c->linked_lead_id = $request->lead_id;

        // Propagar para crm_account via lead.crm_account_id
        $crmAccountId = \Illuminate\Support\Facades\DB::table('leads')
            ->where('id', $request->lead_id)
            ->whereNotNull('crm_account_id')
            ->value('crm_account_id');
        if ($crmAccountId) {
            $c->linked_crm_account_id = $crmAccountId;
        }

        $c->save();
        return response()->json(['success' => true, 'crm_account_id' => $c->linked_crm_account_id]);
    }

    public function linkCliente(Request $request, int $id)
    {
        $request->validate(['cliente_id' => 'required|integer|exists:clientes,id']);
        $c = WaConversation::findOrFail($id);
        $c->linked_cliente_id = $request->cliente_id;

        // Propagar para crm_account via datajuri_pessoa_id
        $datajuriId = \Illuminate\Support\Facades\DB::table('clientes')
            ->where('id', $request->cliente_id)
            ->whereNotNull('datajuri_id')
            ->value('datajuri_id');
        if ($datajuriId) {
            $crmAccountId = \Illuminate\Support\Facades\DB::table('crm_accounts')
                ->where('datajuri_pessoa_id', $datajuriId)
                ->value('id');
            if ($crmAccountId) {
                $c->linked_crm_account_id = $crmAccountId;
            }
        }

        $c->save();
        return response()->json(['success' => true, 'crm_account_id' => $c->linked_crm_account_id]);
    }

    /**
     * DELETE /nexo/atendimento/conversas/{id}/unlink-lead
     * Remove vinculação de lead da conversa.
     */
    public function unlinkLead(int $id)
    {
        $c = WaConversation::findOrFail($id);
        $c->linked_lead_id = null;
        // Limpa crm_account_id somente se não veio de outro caminho (cliente)
        if (!$c->linked_cliente_id) {
            $c->linked_crm_account_id = null;
        }
        $c->save();
        return response()->json(['success' => true]);
    }

    /**
     * DELETE /nexo/atendimento/conversas/{id}/unlink-cliente
     * Remove vinculação de cliente da conversa (e processo vinculado).
     */
    public function unlinkCliente(int $id)
    {
        $c = WaConversation::findOrFail($id);
        $c->linked_cliente_id = null;
        $c->linked_processo_id = null;
        // Limpa crm_account_id somente se não veio de outro caminho (lead)
        if (!$c->linked_lead_id) {
            $c->linked_crm_account_id = null;
        }
        $c->save();
        return response()->json(['success' => true]);
    }

    public function contexto360(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        $ctx = ['link_type' => $conv->link_type, 'lead' => null, 'cliente' => null, 'crm_account_id' => $conv->linked_crm_account_id];

        try {
            if ($conv->linked_lead_id && $conv->lead) {
                $leadData = $conv->lead->toArray();
                // CRM integration: buscar account e oportunidades vinculadas
                $crmId = $conv->lead->crm_account_id ?? $conv->linked_crm_account_id;
                if ($crmId) {
                    $account = \App\Models\Crm\CrmAccount::with(['opportunities' => function($q) {
                        $q->orderByDesc('created_at')->limit(10);
                    }, 'opportunities.stage'])->find($crmId);
                    if ($account) {
                        $leadData['crm_account'] = $account->toArray();
                        $ctx['crm_account_id'] = $account->id;
                    }
                }
                $ctx['lead'] = $leadData;
            }
        } catch (\Throwable $e) { $ctx['lead'] = ['_error' => $e->getMessage()]; }

        try {
            if ($conv->linked_cliente_id && $conv->cliente) {
                $cl = $conv->cliente;
                $d = $cl->toArray();
                // CRM account via cliente (linked_crm_account_id já populado pelo backfill)
                if ($conv->linked_crm_account_id && !isset($leadData['crm_account'])) {
                    $account = \App\Models\Crm\CrmAccount::with(['serviceRequests' => function($q) {
                        $q->latest()->limit(5);
                    }])->find($conv->linked_crm_account_id);
                    if ($account) {
                        $d['crm_account'] = ['id' => $account->id, 'name' => $account->name, 'lifecycle' => $account->lifecycle];
                        $d['crm_service_requests'] = $account->serviceRequests->map(fn($sr) => [
                            'id' => $sr->id, 'protocolo' => $sr->protocolo, 'subject' => $sr->subject,
                            'status' => $sr->status, 'category' => $sr->category, 'created_at' => $sr->created_at,
                        ])->toArray();
                    }
                }
                // Bug 2 fix: buscar processos sem filtro restritivo
                try {
                    if (method_exists($cl, 'processos')) {
                        $d['processos_ativos'] = $cl->processos()
                            ->whereNotIn('status', ['Arquivado', 'arquivado', 'Encerrado', 'encerrado'])
                            ->limit(20)->get()->toArray();
                    }
                } catch (\Throwable $e) {
                    try { $d['processos_ativos'] = $cl->processos()->limit(20)->get()->toArray(); } catch (\Throwable $e2) { $d['processos_ativos'] = []; }
                }
                try { if (method_exists($cl, 'contasReceber')) $d['contas_abertas'] = $cl->contasReceber()->whereIn('status', ['aberta', 'Aberta', 'pendente', 'Pendente'])->limit(10)->get()->toArray(); } catch (\Throwable $e) { $d['contas_abertas'] = []; }
                $ctx['cliente'] = $d;
            }
        } catch (\Throwable $e) { $ctx['cliente'] = ['_error' => $e->getMessage()]; }

        return response()->json($ctx);
    }

    // ═══════════════════════════════════════════════════════════════
    // v2.0: PRIORIDADE
    // ═══════════════════════════════════════════════════════════════

    public function updatePriority(Request $request, int $id)
    {
        $request->validate(['priority' => 'required|in:normal,alta,urgente,critica']);
        $c = WaConversation::findOrFail($id);
        $c->priority = $request->priority;
        $c->save();
        WaEvent::log('priority_changed', $id, ['priority' => $request->priority]);
        return response()->json(['success' => true, 'priority' => $c->priority]);
    }
    /**
     * Toggle marked_unread (marcar como não lida)
     */
    public function toggleMarkedUnread(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        $conv->marked_unread = !$conv->marked_unread;
        $conv->save();
        return response()->json(['success' => true, 'marked_unread' => $conv->marked_unread]);
    }


    // ═══════════════════════════════════════════════════════════════
    // v2.0: NOTAS INTERNAS
    // ═══════════════════════════════════════════════════════════════

    public function notes(int $id)
    {
        WaConversation::findOrFail($id);
        $notes = WaNote::where('conversation_id', $id)->with('user:id,name')->orderBy('created_at', 'desc')->get();
        return response()->json(['notes' => $notes]);
    }

    public function storeNote(Request $request, int $id)
    {
        $request->validate(['content' => 'required|string|max:5000']);
        WaConversation::findOrFail($id);
        $note = WaNote::create(['conversation_id' => $id, 'user_id' => auth()->id(), 'content' => $request->content]);
        $note->load('user:id,name');
        WaEvent::log('note_created', $id, ['note_id' => $note->id]);
        return response()->json(['success' => true, 'note' => $note]);
    }

    public function deleteNote(int $id, int $noteId)
    {
        WaNote::where('conversation_id', $id)->where('id', $noteId)->firstOrFail()->delete();
        return response()->json(['success' => true]);
    }

    // ═══════════════════════════════════════════════════════════════
    // v2.0: FLOWS (SendPulse)
    // ═══════════════════════════════════════════════════════════════

    public function flows()
    {
        try {
            $service = app(SendPulseWhatsAppService::class);
            $flows = $service->getFlows();
            if ($flows === null) return response()->json(['flows' => [], 'error' => 'Falha ao obter flows']);
            $data = isset($flows['data']) && is_array($flows['data']) ? $flows['data'] : (is_array($flows) ? $flows : []);
            return response()->json(['flows' => $data]);
        } catch (\Throwable $e) {
            Log::error('Erro ao listar flows', ['error' => $e->getMessage()]);
            return response()->json(['flows' => [], 'error' => 'Erro interno']);
        }
    }

    public function enviarMedia(Request $request, int $id)
    {
        $request->validate([
            'file' => 'required|file|max:16384', // 16MB max
            'caption' => 'nullable|string|max:1024',
        ]);

        $conversation = WaConversation::findOrFail($id);
        $file = $request->file('file');
        $mime = $file->getMimeType();

        // Determinar tipo WhatsApp
        $typeMap = [
            'image/jpeg' => 'image', 'image/png' => 'image', 'image/webp' => 'image',
            'application/pdf' => 'document', 'application/msword' => 'document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'document',
            'application/vnd.ms-excel' => 'document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'document',
            'audio/mpeg' => 'audio', 'audio/ogg' => 'audio', 'audio/wav' => 'audio', 'audio/mp4' => 'audio', 'audio/webm' => 'audio', 'video/webm' => 'audio',
        ];
        $waType = $typeMap[$mime] ?? 'document';

        // Salvar arquivo
        $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file->getClientOriginalName());
        $file->move(storage_path('app/public/nexo/media'), $filename);
        $path = 'public/nexo/media/' . $filename;
        $publicUrl = url('api/nexo/media/' . $filename);

        try {
            $spService = app(\App\Services\SendPulseWhatsAppService::class);
            $user = auth()->user();
            $operatorName = ($user->role === 'admin' && !empty($user->operator_alias)) ? $user->operator_alias : $user->name;
            $caption = $request->input('caption');
            $captionToSend = $caption ? "*{$operatorName}*\n{$caption}" : "*{$operatorName}*";

            $result = $spService->sendMediaByContact(
                $conversation->contact_id, $waType, $publicUrl,
                $captionToSend, $file->getClientOriginalName()
            );

            if (!($result['success'] ?? false) && $conversation->phone) {
                \Log::info('NEXO: fallback sendMediaByPhone', ['conv_id' => $id]);
                $result = $spService->sendMediaByPhone(
                    $conversation->phone, $waType, $publicUrl,
                    $captionToSend, $file->getClientOriginalName()
                );
            }

            if (!($result['success'] ?? false)) {
                return response()->json(['success' => false, 'error' => $result['error'] ?? 'Falha ao enviar mídia'], 502);
            }

            $message = WaMessage::create([
                'conversation_id' => $id,
                'direction' => WaMessage::DIRECTION_OUTGOING,
                'is_human' => true,
                'message_type' => $waType,
                'body' => $caption ?? '',
                'media_url' => $publicUrl,
                'media_mime_type' => $mime,
                'media_filename' => $file->getClientOriginalName(),
                'media_caption' => $caption,
                'user_id' => auth()->id(),
                'sent_at' => now(),
            ]);

            // Auto-assumir (mesmo codigo do enviarMensagem)
            if ($conversation->bot_ativo) {
                $conversation->update(['bot_ativo' => false, 'assigned_user_id' => auth()->id(), 'assigned_at' => now()]);
                if ($conversation->contact_id) {
                    try { $spService->pausarAutomacao($conversation->contact_id); } catch (\Throwable $e) {}
                }
            } elseif (empty($conversation->assigned_user_id)) {
                $conversation->update(['assigned_user_id' => auth()->id(), 'assigned_at' => now()]);
            }
            $conversation->last_message_at = now();
            $conversation->save();

            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            \Log::error('NEXO: erro enviar media', ['conv_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Falha ao enviar mídia.'], 500);
        }
    }

    public function runFlow(Request $request, int $id)
    {
        $request->validate(['flow_id' => 'required|string']);
        $conv = WaConversation::findOrFail($id);
        if (empty($conv->contact_id)) return response()->json(['success' => false, 'error' => 'Sem contact_id'], 422);

        try {
            $result = app(SendPulseWhatsAppService::class)->runFlow($conv->contact_id, $request->flow_id);
            WaEvent::log('flow_triggered', $id, ['flow_id' => $request->flow_id, 'success' => $result['success']]);
            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('Erro ao executar flow', ['conversa_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Erro ao executar flow'], 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // INTEGRAÇÃO NEXO ↔ DATAJURI (preservados)
    // ═══════════════════════════════════════════════════════════════

    public function autoLinkCliente(int $id)
    {
        $conversa = WaConversation::findOrFail($id);
        $service = new NexoDataJuriService();
        $resultado = $service->tentarVincularCliente($conversa);
        return response()->json([
            'status' => $resultado['status'], 'auto_linked' => $resultado['auto_linked'],
            'clientes' => $resultado['clientes']->map(fn($c) => [
                'id' => $c->id, 'nome' => $c->nome, 'tipo_pessoa' => $c->tipo ?? ($c->cnpj ? 'PJ' : 'PF'),
                'documento' => $c->cpf_cnpj ?? $c->cpf ?? $c->cnpj ?? '—', 'telefone' => $c->telefone ?? '—',
            ])->values(),
            'linked_cliente_id' => $conversa->linked_cliente_id,
        ]);
    }

    public function buscarClientes(int $id)
    {
        $service = new NexoDataJuriService();
        $clientes = $service->buscarClientes(request()->get('q', ''));
        return response()->json([
            'clientes' => $clientes->map(fn($c) => [
                'id' => $c->id, 'nome' => $c->nome, 'tipo_pessoa' => $c->tipo ?? ($c->cnpj ? 'PJ' : 'PF'),
                'documento' => $c->cpf_cnpj ?? $c->cpf ?? $c->cnpj ?? '—', 'telefone' => $c->telefone ?? '—',
            ])->values(),
            'total' => $clientes->count(),
        ]);
    }

    public function contextoDataJuri(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        if (!$conv->linked_cliente_id) return response()->json(['error' => 'Nenhum cliente vinculado.', 'linked_cliente_id' => null]);

        try {
            $ctx = (new NexoDataJuriService())->contextoDataJuri($conv->linked_cliente_id, request()->integer('processo_filtro_id') ?: null);
            $ctx['linked_processo_id'] = $conv->linked_processo_id;
            return response()->json($ctx);
        } catch (\Throwable $e) {
            Log::error('Erro contextoDataJuri', ['conversa_id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao carregar contexto DataJuri.']);
        }
    }

    public function processosCliente(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        if (!$conv->linked_cliente_id) return response()->json(['processos' => [], 'error' => 'Sem cliente vinculado']);

        $processos = (new NexoDataJuriService())->processosDoCliente($conv->linked_cliente_id);
        return response()->json([
            'processos' => $processos->map(fn($p) => [
                'id' => $p->id, 'datajuri_id' => $p->datajuri_id, 'numero' => $p->numero ?? $p->pasta ?? '—',
                'status' => $p->status, 'assunto' => $p->assunto ?? '—', 'natureza' => $p->natureza,
            ])->values(),
            'linked_processo_id' => $conv->linked_processo_id,
        ]);
    }

    public function linkProcesso(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        $pid = (int) request()->input('processo_id');
        if (!$pid) return response()->json(['success' => false, 'error' => 'processo_id obrigatório'], 422);

        $ok = (new NexoDataJuriService())->vincularProcesso($conv, $pid);
        if (!$ok) return response()->json(['success' => false, 'error' => 'Processo não encontrado ou não pertence ao cliente.'], 422);
        return response()->json(['success' => true, 'linked_processo_id' => $conv->linked_processo_id]);
    }

    public function unlinkProcesso(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        (new NexoDataJuriService())->desvincularProcesso($conv);
        return response()->json(['success' => true, 'linked_processo_id' => null]);
    }

    // ═══ Bug 3: BUSCA UNIFICADA para autocomplete ═══
    public function searchContacts(Request $request)
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) return response()->json(['leads' => [], 'clientes' => []]);

        $isNumeric = preg_match('/^\d+$/', $q);
        $isDoc = preg_match('/^[\d.\-\/]+$/', $q);

        $clienteQuery = Cliente::select('id', 'nome', 'cpf_cnpj', 'tipo', 'telefone');
        if ($isNumeric && strlen($q) <= 6) {
            $clienteQuery->where('id', $q);
        } elseif ($isDoc) {
            $cleanDoc = preg_replace('/\D/', '', $q);
            $clienteQuery->where(function ($qb) use ($cleanDoc) {
                $qb->where('cpf_cnpj', 'LIKE', "%{$cleanDoc}%")
                   ->orWhere('cpf', 'LIKE', "%{$cleanDoc}%")
                   ->orWhere('cnpj', 'LIKE', "%{$cleanDoc}%");
            });
        } else {
            $clienteQuery->where('nome', 'LIKE', "%{$q}%");
        }

        $clientes = $clienteQuery->limit(10)->get()->map(fn($c) => [
            'id' => $c->id, 'nome' => $c->nome, 'documento' => $c->cpf_cnpj ?? '-',
            'tipo' => $c->tipo ?? 'PF', 'telefone' => $c->telefone ?? '-',
        ]);

        $leadQuery = Lead::ativo()->select('id', 'nome', 'telefone', 'email', 'area_interesse', 'status');
        if ($isNumeric && strlen($q) <= 6) {
            $leadQuery->where('id', $q);
        } else {
            $leadQuery->where(function ($qb) use ($q) {
                $qb->where('nome', 'LIKE', "%{$q}%")
                   ->orWhere('telefone', 'LIKE', "%{$q}%")
                   ->orWhere('email', 'LIKE', "%{$q}%");
            });
        }

        $leads = $leadQuery->limit(10)->get()->map(fn($l) => [
            'id' => $l->id, 'nome' => $l->nome, 'telefone' => $l->telefone ?? '-',
            'area' => $l->area_interesse ?? '-', 'status' => $l->status ?? '-',
        ]);

        return response()->json(['clientes' => $clientes, 'leads' => $leads]);
    }

    // === CATEGORY ===
    public function assumirConversa(Request $request, int $id)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'coordenador', 'socio'])) {
            return response()->json(['error' => 'Sem permissao'], 403);
        }
        $conversation = WaConversation::findOrFail($id);
        $conversation->update([
            'bot_ativo' => false,
            'assigned_user_id' => $user->id,
            'assigned_at' => now(),
        ]);

        // Pausar automacao no SendPulse (com fallback por telefone)
        $sp = app(\App\Services\SendPulseWhatsAppService::class);
        $botPausado = false;

        if ($conversation->contact_id) {
            try {
                $botPausado = $sp->pausarAutomacao($conversation->contact_id);
                \Log::info('Bot control: automacao pausada no SendPulse', ['contact_id' => $conversation->contact_id]);
            } catch (\Throwable $e) {
                \Log::warning('Bot control: falha ao pausar automacao SendPulse por contact_id', ['error' => $e->getMessage()]);
            }
        }

        // Fallback: se não pausou por contact_id, tentar por telefone
        if (!$botPausado && $conversation->phone) {
            try {
                $resolvedContactId = $sp->pausarAutomacaoByPhone($conversation->phone);
                if ($resolvedContactId) {
                    $botPausado = true;
                    // Atualizar contact_id se estava vazio ou divergente
                    if (empty($conversation->contact_id) || $conversation->contact_id !== $resolvedContactId) {
                        $conversation->update(['contact_id' => $resolvedContactId]);
                        \Log::info('Bot control: contact_id atualizado via fallback telefone', [
                            'conv_id' => $id, 'old' => $conversation->contact_id, 'new' => $resolvedContactId,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Bot control: falha ao pausar automacao por telefone', ['error' => $e->getMessage()]);
            }
        }

        \Log::info('Bot control: conversa assumida manualmente', [
            'conv_id' => $id,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'bot_pausado_sendpulse' => $botPausado,
        ]);

        $msg = $botPausado
            ? 'Conversa assumida e bot pausado com sucesso.'
            : 'Conversa assumida localmente, mas o bot pode não ter sido pausado no SendPulse. Verifique se o cliente recebe mensagens automáticas.';

        return response()->json(['success' => true, 'message' => $msg, 'bot_pausado' => $botPausado]);
    }

    public function devolverAoBot(Request $request, int $id)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'coordenador', 'socio'])) {
            return response()->json(['error' => 'Sem permissao'], 403);
        }
        $conversation = WaConversation::findOrFail($id);
        $conversation->update([
            'bot_ativo' => true,
            'assigned_user_id' => null,
            'assigned_at' => null,
        ]);

        // Reativar automacao no SendPulse
        if ($conversation->contact_id) {
            try {
                $sp = app(\App\Services\SendPulseWhatsAppService::class);
                $sp->reativarAutomacao($conversation->contact_id);
                \Log::info('Bot control: automacao reativada no SendPulse', ['contact_id' => $conversation->contact_id]);
            } catch (\Throwable $e) {
                \Log::warning('Bot control: falha ao reativar automacao SendPulse', ['error' => $e->getMessage()]);
            }
        }

        \Log::info('Bot control: conversa devolvida ao bot', [
            'conv_id' => $id,
            'user_id' => $user->id,
        ]);
        return response()->json(['success' => true, 'message' => 'Conversa devolvida ao bot']);
    }

    public function updateCategory(Request $request, int $id)
    {
        $conv = WaConversation::findOrFail($id);
        $valid = ['ADV', 'PERITO', 'CORRESP', 'FORN', 'OUTRO', null];
        $category = $request->input('category');
        if (!in_array($category, $valid, true)) {
            return response()->json(['error' => 'Categoria invalida'], 422);
        }
        $conv->update(['category' => $category]);
        return response()->json(['ok' => true, 'category' => $category]);
    }

    // === TAGS ===
    /**
     * Tags — serve APENAS dados locais (v2 27/02/2026).
     * Zero chamadas API SendPulse. Tags sincronizadas via webhook/forceSync.
     */
    public function getTags(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        $tags = $conv->tags()->get(['wa_tags.id', 'wa_tags.name', 'wa_tags.color', 'wa_tags.provider_id']);
        $allTags = WaTag::orderBy('name')->get(['id', 'name', 'color', 'provider_id']);
        return response()->json([
            'tags' => $tags,
            'all_tags' => $allTags,
            'category' => $conv->category,
        ]);
    }

    public function updateTags(Request $request, int $id)
    {
        $conv = WaConversation::findOrFail($id);
        $tagIds = $request->input('tag_ids', []);

        $conv->tags()->sync($tagIds);

        try {
            $tagNames = WaTag::whereIn('id', $tagIds)->pluck('name')->toArray();
            $spService = app(\App\Services\SendPulseWhatsAppService::class);
            $spService->setContactTags($conv->contact_id, $tagNames);
        } catch (\Throwable $e) {
            Log::warning("NEXO updateTags sync SendPulse falhou: " . $e->getMessage());
        }

        return response()->json(['ok' => true, 'tags' => $conv->tags()->get(['wa_tags.id', 'wa_tags.name', 'wa_tags.color'])]);
    }

    /**
     * POST /nexo/atendimento/leads/{leadId}/promover-crm
     * Promove lead para CRM: cria crm_account (prospect) + oportunidade (open).
     */
    public function promoverLeadCrm(Request $request, int $leadId)
    {
        // Lock de linha para evitar promoção duplicada concorrente
        $lead = \App\Models\Lead::lockForUpdate()->findOrFail($leadId);

        if ($lead->crm_account_id) {
            return response()->json(['success' => false, 'error' => 'Lead ja promovido ao CRM'], 422);
        }

        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            // Criar crm_account como prospect
            $account = \App\Models\Crm\CrmAccount::create([
                'kind'          => 'prospect',
                'name'          => $lead->nome ?: 'Lead #'.$lead->id,
                'email'         => $lead->email,
                'phone_e164'    => $lead->telefone ? preg_replace('/\D/', '', $lead->telefone) : null,
                'lifecycle'     => 'onboarding',
                'owner_user_id' => auth()->id(),
                'last_touch_at' => now(),
                'notes'         => 'Promovido da Central de Leads. Area: '.($lead->area_interesse ?: 'N/A').'. Demanda: '.\Illuminate\Support\Str::limit($lead->resumo_demanda ?: '', 200),
            ]);

            // Buscar primeiro stage open
            $firstStage = \App\Models\Crm\CrmStage::orderBy('order')->first();

            // Criar oportunidade
            $opp = \App\Models\Crm\CrmOpportunity::create([
                'account_id'      => $account->id,
                'stage_id'        => $firstStage ? $firstStage->id : null,
                'title'           => $lead->area_interesse ? $lead->area_interesse.' - '.$lead->nome : 'Oportunidade - '.$lead->nome,
                'area'            => $lead->area_interesse,
                'type'            => 'new_business',
                'source'          => $lead->origem_canal ?: 'whatsapp',
                'lead_source'     => 'central_leads',
                'status'          => 'open',
                'owner_user_id'   => auth()->id(),
                'tipo_demanda'    => $lead->sub_area,
            ]);

            // Vincular lead ao account
            $lead->update([
                'crm_account_id' => $account->id,
                'status'         => 'convertido',
            ]);

            // Registrar evento CRM
            \App\Models\Crm\CrmEvent::create([
                'account_id'         => $account->id,
                'type'               => 'lead_promoted',
                'payload'            => ['lead_id' => $lead->id, 'opportunity_id' => $opp->id],
                'happened_at'        => now(),
                'created_by_user_id' => auth()->id(),
            ]);

            // Frente C: task primeiro contato
            try {
                (new \App\Services\Crm\CrmProactiveService())->criarTaskPrimeiroContato(
                    $account->id, $account->name, auth()->id()
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[CrmProactive] Falha task primeiro contato: ' . $e->getMessage());
            }
            \Illuminate\Support\Facades\DB::commit();

            return response()->json([
                'success'        => true,
                'account_id'     => $account->id,
                'opportunity_id' => $opp->id,
                'message'        => 'Lead promovido para CRM com sucesso',
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Erro ao promover lead para CRM', ['lead_id' => $leadId, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Erro interno: '.$e->getMessage()], 500);
        }
    }

    /**
     * Filtra mensagens de pesquisa QA para usuarios nao-admin.
     * Admins/socios veem tudo; demais nao veem o fluxo QA.
     */
    private function filterQaMessages($messages)
    {
        $user = auth()->user();
        $isPrivileged = (($user->role ?? '') === 'admin');

        if ($isPrivileged) {
            return $messages;
        }

        $qaChainId = '699afac558acd07d14042143';

        // Identificar IDs de mensagens outbound do flow QA (pelo chain.id no raw_payload)
        $qaOutboundIds = $messages->filter(function ($m) use ($qaChainId) {
            if ($m->direction == 2 && $m->raw_payload) {
                $payload = is_string($m->raw_payload) ? json_decode($m->raw_payload, true) : (array) $m->raw_payload;
                return isset($payload['chain']['id']) && $payload['chain']['id'] === $qaChainId;
            }
            return false;
        })->pluck('id')->toArray();

        if (empty($qaOutboundIds)) {
            return $messages;
        }

        // Pegar timestamps do primeiro e ultimo outbound QA para delimitar a janela
        $qaMessages = $messages->whereIn('id', $qaOutboundIds);
        $qaStart = $qaMessages->min('sent_at');
        $qaEnd = $qaMessages->max('sent_at');

        // Expandir janela: respostas inbound podem vir ate 10min depois
        $qaEndExpanded = \Carbon\Carbon::parse($qaEnd)->addMinutes(10);

        // Filtrar: remover outbound QA e inbound dentro da janela QA
        return $messages->filter(function ($m) use ($qaOutboundIds, $qaStart, $qaEndExpanded) {
            // Sempre remover outbound QA
            if (in_array($m->id, $qaOutboundIds)) {
                return false;
            }
            // Remover inbound na janela QA (respostas de botao, estrelas, texto de feedback)
            if ($m->direction == 1 && $m->sent_at >= $qaStart && $m->sent_at <= $qaEndExpanded) {
                // Verificar se eh resposta QA: button, interactive com estrela, ou texto curto na janela
                if (in_array($m->message_type, ['button', 'interactive'])) {
                    return false;
                }
                // Texto que parece feedback QA (logo apos perguntas)
                if ($m->message_type === 'text' && $m->sent_at <= $qaEndExpanded) {
                    return false;
                }
            }
            return true;
        })->values();
    }

}
