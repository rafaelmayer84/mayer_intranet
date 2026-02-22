<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexoQaResponseIdentity extends Model
{
    public $timestamps = false;

    protected $table = 'nexo_qa_responses_identity';

    protected $fillable = [
        'target_id',
        'phone_hash',
        'answered_at',
        'opted_out',
        'created_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'opted_out' => 'boolean',
        'created_at' => 'datetime',
    ];

    /* ───── Relacionamentos ───── */

    public function target(): BelongsTo
    {
        return $this->belongsTo(NexoQaSampledTarget::class, 'target_id');
    }
}
