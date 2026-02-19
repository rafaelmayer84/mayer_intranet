<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Eval180Form extends Model
{
    protected $table = 'gdp_eval180_forms';

    protected $fillable = [
        'cycle_id', 'user_id', 'period', 'status', 'created_by',
    ];

    // ── Relacionamentos ──

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(GdpCiclo::class, 'cycle_id');
    }

    public function avaliado(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function criador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Eval180Response::class, 'form_id');
    }

    public function selfResponse(): HasOne
    {
        return $this->hasOne(Eval180Response::class, 'form_id')
            ->where('rater_type', 'self');
    }

    public function managerResponse(): HasOne
    {
        return $this->hasOne(Eval180Response::class, 'form_id')
            ->where('rater_type', 'manager');
    }

    public function actionItems(): HasMany
    {
        return $this->hasMany(Eval180ActionItem::class, 'form_id');
    }

    // ── Helpers ──

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }

    public function isPendingSelf(): bool
    {
        return $this->status === 'pending_self';
    }

    public function isPendingManager(): bool
    {
        return $this->status === 'pending_manager';
    }

    public function isPendingFeedback(): bool
    {
        return $this->status === 'pending_feedback';
    }

    public function isReleased(): bool
    {
        return $this->status === 'released';
    }

    public function canAvaliadorSeeManagerNotes(): bool
    {
        return in_array($this->status, ['released', 'locked']);
    }

    public function hasSelfResponse(): bool
    {
        return $this->responses()->where('rater_type', 'self')->whereNotNull('submitted_at')->exists();
    }

    public function hasManagerResponse(): bool
    {
        return $this->responses()->where('rater_type', 'manager')->whereNotNull('submitted_at')->exists();
    }
}
