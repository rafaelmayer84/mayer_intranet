<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Processo extends Model
{
    protected $fillable = [
        'datajuri_id',
        'numero',
        'titulo',
        'status',
        'tipo_acao',
        'area',
        'cliente_nome',
        'cliente_id',
        'valor_causa',
        'advogado_responsavel',
        'advogado_id',
        'data_distribuicao',
        'data_conclusao',
    ];

    protected $casts = [
        'valor_causa' => 'decimal:2',
        'data_distribuicao' => 'date',
        'data_conclusao' => 'date',
    ];
}
