<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Nexo\NexoAutomationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NexoWebhookController extends Controller
{
    private NexoAutomationService $service;

    public function __construct(NexoAutomationService $service)
    {
        $this->service = $service;
    }

    public function identificarCliente(Request $request)
    {
        $this->validarWebhook($request);

        $telefone = $this->normalizarTelefone($request->input('telefone'));

        if (!$telefone) {
            return response()->json(['erro' => 'Telefone inválido'], 400);
        }

        $resultado = $this->service->identificarCliente($telefone);

        return response()->json($resultado);
    }

    public function perguntasAuth(Request $request)
    {
        $this->validarWebhook($request);

        $telefone = $this->normalizarTelefone($request->input('telefone'));

        if (!$telefone) {
            return response()->json(['erro' => 'Telefone inválido'], 400);
        }

        $resultado = $this->service->gerarPerguntasAuth($telefone);

        if (isset($resultado['erro'])) {
            return response()->json($resultado, 422);
        }

        // Salvar chaves de validação na sessão do webhook (via cache temporário)
        cache()->put(
            "nexo_auth_keys_{$telefone}",
            $resultado['chaves'],
            now()->addMinutes(15)
        );

        // Remover chaves antes de enviar resposta
        unset($resultado['chaves']);

        return response()->json($resultado);
    }

    public function validarAuth(Request $request)
    {
        $this->validarWebhook($request);

        $telefone = $this->normalizarTelefone($request->input('telefone'));
        $resposta1 = $request->input('resposta1');
        $resposta2 = $request->input('resposta2');

        if (!$telefone || !$resposta1 || !$resposta2) {
            return response()->json(['erro' => 'Dados incompletos'], 400);
        }

        // Recuperar chaves de validação
        $chaves = cache()->get("nexo_auth_keys_{$telefone}");

        if (!$chaves) {
            return response()->json([
                'erro' => 'Sessão de autenticação expirada. Reinicie o processo.'
            ], 422);
        }

        $resultado = $this->service->validarAuth($telefone, $resposta1, $resposta2, $chaves);

        // Limpar cache se autenticado
        if ($resultado['auth_ok']) {
            cache()->forget("nexo_auth_keys_{$telefone}");
        }

        return response()->json($resultado);
    }

    public function consultaStatus(Request $request)
    {
        $this->validarWebhook($request);

        $telefone = $this->normalizarTelefone($request->input('telefone'));

        if (!$telefone) {
            return response()->json(['erro' => 'Telefone inválido'], 400);
        }

        $resultado = $this->service->consultarStatusProcesso($telefone);

        return response()->json($resultado);
    }

    private function validarWebhook(Request $request): void
    {
        $token = $request->header('X-Sendpulse-Token');
        $expectedToken = config('services.sendpulse.webhook_token');

        if (!$token || $token !== $expectedToken) {
            Log::warning('Tentativa webhook não autorizada', [
                'ip' => $request->ip(),
                'token_recebido' => $token
            ]);

            abort(401, 'Não autorizado');
        }
    }

    private function normalizarTelefone(?string $telefone): ?string
    {
        if (!$telefone) {
            return null;
        }

        // Remove tudo exceto dígitos
        $numero = preg_replace('/\D/', '', $telefone);

        // Deve ter 12 ou 13 dígitos (com DDI 55)
        if (strlen($numero) < 10) {
            return null;
        }

        // Adiciona DDI 55 se não tiver
        if (!str_starts_with($numero, '55')) {
            $numero = '55' . $numero;
        }

        return $numero;
    }
}
