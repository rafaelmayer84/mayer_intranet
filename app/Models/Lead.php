<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $table = 'leads';

    protected $fillable = [
        'nome',
        'email',
        'telefone',
        'origem',
        'cidade',
        'status',
        'motivo_perda',
        'responsavel_id',
        'espocrm_id',
        'data_criacao_lead',
        'data_conversao',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'data_criacao_lead' => 'date',
        'data_conversao' => 'date'
    ];

    public function responsavel()
    {
        return $this->belongsTo(\App\Models\User::class, 'responsavel_id');
    }

    public function oportunidade()
    {
        return $this->hasOne(Oportunidade::class);
    }

    // Scopes
    public function scopeNovos($query)
    {
        return $query->where('status', 'novo');
    }

    public function scopeConvertidos($query)
    {
        return $query->where('status', 'convertido');
    }

    public function scopePerdidos($query)
    {
        return $query->where('status', 'perdido');
    }

    public function scopePorOrigem($query, $origem)
    {
        return $query->where('origem', $origem);
    }

    // Accessors
    public function getEstadoAttribute()
    {
        return match($this->status) {
            'novo' => 'Novo',
            'contactado' => 'Em Contato',
            'qualificado' => 'Qualificado',
            'convertido' => 'Convertido',
            'perdido' => 'Perdido',
            default => $this->status
        };
    }
}
