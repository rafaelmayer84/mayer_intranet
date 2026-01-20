<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Meta extends Model
{
    protected $fillable = [
        'advogado_id',
        'ano',
        'mes',
        'meta_faturamento',
        'meta_processos',
        'meta_atividades',
        'meta_horas',
    ];

    protected $casts = [
        'meta_faturamento' => 'decimal:2',
        'meta_processos' => 'integer',
        'meta_atividades' => 'integer',
        'meta_horas' => 'decimal:2',
    ];

    public function advogado(): BelongsTo
    {
        return $this->belongsTo(Advogado::class);
    }
}
