<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model: FaseProcesso
 * Tabela: fases_processo
 * Módulo DataJuri: Fase
 *
 * Cada fase de um processo judicial (1ª Instância, 2ª Instância, etc.).
 * O campo fase_atual=1 indica a fase corrente.
 * dias_fase_ativa e data_ultimo_andamento são críticos para o KPI de processos parados.
 */
class FaseProcesso extends Model
{
    use HasFactory;

    protected $table = 'fases_processo';

    protected $fillable = [
        'datajuri_id',
        'processo_pasta',
        'processo_id_datajuri',
        'tipo_fase',
        'descricao_fase',
        'localidade',
        'instancia',
        'data',
        'fase_atual',
        'dias_fase_ativa',
        'data_ultimo_andamento',
        'proprietario_nome',
        'proprietario_id',
    ];

    protected $casts = [
        'data'                  => 'date',
        'data_ultimo_andamento' => 'date',
        'fase_atual'            => 'boolean',
    ];

    /* ------------------------------------------------------------------ */
    /* Scopes                                                             */
    /* ------------------------------------------------------------------ */

    /** Apenas fases ativas (correntes) */
    public function scopeAtual($query)
    {
        return $query->where('fase_atual', 1);
    }

    /** Fases com último andamento antes de X dias atrás */
    public function scopeParados($query, int $dias = 30)
    {
        return $query->where('fase_atual', 1)
                     ->where('data_ultimo_andamento', '<', now()->subDays($dias));
    }

    /* ------------------------------------------------------------------ */
    /* Relacionamentos                                                    */
    /* ------------------------------------------------------------------ */

    public function processo()
    {
        return $this->belongsTo(Processo::class, 'processo_pasta', 'pasta');
    }

    public function andamentos()
    {
        return $this->hasMany(AndamentoFase::class, 'fase_processo_id_datajuri', 'datajuri_id');
    }
}
