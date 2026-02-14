<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class KpiTargetService
{
    /**
     * Lista de KPIs válidos por módulo.
     * Usado para validação na importação.
     */
    public const KPI_REGISTRY = [
        'financeiro' => [
            'receita_total'        => ['desc' => 'Receita Total (PF+PJ)',       'unidade' => 'BRL', 'tipo' => 'min'],
            'receita_pf'           => ['desc' => 'Receita Pessoa Física',       'unidade' => 'BRL', 'tipo' => 'min'],
            'receita_pj'           => ['desc' => 'Receita Pessoa Jurídica',     'unidade' => 'BRL', 'tipo' => 'min'],
            'despesas'             => ['desc' => 'Despesas Operacionais',       'unidade' => 'BRL', 'tipo' => 'max'],
            'resultado_operacional'=> ['desc' => 'Resultado Operacional',       'unidade' => 'BRL', 'tipo' => 'min'],
            'inadimplencia'        => ['desc' => 'Taxa de Inadimplência (%)',   'unidade' => 'PCT', 'tipo' => 'max'],
        ],
        'clientes_mercado' => [
            'leads_novos'          => ['desc' => 'Leads Novos',                 'unidade' => 'QTD', 'tipo' => 'min'],
            'oportunidades_ganhas' => ['desc' => 'Oportunidades Ganhas',        'unidade' => 'QTD', 'tipo' => 'min'],
            'clientes_ativos'      => ['desc' => 'Clientes Ativos',             'unidade' => 'QTD', 'tipo' => 'min'],
            'valor_ganho'          => ['desc' => 'Valor Ganho (R$)',            'unidade' => 'BRL', 'tipo' => 'min'],
            'win_rate'             => ['desc' => 'Win Rate (%)',                'unidade' => 'PCT', 'tipo' => 'min'],
            'taxa_retencao'        => ['desc' => 'Taxa de Retenção (%)',        'unidade' => 'PCT', 'tipo' => 'min'],
        ],
        'processos_internos' => [
            'sla_percentual'       => ['desc' => 'SLA (% dentro do prazo)',     'unidade' => 'PCT', 'tipo' => 'min'],
            'throughput'           => ['desc' => 'Throughput (concluídos)',      'unidade' => 'QTD', 'tipo' => 'min'],
            'backlog'              => ['desc' => 'Backlog (máximo)',             'unidade' => 'QTD', 'tipo' => 'max'],
            'sem_movimentacao'     => ['desc' => 'Processos s/ Movimentação',   'unidade' => 'QTD', 'tipo' => 'max'],
            'horas_trabalhadas'    => ['desc' => 'Horas Trabalhadas',           'unidade' => 'QTD', 'tipo' => 'min'],
        ],
    ];

    /**
     * Busca meta de um KPI para ano/mês específico.
     * Retorna null se não existir.
     */
    public function getMeta(string $modulo, string $kpiKey, int $ano, int $mes): ?array
    {
        $row = DB::table('kpi_monthly_targets')
            ->where('modulo', $modulo)
            ->where('kpi_key', $kpiKey)
            ->where('year', $ano)
            ->where('month', $mes)
            ->whereNotNull('meta_valor')
            ->first();

        if (!$row) {
            return null;
        }

        return [
            'meta_valor' => (float) $row->meta_valor,
            'unidade'    => $row->unidade,
            'tipo_meta'  => $row->tipo_meta,
        ];
    }

    /**
     * Busca todas as metas de um módulo para ano/mês.
     * Retorna array indexado por kpi_key.
     */
    public function getMetasByModulo(string $modulo, int $ano, int $mes): array
    {
        $rows = DB::table('kpi_monthly_targets')
            ->where('modulo', $modulo)
            ->where('year', $ano)
            ->where('month', $mes)
            ->whereNotNull('meta_valor')
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->kpi_key] = [
                'meta_valor' => (float) $row->meta_valor,
                'unidade'    => $row->unidade,
                'tipo_meta'  => $row->tipo_meta,
            ];
        }

        return $result;
    }

    /**
     * Calcula delta% entre real e meta.
     * Retorna array com status visual para o KPI card.
     */
    public function calcularDelta(float $real, ?array $meta): array
    {
        if (!$meta || $meta['meta_valor'] == 0) {
            return [
                'meta'       => null,
                'delta'      => null,
                'delta_pct'  => null,
                'status'     => 'sem_meta',
                'cor'        => 'gray',
            ];
        }

        $metaVal = $meta['meta_valor'];
        $delta = $real - $metaVal;
        $deltaPct = ($metaVal != 0) ? round(($delta / $metaVal) * 100, 1) : 0;

        // min = piso (receita): acima = bom
        // max = teto (despesa): abaixo = bom
        if ($meta['tipo_meta'] === 'max') {
            $bom = $real <= $metaVal;
        } else {
            $bom = $real >= $metaVal;
        }

        // Faixas: >= meta = ok, >= 80% meta = atencao, < 80% = critico
        if ($meta['tipo_meta'] === 'max') {
            $ratio = ($metaVal != 0) ? $real / $metaVal : 0;
            $status = $ratio <= 1.0 ? 'ok' : ($ratio <= 1.2 ? 'atencao' : 'critico');
        } else {
            $ratio = ($metaVal != 0) ? $real / $metaVal : 0;
            $status = $ratio >= 1.0 ? 'ok' : ($ratio >= 0.8 ? 'atencao' : 'critico');
        }

        return [
            'meta'       => $metaVal,
            'delta'      => $delta,
            'delta_pct'  => $deltaPct,
            'status'     => $status,
            'cor'        => $status === 'ok' ? 'green' : ($status === 'atencao' ? 'yellow' : 'red'),
            'progresso'  => min(round($ratio * 100), 150), // cap at 150%
        ];
    }

    /**
     * Busca todas as metas para exibição na tela admin.
     */
    public function listarMetas(int $ano): array
    {
        return DB::table('kpi_monthly_targets')
            ->where('year', $ano)
            ->orderBy('modulo')
            ->orderBy('kpi_key')
            ->orderBy('mes')
            ->get()
            ->toArray();
    }

    /**
     * Processa importação XLS — recebe array parseado e grava.
     * Retorna contadores.
     */
    public function importar(array $linhas, int $userId): array
    {
        $inseridos = 0;
        $atualizados = 0;
        $ignorados = 0;
        $erros = [];

        foreach ($linhas as $i => $linha) {
            $modulo   = trim($linha['modulo'] ?? '');
            $kpiKey   = trim($linha['kpi_key'] ?? '');
            $ano      = (int) ($linha['ano'] ?? 0);
            $mes      = (int) ($linha['mes'] ?? 0);
            $metaVal  = $linha['meta_valor'];
            $unidade  = trim($linha['unidade'] ?? 'BRL');
            $tipoMeta = trim($linha['tipo_meta'] ?? 'min');

            // Validações
            if (!isset(self::KPI_REGISTRY[$modulo][$kpiKey])) {
                $erros[] = "Linha {$i}: KPI '{$modulo}.{$kpiKey}' não reconhecido.";
                continue;
            }

            if ($ano < 2020 || $ano > 2099 || $mes < 1 || $mes > 12) {
                $erros[] = "Linha {$i}: Ano/mês inválido ({$ano}/{$mes}).";
                continue;
            }

            // Meta vazia = pular (não criar registro)
            if ($metaVal === null || $metaVal === '') {
                $ignorados++;
                continue;
            }

            // Tratar separador de milhar (25.395 -> 25395, 1.200,50 -> 1200.50)
            if (is_string($metaVal)) {
                $metaVal = str_replace(' ', '', $metaVal);
                // Se tem virgula, formato BR (1.234,56)
                if (str_contains($metaVal, ',')) {
                    $metaVal = str_replace('.', '', $metaVal);
                    $metaVal = str_replace(',', '.', $metaVal);
                }
            }
            $metaFloat = (float) $metaVal;

            $existing = DB::table('kpi_monthly_targets')
                ->where('modulo', $modulo)
                ->where('kpi_key', $kpiKey)
                ->where('year', $ano)
                ->where('month', $mes)
                ->first();

            if ($existing) {
                DB::table('kpi_monthly_targets')
                    ->where('id', $existing->id)
                    ->update([
                        'meta_valor' => $metaFloat,
                        'unidade'    => $unidade,
                        'tipo_meta'  => $tipoMeta,
                        'descricao'  => $linha['descricao'] ?? null,
                        'updated_at' => now(),
                    ]);
                $atualizados++;
            } else {
                DB::table('kpi_monthly_targets')->insert([
                    'modulo'     => $modulo,
                    'kpi_key'    => $kpiKey,
                    'descricao'  => $linha['descricao'] ?? null,
                    'ano'        => $ano,
                    'mes'        => $mes,
                    'meta_valor' => $metaFloat,
                    'unidade'    => $unidade,
                    'tipo_meta'  => $tipoMeta,
                    'year'       => $ano,
                    'month'      => $mes,
                    'year'       => $ano,
                    'month'      => $mes,
                    'created_by' => $userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $inseridos++;
            }
        }

        Log::info("[KpiTargetService] Importação: {$inseridos} inseridos, {$atualizados} atualizados, {$ignorados} ignorados, " . count($erros) . " erros");

        return compact('inseridos', 'atualizados', 'ignorados', 'erros');
    }
}
