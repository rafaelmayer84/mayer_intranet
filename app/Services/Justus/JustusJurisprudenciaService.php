<?php

namespace App\Services\Justus;

use App\Models\JustusJurisprudencia;
use App\Models\JustusConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use App\Services\Evidentia\EvidentiaSearchService;

class JustusJurisprudenciaService
{
    /**
     * Busca jurisprudência relevante para injeção no prompt.
     * Combina termos da pergunta do usuário + dados do perfil do processo.
     */
    public function searchForPrompt(JustusConversation $conversation, string $userMessage): array
    {
        $maxResults = config('justus.stj_max_results_prompt', 5);

        // Construir query combinada
        $searchQuery = $userMessage;
        $profile = $conversation->processProfile;
        $filters = [];

        if ($profile) {
            if ($profile->tese_principal) {
                $searchQuery .= ' ' . $profile->tese_principal;
            }
            if ($profile->objetivo_analise) {
                $searchQuery .= ' ' . $profile->objetivo_analise;
            }
            // Inferir area do direito para filtro
            if ($profile->orgao) {
                $orgaoLower = mb_strtolower($profile->orgao);
                if (str_contains($orgaoLower, 'trt') || str_contains($orgaoLower, 'trabalh')) {
                    $filters['area_direito'] = 'trabalhista';
                } elseif (str_contains($orgaoLower, 'criminal') || str_contains($orgaoLower, 'penal')) {
                    $filters['area_direito'] = 'penal';
                } else {
                    $filters['area_direito'] = 'civil';
                }
            }
        }

        // Usar Evidentia para busca inteligente
        try {
            $evidentia = app(EvidentiaSearchService::class);
            $search = $evidentia->search($searchQuery, $filters, $maxResults, $conversation->user_id);

            if (!$search->results || $search->results->isEmpty()) {
                return ['found' => false, 'count' => 0, 'context' => '', 'references' => []];
            }

            // Converter resultados do Evidentia para o formato do JUSTUS
            $evResults = $search->results()->orderBy('final_rank')->get();
            $formatted = [];
            foreach ($evResults as $er) {
                // Buscar dados completos do acórdão
                $dbConfig = config('evidentia.tribunal_databases.' . $er->tribunal);
                if (!$dbConfig) continue;
                $juris = \DB::connection($dbConfig['connection'])
                    ->table($dbConfig['table'])
                    ->where('id', $er->jurisprudence_id)
                    ->first();
                if (!$juris) continue;
                $formatted[] = $juris;
            }

            if (empty($formatted)) {
                return ['found' => false, 'count' => 0, 'context' => '', 'references' => []];
            }

            return [
                'found' => true,
                'count' => count($formatted),
                'context' => $this->buildPromptContext(collect($formatted)),
                'references' => collect($formatted)->map(fn($r) => self::formatShortRef($r))->toArray(),
            ];
        } catch (\Exception $e) {
            Log::warning('JUSTUS: Evidentia search failed, fallback to legacy', ['error' => $e->getMessage()]);

            // Fallback: busca legacy
            $area = $filters['area_direito'] ?? null;
            $results = JustusJurisprudencia::searchRelevant($searchQuery, $maxResults, $area);
            if ($results->isEmpty()) {
                return ['found' => false, 'count' => 0, 'context' => '', 'references' => []];
            }
            return [
                'found' => true,
                'count' => $results->count(),
                'context' => $this->buildPromptContext($results),
                'references' => $results->map(fn($r) => self::formatShortRef($r))->toArray(),
            ];
        }
    }

    /**
     * Monta o bloco de contexto para injeção no prompt da IA.
     */
    private function buildPromptContext(Collection $results): string
    {
        $context = "\n\n[JURISPRUDÊNCIA — REFERÊNCIAS VERIFICADAS]\n";
        $context .= "As ementas abaixo são reais, extraídas de bases oficiais (STJ, TJSC e outros tribunais). ";
        $context .= "Use APENAS estas referências ao citar jurisprudência. NÃO invente números de acórdãos, ementas ou decisões que não estejam listadas aqui.\n\n";

        foreach ($results as $i => $juris) {
            $num = $i + 1;
            $context .= "--- Referência #{$num} ---\n";
            $context .= self::formatForPrompt($juris);
            $context .= "\n\n";
        }

        $context .= "[FIM DA JURISPRUDÊNCIA VERIFICADA]\n";
        $context .= "REGRA ABSOLUTA: Cite SOMENTE as referências acima. Se precisar de jurisprudência adicional que não conste desta lista, ";
        $context .= "use fórmulas genéricas como 'conforme entendimento consolidado do STJ' sem inventar números.\n";

        return $context;
    }

