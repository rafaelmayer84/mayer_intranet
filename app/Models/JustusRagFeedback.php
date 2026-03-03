<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JustusRagFeedback extends Model
{
    protected $table = 'justus_rag_feedback';

    protected $fillable = [
        'conversation_id',
        'message_id',
        'chunk_id',
        'query_embedding',
        'feedback',
        'score_adjustment',
    ];

    public function conversation()
    {
        return $this->belongsTo(JustusConversation::class);
    }

    public function message()
    {
        return $this->belongsTo(JustusMessage::class);
    }

    public function chunk()
    {
        return $this->belongsTo(JustusDocumentChunk::class);
    }

    /**
     * Calcula ajustes de score para chunks de um attachment baseado no historico de feedback.
     */
    public static function getAdjustmentsForAttachment(int $attachmentId): array
    {
        $feedbacks = self::whereHas('chunk', fn($q) => $q->where('attachment_id', $attachmentId))
            ->selectRaw('chunk_id, SUM(CASE WHEN feedback = "positive" THEN 0.05 ELSE -0.03 END) as adjustment')
            ->groupBy('chunk_id')
            ->pluck('adjustment', 'chunk_id')
            ->toArray();

        return $feedbacks;
    }
}
