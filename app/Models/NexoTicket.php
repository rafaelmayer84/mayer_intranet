<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexoTicket extends Model
{
    protected $table = 'nexo_tickets';

    protected $fillable = [
        'cliente_id',
        'datajuri_id',
        'telefone',
        'nome_cliente',
        'tipo',
        'protocolo',
        'assunto',
        'mensagem',
        'status',
        'prioridade',
        'responsavel_id',
        'origem',
        'atendente',
        'resposta_interna',
        'resolvido_at',
        'resolucao',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'resolvido_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(\App\Models\User::class, 'responsavel_id');
    }

    public function notas()
    {
        return $this->hasMany(NexoTicketNota::class, 'ticket_id')->orderByDesc('created_at');
    }

    public function scopeAbertos($query)
    {
        return $query->whereIn('status', ['aberto', 'em_andamento']);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopePorResponsavel($query, int $userId)
    {
        return $query->where('responsavel_id', $userId);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'aberto' => 'Aberto',
            'em_andamento' => 'Em andamento',
            'concluido' => 'Concluido',
            'cancelado' => 'Cancelado',
            default => $this->status,
        };
    }

    public function getTipoLabelAttribute(): string
    {
        return match ($this->tipo) {
            'documento' => 'Documento',
            'agendamento' => 'Agendamento',
            'retorno' => 'Retorno',
            'financeiro' => 'Financeiro',
            'geral' => 'Geral',
            default => $this->tipo,
        };
    }

    public function getPrioridadeLabelAttribute(): string
    {
        return match ($this->prioridade) {
            'urgente' => 'Urgente',
            'normal' => 'Normal',
            default => $this->prioridade,
        };
    }

    public function isAberto(): bool
    {
        return in_array($this->status, ['aberto', 'em_andamento']);
    }
}
