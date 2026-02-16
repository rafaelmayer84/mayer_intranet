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
        if ($request->filled('tipo')) {
            switch ($request->tipo) {
                case 'lead': $query->whereNotNull('linked_lead_id'); break;
                case 'cliente': $query->whereNotNull('linked_cliente_id'); break;
                case 'indefinido': $query->whereNull('linked_lead_id')->whereNull('linked_cliente_id'); break;
            }
        }

        return response()->json($query->paginate(30));
    }

    public function conversa(int $id)
    {
        $conversation = WaConversation::with('assignedUser')->findOrFail($id);

        // Bug 1 fix: zerar unread ao abrir conversa
        if ($conversation->unread_count > 0) {
            $conversation->unread_count = 0;
            $conversation->save();
        }

        $messages = WaMessage::where('conversation_id', $id)->orderBy('sent_at', 'asc')->get();
        return response()->json(['conversation' => $conversation, 'messages' => $messages]);
    }

    public function enviarMensagem(Request $request, int $id)
    {
        $request->validate(['text' => 'required|string|max:4096']);
        $conversation = WaConversation::findOrFail($id);

        try {
            $spService = app(SendPulseWhatsAppService::class);

            // Tenta envio por contact_id (com bot_id). Se falhar, tenta por telefone.
            $result = $spService->sendMessage($conversation->contact_id, $request->text);

            if (!($result['success'] ?? false) && $conversation->phone) {
                Log::info('NEXO: fallback sendMessageByPhone', ['conversa_id' => $id, 'phone' => $conversation->phone]);
                $result = $spService->sendMessageByPhone($conversation->phone, $request->text);
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
                'sent_at' => now(),
            ]);

            if (!$conversation->first_response_at) $conversation->first_response_at = now();
            $conversation->last_message_at = now();
            $conversation->save();

            WaEvent::log('send_message', $id, ['text_length' => strlen($request->text), 'delivered' => true]);
            return response()->json(['success' => true, 'message' => $message]);
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar mensagem NEXO', ['conversa_id' => $id, 'error' => $e->getMessage()]);
            WaEvent::log('error', $id, ['action' => 'send_message', 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => 'Falha ao enviar mensagem.'], 500);
        }
    }

    public function pollMessages(int $id)
    {
        $conversation = WaConversation::findOrFail($id);
        $cid = $conversation->id;
        $uid = auth()->id() ?? 0;

        $throttleKey = "nexo_poll_throttle_{$uid}_{$cid}";
        if (Cache::has($throttleKey)) {
            $msgs = WaMessage::where('conversation_id', $cid)->orderBy('sent_at', 'asc')->get();
            return response()->json(['throttled' => true, 'new_count' => 0, 'messages' => $msgs]);
        }

        $lock = Cache::lock("nexo_poll_{$cid}", 10);
        if (!$lock->get()) {
            $msgs = WaMessage::where('conversation_id', $cid)->orderBy('sent_at', 'asc')->get();
            return response()->json(['throttled' => true, 'new_count' => 0, 'messages' => $msgs]);
        }

        try {
            app(NexoConversationSyncService::class)->syncConversation($conversation);
            Cache::put($throttleKey, true, 8);
            $msgs = WaMessage::where('conversation_id', $cid)->orderBy('sent_at', 'asc')->get();
            return response()->json(['throttled' => false, 'new_count' => $msgs->count(), 'messages' => $msgs]);
        } catch (\Throwable $e) {
            Log::warning('Poll sync error', ['conversa_id' => $cid, 'error' => $e->getMessage()]);
            $msgs = WaMessage::where('conversation_id', $cid)->orderBy('sent_at', 'asc')->get();
            return response()->json(['throttled' => false, 'new_count' => $msgs->count(), 'messages' => $msgs]);
        } finally {
            $lock->release();
        }
    }

    public function assignUser(Request $request, int $id)
    {
        $c = WaConversation::findOrFail($id);
        $oldUserId = $c->assigned_user_id;
        $newUserId = $request->input('user_id') ?: null;

        $c->assigned_user_id = $newUserId;
        $c->save();

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
        $c->save();
        return response()->json(['success' => true, 'status' => $c->status]);
    }

    public function linkLead(Request $request, int $id)
    {
        $request->validate(['lead_id' => 'required|integer|exists:leads,id']);
        $c = WaConversation::findOrFail($id);
        $c->linked_lead_id = $request->lead_id;
        $c->save();
        return response()->json(['success' => true]);
    }

    public function linkCliente(Request $request, int $id)
    {
        $request->validate(['cliente_id' => 'required|integer|exists:clientes,id']);
        $c = WaConversation::findOrFail($id);
        $c->linked_cliente_id = $request->cliente_id;
        $c->save();
        return response()->json(['success' => true]);
    }

    /**
     * DELETE /nexo/atendimento/conversas/{id}/unlink-lead
     * Remove vinculação de lead da conversa.
     */
    public function unlinkLead(int $id)
    {
        $c = WaConversation::findOrFail($id);
        $c->linked_lead_id = null;
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
        $c->save();
        return response()->json(['success' => true]);
    }

    public function contexto360(int $id)
    {
        $conv = WaConversation::findOrFail($id);
        $ctx = ['link_type' => $conv->link_type, 'lead' => null, 'cliente' => null];

        try {
            if ($conv->linked_lead_id && $conv->lead) $ctx['lead'] = $conv->lead->toArray();
        } catch (\Throwable $e) { $ctx['lead'] = ['_error' => $e->getMessage()]; }

        try {
            if ($conv->linked_cliente_id && $conv->cliente) {
                $cl = $conv->cliente;
                $d = $cl->toArray();
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

        $leadQuery = Lead::select('id', 'nome', 'telefone', 'email', 'area_interesse', 'status');
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
    public function getTags(int $id)
    {
        $conv = WaConversation::findOrFail($id);

        try {
            $spService = app(\App\Services\SendPulseWhatsAppService::class);
            $contactInfo = $spService->getContactInfo($conv->contact_id);
            $spTags = data_get($contactInfo, 'tags', []);

            if (is_array($spTags) && count($spTags) > 0) {
                $localTagIds = [];
                foreach ($spTags as $spTag) {
                    $tagName = is_array($spTag) ? ($spTag['name'] ?? null) : (is_string($spTag) ? $spTag : null);
                    $tagId = is_array($spTag) ? ($spTag['id'] ?? null) : null;
                    if (!$tagName) continue;

                    $waTag = WaTag::updateOrCreate(
                        ['provider_id' => $tagId ?? md5($tagName)],
                        ['name' => $tagName]
                    );
                    $localTagIds[] = $waTag->id;
                }
                $conv->tags()->sync($localTagIds);
            }
        } catch (\Throwable $e) {
            Log::warning("NEXO getTags sync falhou: " . $e->getMessage());
        }

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
}
