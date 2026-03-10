<?php

namespace App\Http\Controllers;

use App\Services\NexoConsultaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NexoConsultaController extends Controller
{
    protected NexoConsultaService $service;

    public function __construct(NexoConsultaService $service)
    {
        $this->service = $service;
    }

    /**
     * Middleware de autenticação por token.
     * Valida X-Sendpulse-Token header.
     */
    private function validarToken(Request $request): bool
    {
        $token = $request->header('X-Sendpulse-Token');
        $esperado = config('services.nexo_consulta.token', env('NEXO_CONSULTA_TOKEN', 'token_secreto'));

        return $token === $esperado;
    }


    /**
     * POST /api/nexo/verificar-sessao
     */
    public function verificarSessao(Request $request): JsonResponse
    {
        if (!$this->validarToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telefone = $request->input('telefone', '');

        if (empty($telefone)) {
            return response()->json(['error' => 'Telefone obrigatório'], 400);
        }

        try {
            $resultado = $this->service->verificarSessao($telefone);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro verificar-sessao: ' . $e->getMessage());
            return response()->json(['sessao_ativa' => 'nao', 'nome' => ''], 200);
        }
    }

    /**
     * POST /api/nexo/identificar-cliente
     */
    public function identificarCliente(Request $request): JsonResponse
    {
        if (!$this->validarToken($request)) {
            Log::warning('[NEXO-CONSULTA] Token inválido em identificar-cliente');
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telefone = $request->input('telefone', '');
        $cpf = $request->input('cpf', '') ?: $request->input('documento', '');

        if (empty($telefone)) {
            return response()->json(['error' => 'Telefone obrigatório'], 400);
        }

        try {
            $resultado = $this->service->identificarCliente($telefone, $cpf ?: null);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro identificar-cliente: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    /**
     * POST /api/nexo/perguntas-auth
     */
    public function perguntasAuth(Request $request): JsonResponse
    {
        if (!$this->validarToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telefone = $request->input('telefone', '');

        if (empty($telefone)) {
            return response()->json(['error' => 'Telefone obrigatório'], 400);
        }

        try {
            $resultado = $this->service->gerarPerguntasAuth($telefone);

            if (isset($resultado['erro'])) {
                return response()->json(['error' => $resultado['erro']], 404);
            }

            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro perguntas-auth: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    /**
     * POST /api/nexo/validar-auth
     */
    public function validarAuth(Request $request): JsonResponse
    {
        if (!$this->validarToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telefone = $request->input('telefone', '');

        if (empty($telefone)) {
            return response()->json(['error' => 'Telefone obrigatório'], 400);
        }

        $respostas = [
            'pergunta1_campo' => $request->input('pergunta1_campo', ''),
            'pergunta1_valor' => $request->input('pergunta1_valor', ''),
            'pergunta2_campo' => $request->input('pergunta2_campo', ''),
            'pergunta2_valor' => $request->input('pergunta2_valor', ''),
            'pergunta3_campo' => $request->input('pergunta3_campo', ''),
            'pergunta3_valor' => $request->input('pergunta3_valor', ''),
            'pergunta4_campo' => $request->input('pergunta4_campo', ''),
            'pergunta4_valor' => $request->input('pergunta4_valor', ''),
        ];

        try {
            $resultado = $this->service->validarAuth($telefone, $respostas);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro validar-auth: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    /**
     * POST /api/nexo/consulta-status
     */
    public function consultaStatus(Request $request): JsonResponse
    {
        if (!$this->validarToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telefone = $request->input('telefone', '');

        if (empty($telefone)) {
            return response()->json(['error' => 'Telefone obrigatório'], 400);
        }

        try {
            $cpf = $request->input('cpf', '') ?: $request->input('documento', '');
            $resultado = $this->service->consultaStatus($telefone, $cpf ?: null);

            if (isset($resultado['erro'])) {
                return response()->json(['error' => $resultado['erro']], 404);
            }

            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro consulta-status: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    /**
     * POST /api/nexo/consulta-status-processo
     */
    public function consultaStatusProcesso(Request $request): JsonResponse
    {
        if (!$this->validarToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telefone = $request->input('telefone', '');
        $pasta = $request->input('pasta', '');

        if (empty($telefone) || empty($pasta)) {
            return response()->json(['error' => 'Telefone e pasta obrigatórios'], 400);
        }

        try {
            $resultado = $this->service->consultaStatusProcesso($telefone, $pasta);

            if (isset($resultado['erro'])) {
                return response()->json(['error' => $resultado['erro']], 404);
            }

            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro consulta-status-processo: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }
}
