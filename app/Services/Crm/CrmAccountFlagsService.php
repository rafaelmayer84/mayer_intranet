<?php

namespace App\Services\Crm;

use Illuminate\Support\Facades\DB;

/**
 * Deriva as "marcas" rapidas de um cliente. Combina:
 *   - Automaticas: derivadas de contas_receber e decisoes de inadimplencia
 *   - Manuais: adicionadas pelo advogado via UI (crm_account_manual_flags)
 *     Manual sempre VENCE a automatica quando o codigo coincide.
 *
 * As marcas sao CUMULATIVAS: um cliente pode ter varias ao mesmo tempo.
 *
 * Convencao data_vencimento em contas_receber:
 *   NULL OU 2099-12-31 = cobranca travada (pode ser protesto OU judicial;
 *     o banco nao distingue, por isso a flag automatica eh generica).
 *     O advogado usa flag MANUAL para especificar COM_PROTESTO ou EM_EXECUCAO_JUDICIAL.
 */
class CrmAccountFlagsService
{
    public const CATALOGO_MANUAL = [
        'COM_PROTESTO'            => ['COM PROTESTO', 'red', 10, 'Protesto em cartório já efetivado.'],
        'EM_EXECUCAO_JUDICIAL'    => ['EM EXECUÇÃO JUDICIAL', 'red', 10, 'Ação de execução ajuizada.'],
        'EM_ACAO_MONITORIA'       => ['EM AÇÃO MONITÓRIA', 'red', 9, 'Ação monitória em curso.'],
        'EM_ACAO_COBRANCA'        => ['EM AÇÃO DE COBRANÇA', 'red', 9, 'Ação de cobrança em curso.'],
        'EM_RECUPERACAO_JUDICIAL' => ['EM RECUPERAÇÃO JUDICIAL', 'orange', 9, 'Cliente em recuperação judicial.'],
        'EM_FALENCIA'             => ['EM FALÊNCIA', 'gray', 9, 'Processo de falência.'],
        'VIP'                     => ['VIP', 'purple', 3, 'Cliente estratégico.'],
        'PARCEIRO'                => ['PARCEIRO', 'purple', 3, 'Parceiro comercial.'],
        'SOCIO_DE_PJ_CLIENTE'     => ['SÓCIO DE PJ CLIENTE', 'blue', 2, 'PF ligada a PJ cliente — contrato na empresa.'],
    ];

    public function calcular(int $accountId): array
    {
        $account = DB::table('crm_accounts')->where('id', $accountId)->first();
        if (!$account) return [];

        $djId = $account->datajuri_pessoa_id;
        $flags = [];

        // ─── Automáticas (derivadas de dados) ───────────────────────────
        if ($djId) {
            $cr = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $djId)
                ->where('is_stale', false)
                ->whereNotIn('status', ['Excluido', 'Excluído', 'Concluído', 'Concluido'])
                ->where('valor', '>', 0)
                ->get(['id', 'valor', 'data_vencimento', 'status']);

            // NULL ou 2099-12-31 = cobranca travada (nao distingue)
            $travadas = $cr->filter(fn($c) =>
                is_null($c->data_vencimento) || $c->data_vencimento === '2099-12-31'
            );
            $vencidas = $cr->filter(fn($c) =>
                $c->data_vencimento && $c->data_vencimento !== '2099-12-31'
                && $c->data_vencimento < now()->toDateString()
            )->sum('valor');

            if ($travadas->isNotEmpty()) {
                $flags[] = [
                    'codigo'  => 'COBRANCA_TRAVADA',
                    'label'   => 'COM COBRANÇA TRAVADA',
                    'color'   => 'red',
                    'peso'    => 7,
                    'origem'  => 'auto',
                    'tooltip' => sprintf('R$ %s em %d conta(s) sem vencimento ou com 2099-12-31. Pode ser protesto OU judicial — use marca manual para especificar.',
                        number_format($travadas->sum('valor'), 2, ',', '.'),
                        $travadas->count()),
                ];
            }
            if ($vencidas > 0) {
                $flags[] = [
                    'codigo'  => 'CONTAS_VENCIDAS',
                    'label'   => 'COM CONTAS VENCIDAS',
                    'color'   => 'orange',
                    'peso'    => 6,
                    'origem'  => 'auto',
                    'tooltip' => sprintf('R$ %s em contas vencidas.', number_format($vencidas, 2, ',', '.')),
                ];
            }
        }

        // Decisões de inadimplência ativas
        $dec = DB::table('crm_inadimplencia_decisoes')
            ->where('account_id', $accountId)
            ->where('status', 'ativa')
            ->orderByDesc('created_at')
            ->first(['decisao', 'justificativa']);

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
                    'origem'  => 'decisao',
                    'tooltip' => $tip . ($dec->justificativa ? ' · ' . $dec->justificativa : ''),
                ];
            }
        }

        // ─── Manuais (tabela crm_account_manual_flags) ──────────────────
        $manuais = DB::table('crm_account_manual_flags')
            ->where('account_id', $accountId)
            ->whereNull('removed_at')
            ->get(['codigo', 'nota']);

        foreach ($manuais as $m) {
            if (!isset(self::CATALOGO_MANUAL[$m->codigo])) continue;
            [$label, $color, $peso, $hint] = self::CATALOGO_MANUAL[$m->codigo];
            $flags[] = [
                'codigo'  => $m->codigo,
                'label'   => $label,
                'color'   => $color,
                'peso'    => $peso + 10, // manual sempre acima das automaticas
                'origem'  => 'manual',
                'tooltip' => $hint . ($m->nota ? ' · ' . $m->nota : ''),
            ];
        }

        // Se flags manuais específicas de cobrança existem, remove a genérica COBRANCA_TRAVADA
        $manualesCobranca = ['COM_PROTESTO', 'EM_EXECUCAO_JUDICIAL', 'EM_ACAO_MONITORIA', 'EM_ACAO_COBRANCA'];
        $temCobrancaManual = collect($manuais)->whereIn('codigo', $manualesCobranca)->isNotEmpty();
        if ($temCobrancaManual) {
            $flags = array_values(array_filter($flags, fn($f) => $f['codigo'] !== 'COBRANCA_TRAVADA'));
        }

        // Ordena por peso desc
        usort($flags, fn($a, $b) => $b['peso'] <=> $a['peso']);

        return $flags;
    }

    public function catalogoLista(): array
    {
        $out = [];
        foreach (self::CATALOGO_MANUAL as $codigo => [$label, $color, $peso, $hint]) {
            $out[] = compact('codigo', 'label', 'color', 'peso', 'hint');
        }
        return $out;
    }
}
