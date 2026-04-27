<?php

namespace App\Services\Nexo;

use App\Exceptions\LexusAnthropicException;
use App\Models\NexoLexusSessao;
use App\Models\WaConversation;
use Illuminate\Support\Facades\Log;

class LexusOrchestratorService
{
    private const MAX_CONTEXTO_TURNOS = 30;
    private const MAX_MENSAGEM_CHARS  = 1000;
    private const MENSAGEM_ERRO_FALLBACK = 'Tive um problema técnico. Vou pedir para um atendente da equipe falar com você em instantes. 🙏';

    private LexusAnthropicClient $client;
    private LexusClienteLookupService $clienteLookup;
    private LexusLeadCreationService $leadCreation;
    private LexusWaConversationLinker $waLinker;

    public function __construct()
    {
        $apiKey = config('services.anthropic.lexus_key')
            ?? env('JUSTUS_ANTHROPIC_API_KEY');

        $model = env('LEXUS_CLAUDE_MODEL', 'claude-sonnet-4-5-20250929');

        $this->client        = new LexusAnthropicClient($apiKey, $model);
        $this->clienteLookup = new LexusClienteLookupService();
        $this->leadCreation  = new LexusLeadCreationService();
        $this->waLinker      = new LexusWaConversationLinker();
    }

    public function processar(array $input): array
    {
        $phone    = WaConversation::normalizePhone($input['phone']);
        $convId   = $input['conversation_id'];
        $nome     = $input['nome_whatsapp'] ?? null;
        $mensagem = $this->limparMensagem($input['mensagem'] ?? '');

        if (trim($mensagem) === '') {
            $mensagem = 'Olá';
        }

        // 1. Buscar/criar sessão
        $sessao = NexoLexusSessao::firstOrCreate(
            ['conversation_id' => $convId, 'phone' => $phone],
            ['etapa' => 'inicial', 'contato' => $nome]
        );

        if ($nome && !$sessao->contato) {
            $sessao->contato = $nome;
        }

        // 2. Lookup de cliente existente (se ainda não descoberto)
        $ehCliente   = false;
        $clienteNome = null;
        if (!$sessao->cliente_id) {
            $lookup = $this->clienteLookup->buscarPorPhone($phone);
            if ($lookup) {
                $sessao->cliente_id = $lookup['cliente_id'];
                $sessao->saveQuietly();
                $ehCliente   = $lookup['is_cliente'];
                $clienteNome = $lookup['nome'];
            }
        } else {
            $ehCliente = true;
        }

        // 3. Montar histórico para Anthropic (sem campo 'ts')
        $contextoAtual = $sessao->contexto_json ?? [];
        $historico = array_map(
            fn($turno) => ['role' => $turno['role'], 'content' => $turno['content']],
            $contextoAtual
        );

        // 4. Adicionar mensagem atual como último turno user
        $historico[] = ['role' => 'user', 'content' => $mensagem];

        // 5. Montar system prompt com contexto atualizado
        $systemPrompt = LexusPromptService::montarSystemPrompt([
            'nome_whatsapp'       => $nome ?? $sessao->contato,
            'eh_cliente_existente'=> $ehCliente,
            'cliente_nome'        => $clienteNome,
            'area_provavel_atual' => $sessao->area_provavel,
            'cidade_atual'        => $sessao->cidade,
            'total_interacoes'    => $sessao->total_interacoes,
            'data_hoje'           => now()->format('Y-m-d'),
            'em_horario_comercial'=> $this->emHorarioComercial(),
        ]);

        $tInicio = microtime(true);

        // 6. Chamar Anthropic
        try {
            $resultado = $this->client->messages($historico, $systemPrompt);
        } catch (LexusAnthropicException $e) {
            Log::error('LEXUS-V3 anthropic falhou', [
                'sessao_id' => $sessao->id,
                'erro'      => $e->getMessage(),
            ]);
            $this->persistirErro($sessao, $mensagem);
            return $this->respostaErro($sessao->id);
        }

        $tempoMs = round((microtime(true) - $tInicio) * 1000);

        // 7. Parsear JSON
        $ia = $this->parsearRespostaIA($resultado['content_text']);

        if ($ia === null) {
            Log::warning('LEXUS-V3 JSON inválido da IA', [
                'sessao_id' => $sessao->id,
                'raw'       => $resultado['content_text'],
            ]);
            $this->persistirErro($sessao, $mensagem);
            return $this->respostaErro($sessao->id);
        }

        // 8. Validar e truncar mensagem_para_cliente
        $mensagemCliente = $ia['mensagem_para_cliente'] ?? '';
        if (mb_strlen($mensagemCliente) > self::MAX_MENSAGEM_CHARS) {
            Log::warning('LEXUS-V3 mensagem truncada', [
                'sessao_id'    => $sessao->id,
                'tamanho_orig' => mb_strlen($mensagemCliente),
            ]);
            $mensagemCliente = mb_substr($mensagemCliente, 0, 997) . '...';
        }

        // 9. Mapear ação para etapa
        $etapa = $this->acaoParaEtapa($ia['acao'] ?? 'perguntar');

        // 10. Append contexto (turno user + turno assistant)
        $contextoAtual[] = ['role' => 'user',      'content' => $mensagem,        'ts' => now()->format('Y-m-d H:i:s')];
        $contextoAtual[] = ['role' => 'assistant',  'content' => $mensagemCliente, 'ts' => now()->format('Y-m-d H:i:s')];
        if (count($contextoAtual) > self::MAX_CONTEXTO_TURNOS) {
            $contextoAtual = array_slice($contextoAtual, -self::MAX_CONTEXTO_TURNOS);
        }

        // 11. Persistir campos da IA na sessão
        $sessao->etapa               = $etapa;
        $sessao->area_provavel       = $ia['area_detectada']         ?? $sessao->area_provavel;
        $sessao->intencao_contratar  = $ia['intencao_contratar']     ?? $sessao->intencao_contratar;
        $sessao->urgencia            = $ia['urgencia']               ?? $sessao->urgencia;
        $sessao->nome_cliente        = $ia['nome_cliente_capturado'] ?? $sessao->nome_cliente;
        $sessao->cidade              = $ia['cidade_capturada']       ?? $sessao->cidade;
        $sessao->resumo_caso         = $ia['resumo_caso']            ?? $sessao->resumo_caso;
        $sessao->briefing_operador   = $ia['briefing_operador']      ?? $sessao->briefing_operador;
        $sessao->contexto_json       = $contextoAtual;
        $sessao->ultima_atividade    = now();
        $sessao->total_interacoes    = ($sessao->total_interacoes ?? 0) + 1;
        $sessao->input_tokens_total  = ($sessao->input_tokens_total  ?? 0) + ($resultado['input_tokens']  ?? 0);
        $sessao->output_tokens_total = ($sessao->output_tokens_total ?? 0) + ($resultado['output_tokens'] ?? 0);
        $sessao->save();

        Log::warning('LEXUS-V3 saída', [
            'sessao_id'    => $sessao->id,
            'acao'         => $ia['acao'],
            'etapa'        => $etapa,
            'tokens_in'    => $resultado['input_tokens'],
            'tokens_out'   => $resultado['output_tokens'],
            'tempo_ms'     => $tempoMs,
            'raciocinio'   => $ia['raciocinio_interno'] ?? null,
        ]);

        // 12. Criar lead se qualificado
        if ($etapa === 'qualificado') {
            $leadId = $this->leadCreation->criarOuAtualizar($sessao);
            if ($leadId) {
                $sessao->lead_id = $leadId;
                $sessao->saveQuietly();
            }
        }

        // 13. Linkar wa_conversation (decide internamente o que fazer por etapa)
        $this->waLinker->linkar($sessao);

        return [
            'acao'           => $ia['acao'] ?? 'perguntar',
            'mensagem_lexus' => $mensagemCliente,
            'etapa_atual'    => $etapa,
            'lead_id'        => $sessao->lead_id,
            'sessao_id'      => $sessao->id,
        ];
    }

