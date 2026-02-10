<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ManualGrupo extends Model
{
    protected $table = 'manuais_grupos';

    protected $fillable = [
        'nome',
        'slug',
        'ordem',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function documentos(): HasMany
    {
        return $this->hasMany(ManualDocumento::class, 'grupo_id');
    }

    public function documentosAtivos(): HasMany
    {
        return $this->hasMany(ManualDocumento::class, 'grupo_id')
            ->where('ativo', true)
            ->orderBy('ordem')
            ->orderBy('titulo');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'manuais_grupo_user', 'grupo_id', 'user_id')
            ->withTimestamps();
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeOrdenados($query)
    {
        return $query->orderBy('ordem')->orderBy('nome');
    }
}
