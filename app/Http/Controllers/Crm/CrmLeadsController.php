<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmEvent;
use App\Models\User;
use Illuminate\Http\Request;

class CrmLeadsController extends Controller
{
    public function index(Request $request)
    {
        $query = CrmAccount::with('owner')
            ->prospects()
            ->withCount(['opportunities as open_opps' => fn($q) => $q->where('status', 'open')]);

        // Filtro por status do lead (via lifecycle)
        if ($request->filled('status')) {
            $map = [
                'novo'        => 'onboarding',
                'em_contato'  => 'ativo',
                'convertido'  => null, // clients, não prospects
                'perdido'     => 'risco',
            ];
            if ($request->status === 'convertido') {
                // Mostrar accounts que eram prospect e viraram client
                $query = CrmAccount::with('owner')
                    ->clients()
                    ->whereNotNull('converted_from_prospect_at')
                    ->withCount(['opportunities as open_opps' => fn($q) => $q->where('status', 'open')]);
            } elseif (isset($map[$request->status])) {
                $query->where('lifecycle', $map[$request->status]);
            }
        }

        if ($request->filled('owner_user_id')) {
            $query->where('owner_user_id', $request->owner_user_id);
        }

        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', $s)
                  ->orWhere('email', 'LIKE', $s)
                  ->orWhere('phone_e164', 'LIKE', $s);
            });
        }

        $sortField = $request->get('sort', 'created_at');
        $sortDir = $request->get('dir', 'desc');
        $allowed = ['name', 'created_at', 'last_touch_at', 'lifecycle'];
        if (!in_array($sortField, $allowed)) $sortField = 'created_at';
        $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');

        $leads = $query->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        // Contadores
        $totals = [
            'novo'       => CrmAccount::prospects()->where('lifecycle', 'onboarding')->count(),
            'em_contato' => CrmAccount::prospects()->where('lifecycle', 'ativo')->count(),
            'perdido'    => CrmAccount::prospects()->where('lifecycle', 'risco')->count(),
            'total'      => CrmAccount::prospects()->count(),
        ];

        return view('crm.leads.index', compact('leads', 'users', 'totals'));
    }

    /**
     * Atualizar status/lifecycle do lead (AJAX).
     */
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

    /**
     * Atribuir responsável ao lead (AJAX).
     */
    public function assignOwner(Request $request, int $id)
    {
        $request->validate(['owner_user_id' => 'nullable|exists:users,id']);

        $account = CrmAccount::findOrFail($id);
        $account->update(['owner_user_id' => $request->owner_user_id]);

        return response()->json(['ok' => true]);
    }
}
