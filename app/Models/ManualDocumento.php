<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualDocumento extends Model
{
    protected $table = 'manuais_documentos';

    protected $fillable = [
        'grupo_id',
        'titulo',
        'descricao',
        'url_onedrive',
        'data_publicacao',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
        'data_publicacao' => 'date',
    ];

    public function grupo(): BelongsTo
    {
        return $this->belongsTo(ManualGrupo::class, 'grupo_id');
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }
}
