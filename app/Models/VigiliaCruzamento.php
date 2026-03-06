<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VigiliaCruzamento extends Model
{
    protected $table = 'vigilia_cruzamentos';

    protected $fillable = [
        'atividade_datajuri_id',
        'andamento_fase_id',
        'status_cruzamento',
        'dias_gap',
        'data_ultimo_andamento',
        'observacao',
    ];

    protected $casts = [
        'data_ultimo_andamento' => 'date',
        'dias_gap' => 'integer',
    ];

    public function atividade()
    {
        return $this->belongsTo(\App\Models\AtividadeDatajuri::class, 'atividade_datajuri_id', 'id');
    }
}
