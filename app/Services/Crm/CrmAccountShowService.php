<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmAccountDataGate;
use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmAdminProcess;
use App\Models\Crm\CrmDocument;
use App\Models\Crm\CrmEvent;
use App\Models\Crm\CrmInadimplenciaDecisao;
use App\Models\Crm\CrmServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orquestra toda a preparação de dados da ficha 360 de uma conta CRM.
 *
 * O controller CrmAccountController::show() delega para cá, ficando como mero
 * delegador HTTP. Isso permite:
 *   - Testar preparação de dados sem request HTTP
 *   - Reutilizar em CLI/jobs
 *   - Manter show() enxuto
 *
 * Preserva comportamento exato do controller anterior (copy pase fiel das
 * seções privadas loadDataJuriContext, loadCommunicationContext,
 * buildFinancialSummary, eventTitle, describeAccountUpdate).
 */
class CrmAccountShowService
{
    /**
     * Prepara tudo que a ficha de conta precisa.
     *
     * Retorna:
     *   - ['view' => string, 'data' => array] — pronto para passar a view()
     *
     * A view pode ser 'crm.accounts.show' OU uma variante de gate
     * ('crm.accounts.gate-bloqueio', 'crm.accounts.gate-ciente').
     */
    public function prepare(int $id, ?User $user): array
    {
        $account = CrmAccount::with([
            'owner',
            'identities',
            'opportunities' => fn($q) => $q->with('stage', 'owner')->latest(),
            'activities'    => fn($q) => $q->latest()->limit(20),
        ])->findOrFail($id);

        // Gate de qualidade de dados: se há gate(s) status=aberto, exige revisão
        // obrigatória antes de liberar a conta. Admin passa direto.
        $gatesAbertos = CrmAccountDataGate::where('account_id', $id)
            ->where('status', CrmAccountDataGate::STATUS_ABERTO)
            ->orderBy('opened_at')
            ->get();

        if ($gatesAbertos->isNotEmpty() && !($user && $user->isAdmin())) {
            return [
                'view' => 'crm.accounts.gate-bloqueio',
                'data' => [
                    'account'    => $account,
                    'gates'      => $gatesAbertos,
                    'gateLabels' => $this->gateLabels(),
                ],
            ];
        }

        $gatesAtivos = CrmAccountDataGate::where('account_id', $id)
            ->whereIn('status', CrmAccountDataGate::STATUS_ATIVOS)
            ->orderBy('opened_at')
            ->get();

        // Ciente diário obrigatório: gates em_revisao/escalado (inclusive para admin)
        // exigem compromisso registrado a cada dia que o usuário acessa a conta.
        $userId = $user?->id;
        $gatesPendentesCiente = $gatesAtivos
            ->whereIn('status', [
                CrmAccountDataGate::STATUS_EM_REVISAO,
                CrmAccountDataGate::STATUS_ESCALADO,
            ])
            ->filter(function ($g) use ($userId) {
                return !DB::table('crm_account_data_gate_cientes')
                    ->where('gate_id', $g->id)
                    ->where('user_id', $userId)
                    ->where('given_date', now()->toDateString())
                    ->exists();
            })
            ->values();

        if ($userId && $gatesPendentesCiente->isNotEmpty()) {
            return [
                'view' => 'crm.accounts.gate-ciente',
                'data' => [
                    'account'    => $account,
                    'gates'      => $gatesPendentesCiente,
                    'gateLabels' => $this->gateLabels(),
                ],
            ];
        }

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

        // Inadimplência: tarefa aberta de cobrança + decisão ativa + histórico
        $inadTarefaAberta = CrmActivity::where('account_id', $id)
            ->where('type', 'task')
            ->where('purpose', 'cobranca')
            ->where('requires_evidence', true)
            ->whereNull('done_at')
            ->with('createdBy')
            ->first();

        $inadEvidencias = $inadTarefaAberta
            ? CrmDocument::where('account_id', $id)
                ->where('activity_id', $inadTarefaAberta->id)
                ->with('uploadedBy')
                ->latest()
                ->get()
            : collect();

        $inadDecisaoAtiva = CrmInadimplenciaDecisao::where('account_id', $id)
            ->where('status', 'ativa')
            ->with('createdBy')
            ->latest()
            ->first();

        $inadHistoricoDecisoes = CrmInadimplenciaDecisao::where('account_id', $id)
            ->with('createdBy')
            ->latest()
            ->limit(5)
            ->get();

        $serviceRequests = CrmServiceRequest::where('account_id', $id)
            ->with(['requestedBy', 'assignedTo'])
            ->latest()
            ->get();
        $srCategorias = CrmServiceRequest::categorias();

        // Processos administrativos
        $adminProcesses = CrmAdminProcess::where('account_id', $id)
            ->with('owner')
            ->orderByDesc('created_at')
            ->get();

        // Notificações WhatsApp pendentes (Nexo) vinculadas a este cliente
        $nexoPendentes = collect();
        $clienteLocal = $djContext['cliente'] ?? null;
        if ($clienteLocal && isset($clienteLocal->id)) {
            $nexoPendentes = DB::table('nexo_notificacoes')
                ->where('cliente_id', $clienteLocal->id)
                ->where('status', 'pending')
                ->orderByDesc('created_at')
                ->get();
        }

        $gateLabels = $this->gateLabels();
        $flagsService = app(CrmAccountFlagsService::class);
        $accountFlags = $flagsService->calcular($id);
        $flagsCatalogo = $flagsService->catalogoLista();
        $flagsManuaisAtivas = DB::table('crm_account_manual_flags')
            ->where('account_id', $id)
            ->whereNull('removed_at')
            ->orderBy('created_at')
            ->get();

        return [
            'view' => 'crm.accounts.show',
            'data' => compact(
                'account', 'timeline', 'djContext', 'commContext', 'users', 'segmentation', 'finSummary',
                'documents', 'docCategorias', 'serviceRequests', 'srCategorias', 'nexoPendentes', 'adminProcesses',
                'inadTarefaAberta', 'inadEvidencias', 'inadDecisaoAtiva', 'inadHistoricoDecisoes',
                'gatesAtivos', 'gateLabels', 'accountFlags', 'flagsCatalogo', 'flagsManuaisAtivas'
            ),
        ];
    }

