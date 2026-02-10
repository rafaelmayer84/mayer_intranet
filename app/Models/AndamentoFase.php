<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model: AndamentoFase
 * Tabela: andamentos_fase
 * Módulo DataJuri: AndamentoFase
 *
 * Registra cada andamento processual vinculado a uma fase.
 * Usado pelo dashboard Processos Internos para calcular SLA,
 * identificar processos sem movimentação e métricas de throughput.
 */
class AndamentoFase extends Model
{
    use HasFactory;

    protected $table = 'andamentos_fase';

    protected $fillable = [
        'datajuri_id',
        'fase_processo_id_datajuri',
        'processo_id_datajuri',
        'processo_pasta',
        'data_andamento',
        'descricao',
        'tipo',
        'parecer',
        'parecer_revisado',
        'parecer_revisado_por',
        'data_parecer_revisado',
        'proprietario_id',
        'proprietario_nome',
    ];

    protected $casts = [
        'data_andamento'        => 'date',
        'data_parecer_revisado' => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /* Relacionamentos                                                    */
    /* ------------------------------------------------------------------ */

    /**
     * Processo ao qual este andamento pertence (via processo_pasta).
     * Usa a coluna "pasta" na tabela processos como vínculo.
     */
    public function processo()
    {
        return $this->belongsTo(Processo::class, 'processo_pasta', 'pasta');
    }

    /**
     * Fase do processo (via datajuri_id da fase).
     */
    public function faseProcesso()
    {
        return $this->belongsTo(FaseProcesso::class, 'fase_processo_id_datajuri', 'datajuri_id');
    }
}
