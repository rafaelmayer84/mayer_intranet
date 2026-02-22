<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NexoQaCampaign extends Model
{
    protected $table = 'nexo_qa_campaigns';

    protected $fillable = [
        'name',
        'status',
        'sample_size',
        'lookback_days',
        'cooldown_days',
        'channels',
        'survey_questions',
        'created_by_user_id',
    ];

    protected $casts = [
        'channels' => 'array',
        'survey_questions' => 'array',
        'sample_size' => 'integer',
        'lookback_days' => 'integer',
        'cooldown_days' => 'integer',
    ];

    /* ───── Relacionamentos ───── */

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by_user_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(NexoQaSampledTarget::class, 'campaign_id');
    }

    /* ───── Scopes ───── */

    public function scopeActive($query)
    {
        return $query->where('status', 'ACTIVE');
    }

    /* ───── Helpers ───── */

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    /**
     * Retorna perguntas padrão caso survey_questions esteja vazio.
     */
    public function getEffectiveQuestions(): array
    {
        if (!empty($this->survey_questions)) {
            return $this->survey_questions;
        }

        return [
            [
                'key' => 'score_1_5',
                'text' => 'De 1 a 5, como você avalia o atendimento que recebeu do nosso escritório?',
                'type' => 'scale',
                'min' => 1,
                'max' => 5,
            ],
            [
                'key' => 'nps',
                'text' => 'De 0 a 10, qual a probabilidade de recomendar nosso escritório a um amigo ou familiar?',
                'type' => 'scale',
                'min' => 0,
                'max' => 10,
            ],
        ];
    }
}
