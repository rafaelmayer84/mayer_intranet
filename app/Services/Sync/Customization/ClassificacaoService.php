<?php

namespace App\Services\Sync\Customization;

use App\Models\ClassificacaoRegra;
use App\Models\Movimento;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassificacaoService
{
    /**
     * Classifica um movimento com base nas regras
     */
    public function classificar(string $codigoPlano): string
    {
        return ClassificacaoRegra::buscarClassificacao($codigoPlano);
    }

    /**
     * Reclassifica todos os movimentos pendentes
     */
    public function reclassificarTudo(): array
    {
        $stats = [
            'total' => 0,
            'processados' => 0,
            'erros' => 0,
        ];

        try {
            $movimentos = Movimento::where('classificacao', 'PENDENTE_CLASSIFICACAO')
                ->whereNotNull('codigo_plano')
                ->get();

            $stats['total'] = $movimentos->count();

            foreach ($movimentos as $movimento) {
                try {
                    $novaClassificacao = $this->classificar($movimento->codigo_plano);
                    
                    if ($novaClassificacao !== 'PENDENTE_CLASSIFICACAO') {
                        $movimento->classificacao = $novaClassificacao;
                        $movimento->save();
                        $stats['processados']++;
                    }
                } catch (\Exception $e) {
                    $stats['erros']++;
                    Log::error("Erro ao reclassificar movimento {$movimento->id}: {$e->getMessage()}");
                }
            }
        } catch (\Exception $e) {
            Log::error("Erro geral na reclassificação: {$e->getMessage()}");
            throw $e;
        }

        return $stats;
    }

    /**
     * Importa planos de contas do DataJuri
     * (Placeholder - será implementado quando necessário)
     */
    public function importarDoDataJuri(): array
    {
        // Este método será implementado quando houver necessidade
        // de buscar os planos de contas diretamente da API DataJuri
        
        return [
            'importados' => 0,
            'duplicados' => 0,
            'erros' => 0,
        ];
    }

    /**
     * Cria ou atualiza uma regra de classificação
     */
    public function criarOuAtualizarRegra(array $dados): ClassificacaoRegra
    {
        return ClassificacaoRegra::updateOrCreate(
            ['codigo_plano' => $dados['codigo_plano']],
            [
                'nome_plano' => $dados['nome_plano'] ?? null,
                'classificacao' => $dados['classificacao'],
                'origem' => $dados['origem'] ?? 'MANUAL',
                'ativo' => $dados['ativo'] ?? true,
            ]
        );
    }

    /**
     * Busca códigos de plano únicos nos movimentos sem regra
     */
    public function buscarCodigosSemRegra(): array
    {
        return Movimento::select('codigo_plano')
            ->whereNotNull('codigo_plano')
            ->where('codigo_plano', '!=', '')
            ->whereNotIn('codigo_plano', function($query) {
                $query->select('codigo_plano')
                    ->from('classificacao_regras');
            })
            ->distinct()
            ->pluck('codigo_plano')
            ->toArray();
    }

    /**
     * Estatísticas de classificação
     */
    public function estatisticas(): array
    {
        return [
            'total_regras' => ClassificacaoRegra::count(),
            'regras_ativas' => ClassificacaoRegra::where('ativo', true)->count(),
            'movimentos_pendentes' => Movimento::where('classificacao', 'PENDENTE_CLASSIFICACAO')->count(),
            'movimentos_classificados' => Movimento::where('classificacao', '!=', 'PENDENTE_CLASSIFICACAO')->count(),
        ];
    }
}
