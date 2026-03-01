<?php

namespace App\Services\Justus;

use App\Models\JustusDocumentChunk;
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

        if (empty($keywords)) {
            return JustusDocumentChunk::whereIn('attachment_id', $attachmentIds)
                ->orderBy('page_start')
                ->limit($maxChunks)
                ->get();
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

    private function fallbackLikeSearch(Collection $attachmentIds, array $keywords, int $maxChunks): Collection
    {
        $query = JustusDocumentChunk::whereIn('attachment_id', $attachmentIds);

        foreach ($keywords as $kw) {
            $query->where('content', 'LIKE', '%' . $kw . '%');
        }

        return $query->orderBy('page_start')->limit($maxChunks)->get();
    }

    public function buildContextFromChunks(Collection $chunks): string
    {
        if ($chunks->isEmpty()) {
            return 'Nenhum trecho dos autos disponível para consulta.';
        }

        $context = "TRECHOS DOS AUTOS (com páginas):\n\n";
        foreach ($chunks as $chunk) {
            $context .= "--- Páginas {$chunk->page_start}–{$chunk->page_end} ---\n";
            $context .= $chunk->content . "\n\n";
        }

        return $context;
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
