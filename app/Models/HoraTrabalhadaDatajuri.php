<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HoraTrabalhadaDatajuri extends Model
{
    use HasFactory;

    protected $table = 'horas_trabalhadas_datajuri';

    protected $fillable = [
        'datajuri_id',
        'data',
        'duracao_original',
        'total_hora_trabalhada',
        'hora_inicial',
        'hora_final',
        'valor_hora',
        'valor_total_original',
        'assunto',
        'tipo',
        'status',
        'proprietario_id',
        'particular',
        'data_faturado',
    ];

    protected $casts = [
        'data' => 'date',
        'hora_inicial' => 'datetime:H:i',
        'hora_final' => 'datetime:H:i',
        'valor_hora' => 'decimal:2',
        'valor_total_original' => 'decimal:2',
        'particular' => 'boolean',
        'data_faturado' => 'date',
    ];

    // Scopes
    public function scopePorProprietario($query, int $proprietarioId)
    {
        return $query->where('proprietario_id', $proprietarioId);
    }

    public function scopeFaturadas($query)
    {
        return $query->whereNotNull('data_faturado');
    }

    public function scopeNaoFaturadas($query)
    {
        return $query->whereNull('data_faturado');
    }

    public function scopeNoMes($query, int $ano, int $mes)
    {
        return $query->whereYear('data', $ano)
                     ->whereMonth('data', $mes);
    }

    public function scopePublicas($query)
    {
        return $query->where('particular', false);
    }

    // Acessor para duração em minutos
    public function getDuracaoMinutosAttribute(): int
    {
        if (empty($this->duracao_original)) return 0;
        
        $parts = explode(':', $this->duracao_original);
        if (count($parts) >= 2) {
            return ((int) $parts[0] * 60) + (int) $parts[1];
        }
        return 0;
    }
}
