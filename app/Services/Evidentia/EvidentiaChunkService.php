<?php

namespace App\Services\Evidentia;

use App\Models\EvidentiaChunk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EvidentiaChunkService
{
    private int $chunkSize;
    private int $overlap;

    public function __construct()
    {
        $this->chunkSize = config('evidentia.chunk_size_chars', 1000);
        $this->overlap   = config('evidentia.chunk_overlap_chars', 150);
    }

    /**
     * Gera chunks para uma jurisprudência específica.
     * Retorna número de chunks criados.
     */
    public function chunkJurisprudence(object $juris, string $tribunal, string $sourceDb): int
    {
        $jurisId = $juris->id;

        // Verifica se já existem chunks para esta jurisprudência
        $existing = EvidentiaChunk::where('jurisprudence_id', $jurisId)
            ->where('tribunal', $tribunal)
            ->where('source_db', $sourceDb)
            ->count();

        if ($existing > 0) {
            return 0; // Já processado
        }

        $chunksCreated = 0;

        // Chunk da ementa (obrigatório)
        $ementa = trim($juris->ementa ?? '');
        if (mb_strlen($ementa) > 0) {
            $textChunks = $this->splitText($ementa);
            foreach ($textChunks as $index => $text) {
                EvidentiaChunk::create([
                    'jurisprudence_id' => $jurisId,
                    'tribunal'         => $tribunal,
                    'source_db'        => $sourceDb,
                    'chunk_index'      => $index,
                    'chunk_text'       => $text,
                    'chunk_hash'       => sha1($text),
                    'chunk_source'     => 'ementa',
                ]);
                $chunksCreated++;
            }
        }

        // Chunk da decisão/inteiro teor (se habilitado e existir)
        if (config('evidentia.embed_inteiro_teor', false)) {
            $decisao = trim($juris->decisao ?? '');
            if (mb_strlen($decisao) > 100) {
                $textChunks = $this->splitText($decisao);
                foreach ($textChunks as $index => $text) {
                    $hash = sha1($text);
                    // Evita duplicata por hash
                    if (!EvidentiaChunk::where('chunk_hash', $hash)->exists()) {
                        EvidentiaChunk::create([
                            'jurisprudence_id' => $jurisId,
                            'tribunal'         => $tribunal,
                            'source_db'        => $sourceDb,
                            'chunk_index'      => $chunksCreated + $index,
                            'chunk_text'       => $text,
                            'chunk_hash'       => $hash,
                            'chunk_source'     => 'decisao',
                        ]);
                        $chunksCreated++;
                    }
                }
            }
        }

        return $chunksCreated;
    }

    /**
     * Divide texto em chunks com overlap.
     */
    public function splitText(string $text): array
    {
        $text = $this->normalizeText($text);
        $length = mb_strlen($text);

        if ($length <= $this->chunkSize) {
            return [$text];
        }

        $chunks = [];
        $pos = 0;

        while ($pos < $length) {
            $end = min($pos + $this->chunkSize, $length);
            $chunk = mb_substr($text, $pos, $end - $pos);

            // Tenta quebrar em fim de sentença (. ! ? ;) para não cortar no meio
            if ($end < $length) {
                $lastBreak = $this->findLastSentenceBreak($chunk);
                if ($lastBreak > $this->chunkSize * 0.5) {
                    $chunk = mb_substr($chunk, 0, $lastBreak + 1);
                    $end = $pos + $lastBreak + 1;
                }
            }

            $chunk = trim($chunk);
            if (mb_strlen($chunk) > 20) {
                $chunks[] = $chunk;
            }

            // Avança com overlap
            $pos = $end - $this->overlap;
            if ($pos <= 0 && $end >= $length) {
                break;
            }
            if ($end >= $length) {
                break;
            }
        }

        return $chunks;
    }

    /**
     * Encontra a posição do último fim de sentença no chunk.
     */
    private function findLastSentenceBreak(string $text): int
    {
        $lastDot       = mb_strrpos($text, '. ');
        $lastSemicolon = mb_strrpos($text, '; ');
        $lastExcl      = mb_strrpos($text, '! ');
        $lastQuest     = mb_strrpos($text, '? ');

        $positions = array_filter([$lastDot, $lastSemicolon, $lastExcl, $lastQuest], fn($p) => $p !== false);

        return !empty($positions) ? max($positions) + 1 : mb_strlen($text);
    }

    /**
     * Normaliza texto: remove espaços múltiplos, trim.
     */
    private function normalizeText(string $text): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Processa um lote de jurisprudências de um tribunal.
     * Retorna array com stats.
     */
    public function processarLoteTribunal(string $tribunal, int $limit = 500, int $offset = 0): array
    {
        $config = config("evidentia.tribunal_databases.{$tribunal}");
        if (!$config) {
            return ['error' => "Tribunal {$tribunal} não configurado"];
        }

        $juris = DB::connection($config['connection'])
            ->table($config['table'])
            ->where('tribunal', $tribunal)
            ->whereNotNull('ementa')
            ->where('ementa', '!=', '')
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get();

        $stats = ['total' => $juris->count(), 'chunks_created' => 0, 'skipped' => 0];

        foreach ($juris as $j) {
            $created = $this->chunkJurisprudence($j, $tribunal, $config['connection']);
            if ($created > 0) {
                $stats['chunks_created'] += $created;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }
}
