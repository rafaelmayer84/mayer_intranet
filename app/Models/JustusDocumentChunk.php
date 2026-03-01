<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustusDocumentChunk extends Model
{
    protected $table = 'justus_document_chunks';

    protected $fillable = [
        'attachment_id',
        'chunk_index',
        'page_start',
        'page_end',
        'content',
        'token_estimate',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(JustusAttachment::class, 'attachment_id');
    }

    public function getPageRangeAttribute(): string
    {
        if ($this->page_start === $this->page_end) {
            return 'p. ' . $this->page_start;
        }
        return 'p. ' . $this->page_start . '–' . $this->page_end;
    }
}
