<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustusProcessProfile extends Model
{
    protected $table = 'justus_process_profiles';

    protected $fillable = [
        'conversation_id',
        'numero_cnj',
        'classe',
        'orgao',
        'fase_atual',
        'relator_vara',
        'autor',
        'reu',
        'objetivo_analise',
        'tese_principal',
        'limites_restricoes',
        'data_intimacao',
        'prazo_medio',
        'partes_extras',
        'datas_relevantes',
        'manual_estilo_aceito',
    ];

    protected $casts = [
        'partes_extras' => 'array',
        'datas_relevantes' => 'array',
        'data_intimacao' => 'date',
        'manual_estilo_aceito' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(JustusConversation::class, 'conversation_id');
    }

    public function getFieldOrPlaceholder(string $field): string
    {
        $value = $this->getAttribute($field);
        if (empty($value)) {
            return '[dado não localizado nos autos]';
        }
        return $value;
    }
}
