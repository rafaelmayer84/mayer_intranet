<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Modulo extends Model
{
    protected $fillable = [
        'slug', 'nome', 'grupo', 'descricao', 'rota', 'icone', 'ordem', 'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'ordem' => 'integer',
    ];

    public function permissions(): HasMany
    {
        return $this->hasMany(UserPermission::class);
    }

    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    public function scopeDoGrupo($query, string $grupo)
    {
        return $query->where('grupo', $grupo);
    }

    public function scopeOrdenado($query)
    {
        return $query->orderBy('grupo')->orderBy('ordem');
    }

    public static function porSlug(string $slug): ?self
    {
        return static::where('slug', $slug)->first();
    }

    public static function grupos(): array
    {
        return static::distinct()->pluck('grupo')->toArray();
    }

    /**
     * Retorna módulos agrupados por grupo (como Collection, não array)
     */
    public static function agrupadosPorGrupo(): Collection
    {
        return static::ativos()
            ->ordenado()
            ->get()
            ->groupBy('grupo');
    }
}
