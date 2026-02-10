<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WaTag extends Model
{
    protected $table = 'wa_tags';

    protected $fillable = [
        'provider_id',
        'name',
        'color',
        'contact_count',
    ];

    public function conversations()
    {
        return $this->belongsToMany(WaConversation::class, 'wa_conversation_tag', 'tag_id', 'conversation_id')
                    ->withTimestamps();
    }
}
