<?php

namespace App\Models\Gdp;

use Illuminate\Database\Eloquent\Model;

class GdpPenalizacao extends Model
{
    protected $table = 'gdp_penalizacoes';

    protected $fillable = [
        'ciclo_id', 'user_id', 'tipo_id', 'mes', 'ano',
        'pontos_desconto', 'descricao_automatica', 'referencia_tipo',
        'referencia_id', 'automatica', 'contestada', 'contestacao_texto',
        'contestacao_status', 'contestacao_por', 'contestacao_em',
    ];

    protected $casts = [
        'automatica' => 'boolean',
        'contestada' => 'boolean',
        'contestacao_em' => 'datetime',
    ];

    public function tipo()
    {
        return $this->belongsTo(GdpPenalizacaoTipo::class, 'tipo_id');
    }

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function ciclo()
    {
        return $this->belongsTo(\App\Models\GdpCiclo::class, 'ciclo_id');
    }

    public function avaliador()
    {
        return $this->belongsTo(\App\Models\User::class, 'contestacao_por');
    }

    public static function totalDescontoEixo(int $userId, int $eixoId, int $mes, int $ano): float
    {
        return static::where('user_id', $userId)
            ->where('mes', $mes)
            ->where('ano', $ano)
            ->whereHas('tipo', fn($q) => $q->where('eixo_id', $eixoId))
            ->where(function ($q) {
                $q->whereNull('contestacao_status')
                  ->orWhere('contestacao_status', '!=', 'aceita');
            })
            ->sum('pontos_desconto');
    }
}
