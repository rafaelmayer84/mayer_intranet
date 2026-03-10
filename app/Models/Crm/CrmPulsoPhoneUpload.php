<?php

namespace App\Models\Crm;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmPulsoPhoneUpload extends Model
{
    protected $table = 'crm_pulso_phone_uploads';

    protected $fillable = [
        'user_id', 'filename', 'registros_processados',
        'registros_ignorados', 'periodo_inicio', 'periodo_fim',
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fim'    => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
