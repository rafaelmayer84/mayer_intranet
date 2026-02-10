<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    use HasFactory;

    protected $table = 'contratos';

    protected $fillable = [
        'datajuri_id',
        'numero',
        'valor',
        'data_assinatura',
        'contratante_nome',
        'contratante_id_datajuri',
        'proprietario_nome',
        'proprietario_id',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'data_assinatura' => 'date',
    ];

    // Relacionamento com Cliente/Contratante
    public function contratante()
    {
        return $this->belongsTo(Cliente::class, 'contratante_id_datajuri', 'datajuri_id');
    }

    // Scopes
    public function scopePorProprietario($query, int $proprietarioId)
    {
        return $query->where('proprietario_id', $proprietarioId);
    }

    public function scopeNoMes($query, int $ano, int $mes)
    {
        return $query->whereYear('data_assinatura', $ano)
                     ->whereMonth('data_assinatura', $mes);
    }

    public function scopeNoAno($query, int $ano)
    {
        return $query->whereYear('data_assinatura', $ano);
    }
}
