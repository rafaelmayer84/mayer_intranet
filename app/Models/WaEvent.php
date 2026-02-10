<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaEvent extends Model
{
    public $timestamps = false;

    protected $table = 'wa_events';

    protected $fillable = [
        'conversation_id',
        'type',
        'payload',
        'created_at',
    ];

    protected $casts = [
        'payload'    => 'array',
        'created_at' => 'datetime',
    ];

    // ─── Constantes de tipo ────────────────────────────────

    const TYPE_WEBHOOK_RECEIVED = 'webhook_received';
    const TYPE_SYNC_RUN         = 'sync_run';
    const TYPE_SEND_MESSAGE     = 'send_message';
    const TYPE_ERROR            = 'error';

    // ─── Relacionamentos ───────────────────────────────────

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    // ─── Factory helper ────────────────────────────────────

    /**
     * Registra um evento de forma segura (sem travar a operação principal).
     */
    public static function log(string $type, ?int $conversationId = null, array $payload = []): void
    {
        try {
            // Limitar tamanho do payload para não estourar o banco
            $safePayload = self::sanitizePayload($payload);

            self::create([
                'type'            => $type,
                'conversation_id' => $conversationId,
                'payload'         => $safePayload,
                'created_at'      => now(),
            ]);
        } catch (\Throwable $e) {
            // Nunca travar a operação principal por falha de log
            \Log::warning('WaEvent::log falhou', [
                'type'  => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Remove tokens e trunca payload para tamanho seguro.
     */
    private static function sanitizePayload(array $payload): array
    {
        // Remover campos sensíveis
        $sensitiveKeys = ['token', 'access_token', 'api_key', 'secret', 'password', 'authorization'];

        array_walk_recursive($payload, function (&$value, $key) use ($sensitiveKeys) {
            if (in_array(strtolower($key), $sensitiveKeys, true)) {
                $value = '***REDACTED***';
            }
        });

        // Truncar se JSON resultante > 10KB
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 10240) {
            return ['_truncated' => true, 'size' => strlen($json), 'keys' => array_keys($payload)];
        }

        return $payload;
    }
}
