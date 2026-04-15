<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessStep extends Model
{
    protected $table = 'crm_admin_process_steps';

    protected $fillable = [
        'admin_process_id','order','titulo','descricao','tipo','orgao','status',
        'responsible_user_id','deadline_days','deadline_at','scheduled_return_at',
        'started_at','completed_at','depends_on_step_id','is_client_visible',
        'requires_document','notes',
    ];

    protected $casts = [
        'deadline_at'          => 'date',
        'scheduled_return_at'  => 'datetime',
        'started_at'           => 'datetime',
        'completed_at'         => 'datetime',
        'is_client_visible'    => 'boolean',
        'requires_document'    => 'boolean',
    ];

    public function process()
    {
        return $this->belongsTo(CrmAdminProcess::class, 'admin_process_id');
    }

    public function responsible()
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_user_id');
    }

    public function dependsOn()
    {
        return $this->belongsTo(CrmAdminProcessStep::class, 'depends_on_step_id');
    }

    public function documents()
    {
        return $this->hasMany(CrmAdminProcessDocument::class, 'step_id');
    }

    public function isAtrasada(): bool
    {
        return $this->deadline_at
            && $this->deadline_at->isPast()
            && !in_array($this->status, ['concluido','nao_aplicavel']);
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'pendente'       => 'Pendente',
            'em_andamento'   => 'Em andamento',
            'aguardando'     => 'Aguardando',
            'concluido'      => 'Concluído',
            'nao_aplicavel'  => 'N/A',
            'bloqueado'      => 'Bloqueado',
            default          => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'pendente'      => 'bg-gray-100 text-gray-500',
            'em_andamento'  => 'bg-blue-100 text-blue-700',
            'aguardando'    => 'bg-yellow-100 text-yellow-700',
            'concluido'     => 'bg-green-100 text-green-700',
            'nao_aplicavel' => 'bg-gray-100 text-gray-400',
            'bloqueado'     => 'bg-red-100 text-red-600',
            default         => 'bg-gray-100 text-gray-500',
        };
    }

    public function tipoIcon(): string
    {
        return match($this->tipo) {
            'interno'   => '🏠',
            'externo'   => '🏛️',
            'cliente'   => '👤',
            'aprovacao' => '✅',
            default     => '•',
        };
    }

    public function tipoLabel(): string
    {
        return match($this->tipo) {
            'interno'   => 'Interno',
            'externo'   => 'Externo',
            'cliente'   => 'Cliente',
            'aprovacao' => 'Aprovação',
            default     => ucfirst($this->tipo),
        };
    }
}
