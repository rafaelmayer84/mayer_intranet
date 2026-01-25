<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationLog extends Model
{
    protected $table = 'integration_logs';

    protected $fillable = [
        'sync_id',
        'tipo',
        'fonte',
        'status',
        'registros_processados',
        'registros_criados',
        'registros_atualizados',
        'registros_ignorados',
        'registros_erro',
        'mensagem_erro',
        'detalhes',
        'inicio',
        'fim',
        'duracao_segundos'
    ];

    protected $casts = [
        'detalhes' => 'array',
        'inicio' => 'datetime',
        'fim' => 'datetime'
    ];

    // Scopes
    public function scopeConcluidos($query)
    {
        return $query->where('status', 'concluido');
    }

    public function scopeComErro($query)
    {
        return $query->where('status', 'erro');
    }

    public function scopeRecentes($query, $dias = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($dias));
    }

    // Accessors
    public function getTipoLabelAttribute()
    {
        return match($this->tipo) {
            'sync_clientes' => 'Sincronização de Clientes',
            'sync_leads' => 'Sincronização de Leads',
            'sync_oportunidades' => 'Sincronização de Oportunidades',
            'sync_full' => 'Sincronização Completa',
            default => $this->tipo
        };
    }

    public function getStatusCorAttribute()
    {
        return match($this->status) {
            'concluido' => 'success',
            'erro' => 'danger',
            'em_progresso' => 'warning',
            'iniciado' => 'info',
            default => 'secondary'
        };
    }

    public function getDuracaoFormatadaAttribute()
    {
        if (!$this->duracao_segundos) {
            return '-';
        }

        if ($this->duracao_segundos < 60) {
            return $this->duracao_segundos . 's';
        }

        $minutos = floor($this->duracao_segundos / 60);
        $segundos = $this->duracao_segundos % 60;
        return $minutos . 'm ' . $segundos . 's';
    }
}
