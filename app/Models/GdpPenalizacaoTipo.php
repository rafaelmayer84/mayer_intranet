<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GdpPenalizacaoTipo extends Model
{
    protected $table = 'gdp_penalizacao_tipos';

    protected $fillable = [
        'codigo','eixo_id','nome','descricao','gravidade',
        'pontos_desconto','threshold_valor','threshold_unidade',
        'fonte_tabela','ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'pontos_desconto' => 'integer',
        'threshold_valor' => 'integer',
    ];

    public function eixo(): BelongsTo
    {
        return $this->belongsTo(GdpEixo::class, 'eixo_id');
    }

    public function penalizacoes(): HasMany
    {
        return $this->hasMany(GdpPenalizacao::class, 'tipo_id');
    }

    public function configOverrides(): HasMany
    {
        return $this->hasMany(GdpPenalizacaoConfig::class, 'tipo_id');
    }

    public function getThresholdEfetivo(int $cicloId): int
    {
        $override = $this->configOverrides()->where('ciclo_id', $cicloId)->first();
        return $override && $override->threshold_valor !== null
            ? $override->threshold_valor : $this->threshold_valor;
    }

    public function getPontosEfetivo(int $cicloId): int
    {
        $override = $this->configOverrides()->where('ciclo_id', $cicloId)->first();
        return $override && $override->pontos_desconto !== null
            ? $override->pontos_desconto : $this->pontos_desconto;
    }

    public function isAtivoNoCiclo(int $cicloId): bool
    {
        $override = $this->configOverrides()->where('ciclo_id', $cicloId)->first();
        return $override && $override->ativo !== null
            ? $override->ativo : $this->ativo;
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeDoEixo($query, string $codigoEixo)
    {
        return $query->whereHas('eixo', fn($q) => $q->where('codigo', $codigoEixo));
    }
}
