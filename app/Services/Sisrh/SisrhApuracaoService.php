<?php

namespace App\Services\Sisrh;

use App\Models\GdpRemuneracaoFaixa;
use App\Models\SisrhApuracao;
use App\Models\SisrhBancoCreditoMov;
use App\Models\SisrhRbNivel;
use App\Models\SisrhRbOverride;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Motor de apuração de Remuneração Variável (RV) do SISRH.
 *
 * Fórmula:
 *   1. captacao = receita efetivamente recebida (F1 GDP)
 *   2. percentual_faixa = f(gdp_score) via gdp_remuneracao_faixas
 *   3. rv_bruta = captacao * percentual_faixa / 100
 *   4. reducao_total = min(conformidade + acompanhamento, 40%)
 *   5. rv_pos_reducoes = rv_bruta * (1 - reducao_total / 100)
 *   6. teto = rb_valor * 0.50
 *   7. rv_aplicada = min(rv_pos_reducoes + credito_utilizado, teto)
 *   8. excedente = max(0, rv_pos_reducoes - teto) → banco créditos
 *
 * Bloqueios:
 *   - Sem plano vigente (acordo aceito) → rv = 0, motivo = blocked_by_plan
 *   - Sem score GDP → rv = 0, motivo = blocked_by_score
 */
class SisrhApuracaoService
{
    private const TETO_RV_PERCENTUAL = 50.00; // 50% da RB
    private const CAP_REDUCAO_TOTAL = 40.00;

    private SisrhCaptacaoService $captacaoService;
    private SisrhScoreService $scoreService;
    private SisrhConformidadeImpactService $conformidadeService;
    private SisrhAcompanhamentoService $acompanhamentoService;

    public function __construct(
        SisrhCaptacaoService $captacaoService,
        SisrhScoreService $scoreService,
        SisrhConformidadeImpactService $conformidadeService,
        SisrhAcompanhamentoService $acompanhamentoService
    ) {
        $this->captacaoService = $captacaoService;
        $this->scoreService = $scoreService;
        $this->conformidadeService = $conformidadeService;
        $this->acompanhamentoService = $acompanhamentoService;
    }

