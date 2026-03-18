<?php

namespace App\Services\Reports;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ReportJustusService
{
    private array $connections = ['justus_tjsc', 'justus_stj', 'justus_falcao', 'mysql'];

    // ── REL-J01: Acervo ──────────────────────────────────────
    public function acervo(array $filters, int $perPage = 25)
    {
        $queries = [];

        foreach ($this->connections as $conn) {
            try {
                $q = DB::connection($conn)->table('justus_jurisprudencia')
                    ->select('id', 'tribunal', 'numero_processo', 'sigla_classe', 'orgao_julgador',
                             'relator', 'data_decisao', 'area_direito', 'ementa', 'fonte_dataset');

                $this->applyJustusFilters($q, $filters);
                $queries[] = $q;
            } catch (\Exception $e) {
                continue;
            }
        }

        if (empty($queries)) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage);
        }

        // UNION via raw — pegar contagem total e paginar manualmente
        $allData = collect();
        $totalCount = 0;

        foreach ($this->connections as $conn) {
            try {
                $q = DB::connection($conn)->table('justus_jurisprudencia');
                $this->applyJustusFilters($q, $filters);
                $totalCount += $q->count();
            } catch (\Exception $e) {
                continue;
            }
        }

        // Pegar dados paginados — strategy: offset distribuído
        $page = (int) request('page', 1);
        $offset = ($page - 1) * $perPage;
        $remaining = $perPage;
        $skip = $offset;

        $sort = $filters['sort'] ?? 'data_decisao';
        $dir = $filters['dir'] ?? 'desc';
        $allowedSort = ['data_decisao', 'tribunal', 'numero_processo', 'orgao_julgador', 'relator'];
        if (!in_array($sort, $allowedSort)) $sort = 'data_decisao';

        foreach ($this->connections as $conn) {
            if ($remaining <= 0) break;
            try {
                $q = DB::connection($conn)->table('justus_jurisprudencia')
                    ->select('id', 'tribunal', 'numero_processo', 'sigla_classe', 'orgao_julgador',
                             'relator', 'data_decisao', 'area_direito',
                             DB::raw("LEFT(ementa, 200) as ementa"), 'fonte_dataset');

                $this->applyJustusFilters($q, $filters);
                $connCount = (clone $q)->count();

                if ($skip >= $connCount) {
                    $skip -= $connCount;
                    continue;
                }

                $rows = $q->orderBy($sort, $dir)->skip($skip)->take($remaining)->get();
                $allData = $allData->merge($rows);
                $remaining -= $rows->count();
                $skip = 0;
            } catch (\Exception $e) {
                continue;
            }
        }

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $allData, $totalCount, $perPage, $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    // ── REL-J02: Estatísticas de Captura ─────────────────────
    public function captura(): array
    {
        $stats = [];
        
        // Mapeamento por valor da coluna tribunal (não por conexão)
        $fonteMap = [
            'TJSC'  => 'Scraping eproc TJSC',
            'STJ'   => 'Dados Abertos STJ',
            'TRT12' => 'API REST Falcão',
            'TRF4'  => 'Scraping eproc',
        ];
        
        foreach ($this->connections as $conn) {
            try {
                // Agrupa por coluna tribunal dentro de cada conexão
                $grupos = DB::connection($conn)->table('justus_jurisprudencia')
                    ->select(
                        'tribunal',
                        DB::raw('COUNT(*) as total'),
                        DB::raw('MIN(data_decisao) as min_date'),
                        DB::raw('MAX(data_decisao) as max_date'),
                        DB::raw('MAX(created_at) as last_import'),
                        DB::raw('SUM(CASE WHEN created_at >= "' . now()->startOfMonth()->toDateString() . '" THEN 1 ELSE 0 END) as this_month')
                    )
                    ->groupBy('tribunal')
                    ->get();
                
                foreach ($grupos as $g) {
                    $trib = $g->tribunal ?: 'Outros';
                    $stats[] = [
                        'tribunal' => $trib,
                        'total' => (int) $g->total,
                        'periodo_de' => $g->min_date,
                        'periodo_ate' => $g->max_date,
                        'ultima_importacao' => $g->last_import,
                        'novos_mes' => (int) $g->this_month,
                        'fonte' => $fonteMap[$trib] ?? 'Desconhecido',
                    ];
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        // Ordenar por total desc
        usort($stats, function($a, $b) { return $b['total'] - $a['total']; });
        
        return $stats;
    }

    // ── REL-J03    // ── REL-J03: Distribuição por Área ───────────────────────
    public function distribuicao(): array
    {
        $matrix = [];
        $tribunais = [];

        foreach ($this->connections as $conn) {
            try {
                $rows = DB::connection($conn)->table('justus_jurisprudencia')
                    ->select('area_direito', 'tribunal', DB::raw('COUNT(*) as total'))
                    ->groupBy('area_direito', 'tribunal')
                    ->get();

                foreach ($rows as $r) {
                    $area = $r->area_direito ?: '(não classificado)';
                    $trib = $r->tribunal ?: 'Outros';
                    if (!isset($matrix[$area])) $matrix[$area] = ['area' => $area];
                    if (!isset($matrix[$area][$trib])) $matrix[$area][$trib] = 0;
                    $matrix[$area][$trib] += $r->total;
                    $tribunais[$trib] = true;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // Calcular totais por linha
        foreach ($matrix as &$row) {
            $row['total'] = 0;
            foreach (array_keys($tribunais) as $t) {
                $row[$t] = $row[$t] ?? 0;
                $row['total'] += $row[$t];
            }
        }
        unset($row);

        // Ordenar por total desc
        usort($matrix, function ($a, $b) { return $b['total'] - $a['total']; });

        return ['data' => array_values($matrix), 'tribunais' => array_keys($tribunais)];
    }

    private function applyJustusFilters($query, array $filters): void
    {
        if (!empty($filters['tribunal'])) {
            $query->where('tribunal', $filters['tribunal']);
        }
        if (!empty($filters['orgao'])) {
            $query->where('orgao_julgador', 'LIKE', '%' . $filters['orgao'] . '%');
        }
        if (!empty($filters['area'])) {
            $query->where('area_direito', $filters['area']);
        }
        if (!empty($filters['classe'])) {
            $query->where('sigla_classe', 'LIKE', '%' . $filters['classe'] . '%');
        }
        if (!empty($filters['busca'])) {
            $query->whereRaw("MATCH(ementa) AGAINST(? IN BOOLEAN MODE)", [$filters['busca']]);
        }
        if (!empty($filters['periodo_de'])) {
            $query->where('data_decisao', '>=', $filters['periodo_de'] . '-01');
        }
        if (!empty($filters['periodo_ate'])) {
            $parts = explode('-', $filters['periodo_ate']);
            if (count($parts) === 2) {
                $lastDay = date('Y-m-t', mktime(0, 0, 0, (int)$parts[1], 1, (int)$parts[0]));
                $query->where('data_decisao', '<=', $lastDay);
            }
        }
    }
}
