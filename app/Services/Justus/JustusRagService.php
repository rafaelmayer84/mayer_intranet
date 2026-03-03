<?php

namespace App\Services\Justus;

use App\Models\JustusDocumentChunk;
use App\Models\JustusDocumentPage;
use App\Models\JustusConversation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class JustusRagService
{
    public function retrieveRelevantChunks(JustusConversation $conversation, string $query, ?int $maxChunks = null): Collection
    {
        $maxChunks = $maxChunks ?? config('justus.rag_max_chunks', 15);

        $attachmentIds = $conversation->attachments()
            ->where('processing_status', 'completed')
            ->pluck('id');

        if ($attachmentIds->isEmpty()) {
            return collect();
        }

        $keywords = $this->extractKeywords($query);
        $isIntegralRequest = $this->isIntegralAnalysis($query);

        // Se nao ha keywords ou eh pedido de analise integral, usar selecao hibrida
        if (empty($keywords) || $isIntegralRequest) {
            return $this->hybridSelection($attachmentIds, $keywords, $maxChunks);
        }

        $matchExpr = implode(' ', $keywords);

        try {
            $chunks = JustusDocumentChunk::whereIn('attachment_id', $attachmentIds)
                ->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$matchExpr])
                ->selectRaw('*, MATCH(content) AGAINST(? IN BOOLEAN MODE) as relevance', [$matchExpr])
                ->orderByDesc('relevance')
                ->limit($maxChunks)
                ->get();

            if ($chunks->isEmpty()) {
                return $this->fallbackLikeSearch($attachmentIds, $keywords, $maxChunks);
            }

            return $chunks;
        } catch (\Exception $e) {
            return $this->fallbackLikeSearch($attachmentIds, $keywords, $maxChunks);
        }
    }

    private function isIntegralAnalysis(string $query): bool
    {
        $triggers = [
            'integralmente', 'integral', 'completo', 'completa', 'todos os atos',
            'toda a', 'todo o', 'cronologia', 'cronologicamente', 'mapear',
            'mapeie', 'mapeamento', 'identificar todos', 'listar todos',
            'ler integralmente', 'leia integralmente', 'analise completa',
        ];
        $lower = mb_strtolower($query);
        foreach ($triggers as $t) {
            if (str_contains($lower, $t)) return true;
        }
        return false;
    }

    private function hybridSelection(Collection $attachmentIds, array $keywords, int $maxChunks): Collection
    {
        $totalChunks = JustusDocumentChunk::whereIn('attachment_id', $attachmentIds)->count();

        // Distribuir: metade por relevancia (se houver keywords), metade por cobertura temporal
        $relevanceSlots = !empty($keywords) ? intdiv($maxChunks, 2) : 0;
        $coverageSlots = $maxChunks - $relevanceSlots;

        $selectedIds = collect();

        // Parte 1: chunks mais relevantes por fulltext
        if ($relevanceSlots > 0 && !empty($keywords)) {
            $matchExpr = implode(' ', $keywords);
            try {
                $relevant = JustusDocumentChunk::whereIn('attachment_id', $attachmentIds)
                    ->whereRaw('MATCH(content) AGAINST(? IN BOOLEAN MODE)', [$matchExpr])
                    ->selectRaw('*, MATCH(content) AGAINST(? IN BOOLEAN MODE) as relevance', [$matchExpr])
                    ->orderByDesc('relevance')
                    ->limit($relevanceSlots)
                    ->get();
                $selectedIds = $selectedIds->merge($relevant->pluck('id'));
            } catch (\Exception $e) {}
        }

        // Parte 2: chunks distribuidos uniformemente ao longo do documento
        if ($coverageSlots > 0) {
            $allChunks = JustusDocumentChunk::whereIn('attachment_id', $attachmentIds)
                ->when($selectedIds->isNotEmpty(), fn($q) => $q->whereNotIn('id', $selectedIds))
                ->orderBy('page_start')
                ->get();

            if ($allChunks->isNotEmpty()) {
                $step = max(1, intdiv($allChunks->count(), $coverageSlots));
                $distributed = collect();
                for ($i = 0; $i < $allChunks->count() && $distributed->count() < $coverageSlots; $i += $step) {
                    $distributed->push($allChunks[$i]);
                }
                $selectedIds = $selectedIds->merge($distributed->pluck('id'));
            }
        }

        // Buscar todos os chunks selecionados ordenados por pagina
        return JustusDocumentChunk::whereIn('id', $selectedIds->unique())
            ->orderBy('page_start')
            ->get();
    }

    private function fallbackLikeSearch(Collection $attachmentIds, array $keywords, int $maxChunks): Collection
    {
        $query = JustusDocumentChunk::whereIn('attachment_id', $attachmentIds);

        foreach ($keywords as $kw) {
            $query->where('content', 'LIKE', '%' . $kw . '%');
        }

        return $query->orderBy('page_start')->limit($maxChunks)->get();
    }

    public function buildContextFromChunks(Collection $chunks, ?int $attachmentId = null): string
    {
        if ($chunks->isEmpty()) {
            return 'Nenhum trecho dos autos disponível para consulta.';
        }

        $context = "TRECHOS RELEVANTES DOS AUTOS:\n\n";
        foreach ($chunks as $chunk) {
            $context .= "--- Páginas {$chunk->page_start}–{$chunk->page_end} ---\n";
            $context .= $chunk->content . "\n\n";
        }

        if ($attachmentId) {
            $omitted = JustusDocumentPage::where('attachment_id', $attachmentId)
                ->where('is_relevant', false)
                ->orderBy('page_number')
                ->get()
                ->groupBy('doc_type');

            if ($omitted->isNotEmpty()) {
                $context .= "\n--- PÁGINAS OMITIDAS (não relevantes para análise jurídica) ---\n";
                foreach ($omitted as $type => $pages) {
                    $pageNums = $pages->pluck('page_number')->toArray();
                    $ranges = $this->compactPageRanges($pageNums);
                    $label = str_replace(['separador_', '_'], ['', ' '], $type);
                    $context .= ucfirst($label) . " (" . count($pages) . " pág.): " . $ranges . "\n";
                }
            }
        }

        return $context;
    }

    private function compactPageRanges(array $pages): string
    {
        if (empty($pages)) return '';
        sort($pages);
        $ranges = [];
        $start = $pages[0];
        $prev = $pages[0];
        for ($i = 1; $i < count($pages); $i++) {
            if ($pages[$i] === $prev + 1) {
                $prev = $pages[$i];
            } else {
                $ranges[] = $start === $prev ? (string)$start : "{$start}-{$prev}";
                $start = $pages[$i];
                $prev = $pages[$i];
            }
        }
        $ranges[] = $start === $prev ? (string)$start : "{$start}-{$prev}";
        return implode(', ', $ranges);
    }

    private function extractKeywords(string $query): array
    {
        $stopWords = [
            'o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das',
            'em', 'no', 'na', 'nos', 'nas', 'por', 'para', 'com', 'sem', 'que', 'e', 'ou',
            'se', 'mas', 'como', 'mais', 'menos', 'ser', 'ter', 'fazer', 'ir', 'ver', 'dar',
            'ao', 'aos', 'pelo', 'pela', 'pelos', 'pelas', 'este', 'esta', 'esse', 'essa',
            'aquele', 'aquela', 'isso', 'isto', 'aquilo', 'me', 'te', 'lhe', 'nos', 'vos',
            'ele', 'ela', 'eles', 'elas', 'eu', 'tu', 'nós', 'vós', 'não', 'sim', 'já',
            'foi', 'são', 'está', 'qual', 'quais', 'onde', 'quando', 'quem', 'sobre',
        ];

        $words = preg_split('/\s+/', mb_strtolower($query));
        $words = array_filter($words, function ($w) use ($stopWords) {
            return mb_strlen($w) >= 3 && !in_array($w, $stopWords);
        });

        return array_values(array_unique($words));
    }
}
