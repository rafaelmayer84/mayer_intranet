<?php

namespace App\Services\Evidentia;

use App\Models\EvidentiaChunk;
use App\Models\EvidentiaEmbedding;
use App\Models\EvidentiaSearch;
use App\Models\EvidentiaSearchResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvidentiaSearchService
{
    private EvidentiaOpenAIService $openai;

    public function __construct(EvidentiaOpenAIService $openai)
    {
        $this->openai = $openai;
    }

    /**
     * Executa busca híbrida completa.
     */
    public function search(string $query, array $filters = [], int $topk = 10, ?int $userId = null, bool $exactMatch = false): EvidentiaSearch
    {
        $startTime = microtime(true);

        // Cria registro da busca
        $search = EvidentiaSearch::create([
            'user_id'      => $userId,
            'query'        => $query,
            'filters_json' => $filters,
            'topk'         => $topk,
            'status'       => 'processing',
        ]);

        try {
            // ETAPA 1: Query Understanding
            $understood = $this->openai->queryUnderstanding($query);
            $search->expanded_terms_json = $understood;
            $search->addTokenUsage(
                $understood['_tokens_in'] ?? 0,
                $understood['_tokens_out'] ?? 0,
                $understood['_model'] ?? 'fallback'
            );

            $termos    = $understood['termos'] ?? [];
            $expansoes = $understood['expansoes'] ?? [];
            $aiFilters = $understood['filtros'] ?? [];

            // Merge filtros do usuário com sugeridos pela IA (usuário tem prioridade)
            $mergedFilters = array_merge($aiFilters, $filters);

            // ETAPA 2: Fulltext search cross-database
            $fulltextCandidates = $this->fulltextSearch($termos, $expansoes, $mergedFilters, $exactMatch, $query);

            if (empty($fulltextCandidates)) {
                $search->update([
                    'status'     => 'complete',
                    'latency_ms' => $this->elapsedMs($startTime),
                ]);
                return $search;
            }

            // ETAPA 3: Busca semântica (cosine similarity)
            $degradedMode = false;
            $semanticScores = [];

            $queryEmbedding = $this->openai->generateQueryEmbedding($query);

            if ($queryEmbedding) {
                $search->addTokenUsage(
                    (int) ceil(mb_strlen($query) / 4), 0,
                    config('evidentia.openai_embedding_model')
                );
                $semanticScores = $this->semanticSearch(
                    $queryEmbedding,
                    $fulltextCandidates
                );
            } else {
                $degradedMode = true;
                Log::warning('Evidentia: busca semântica indisponível, modo degradado');
            }

            // ETAPA 4: Mistura de scores
            $mixed = $this->mixScores($fulltextCandidates, $semanticScores);

            // Top N para rerank
            $maxRerank = config('evidentia.max_rerank', 30);
            $toRerank = array_slice($mixed, 0, $maxRerank);

            // ETAPA 5: Rerank com OpenAI
            $rerankResult = $this->openai->rerank($query, $toRerank);
            $search->addTokenUsage(
                $rerankResult['_tokens_in'] ?? 0,
                $rerankResult['_tokens_out'] ?? 0,
                $rerankResult['_model'] ?? config('evidentia.openai_model_rerank')
            );

            // ETAPA 6: Score final
            $finalRanked = $this->applyRerank($toRerank, $rerankResult['ranking'] ?? []);

            // Pega apenas topk
            $finalResults = array_slice($finalRanked, 0, $topk);

            // Salva resultados
            foreach ($finalResults as $rank => $item) {
                EvidentiaSearchResult::create([
                    'search_id'            => $search->id,
                    'jurisprudence_id'     => $item['id'],
                    'tribunal'             => $item['tribunal'],
                    'source_db'            => $item['source_db'],
                    'score_text'           => $item['score_text'] ?? 0,
                    'score_semantic'       => $item['score_semantic'] ?? 0,
                    'score_rerank'         => $item['score_rerank'] ?? 0,
                    'final_score'          => $item['final_score'] ?? 0,
                    'highlights_json'      => $item['highlights'] ?? [],
                    'rerank_justification' => $item['rerank_justification'] ?? null,
                    'final_rank'           => $rank + 1,
                ]);
            }

            $search->update([
                'status'        => 'complete',
                'degraded_mode' => $degradedMode,
                'latency_ms'    => $this->elapsedMs($startTime),
            ]);
        } catch (\Exception $e) {
            Log::error('Evidentia: search error', [
                'search_id' => $search->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            $search->update([
                'status'        => 'error',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'latency_ms'    => $this->elapsedMs($startTime),
            ]);
        }

        return $search->fresh(['results']);
    }

    /**
     * ETAPA 2: Fulltext search cross-database.
     * Retorna array de candidatos com score_text e metadados.
     */
    private function fulltextSearch(array $termos, array $expansoes, array $filters, bool $exactMatch = false, string $originalQuery = ''): array
    {
        $allTerms = array_merge($termos, $expansoes);
        // Se IA nao gerou termos, usa palavras originais da query
        if (empty($allTerms) && !$exactMatch) {
            return [];
        }

        $maxCandidates = config('evidentia.max_candidates_fulltext', 200);
        $fulltextColumn = config('evidentia.fulltext_column', 'ementa');
        $candidates = [];

        // Extrai termos exatos entre aspas da query original
        $exactTerms = [];
        if ($exactMatch || preg_match_all('/"([^"]+)"/', $originalQuery, $matches)) {
            if ($exactMatch) {
                // Modo exato: trata a query inteira como termo exato
                $exactTerms[] = trim($originalQuery, '"');
            } elseif (!empty($matches[1])) {
                $exactTerms = $matches[1];
            }
        }

        // Se há termos exatos, prioriza busca com eles
        if (!empty($exactTerms)) {
            $strategies = [
                ['mode' => 'BOOLEAN',  'query' => $this->buildExactBooleanQuery($exactTerms, $allTerms)],
                ['mode' => 'BOOLEAN',  'query' => $this->buildBooleanQuery($allTerms)],
                ['mode' => 'NATURAL',  'query' => $this->buildNaturalQuery($allTerms)],
            ];
        } else {
            // Tenta 3 estrategias em cascata ate achar resultados
            $strategies = [
                ['mode' => 'BOOLEAN',  'query' => $this->buildBooleanQuery($allTerms)],
                ['mode' => 'NATURAL',  'query' => $this->buildNaturalQuery($allTerms)],
                ['mode' => 'NATURAL',  'query' => $this->buildNaturalQuery($termos)], // so termos originais
            ];
        }

        foreach ($strategies as $stratIndex => $strategy) {
            if (!empty($candidates)) {
                break; // Ja achou na estrategia anterior
            }

            $modeClause = $strategy['mode'] === 'BOOLEAN' ? 'IN BOOLEAN MODE' : 'IN NATURAL LANGUAGE MODE';
            $searchQuery = $strategy['query'];

            if (empty(trim($searchQuery))) {
                continue;
            }

            foreach (config('evidentia.tribunal_databases') as $tribunal => $dbConfig) {
                if (!empty($filters['tribunal']) && strtoupper($filters['tribunal']) !== $tribunal) {
                    continue;
                }

                try {
                    $query = DB::connection($dbConfig['connection'])
                        ->table($dbConfig['table'])
                        ->selectRaw("id, tribunal, numero_processo, sigla_classe, descricao_classe, 
                            relator, orgao_julgador, data_decisao, data_publicacao, area_direito,
                            LEFT(ementa, 2000) as ementa,
                            MATCH({$fulltextColumn}) AGAINST(? {$modeClause}) as ft_score", [$searchQuery])
                        ->whereRaw("MATCH({$fulltextColumn}) AGAINST(? {$modeClause})", [$searchQuery])
                        ->whereNotNull('ementa')
                        ->where('ementa', '!=', '');

                    if (!empty($filters['classe'])) {
                        $query->where('sigla_classe', 'LIKE', '%' . $filters['classe'] . '%');
                    }
                    if (!empty($filters['area_direito'])) {
                        $query->where('area_direito', $filters['area_direito']);
                    }
                    if (!empty($filters['orgao_julgador'])) {
                        $query->where('orgao_julgador', 'LIKE', '%' . $filters['orgao_julgador'] . '%');
                    }
                    if (!empty($filters['relator'])) {
                        $query->where('relator', 'LIKE', '%' . $filters['relator'] . '%');
                    }
                    if (!empty($filters['periodo_inicio'])) {
                        $query->where('data_decisao', '>=', $filters['periodo_inicio']);
                    }
                    if (!empty($filters['periodo_fim'])) {
                        $query->where('data_decisao', '<=', $filters['periodo_fim']);
                    }

                    $results = $query->orderByDesc('ft_score')
                        ->limit($maxCandidates)
                        ->get();

                    foreach ($results as $r) {
                        $candidates[] = [
                            'id'               => $r->id,
                            'tribunal'         => $r->tribunal ?? $tribunal,
                            'source_db'        => $dbConfig['connection'],
                            'numero_processo'  => $r->numero_processo,
                            'sigla_classe'     => $r->sigla_classe,
                            'descricao_classe' => $r->descricao_classe,
                            'relator'          => $r->relator,
                            'orgao_julgador'   => $r->orgao_julgador,
                            'data_decisao'     => $r->data_decisao,
                            'data_publicacao'  => $r->data_publicacao,
                            'area_direito'     => $r->area_direito,
                            'ementa'           => $r->ementa,
                            'score_text'       => (float) $r->ft_score,
                            'best_chunk'       => '',
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning("Evidentia: fulltext strategy {$stratIndex} em {$tribunal} falhou", ['error' => $e->getMessage()]);
                }
            }

            if (!empty($candidates)) {
                Log::info("Evidentia: fulltext encontrou resultados na estrategia {$stratIndex} ({$strategy['mode']})");
            }
        }

        // ULTIMO RECURSO: busca LIKE nos 3 primeiros termos
        if (empty($candidates) && !empty($termos)) {
            Log::info('Evidentia: fallback para busca LIKE');
            $likeTerm = '%' . implode('%', array_slice($termos, 0, 3)) . '%';

            foreach (config('evidentia.tribunal_databases') as $tribunal => $dbConfig) {
                if (!empty($filters['tribunal']) && strtoupper($filters['tribunal']) !== $tribunal) {
                    continue;
                }
                try {
                    $results = DB::connection($dbConfig['connection'])
                        ->table($dbConfig['table'])
                        ->select('id', 'tribunal', 'numero_processo', 'sigla_classe', 'descricao_classe',
                            'relator', 'orgao_julgador', 'data_decisao', 'data_publicacao', 'area_direito')
                        ->selectRaw('LEFT(ementa, 2000) as ementa')
                        ->where('ementa', 'LIKE', $likeTerm)
                        ->whereNotNull('ementa')
                        ->limit(50)
                        ->get();

                    foreach ($results as $r) {
                        $candidates[] = [
                            'id'               => $r->id,
                            'tribunal'         => $r->tribunal ?? $tribunal,
                            'source_db'        => $dbConfig['connection'],
                            'numero_processo'  => $r->numero_processo,
                            'sigla_classe'     => $r->sigla_classe,
                            'descricao_classe' => $r->descricao_classe,
                            'relator'          => $r->relator,
                            'orgao_julgador'   => $r->orgao_julgador,
                            'data_decisao'     => $r->data_decisao,
                            'data_publicacao'  => $r->data_publicacao,
                            'area_direito'     => $r->area_direito,
                            'ementa'           => $r->ementa,
                            'score_text'       => 1.0,
                            'best_chunk'       => '',
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning("Evidentia: LIKE fallback em {$tribunal} falhou", ['error' => $e->getMessage()]);
                }
            }
        }

        // Ordena por score_text
        usort($candidates, fn($a, $b) => $b['score_text'] <=> $a['score_text']);

        // Deduplicacao por numero_processo
        $seen = [];
        $unique = [];
        foreach ($candidates as $c) {
            $key = $c['numero_processo'] ?? ($c['tribunal'] . '-' . $c['id']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $c;
        }

        return array_slice($unique, 0, $maxCandidates);
    }

    /**
     * Constrói query boolean com termos exatos (aspas duplas no BOOLEAN MODE).
     */
    private function buildExactBooleanQuery(array $exactTerms, array $otherTerms = []): string
    {
        $parts = [];
        // Termos exatos obrigatórios com aspas
        foreach ($exactTerms as $term) {
            $clean = preg_replace('/[+\-><()~*@]/', '', trim($term));
            if (mb_strlen($clean) >= 2) {
                $parts[] = '+"' . $clean . '"';
            }
        }
        // Termos adicionais opcionais (sem +)
        foreach (array_slice($otherTerms, 0, 6) as $term) {
            $clean = preg_replace('/[+\-><()~*"@]/', '', trim($term));
            if (mb_strlen($clean) >= 3 && !in_array(mb_strtolower($clean), array_map('mb_strtolower', $exactTerms))) {
                $parts[] = $clean;
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Constrói query boolean para MATCH AGAINST.
     */
    private function buildBooleanQuery(array $terms): string
    {
        $parts = [];
        foreach ($terms as $term) {
            $term = trim($term);
            if (mb_strlen($term) < 3) {
                continue;
            }
            $clean = preg_replace('/[+\-><()~*"@]/', '', $term);
            if (str_contains($clean, ' ')) {
                $parts[] = '"' . $clean . '"';
            } else {
                $parts[] = $clean . '*';
            }
        }
        return implode(' ', $parts);
    }

    /**
     * Constroi query para NATURAL LANGUAGE MODE (fallback mais permissivo).
     */
    private function buildNaturalQuery(array $terms): string
    {
        $parts = [];
        foreach ($terms as $term) {
            $term = trim($term);
            if (mb_strlen($term) < 3) {
                continue;
            }
            $clean = preg_replace('/[+\-><()~*"@]/', '', $term);
            $parts[] = $clean;
        }
        return implode(' ', $parts);
    }

    /**
     * ETAPA 3: Busca semântica nos chunks dos candidatos.
     * Retorna array [jurisprudence_id => max_similarity]
     */
    private function semanticSearch(array $queryEmbedding, array $fulltextCandidates): array
    {
        $maxSemantic = config('evidentia.max_candidates_semantic', 100);
        $queryVector = $queryEmbedding['vector'];
        $queryNorm   = $queryEmbedding['norm'];

        // Agrupa candidatos por tribunal
        $candidateGroups = [];
        foreach (array_slice($fulltextCandidates, 0, $maxSemantic) as $c) {
            $tribunal = $c['tribunal'];
            $candidateGroups[$tribunal][] = $c;
        }

        $scores = [];
        $bestChunks = [];

        foreach ($candidateGroups as $tribunal => $candidates) {
            $jurisIds = array_column($candidates, 'id');
            $sourceDb = $candidates[0]['source_db'];
            $embConn = EvidentiaEmbedding::connectionForTribunal($tribunal);

            // Busca chunk_ids no banco shardado
            $chunkIds = DB::connection($embConn)
                ->table('evidentia_chunks')
                ->where('source_db', $sourceDb)
                ->where('tribunal', $tribunal)
                ->whereIn('jurisprudence_id', $jurisIds)
                ->pluck('id')
                ->toArray();

            // Fallback: busca no banco evidentia original (chunks ainda não migrados)
            if (empty($chunkIds) && $embConn !== 'evidentia') {
                $chunkIds = DB::connection('evidentia')
                    ->table('evidentia_chunks')
                    ->where('source_db', $sourceDb)
                    ->where('tribunal', $tribunal)
                    ->whereIn('jurisprudence_id', $jurisIds)
                    ->pluck('id')
                    ->toArray();

                // Se achou no evidentia, busca embeddings lá (formato JSON legado)
                if (!empty($chunkIds)) {
                    $this->searchEmbeddingsLegacy($chunkIds, $queryVector, $queryNorm, $scores, $bestChunks);
                    continue;
                }
            }

            if (empty($chunkIds)) {
                continue;
            }

            // Carrega embeddings (float16 binário) em lotes
            $batchSize = 500;
            foreach (array_chunk($chunkIds, $batchSize) as $batch) {
                $rows = DB::connection($embConn)
                    ->table('evidentia_embeddings as e')
                    ->join('evidentia_chunks as c', 'c.id', '=', 'e.chunk_id')
                    ->whereIn('e.chunk_id', $batch)
                    ->select('e.vector_bin', 'e.norm', 'c.tribunal', 'c.jurisprudence_id', 'c.chunk_text')
                    ->get();

                foreach ($rows as $row) {
                    $emb = new EvidentiaEmbedding();
                    $emb->attributes['vector_bin'] = $row->vector_bin;
                    $emb->norm = $row->norm;

                    $similarity = $emb->cosineSimilarity($queryVector, $queryNorm);
                    $compositeKey = $row->tribunal . '|' . $row->jurisprudence_id;

                    if (!isset($scores[$compositeKey]) || $similarity > $scores[$compositeKey]) {
                        $scores[$compositeKey] = $similarity;
                        $bestChunks[$compositeKey] = $row->chunk_text;
                    }
                }
            }
        }

        return ['scores' => $scores, 'best_chunks' => $bestChunks];
    }

    /**
     * Busca embeddings no formato legado (JSON) do banco evidentia original.
     * Usado durante o período de transição antes da migração completa.
     */
    private function searchEmbeddingsLegacy(array $chunkIds, array $queryVector, float $queryNorm, array &$scores, array &$bestChunks): void
    {
        $batchSize = 500;
        foreach (array_chunk($chunkIds, $batchSize) as $batch) {
            $rows = DB::connection('evidentia')
                ->table('evidentia_embeddings as e')
                ->join('evidentia_chunks as c', 'c.id', '=', 'e.chunk_id')
                ->whereIn('e.chunk_id', $batch)
                ->select('e.vector_json', 'e.norm', 'c.tribunal', 'c.jurisprudence_id', 'c.chunk_text')
                ->get();

            foreach ($rows as $row) {
                $vector = json_decode($row->vector_json, true);
                if (!$vector || !is_array($vector)) {
                    continue;
                }

                $dot = 0.0;
                $count = min(count($vector), count($queryVector));
                for ($i = 0; $i < $count; $i++) {
                    $dot += $vector[$i] * $queryVector[$i];
                }
                $denominator = $row->norm * $queryNorm;
                $similarity = $denominator > 0 ? $dot / $denominator : 0.0;

                $compositeKey = $row->tribunal . '|' . $row->jurisprudence_id;
                if (!isset($scores[$compositeKey]) || $similarity > $scores[$compositeKey]) {
                    $scores[$compositeKey] = $similarity;
                    $bestChunks[$compositeKey] = $row->chunk_text;
                }
            }
        }
    }

    /**
     * ETAPA 4: Mistura scores fulltext + semântico.
     */
    private function mixScores(array $fulltextCandidates, array $semanticData): array
    {
        $semanticScores = $semanticData['scores'] ?? [];
        $bestChunks     = $semanticData['best_chunks'] ?? [];

        $wSemantic = config('evidentia.weight_semantic', 0.55);
        $wText     = config('evidentia.weight_text', 0.45);

        // Normaliza scores fulltext (min-max)
        $textScores = array_column($fulltextCandidates, 'score_text');
        $textMin = !empty($textScores) ? min($textScores) : 0;
        $textMax = !empty($textScores) ? max($textScores) : 1;
        $textRange = $textMax - $textMin ?: 1;

        // Normaliza scores semânticos
        $semValues = !empty($semanticScores) ? array_values($semanticScores) : [0];
        $semMin = min($semValues);
        $semMax = max($semValues);
        $semRange = $semMax - $semMin ?: 1;

        foreach ($fulltextCandidates as &$c) {
            $compositeKey = $c['tribunal'] . '|' . $c['id'];

            // Normaliza fulltext
            $normText = ($c['score_text'] - $textMin) / $textRange;

            // Normaliza semântico
            $rawSemantic = $semanticScores[$compositeKey] ?? 0;
            $normSemantic = $rawSemantic > 0 ? ($rawSemantic - $semMin) / $semRange : 0;

            $c['score_text_norm'] = $normText;
            $c['score_semantic']  = $normSemantic;
            $c['mixed_score']     = ($wSemantic * $normSemantic) + ($wText * $normText);
            $c['best_chunk']      = $bestChunks[$compositeKey] ?? mb_substr($c['ementa'], 0, 500);
        }
        unset($c);

        // Ordena por mixed_score
        usort($fulltextCandidates, fn($a, $b) => $b['mixed_score'] <=> $a['mixed_score']);

        return $fulltextCandidates;
    }

    /**
     * ETAPA 6: Aplica scores do rerank ao resultado final.
     */
    private function applyRerank(array $candidates, array $ranking): array
    {
        $wMix    = config('evidentia.weight_mix', 0.50);
        $wRerank = config('evidentia.weight_rerank', 0.50);

        // Mapa de rerank por ID
        $rerankMap = [];
        foreach ($ranking as $item) {
            $rerankMap[$item['id']] = $item;
        }

        // Normaliza rerank scores
        $rerankScores = array_column($ranking, 'score');
        $rrMin = !empty($rerankScores) ? min($rerankScores) : 0;
        $rrMax = !empty($rerankScores) ? max($rerankScores) : 1;
        $rrRange = $rrMax - $rrMin ?: 1;

        foreach ($candidates as &$c) {
            $rr = $rerankMap[$c['id']] ?? null;
            if ($rr) {
                $normRerank = ($rr['score'] - $rrMin) / $rrRange;
                $c['score_rerank']          = $normRerank;
                $c['rerank_justification']  = $rr['justificativa'] ?? null;
                $c['final_score']           = ($wMix * $c['mixed_score']) + ($wRerank * $normRerank);
            } else {
                $c['score_rerank']         = 0;
                $c['rerank_justification'] = null;
                $c['final_score']          = $c['mixed_score'] * $wMix;
            }

            // Highlights: trechos do best_chunk + ementa
            $c['highlights'] = $this->extractHighlights($c['best_chunk'] ?? '', $c['ementa'] ?? '');
        }
        unset($c);

        // Ordena por final_score
        usort($candidates, fn($a, $b) => $b['final_score'] <=> $a['final_score']);

        return $candidates;
    }

    /**
     * Extrai highlights (trechos relevantes) para exibição.
     */
    private function extractHighlights(string $chunk, string $ementa): array
    {
        $highlights = [];

        if (mb_strlen($chunk) > 20) {
            $highlights[] = [
                'source' => 'chunk',
                'text'   => mb_substr($chunk, 0, 400),
            ];
        }

        if (mb_strlen($ementa) > 20) {
            $highlights[] = [
                'source' => 'ementa',
                'text'   => mb_substr($ementa, 0, 600),
            ];
        }

        return $highlights;
    }

    private function elapsedMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
