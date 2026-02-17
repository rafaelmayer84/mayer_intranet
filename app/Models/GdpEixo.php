<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GdpEixo extends Model
{
    protected $table = 'gdp_eixos';

    protected $fillable = ['ciclo_id', 'codigo', 'nome', 'peso', 'ordem'];

    protected $casts = ['peso' => 'decimal:2'];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(GdpCiclo::class, 'ciclo_id');
    }

    public function indicadores(): HasMany
    {
        return $this->hasMany(GdpIndicador::class, 'eixo_id')->orderBy('ordem');
    }
}
