<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPulsoDiario extends Model
{
    protected $table = 'crm_pulso_diario';

    protected $fillable = [
        'account_id', 'data', 'wa_msgs_incoming', 'wa_conversations_opened',
        'tickets_abertos', 'crm_interactions', 'phone_calls', 'total_contatos',
        'has_movimentacao', 'threshold_exceeded',
    ];

    protected $casts = [
        'data'               => 'date',
        'has_movimentacao'   => 'boolean',
        'threshold_exceeded' => 'boolean',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
