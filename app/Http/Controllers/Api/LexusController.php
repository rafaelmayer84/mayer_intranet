<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ValidatesSendPulseWebhook;
use App\Http\Controllers\Controller;
use App\Models\NexoLexusSessao;
use App\Models\WaConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LexusController extends Controller
{
    use ValidatesSendPulseWebhook;

    private const MAX_CONTEXTO_TURNOS = 30;
    private const MOCK_MENSAGEM = '[MOCK] Olá! Sou o Lexus, da Mayer. Estou em fase de testes — ainda não estou processando suas mensagens. Para falar com nossa equipe, ligue (47) 3842-1050.';

    public function processar(Request $request): JsonResponse
    {
        if (!$this->validarWebhookFlexivel($request)) {
            return response()->json(['erro' => 'Não autorizado'], 401);
        }

        $request->validate([
            'phone'           => 'required|string',
            'conversation_id' => 'required|string',
            'mensagem'        => 'nullable|string|max:4000',
            'nome_whatsapp'   => 'nullable|string|max:150',
        ]);

        $phone    = WaConversation::normalizePhone($request->phone);
        $convId   = $request->conversation_id;
        $mensagem = $this->limparMensagem($request->mensagem ?? '');
        $nome     = $request->nome_whatsapp;

        Log::info('LEXUS-V3: entrada', [
            'phone'           => $phone,
            'conversation_id' => $convId,
            'mensagem_len'    => strlen($mensagem),
        ]);

        try {
            $sessao = NexoLexusSessao::firstOrCreate(
                ['conversation_id' => $convId, 'phone' => $phone],
                ['etapa' => 'inicial', 'contato' => $nome]
            );

            if ($nome && !$sessao->contato) {
                $sessao->contato = $nome;
            }

            $contexto   = $sessao->contexto_json ?? [];
            $contexto[] = [
                'role'    => 'user',
                'content' => $mensagem,
                'ts'      => now()->format('Y-m-d H:i:s'),
            ];
            if (count($contexto) > self::MAX_CONTEXTO_TURNOS) {
                $contexto = array_slice($contexto, -self::MAX_CONTEXTO_TURNOS);
            }

            $sessao->contexto_json    = $contexto;
            $sessao->ultima_atividade = now();
            $sessao->total_interacoes = ($sessao->total_interacoes ?? 0) + 1;
            $sessao->save();

            $resposta = [
                'acao'           => 'perguntar',
                'mensagem_lexus' => self::MOCK_MENSAGEM,
                'etapa_atual'    => $sessao->etapa,
                'lead_id'        => null,
                'sessao_id'      => $sessao->id,
            ];

            Log::info('LEXUS-V3: saída', [
                'sessao_id'        => $sessao->id,
                'total_interacoes' => $sessao->total_interacoes,
                'acao'             => $resposta['acao'],
            ]);

            return response()->json($resposta);

        } catch (\Exception $e) {
            Log::error('LEXUS-V3: erro', ['erro' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'acao'           => 'erro',
                'mensagem_lexus' => 'Ocorreu um erro interno. Por favor, tente novamente em instantes.',
                'etapa_atual'    => 'erro',
                'lead_id'        => null,
                'sessao_id'      => null,
            ]);
        }
    }

    private function limparMensagem(string $mensagem): string
    {
        // Remove tracking params que às vezes chegam colados na mensagem
        return trim(preg_replace('/\{g?clid[^}]*\}|\{utm_[^}]*\}/i', '', $mensagem));
    }
}
