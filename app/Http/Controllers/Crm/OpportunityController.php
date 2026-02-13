<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Opportunity;
use App\Models\Crm\Stage;
use App\Models\Crm\Account;
use App\Models\Crm\Identity;
use App\Services\Crm\CrmOpportunityService;
use App\Services\Crm\CrmIdentityResolver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OpportunityController extends Controller
{
    protected CrmOpportunityService $oppService;
    protected CrmIdentityResolver $resolver;

    public function __construct(CrmOpportunityService $oppService, CrmIdentityResolver $resolver)
    {
        $this->oppService = $oppService;
        $this->resolver = $resolver;
    }

    /**
     * Formulário de criação.
     */
    public function create()
    {
        $stages = Stage::workable()->ordered()->get();
        $users = User::orderBy('name')->get(['id', 'name']);

        return view('crm.opportunity_create', compact('stages', 'users'));
    }

    /**
     * Salvar nova oportunidade.
     */
    public function store(Request $request)
    {
        $request->validate([
            'account_name' => 'required|string|max:255',
            'title' => 'required|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'doc' => 'nullable|string|max:20',
            'type' => 'required|in:PF,PJ',
        ]);

        // Resolver ou criar account
        $account = $this->resolver->resolve(
            $request->input('phone'),
            $request->input('email'),
            $request->input('doc'),
            [
                'name' => $request->input('account_name'),
                'type' => $request->input('type'),
                'owner_user_id' => $request->input('owner_user_id'),
            ]
        );

        // Criar oportunidade
        $opportunity = $this->oppService->create($account, [
            'title' => $request->input('title'),
            'area' => $request->input('area'),
            'source' => $request->input('source', 'manual'),
            'value_estimated' => $request->input('value_estimated'),
            'owner_user_id' => $request->input('owner_user_id', auth()->id()),
            'stage_id' => $request->input('stage_id'),
            'next_action_at' => $request->input('next_action_at'),
        ]);

        return redirect()->route('crm.opportunity.show', $opportunity->id)
            ->with('success', 'Oportunidade criada com sucesso.');
    }

    /**
     * Detalhe da oportunidade.
     */
    public function show(int $id)
    {
        $opportunity = Opportunity::with([
            'account.identities',
            'stage',
            'owner',
            'activities' => fn($q) => $q->orderByRaw('done_at IS NOT NULL, due_at ASC'),
            'events' => fn($q) => $q->orderByDesc('happened_at')->limit(50),
        ])->findOrFail($id);

        $stages = Stage::active()->ordered()->get();
        $users = User::orderBy('name')->get(['id', 'name']);

        // DataJuri: buscar processos/recebíveis se account tem identity datajuri
        $datajuriData = $this->loadDataJuriContext($opportunity->account);

        return view('crm.opportunity_show', compact(
            'opportunity', 'stages', 'users', 'datajuriData'
        ));
    }

    /**
     * Atualizar oportunidade.
     */
    public function update(Request $request, int $id)
    {
        $opportunity = Opportunity::findOrFail($id);

        $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $this->oppService->update($opportunity, $request->only([
            'title', 'area', 'source', 'value_estimated',
            'owner_user_id', 'next_action_at',
        ]));

        return redirect()->route('crm.opportunity.show', $id)
            ->with('success', 'Oportunidade atualizada.');
    }

    /**
     * Mover estágio.
     */
    public function moveStage(Request $request, int $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $request->validate(['stage_id' => 'required|exists:crm_stages,id']);

        $this->oppService->moveStage($opportunity, $request->input('stage_id'));

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('crm.opportunity.show', $id)
            ->with('success', 'Estágio atualizado.');
    }

    /**
     * Marcar como ganho.
     */
    public function markWon(Request $request, int $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $finalValue = $request->input('final_value');

        $this->oppService->markWon($opportunity, $finalValue ? (float) $finalValue : null);

        return redirect()->route('crm.opportunity.show', $id)
            ->with('success', 'Oportunidade marcada como GANHA!');
    }

    /**
     * Marcar como perdida.
     */
    public function markLost(Request $request, int $id)
    {
        $opportunity = Opportunity::findOrFail($id);
        $request->validate(['lost_reason' => 'required|string|max:255']);

        $this->oppService->markLost($opportunity, $request->input('lost_reason'));

        return redirect()->route('crm.opportunity.show', $id)
            ->with('success', 'Oportunidade marcada como perdida.');
    }

    /**
     * Carrega dados DataJuri se houver vínculo.
     */
    private function loadDataJuriContext(Account $account): array
    {
        $data = ['has_datajuri' => false, 'processos' => [], 'contas_receber' => []];

        // Verificar se account tem identity datajuri
        $djIdentity = $account->identities()->where('kind', 'datajuri')->first();

        // Também buscar por nome na tabela clientes (fallback)
        $clienteLocal = DB::table('clientes')
            ->where('nome', 'like', '%' . $account->name . '%')
            ->first();

        $datajuriId = $djIdentity ? $djIdentity->value : ($clienteLocal->datajuri_id ?? null);

        if (!$datajuriId && !$clienteLocal) {
            return $data;
        }

        $data['has_datajuri'] = true;

        // Processos ativos
        if ($clienteLocal) {
            $data['processos'] = DB::table('processos')
                ->where('cliente_id', $clienteLocal->id ?? 0)
                ->orWhere('adverso_nome', 'like', '%' . $account->name . '%')
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->toArray();
        }

        // Contas a receber
        $data['contas_receber'] = DB::table('contas_receber')
            ->where('cliente', 'like', '%' . $account->name . '%')
            ->orderByDesc('data_vencimento')
            ->limit(10)
            ->get()
            ->toArray();

        return $data;
    }
}
