<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class GdpCiclo extends Model
{
    protected $table = 'gdp_ciclos';

    protected $fillable = [
        'nome', 'data_inicio', 'data_fim', 'status', 'observacao', 'criado_por',
    ];

    protected $casts = [
        'data_inicio' => 'date',
        'data_fim'    => 'date',
    ];

    public function eixos(): HasMany
    {
        return $this->hasMany(GdpEixo::class, 'ciclo_id')->orderBy('ordem');
    }

    public function metas(): HasMany
    {
        return $this->hasMany(GdpMetaIndividual::class, 'ciclo_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(GdpResultadoMensal::class, 'ciclo_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(GdpSnapshot::class, 'ciclo_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por');
    }

    public static function ativo(): ?self
    {
        return static::where('status', 'aberto')->orderByDesc('data_inicio')->first();
    }

    public function contemMes(int $mes, int $ano): bool
    {
        $check = Carbon::createFromDate($ano, $mes, 1);
        return $check->greaterThanOrEqualTo($this->data_inicio->copy()->startOfMonth())
            && $check->lessThanOrEqualTo($this->data_fim->copy()->endOfMonth());
    }
}
