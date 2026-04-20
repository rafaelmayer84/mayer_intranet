<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmInadimplenciaDecisao extends Model
{
    protected $table = 'crm_inadimplencia_decisoes';

    protected $fillable = [
        'account_id', 'decisao', 'justificativa', 'prazo_revisao',
        'created_by_user_id', 'status', 'oportunidade_id', 'sinistro_notas',
    ];

    protected $casts = [
        'prazo_revisao' => 'date',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function oportunidade(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'oportunidade_id');
    }

    public function scopeAtiva($query)
    {
        return $query->where('status', 'ativa');
    }

    public function isAguardar(): bool
    {
        return $this->decisao === 'aguardar' && $this->status === 'ativa';
    }
}
