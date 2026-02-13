<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmAccount;
use App\Models\User;
use Illuminate\Http\Request;

class CrmCarteiraController extends Controller
{
    public function index(Request $request)
    {
        $query = CrmAccount::with('owner')
            ->withCount(['opportunities as open_opps_count' => fn($q) => $q->where('status', 'open')]);

        // Filtros
        if ($request->filled('kind')) {
            $query->where('kind', $request->kind);
        }
        if ($request->filled('lifecycle')) {
            $query->where('lifecycle', $request->lifecycle);
        }
        if ($request->filled('owner_user_id')) {
            $query->where('owner_user_id', $request->owner_user_id);
        }
        if ($request->filled('sem_contato_dias')) {
            $query->withoutContactSince((int) $request->sem_contato_dias);
        }
        if ($request->boolean('overdue_only')) {
            $query->overdueNextTouch();
        }
        if ($request->filled('health_max')) {
            $query->where(function ($q) use ($request) {
                $q->whereNull('health_score')
                  ->orWhere('health_score', '<=', (int) $request->health_max);
            });
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('name', 'LIKE', $s)
                  ->orWhere('doc_digits', 'LIKE', $s)
                  ->orWhere('email', 'LIKE', $s)
                  ->orWhere('phone_e164', 'LIKE', $s);
            });
        }

        $sortField = $request->get('sort', 'name');
        $sortDir = $request->get('dir', 'asc');
        $allowed = ['name', 'lifecycle', 'last_touch_at', 'next_touch_at', 'health_score', 'created_at'];
        if (!in_array($sortField, $allowed)) $sortField = 'name';
        $query->orderBy($sortField, $sortDir === 'desc' ? 'desc' : 'asc');

        $accounts = $query->paginate(25)->withQueryString();
        $users = User::orderBy('name')->get(['id', 'name']);

        // Contadores rápidos
        $totals = [
            'total'      => CrmAccount::count(),
            'clients'    => CrmAccount::clients()->count(),
            'prospects'  => CrmAccount::prospects()->count(),
            'risco'      => CrmAccount::byLifecycle('risco')->count(),
            'adormecido' => CrmAccount::byLifecycle('adormecido')->count(),
        ];

        return view('crm.carteira.index', compact('accounts', 'users', 'totals'));
    }
}
