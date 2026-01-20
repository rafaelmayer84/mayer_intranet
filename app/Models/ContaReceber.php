<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContaReceber extends Model
{
    protected $table = 'contas_receber';
    
    protected $fillable = [
        'datajuri_id',
        'descricao',
        'valor',
        'data_vencimento',
        'data_pagamento',
        'status',
        'plano_conta',
        'cliente',
        'cliente_id',
        'responsavel_nome',
        'responsavel_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_vencimento' => 'date',
        'data_pagamento' => 'date',
    ];
}
