<?php

namespace App\Services\BscInsights\V2;

class BscDerivedMetricsService
{
    public function calculate(array $snapshot): array
    {
        $d = [];
        $d['finance'] = $this->deriveFinance($snapshot['finance'] ?? []);
        $d['inadimplencia'] = $this->deriveInadimplencia($snapshot['inadimplencia'] ?? []);
        $d['clientes'] = $this->deriveClientes($snapshot['clientes'] ?? []);
        $d['leads'] = $this->deriveLeads($snapshot['leads'] ?? []);
        $d['processos'] = $this->deriveProcessos($snapshot['processos'] ?? []);
        $d['horas'] = $this->deriveHoras($snapshot['horas'] ?? []);
        $d['data_quality'] = $this->deriveDataQuality($snapshot);
        return $d;
    }

    private function deriveFinance(array $fin): array
    {
        if (empty($fin) || isset($fin['_status'])) return [];
        $receita = $fin['receita_total_mensal'] ?? [];
        $despesas = $fin['despesas_mensal'] ?? [];
        $d = [];
        $d['receita_rolling_3m'] = $this->rolling($receita, 3);
        $d['despesas_rolling_3m'] = $this->rolling($despesas, 3);
        $d['receita_var_mensal'] = $this->mom($receita);
        $d['despesas_var_mensal'] = $this->mom($despesas);
        $last3 = array_slice($despesas, -3, 3, true);
        $d['burn_rate_mensal'] = count($last3) > 0 ? round(array_sum($last3) / count($last3), 2) : 0;
        $d['receita_acum_trimestre'] = round(array_sum(array_slice($receita, -3, 3, true)), 2);
        $d['tendencia_receita'] = $this->trend($receita);
        return $d;
    }

    private function deriveInadimplencia(array $inad): array
    {
        if (empty($inad) || isset($inad['_status'])) return [];
        $d = [];
        $top5 = $inad['top5_devedores'] ?? [];
        $total = $inad['total_vencido'] ?? 0;
        if ($total > 0 && !empty($top5)) {
            $d['concentracao_top5_pct'] = round(array_sum(array_column($top5, 'total')) / $total * 100, 1);
        }
        $aging = $inad['aging_buckets'] ?? [];
        if ($total > 0 && !empty($aging)) {
            $d['aging_pct'] = [];
            foreach ($aging as $bucket => $val) $d['aging_pct'][$bucket] = round($val / $total * 100, 1);
        }
        return $d;
    }

    private function deriveClientes(array $cli): array
    {
        if (empty($cli) || isset($cli['_status'])) return [];
        $novos = $cli['novos_clientes_mensal'] ?? [];
        return ['novos_var_mensal' => $this->mom($novos), 'novos_acum_trimestre' => array_sum(array_slice($novos, -3, 3, true))];
    }

    private function deriveLeads(array $leads): array
    {
        if (empty($leads) || isset($leads['_status'])) return [];
        $mensal = $leads['leads_mensal'] ?? [];
        $total = $leads['total'] ?? 0;
        $d = ['leads_var_mensal' => $this->mom($mensal)];
        if ($total > 0) {
            $d['status_pct'] = [];
            foreach (($leads['por_status'] ?? []) as $s => $q) $d['status_pct'][$s] = round($q / $total * 100, 1);
        }
        return $d;
    }

    private function deriveProcessos(array $proc): array
    {
        if (empty($proc) || isset($proc['_status'])) return [];
        $novos = $proc['novos_mensal'] ?? [];
        $enc = $proc['encerrados_mensal'] ?? [];
        $d = ['throughput_liquido' => []];
        foreach ($novos as $m => $v) $d['throughput_liquido'][$m] = $v - ($enc[$m] ?? 0);
        $ativos = $proc['ativos'] ?? 0;
        $d['pct_parados_90d'] = $ativos > 0 ? round(($proc['sem_movimentacao_90d'] ?? 0) / $ativos * 100, 1) : 0;
        return $d;
    }

    private function deriveHoras(array $horas): array
    {
        if (empty($horas) || isset($horas['_status'])) return [];
        if (($horas['_data_quality'] ?? '') === 'unreliable') return ['_unreliable' => true];
        $mensal = $horas['horas_mensal'] ?? [];
        return ['horas_var_mensal' => $this->mom($mensal), 'horas_rolling_3m' => $this->rolling($mensal, 3)];
    }

    private function deriveDataQuality(array $snapshot): array
    {
        $blocks = ['finance','inadimplencia','clientes','leads','crm','processos','atendimento','tickets','horas'];
        $avail = 0; $unavail = [];
        foreach ($blocks as $b) {
            $bl = $snapshot[$b] ?? [];
            if (empty($bl) || isset($bl['_status'])) $unavail[] = $b; else $avail++;
        }
        $total = count($blocks);
        return ['integrity_pct' => $total > 0 ? round($avail / $total * 100, 0) : 0, 'available_blocks' => $avail, 'total_blocks' => $total, 'unavailable' => $unavail];
    }

    private function rolling(array $s, int $w): ?float
    {
        $v = array_values($s);
        $last = array_slice($v, -$w, $w);
        return count($last) >= $w ? round(array_sum($last) / $w, 2) : null;
    }

    private function mom(array $s): array
    {
        $keys = array_keys($s); $vars = [];
        for ($i = 1; $i < count($keys); $i++) {
            $p = $s[$keys[$i-1]]; $c = $s[$keys[$i]];
            $vars[$keys[$i]] = $p != 0 ? round(($c - $p) / abs($p) * 100, 1) : null;
        }
        return $vars;
    }

    private function trend(array $s): string
    {
        $v = array_values($s);
        if (count($v) < 6) return 'insuficiente';
        $r = array_slice($v, -3, 3); $e = array_slice($v, -6, 3);
        $ar = array_sum($r) / 3; $ae = array_sum($e) / 3;
        if ($ae == 0) return 'indeterminado';
        $pct = ($ar - $ae) / abs($ae) * 100;
        if ($pct > 10) return 'alta';
        if ($pct > 0) return 'leve_alta';
        if ($pct > -10) return 'leve_queda';
        return 'queda';
    }
}