    /**
     * Executa a apuração para um usuário em mês/ano.
     * Pode ser chamada em modo simulação (não persiste) ou fechamento (persiste).
     *
     * @return array Dados da apuração calculada
     */
    public function apurar(int $userId, int $ano, int $mes, int $cicloId, bool $persistir = false, bool $ignorarBloqueio = false): array
    {
        // Verificar se já existe apuração fechada
        $existente = SisrhApuracao::where('user_id', $userId)
            ->where('ano', $ano)
            ->where('mes', $mes)
            ->where('status', 'closed')
            ->first();

        if ($existente) {
            return [
                'erro' => 'Apuração já fechada para este período.',
                'apuracao_id' => $existente->id,
            ];
        }

        // 1. Obter RB vigente
        $rbValor = $this->obterRb($userId, $cicloId);

        // 2. Verificar plano vigente
        $temPlano = $this->scoreService->temPlanoVigente($userId, $cicloId);

        // 3. Obter score GDP
        $gdpScore = $this->scoreService->scoreMensal($userId, $ano, $mes);

        // Bloqueios
        $bloqueioMotivo = null;
        if (!$temPlano && !$ignorarBloqueio) {
            $bloqueioMotivo = 'blocked_by_plan';
        } elseif ($gdpScore === null && !$ignorarBloqueio) {
            $bloqueioMotivo = 'blocked_by_score';
        }

        if ($bloqueioMotivo) {
            $dados = $this->dadosBloqueados($userId, $ano, $mes, $cicloId, $rbValor, $gdpScore, $bloqueioMotivo);
            if ($persistir) {
                $this->persistirApuracao($dados);
            }
            return $dados;
        }

        // 4. Captação
        $captacao = $this->captacaoService->captacaoMensal($userId, $ano, $mes);

        // 5. Faixa GDP → percentual
        $percentualFaixa = GdpRemuneracaoFaixa::percentualParaScore($cicloId, $gdpScore ?? 0.0);

        // 6. RV Bruta
        $rvBruta = round($captacao * $percentualFaixa / 100, 2);

        // 7. Reduções
        $reducaoConformidade = $this->conformidadeService->reducaoConformidade($userId, $ano, $mes);
        $reducaoAcompanhamento = $this->acompanhamentoService->reducaoAcompanhamento($userId, $cicloId, $ano, $mes);
        $reducaoTotal = min($reducaoConformidade + $reducaoAcompanhamento, self::CAP_REDUCAO_TOTAL);

        // 8. RV pós reduções
        $rvPosReducoes = round($rvBruta * (1 - $reducaoTotal / 100), 2);

        // 9. Teto
        $tetoRv = round($rbValor * self::TETO_RV_PERCENTUAL / 100, 2);

        // 10. Banco de créditos - uso automático se RV < teto e há saldo
        $creditoUtilizado = 0.00;
        if ($rvPosReducoes < $tetoRv) {
            $saldoBanco = SisrhBancoCreditoMov::saldo($userId);
            if ($saldoBanco > 0) {
                $espacoDisponivel = $tetoRv - $rvPosReducoes;
                $creditoUtilizado = round(min($saldoBanco, $espacoDisponivel), 2);
            }
        }

        // 11. RV aplicada e excedente
        $rvComCredito = $rvPosReducoes + $creditoUtilizado;
        $rvAplicada = round(min($rvComCredito, $tetoRv), 2);
        $excedente = round(max(0, $rvPosReducoes - $tetoRv), 2);

        // Snapshot para auditoria
        $conformidadeDetalhe = $this->conformidadeService->detalhamento($userId, $ano, $mes);
        $acompStatus = $this->acompanhamentoService->statusAcompanhamento($userId, $cicloId, $ano, $mes);

        $snapshotData = [
            'user_id' => $userId,
            'ano' => $ano,
            'mes' => $mes,
            'ciclo_id' => $cicloId,
            'rb_valor' => $rbValor,
            'captacao_valor' => $captacao,
            'gdp_score' => $gdpScore,
            'percentual_faixa' => $percentualFaixa,
            'rv_bruta' => $rvBruta,
            'reducao_conformidade_pct' => $reducaoConformidade,
            'reducao_acompanhamento_pct' => $reducaoAcompanhamento,
            'reducao_total_pct' => $reducaoTotal,
            'rv_pos_reducoes' => $rvPosReducoes,
            'teto_rv_valor' => $tetoRv,
            'credito_utilizado' => $creditoUtilizado,
            'rv_aplicada' => $rvAplicada,
            'rv_excedente_credito' => $excedente,
            'conformidade_detalhes' => $conformidadeDetalhe,
            'acompanhamento_status' => $acompStatus,
            'saldo_banco_pre' => SisrhBancoCreditoMov::saldo($userId),
            'tem_plano_vigente' => $temPlano,
            'calculado_em' => now()->toIso8601String(),
        ];

        $snapshotJson = json_encode($snapshotData, JSON_UNESCAPED_UNICODE);
        $snapshotHash = hash('sha256', $snapshotJson);

        $dados = [
            'user_id' => $userId,
            'ano' => $ano,
            'mes' => $mes,
            'ciclo_id' => $cicloId,
            'rb_valor' => $rbValor,
            'captacao_valor' => $captacao,
            'gdp_score' => $gdpScore,
            'percentual_faixa' => $percentualFaixa,
            'rv_bruta' => $rvBruta,
            'reducao_conformidade_pct' => $reducaoConformidade,
            'reducao_acompanhamento_pct' => $reducaoAcompanhamento,
            'reducao_total_pct' => $reducaoTotal,
            'rv_pos_reducoes' => $rvPosReducoes,
            'teto_rv_valor' => $tetoRv,
            'rv_aplicada' => $rvAplicada,
            'rv_excedente_credito' => $excedente,
            'credito_utilizado' => $creditoUtilizado,
            'status' => 'open',
            'bloqueio_motivo' => null,
            'snapshot_json' => $snapshotData,
            'snapshot_hash' => $snapshotHash,
        ];

        if ($persistir) {
            $this->persistirApuracao($dados);
        }

        return $dados;
    }

