<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AtividadeDatajuri extends Model
{
    use HasFactory;

    protected $table = 'atividades_datajuri';

    protected $fillable = [
        'datajuri_id',
        'status',
        'data_hora',
        'data_conclusao',
        'data_prazo_fatal',
        'processo_pasta',
        'proprietario_id',
        'particular',
    ];

    protected $casts = [
        'data_hora' => 'datetime',
        'data_conclusao' => 'datetime',
        'data_prazo_fatal' => 'date',
        'particular' => 'boolean',
    ];

    // Scopes
    public function scopePorStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePendentes($query)
    {
        return $query->whereNull('data_conclusao')
                     ->where('status', '!=', 'Concluído');
    }

    public function scopeAtrasadas($query)
    {
        return $query->whereNull('data_conclusao')
                     ->where('data_prazo_fatal', '<', now());
    }

    public function scopePorProprietario($query, int $proprietarioId)
    {
        return $query->where('proprietario_id', $proprietarioId);
    }

    public function scopePublicas($query)
    {
        return $query->where('particular', false);
    }
}
