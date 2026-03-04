<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class EvidentiaChunk extends Model
{
    protected $table = 'evidentia_chunks';

    protected $fillable = [
        'jurisprudence_id',
        'tribunal',
        'source_db',
        'chunk_index',
        'chunk_text',
        'chunk_hash',
        'chunk_source',
    ];

    /**
     * Relação com o embedding deste chunk.
     */
    public function embedding(): HasOne
    {
        return $this->hasOne(EvidentiaEmbedding::class, 'chunk_id');
    }

    /**
     * Verifica se este chunk já tem embedding gerado.
     */
    public function hasEmbedding(): bool
    {
        return $this->embedding()->exists();
    }

    /**
     * Retorna os dados da jurisprudência original (cross-database).
     * Como é cross-database, não usamos Eloquent relationship direta.
     */
    public function getJurisprudence(): ?object
    {
        $config = config("evidentia.tribunal_databases.{$this->tribunal}");
        if (!$config) {
            return null;
        }

        return \DB::connection($config['connection'])
            ->table($config['table'])
            ->where('id', $this->jurisprudence_id)
            ->first();
    }
}
