<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermission extends Model
{
    protected $fillable = [
        'user_id', 'modulo_id', 'pode_visualizar', 'pode_editar',
        'pode_criar', 'pode_excluir', 'pode_executar', 'escopo'
    ];

    protected $casts = [
        'pode_visualizar' => 'boolean',
        'pode_editar' => 'boolean',
        'pode_criar' => 'boolean',
        'pode_excluir' => 'boolean',
        'pode_executar' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function modulo(): BelongsTo
    {
        return $this->belongsTo(Modulo::class);
    }

    public function temAlgumaPermissao(): bool
    {
        return $this->pode_visualizar || $this->pode_editar || 
               $this->pode_criar || $this->pode_excluir || $this->pode_executar;
    }
}
