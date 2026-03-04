<?php

namespace App\Services\Evidentia;

use App\Models\EvidentiaCitationBlock;
use App\Models\EvidentiaSearch;
use App\Models\EvidentiaSearchResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvidentiaCitationService
{
    private EvidentiaOpenAIService $openai;

    public function __construct(EvidentiaOpenAIService $openai)
    {
        $this->openai = $openai;
    }

    /**
     * Gera bloco de citação a partir dos top resultados de uma busca.
     */
    public function generate(EvidentiaSearch $search, ?int $userId = null): ?EvidentiaCitationBlock
    {
        // Verifica se já existe
        $existing = $search->citationBlock;
        if ($existing) {
            return $existing;
        }

        $maxResults = config('evidentia.citation_max_results', 5);
        $results = $search->results()->limit($maxResults)->get();

        if ($results->isEmpty()) {
            return null;
        }

        // Carrega dados completos das jurisprudências
        $topData = [];
        $usedIds = [];

        foreach ($results as $result) {
            $juris = $result->getJurisprudence();
            if (!$juris) {
                continue;
            }

            $topData[] = [
                'id'               => $result->jurisprudence_id,
                'tribunal'         => $result->tribunal,
                'sigla_classe'     => $juris->sigla_classe ?? '',
                'descricao_classe' => $juris->descricao_classe ?? '',
                'numero_processo'  => $juris->numero_processo ?? '',
                'relator'          => $juris->relator ?? '',
                'orgao_julgador'   => $juris->orgao_julgador ?? '',
                'data_decisao'     => $juris->data_decisao ?? '',
                'ementa'           => $juris->ementa ?? '',
                'best_chunk'       => $result->highlights_json[0]['text'] ?? '',
            ];

            $usedIds[] = [
                'id'       => $result->jurisprudence_id,
                'tribunal' => $result->tribunal,
            ];
        }

        if (empty($topData)) {
            return null;
        }

        $aiResult = $this->openai->generateCitationBlock($search->query, $topData);

        if (!($aiResult['success'] ?? false)) {
            Log::error('Evidentia: falha ao gerar bloco de citação', [
                'search_id' => $search->id,
                'error'     => $aiResult['error'] ?? 'desconhecido',
            ]);
            return null;
        }

        $block = EvidentiaCitationBlock::create([
            'search_id'              => $search->id,
            'user_id'                => $userId,
            'sintese_objetiva'       => $aiResult['sintese_objetiva'],
            'bloco_precedentes'      => $aiResult['bloco_precedentes'],
            'jurisprudence_ids_used' => $usedIds,
            'tokens_in'              => $aiResult['_tokens_in'] ?? 0,
            'tokens_out'             => $aiResult['_tokens_out'] ?? 0,
            'cost_usd'               => 0,
        ]);

        // Atualiza custo na search
        $search->addTokenUsage(
            $aiResult['_tokens_in'] ?? 0,
            $aiResult['_tokens_out'] ?? 0,
            $aiResult['_model'] ?? config('evidentia.openai_model_writer')
        );

        return $block;
    }
}
