<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmEvent;
use App\Models\Crm\CrmActivity;
use App\Models\User;
use App\Services\Crm\CrmIdentityResolver;
use App\Services\Crm\CrmOpportunityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\Crm\CrmSegmentationService;
use App\Services\Crm\CrmCadenceService;
use App\Services\Crm\CrmHealthScoreService;
use App\Models\Crm\CrmDocument;
use Illuminate\Support\Facades\Storage;

class CrmAccountController extends Controller
{
    /**
     * Cliente 360 — Perfil enriquecido.
     */
    public function show(int $id)
    {
        $account = CrmAccount::with([
            'owner',
            'identities',
            'opportunities' => fn($q) => $q->with('stage', 'owner')->latest(),
            'activities'    => fn($q) => $q->latest()->limit(20),
        ])->findOrFail($id);

        // Timeline: events + activities merged
        $events     = CrmEvent::where('account_id', $id)->latest('happened_at')->limit(30)->get();
        $activities = CrmActivity::where('account_id', $id)->latest('created_at')->limit(30)->get();

        $timeline = collect()
            ->merge($events->map(fn($e) => [
                'type'    => 'event',
                'subtype' => $e->type,
                'title'   => $this->eventTitle($e),
                'payload' => $e->payload,
                'date'    => $e->happened_at,
                'user'    => $e->createdBy?->name,
            ]))
            ->merge($activities->map(fn($a) => [
                'type'    => 'activity',
                'subtype' => $a->type,
                'title'   => $a->title,
                'body'    => $a->body,
                'date'    => $a->created_at,
                'done'    => $a->isDone(),
                'user'    => $a->createdBy?->name,
            ]))
            ->sortByDesc('date')
            ->values()
            ->take(50);

        // DataJuri context enriquecido
        $djContext = $this->loadDataJuriContext($account);

        // Comunicação: WhatsApp + Tickets
        $commContext = $this->loadCommunicationContext($account, $djContext);

        $users = User::orderBy('name')->get(['id', 'name']);

        // Segmentação IA (cache 7 dias)
        $segmentation = null;
        try {
            $segService = app(CrmSegmentationService::class);
            $segmentation = $segService->segmentar($account->id);
        } catch (\Exception $e) {
            Log::warning('[CRM] Segmentação falhou account #' . $account->id . ': ' . $e->getMessage());
        }

        // Aging financeiro
        $finSummary = $this->buildFinancialSummary($djContext);

        $documents = CrmDocument::where('account_id', $id)->with('uploadedBy')->latest()->get();
        $docCategorias = CrmDocument::categorias();

        $serviceRequests = \App\Models\Crm\CrmServiceRequest::where('account_id', $id)
            ->with(['requestedBy', 'assignedTo'])
            ->latest()
            ->get();
        $srCategorias = \App\Models\Crm\CrmServiceRequest::categorias();

        return view('crm.accounts.show', compact(
            'account', 'timeline', 'djContext', 'commContext', 'users', 'segmentation', 'finSummary', 'documents', 'docCategorias', 'serviceRequests', 'srCategorias'
        ));
    }

