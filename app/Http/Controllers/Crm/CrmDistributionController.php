<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmOwnerProfile;
use App\Models\Crm\CrmDistributionProposal;
use App\Services\Crm\CrmDistributionService;
use Illuminate\Http\Request;

class CrmDistributionController extends Controller
{
    public function index()
    {
        $profiles = CrmOwnerProfile::active()->with('user')->get()->map(function ($p) {
            $p->current_count = $p->currentCount();
            return $p;
        });

        $proposals = CrmDistributionProposal::orderByDesc('created_at')->limit(10)->get();
        $lastApplied = CrmDistributionProposal::where('status', 'applied')->orderByDesc('applied_at')->first();

        return view('crm.distribution.index', compact('profiles', 'proposals', 'lastApplied'));
    }

    public function updateProfile(Request $req, int $id)
    {
        $profile = CrmOwnerProfile::findOrFail($id);
        $profile->update($req->only(['max_accounts', 'priority_weight', 'description', 'active']));

        if ($req->has('specialties')) {
            $profile->update(['specialties' => array_filter(array_map('trim', explode(',', $req->specialties)))]);
        }

        return back()->with('success', 'Perfil atualizado.');
    }

    public function generate(CrmDistributionService $service)
    {
        try {
            $proposal = $service->gerarProposta(auth()->id());
            return redirect()->route('crm.distribution.review', $proposal->id)->with('success', 'Proposta gerada com sucesso.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro ao gerar proposta: ' . $e->getMessage());
        }
    }

    public function review(int $id)
    {
        $proposal = CrmDistributionProposal::findOrFail($id);
        $profiles = CrmOwnerProfile::active()->with('user')->get();

        // Enriquecer assignments com nomes
        $accountNames = \App\Models\Crm\CrmAccount::whereIn('id', collect($proposal->assignments)->pluck('account_id'))
            ->pluck('name', 'id');
        $userNames = \App\Models\User::whereIn('id', $profiles->pluck('user_id'))->pluck('name', 'id');

        return view('crm.distribution.review', compact('proposal', 'profiles', 'accountNames', 'userNames'));
    }

    public function apply(Request $req, int $id, CrmDistributionService $service)
    {
        $proposal = CrmDistributionProposal::findOrFail($id);

        // Overrides: account_id => new_owner_id
        $overrides = [];
        if ($req->has('overrides')) {
            foreach ($req->overrides as $accId => $newOwner) {
                if ($newOwner && $newOwner !== 'keep') {
                    $overrides[(int)$accId] = (int)$newOwner;
                }
            }
        }

        try {
            $service->aplicarProposta($proposal, auth()->id(), $overrides);
            return redirect()->route('crm.distribution')->with('success', 'Distribuição aplicada! ' . count($proposal->assignments) . ' clientes atribuídos.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Erro: ' . $e->getMessage());
        }
    }
}
