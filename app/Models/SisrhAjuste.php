<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SisrhAjuste extends Model
{
    protected $table = 'sisrh_ajustes';

    protected $fillable = [
        'apuracao_id', 'tipo', 'valor', 'motivo', 'created_by',
    ];

    protected $casts = [
        'valor' => 'float',
    ];

    public function apuracao(): BelongsTo
    {
        return $this->belongsTo(SisrhApuracao::class, 'apuracao_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
