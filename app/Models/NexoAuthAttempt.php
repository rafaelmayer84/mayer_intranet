<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexoAuthAttempt extends Model
{
    protected $table = 'nexo_auth_attempts';

    protected $fillable = [
        'telefone',
        'tentativas',
        'bloqueado',
        'bloqueado_ate',
        'ultimo_tentativa',
    ];

    protected $casts = [
        'bloqueado' => 'boolean',
        'bloqueado_ate' => 'datetime',
        'ultimo_tentativa' => 'datetime',
    ];

    /**
     * Verifica se o telefone está bloqueado (30 min)
     */
    public function estaBloqueado(): bool
    {
        if (!$this->bloqueado) {
            return false;
        }

        // Desbloqueia automaticamente após 30 minutos
        if ($this->bloqueado_ate && $this->bloqueado_ate->isPast()) {
            $this->update([
                'bloqueado' => false,
                'tentativas' => 0,
                'bloqueado_ate' => null,
            ]);
            return false;
        }

        return true;
    }
}
