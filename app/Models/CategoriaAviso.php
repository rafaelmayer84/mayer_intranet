<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaAviso extends Model
{
    use HasFactory;

    protected $table = 'categorias_avisos';

    protected $fillable = [
        'nome',
        'descricao',
        'cor_hexadecimal',
        'icone',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function avisos(): HasMany
    {
        return $this->hasMany(Aviso::class, 'categoria_id');
    }

    public function scopeAtivas($query)
    {
        return $query->where('ativo', true);
    }
}
