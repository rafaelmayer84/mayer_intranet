<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemErrorLog extends Model
{
    public $timestamps = false;

    protected $table = 'system_error_logs';

    protected $fillable = [
        'level', 'message', 'module', 'context_json', 'trace',
        'file', 'line', 'url', 'user_id', 'user_name', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'context_json' => 'array',
        'created_at'   => 'datetime',
    ];

    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }
}
