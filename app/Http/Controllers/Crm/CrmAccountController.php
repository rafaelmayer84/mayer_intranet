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
     * Cliente 360.
     */
    public function show(int $id)
    {
        $account = CrmAccount::with([
            'owner',
            'identities',
            'opportunities' => fn($q) => $q->with('stage', 'owner')->latest(),
            'activities' => fn($q) => $q->latest()->limit(20),
        ])->findOrFail($id);

        // Timeline: events + activities merged
        $events = CrmEvent::where('account_id', $id)->latest('happened_at')->limit(30)->get();
        $activities = CrmActivity::where('account_id', $id)->latest('created_at')->limit(30)->get();

        $timeline = collect()
            ->merge($events->map(fn($e) => [
                'type'       => 'event',
                'subtype'    => $e->type,
                'title'      => $this->eventTitle($e),
                'payload'    => $e->payload,
                'date'       => $e->happened_at,
                'user'       => $e->createdBy?->name,
            ]))
            ->merge($activities->map(fn($a) => [
                'type'       => 'activity',
                'subtype'    => $a->type,
                'title'      => $a->title,
                'body'       => $a->body,
                'date'       => $a->created_at,
                'done'       => $a->isDone(),
                'user'       => $a->createdBy?->name,
            ]))
            ->sortByDesc('date')
            ->values()
            ->take(50);

        // DataJuri context (degrade gracefully)
        $djContext = $this->loadDataJuriContext($account);

        $users = User::orderBy('name')->get(['id', 'name']);

        return view('crm.accounts.show', compact('account', 'timeline', 'djContext', 'users'));
    }

    /**
     * Atualizar campos gerenciais do account (AJAX).
     */
    public function update(Request $request, int $id)
    {
        $account = CrmAccount::findOrFail($id);

        $validated = $request->validate([
            'owner_user_id' => 'nullable|exists:users,id',
            'lifecycle'     => 'nullable|in:onboarding,ativo,adormecido,risco',
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

    // --- Private ---

    private function loadDataJuriContext(CrmAccount $account): array
    {
        $ctx = ['processos' => [], 'contratos' => [], 'financeiro' => [], 'available' => false];

        if (!$account->datajuri_pessoa_id) return $ctx;

        try {
            $djId = $account->datajuri_pessoa_id;

            $ctx['processos'] = DB::table('processos')
                ->where('cliente_id', function ($q) use ($djId) {
                    $q->select('id')->from('clientes')->where('datajuri_id', $djId)->limit(1);
                })
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->toArray();

            $ctx['contratos'] = DB::table('contratos')
                ->where('contratante_id_datajuri', $djId)
                ->orderByDesc('data_assinatura')
                ->limit(10)
                ->get()
                ->toArray();

            $ctx['financeiro'] = $account->datajuriFinanceiro();
            $ctx['available'] = true;
        } catch (\Exception $e) {
            Log::warning("[CRM] DataJuri context falhou para account #{$account->id}: {$e->getMessage()}");
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
