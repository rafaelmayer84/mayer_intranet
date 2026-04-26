<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class NexoLexusSessao extends Model
{
    protected $table = 'nexo_lexus_sessoes';

    protected $fillable = [
        'conversation_id',
        'phone',
        'contato',
        'etapa',
        'area_provavel',
        'intencao_contratar',
        'urgencia',
        'nome_cliente',
        'cidade',
        'resumo_caso',
        'briefing_operador',
        'contexto_json',
        'total_interacoes',
        'input_tokens_total',
        'output_tokens_total',
        'lead_id',
        'cliente_id',
        'ultima_atividade',
    ];

    protected $casts = [
        'contexto_json'    => 'array',
        'ultima_atividade' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    // Sessões prontas para atendimento humano (implementação completa na Fase 4)
    public function scopeAguardandoAtendimento(Builder $query): Builder
    {
        return $query->where('etapa', 'qualificado')->whereNotNull('lead_id');
    }
}
