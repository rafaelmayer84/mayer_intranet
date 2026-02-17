<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GdpIndicador extends Model
{
    protected $table = 'gdp_indicadores';

    protected $fillable = [
        'eixo_id', 'codigo', 'nome', 'descricao', 'chave_atribuicao',
        'chave_fallback', 'fonte_dados', 'unidade', 'direcao', 'peso',
        'cap_percentual', 'status_v1', 'ordem', 'ativo',
    ];

    protected $casts = [
        'peso'           => 'decimal:2',
        'cap_percentual' => 'decimal:2',
        'ativo'          => 'boolean',
    ];

    public function eixo(): BelongsTo
    {
        return $this->belongsTo(GdpEixo::class, 'eixo_id');
    }

    public function metas(): HasMany
    {
        return $this->hasMany(GdpMetaIndividual::class, 'indicador_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(GdpResultadoMensal::class, 'indicador_id');
    }
}
