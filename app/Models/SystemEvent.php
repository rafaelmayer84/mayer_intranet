<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SystemEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'category', 'severity', 'event_type', 'title', 'description',
        'metadata', 'related_model', 'related_id',
        'user_id', 'user_name', 'ip_address', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public static function register(
        string $category, string $eventType, string $severity, string $title,
        ?string $description = null, ?array $metadata = null,
        ?string $relatedModel = null, ?int $relatedId = null
    ): ?self {
        try {
            $user = Auth::user();
            return self::create([
                'category'      => $category,
                'severity'      => $severity,
                'event_type'    => $eventType,
                'title'         => $title,
                'description'   => $description,
                'metadata'      => $metadata,
                'related_model' => $relatedModel,
                'related_id'    => $relatedId,
                'user_id'       => $user?->id,
                'user_name'     => $user?->name,
                'ip_address'    => request()?->ip(),
                'created_at'    => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('SystemEvent::register falhou: ' . $e->getMessage());
            return null;
        }
    }

    public static function gdp(string $eventType, string $severity, string $title, ?string $description = null, ?array $metadata = null, ?string $relatedModel = null, ?int $relatedId = null): ?self
    {
        return self::register('gdp', $eventType, $severity, $title, $description, $metadata, $relatedModel, $relatedId);
    }

    public static function financeiro(string $eventType, string $severity, string $title, ?string $description = null, ?array $metadata = null, ?string $relatedModel = null, ?int $relatedId = null): ?self
    {
        return self::register('financeiro', $eventType, $severity, $title, $description, $metadata, $relatedModel, $relatedId);
    }

    public static function crm(string $eventType, string $severity, string $title, ?string $description = null, ?array $metadata = null, ?string $relatedModel = null, ?int $relatedId = null): ?self
    {
        return self::register('crm', $eventType, $severity, $title, $description, $metadata, $relatedModel, $relatedId);
    }

    public static function sistema(string $eventType, string $severity, string $title, ?string $description = null, ?array $metadata = null, ?string $relatedModel = null, ?int $relatedId = null): ?self
    {
        return self::register('sistema', $eventType, $severity, $title, $description, $metadata, $relatedModel, $relatedId);
    }

    public function scopeCategory($query, string $category) { return $query->where('category', $category); }
    public function scopeSeverity($query, string $severity) { return $query->where('severity', $severity); }
    public function scopeEventType($query, string $eventType) { return $query->where('event_type', $eventType); }
    public function scopeOlderThan($query, int $days) { return $query->where('created_at', '<', now()->subDays($days)); }
    public function scopeToday($query) { return $query->whereDate('created_at', today()); }
    public function scopeLastDays($query, int $days) { return $query->where('created_at', '>=', now()->subDays($days)); }

    public function user() { return $this->belongsTo(User::class); }

    public function getSeverityColorAttribute(): string
    {
        return match ($this->severity) { 'info' => 'blue', 'warning' => 'yellow', 'error' => 'red', 'critical' => 'purple', default => 'gray' };
    }

    public function getCategoryColorAttribute(): string
    {
        return match ($this->category) { 'gdp' => 'indigo', 'financeiro' => 'green', 'crm' => 'orange', 'sistema' => 'gray', default => 'gray' };
    }

    public function getCategoryLabelAttribute(): string
    {
        return match ($this->category) { 'gdp' => 'GDP', 'financeiro' => 'Financeiro', 'crm' => 'CRM', 'sistema' => 'Sistema', default => $this->category };
    }
}
