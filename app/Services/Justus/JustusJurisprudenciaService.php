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

        // Inferir área do direito pelo tipo de processo se possível
        $area = null;
        // Não filtrar por área por default — deixar fulltext encontrar o melhor match

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
            'references' => $results->map(fn($r) => $r->short_ref)->toArray(),
        ];
    }

    /**
     * Monta o bloco de contexto para injeção no prompt da IA.
     */
    private function buildPromptContext(Collection $results): string
    {
        $context = "\n\n[JURISPRUDÊNCIA STJ — REFERÊNCIAS VERIFICADAS]\n";
        $context .= "As ementas abaixo são reais, extraídas do Portal de Dados Abertos do STJ (dadosabertos.web.stj.jus.br). ";
        $context .= "Use APENAS estas referências ao citar jurisprudência. NÃO invente números de acórdãos, ementas ou decisões que não estejam listadas aqui.\n\n";

        foreach ($results as $i => $juris) {
            $num = $i + 1;
            $context .= "--- Referência #{$num} ---\n";
            $context .= $juris->toPromptFormat();
            $context .= "\n\n";
        }

        $context .= "[FIM DA JURISPRUDÊNCIA VERIFICADA]\n";
        $context .= "REGRA ABSOLUTA: Cite SOMENTE as referências acima. Se precisar de jurisprudência adicional que não conste desta lista, ";
        $context .= "use fórmulas genéricas como 'conforme entendimento consolidado do STJ' sem inventar números.\n";

        return $context;
    }

    /**
     * Retorna estatísticas da base de jurisprudência.
     */
    public function getStats(): array
    {
        return [
            'total' => JustusJurisprudencia::count(),
            'por_tribunal' => JustusJurisprudencia::selectRaw('tribunal, COUNT(*) as total')
                ->groupBy('tribunal')->pluck('total', 'tribunal')->toArray(),
            'por_area' => JustusJurisprudencia::selectRaw('area_direito, COUNT(*) as total')
                ->groupBy('area_direito')->pluck('total', 'area_direito')->toArray(),
            'por_orgao' => JustusJurisprudencia::selectRaw('orgao_julgador, COUNT(*) as total')
                ->groupBy('orgao_julgador')->orderByDesc('total')->pluck('total', 'orgao_julgador')->toArray(),
            'mais_recente' => JustusJurisprudencia::max('data_decisao'),
            'mais_antigo' => JustusJurisprudencia::min('data_decisao'),
        ];
    }
}
