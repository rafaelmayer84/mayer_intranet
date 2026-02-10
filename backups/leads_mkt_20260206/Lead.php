<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'telefone',
        'contact_id',
        'area_interesse',
        'cidade',
        'resumo_demanda',
        'palavras_chave',
        'intencao_contratar',
        'gclid',
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
     * Scope para filtrar por área
     */
    public function scopeArea($query, $area)
    {
        if ($area && $area !== 'todos') {
            return $query->where('area_interesse', $area);
        }
        return $query;
    }

    /**
     * Scope para filtrar por cidade
     */
    public function scopeCidade($query, $cidade)
    {
        if ($cidade && $cidade !== 'todos') {
            return $query->where('cidade', $cidade);
        }
        return $query;
    }

    /**
     * Scope para filtrar por período
     */
    public function scopePeriodo($query, $periodo)
    {
        return match($periodo) {
            'hoje' => $query->whereDate('data_entrada', today()),
            'semana' => $query->where('data_entrada', '>=', now()->subDays(7)),
            'mes' => $query->where('data_entrada', '>=', now()->subDays(30)),
            default => $query
        };
    }

    /**
     * Scope para filtrar por intenção
     */
    public function scopeIntencao($query, $intencao)
    {
        if ($intencao && $intencao !== 'todos') {
            return $query->where('intencao_contratar', $intencao);
        }
        return $query;
    }

    /**
     * Scope para leads não processados
     */
    public function scopeNaoProcessados($query)
    {
        return $query->whereNull('resumo_demanda')
                    ->orWhere('resumo_demanda', '');
    }

    /**
     * Verifica se o lead tem erro de processamento
     */
    public function temErro(): bool
    {
        return !empty($this->erro_processamento);
    }

    /**
     * Retorna badge de status
     */
    public function getStatusBadge(): string
    {
        return match($this->status) {
            'novo' => '<span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Novo</span>',
            'contatado' => '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Contatado</span>',
            'qualificado' => '<span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Qualificado</span>',
            'convertido' => '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Convertido</span>',
            'descartado' => '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">Descartado</span>',
            default => '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">' . ucfirst($this->status) . '</span>'
        };
    }

    /**
     * Retorna badge de intenção
     */
    public function getIntencaoBadge(): string
    {
        return match($this->intencao_contratar) {
            'sim' => '<span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Sim</span>',
            'não' => '<span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">Não</span>',
            'talvez' => '<span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">Talvez</span>',
            default => '<span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">' . ucfirst($this->intencao_contratar) . '</span>'
        };
    }
}
