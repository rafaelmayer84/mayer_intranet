<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessTramitacao extends Model
{
    protected $table = 'crm_admin_process_tramitacoes';

    protected $fillable = [
        'admin_process_id','de_user_id','para_user_id','tipo','despacho','recebido_at',
    ];

    protected $casts = [
        'recebido_at' => 'datetime',
    ];

    public function process()
    {
        return $this->belongsTo(CrmAdminProcess::class, 'admin_process_id');
    }

    public function de()
    {
        return $this->belongsTo(\App\Models\User::class, 'de_user_id');
    }

    public function para()
    {
        return $this->belongsTo(\App\Models\User::class, 'para_user_id');
    }

    public function tipoLabel(): string
    {
        return match($this->tipo) {
            'tramitacao'     => 'Tramitação',
            'devolucao'      => 'Devolução',
            'encaminhamento' => 'Encaminhamento',
            default          => ucfirst($this->tipo),
        };
    }
}
