<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ValidatesSendPulseWebhook;
use App\Http\Controllers\Controller;
use App\Services\Nexo\LexusOrchestratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LexusController extends Controller
{
    use ValidatesSendPulseWebhook;

    public function processar(Request $request): JsonResponse
    {
        if (!$this->validarWebhookFlexivel($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        $validated = $request->validate([
            'phone'           => 'required|string',
            'conversation_id' => 'required|string',
            'mensagem'        => 'nullable|string|max:4000',
            'nome_whatsapp'   => 'nullable|string|max:150',
        ]);

        Log::warning('LEXUS-V3 entrada', [
            'phone'           => $validated['phone'],
            'conversation_id' => $validated['conversation_id'],
            'mensagem_len'    => strlen($validated['mensagem'] ?? ''),
        ]);

        try {
            $resposta = (new LexusOrchestratorService())->processar($validated);
            return response()->json($resposta);

        } catch (\Exception $e) {
            Log::error('LEXUS-V3 erro controller', [
                'erro'  => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'acao'           => 'erro',
                'mensagem_lexus' => 'Tive um problema técnico. Vou pedir para um atendente da equipe falar com você em instantes. 🙏',
                'etapa_atual'    => 'erro',
                'lead_id'        => null,
                'sessao_id'      => null,
            ]);
        }
    }
}
