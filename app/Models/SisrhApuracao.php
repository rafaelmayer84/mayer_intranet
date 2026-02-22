<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SisrhApuracao extends Model
{
    protected $table = 'sisrh_apuracoes';

    protected $fillable = [
        'user_id', 'ano', 'mes', 'ciclo_id',
        'rb_valor', 'captacao_valor', 'gdp_score', 'percentual_faixa',
        'rv_bruta', 'reducao_conformidade_pct', 'reducao_acompanhamento_pct',
        'reducao_total_pct', 'rv_pos_reducoes', 'teto_rv_valor',
        'rv_aplicada', 'rv_excedente_credito', 'credito_utilizado',
        'status', 'bloqueio_motivo', 'snapshot_json', 'snapshot_hash',
        'closed_by', 'closed_at',
    ];

    protected $casts = [
        'rb_valor' => 'float',
        'captacao_valor' => 'float',
        'gdp_score' => 'float',
        'percentual_faixa' => 'float',
        'rv_bruta' => 'float',
        'reducao_conformidade_pct' => 'float',
        'reducao_acompanhamento_pct' => 'float',
        'reducao_total_pct' => 'float',
        'rv_pos_reducoes' => 'float',
        'teto_rv_valor' => 'float',
        'rv_aplicada' => 'float',
        'rv_excedente_credito' => 'float',
        'credito_utilizado' => 'float',
        'snapshot_json' => 'array',
        'closed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GdpCiclo::class, 'ciclo_id');
    }

    public function ajustes(): HasMany
    {
        return $this->hasMany(SisrhAjuste::class, 'apuracao_id');
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isBlocked(): bool
    {
        return !empty($this->bloqueio_motivo);
    }
}
