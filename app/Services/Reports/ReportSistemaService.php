<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;

class ReportSistemaService
{
    // ── REL-S01: Sincronização DataJuri ───────────────────────
    public function sync(array $filters, int $perPage = 25)
    {
        $query = DB::table('sync_runs')
            ->select(
                'id', 'run_id', 'tipo', 'status', 'registros_processados',
                'registros_criados', 'registros_atualizados', 'registros_deletados',
                'erros', 'mensagem', 'started_at', 'finished_at',
                DB::raw("TIMESTAMPDIFF(SECOND, started_at, finished_at) as duracao_seg")
            );

        if (!empty($filters['modulo'])) {
            $query->where('tipo', 'LIKE', '%' . $filters['modulo'] . '%');
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['periodo_de'])) {
            $query->where('started_at', '>=', $filters['periodo_de'] . '-01');
        }
        if (!empty($filters['periodo_ate'])) {
            $parts = explode('-', $filters['periodo_ate']);
            if (count($parts) === 2) {
                $lastDay = date('Y-m-t', mktime(0, 0, 0, (int)$parts[1], 1, (int)$parts[0]));
                $query->where('started_at', '<=', $lastDay . ' 23:59:59');
            }
        }

        $sort = $filters['sort'] ?? 'started_at';
        $dir = $filters['dir'] ?? 'desc';
        $allowed = ['started_at', 'tipo', 'status', 'registros_processados', 'duracao_seg', 'erros'];
        if (!in_array($sort, $allowed)) $sort = 'started_at';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function syncTotals(): array
    {
        $total = DB::table('sync_runs')->count();
        $sucesso = DB::table('sync_runs')->where('status', 'completed')->count();
        $falha = DB::table('sync_runs')->where('status', 'failed')->count();
        $avgDur = DB::table('sync_runs')
            ->where('status', 'completed')
            ->whereNotNull('finished_at')
            ->avg(DB::raw("TIMESTAMPDIFF(SECOND, started_at, finished_at)"));
        $ultimaSync = DB::table('sync_runs')->max('finished_at');

        return [
            'total' => $total,
            'sucesso' => $sucesso,
            'falha' => $falha,
            'taxa_sucesso' => $total > 0 ? round($sucesso / $total * 100, 1) : 0,
            'duracao_media' => round((float)$avgDur),
            'ultima_sync' => $ultimaSync,
        ];
    }

    // ── REL-S02: Eventos do Sistema ──────────────────────────
    public function eventos(array $filters, int $perPage = 25)
    {
        $query = DB::table('system_events')
            ->select('id', 'category', 'severity', 'event_type', 'title', 'description', 'metadata', 'user_name', 'ip_address', 'created_at');

        if (!empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['severity'])) {
            $query->where('severity', $filters['severity']);
        }
        if (!empty($filters['busca'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('title', 'LIKE', '%' . $filters['busca'] . '%')
                  ->orWhere('description', 'LIKE', '%' . $filters['busca'] . '%');
            });
        }
        if (!empty($filters['periodo_de'])) {
            $query->where('created_at', '>=', $filters['periodo_de'] . '-01');
        }
        if (!empty($filters['periodo_ate'])) {
            $parts = explode('-', $filters['periodo_ate']);
            if (count($parts) === 2) {
                $lastDay = date('Y-m-t', mktime(0, 0, 0, (int)$parts[1], 1, (int)$parts[0]));
                $query->where('created_at', '<=', $lastDay . ' 23:59:59');
            }
        }

        $sort = $filters['sort'] ?? 'created_at';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function eventosTotals(): array
    {
        return [
            'total' => DB::table('system_events')->count(),
            'info' => DB::table('system_events')->where('severity', 'info')->count(),
            'warning' => DB::table('system_events')->where('severity', 'warning')->count(),
            'error' => DB::table('system_events')->where('severity', 'error')->count(),
            'critical' => DB::table('system_events')->where('severity', 'critical')->count(),
        ];
    }

    // ── REL-S03: Auditoria ───────────────────────────────────
    public function auditoria(array $filters, int $perPage = 25)
    {
        $query = DB::table('audit_logs')
            ->select('id', 'user_name', 'user_role', 'action', 'module', 'description', 'ip_address', 'route', 'method', 'created_at');

        if (!empty($filters['usuario'])) {
            $query->where('user_name', 'LIKE', '%' . $filters['usuario'] . '%');
        }
        if (!empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }
        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (!empty($filters['periodo_de'])) {
            $query->where('created_at', '>=', $filters['periodo_de'] . '-01');
        }
        if (!empty($filters['periodo_ate'])) {
            $parts = explode('-', $filters['periodo_ate']);
            if (count($parts) === 2) {
                $lastDay = date('Y-m-t', mktime(0, 0, 0, (int)$parts[1], 1, (int)$parts[0]));
                $query->where('created_at', '<=', $lastDay . ' 23:59:59');
            }
        }

        $sort = $filters['sort'] ?? 'created_at';
        $dir = $filters['dir'] ?? 'desc';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }
}
