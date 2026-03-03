<?php

namespace App\Services\Justus;

use App\Models\JustusJurisprudencia;
use App\Models\JustusConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class JustusJurisprudenciaService
{
    /**
     * Busca jurisprudência relevante para injeção no prompt.
     * Combina termos da pergunta do usuário + dados do perfil do processo.
     */
    public function searchForPrompt(JustusConversation $conversation, string $userMessage): array
    {
        $maxResults = config('justus.stj_max_results_prompt', 5);

        // Construir query combinada: mensagem do usuário + contexto do processo
        $searchQuery = $userMessage;
        $profile = $conversation->processProfile;
        if ($profile) {
            if ($profile->tese_principal) {
                $searchQuery .= ' ' . $profile->tese_principal;
            }
            if ($profile->objetivo_analise) {
                $searchQuery .= ' ' . $profile->objetivo_analise;
            }
        }

        // Se profile nao tem dados essenciais E mensagem eh instrucao generica, nao buscar
        $hasProfileContext = $profile && ($profile->classe || $profile->tese_principal || $profile->objetivo_analise);
        
        // Inferir area do direito se possivel
        $area = null;
        if ($profile && $profile->orgao) {
            $area = JustusJurisprudencia::inferAreaDireito($profile->orgao);
        }

        $results = JustusJurisprudencia::searchRelevant($searchQuery, $maxResults, $area);

        if ($results->isEmpty()) {
            return [
                'found' => false,
                'count' => 0,
                'context' => '',
                'references' => [],
            ];
        }

        return [
            'found' => true,
            'count' => $results->count(),
            'context' => $this->buildPromptContext($results),
            'references' => $results->map(fn($r) => self::formatShortRef($r))->toArray(),
        ];
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
