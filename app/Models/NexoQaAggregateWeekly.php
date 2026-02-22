<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexoQaAggregateWeekly extends Model
{
    public $timestamps = false;

    protected $table = 'nexo_qa_aggregates_weekly';

    protected $fillable = [
        'week_start',
        'week_end',
        'responsible_user_id',
        'responses_count',
        'avg_score',
        'nps_score',
        'detractors',
        'passives',
        'promoters',
        'targets_sent',
        'created_at',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'avg_score' => 'decimal:2',
        'nps_score' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /* ───── Relacionamentos ───── */

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_user_id');
    }

    /* ───── Helpers ───── */

    public function getResponseRateAttribute(): float
    {
        if ($this->targets_sent === 0) {
            return 0.0;
        }
        return round(($this->responses_count / $this->targets_sent) * 100, 2);
    }
}
