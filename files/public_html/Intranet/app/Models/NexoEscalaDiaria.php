<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexoEscalaDiaria extends Model
{
    protected $table = 'nexo_escala_diaria';

    protected $fillable = [
        'data',
        'user_id',
        'inicio',
        'fim',
        'observacao',
    ];

    protected $casts = [
        'data'   => 'date',
        'inicio' => 'string',
        'fim'    => 'string',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
