<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Oportunidade extends Model
{
    use SoftDeletes;

    protected $table = 'oportunidades';

    protected $fillable = [
        'cliente_id',
        'lead_id',
        'nome',
        'estagio',
        'valor',
        'tipo',
        'responsavel_id',
        'espocrm_id',
        'datajuri_contrato_id',
        'data_criacao',
        'data_fechamento',
        'observacoes',
        'metadata'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'metadata' => 'array',
        'data_criacao' => 'date',
        'data_fechamento' => 'date'
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function responsavel()
    {
        return $this->belongsTo(\App\Models\User::class, 'responsavel_id');
    }

    // Scopes
    public function scopeAbertas($query)
    {
        return $query->whereIn('estagio', ['prospectando', 'qualificacao', 'proposta', 'negociacao']);
    }

    public function scopeGanhas($query)
    {
        return $query->where('estagio', 'ganha');
    }

    public function scopePerdidas($query)
    {
        return $query->where('estagio', 'perdida');
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // Accessors
    public function getEstagioLabelAttribute()
    {
        return match($this->estagio) {
            'prospectando' => 'Prospectando',
            'qualificacao' => 'Qualificação',
            'proposta' => 'Proposta Enviada',
            'negociacao' => 'Em Negociação',
            'ganha' => 'Ganha',
            'perdida' => 'Perdida',
            default => $this->estagio
        };
    }

    public function getValorFormatadoAttribute()
    {
        return 'R$ ' . number_format($this->valor, 2, ',', '.');
    }

    public function getStatusCorAttribute()
    {
        return match($this->estagio) {
            'ganha' => 'success',
            'perdida' => 'danger',
            'negociacao' => 'warning',
            default => 'info'
        };
    }
}
