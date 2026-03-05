<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmAiInsight extends Model
{
    protected $table = 'crm_ai_insights';

    protected $fillable = [
        'account_id',
        'tipo',
        'titulo',
        'insight_text',
        'action_suggested',
        'priority',
        'status',
        'generated_by_user_id',
        'context_snapshot',
    ];

    protected $casts = [
        'context_snapshot' => 'array',
    ];

    public function account()
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by_user_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWeeklyDigest($query)
    {
        return $query->where('tipo', 'weekly_digest');
    }

    public function scopeAccountAction($query)
    {
        return $query->where('tipo', 'account_action');
    }
}
