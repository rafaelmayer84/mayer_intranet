<?php

namespace App\Services\BscInsights\V2;

class BscSnapshotNormalizer
{
    private array $log = [];

    public function normalize(array $snapshot): array
    {
        $this->log = [];
        $blocks = ['meta','finance','inadimplencia','clientes','leads','crm','processos','atendimento','tickets','horas','gdp'];
        foreach ($blocks as $b) {
            if (!isset($snapshot[$b])) {
                $snapshot[$b] = [];
                $this->log("Bloco '{$b}' ausente — inicializado vazio");
            }
        }
        $snapshot['finance'] = $this->normalizeFinance($snapshot['finance']);
        $snapshot['leads'] = $this->normalizeLeads($snapshot['leads']);
        $snapshot['horas'] = $this->normalizeHoras($snapshot['horas']);
        $snapshot['crm'] = $this->normalizeCrm($snapshot['crm']);
        unset($snapshot['_errors']);
        $snapshot = $this->ensureArrays($snapshot);
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        $hash = hash('sha256', $json);
        return ['snapshot' => $snapshot, 'hash' => $hash, 'log' => $this->log];
    }

    private function normalizeFinance(array $fin): array
    {
        if (empty($fin) || isset($fin['_status'])) return $fin;
        foreach (['despesas_mensal', 'deducoes_mensal'] as $key) {
            if (isset($fin[$key]) && is_array($fin[$key])) {
                foreach ($fin[$key] as $mes => $val) {
                    if ($val < 0) {
                        $fin[$key][$mes] = abs($val);
                        $this->log("Finance: {$key} {$mes} convertida para positivo");
                    }
                }
            }
        }
        return $fin;
    }

    private function normalizeLeads(array $leads): array
    {
        if (empty($leads) || isset($leads['_status'])) return $leads;
        if (!isset($leads['por_status'])) return $leads;
        $map = ['New'=>'novo','new'=>'novo','Novo'=>'novo','Contacted'=>'contatado','contacted'=>'contatado','Contatado'=>'contatado','Converted'=>'convertido','converted'=>'convertido','Convertido'=>'convertido','Dead'=>'descartado','dead'=>'descartado','Descartado'=>'descartado','Archived'=>'arquivado','archived'=>'arquivado','Arquivado'=>'arquivado'];
        $normalized = [];
        foreach ($leads['por_status'] as $status => $qtd) {
            $key = $map[$status] ?? strtolower($status);
            $normalized[$key] = ($normalized[$key] ?? 0) + $qtd;
        }
        $leads['por_status'] = $normalized;
        $total = array_sum($normalized);
        $leads['taxa_conversao'] = $total > 0 ? round(($normalized['convertido'] ?? 0) / $total * 100, 1) : 0;
        return $leads;
    }

    private function normalizeHoras(array $horas): array
    {
        if (empty($horas) || isset($horas['_status'])) return $horas;
        $mensal = $horas['horas_mensal'] ?? [];
        if (!empty($mensal) && array_sum($mensal) == 0 && ($horas['total_registros'] ?? 0) > 0) {
            $horas['_data_quality'] = 'unreliable';
            $this->log('Horas: marcado unreliable (zero com registros existentes)');
        }
        return $horas;
    }

    private function normalizeCrm(array $crm): array
    {
        if (empty($crm) || isset($crm['_status'])) return $crm;
        if (isset($crm['oportunidades']) && is_array($crm['oportunidades'])) {
            $o = $crm['oportunidades'];
            $crm['oportunidades'] = ['total'=>$o['total']??0,'won'=>$o['won']??0,'lost'=>$o['lost']??0,'open'=>$o['open']??0,'win_rate'=>$o['win_rate']??0,'valor_pipeline'=>$o['valor_pipeline']??0];
        }
        return $crm;
    }

    private function ensureArrays(array $data): array
    {
        array_walk_recursive($data, function (&$value) { if ($value === null) $value = 0; });
        return $data;
    }

    private function log(string $msg): void { $this->log[] = $msg; }
}
