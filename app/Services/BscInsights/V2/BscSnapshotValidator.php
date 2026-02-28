<?php

namespace App\Services\BscInsights\V2;

class BscSnapshotValidator
{
    private array $issues = [];
    private array $config;

    public function __construct()
    {
        $this->config = config('bsc_insights.validator', []);
    }

    public function validate(array $snapshot): array
    {
        $this->issues = [];
        $this->validateMeta($snapshot);
        $this->validateFinance($snapshot['finance'] ?? []);
        $this->validateInadimplencia($snapshot['inadimplencia'] ?? []);
        $this->validateClientes($snapshot['clientes'] ?? []);
        $this->validateLeads($snapshot['leads'] ?? []);
        $this->validateCrm($snapshot['crm'] ?? []);
        $this->validateProcessos($snapshot['processos'] ?? []);
        $this->validateHoras($snapshot['horas'] ?? []);
        $this->validateAtendimento($snapshot['atendimento'] ?? []);
        $this->validateBlockErrors($snapshot['_errors'] ?? []);

        $criticals = count(array_filter($this->issues, fn($i) => $i['severity'] === 'error'));
        $warnings  = count(array_filter($this->issues, fn($i) => $i['severity'] === 'warning'));

        return [
            'valid'          => $criticals === 0,
            'issues'         => $this->issues,
            'critical_count' => $criticals,
            'warning_count'  => $warnings,
        ];
    }

    private function validateMeta(array $s): void
    {
        if (empty($s['meta'])) { $this->error('meta', 'Bloco meta ausente'); return; }
        if (empty($s['meta']['periodo_inicio']) || empty($s['meta']['periodo_fim'])) {
            $this->error('meta', 'Periodo inicio/fim ausente');
        }
    }

    private function validateFinance(array $fin): void
    {
        if ($this->isUnavailable($fin, 'finance')) return;
        $series = ['receita_total_mensal', 'despesas_mensal', 'resultado_mensal'];
        foreach ($series as $key) {
            if (empty($fin[$key]) || !is_array($fin[$key])) {
                $this->error('finance', "Serie {$key} vazia ou ausente");
            }
        }
        $receita = $fin['receita_total_mensal'] ?? [];
        $despesas = $fin['despesas_mensal'] ?? [];
        $deducoes = $fin['deducoes_mensal'] ?? [];
        $resultado = $fin['resultado_mensal'] ?? [];

        foreach ($resultado as $mes => $val) {
            $rec = $receita[$mes] ?? null;
            $desp = $despesas[$mes] ?? null;
            $ded = $deducoes[$mes] ?? 0;
            if ($rec === null || $desp === null) continue;
            $expected = $rec - $desp - $ded;
            if (abs($val - $expected) > 1) {
                $this->error('finance', "Resultado {$mes}: snapshot={$val}, esperado={$expected}");
            }
        }

        foreach ($receita as $mes => $val) {
            if ($val < 0) $this->warning('finance', "Receita negativa em {$mes}: {$val}");
        }
        foreach ($despesas as $mes => $val) {
            if ($val < 0) $this->warning('finance', "Despesas negativas em {$mes}: {$val}");
        }
    }

    private function validateInadimplencia(array $inad): void
    {
        if ($this->isUnavailable($inad, 'inadimplencia')) return;
        $total = $inad['total_vencido'] ?? 0;
        $qtd = $inad['qtd_vencidas'] ?? 0;
        if ($total > 0 && $qtd == 0) $this->error('inadimplencia', "Total vencido > 0 mas qtd = 0");
        if ($total == 0 && $qtd > 0) $this->warning('inadimplencia', "qtd > 0 mas total = 0");
        $aging = $inad['aging_buckets'] ?? [];
        if (!empty($aging) && $total > 0) {
            $sumAging = array_sum($aging);
            if (abs($sumAging - $total) > 100) {
                $this->warning('inadimplencia', "Aging soma {$sumAging} vs total {$total}");
            }
        }
    }

    private function validateClientes(array $cli): void
    {
        if ($this->isUnavailable($cli, 'clientes')) return;
        if (($cli['total_clientes'] ?? 0) == 0) $this->warning('clientes', 'Total clientes = 0');
    }

    private function validateLeads(array $leads): void
    {
        if ($this->isUnavailable($leads, 'leads')) return;
        $total = $leads['total'] ?? 0;
        $sumStatus = array_sum($leads['por_status'] ?? []);
        if ($total > 0 && $sumStatus > 0 && abs($total - $sumStatus) > 0) {
            $this->warning('leads', "Total ({$total}) != soma por_status ({$sumStatus})");
        }
    }

    private function validateCrm(array $crm): void
    {
        if ($this->isUnavailable($crm, 'crm')) return;
        $opps = $crm['oportunidades'] ?? [];
        if (!empty($opps)) {
            $winRate = $opps['win_rate'] ?? 0;
            if ($winRate > 100) $this->error('crm', "Win rate {$winRate}% > 100%");
        }
    }

    private function validateProcessos(array $proc): void
    {
        if ($this->isUnavailable($proc, 'processos')) return;
        $ativos = $proc['ativos'] ?? 0;
        $sem90 = $proc['sem_movimentacao_90d'] ?? 0;
        if ($sem90 > $ativos && $ativos > 0) {
            $this->warning('processos', "sem_movimentacao_90d ({$sem90}) > ativos ({$ativos})");
        }
    }

    private function validateHoras(array $horas): void
    {
        if ($this->isUnavailable($horas, 'horas')) return;
        $mensal = $horas['horas_mensal'] ?? [];
        $totalReg = $horas['total_registros'] ?? 0;
        if (!empty($mensal) && array_sum($mensal) == 0 && $totalReg > 0) {
            $this->error('horas', "{$totalReg} registros mas horas = 0 — campo de calculo errado");
        }
    }

    private function validateAtendimento(array $atend): void
    {
        if ($this->isUnavailable($atend, 'atendimento')) return;
        $total = $atend['total_conversas'] ?? 0;
        $semResp = $atend['sem_resposta'] ?? 0;
        if ($semResp > $total && $total > 0) {
            $this->error('atendimento', "sem_resposta ({$semResp}) > total ({$total})");
        }
    }

    private function validateBlockErrors(array $errors): void
    {
        foreach ($errors as $err) {
            $this->error($err['bloco'] ?? 'unknown', 'Bloco falhou: ' . ($err['mensagem'] ?? ''));
        }
    }

    private function isUnavailable(array $block, string $name): bool
    {
        if (isset($block['_status']) && $block['_status'] === 'unavailable') {
            $this->error($name, 'Bloco indisponivel');
            return true;
        }
        if (empty($block)) { $this->warning($name, 'Bloco vazio'); return true; }
        return false;
    }

    private function error(string $b, string $m): void { $this->issues[] = ['severity' => 'error', 'block' => $b, 'message' => $m]; }
    private function warning(string $b, string $m): void { $this->issues[] = ['severity' => 'warning', 'block' => $b, 'message' => $m]; }
    private function info(string $b, string $m): void { $this->issues[] = ['severity' => 'info', 'block' => $b, 'message' => $m]; }
}
