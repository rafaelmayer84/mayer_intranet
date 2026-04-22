<?php

namespace App\Http\Controllers;

use App\Models\NexoConsultaLog;
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

    private function registrarLog(Request $request, string $acao, string $resultado, ?int $clienteId = null, array $meta = []): void
    {
        try {
            NexoConsultaLog::create([
                'telefone'   => $request->input('telefone', ''),
                'cliente_id' => $clienteId,
                'acao'       => $acao,
                'resultado'  => $resultado,
                'ip'         => $request->ip(),
                'meta'       => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::error('[NEXO-LOG] Falha ao gravar nexo_consulta_log: ' . $e->getMessage());
        }
    }

    /**
     * Middleware de autenticação por token.
     * Valida X-Sendpulse-Token header.
     */
    private function validarToken(Request $request): bool
    {
        $token = $request->header('X-Sendpulse-Token');
        $esperado = config('services.nexo_consulta.token', env('NEXO_CONSULTA_TOKEN'));

        return is_string($token) && is_string($esperado) && hash_equals($esperado, $token);
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
            $encontrado = $resultado['encontrado'] ?? 'nao';
            $acao = $encontrado === 'nao' ? 'probe_suspeito' : 'identificar';
            $this->registrarLog($request, $acao, $encontrado === 'sim' ? 'ok' : 'nao_encontrado');
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
        $cpf = $request->input('cpf', '') ?: $request->input('documento', '');

        if (empty($telefone) && empty($cpf)) {
            return response()->json(['error' => 'Telefone ou CPF obrigatório'], 400);
        }

        try {
            $resultado = $this->service->gerarPerguntasAuth($telefone, $cpf ?: null);

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

        $cpf = $request->input('cpf', '') ?: $request->input('documento', '');

        $respostas = [
            'session_token'   => $request->input('session_token', ''),
            'pin_valor'       => $request->input('pin_valor', ''),
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
            $resultado = $this->service->validarAuth($telefone, $respostas, $cpf ?: null);
            $valido = $resultado['valido'] ?? 'nao';
            $bloqueado = $resultado['bloqueado'] ?? 'nao';
            $acao = $valido === 'sim' ? 'auth_ok' : 'auth_falha';
            $logResultado = $bloqueado === 'sim' ? 'bloqueado' : ($valido === 'sim' ? 'ok' : 'falha');
            $this->registrarLog($request, $acao, $logResultado);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro validar-auth: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }

    /**
     * POST /api/nexo/definir-pin
     */
    public function definirPin(Request $request): JsonResponse
    {
        if (!$this->validarToken($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $telefone = $request->input('telefone', '');
        $pin = $request->input('pin', '');

        if (empty($telefone) || empty($pin)) {
            return response()->json(['error' => 'Telefone e PIN obrigatórios'], 400);
        }

        try {
            $resultado = $this->service->definirPin($telefone, $pin);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro definir-pin: ' . $e->getMessage());
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

            $this->registrarLog($request, 'consulta_status', 'ok', null, [
                'total_processos' => $resultado['total'] ?? null,
            ]);
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

            $this->registrarLog($request, 'consulta_processo', 'ok', null, [
                'pasta' => $pasta,
            ]);
            return response()->json($resultado);
        } catch (\Exception $e) {
            Log::error('[NEXO-CONSULTA] Erro consulta-status-processo: ' . $e->getMessage());
            return response()->json(['error' => 'Erro interno'], 500);
        }
    }
}
