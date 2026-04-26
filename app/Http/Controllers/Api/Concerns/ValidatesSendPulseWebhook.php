<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

trait ValidatesSendPulseWebhook
{
    protected array $sendpulseIpv4Prefixes = [
        '185.23.85.', '185.23.86.', '185.23.87.',
        '91.229.95.', '178.32.', '188.40.', '46.4.',
    ];

    protected array $sendpulseIpv6Prefixes = [
        '2a02:4780:',
    ];

    protected function validarWebhookFlexivel(Request $request): bool
    {
        // Aceita token via header OU via body (para testes do builder SendPulse)
        $token         = $request->header('X-Sendpulse-Token') ?? $request->input('_token_auth');
        $expectedToken = config('services.sendpulse.webhook_token');

        if ($token && $token === $expectedToken) {
            return true;
        }

        // Fallback: aceitar requests de IPs SendPulse sem token (builder de teste)
        $ip = $request->ip();

        foreach ($this->sendpulseIpv4Prefixes as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                Log::info(static::class . ': acesso via IP SendPulse (v4)', ['ip' => $ip]);
                return true;
            }
        }

        foreach ($this->sendpulseIpv6Prefixes as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                Log::info(static::class . ': acesso via IP SendPulse (v6)', ['ip' => $ip]);
                return true;
            }
        }

        Log::warning(static::class . ': webhook flexivel não autorizado', [
            'ip'             => $ip,
            'token_recebido' => $token,
        ]);
        return false;
    }
}
