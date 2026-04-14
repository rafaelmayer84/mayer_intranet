<?php

/**
 * ============================================================================
 * SIRIC v2 — SiricEvidencia (Model)
 * ============================================================================
 *
 * Armazena evidências/provas coletadas durante a análise de crédito.
 * Cada evidência vem de uma fonte específica e tem impacto classificado.
 *
 * Fontes: 'interno' (BD do escritório), 'asaas_serasa' (v2), 'web_intel'
 * Tipos: contas_receber, movimentos, processos, leads, serasa_report (v2)
 * Impacto: positivo, neutro, negativo, risco
 *
 * v2: Adicionada fonte 'asaas_serasa' com tipo 'serasa_report' para
 *     persistir dados estruturados extraídos do PDF Serasa.
 * ============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiricEvidencia extends Model
{
    protected $table = 'siric_evidencias';

    protected $fillable = [
        'consulta_id', 'fonte', 'tipo', 'payload', 'impacto', 'resumo',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(SiricConsulta::class, 'consulta_id');
    }

    /**
     * Cor do badge de impacto.
     */
    public function getImpactoCorAttribute(): string
    {
        return match ($this->impacto) {
            'positivo' => 'green',
            'neutro'   => 'gray',
            'negativo' => 'orange',
            'risco'    => 'red',
            default    => 'gray',
        };
    }
}
