<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaseProcesso extends Model
{
    use HasFactory;

    protected $table = 'fases_processo';

    protected $fillable = [
        'datajuri_id',
        'processo_pasta',
        'processo_id_datajuri',
        'tipo_fase',
        'localidade',
        'instancia',
        'data',
        'fase_atual',
        'dias_fase_ativa',
        'data_ultimo_andamento',
        'proprietario_nome',
        'proprietario_id',
    ];

    protected $casts = [
        'data' => 'date',
        'data_ultimo_andamento' => 'date',
        'fase_atual' => 'boolean',
        'dias_fase_ativa' => 'integer',
    ];

    // Relacionamento com Processo
    public function processo()
    {
        return $this->belongsTo(Processo::class, 'processo_id_datajuri', 'datajuri_id');
    }

    // Scopes
    public function scopeFaseAtual($query)
    {
        return $query->where('fase_atual', true);
    }

    public function scopePorInstancia($query, string $instancia)
    {
        return $query->where('instancia', $instancia);
    }

    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_fase', $tipo);
    }
}
