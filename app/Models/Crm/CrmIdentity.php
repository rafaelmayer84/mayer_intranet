<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmIdentity extends Model
{
    protected $table = 'crm_identities';

    protected $fillable = [
        'account_id', 'kind', 'value', 'value_norm',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
