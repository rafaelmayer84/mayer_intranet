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

    // ── REL-S04: Erros de Aplicação ─────────────────────────
    public function erros(array $filters, int $perPage = 25)
    {
        $query = DB::table('system_error_logs')
            ->select('id', 'level', 'message', 'module', 'file', 'line', 'url', 'user_name', 'ip_address', 'created_at');

        if (!empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }
        if (!empty($filters['module'])) {
            $query->where('module', $filters['module']);
        }
        if (!empty($filters['busca'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('message', 'LIKE', '%' . $filters['busca'] . '%')
                  ->orWhere('file', 'LIKE', '%' . $filters['busca'] . '%');
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
        $allowed = ['created_at', 'level', 'module', 'file'];
        if (!in_array($sort, $allowed)) $sort = 'created_at';
        $query->orderBy($sort, $dir);

        return $query->paginate($perPage);
    }

    public function errosTotals(): array
    {
        return [
            'total'    => DB::table('system_error_logs')->count(),
            'error'    => DB::table('system_error_logs')->where('level', 'error')->count(),
            'critical' => DB::table('system_error_logs')->where('level', 'critical')->count(),
            'alert'    => DB::table('system_error_logs')->where('level', 'alert')->count(),
            'emergency'=> DB::table('system_error_logs')->where('level', 'emergency')->count(),
            'hoje'     => DB::table('system_error_logs')->whereDate('created_at', today())->count(),
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

    // ── REL-S05: Log Laravel ────────────────────────────────
    public function laravelLog(array $filters, int $perPage = 50): array
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!file_exists($logPath)) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage];
        }

        // Ler últimas N linhas do arquivo (para performance)
        $maxLines = 5000;
        $lines = $this->tailFile($logPath, $maxLines);
        
        // Parsear linhas em entradas estruturadas
        $entries = $this->parseLogEntries($lines);
        
        // Aplicar filtros
        $filtered = $this->filterLogEntries($entries, $filters);
        
        // Ordenar (mais recente primeiro por padrão)
        $sort = $filters['sort'] ?? 'datetime';
        $dir = $filters['dir'] ?? 'desc';
        usort($filtered, function($a, $b) use ($sort, $dir) {
            $cmp = strcmp($a[$sort] ?? '', $b[$sort] ?? '');
            return $dir === 'desc' ? -$cmp : $cmp;
        });
        
        // Paginar
        $page = max(1, (int)($filters['page'] ?? 1));
        $total = count($filtered);
        $offset = ($page - 1) * $perPage;
        $paged = array_slice($filtered, $offset, $perPage);
        
        return [
            'data' => $paged,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => max(1, ceil($total / $perPage)),
        ];
    }

    public function laravelLogTotals(): array
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!file_exists($logPath)) {
            return ['total' => 0, 'info' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0, 'debug' => 0, 'hoje' => 0, 'size' => '0 B'];
        }

        $lines = $this->tailFile($logPath, 5000);
        $entries = $this->parseLogEntries($lines);
        
        $hoje = date('Y-m-d');
        $stats = ['total' => 0, 'info' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0, 'debug' => 0, 'hoje' => 0];
        
        foreach ($entries as $e) {
            $stats['total']++;
            $level = strtolower($e['level'] ?? 'info');
            if (isset($stats[$level])) {
                $stats[$level]++;
            }
            if (strpos($e['datetime'] ?? '', $hoje) === 0) {
                $stats['hoje']++;
            }
        }
        
        $size = filesize($logPath);
        $stats['size'] = $this->formatBytes($size);
        
        return $stats;
    }

    protected function tailFile(string $filepath, int $lines): array
    {
        $result = [];
        $fp = fopen($filepath, 'r');
        if (!$fp) return $result;
        
        fseek($fp, 0, SEEK_END);
        $pos = ftell($fp);
        $buffer = '';
        $lineCount = 0;
        
        while ($pos > 0 && $lineCount < $lines) {
            $pos--;
            fseek($fp, $pos);
            $char = fgetc($fp);
            if ($char === "\n") {
                if ($buffer !== '') {
                    array_unshift($result, $buffer);
                    $lineCount++;
                    $buffer = '';
                }
            } else {
                $buffer = $char . $buffer;
            }
        }
        if ($buffer !== '' && $lineCount < $lines) {
            array_unshift($result, $buffer);
        }
        fclose($fp);
        
        return $result;
    }

    protected function parseLogEntries(array $lines): array
    {
        $entries = [];
        $currentEntry = null;
        $pattern = '/^\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+(\w+)\.(\w+):\s*(.*)$/';
        
        foreach ($lines as $line) {
            if (preg_match($pattern, $line, $m)) {
                if ($currentEntry) {
                    $entries[] = $currentEntry;
                }
                $currentEntry = [
                    'datetime' => $m[1],
                    'env' => $m[2],
                    'level' => strtoupper($m[3]),
                    'message' => $m[4],
                    'module' => $this->extractModule($m[4]),
                    'full' => $line,
                ];
            } elseif ($currentEntry && trim($line) !== '') {
                $currentEntry['message'] .= ' ' . trim($line);
                $currentEntry['full'] .= "\n" . $line;
            }
        }
        if ($currentEntry) {
            $entries[] = $currentEntry;
        }
        
        return $entries;
    }

    protected function extractModule(string $message): string
    {
        $modulePatterns = [
            'NEXO' => '/^(NEXO|NexoInactivity|Bot control|nexo:)/i',
            'SendPulse' => '/^(Webhook SendPulse|SendPulse WA)/i',
            'DataJuri' => '/^(DataJuri|processLead)/i',
            'CRM' => '/^(CRM|crm:)/i',
            'GDP' => '/^(GDP|gdp:)/i',
            'Justus' => '/^(Justus|justus:)/i',
            'Evidentia' => '/^(Evidentia|evidentia:)/i',
            'Vigília' => '/^(Vigília|vigilia:)/i',
            'SIATE' => '/^\[SIATE\]/i',
            'Sync' => '/^(Sync|sync:)/i',
        ];
        
        foreach ($modulePatterns as $module => $pattern) {
            if (preg_match($pattern, $message)) {
                return $module;
            }
        }
        
        return 'Sistema';
    }

    protected function filterLogEntries(array $entries, array $filters): array
    {
        return array_filter($entries, function($e) use ($filters) {
            if (!empty($filters['level']) && strtolower($e['level']) !== strtolower($filters['level'])) {
                return false;
            }
            if (!empty($filters['module']) && $e['module'] !== $filters['module']) {
                return false;
            }
            if (!empty($filters['busca'])) {
                $search = strtolower($filters['busca']);
                if (strpos(strtolower($e['message']), $search) === false) {
                    return false;
                }
            }
            if (!empty($filters['data_de'])) {
                if ($e['datetime'] < $filters['data_de']) {
                    return false;
                }
            }
            if (!empty($filters['data_ate'])) {
                if ($e['datetime'] > $filters['data_ate'] . ' 23:59:59') {
                    return false;
                }
            }
            return true;
        });
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }

}
