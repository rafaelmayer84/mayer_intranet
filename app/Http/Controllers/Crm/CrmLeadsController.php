<?php
namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmEvent;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmLeadsController extends Controller
{
    public function index(Request $request)
    {
        $origem = $request->get('origem', 'todos');
        $status = $request->get('status', '');
        $search = $request->get('search', '');
        $sortField = $request->get('sort', 'data');
        $sortDir = $request->get('dir', 'desc');

        $crmQuery = DB::table('crm_accounts')
            ->leftJoin('users', 'crm_accounts.owner_user_id', '=', 'users.id')
            ->leftJoin('leads', 'leads.crm_account_id', '=', 'crm_accounts.id')
            ->where('crm_accounts.kind', 'prospect')
            ->where('crm_accounts.lifecycle', '!=', 'arquivado')
            ->select([
                DB::raw("crm_accounts.id as id"),
                DB::raw("'crm' as origem"),
                DB::raw("crm_accounts.name as nome"),
                DB::raw("crm_accounts.email as email"),
                DB::raw("crm_accounts.phone_e164 as telefone"),
                DB::raw("crm_accounts.lifecycle as lifecycle"),
                DB::raw("crm_accounts.owner_user_id as owner_user_id"),
                DB::raw("users.name as owner_name"),
                DB::raw("COALESCE(leads.area_interesse, crm_accounts.notes, '') as area_interesse"),
                DB::raw("COALESCE(leads.intencao_contratar, '') as intencao_contratar"),
                DB::raw("COALESCE(leads.potencial_honorarios, '') as potencial_honorarios"),
                DB::raw("COALESCE(leads.origem_canal, '') as origem_canal"),
                DB::raw("crm_accounts.last_touch_at as ultimo_contato"),
                DB::raw("crm_accounts.created_at as data"),
                DB::raw("crm_accounts.id as crm_account_id"),
                DB::raw("COALESCE(leads.id, 0) as lead_id"),
            ]);

        $leadsQuery = DB::table('leads')
            ->leftJoin('crm_accounts', 'leads.crm_account_id', '=', 'crm_accounts.id')
            ->leftJoin('users', 'crm_accounts.owner_user_id', '=', 'users.id')
            ->whereNotIn('leads.status', ['arquivado', 'convertido'])
            ->select([
                DB::raw("leads.id as id"),
                DB::raw("'marketing' as origem"),
                DB::raw("leads.nome as nome"),
                DB::raw("leads.email as email"),
                DB::raw("leads.telefone as telefone"),
                DB::raw("CASE leads.status WHEN 'novo' THEN 'onboarding' WHEN 'contatado' THEN 'ativo' WHEN 'descartado' THEN 'risco' ELSE 'onboarding' END as lifecycle"),
                DB::raw("crm_accounts.owner_user_id as owner_user_id"),
                DB::raw("users.name as owner_name"),
                DB::raw("COALESCE(leads.area_interesse, '') as area_interesse"),
                DB::raw("COALESCE(leads.intencao_contratar, '') as intencao_contratar"),
                DB::raw("COALESCE(leads.potencial_honorarios, '') as potencial_honorarios"),
                DB::raw("COALESCE(leads.origem_canal, '') as origem_canal"),
                DB::raw("leads.updated_at as ultimo_contato"),
                DB::raw("leads.data_entrada as data"),
                DB::raw("COALESCE(leads.crm_account_id, 0) as crm_account_id"),
                DB::raw("leads.id as lead_id"),
            ]);

        if ($search) {
            $s = '%' . $search . '%';
            $crmQuery->where(function ($q) use ($s) {
                $q->where('crm_accounts.name', 'LIKE', $s)
                  ->orWhere('crm_accounts.email', 'LIKE', $s)
                  ->orWhere('crm_accounts.phone_e164', 'LIKE', $s);
            });
            $leadsQuery->where(function ($q) use ($s) {
                $q->where('leads.nome', 'LIKE', $s)
                  ->orWhere('leads.email', 'LIKE', $s)
                  ->orWhere('leads.telefone', 'LIKE', $s);
            });
        }

        if ($status) {
            $lifecycleMap = [
                'novo' => 'onboarding',
                'em_contato' => 'ativo',
                'perdido' => 'risco',
            ];
            if (isset($lifecycleMap[$status])) {
                $lc = $lifecycleMap[$status];
                $crmQuery->where('crm_accounts.lifecycle', $lc);
                // Para leads, o CASE ja mapeia status->lifecycle
                $statusMap = ['onboarding' => 'novo', 'ativo' => 'contatado', 'risco' => 'descartado'];
                if (isset($statusMap[$lc])) {
                    $leadsQuery->where('leads.status', $statusMap[$lc]);
                }
            }
        }

        if ($origem === 'crm') {
            $union = $crmQuery;
        } elseif ($origem === 'marketing') {
            $union = $leadsQuery;
        } else {
            $union = $crmQuery->unionAll($leadsQuery);
        }

        $allowedSort = ['nome', 'data', 'ultimo_contato', 'lifecycle'];
        if (!in_array($sortField, $allowedSort)) $sortField = 'data';

        $results = DB::table(DB::raw("({$union->toSql()}) as unified"))
            ->mergeBindings($union)
            ->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc')
            ->paginate(25)
            ->withQueryString();

        $users = User::orderBy('name')->get(['id', 'name']);

        $leadsAtivos = DB::table('leads')->whereNotIn('status', ['arquivado', 'convertido']);
        $crmProspects = DB::table('crm_accounts')->where('kind', 'prospect')->where('lifecycle', '!=', 'arquivado');

        $totals = [
            'novo'       => (clone $leadsAtivos)->where('status', 'novo')->count()
                          + (clone $crmProspects)->where('lifecycle', 'onboarding')->count(),
            'em_contato' => (clone $leadsAtivos)->where('status', 'contatado')->count()
                          + (clone $crmProspects)->where('lifecycle', 'ativo')->count(),
            'perdido'    => (clone $leadsAtivos)->where('status', 'descartado')->count()
                          + (clone $crmProspects)->where('lifecycle', 'risco')->count(),
            'marketing'  => (clone $leadsAtivos)->count(),
            'crm'        => (clone $crmProspects)->count(),
            'total'      => $leadsAtivos->count() + $crmProspects->count(),
        ];

        return view('crm.leads.index', compact('results', 'users', 'totals'));
    }

    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'lifecycle' => 'required|in:onboarding,ativo,adormecido,risco',
        ]);
        $account = CrmAccount::findOrFail($id);
        $old = $account->lifecycle;
        $account->update(['lifecycle' => $request->lifecycle, 'last_touch_at' => now()]);
        CrmEvent::create([
            'account_id'         => $id,
            'type'               => 'lead_status_changed',
            'payload'            => ['from' => $old, 'to' => $request->lifecycle],
            'happened_at'        => now(),
            'created_by_user_id' => auth()->id(),
        ]);
        return response()->json(['ok' => true]);
    }

    public function assignOwner(Request $request, int $id)
    {
        $request->validate(['owner_user_id' => 'nullable|exists:users,id']);
        $account = CrmAccount::findOrFail($id);
        $account->update(['owner_user_id' => $request->owner_user_id]);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /crm/leads/manual
     * Cadastra lead manual e promove ao CRM no mesmo fluxo.
     */
    public function storeManual(Request $request)
    {
        $request->validate([
            'nome'            => 'required|string|max:255',
            'telefone'        => 'nullable|string|max:30',
            'email'           => 'nullable|email|max:255',
            'area_interesse'  => 'nullable|string|max:255',
            'resumo_demanda'  => 'nullable|string|max:2000',
            'origem_canal'    => 'required|in:relacionamento,indicacao,telefone,presencial,google_ads,redes_sociais,organico,nao_identificado',
            'owner_user_id'   => 'required|exists:users,id',
        ]);

        DB::beginTransaction();
        try {
            // 1) Criar lead na tabela leads (mesmo formato dos automaticos)
            $lead = Lead::create([
                'nome'                  => $request->nome,
                'telefone'              => $request->telefone,
                'email'                 => $request->email,
                'area_interesse'        => $request->area_interesse,
                'resumo_demanda'        => $request->resumo_demanda,
                'origem_canal'          => $request->origem_canal,
                'status'                => 'novo',
                'intencao_contratar'    => 'sim',
                'data_entrada'          => now(),
            ]);

            // 2) Criar crm_account como prospect
            $account = CrmAccount::create([
                'kind'          => 'prospect',
                'name'          => $lead->nome,
                'email'         => $lead->email,
                'phone_e164'    => $lead->telefone ? preg_replace('/\D/', '', $lead->telefone) : null,
                'lifecycle'     => 'onboarding',
                'owner_user_id' => $request->owner_user_id,
                'last_touch_at' => now(),
                'notes'         => 'Lead manual (' . $request->origem_canal . '). Area: ' . ($lead->area_interesse ?: 'N/A') . '. Demanda: ' . \Illuminate\Support\Str::limit($lead->resumo_demanda ?: '', 200),
            ]);

            // 3) Criar oportunidade
            $firstStage = \App\Models\Crm\CrmStage::orderBy('order')->first();
            $opp = \App\Models\Crm\CrmOpportunity::create([
                'account_id'    => $account->id,
                'stage_id'      => $firstStage ? $firstStage->id : null,
                'title'         => $lead->area_interesse ? $lead->area_interesse . ' - ' . $lead->nome : 'Oportunidade - ' . $lead->nome,
                'area'          => $lead->area_interesse,
                'type'          => 'new_business',
                'source'        => $request->origem_canal,
                'lead_source'   => 'manual',
                'status'        => 'open',
                'owner_user_id' => $request->owner_user_id,
                'tipo_demanda'  => null,
            ]);

            // 4) Vincular lead ao account
            $lead->update([
                'crm_account_id' => $account->id,
                'status'         => 'convertido',
            ]);

            // 5) Evento CRM
            CrmEvent::create([
                'account_id'         => $account->id,
                'type'               => 'lead_promoted',
                'payload'            => ['lead_id' => $lead->id, 'opportunity_id' => $opp->id, 'source' => 'manual'],
                'happened_at'        => now(),
                'created_by_user_id' => auth()->id(),
            ]);

            // 6) Task primeiro contato
            try {
                (new \App\Services\Crm\CrmProactiveService())->criarTaskPrimeiroContato(
                    $account->id, $account->name, $request->owner_user_id
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[CrmProactive] Falha task primeiro contato manual: ' . $e->getMessage());
            }

            // 7) Notificar advogado responsavel
            if ((int) $request->owner_user_id !== auth()->id()) {
                DB::table('notifications_intranet')->insert([
                    'user_id'    => $request->owner_user_id,
                    'tipo'       => 'lead',
                    'titulo'     => 'Novo lead atribuido a voce',
                    'mensagem'   => auth()->user()->name . ' cadastrou o lead ' . $lead->nome . ' (' . $request->origem_canal . ')' . ($lead->area_interesse ? ' - ' . $lead->area_interesse : ''),
                    'link'       => url('/crm/accounts/' . $account->id),
                    'icone'      => 'user-plus',
                    'lida'       => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            return response()->json([
                'ok'             => true,
                'lead_id'        => $lead->id,
                'account_id'     => $account->id,
                'opportunity_id' => $opp->id,
                'message'        => 'Lead manual criado e promovido ao CRM.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            \Illuminate\Support\Facades\Log::error('Erro ao criar lead manual', ['error' => $e->getMessage()]);
            return response()->json(['ok' => false, 'error' => 'Erro interno: ' . $e->getMessage()], 500);
        }
    }

}