    /**
     * Atualizar campos gerenciais do account (AJAX).
     */
    public function update(Request $request, int $id)
    {
        $account = CrmAccount::findOrFail($id);

        $validated = $request->validate([
            'owner_user_id' => 'nullable|exists:users,id',
            'lifecycle'     => 'nullable|in:onboarding,ativo,adormecido,arquivado,risco',
            'health_score'  => 'nullable|integer|min:0|max:100',
            'next_touch_at' => 'nullable|date',
            'notes'         => 'nullable|string|max:5000',
            'tags'          => 'nullable|string|max:1000',
        ]);

        $before = $account->only(array_keys($validated));
        $account->update($validated);
        $after = $account->only(array_keys($validated));

        $changes = [];
        foreach ($before as $key => $oldVal) {
            $newVal = $after[$key] ?? null;
            if ((string) $oldVal !== (string) $newVal) {
                $changes[$key] = ['from' => $oldVal, 'to' => $newVal];
            }
        }

        if (!empty($changes)) {
            CrmEvent::create([
                'account_id'         => $id,
                'type'               => 'account_updated',
                'payload'            => $changes,
                'happened_at'        => now(),
                'created_by_user_id' => auth()->id(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Criar nova oportunidade a partir do account.
     */
    public function createOpportunity(Request $request, int $id, CrmOpportunityService $service)
    {
        $request->validate([
            'title'  => 'nullable|string|max:255',
            'type'   => 'required|in:aquisicao,carteira',
            'area'   => 'nullable|string|max:100',
            'source' => 'nullable|string|max:100',
        ]);

        $opp = $service->createOrGetOpen(
            $id,
            $request->source ?? 'manual',
            $request->type,
            $request->area,
            $request->title,
            auth()->id()
        );

        // Aplicar cadência padrão
        try {
            app(CrmCadenceService::class)->aplicarCadencia($opp);
        } catch (\Exception $e) {
            \Log::warning('[CRM] Cadência falhou opp #' . $opp->id . ': ' . $e->getMessage());
        }

        return redirect()->route('crm.opportunities.show', $opp->id)
            ->with('success', 'Oportunidade criada.');
    }

    /**
     * Adicionar atividade/prontuário ao account (AJAX) — CO002 compliant.
     */
    public function storeActivity(Request $request, int $id)
    {
        $rules = [
            'type'          => 'required|in:call,meeting,whatsapp,note,email,visit',
            'purpose'       => 'required|in:acompanhamento,comercial,cobranca,orientacao,documental,agendamento,retorno,registro_interno,relacionamento,assinatura,estrategica',
            'title'         => 'required|string|max:255',
            'body'          => 'nullable|string|max:5000',
            'decisions'     => 'nullable|string|max:3000',
            'pending_items' => 'nullable|string|max:3000',
            'due_at'        => 'nullable|date',
        ];
        if ($request->input('type') === 'visit') {
            $rules = array_merge($rules, [
                'visit_arrival_time'   => 'required|date_format:H:i',
                'visit_departure_time' => 'required|date_format:H:i|after:visit_arrival_time',
                'visit_transport'      => 'required|in:carro_proprio,aplicativo,taxi,transporte_publico,a_pe,moto,outro',
                'visit_location'       => 'nullable|string|max:500',
                'visit_attendees'      => 'nullable|string|max:1000',
                'visit_objective'      => 'required|in:acompanhamento,relacionamento,prospeccao,cobranca,entrega_docs,assinatura,reuniao_estrategica,outro',
                'visit_receptivity'    => 'nullable|in:positiva,neutra,negativa',
                'visit_next_contact'   => 'nullable|date',
            ]);
        }
        $request->validate($rules);
        $data = [
            'account_id'         => $id,
            'type'               => $request->type,
            'purpose'            => $request->purpose,
            'title'              => $request->title,
            'body'               => $request->body,
            'decisions'          => $request->decisions,
            'pending_items'      => $request->pending_items,
            'due_at'             => $request->due_at,
            'created_by_user_id' => auth()->id(),
        ];
        if ($request->type === 'visit') {
            $data = array_merge($data, $request->only([
                'visit_arrival_time', 'visit_departure_time', 'visit_transport',
                'visit_location', 'visit_attendees', 'visit_objective',
                'visit_receptivity', 'visit_next_contact',
            ]));
        }
        $activity = CrmActivity::create($data);

        CrmAccount::where('id', $id)->update(['last_touch_at' => now()]);

        // Recalcular health score automaticamente
        try {
            app(CrmHealthScoreService::class)->recalculate($id);
        } catch (\Exception $e) {
            Log::warning("[CRM] HealthScore falhou account #{$id}: {$e->getMessage()}");
        }

        return response()->json(['ok' => true, 'id' => $activity->id]);
    }

    /**
     * Gerar PDF do relatorio de visita presencial.
     */
    public function generateVisitPdf(int $id, int $activityId)
    {
        $account = CrmAccount::findOrFail($id);
        $activity = CrmActivity::where('id', $activityId)
            ->where('account_id', $id)
            ->where('type', 'visit')
            ->firstOrFail();

        $transportLabels = [
            'carro_proprio' => 'Carro proprio', 'aplicativo' => 'Aplicativo (Uber/99)',
            'taxi' => 'Taxi', 'transporte_publico' => 'Transporte publico',
            'a_pe' => 'A pe', 'moto' => 'Moto', 'outro' => 'Outro',
        ];
        $objectiveLabels = [
            'acompanhamento' => 'Acompanhamento processual', 'relacionamento' => 'Relacionamento',
            'prospeccao' => 'Prospeccao comercial', 'cobranca' => 'Cobranca',
            'entrega_docs' => 'Entrega de documentos', 'assinatura' => 'Assinatura de contrato',
            'reuniao_estrategica' => 'Reuniao estrategica', 'outro' => 'Outro',
        ];
        $receptivityLabels = ['positiva' => 'Positiva', 'neutra' => 'Neutra', 'negativa' => 'Negativa'];

        $html = view('crm.visit-pdf', compact('account', 'activity', 'transportLabels', 'objectiveLabels', 'receptivityLabels'))->render();

        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Visita_' . str_replace(' ', '_', $account->name) . '_' . $activity->created_at->format('d-m-Y') . '.pdf';
        return response($dompdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Concluir atividade com status e anotação (AJAX).
     */
    public function completeActivity(Request $request, int $id, int $activityId)
    {
        $request->validate([
            'resolution_status' => 'required|in:procedente,improcedente,parcial,cancelada',
            'resolution_notes'  => 'required|string|max:3000',
        ]);

        $activity = CrmActivity::where('id', $activityId)
            ->where('account_id', $id)
            ->whereNull('done_at')
            ->firstOrFail();

        $activity->update([
            'done_at'              => now(),
            'resolution_status'    => $request->resolution_status,
            'resolution_notes'     => $request->resolution_notes,
            'completed_by_user_id' => auth()->id(),
        ]);

        // Recalcular health score
        try {
            app(CrmHealthScoreService::class)->recalculate($id);
        } catch (\Exception $e) {
            Log::warning("[CRM] HealthScore falhou account #{$id}: {$e->getMessage()}");
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Transferir responsável do account (admin/coordenador).
     */
    public function transferOwner(Request $request, int $id)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'coordenador', 'socio'])) {
            return response()->json(['error' => 'Sem permissão'], 403);
        }

        $request->validate([
            'new_owner_id' => 'required|exists:users,id',
            'reason'       => 'nullable|string|max:500',
        ]);

        $account = CrmAccount::findOrFail($id);
        $oldOwnerId = $account->owner_user_id;

        if ((int) $request->new_owner_id === $oldOwnerId) {
            return response()->json(['error' => 'Mesmo responsável'], 422);
        }

        $account->update(['owner_user_id' => $request->new_owner_id]);

        $oldOwnerName = User::find($oldOwnerId)?->name ?? 'N/A';
        $newOwnerName = User::find($request->new_owner_id)?->name ?? 'N/A';

        CrmEvent::create([
            'account_id'         => $id,
            'type'               => 'owner_transferred',
            'payload'            => [
                'from_user_id'   => $oldOwnerId,
                'from_user_name' => $oldOwnerName,
                'to_user_id'     => (int) $request->new_owner_id,
                'to_user_name'   => $newOwnerName,
                'reason'         => $request->reason,
            ],
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);

        return response()->json(['ok' => true, 'new_owner' => $newOwnerName]);
    }

    /**
     * Arquivar account.
     */
    public function archive(Request $request, int $id)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $account = CrmAccount::findOrFail($id);
        $oldLifecycle = $account->lifecycle;
        $account->update(['lifecycle' => 'arquivado']);

        CrmEvent::create([
            'account_id'         => $id,
            'type'               => 'account_archived',
            'payload'            => [
                'from_lifecycle' => $oldLifecycle,
                'reason'         => $request->reason,
            ],
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Desarquivar account (admin).
     */
    public function unarchive(int $id)
    {
        $user = auth()->user();
        if (!in_array($user->role, ['admin', 'coordenador', 'socio'])) {
            return response()->json(['error' => 'Sem permissão'], 403);
        }

        $account = CrmAccount::findOrFail($id);
        $account->update(['lifecycle' => 'ativo']);

        CrmEvent::create([
            'account_id'         => $id,
            'type'               => 'account_unarchived',
            'payload'            => ['restored_by' => $user->name],
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    // =========================================================================
    // PRIVATE — Data Loaders
    // =========================================================================

    /**
     * Carrega contexto DataJuri enriquecido: dados pessoais, processos,
     * contratos, financeiro (movimentos + contas a receber).
     */
    private function loadDataJuriContext(CrmAccount $account): array
    {
        $ctx = [
            'available'        => false,
            'cliente'          => null,
            'processos'        => [],
            'contratos'        => [],
            'contas_receber'   => [],
            'receita_total'    => 0,
            'ultimo_movimento' => null,
            'financeiro_cache' => [],
        ];

        if (!$account->datajuri_pessoa_id) {
            return $ctx;
        }

        try {
            $djId = $account->datajuri_pessoa_id;

            // 1. Dados pessoais do cliente
            $ctx['cliente'] = DB::table('clientes')
                ->where('datajuri_id', $djId)
                ->first([
                    'id', 'nome', 'cpf_cnpj', 'tipo', 'email', 'outro_email',
                    'telefone', 'celular', 'telefone_normalizado',
                    'endereco_cidade', 'endereco_estado', 'endereco_rua',
                    'endereco_numero', 'endereco_bairro', 'endereco_cep',
                    'profissao', 'data_nascimento', 'sexo', 'estado_civil',
                    'nacionalidade', 'rg', 'nome_fantasia', 'status_pessoa',
                    'proprietario_nome', 'data_primeiro_contato', 'data_ultimo_contato',
                    'total_contas_receber', 'total_contas_vencidas',
                    'valor_contas_abertas', 'total_processos', 'total_contratos',
                ]);

            // 2. Processos via cliente_datajuri_id (CORRETO, nunca cliente_id)
            $ctx['processos'] = DB::table('processos')
                ->where('cliente_datajuri_id', $djId)
                ->orderByDesc('data_abertura')
                ->limit(20)
                ->get([
                    'id', 'pasta', 'numero', 'status', 'tipo_acao',
                    'adverso_nome', 'data_abertura', 'data_encerramento',
                    'proprietario_nome',
                ])
                ->toArray();

            // 3. Contratos via contratante_id_datajuri
            $ctx['contratos'] = DB::table('contratos')
                ->where('contratante_id_datajuri', $djId)
                ->orderByDesc('data_assinatura')
                ->limit(10)
                ->get([
                    'id', 'numero', 'valor', 'data_assinatura',
                    'proprietario_nome',
                ])
                ->toArray();

            // 4. Receita total + último movimento (movimentos por pessoa_id_datajuri)
            $ctx['receita_total'] = DB::table('movimentos')
                ->where('pessoa_id_datajuri', $djId)
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('valor', '>', 0)
                ->sum('valor') ?? 0;

            $ctx['ultimo_movimento'] = DB::table('movimentos')
                ->where('pessoa_id_datajuri', $djId)
                ->orderByDesc('data')
                ->first(['data', 'valor', 'descricao', 'classificacao']);

            // 5. Contas a receber detalhadas
            $ctx['contas_receber'] = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $djId)
                ->orderByDesc('data_vencimento')
                ->limit(20)
                ->get([
                    'id', 'descricao', 'valor', 'data_vencimento',
                    'data_pagamento', 'status', 'tipo',
                ])
                ->toArray();

            // 6. Financeiro cache (tabela clientes — pré-calculado pela sync)
            $ctx['financeiro_cache'] = $account->datajuriFinanceiro();

            $ctx['available'] = true;

        } catch (\Exception $e) {
            Log::warning("[CRM] DataJuri context falhou account #{$account->id}: {$e->getMessage()}");
        }

        return $ctx;
    }

    /**
     * Carrega contexto de comunicação: WhatsApp + Tickets NEXO.
     */
    private function loadCommunicationContext(CrmAccount $account, array $djContext): array
    {
        $ctx = [
            'whatsapp'    => null,
            'tickets'     => [],
            'has_wa'      => false,
            'has_tickets' => false,
        ];

        try {
            // Telefone para match — prioridade: CRM phone_e164, depois clientes.celular/telefone_normalizado
            $phones = [];
            if ($account->phone_e164) {
                $phones[] = $account->phone_e164;
            }
            if ($djContext['cliente']) {
                $cli = $djContext['cliente'];
                if (!empty($cli->telefone_normalizado)) {
                    $phones[] = $cli->telefone_normalizado;
                }
                if (!empty($cli->celular)) {
                    $digits = preg_replace('/\D/', '', $cli->celular);
                    if (strlen($digits) >= 10 && !str_starts_with($digits, '55')) {
                        $digits = '55' . $digits;
                    }
                    $phones[] = $digits;
                }
            }

            // WhatsApp — match por últimos 9 dígitos do telefone
            if (!empty($phones)) {
                foreach ($phones as $phone) {
                    $last9 = substr(preg_replace('/\D/', '', $phone), -9);
                    if (strlen($last9) < 8) {
                        continue;
                    }

                    $wa = DB::table('wa_conversations')
                        ->where('phone', 'like', '%' . $last9)
                        ->orderByDesc('last_message_at')
                        ->first(['id', 'phone', 'name', 'status', 'last_message_at', 'assigned_user_id', 'bot_ativo', 'priority']);

                    if ($wa) {
                        $ctx['whatsapp'] = $wa;
                        $ctx['has_wa']   = true;

                        // Últimas 20 mensagens inline
                        $ctx['wa_messages'] = DB::table('wa_messages')
                            ->where('conversation_id', $wa->id)
                            ->orderByDesc('created_at')
                            ->limit(20)
                            ->get(['id', 'direction', 'body', 'type', 'status', 'created_at'])
                            ->reverse()
                            ->values()
                            ->toArray();

                        break;
                    }
                }
            }

            // Tickets NEXO — match por datajuri_id ou telefone
            $djId = $account->datajuri_pessoa_id;

            if ($djId) {
                $tickets = DB::table('nexo_tickets')
                    ->where('datajuri_id', $djId)
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get(['id', 'protocolo', 'assunto', 'status', 'prioridade', 'tipo', 'created_at', 'resolvido_at']);
            } elseif (!empty($phones)) {
                $last9   = substr(preg_replace('/\D/', '', $phones[0]), -9);
                $tickets = DB::table('nexo_tickets')
                    ->where('telefone', 'like', '%' . $last9)
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get(['id', 'protocolo', 'assunto', 'status', 'prioridade', 'tipo', 'created_at', 'resolvido_at']);
            } else {
                $tickets = collect();
            }

            $ctx['tickets']     = $tickets->toArray();
            $ctx['has_tickets'] = count($ctx['tickets']) > 0;

        } catch (\Exception $e) {
            Log::warning("[CRM] Communication context falhou account #{$account->id}: {$e->getMessage()}");
        }

        return $ctx;
    }

    private function buildFinancialSummary(array $djContext): array
    {
        $contas = collect($djContext['contas_receber'] ?? []);
        $abertas = $contas->filter(fn($c) => !in_array($c->status ?? '', ['Concluído', 'Concluido', 'Excluido', 'Excluído']));
        $pagas = $contas->filter(fn($c) => in_array($c->status ?? '', ['Concluído', 'Concluido']));

        $hoje = date('Y-m-d');
        $vencidas = $abertas->filter(fn($c) => ($c->data_vencimento ?? null) && $c->data_vencimento < $hoje);
        $aVencer30 = $abertas->filter(fn($c) => ($c->data_vencimento ?? null) && $c->data_vencimento >= $hoje && $c->data_vencimento <= date('Y-m-d', strtotime('+30 days')));
        $aVencer60 = $abertas->filter(fn($c) => ($c->data_vencimento ?? null) && $c->data_vencimento > date('Y-m-d', strtotime('+30 days')) && $c->data_vencimento <= date('Y-m-d', strtotime('+60 days')));
        $aVencer90p = $abertas->filter(fn($c) => ($c->data_vencimento ?? null) && $c->data_vencimento > date('Y-m-d', strtotime('+60 days')));

        return [
            'total_recebido'   => $pagas->sum(fn($c) => (float)($c->valor ?? 0)),
            'total_aberto'     => $abertas->sum(fn($c) => (float)($c->valor ?? 0)),
            'total_vencido'    => $vencidas->sum(fn($c) => (float)($c->valor ?? 0)),
            'qty_vencidas'     => $vencidas->count(),
            'qty_abertas'      => $abertas->count(),
            'aging' => [
                ['label' => 'Vencidas',    'valor' => $vencidas->sum(fn($c) => (float)($c->valor ?? 0)),  'qty' => $vencidas->count(),  'cor' => '#EF4444'],
                ['label' => '0-30 dias',   'valor' => $aVencer30->sum(fn($c) => (float)($c->valor ?? 0)), 'qty' => $aVencer30->count(), 'cor' => '#F59E0B'],
                ['label' => '31-60 dias',  'valor' => $aVencer60->sum(fn($c) => (float)($c->valor ?? 0)), 'qty' => $aVencer60->count(), 'cor' => '#3B82F6'],
                ['label' => '61+ dias',    'valor' => $aVencer90p->sum(fn($c) => (float)($c->valor ?? 0)),'qty' => $aVencer90p->count(),'cor' => '#6B7280'],
            ],
        ];
    }

    private function describeAccountUpdate(array $p): string
    {
        if (empty($p)) return 'Dados atualizados';

        // Novo formato: {campo: {from: x, to: y}}
        $labels = [
            'owner_user_id' => 'Responsável',
            'lifecycle'     => 'Lifecycle',
            'health_score'  => 'Saúde',
            'notes'         => 'Anotações',
            'tags'          => 'Tags',
            'next_touch_at' => 'Próximo contato',
        ];

        $parts = [];
        foreach ($p as $key => $change) {
            if (is_array($change) && isset($change['from'], $change['to'])) {
                $label = $labels[$key] ?? $key;
                $parts[] = $label . ': ' . ($change['from'] ?: '—') . ' → ' . ($change['to'] ?: '—');
            }
        }

        return $parts ? 'Atualizado: ' . implode(', ', $parts) : 'Dados atualizados';
    }

    private function eventTitle(CrmEvent $event): string
    {
        $p = $event->payload ?? [];
        return match ($event->type) {
            'opportunity_created'        => 'Nova oportunidade criada',
            'opportunity_imported'       => 'Oportunidade importada (ESPO)',
            'opportunity_lost'           => 'Oportunidade perdida' . (isset($p['reason']) ? ': ' . $p['reason'] : ''),
            'stage_changed'              => 'Estágio alterado: ' . ($p['from_stage'] ?? '?') . ' → ' . ($p['to_stage'] ?? '?'),
            'lead_qualified'             => 'Lead qualificado',
            'lead_status_changed'        => 'Status do lead: ' . ($p['from'] ?? '?') . ' → ' . ($p['to'] ?? '?'),
            'nexo_opened_chat'           => 'Chat WhatsApp aberto (NEXO)',
            'account_created_from_lead'  => 'Conta criada a partir de lead',
            'account_updated'            => $this->describeAccountUpdate($p),
            'account_archived'           => 'Conta arquivada',
            'health_score_changed'       => 'Saúde: ' . ($p['from'] ?? '?') . ' → ' . ($p['to'] ?? '?'),
            'segment_changed'            => 'Segmentação: ' . ($p['from'] ?? '?') . ' → ' . ($p['to'] ?? '?'),
            'document_uploaded'          => 'Documento enviado: ' . ($p['name'] ?? ''),
            'document_deleted'           => 'Documento removido: ' . ($p['name'] ?? ''),
            'service_request_created'    => 'Solicitação criada: ' . ($p['subject'] ?? '#' . ($p['sr_id'] ?? '')),
            'service_request_updated'    => 'Solicitação atualizada' . (isset($p['from_status']) ? ' (' . $p['from_status'] . ' → ' . ($p['status'] ?? '?') . ')' : ''),
            default                      => ucfirst(str_replace('_', ' ', $event->type)),
        };
    }


    /**
     * POST /crm/accounts/{id}/documents
     */
    public function uploadDocument(Request $request, int $id)
    {
        $request->validate([
            'file'     => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:20480',
            'category' => 'required|string|max:50',
            'notes'    => 'nullable|string|max:500',
        ]);

        $account = \App\Models\Crm\CrmAccount::findOrFail($id);
        $file = $request->file('file');

        $normalizedName = CrmDocument::normalizarNome(
            $file->getClientOriginalName(),
            $request->category,
            $id
        );

        $path = $file->storeAs(
            'crm/documents/' . $id,
            $normalizedName,
            'public'
        );

        $doc = CrmDocument::create([
            'account_id'         => $id,
            'uploaded_by_user_id'=> auth()->id(),
            'category'           => $request->category,
            'original_name'      => $file->getClientOriginalName(),
            'normalized_name'    => $normalizedName,
            'disk_path'          => $path,
            'mime_type'          => $file->getMimeType(),
            'size_bytes'         => $file->getSize(),
            'notes'              => $request->notes,
        ]);

        // Registrar evento CRM
        \App\Models\Crm\CrmEvent::create([
            'account_id'         => $id,
            'type'               => 'document_uploaded',
            'payload'            => ['document_id' => $doc->id, 'name' => $normalizedName, 'category' => $request->category],
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);

        return back()->with('success', 'Documento enviado com sucesso.')->withFragment('documentos');
    }

    /**
     * DELETE /crm/accounts/{id}/documents/{docId}
     */
    public function deleteDocument(int $id, int $docId)
    {
        $doc = CrmDocument::where('account_id', $id)->findOrFail($docId);

        Storage::disk('public')->delete($doc->disk_path);

        \App\Models\Crm\CrmEvent::create([
            'account_id'         => $id,
            'type'               => 'document_deleted',
            'payload'            => ['name' => $doc->normalized_name, 'category' => $doc->category],
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);

        $doc->delete();

        return back()->with('success', 'Documento removido.')->withFragment('documentos');
    }

}
