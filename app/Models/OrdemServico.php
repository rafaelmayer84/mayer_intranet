<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdemServico extends Model
{
    use HasFactory;

    protected $table = 'ordens_servico';

    protected $fillable = [
        'datajuri_id',
        'numero',
        'situacao',
        'data_conclusao',
        'data_ultimo_andamento',
        'advogado_nome',
        'advogado_id',
    ];

    protected $casts = [
        'data_conclusao' => 'date',
        'data_ultimo_andamento' => 'date',
    ];

    // Scopes
    public function scopePorSituacao($query, string $situacao)
    {
        return $query->where('situacao', $situacao);
    }

    public function scopeEmMovimento($query)
    {
        return $query->where('situacao', 'Movimento');
    }

    public function scopeConcluidas($query)
    {
        return $query->where('situacao', 'Concluído');
    }

    public function scopePorAdvogado($query, int $advogadoId)
    {
        return $query->where('advogado_id', $advogadoId);
    }
}
