<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPulsoAlerta extends Model
{
    protected $table = 'crm_pulso_alertas';

    protected $fillable = [
        'account_id', 'tipo', 'descricao', 'dados_json', 'status',
        'notificado_em', 'resolvido_por', 'resolvido_em',
    ];

    protected $casts = [
        'dados_json'    => 'array',
        'notificado_em' => 'datetime',
        'resolvido_em'  => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function resolvidoPorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolvido_por');
    }

    public function scopePendentes($query)
    {
        return $query->where('status', 'pendente');
    }
}
