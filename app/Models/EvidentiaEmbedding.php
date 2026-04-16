<?php

// ESTÁVEL desde 16/04/2026
//
// ┌─────────────────────────────────────────────────────────────────────────┐
// │  EvidentiaEmbedding — Model de Vetores  v2.1                           │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Armazena embeddings de chunks de jurisprudência em formato float16    │
// │  (BLOB binário) para busca semântica por cosine similarity.             │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Formato de armazenamento                                               │
// │  - vector_bin (BLOB): float16 big-endian, 2 bytes/dim → 3072 bytes     │
// │    para 1536 dimensões (text-embedding-3-small). ~50% menor que JSON.  │
// │  - vector_json (JSON, legado): array de float32. Presente somente em   │
// │    registros anteriores à migration 2026_04_16_000001 no banco evidentia│
// ├─────────────────────────────────────────────────────────────────────────┤
// │  Sharding por tribunal                                                  │
// │  TJSC → emb_tjsc  |  STJ → emb_stj  |  TRF4/TRT12 → evidentia         │
// │  Acesso via: EvidentiaEmbedding::onTribunal('STJ')                      │
// │             EvidentiaEmbedding::connectionForTribunal('TJSC')           │
// ├─────────────────────────────────────────────────────────────────────────┤
// │  ATENÇÃO: floatToHalf/halfToFloat são implementações IEEE 754 manuais. │
// │  Não alterar sem validar o round-trip em EvidentiaSearchTest.          │
// └─────────────────────────────────────────────────────────────────────────┘

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidentiaEmbedding extends Model
{
    protected $table = 'evidentia_embeddings';

    protected $fillable = [
        'chunk_id',
        'model',
        'dims',
        'vector_bin',
        'norm',
    ];

    protected $casts = [
        'norm' => 'double',
        'dims' => 'integer',
    ];

    /**
     * Resolve a connection correta baseada no tribunal do chunk.
     * Usado via EvidentiaEmbedding::onTribunal('STJ')->where(...)
     */
    public static function onTribunal(string $tribunal): \Illuminate\Database\Eloquent\Builder
    {
        $conn = self::connectionForTribunal($tribunal);
        return (new static)->setConnection($conn)->newQuery();
    }

    /**
     * Retorna o nome da connection para um tribunal.
     */
    public static function connectionForTribunal(string $tribunal): string
    {
        $map = config('evidentia.embedding_databases', []);
        return $map[strtoupper($tribunal)] ?? ($map['default'] ?? 'evidentia');
    }

    /**
     * Relationship com chunk (precisa ser no mesmo banco).
     */
    public function chunk(): BelongsTo
    {
        return $this->belongsTo(EvidentiaChunk::class, 'chunk_id');
    }

    /**
     * Converte array de floats para BLOB float16 (half-precision).
     * Cada float ocupa 2 bytes. 1536 dims = 3072 bytes.
     */
    public static function vectorToBin(array $vector): string
    {
        $bin = '';
        foreach ($vector as $val) {
            $bin .= self::floatToHalf((float) $val);
        }
        return $bin;
    }

    /**
     * Converte BLOB float16 de volta para array de floats.
     */
    public static function binToVector(string $bin): array
    {
        $vector = [];
        $len = strlen($bin);
        for ($i = 0; $i < $len; $i += 2) {
            $vector[] = self::halfToFloat(substr($bin, $i, 2));
        }
        return $vector;
    }

    /**
     * Retorna o vetor como array de floats, decodificando do binário.
     */
    public function getVector(): array
    {
        $raw = $this->attributes['vector_bin'] ?? null;
        if ($raw === null) {
            return [];
        }
        return self::binToVector($raw);
    }

    /**
     * Calcula similaridade coseno com outro vetor.
     * Otimizado: decodifica float16 direto no loop do dot product
     * para evitar alocação de array intermediário.
     */
    public function cosineSimilarity(array $queryVector, float $queryNorm): float
    {
        $raw = $this->attributes['vector_bin'] ?? null;
        if ($raw === null || $this->norm == 0 || $queryNorm == 0) {
            return 0.0;
        }

        $dot = 0.0;
        $len = strlen($raw);
        $count = min((int)($len / 2), count($queryVector));

        for ($i = 0; $i < $count; $i++) {
            $val = self::halfToFloat(substr($raw, $i * 2, 2));
            $dot += $val * $queryVector[$i];
        }

        return $dot / ($this->norm * $queryNorm);
    }

    /**
     * Converte float32 para float16 (2 bytes, big-endian).
     * IEEE 754 half-precision: 1 bit sign, 5 bits exponent, 10 bits mantissa.
     */
    private static function floatToHalf(float $val): string
    {
        // Pack as float32, extract bits
        $f32 = unpack('N', pack('G', $val))[1];

        $sign     = ($f32 >> 31) & 0x1;
        $exponent = ($f32 >> 23) & 0xFF;
        $mantissa = $f32 & 0x7FFFFF;

        if ($exponent == 0xFF) {
            // Inf/NaN
            $halfExp = 0x1F;
            $halfMan = $mantissa ? 0x200 : 0; // NaN or Inf
        } elseif ($exponent == 0) {
            // Zero or subnormal -> zero in float16
            $halfExp = 0;
            $halfMan = 0;
        } else {
            $newExp = $exponent - 127 + 15;
            if ($newExp >= 0x1F) {
                // Overflow -> Inf
                $halfExp = 0x1F;
                $halfMan = 0;
            } elseif ($newExp <= 0) {
                // Underflow -> zero
                $halfExp = 0;
                $halfMan = 0;
            } else {
                $halfExp = $newExp;
                $halfMan = $mantissa >> 13;
            }
        }

        $half = ($sign << 15) | ($halfExp << 10) | $halfMan;
        return pack('n', $half); // big-endian unsigned short
    }

    /**
     * Converte float16 (2 bytes, big-endian) para float32.
     */
    private static function halfToFloat(string $bytes): float
    {
        $half = unpack('n', $bytes)[1];

        $sign     = ($half >> 15) & 0x1;
        $exponent = ($half >> 10) & 0x1F;
        $mantissa = $half & 0x3FF;

        if ($exponent == 0x1F) {
            // Inf/NaN
            $f32 = ($sign << 31) | (0xFF << 23) | ($mantissa << 13);
        } elseif ($exponent == 0) {
            if ($mantissa == 0) {
                // Zero
                $f32 = ($sign << 31);
            } else {
                // Subnormal -> normalize
                $e = -1;
                $m = $mantissa;
                while (($m & 0x400) == 0) {
                    $m <<= 1;
                    $e--;
                }
                $m &= 0x3FF;
                $f32 = ($sign << 31) | (($e + 127 + 15) << 23) | ($m << 13);
            }
        } else {
            $f32 = ($sign << 31) | (($exponent - 15 + 127) << 23) | ($mantissa << 13);
        }

        return unpack('G', pack('N', $f32))[1];
    }
}
