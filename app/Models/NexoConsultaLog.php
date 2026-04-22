<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexoConsultaLog extends Model
{
    protected $table = 'nexo_consulta_log';

    public $timestamps = false;

    protected $fillable = [
        'telefone',
        'cliente_id',
        'acao',
        'resultado',
        'ip',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];
}
