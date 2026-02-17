<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Eval180ActionItem extends Model
{
    protected $table = 'gdp_eval180_action_items';

    protected $fillable = [
        'form_id', 'owner_user_id', 'title', 'due_date', 'status', 'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(Eval180Form::class, 'form_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}
