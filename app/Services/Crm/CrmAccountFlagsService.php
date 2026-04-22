<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;

/**
 * Deriva as "marcas" rapidas de um cliente a partir da realidade observavel
 * (contas_receber, decisoes de inadimplencia, status DJ). Sao badges visuais
 * que aparecem no topo da ficha — info rapida e acessivel.
 *
 * As marcas sao CUMULATIVAS: um cliente pode ter COM PROTESTO e EM ACORDO,
 * ou COM EXECUCAO JUDICIAL e CONTRATO SINISTRADO ao mesmo tempo.
 *
 * Convenção data_vencimento em contas_receber:
 *   NULL        = protesto / pendente judicial (memória: projeto_inadimplencia_regra)
 *   2099-12-31  = judicial / indefinido
 */
class CrmAccountFlagsService
{
    public function calcular(int $accountId): array
    {
        $account = DB::table('crm_accounts')->where('id', $accountId)->first();
        if (!$account) return [];

        $djId = $account->datajuri_pessoa_id;
        $flags = [];

        if ($djId) {
            $cr = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $djId)
                ->where('is_stale', false)
                ->whereNotIn('status', ['Excluido', 'Excluído', 'Concluído', 'Concluido'])
                ->where('valor', '>', 0)
                ->get(['id', 'valor', 'data_vencimento', 'status']);

            $comProtesto = $cr->filter(fn($c) => is_null($c->data_vencimento))->sum('valor');
            $comJudicial = $cr->filter(fn($c) => $c->data_vencimento === '2099-12-31')->sum('valor');
            $vencidas = $cr->filter(fn($c) =>
                $c->data_vencimento && $c->data_vencimento !== '2099-12-31'
                && $c->data_vencimento < now()->toDateString()
            )->sum('valor');

            if ($comProtesto > 0) {
                $flags[] = [
                    'codigo'  => 'COM_PROTESTO',
                    'label'   => 'COM PROTESTO',
                    'color'   => 'red',
                    'peso'    => 10,
                    'tooltip' => sprintf('R$ %s em contas sem data de vencimento (convenção protesto).',
                        number_format($comProtesto, 2, ',', '.')),
                ];
            }
            if ($comJudicial > 0) {
                $flags[] = [
                    'codigo'  => 'EM_EXECUCAO_JUDICIAL',
                    'label'   => 'COM EXECUÇÃO JUDICIAL',
                    'color'   => 'red',
                    'peso'    => 10,
                    'tooltip' => sprintf('R$ %s em contas com vencimento 2099-12-31 (convenção judicial).',
                        number_format($comJudicial, 2, ',', '.')),
                ];
            }
            if ($vencidas > 0 && $comProtesto == 0 && $comJudicial == 0) {
                $flags[] = [
                    'codigo'  => 'COM_CONTAS_VENCIDAS',
                    'label'   => 'COM CONTAS VENCIDAS',
                    'color'   => 'orange',
                    'peso'    => 6,
                    'tooltip' => sprintf('R$ %s em contas vencidas (ainda não protestadas).',
                        number_format($vencidas, 2, ',', '.')),
                ];
            }
        }

        // Decisões ativas de inadimplência
        $dec = DB::table('crm_inadimplencia_decisoes')
            ->where('account_id', $accountId)
            ->where('status', 'ativa')
            ->orderByDesc('created_at')
            ->first(['decisao', 'justificativa', 'sinistro_notas']);

        if ($dec) {
            $byDecisao = [
                'sinistrar' => ['SINISTRADO', 'gray', 'Contrato sinistrado — cobranças suprimidas.'],
                'judicial'  => ['EM AÇÃO JUDICIAL', 'red', 'Decisão: ajuizar ação judicial.'],
                'protestar' => ['PROTESTADO (decisão)', 'red', 'Decisão: protesto em cartório.'],
                'acordo'    => ['EM ACORDO', 'blue', 'Acordo de parcelamento ativo.'],
                'aguardar'  => ['AGUARDANDO', 'yellow', 'Aguardando ação do cliente.'],
                'cobrar'    => ['EM COBRANÇA', 'amber', 'Cobrança amigável em curso.'],
            ];
            if (isset($byDecisao[$dec->decisao])) {
                [$label, $color, $tip] = $byDecisao[$dec->decisao];
                $flags[] = [
                    'codigo'  => 'DECISAO_' . strtoupper($dec->decisao),
                    'label'   => $label,
                    'color'   => $color,
                    'peso'    => 8,
                    'tooltip' => $tip . ($dec->justificativa ? ' · ' . $dec->justificativa : ''),
                ];
            }
        }

        // Ordena por peso desc (mais graves primeiro)
        usort($flags, fn($a, $b) => $b['peso'] <=> $a['peso']);

        return $flags;
    }
}
