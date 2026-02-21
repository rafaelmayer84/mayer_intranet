<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuditLogController extends Controller
{
    public function index()
    {
        return view('admin.audit-log.index');
    }

    public function data(Request $request)
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        // Filtros
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }
        if ($request->filled('module')) {
            $query->where('module', 'like', '%' . $request->module . '%');
        }
        if ($request->filled('date_from')) {
            $query->where('created_at', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('created_at', '<=', $request->date_to . ' 23:59:59');
        }

        // Cards de resumo
        $today = now()->toDateString();
        $summary = [
            'total'          => AuditLog::count(),
            'today'          => AuditLog::where('created_at', '>=', $today)->count(),
            'logins_today'   => AuditLog::where('action', 'login')->where('created_at', '>=', $today)->count(),
            'denied_today'   => AuditLog::where('action', 'access_denied')->where('created_at', '>=', $today)->count(),
            'actions_7d'     => AuditLog::where('created_at', '>=', now()->subDays(7))->count(),
        ];

        // Gráfico: ações por dia (últimos 30 dias)
        $chartDaily = AuditLog::where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('COUNT(*) as total'))
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();

        // Gráfico: ações por usuário (últimos 30 dias)
        $chartUsers = AuditLog::where('created_at', '>=', now()->subDays(30))
            ->whereNotNull('user_name')
            ->select('user_name', DB::raw('COUNT(*) as total'))
            ->groupBy('user_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Gráfico: ações por tipo (últimos 30 dias)
        $chartActions = AuditLog::where('created_at', '>=', now()->subDays(30))
            ->select('action', DB::raw('COUNT(*) as total'))
            ->groupBy('action')
            ->orderByDesc('total')
            ->get();

        // Tabela paginada
        $logs = $query->paginate(50);

        return response()->json([
            'summary'      => $summary,
            'chart_daily'  => $chartDaily,
            'chart_users'  => $chartUsers,
            'chart_actions'=> $chartActions,
            'logs'         => $logs,
        ]);
    }
}
