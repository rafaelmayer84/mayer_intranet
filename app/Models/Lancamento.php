<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lancamento extends Model
{
    use HasFactory;

    protected $table = 'lancamentos';

    protected $fillable = [
        'datajuri_id',
        'cliente_id',
        'valor',
        'tipo',
        'descricao',
        'referencia',
        'data_lancamento',
        'data_recebimento',
        'usuario_responsavel',
        'metadata',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_lancamento' => 'date',
        'data_recebimento' => 'date',
        'metadata' => 'array',
    ];

    /**
     * Relacionamento com Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Scope para filtrar apenas receitas
     */
    public function scopeReceitas($query)
    {
        return $query->where('tipo', 'Receita');
    }

    /**
     * Scope para filtrar apenas despesas
     */
    public function scopeDespesas($query)
    {
        return $query->where('tipo', 'Despesa');
    }

    /**
     * Scope para filtrar por período
     */
    public function scopePorPeriodo($query, $dataInicio, $dataFim)
    {
        return $query->whereBetween('data_lancamento', [$dataInicio, $dataFim]);
    }

    /**
     * Scope para filtrar por cliente
     */
    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    /**
     * Scope para filtrar lançamentos do mês atual
     */
    public function scopeMesAtual($query)
    {
        return $query->whereYear('data_lancamento', now()->year)
                     ->whereMonth('data_lancamento', now()->month);
    }

    /**
     * Scope para filtrar lançamentos do mês anterior
     */
    public function scopeMesAnterior($query)
    {
        $mesAnterior = now()->subMonth();
        return $query->whereYear('data_lancamento', $mesAnterior->year)
                     ->whereMonth('data_lancamento', $mesAnterior->month);
    }

    /**
     * Scope para filtrar lançamentos do ano atual
     */
    public function scopeAnoAtual($query)
    {
        return $query->whereYear('data_lancamento', now()->year);
    }
}
