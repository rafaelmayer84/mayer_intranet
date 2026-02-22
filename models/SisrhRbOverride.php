<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SisrhRbOverride extends Model
{
    protected $table = 'sisrh_rb_overrides';

    protected $fillable = [
        'user_id', 'ciclo_id', 'valor_rb', 'motivo', 'created_by',
    ];

    protected $casts = [
        'valor_rb' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(\App\Models\GdpCiclo::class, 'ciclo_id');
    }
}
