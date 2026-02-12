<?php

namespace App\Services;

use App\Models\ClassificacaoRegra;
use App\Models\Movimento;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassificacaoService
{
    /**
     * Classifica um movimento baseado em regras configuráveis
     *
     * @param string|null $codigoPlano Código do plano de contas
     * @param string|null $tipoMovimento Tipo do movimento (RECEITA, DESPESA)
     * @return string Classificação encontrada
     */
    public function classificar(?string $codigoPlano, ?string $tipoMovimento = null): string
    {
        if (empty($codigoPlano)) {
            return 'PENDENTE_CLASSIFICACAO';
        }

        // Fonte UNICA: tabela classificacao_regras (gerenciada pela UI)
        $regra = ClassificacaoRegra::buscarRegraMaisEspecifica($codigoPlano);

        if ($regra) {
            return $regra->classificacao;
        }

        // Sem regra = pendente (operador deve criar regra na UI)
        return 'PENDENTE_CLASSIFICACAO';
    }

    /**
     * Infere classificação baseada em padrões contábeis conhecidos.
     *
     * Mapeamento validado contra a "Árvore do Plano de Contas" (DataJuri):
     *
     *   3.01.01.*  = RECEITA BRUTA
     *     3.01.01.01 = Receita bruta - Contrato PF   -> RECEITA_PF
     *     3.01.01.02 = Receita Bruta - Contrato PJ   -> RECEITA_PJ
     *     3.01.01.03 = Receita bruta - PF Quota Litis -> RECEITA_PF
     *     3.01.01.05 = Receita bruta - PJ Quota Litis -> RECEITA_PJ
     *
     *   3.01.02.*  = OUTRAS RECEITAS OPERACIONAIS     -> RECEITA_PF (receita!)
     *     Ex: 3.01.02.06 Multas (valor POSITIVO na Arvore)
     *
     *   3.01.03.*  = DEDUCOES DA RECEITA              -> DEDUCAO
     *     3.01.03.01 Simples Nacional, 3.01.03.05 INSS, etc.
     *     (valores NEGATIVOS na Arvore)
     *
     *   3.02.*     = DESPESAS OPERACIONAIS             -> DESPESA
     *   3.03.*     = RECEITAS FINANCEIRAS              -> RECEITA_FINANCEIRA
     *   3.04.*     = DESPESAS FINANCEIRAS              -> DESPESA_FINANCEIRA
     *   2.*        = PASSIVO (balanco)                  -> PENDENTE (nao e P&L)
     *
     * CHANGELOG v3.0 (06/02/2026):
     *   - FIX CRITICO: 3.01.02.* era DEDUCAO, agora RECEITA_PF (receitas operacionais)
     *   - FIX CRITICO: 3.01.03.* caia no fallthrough 3.01->RECEITA_PF, agora DEDUCAO
     */
    private function inferirPorPadraoContabil(string $codigo): string
    {
        // ========================================
        // RECEITAS BRUTAS (3.01.01.xx)
        // ========================================

        // Receita PF: 3.01.01.01 (Contrato PF)
        if ($codigo === '3.01.01.01') {
            return 'RECEITA_PF';
        }

        // Receita PJ: 3.01.01.02 (Contrato PJ)
        if ($codigo === '3.01.01.02') {
            return 'RECEITA_PJ';
        }

        // Receita PF Quota Litis: 3.01.01.03
        if ($codigo === '3.01.01.03') {
            return 'RECEITA_PF';
        }

        // Receita PJ Quota Litis: 3.01.01.05
        if ($codigo === '3.01.01.05') {
            return 'RECEITA_PJ';
        }

        // Receitas genericas 3.01.01.xx - default PF
        if (preg_match('/^3\.01\.01\.\d+$/', $codigo)) {
            return 'RECEITA_PF';
        }

        // ========================================
        // OUTRAS RECEITAS OPERACIONAIS (3.01.02.*)
        // FIX v3.0: Era DEDUCAO. Arvore mostra valor POSITIVO.
        // ========================================
        if (str_starts_with($codigo, '3.01.02')) {
            return 'RECEITA_PF';
        }

        // ========================================
        // DEDUCOES DA RECEITA (3.01.03.*)
        // FIX v3.0: Antes nao tinha regra, caia em 3.01->RECEITA_PF.
        // Arvore mostra valores NEGATIVOS (Simples, INSS, etc.)
        // ========================================
        if (str_starts_with($codigo, '3.01.03')) {
            return 'DEDUCAO';
        }

        // ========================================
        // DESPESAS OPERACIONAIS (3.02.xx)
        // ========================================
        if (str_starts_with($codigo, '3.02')) {
            return 'DESPESA';
        }

        // ========================================
        // RECEITAS FINANCEIRAS (3.03.xx)
        // ========================================
        if (str_starts_with($codigo, '3.03')) {
            return 'RECEITA_FINANCEIRA';
        }

        // ========================================
        // DESPESAS FINANCEIRAS (3.04.xx)
        // ========================================
        if (str_starts_with($codigo, '3.04')) {
            return 'DESPESA_FINANCEIRA';
        }

        // ========================================
        // PADROES GENERICOS (fallback)
        // ========================================

        // Qualquer coisa em 3.01 nao capturada acima
        if (str_starts_with($codigo, '3.01')) {
            return 'RECEITA_PF';
        }

        // Qualquer coisa em 3.0x onde x > 1
        if (preg_match('/^3\.0[2-9]/', $codigo)) {
            return 'DESPESA';
        }

        return 'PENDENTE_CLASSIFICACAO';
    }

    /**
     * Classifica baseado apenas no tipo do movimento
     */
    private function classificarPorTipo(?string $tipo): string
    {
        if (empty($tipo)) {
            return 'PENDENTE_CLASSIFICACAO';
        }

        $tipo = strtolower(trim($tipo));

        if (in_array($tipo, ['receita', 'entrada', 'credito', 'c'])) {
            return 'RECEITA_PF';
        }

        if (in_array($tipo, ['despesa', 'saida', 'debito', 'd'])) {
            return 'DESPESA';
        }

        return 'PENDENTE_CLASSIFICACAO';
    }

    /**
     * Importa planos de contas unicos de movimentos
     */
    public function importarDoDataJuri(array $movimentos): int
    {
        $importados = 0;
        $planosUnicos = $this->extrairPlanosUnicos($movimentos);

        foreach ($planosUnicos as $plano) {
            $existe = ClassificacaoRegra::where('codigo_plano', $plano['codigo'])->exists();

            if ($existe) {
                continue;
            }

            ClassificacaoRegra::create([
                'codigo_plano' => $plano['codigo'],
                'nome_plano' => $plano['nome'],
                'classificacao' => $this->inferirPorPadraoContabil($plano['codigo']),
                'tipo_movimento' => $this->normalizarTipo($plano['tipo']),
                'origem' => 'datajuri',
                'ativo' => false,
                'prioridade' => $this->calcularPrioridade($plano['codigo']),
                'observacoes' => 'Importado automaticamente do DataJuri',
            ]);

            $importados++;
        }

        Log::info("Importacao do DataJuri concluida", [
            'total_importados' => $importados,
            'total_analisados' => count($planosUnicos),
        ]);

        return $importados;
    }

    private function extrairPlanosUnicos(array $movimentos): array
    {
        $planos = [];
        $vistos = [];

        foreach ($movimentos as $movimento) {
            $codigo = $movimento['codigo_plano'] ?? null;
            $nome = $movimento['plano_contas'] ?? 'Sem nome';
            $tipo = $movimento['tipo'] ?? 'INDEFINIDO';

            if (empty($codigo) || in_array($codigo, $vistos)) {
                continue;
            }

            $planos[] = [
                'codigo' => $codigo,
                'nome' => $nome,
                'tipo' => $tipo,
            ];

            $vistos[] = $codigo;
        }

        return $planos;
    }

    private function normalizarTipo(?string $tipo): string
    {
        if (empty($tipo)) {
            return 'INDEFINIDO';
        }

        $tipo = strtolower(trim($tipo));

        if (in_array($tipo, ['receita', 'entrada', 'credito', 'c'])) {
            return 'RECEITA';
        }

        if (in_array($tipo, ['despesa', 'saida', 'debito', 'd'])) {
            return 'DESPESA';
        }

        return 'INDEFINIDO';
    }

    private function calcularPrioridade(string $codigo): int
    {
        $niveis = substr_count($codigo, '.');

        return match($niveis) {
            0 => 10,
            1 => 20,
            2 => 30,
            3 => 40,
            default => 50,
        };
    }

    /**
     * Reclassifica todos os movimentos pendentes
     */
    public function reclassificarMovimentos(): array
    {
        $stats = [
            'total_analisados' => 0,
            'reclassificados' => 0,
            'pendentes' => 0,
            'por_classificacao' => [],
        ];

        $movimentos = Movimento::where(function($q) {
            $q->whereIn('classificacao', ['PENDENTE_CLASSIFICACAO', ''])
              ->orWhereNull('classificacao');
        })->get();

        $stats['total_analisados'] = $movimentos->count();

        foreach ($movimentos as $movimento) {
            $novaClassificacao = $this->classificar(
                $movimento->codigo_plano,
                $movimento->tipo_classificacao ?? $movimento->tipo ?? null
            );

            if ($novaClassificacao !== 'PENDENTE_CLASSIFICACAO') {
                $movimento->classificacao = $novaClassificacao;
                $movimento->save();

                $stats['reclassificados']++;
                $stats['por_classificacao'][$novaClassificacao] =
                    ($stats['por_classificacao'][$novaClassificacao] ?? 0) + 1;
            } else {
                $stats['pendentes']++;
            }
        }

        Log::info("Reclassificacao em massa concluida", $stats);

        return $stats;
    }

    /**
     * Reclassifica TODOS os movimentos (nao so pendentes).
     * DEVE ser executado apos fix de classificacao v3.0.
     */
    public function reclassificarTodos(): array
    {
        $stats = [
            'total_analisados' => 0,
            'atualizados' => 0,
            'por_classificacao' => [],
        ];

        DB::table('movimentos')
            ->whereNotNull('codigo_plano')
            ->where(function($q) {
                $q->where('classificacao_manual', '!=', 1)
                  ->orWhereNull('classificacao_manual');
            })
            ->orderBy('id')
            ->chunk(500, function ($movimentos) use (&$stats) {
                foreach ($movimentos as $mov) {
                    $stats['total_analisados']++;
                    
                    $novaClassificacao = $this->classificar(
                        $mov->codigo_plano,
                        $mov->tipo_classificacao ?? null
                    );

                    if ($mov->classificacao !== $novaClassificacao) {
                        DB::table('movimentos')
                            ->where('id', $mov->id)
                            ->update(['classificacao' => $novaClassificacao]);

                        $stats['atualizados']++;
                    }

                    $stats['por_classificacao'][$novaClassificacao] =
                        ($stats['por_classificacao'][$novaClassificacao] ?? 0) + 1;
                }
            });

        Log::info("Reclassificacao total concluida", $stats);

        return $stats;
    }

    public function classificacaoValida(string $classificacao): bool
    {
        return array_key_exists($classificacao, ClassificacaoRegra::CLASSIFICACOES);
    }

    public function listarClassificacoes(): array
    {
        return ClassificacaoRegra::CLASSIFICACOES;
    }

    public function listarTiposMovimento(): array
    {
        return ClassificacaoRegra::TIPOS_MOVIMENTO;
    }
}