    /**
     * Fecha (consolida) a apuração: marca como closed, registra banco de créditos.
     */
    public function fechar(int $apuracaoId, int $closedBy): SisrhApuracao
    {
        $apuracao = SisrhApuracao::findOrFail($apuracaoId);

        if ($apuracao->isClosed()) {
            throw new \RuntimeException('Apuração já está fechada.');
        }

        return DB::transaction(function () use ($apuracao, $closedBy) {
            // Fechar
            $apuracao->update([
                'status' => 'closed',
                'closed_by' => $closedBy,
                'closed_at' => now(),
            ]);

            // Registrar excedente no banco de créditos
            if ($apuracao->rv_excedente_credito > 0) {
                SisrhBancoCreditoMov::create([
                    'user_id' => $apuracao->user_id,
                    'ano' => $apuracao->ano,
                    'mes' => $apuracao->mes,
                    'tipo' => 'credit',
                    'valor' => $apuracao->rv_excedente_credito,
                    'origem_apuracao_id' => $apuracao->id,
                    'motivo' => 'Excedente RV acima do teto',
                    'created_by' => $closedBy,
                ]);
            }

            // Registrar uso de crédito (debit)
            if ($apuracao->credito_utilizado > 0) {
                SisrhBancoCreditoMov::create([
                    'user_id' => $apuracao->user_id,
                    'ano' => $apuracao->ano,
                    'mes' => $apuracao->mes,
                    'tipo' => 'debit',
                    'valor' => $apuracao->credito_utilizado,
                    'origem_apuracao_id' => $apuracao->id,
                    'motivo' => 'Uso automático de crédito para completar RV',
                    'created_by' => $closedBy,
                ]);
            }

            // Audit log
            DB::table('audit_logs')->insert([
                'user_id' => $closedBy,
                'user_name' => Auth::user()->name ?? 'Sistema',
                'user_role' => Auth::user()->role ?? 'system',
                'action' => 'sisrh_fechamento',
                'module' => 'sisrh',
                'description' => "Fechamento apuração #{$apuracao->id} | User:{$apuracao->user_id} | {$apuracao->mes}/{$apuracao->ano} | RV: R$ " . number_format($apuracao->rv_aplicada, 2, ',', '.'),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'route' => request()->path(),
                'method' => request()->method(),
                'created_at' => now(),
            ]);

            return $apuracao->fresh();
        });
    }

    /**
     * Obtém a RB vigente para o usuário: override > nível > fallback 0.
     */
    private function obterRb(int $userId, int $cicloId): float
    {
        // Override tem prioridade
        $override = SisrhRbOverride::where('user_id', $userId)
            ->where('ciclo_id', $cicloId)
            ->first();

        if ($override) {
            return $override->valor_rb;
        }

        // Buscar nível do usuário
        $nivel = DB::table('users')->where('id', $userId)->value('nivel_senioridade');

        if ($nivel) {
            $rbNivel = SisrhRbNivel::where('nivel', $nivel)
                ->where('ciclo_id', $cicloId)
                ->first();

            if ($rbNivel) {
                return $rbNivel->valor_rb;
            }
        }

        return 0.00;
    }

    /**
     * Gera dados de apuração bloqueada (sem plano ou sem score).
     */
    private function dadosBloqueados(int $userId, int $ano, int $mes, int $cicloId, float $rbValor, ?float $gdpScore, string $motivo): array
    {
        $snapshotData = [
            'user_id' => $userId,
            'ano' => $ano,
            'mes' => $mes,
            'ciclo_id' => $cicloId,
            'rb_valor' => $rbValor,
            'bloqueio_motivo' => $motivo,
            'calculado_em' => now()->toIso8601String(),
        ];

        return [
            'user_id' => $userId,
            'ano' => $ano,
            'mes' => $mes,
            'ciclo_id' => $cicloId,
            'rb_valor' => $rbValor,
            'captacao_valor' => 0,
            'gdp_score' => $gdpScore ?? 0,
            'percentual_faixa' => 0,
            'rv_bruta' => 0,
            'reducao_conformidade_pct' => 0,
            'reducao_acompanhamento_pct' => 0,
            'reducao_total_pct' => 0,
            'rv_pos_reducoes' => 0,
            'teto_rv_valor' => round($rbValor * self::TETO_RV_PERCENTUAL / 100, 2),
            'rv_aplicada' => 0,
            'rv_excedente_credito' => 0,
            'credito_utilizado' => 0,
            'status' => 'open',
            'bloqueio_motivo' => $motivo,
            'snapshot_json' => $snapshotData,
            'snapshot_hash' => hash('sha256', json_encode($snapshotData)),
        ];
    }

    /**
     * Persiste (updateOrCreate) a apuração.
     */
    private function persistirApuracao(array $dados): SisrhApuracao
    {
        return SisrhApuracao::updateOrCreate(
            [
                'user_id' => $dados['user_id'],
                'ano' => $dados['ano'],
                'mes' => $dados['mes'],
            ],
            collect($dados)->except(['erro'])->toArray()
        );
    }
}
