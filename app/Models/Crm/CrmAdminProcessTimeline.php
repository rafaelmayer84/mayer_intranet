<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessTimeline extends Model
{
    protected $table = 'crm_admin_process_timeline';

    protected $fillable = [
        'admin_process_id','step_id','user_id','tipo','titulo','corpo',
        'is_client_visible','is_internal','metadata','happened_at',
    ];

    protected $casts = [
        'is_client_visible' => 'boolean',
        'is_internal'       => 'boolean',
        'metadata'          => 'array',
        'happened_at'       => 'datetime',
    ];

    public function process()
    {
        return $this->belongsTo(CrmAdminProcess::class, 'admin_process_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function step()
    {
        return $this->belongsTo(CrmAdminProcessStep::class, 'step_id');
    }

    public function tipoIcon(): string
    {
        return match($this->tipo) {
            'criado'            => '📁',
            'etapa_iniciada'    => '▶️',
            'etapa_concluida'   => '✅',
            'etapa_atrasada'    => '⚠️',
            'documento_adicionado' => '📎',
            'andamento_manual'  => '📝',
            'status_alterado'   => '🔄',
            'suspenso'          => '⏸️',
            'retomado'          => '▶️',
            'concluido'         => '🏁',
            'cancelado'         => '❌',
            'comunicacao_enviada' => '📱',
            default             => '•',
        };
    }

    public function tipoColor(): string
    {
        return match($this->tipo) {
            'criado'            => 'border-blue-300 bg-blue-50',
            'etapa_concluida'   => 'border-green-300 bg-green-50',
            'etapa_atrasada'    => 'border-red-300 bg-red-50',
            'concluido'         => 'border-green-400 bg-green-100',
            'suspenso'          => 'border-yellow-300 bg-yellow-50',
            'cancelado'         => 'border-gray-300 bg-gray-50',
            'andamento_manual'  => 'border-indigo-200 bg-indigo-50',
            default             => 'border-gray-200 bg-white',
        };
    }
}
