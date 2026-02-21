<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GdpPenalizacao extends Model
{
    protected $table = 'gdp_penalizacoes';

    protected $fillable = [
        'ciclo_id','user_id','tipo_id','mes','ano','pontos_desconto',
        'descricao_automatica','referencia_tipo','referencia_id',
        'automatica','contestada','contestacao_texto',
        'contestacao_status','contestacao_por','contestacao_em',
    ];

    protected $casts = [
        'automatica' => 'boolean',
        'contestada' => 'boolean',
        'pontos_desconto' => 'integer',
        'mes' => 'integer',
        'ano' => 'integer',
        'contestacao_em' => 'datetime',
    ];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(GdpCiclo::class, 'ciclo_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(GdpPenalizacaoTipo::class, 'tipo_id');
    }

    public function julgadoPor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'contestacao_por');
    }

    public function scopeDoMes($query, int $mes, int $ano)
    {
        return $query->where('mes', $mes)->where('ano', $ano);
    }

    public function scopeDoUsuario($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeDoEixo($query, int $eixoId)
    {
        return $query->whereHas('tipo', fn($q) => $q->where('eixo_id', $eixoId));
    }

    public function scopeEfetivas($query)
    {
        return $query->where(function ($q) {
            $q->where('contestada', false)
              ->orWhere('contestacao_status', 'rejeitada');
        });
    }

    public function scopePendentesContestacao($query)
    {
        return $query->where('contestada', true)->where('contestacao_status', 'pendente');
    }

    public function isEfetiva(): bool
    {
        if (!$this->contestada) return true;
        return $this->contestacao_status === 'rejeitada';
    }

    public function getPontosEfetivos(): int
    {
        return $this->isEfetiva() ? $this->pontos_desconto : 0;
    }

    public function getBadgeClass(): string
    {
        return match ($this->tipo->gravidade ?? 'leve') {
            'grave' => 'bg-red-100 text-red-800',
            'moderada' => 'bg-yellow-100 text-yellow-800',
            'leve' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public static function totalDescontoEixo(int $userId, int $eixoId, int $mes, int $ano, int $cap = 30): int
    {
        $total = static::doUsuario($userId)
            ->doMes($mes, $ano)
            ->doEixo($eixoId)
            ->efetivas()
            ->sum('pontos_desconto');
        return min($total, $cap);
    }
}
