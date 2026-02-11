<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiricRelatorio extends Model
{
    protected $table = 'siric_relatorios';

    protected $fillable = [
        'consulta_id', 'markdown', 'pdf_path', 'gerado_por',
    ];

    public function consulta(): BelongsTo
    {
        return $this->belongsTo(SiricConsulta::class, 'consulta_id');
    }

    public function geradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'gerado_por');
    }
}