    private function parsearRespostaIA(string $texto): ?array
    {
        $texto = trim($texto);
        $texto = preg_replace('/^```json\s*/i', '', $texto);
        $texto = preg_replace('/\s*```$/i', '', $texto);

        $data = json_decode($texto, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        $acaoValida = ['perguntar', 'qualificado', 'desqualificado', 'spam', 'ja_cliente'];
        if (!isset($data['acao']) || !in_array($data['acao'], $acaoValida, true)) {
            return null;
        }

        return $data;
    }

    private function acaoParaEtapa(string $acao): string
    {
        return match ($acao) {
            'qualificado'    => 'qualificado',
            'desqualificado' => 'desqualificado',
            'spam'           => 'spam',
            'ja_cliente'     => 'ja_cliente',
            default          => 'triagem',
        };
    }

    private function persistirErro(NexoLexusSessao $sessao, string $mensagem): void
    {
        $contexto   = $sessao->contexto_json ?? [];
        $contexto[] = ['role' => 'user', 'content' => $mensagem, 'ts' => now()->format('Y-m-d H:i:s')];
        if (count($contexto) > self::MAX_CONTEXTO_TURNOS) {
            $contexto = array_slice($contexto, -self::MAX_CONTEXTO_TURNOS);
        }

        $sessao->etapa            = 'erro';
        $sessao->contexto_json    = $contexto;
        $sessao->ultima_atividade = now();
        $sessao->total_interacoes = ($sessao->total_interacoes ?? 0) + 1;
        $sessao->save();
    }

    private function respostaErro(int $sessaoId): array
    {
        return [
            'acao'           => 'erro',
            'mensagem_lexus' => self::MENSAGEM_ERRO_FALLBACK,
            'etapa_atual'    => 'erro',
            'lead_id'        => null,
            'sessao_id'      => $sessaoId,
        ];
    }

    private function limparMensagem(string $mensagem): string
    {
        return trim(preg_replace('/\{g?clid[^}]*\}|\{utm_[^}]*\}/i', '', $mensagem));
    }

    private function emHorarioComercial(): bool
    {
        $hora      = (int) now('America/Sao_Paulo')->format('H');
        $diaSemana = (int) now('America/Sao_Paulo')->format('N');
        return $diaSemana <= 5 && $hora >= 8 && $hora < 18;
    }
}
