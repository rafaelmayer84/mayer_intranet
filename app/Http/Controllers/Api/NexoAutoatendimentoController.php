<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Nexo\NexoAutoatendimentoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Jobs\ProcessChatIAJob;

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
        if (!$this->validarWebhookFlexivel($request)) {
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
    // CHAT IA (FASE 2)
    // =====================================================

    public function chatIA(Request $request)
    {
        if (!$this->validarWebhookFlexivel($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }
        $request->validate([
            'telefone' => 'required|string',
            'pergunta' => 'required|string|min:3|max:1000',
            'processo_pasta' => 'nullable|string|max:50',
        ]);

        $telefone = $request->input('telefone');
        $pergunta = $request->input('pergunta');
        $pasta = $request->input('processo_pasta');

        // Despachar para fila — processamento assíncrono
        Log::info('ChatIA payload recebido', ['body' => $request->all(), 'telefone' => $telefone, 'pergunta' => $pergunta]);
        ProcessChatIAJob::dispatch($telefone, $pergunta, $pasta);

        return response()->json([
            'encontrado' => 'true',
            'resposta' => 'Estou analisando sua pergunta, um momento por favor...',
            'async' => true,
        ]);
    }

    // =====================================================
    // SOLICITAR DOCUMENTO (FASE 2)
    // =====================================================

    public function solicitarDocumento(Request $request)
    {
        if (!$this->validarWebhookFlexivel($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }
        $request->validate(['telefone' => 'required|string', 'tipo_documento' => 'required|string', 'observacao' => 'nullable|string|max:500']);
        try {
            $resultado = $this->service->solicitarDocumento($request->input('telefone'), $request->input('tipo_documento'), $request->input('observacao'));
            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error('NexoAutoatendimento@solicitarDocumento erro', ['msg' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro ao registrar solicitação.'], 500);
        }
    }

    // =====================================================
    // ENVIAR DOCUMENTO (FASE 2)
    // =====================================================

    public function enviarDocumento(Request $request)
    {
        if (!$this->validarWebhookFlexivel($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }
        $request->validate(['telefone' => 'required|string', 'url_arquivo' => 'nullable|string|max:2000', 'observacao' => 'nullable|string|max:500']);
        try {
            $resultado = $this->service->enviarDocumento($request->input('telefone'), $request->input('url_arquivo'), $request->input('observacao'));
            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error('NexoAutoatendimento@enviarDocumento erro', ['msg' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro ao registrar documento.'], 500);
        }
    }

    // =====================================================
    // SOLICITAR AGENDAMENTO (FASE 2)
    // =====================================================

    public function solicitarAgendamento(Request $request)
    {
        if (!$this->validarWebhookFlexivel($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }
        $request->validate(['telefone' => 'required|string', 'motivo' => 'required|string', 'urgencia' => 'nullable|string|in:normal,urgente', 'preferencia' => 'nullable|string|in:manha,tarde,sem_preferencia', 'observacao' => 'nullable|string|max:500']);
        try {
            $resultado = $this->service->solicitarAgendamento($request->input('telefone'), $request->input('motivo'), $request->input('urgencia', 'normal'), $request->input('preferencia', 'sem_preferencia'), $request->input('observacao'));
            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error('NexoAutoatendimento@solicitarAgendamento erro', ['msg' => $e->getMessage()]);
            return response()->json(['erro' => 'Erro ao registrar agendamento.'], 500);
        }
    }

    // =====================================================
    // VALIDAÇÃO (mesmo padrão do NexoWebhookController)
    // =====================================================

    // =====================================================
    // TICKETS - RESUMIR CONTEXTO (IA)
    // =====================================================

    public function resumirContexto(Request $request)
    {
        if (!$this->validarWebhookFlexivel($request)) {
            return response()->json(['erro' => 'Nao autorizado'], 401);
        }

        $request->validate(['telefone' => 'required|string']);

        try {
            $resultado = $this->service->resumirContexto($request->input('telefone'));
            return response()->json($resultado);
        } catch (\Throwable $e) {
            Log::error('NexoAutoatendimento@resumirContexto erro', ['msg' => $e->getMessage()]);
            return response()->json([
                'sucesso' => true,
                'ticket_resumo' => 'Cliente entrou em contato via WhatsApp.',
                'fonte' => 'erro',
            ]);
        }
    }

    private function validarWebhook(Request $request): bool
    {
        $token = $request->header('X-Sendpulse-Token');
        $expectedToken = config('services.sendpulse.webhook_token');

        if (!$token || $token !== $expectedToken) {
            Log::warning('NEXO-Autoatendimento: webhook não autorizado', [
                'ip' => $request->ip(),
                'token_recebido' => $token,
            ]);
            return false;
        }

        return true;
    }

    private function validarWebhookFlexivel(Request $request): bool
    {
        // Aceita token via header OU via body (para testes do builder SendPulse)
        $token = $request->header('X-Sendpulse-Token') ?? $request->input('_token_auth');
        $expectedToken = config('services.sendpulse.webhook_token');

        if ($token && $token === $expectedToken) {
            return true;
        }

        // Fallback: aceitar requests do IP do SendPulse sem token (builder de teste)
        $sendpulseIps = ['185.23.85.', '185.23.86.', '185.23.87.', '91.229.95.', '178.32.', '188.40.', '46.4.'];
        $sendpulseIpv6 = ['2a02:4780:'];
        $ip = $request->ip();
        foreach ($sendpulseIps as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                Log::info('NEXO-Autoatendimento: acesso via IP SendPulse (v4)', ['ip' => $ip]);
                return true;
            }
        }
        foreach ($sendpulseIpv6 as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                Log::info('NEXO-Autoatendimento: acesso via IP SendPulse (v6)', ['ip' => $ip]);
                return true;
            }
        }

        Log::warning('NEXO-Autoatendimento: webhook flexivel não autorizado', [
            'ip' => $ip,
            'token_recebido' => $token,
        ]);
        return false;
    }

    /**
     * Desativar bot para conversa (chamado pelo flow SendPulse ao "Falar com equipe")
     * POST /api/nexo/autoatendimento/desativar-bot
     */
    public function desativarBot(Request $request)
    {
        $telefone = preg_replace('/\D/', '', $request->input('telefone', ''));
        if (empty($telefone)) {
            return response()->json(['success' => false, 'erro' => 'Telefone obrigatorio'], 400);
        }

        $conv = \App\Models\WaConversation::where('phone', $telefone)->first();
        if ($conv) {
            $conv->update(['bot_ativo' => false, 'status' => 'open']);
            \Log::info('Bot control: bot_ativo=false via endpoint desativar-bot', [
                'conv_id' => $conv->id, 'phone' => $telefone
            ]);
            return response()->json(['success' => true, 'bot_desativado' => 'sim']);
        }

        return response()->json(['success' => true, 'bot_desativado' => 'nao', 'motivo' => 'conversa nao encontrada']);
    }
}
