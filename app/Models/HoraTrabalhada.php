<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoraTrabalhada extends Model
{
    protected $table = 'horas_trabalhadas';
    
    protected $fillable = [
        'datajuri_id',
        'descricao',
        'horas',
        'valor_hora',
        'valor_total',
        'status',
        'tipo_atividade',
        'responsavel_nome',
        'responsavel_id',
        'processo_id',
        'data_lancamento',
    ];

    protected $casts = [
        'horas' => 'decimal:2',
        'valor_hora' => 'decimal:2',
        'valor_total' => 'decimal:2',
        'data_lancamento' => 'date',
    ];
}
