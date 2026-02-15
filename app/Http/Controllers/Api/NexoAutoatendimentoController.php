<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Nexo\NexoAutoatendimentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NexoAutoatendimentoController extends Controller
{
    private NexoAutoatendimentoService $service;

    public function __construct(NexoAutoatendimentoService $service)
    {
        $this->service = $service;
    }

    // =====================================================
    // FINANCEIRO
    // =====================================================

    public function titulosAbertos(Request $request)
    {
        if (!$this->validarWebhook($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        $request->validate(['telefone' => 'required|string']);

        try {
            $resultado = $this->service->titulosAbertos($request->telefone);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Erro titulosAbertos', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno'], 500);
        }
    }

    public function segundaVia(Request $request)
    {
        if (!$this->validarWebhook($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        $request->validate(['telefone' => 'required|string']);

        try {
            $resultado = $this->service->segundaVia($request->telefone);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Erro segundaVia', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno'], 500);
        }
    }

    // =====================================================
    // COMPROMISSOS
    // =====================================================

    public function proximosCompromissos(Request $request)
    {
        if (!$this->validarWebhook($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        $request->validate(['telefone' => 'required|string']);

        try {
            $resultado = $this->service->proximosCompromissos($request->telefone);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Erro proximosCompromissos', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno'], 500);
        }
    }

    // =====================================================
    // TICKETS
    // =====================================================

    public function abrirTicket(Request $request)
    {
        if (!$this->validarWebhook($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        $request->validate([
            'telefone' => 'required|string',
            'assunto' => 'required|string|max:255',
            'mensagem' => 'nullable|string|max:2000',
        ]);

        try {
            $resultado = $this->service->abrirTicket(
                $request->telefone,
                $request->assunto,
                $request->mensagem
            );
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Erro abrirTicket', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno'], 500);
        }
    }

    public function listarTickets(Request $request)
    {
        if (!$this->validarWebhook($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        try {
            $resultado = $this->service->listarTickets(
                $request->telefone,
                $request->status,
                (int)($request->limit ?? 20)
            );
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Erro listarTickets', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno'], 500);
        }
    }

    // =====================================================
    // RESUMO LEIGO
    // =====================================================

    public function resumoLeigo(Request $request)
    {
        if (!$this->validarWebhook($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        $request->validate(['telefone' => 'required|string']);

        try {
            $resultado = $this->service->resumoLeigo(
                $request->telefone,
                $request->pasta
            );
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('Erro resumoLeigo', ['erro' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro interno'], 500);
        }
    }

    // =====================================================
    // VALIDAÇÃO (mesmo padrão do NexoWebhookController)
    // =====================================================

    private function validarWebhook(Request $request): bool
    {
        $token = $request->header('X-Sendpulse-Token');
        $expectedToken = config('services.sendpulse.webhook_token');

        if (!$token || $token !== $expectedToken) {
            Log::warning('NEXO-Autoatendimento: webhook não autorizado', [
                'ip' => $request->ip(),
                'token_recebido' => $token ? 'presente' : 'ausente',
            ]);
            return false;
        }

        return true;
    }
}
