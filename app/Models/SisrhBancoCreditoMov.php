<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SisrhBancoCreditoMov extends Model
{
    protected $table = 'sisrh_banco_creditos_movs';

    protected $fillable = [
        'user_id', 'ano', 'mes', 'tipo', 'valor',
        'origem_apuracao_id', 'motivo', 'created_by',
    ];

    protected $casts = [
        'valor' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function apuracao(): BelongsTo
    {
        return $this->belongsTo(SisrhApuracao::class, 'origem_apuracao_id');
    }

    /**
     * Calcula o saldo atual do banco de créditos de um usuário.
     */
    public static function saldo(int $userId): float
    {
        $credits = self::where('user_id', $userId)->where('tipo', 'credit')->sum('valor');
        $debits = self::where('user_id', $userId)->where('tipo', 'debit')->sum('valor');
        return round($credits - $debits, 2);
    }
}
