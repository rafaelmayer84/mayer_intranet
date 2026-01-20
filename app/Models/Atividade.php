<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atividade extends Model
{
    protected $fillable = [
        'datajuri_id',
        'titulo',
        'status',
        'tipo',
        'responsavel_nome',
        'responsavel_id',
        'processo_id',
        'data_vencimento',
        'data_conclusao',
    ];

    protected $casts = [
        'data_vencimento' => 'date',
        'data_conclusao' => 'date',
    ];
}
