<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BscInsightCardV2 extends Model
{
    protected $table = 'bsc_insight_cards_v2';

    public $timestamps = false;

    protected $fillable = [
        'run_id',
        'perspectiva',
        'severidade',
        'titulo',
        'descricao',
        'recomendacao',
        'acao_sugerida',
        'metricas_referenciadas_json',
        'evidencias_json',
        'confidence',
        'impact_score',
        'ordem',
    ];

    protected $casts = [
        'confidence'   => 'integer',
        'impact_score' => 'float',
        'ordem'        => 'integer',
        'created_at'   => 'datetime',
    ];

    // ── Perspectivas e severidades válidas ──

    public const PERSPECTIVAS = ['financeiro', 'clientes', 'processos', 'times'];
    public const SEVERIDADES  = ['info', 'atencao', 'critico'];

    // ── Relationships ──

    public function run(): BelongsTo
    {
        return $this->belongsTo(BscInsightRun::class, 'run_id');
    }

    // ── Accessors ──

    public function getMetricasReferenciadasAttribute(): array
    {
        $raw = $this->attributes['metricas_referenciadas_json'] ?? null;
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    public function getEvidenciasAttribute(): array
    {
        $raw = $this->attributes['evidencias_json'] ?? null;
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    public function getSeveridadeColorAttribute(): string
    {
        return match ($this->severidade) {
            'critico' => '#ef4444',
            'atencao' => '#f59e0b',
            default   => '#3b82f6',
        };
    }

    public function getSeveridadeLabelAttribute(): string
    {
        return match ($this->severidade) {
            'critico' => 'Crítico',
            'atencao' => 'Atenção',
            default   => 'Info',
        };
    }

    public function getPerspectivaLabelAttribute(): string
    {
        return match ($this->perspectiva) {
            'financeiro' => 'Financeiro',
            'clientes'   => 'Clientes & Mercado',
            'processos'  => 'Processos Internos',
            'times'      => 'Times & Evolução',
            default      => ucfirst($this->perspectiva),
        };
    }

    // ── Scopes ──

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("FIELD(severidade, 'critico', 'atencao', 'info')")
                     ->orderByDesc('impact_score')
                     ->orderBy('ordem');
    }

    public function scopePerspectiva($query, string $perspectiva)
    {
        return $query->where('perspectiva', $perspectiva);
    }

    public function scopeCriticos($query)
    {
        return $query->where('severidade', 'critico');
    }
}
