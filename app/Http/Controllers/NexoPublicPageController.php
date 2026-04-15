<?php

namespace App\Http\Controllers;

use App\Models\NexoPublicToken;
use App\Models\Crm\CrmAdminProcess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NexoPublicPageController extends Controller
{
    private const ANTI_SCRAPING_LIMIT = 200;

    // URL do WhatsApp do escritório — configurar em services.nexo.whatsapp
    private string $whatsappUrl;

    public function __construct()
    {
        $phone = config('services.nexo.whatsapp', config('services.sendpulse.from_phone', '5548'));
        $this->whatsappUrl = 'https://wa.me/' . preg_replace('/\D/', '', $phone);
    }

    public function show(Request $request, string $token)
    {
        // Headers de segurança
        $headers = [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma'        => 'no-cache',
            'X-Robots-Tag'  => 'noindex, nofollow',
        ];

        // Buscar token — token inexistente = mesma view de expirado (sem information leak)
        $publicToken = NexoPublicToken::where('token', $token)->first();

        if (!$publicToken || $publicToken->isExpired()) {
            return response()
                ->view('public.expired', ['whatsappUrl' => $this->whatsappUrl])
                ->withHeaders($headers);
        }

        // Anti-scraping
        if ($publicToken->access_count >= self::ANTI_SCRAPING_LIMIT) {
            Log::warning('NexoPublicPage: access_count >= 200, tratando como expirado', [
                'token'        => $token,
                'access_count' => $publicToken->access_count,
            ]);
            return response()
                ->view('public.expired', ['whatsappUrl' => $this->whatsappUrl])
                ->withHeaders($headers);
        }

        $publicToken->recordAccess();

        $tipo    = $publicToken->tipo;
        $payload = $publicToken->payload;

        // Processo Administrativo: consulta LIVE
        if ($tipo === 'processo-admin') {
            return $this->showProcessoAdmin($publicToken, $payload, $headers);
        }

        // Demais tipos: snapshot do payload
        $viewData = array_merge($payload, [
            'whatsappUrl' => $this->whatsappUrl,
            'consultadoEm' => $publicToken->created_at->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i'),
        ]);

        return response()
            ->view("public.{$tipo}", $viewData)
            ->withHeaders($headers);
    }

    private function showProcessoAdmin(NexoPublicToken $publicToken, array $payload, array $headers)
    {
        $processoId = $payload['processo_id'] ?? null;

        if (!$processoId) {
            return response()
                ->view('public.expired', ['whatsappUrl' => $this->whatsappUrl])
                ->withHeaders($headers);
        }

        $processo = CrmAdminProcess::with([
            'steps'    => fn($q) => $q->where('is_client_visible', true)->orderBy('order'),
            'timeline' => fn($q) => $q->where('is_client_visible', true)->orderByDesc('happened_at')->limit(20),
            'checklist',
        ])->find($processoId);

        if (!$processo) {
            return response()
                ->view('public.expired', ['whatsappUrl' => $this->whatsappUrl])
                ->withHeaders($headers);
        }

        return response()->view('public.processo-admin', [
            'processo'    => $processo,
            'whatsappUrl' => $this->whatsappUrl,
            'consultadoEm' => now('America/Sao_Paulo')->format('d/m/Y H:i'),
        ])->withHeaders($headers);
    }
}
