<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpAcompanhamento extends Model
{
    protected $table = 'gdp_acompanhamentos';

    protected $fillable = [
        'user_id', 'ciclo_id', 'ano', 'bimestre',
        'respostas_json', 'status', 'submitted_at',
        'validated_by', 'validated_at', 'observacoes_validador',
    ];

    protected $casts = [
        'respostas_json' => 'array',
        'submitted_at' => 'datetime',
        'validated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GdpCiclo::class, 'ciclo_id');
    }

    public function validador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'validated_by');
    }

    /**
     * Dado um mês (1-12), retorna o bimestre correspondente.
     * Bimestre 1 = jan-fev, 2 = mar-abr, 3 = mai-jun, 4 = jul-ago, 5 = set-out, 6 = nov-dez
     */
    public static function mesBimestre(int $mes): int
    {
        return (int) ceil($mes / 2);
    }
}