    public function gateLabels(): array
    {
        return [
            CrmAccountDataGate::TIPO_ONBOARDING_COM_CONTRATO     => 'Onboarding no DJ, mas já existe contrato/processo',
            CrmAccountDataGate::TIPO_STATUS_CLIENTE_SEM_VINCULO  => 'Marcado como Cliente no DJ, sem contrato nem processo',
            CrmAccountDataGate::TIPO_ADVERSA_COM_CONTRATO        => 'DJ classifica como Adversa/Contraparte/Fornecedor, mas existe contrato',
            CrmAccountDataGate::TIPO_INADIMPLENCIA_SUSPEITA_2099 => 'Contas com vencimento 2099-12-31 (judicial/indefinido) sem marca de inadimplência',
            CrmAccountDataGate::TIPO_SEM_STATUS_PESSOA           => 'Cadastro DJ sem status_pessoa preenchido',
        ];
    }

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

            $ctx['contratos'] = DB::table('contratos')
                ->where('contratante_id_datajuri', $djId)
                ->orderByDesc('data_assinatura')
                ->get([
                    'id', 'datajuri_id', 'numero', 'valor', 'data_assinatura',
                    'proprietario_nome', 'payload_raw', 'updated_at_api',
                ])
                ->map(function ($c) {
                    $payload = $c->payload_raw ? json_decode($c->payload_raw, true) : [];
                    $c->data_cadastro = $payload['dataCadastro'] ?? null;
                    return $c;
                })
                ->toArray();

            $ctx['receita_total'] = DB::table('movimentos')
                ->where('pessoa_id_datajuri', $djId)
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('valor', '>', 0)
                ->sum('valor') ?? 0;

            $ctx['ultimo_movimento'] = DB::table('movimentos')
                ->where('pessoa_id_datajuri', $djId)
                ->orderByDesc('data')
                ->first(['data', 'valor', 'descricao', 'classificacao']);

            $ctx['contas_receber'] = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $djId)
                ->where('is_stale', false)
                ->orderByDesc('data_vencimento')
                ->get([
                    'id', 'descricao', 'valor', 'data_vencimento',
                    'data_pagamento', 'status', 'tipo',
                ])
                ->toArray();

            $ctx['financeiro_cache'] = $account->datajuriFinanceiro();

            $ctx['available'] = true;
        } catch (\Exception $e) {
            Log::warning("[CRM] DataJuri context falhou account #{$account->id}: {$e->getMessage()}");
        }

        return $ctx;
    }

    private function loadCommunicationContext(CrmAccount $account, array $djContext): array
    {
        $ctx = [
            'whatsapp'    => null,
            'tickets'     => [],
            'has_wa'      => false,
            'has_tickets' => false,
        ];

        try {
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

            $wa = DB::table('wa_conversations')
                ->where('linked_crm_account_id', $account->id)
                ->orderByDesc('last_message_at')
                ->first(['id', 'phone', 'name', 'status', 'last_message_at', 'assigned_user_id', 'bot_ativo', 'priority']);

            if (!$wa && !empty($phones)) {
                foreach ($phones as $phone) {
                    $last9 = substr(preg_replace('/\D/', '', $phone), -9);
                    if (strlen($last9) < 8) continue;

                    $wa = DB::table('wa_conversations')
                        ->where('phone', 'like', '%' . $last9)
                        ->orderByDesc('last_message_at')
                        ->first(['id', 'phone', 'name', 'status', 'last_message_at', 'assigned_user_id', 'bot_ativo', 'priority']);

                    if ($wa) break;
                }
            }

            if ($wa) {
                $ctx['whatsapp'] = $wa;
                $ctx['has_wa']   = true;

                $ctx['wa_messages'] = DB::table('wa_messages')
                    ->where('conversation_id', $wa->id)
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'direction', 'body', 'message_type', 'created_at'])
                    ->reverse()
                    ->values()
                    ->toArray();
            }

            $srQuery = DB::table('crm_service_requests')
                ->where('account_id', $account->id)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'protocolo', 'subject', 'status', 'priority', 'category', 'origem', 'created_at', 'resolved_at']);

            if ($srQuery->isEmpty() && !empty($phones)) {
                $last9 = substr(preg_replace('/\D/', '', $phones[0]), -9);
                $srQuery = DB::table('crm_service_requests')
                    ->where('phone_contato', 'like', '%' . $last9)
                    ->where('origem', 'autoatendimento')
                    ->orderByDesc('created_at')
                    ->limit(10)
                    ->get(['id', 'protocolo', 'subject', 'status', 'priority', 'category', 'origem', 'created_at', 'resolved_at']);
            }

            $ctx['tickets']     = $srQuery->toArray();
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

    private function describeAccountUpdate(array $p): string
    {
        if (empty($p)) return 'Dados atualizados';

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
}
