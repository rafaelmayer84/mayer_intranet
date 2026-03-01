<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustusDocumentPage extends Model
{
    protected $table = 'justus_document_pages';

    protected $fillable = [
        'attachment_id',
        'page_number',
        'text_content',
        'char_count',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(JustusAttachment::class, 'attachment_id');
    }
}
