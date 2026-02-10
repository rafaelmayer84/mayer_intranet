<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    protected $fillable = [
        'nome',
        'telefone',
        'contact_id',
        'area_interesse',
        'sub_area',
        'complexidade',
        'urgencia',
        'cidade',
        'resumo_demanda',
        'palavras_chave',
        'intencao_contratar',
        'intencao_justificativa',
        'objecoes',
        'gatilho_emocional',
        'perfil_socioeconomico',
        'potencial_honorarios',
        'origem_canal',
        'gclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'landing_page', 'referrer_url',
        'status',
        'espocrm_id',
        'erro_processamento',
        'metadata',
        'data_entrada'
    ];

    protected $casts = [
        'metadata' => 'array',
        'data_entrada' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relacionamento com mensagens do lead
     */
    public function messages(): HasMany
    {
        return $this->hasMany(LeadMessage::class)->orderBy('sent_at');
    }

    /**
     * Relacionamento com cliente (se convertido)
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relacionamento com oportunidades
     */
    public function oportunidades(): HasMany
    {
        return $this->hasMany(Oportunidade::class);
    }

    // ========== SCOPES PARA FILTROS ==========

    public function scopeArea($query, $area)
    {
        if ($area && $area !== 'todos') {
            return $query->where('area_interesse', $area);
        }
        return $query;
    }

    public function scopeCidade($query, $cidade)
    {
        if ($cidade && $cidade !== 'todos') {
            return $query->where('cidade', $cidade);
        }
        return $query;
    }

    public function scopePeriodo($query, $periodo)
    {
        return match ($periodo) {
            'hoje' => $query->whereDate('data_entrada', today()),
            'semana' => $query->where('data_entrada', '>=', now()->subDays(7)),
            'mes' => $query->where('data_entrada', '>=', now()->subDays(30)),
            'trimestre' => $query->where('data_entrada', '>=', now()->subDays(90)),
            default => $query,
        };
    }

    public function scopeIntencao($query, $intencao)
    {
        if ($intencao && $intencao !== 'todos') {
            return $query->where('intencao_contratar', $intencao);
        }
        return $query;
    }

    // ========== HELPERS ==========

    public function getOrigemLabelAttribute(): string
    {
        return match ($this->origem_canal) {
            'google_ads' => 'Google Ads',
            'indicacao' => 'Indicação',
            'redes_sociais' => 'Redes Sociais',
            'organico' => 'Orgânico',
            'outro' => 'Outro',
            default => 'Não identificado',
        };
    }

    public function getUrgenciaColorAttribute(): string
    {
        return match ($this->urgencia) {
            'crítica' => 'red',
            'alta' => 'orange',
            'média' => 'yellow',
            'baixa' => 'green',
            default => 'gray',
        };
    }

    public function getPotencialColorAttribute(): string
    {
        return match ($this->potencial_honorarios) {
            'alto' => 'emerald',
            'médio' => 'yellow',
            'baixo' => 'gray',
            default => 'gray',
        };
    }

    public function getIntencaoColorAttribute(): string
    {
        return match ($this->intencao_contratar) {
            'sim' => 'emerald',
            'talvez' => 'yellow',
            'não' => 'red',
            default => 'gray',
        };
    }
}
