<?php

namespace App\Models\Gdp;

use Illuminate\Database\Eloquent\Model;

class GdpPenalizacaoTipo extends Model
{
    protected $table = 'gdp_penalizacao_tipos';

    protected $fillable = [
        'codigo', 'eixo_id', 'nome', 'descricao', 'gravidade',
        'pontos_desconto', 'threshold_valor', 'threshold_unidade',
        'fonte_tabela', 'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function eixo()
    {
        return $this->belongsTo(\App\Models\GdpEixo::class, 'eixo_id');
    }

    public function penalizacoes()
    {
        return $this->hasMany(GdpPenalizacao::class, 'tipo_id');
    }
}
