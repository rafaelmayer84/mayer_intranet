<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SisrhRbNivel extends Model
{
    protected $table = 'sisrh_rb_niveis';

    protected $fillable = [
        'nivel', 'ciclo_id', 'valor_rb', 'vigencia_inicio', 'vigencia_fim', 'created_by',
    ];

    protected $casts = [
        'valor_rb' => 'float',
        'vigencia_inicio' => 'date',
        'vigencia_fim' => 'date',
    ];

    public const NIVEIS = ['Junior', 'Pleno', 'Senior_I', 'Senior_II', 'Senior_III'];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GdpCiclo::class, 'ciclo_id');
    }
}
