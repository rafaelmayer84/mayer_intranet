<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Eval180Response extends Model
{
    protected $table = 'gdp_eval180_responses';

    protected $fillable = [
        'form_id', 'rater_type', 'rater_user_id',
        'answers_json', 'section_scores_json', 'total_score',
        'comment_text', 'evidence_text', 'submitted_at',
    ];

    protected $casts = [
        'answers_json'        => 'array',
        'section_scores_json' => 'array',
        'total_score'         => 'float',
        'submitted_at'        => 'datetime',
    ];

    // ── Relacionamentos ──

    public function form(): BelongsTo
    {
        return $this->belongsTo(Eval180Form::class, 'form_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }

    // ── Helpers ──

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isSelf(): bool
    {
        return $this->rater_type === 'self';
    }

    public function isManager(): bool
    {
        return $this->rater_type === 'manager';
    }
}
