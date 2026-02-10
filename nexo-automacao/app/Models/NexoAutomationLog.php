<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexoAutomationLog extends Model
{
    protected $table = 'nexo_automation_logs';

    protected $fillable = [
        'telefone',
        'acao',
        'dados',
        'resposta_ia',
        'tempo_resposta_ms',
        'erro'
    ];

    protected $casts = [
        'dados' => 'array',
        'tempo_resposta_ms' => 'integer'
    ];

    public function cliente()
    {
        return $this->belongsTo(NexoClienteValidacao::class, 'telefone', 'telefone');
    }

    public function scopeHoje($query)
    {
        return $query->whereDate('created_at', today());
    }

    public function scopeSucesso($query)
    {
        return $query->where('acao', 'auth_sucesso');
    }

    public function scopeFalha($query)
    {
        return $query->where('acao', 'auth_falha');
    }

    public function scopeRecentes($query, int $limit = 10)
    {
        return $query->orderBy('created_at', 'desc')->limit($limit);
    }
}
