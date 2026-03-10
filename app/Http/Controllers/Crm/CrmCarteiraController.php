<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmAccount;
use App\Models\User;
use Illuminate\Http\Request;

class CrmCarteiraController extends Controller
{
    public function bulkAssign(Request $request)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'account_ids'   => 'required|array|min:1',
            'account_ids.*' => 'integer|exists:crm_accounts,id',
            'owner_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $updated = CrmAccount::whereIn('id', $request->account_ids)
            ->update(['owner_user_id' => $request->owner_user_id ?: null]);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'message' => $updated . ' conta(s) atualizada(s) com sucesso.',
        ]);
    }


    public function bulkAction(Request $request)
    {
        $user = auth()->user();
        if (!$user->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'account_ids'   => 'required|array|min:1',
            'account_ids.*' => 'integer|exists:crm_accounts,id',
            'action'        => 'required|in:assign,archive,activate,dormant,onboarding,delete_prospect',
            'owner_user_id' => 'nullable|integer|exists:users,id',
        ]);

        $ids = $request->account_ids;
        $action = $request->action;
        $count = 0;

        switch ($action) {
            case 'assign':
                $count = CrmAccount::whereIn('id', $ids)
                    ->update(['owner_user_id' => $request->owner_user_id ?: null, 'updated_at' => now()]);
                $msg = $count . ' conta(s) atribuida(s).';
                break;

            case 'archive':
                $count = CrmAccount::whereIn('id', $ids)
                    ->where('lifecycle', '!=', 'arquivado')
                    ->update(['lifecycle' => 'arquivado', 'updated_at' => now()]);
                $msg = $count . ' conta(s) arquivada(s).';
                break;

            case 'activate':
                $count = CrmAccount::whereIn('id', $ids)
                    ->where('lifecycle', '!=', 'ativo')
                    ->update(['lifecycle' => 'ativo', 'updated_at' => now()]);
                $msg = $count . ' conta(s) reativada(s).';
                break;

            case 'dormant':
                $count = CrmAccount::whereIn('id', $ids)
                    ->update(['lifecycle' => 'adormecido', 'updated_at' => now()]);
                $msg = $count . ' conta(s) marcada(s) como adormecida(s).';
                break;

            case 'onboarding':
                $count = CrmAccount::whereIn('id', $ids)
                    ->update(['lifecycle' => 'onboarding', 'updated_at' => now()]);
                $msg = $count . ' conta(s) movida(s) para onboarding.';
                break;

            case 'delete_prospect':
                $count = CrmAccount::whereIn('id', $ids)
                    ->where('kind', 'prospect')
                    ->whereNull('datajuri_pessoa_id')
                    ->delete();
                $msg = $count . ' prospect(s) sem vinculo removido(s).';
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Acao invalida.'], 400);
        }

        return response()->json(['success' => true, 'updated' => $count, 'message' => $msg]);
    }

    public function index(Request $request)
    {
        $query = CrmAccount::with('owner')
            ->withCount(['opportunities as open_opps_count' => fn($q) => $q->where('status', 'open')]);

        // Filtro automatico: advogado ve apenas sua carteira (admin/coord/socio veem todos)
        $user = auth()->user();
        if ($user->role === 'advogado') {
            $query->where('owner_user_id', $user->id);
        }

        // Filtros
        if ($request->filled('kind')) {
            $query->where('kind', $request->kind);
        }
        // Default: exibir apenas ativos; 'todos' mostra tudo
        if ($request->filled('lifecycle')) {
            if ($request->lifecycle !== 'todos') {
                $query->where('lifecycle', $request->lifecycle);
            }
        } else {
            $query->where('lifecycle', 'ativo');
        }
        if ($request->filled('owner_user_id')) {
            if ($request->owner_user_id === 'sem_responsavel') {
                $query->whereNull('owner_user_id');
            } else {
                $query->where('owner_user_id', $request->owner_user_id);
            }
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
        if ($request->filled('segment')) {
            $query->where('segment', $request->segment);
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
        // Se advogado, contadores filtrados pela carteira dele
        $baseQuery = ($user->role === 'advogado')
            ? CrmAccount::where('owner_user_id', $user->id)
            : new CrmAccount;

        $totals = [
            'total'      => (clone $baseQuery)->count(),
            'ativos'     => (clone $baseQuery)->byLifecycle('ativo')->count(),
            'adormecido' => (clone $baseQuery)->byLifecycle('adormecido')->count(),
            'arquivado'  => (clone $baseQuery)->byLifecycle('arquivado')->count(),
            'onboarding' => (clone $baseQuery)->byLifecycle('onboarding')->count(),
            'sem_contato_30d' => (clone $baseQuery)->byLifecycle('ativo')->withoutContactSince(30)->count(),
        ];

        $segments = \App\Models\Crm\CrmAccount::whereNotNull('segment')
            ->distinct()->orderBy('segment')->pluck('segment');

        return view('crm.carteira.index', compact('accounts', 'users', 'totals', 'segments'));
    }
}
