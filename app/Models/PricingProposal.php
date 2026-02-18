<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingProposal extends Model
{
    protected $table = 'pricing_proposals';

    protected $fillable = [
        'user_id', 'lead_id', 'cliente_id', 'oportunidade_id', 'crm_opportunity_id',
        'nome_proponente', 'documento_proponente', 'tipo_pessoa',
        'area_direito', 'tipo_acao', 'descricao_demanda', 'valor_causa', 'valor_economico',
        'contexto_adicional',
        'siric_score', 'siric_rating', 'siric_limite', 'siric_recomendacao',
        'calibracao_snapshot', 'historico_agregado',
        'proposta_rapida', 'proposta_equilibrada', 'proposta_premium',
        'recomendacao_ia', 'justificativa_ia',
        'proposta_escolhida', 'valor_final', 'status', 'observacao_advogado',
        'texto_proposta_cliente',
    ];

    protected $casts = [
        'valor_causa' => 'decimal:2',
        'valor_economico' => 'decimal:2',
        'siric_limite' => 'decimal:2',
        'valor_final' => 'decimal:2',
        'calibracao_snapshot' => 'array',
        'historico_agregado' => 'array',
        'proposta_rapida' => 'array',
        'proposta_equilibrada' => 'array',
        'proposta_premium' => 'array',
        'texto_proposta_cliente' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function scopeDoUsuario($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Retorna a proposta escolhida como array
     */
    public function getPropostaEscolhidaDetalhes(): ?array
    {
        if (!$this->proposta_escolhida) {
            return null;
        }
        $campo = 'proposta_' . $this->proposta_escolhida;
        return $this->$campo;
    }

    public function crmOpportunity()
    {
        return $this->belongsTo(\App\Models\Crm\CrmOpportunity::class, 'crm_opportunity_id');
    }

}
