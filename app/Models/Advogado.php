<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advogado extends Model
{
    protected $fillable = [
        'datajuri_id',
        'nome',
        'email',
        'valor_hora',
        'custo_fixo_mensal',
        'ativo',
        'user_id',
    ];

    protected $casts = [
        'valor_hora' => 'decimal:2',
        'custo_fixo_mensal' => 'decimal:2',
        'ativo' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function metas(): HasMany
    {
        return $this->hasMany(Meta::class);
    }
}
