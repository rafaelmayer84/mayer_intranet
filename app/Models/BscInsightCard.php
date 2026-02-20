<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BscInsightCard extends Model
{
    protected $table = 'bsc_insight_cards';

    protected $fillable = [
        'run_id',
        'snapshot_id',
        'universo',
        'severidade',
        'confidence',
        'title',
        'what_changed',
        'why_it_matters',
        'evidences_json',
        'recommendation',
        'next_step',
        'questions_json',
        'dependencies_json',
        'evidence_keys_json',
        'impact_score',
    ];

    protected $casts = [
        'confidence'         => 'integer',
        'impact_score'       => 'float',
        'evidences_json'     => 'array',
        'questions_json'     => 'array',
        'dependencies_json'  => 'array',
        'evidence_keys_json' => 'array',
    ];

    const UNIVERSOS = [
        'FINANCEIRO',
        'CLIENTES_MERCADO',
        'PROCESSOS_INTERNOS',
        'TIMES_EVOLUCAO',
    ];

    const SEVERIDADES = ['INFO', 'ATENCAO', 'CRITICO'];

    const UNIVERSO_LABELS = [
        'FINANCEIRO'         => 'Financeiro',
        'CLIENTES_MERCADO'   => 'Clientes & Mercado',
        'PROCESSOS_INTERNOS' => 'Processos Internos',
        'TIMES_EVOLUCAO'     => 'Times & Evolução',
    ];

    const SEVERIDADE_COLORS = [
        'INFO'    => ['bg' => 'bg-blue-50', 'border' => 'border-blue-300', 'badge' => 'bg-blue-100 text-blue-800'],
        'ATENCAO' => ['bg' => 'bg-amber-50', 'border' => 'border-amber-300', 'badge' => 'bg-amber-100 text-amber-800'],
        'CRITICO' => ['bg' => 'bg-red-50', 'border' => 'border-red-300', 'badge' => 'bg-red-100 text-red-800'],
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiRun::class, 'run_id');
    }

    public function snapshot(): BelongsTo
    {
        return $this->belongsTo(BscInsightSnapshot::class, 'snapshot_id');
    }

    public function scopeByUniverso($query, string $universo)
    {
        return $query->where('universo', $universo);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByRaw("FIELD(severidade, 'CRITICO', 'ATENCAO', 'INFO')")
                     ->orderByDesc('impact_score');
    }

    public function getUniversoLabelAttribute(): string
    {
        return self::UNIVERSO_LABELS[$this->universo] ?? $this->universo;
    }

    public function getSeveridadeColorAttribute(): array
    {
        return self::SEVERIDADE_COLORS[$this->severidade] ?? self::SEVERIDADE_COLORS['INFO'];
    }

    public function getConfidenceLabelAttribute(): string
    {
        if ($this->confidence >= 80) return 'Alta';
        if ($this->confidence >= 50) return 'Média';
        return 'Baixa';
    }
}
