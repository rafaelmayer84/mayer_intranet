<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvisoLido extends Model
{
    public $timestamps = false;

    protected $table = 'avisos_lidos';

    protected $fillable = [
        'aviso_id',
        'usuario_id',
        'lido_em',
    ];

    protected $casts = [
        'lido_em' => 'datetime',
    ];

    public function aviso(): BelongsTo
    {
        return $this->belongsTo(Aviso::class, 'aviso_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
