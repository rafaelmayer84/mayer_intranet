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

        return view('crm.accounts.show', compact(
            'account', 'timeline', 'djContext', 'commContext', 'users'
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

        $account->update($validated);

        CrmEvent::create([
            'account_id'         => $id,
            'type'               => 'account_updated',
            'payload'            => array_keys($validated),
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);

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

        return redirect()->route('crm.opportunities.show', $opp->id)
            ->with('success', 'Oportunidade criada.');
    }

    /**
     * Adicionar atividade ao account (AJAX).
     */
    public function storeActivity(Request $request, int $id)
    {
        $request->validate([
            'type'   => 'required|in:task,call,meeting,whatsapp,note',
            'title'  => 'required|string|max:255',
            'body'   => 'nullable|string|max:5000',
            'due_at' => 'nullable|date',
        ]);

        $activity = CrmActivity::create([
            'account_id'         => $id,
            'type'               => $request->type,
            'title'              => $request->title,
            'body'               => $request->body,
            'due_at'             => $request->due_at,
            'created_by_user_id' => auth()->id(),
        ]);

        CrmAccount::where('id', $id)->update(['last_touch_at' => now()]);

        return response()->json(['ok' => true, 'id' => $activity->id]);
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

    private function eventTitle(CrmEvent $event): string
    {
        return match ($event->type) {
            'opportunity_created'  => 'Nova oportunidade criada',
            'stage_changed'        => 'Estágio alterado: ' . ($event->payload['from_stage'] ?? '?') . ' → ' . ($event->payload['to_stage'] ?? '?'),
            'opportunity_lost'     => 'Oportunidade perdida' . (isset($event->payload['reason']) ? ': ' . $event->payload['reason'] : ''),
            'lead_qualified'       => 'Lead qualificado',
            'nexo_opened_chat'     => 'Chat WhatsApp aberto (NEXO)',
            'account_updated'      => 'Dados atualizados',
            default                => ucfirst(str_replace('_', ' ', $event->type)),
        };
    }
}
