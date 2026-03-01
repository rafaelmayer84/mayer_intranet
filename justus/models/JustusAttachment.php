<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JustusAttachment extends Model
{
    protected $table = 'justus_attachments';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'original_name',
        'stored_path',
        'mime_type',
        'file_size',
        'total_pages',
        'processing_status',
        'processing_error',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(JustusConversation::class, 'conversation_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(JustusDocumentPage::class, 'attachment_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(JustusDocumentChunk::class, 'attachment_id');
    }

    public function getFileSizeHumanAttribute(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 0) . ' KB';
        }
        return $bytes . ' B';
    }

    public function isReady(): bool
    {
        return $this->processing_status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->processing_status === 'failed';
    }

    public function isProcessing(): bool
    {
        return in_array($this->processing_status, ['pending', 'processing']);
    }
}