    /**
     * Formata registro (stdClass ou Model) para injecao no prompt
     */
    private static function formatForPrompt($juris): string
    {
        $ref = ($juris->sigla_classe ?? '') . ' ' . ($juris->numero_registro ?? '');
        $relator = $juris->relator ?? '';
        $orgao = $juris->orgao_julgador ?? '';
        $data = $juris->data_decisao ?? 'data n/d';
        $ementa = trim($juris->ementa ?? '');
        $tribunal = strtoupper($juris->tribunal ?? '');
        $relTitle = self::relatorTitle($tribunal);
        return "{$ref}, {$relTitle} {$relator}, {$orgao}, j. {$data}\nEMENTA: {$ementa}";
    }

    private static function relatorTitle(string $tribunal): string
    {
        return match($tribunal) {
            'STJ', 'STF' => 'Rel. Min.',
            'TJSC', 'TJSP', 'TJRJ', 'TJRS', 'TJPR', 'TJMG' => 'Rel. Des.',
            'TRF4', 'TRF1', 'TRF2', 'TRF3', 'TRF5' => 'Rel. Des. Federal',
            'TRT12', 'TRT1', 'TRT2', 'TRT3', 'TRT4', 'TRT9', 'TRT15' => 'Rel. Des.',
            'TST' => 'Rel. Min.',
            default => 'Rel.',
        };
    }

    /**
     * Formata referencia curta (stdClass ou Model)
     */
    private static function formatShortRef($juris): string
    {
        $data = $juris->data_decisao ?? '';
        $tribunal = strtoupper($juris->tribunal ?? '');
        $relTitle = self::relatorTitle($tribunal);
        return ($juris->sigla_classe ?? '') . ' ' . ($juris->numero_registro ?? '') . ', ' . $relTitle . ' ' . ($juris->relator ?? '') . ', ' . ($juris->orgao_julgador ?? '') . ', j. ' . $data;
    }

    /**
     * Retorna estatísticas da base de jurisprudência.
     */
    public function getStats(): array
    {
        $totalGeral = 0;
        $porTribunal = [];
        $porArea = [];
        $porOrgao = [];
        $maisRecente = null;
        $maisAntigo = null;

        // Consultar cada banco de tribunal
        $connections = JustusJurisprudencia::allTribunalConnections();
        $connections['PRINCIPAL'] = 'mysql'; // incluir banco principal

        foreach ($connections as $tribunal => $conn) {
            try {
                $db = \DB::connection($conn);
                $count = $db->table('justus_jurisprudencia')->count();
                if ($count === 0) continue;

                $totalGeral += $count;

                // Por tribunal
                $tribunais = $db->table('justus_jurisprudencia')
                    ->selectRaw('tribunal, COUNT(*) as total')
                    ->groupBy('tribunal')->pluck('total', 'tribunal')->toArray();
                foreach ($tribunais as $t => $v) {
                    $porTribunal[$t] = ($porTribunal[$t] ?? 0) + $v;
                }

                // Por area
                $areas = $db->table('justus_jurisprudencia')
                    ->selectRaw('area_direito, COUNT(*) as total')
                    ->groupBy('area_direito')->pluck('total', 'area_direito')->toArray();
                foreach ($areas as $a => $v) {
                    $porArea[$a] = ($porArea[$a] ?? 0) + $v;
                }

                // Por orgao (top 20)
                $orgaos = $db->table('justus_jurisprudencia')
                    ->selectRaw('orgao_julgador, COUNT(*) as total')
                    ->groupBy('orgao_julgador')->orderByDesc('total')
                    ->limit(20)->pluck('total', 'orgao_julgador')->toArray();
                foreach ($orgaos as $o => $v) {
                    $porOrgao[$o] = ($porOrgao[$o] ?? 0) + $v;
                }

                // Datas
                $maxDate = $db->table('justus_jurisprudencia')->max('data_decisao');
                $minDate = $db->table('justus_jurisprudencia')->min('data_decisao');
                if ($maxDate && (!$maisRecente || $maxDate > $maisRecente)) $maisRecente = $maxDate;
                if ($minDate && (!$maisAntigo || $minDate < $maisAntigo)) $maisAntigo = $minDate;
            } catch (\Exception $e) {
                \Log::warning("Justus stats: falha em {$tribunal}: " . $e->getMessage());
            }
        }

        arsort($porOrgao);

        return [
            'total' => $totalGeral,
            'por_tribunal' => $porTribunal,
            'por_area' => $porArea,
            'por_orgao' => $porOrgao,
            'mais_recente' => $maisRecente,
            'mais_antigo' => $maisAntigo,
        ];
    }
}
