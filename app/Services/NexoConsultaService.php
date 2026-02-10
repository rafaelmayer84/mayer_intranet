<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\NexoAuthAttempt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class NexoConsultaService
{
    // ================================================================
    // CONSTANTES
    // ================================================================

    const MAX_TENTATIVAS = 3;
    const BLOQUEIO_MINUTOS = 30;
    const CAMPOS_AUTH = ['email', 'cpf_cnpj', 'data_nascimento', 'nome'];

    // ================================================================
    // 1. IDENTIFICAR CLIENTE
    // ================================================================

    /**
     * Identifica cliente pelo telefone.
     * Retorna: encontrado, nome, bloqueado
     */
    public function identificarCliente(string $telefone): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);

        Log::info('[NEXO-CONSULTA] identificarCliente', ['telefone' => $telefoneNorm]);

        // Verificar bloqueio
        $attempt = NexoAuthAttempt::where('telefone', $telefoneNorm)->first();
        if ($attempt && $attempt->estaBloqueado()) {
            return [
                'encontrado' => 'sim',
                'nome' => '',
                'bloqueado' => 'sim',
            ];
        }

        // Buscar cliente pelo telefone (match exato)
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        if (!$cliente) {
            Log::info('[NEXO-CONSULTA] Cliente não encontrado', ['telefone' => $telefoneNorm]);
            return [
                'encontrado' => 'nao',
                'nome' => '',
                'bloqueado' => 'nao',
            ];
        }

        Log::info('[NEXO-CONSULTA] Cliente encontrado', ['id' => $cliente->id, 'nome' => $cliente->nome]);

        return [
            'encontrado' => 'sim',
            'nome' => $cliente->nome ?? '',
            'bloqueado' => 'nao',
        ];
    }

    // ================================================================
    // 2. GERAR PERGUNTAS DE AUTENTICAÇÃO
    // ================================================================

    /**
     * Gera 2 perguntas aleatórias com 3 opções (1 correta + 2 falsas).
     * Campos possíveis: email, cpf_cnpj, data_nascimento, nome
     */
    public function gerarPerguntasAuth(string $telefone): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        // Selecionar 2 campos disponíveis (que tenham dado preenchido)
        $camposDisponiveis = $this->getCamposDisponiveis($cliente);

        if (count($camposDisponiveis) < 2) {
            Log::warning('[NEXO-CONSULTA] Menos de 2 campos disponíveis para auth', [
                'cliente_id' => $cliente->id,
                'campos' => $camposDisponiveis,
            ]);
            return ['erro' => 'Dados insuficientes para autenticação'];
        }

        // Sortear 2 campos
        $camposSorteados = collect($camposDisponiveis)->shuffle()->take(2)->values()->all();

        $resultado = [];
        foreach ($camposSorteados as $i => $campo) {
            $n = $i + 1;
            $pergunta = $this->montarPergunta($cliente, $campo);
            $resultado["pergunta{$n}_texto"] = $pergunta['texto'];
            $resultado["pergunta{$n}_campo"] = $campo;
            $resultado["pergunta{$n}_opcao_a"] = $pergunta['opcoes'][0];
            $resultado["pergunta{$n}_opcao_b"] = $pergunta['opcoes'][1];
            $resultado["pergunta{$n}_opcao_c"] = $pergunta['opcoes'][2];
        }

        Log::info('[NEXO-CONSULTA] Perguntas geradas', [
            'cliente_id' => $cliente->id,
            'campos' => $camposSorteados,
        ]);

        return $resultado;
    }

    // ================================================================
    // 3. VALIDAR AUTENTICAÇÃO
    // ================================================================

    /**
     * Valida as respostas da autenticação multifator.
     */
    public function validarAuth(string $telefone, array $respostas): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        if (!$cliente) {
            return ['valido' => 'nao', 'tentativas_restantes' => '0', 'bloqueado' => 'sim'];
        }

        // Obter ou criar registro de tentativas
        // Limpar tentativas antigas (>24h)
        NexoAuthAttempt::where('updated_at', '<', now()->subHours(24))->delete();

        $attempt = NexoAuthAttempt::firstOrCreate(
            ['telefone' => $telefoneNorm],
            ['tentativas' => 0, 'bloqueado' => false]
        );

        // Verificar bloqueio
        if ($attempt->estaBloqueado()) {
            return ['valido' => 'nao', 'tentativas_restantes' => '0', 'bloqueado' => 'sim'];
        }

        // Validar cada pergunta
        $acertos = 0;
        foreach ([1, 2] as $n) {
            $campo = $respostas["pergunta{$n}_campo"] ?? '';
            $valor = $respostas["pergunta{$n}_valor"] ?? '';

            if ($this->validarResposta($cliente, $campo, $valor)) {
                $acertos++;
            }
        }

        Log::info('[NEXO-AUTH-DEBUG]', ['respostas' => $respostas, 'cliente_nome' => $cliente->nome, 'cliente_email' => $cliente->email, 'acertos' => $acertos]);
        $valido = ($acertos === 2);

        if ($valido) {
            // Reset tentativas
            $attempt->update([
                'tentativas' => 0,
                'bloqueado' => false,
                'bloqueado_ate' => null,
            ]);

            Log::info('[NEXO-CONSULTA] Auth OK', ['cliente_id' => $cliente->id]);

            return ['valido' => 'sim', 'tentativas_restantes' => (string) self::MAX_TENTATIVAS, 'bloqueado' => 'nao'];
        }

        // Incrementar tentativas
        $tentativas = $attempt->tentativas + 1;
        $bloqueado = $tentativas >= self::MAX_TENTATIVAS;

        $attempt->update([
            'tentativas' => $tentativas,
            'bloqueado' => $bloqueado,
            'bloqueado_ate' => $bloqueado ? Carbon::now()->addMinutes(self::BLOQUEIO_MINUTOS) : null,
            'ultimo_tentativa' => Carbon::now(),
        ]);

        $restantes = max(0, self::MAX_TENTATIVAS - $tentativas);

        Log::warning('[NEXO-CONSULTA] Auth FALHOU', [
            'cliente_id' => $cliente->id,
            'tentativas' => $tentativas,
            'bloqueado' => $bloqueado,
        ]);

        return [
            'valido' => 'nao',
            'tentativas_restantes' => (string) $restantes,
            'bloqueado' => $bloqueado ? 'true' : 'false',
        ];
    }

    // ================================================================
    // 4. CONSULTA STATUS (lista processos ou responde direto)
    // ================================================================

    /**
     * Lista processos do cliente ou retorna status direto (se 1 processo).
     */
    public function consultaStatus(string $telefone): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        // Buscar processos ativos
        $processos = DB::table('processos')
            ->where('cliente_id', $cliente->id)
            ->where('status', 'Ativo')
            ->select('id', 'pasta', 'titulo', 'adverso_nome', 'numero')
            ->orderBy('pasta')
            ->get();

        $total = $processos->count();

        if ($total === 0) {
            return [
                'selecao_necessaria' => 'nao',
                'mensagem' => '',
                'resposta' => 'Não encontramos processos ativos vinculados ao seu cadastro. Se acredita que isso é um erro, entre em contato com nossa equipe.',
                'total' => '0',
            ];
        }

        if ($total === 1) {
            // Consultar direto
            $processo = $processos->first();
            $resposta = $this->montarRespostaProcesso($cliente, $processo);

            return [
                'selecao_necessaria' => 'nao',
                'mensagem' => '',
                'resposta' => $resposta,
                'total' => '1',
            ];
        }

        // Múltiplos processos — precisa seleção
        $resultado = [
            'selecao_necessaria' => 'sim',
            'mensagem' => "Você possui {$total} processos ativos. Qual deseja consultar?",
            'resposta' => '',
            'total' => (string) $total,
        ];

        // Montar lista compacta (limite 1024 chars SendPulse)
        $lista = '';
        foreach ($processos as $i => $proc) {
            $n = $i + 1;
            $adverso = $proc->adverso_nome ?: 'N/A';
            if (mb_strlen($adverso) > 25) {
                $adverso = mb_substr($adverso, 0, 23) . '..';
            }
            $pasta = $proc->pasta;
            $linha = "{$n}. {$pasta} x {$adverso}\n";
            if (mb_strlen($lista . $linha) > 950) {
                $lista .= "(+mais)\n";
                break;
            }
            $lista .= $linha;
        }
        $resultado['resposta'] = $lista;
        return $resultado;
    }

    // ================================================================
    // 5. CONSULTA STATUS PROCESSO ESPECÍFICO
    // ================================================================

    /**
     * Consulta processo específico por pasta.
     */
    public function consultaStatusProcesso(string $telefone, string $pasta): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        // Aceita numero sequencial (1,2,3...) ou pasta direta
        if (is_numeric($pasta) && (int)$pasta <= 50) {
            $processos = DB::table('processos')
                ->where('cliente_id', $cliente->id)
                ->where('status', 'Ativo')
                ->orderBy('pasta')
                ->get();
            $idx = (int)$pasta - 1;
            $processo = $processos[$idx] ?? null;
        } else {
            $processo = DB::table('processos')
                ->where('cliente_id', $cliente->id)
                ->where('pasta', $pasta)
                ->first();
        }

        if (!$processo) {
            return [
                'resposta' => 'Processo não encontrado. Verifique com nossa equipe.',
                'processo_descricao' => '',
            ];
        }

        $adverso = $processo->adverso_nome ?: 'N/A';

        return [
            'resposta' => $this->montarRespostaProcesso($cliente, $processo),
            'processo_descricao' => "Pasta {$processo->pasta} × {$adverso}",
        ];
    }

    // ================================================================
    // MÉTODOS PRIVADOS — AUTENTICAÇÃO
    // ================================================================

    private function getCamposDisponiveis(object $cliente): array
    {
        $campos = [];

        if (!empty($cliente->email)) {
            $campos[] = 'email';
        }
        if (!empty($cliente->cpf_cnpj) || !empty($cliente->cpf) || !empty($cliente->cnpj)) {
            $campos[] = 'cpf_cnpj';
        }
        if (!empty($cliente->data_nascimento)) {
            $campos[] = 'data_nascimento';
        }
        // Nome sempre disponível como fallback
        if (!empty($cliente->nome) && count($campos) < 4) {
            $campos[] = 'nome';
        }

        return $campos;
    }

    private function montarPergunta(object $cliente, string $campo): array
    {
        switch ($campo) {
            case 'email':
                return $this->perguntaEmail($cliente);
            case 'cpf_cnpj':
                return $this->perguntaCpfCnpj($cliente);
            case 'data_nascimento':
                return $this->perguntaAnoNascimento($cliente);
            case 'nome':
                return $this->perguntaNome($cliente);
            default:
                return ['texto' => 'Erro', 'opcoes' => ['A', 'B', 'C']];
        }
    }

    private function perguntaEmail(object $cliente): array
    {
        $email = strtolower(trim($cliente->email));
        $mascarado = $this->mascararEmail($email);

        // Gerar 2 emails falsos plausíveis
        $parts = explode('@', $email);
        $dominio = $parts[1] ?? 'gmail.com';
        $dominiosFake = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'terra.com.br'];
        $dominiosFake = array_diff($dominiosFake, [$dominio]);
        $dominiosFake = array_values($dominiosFake);

        $nomeParte = substr($parts[0], 0, 2);
        $falso1 = $nomeParte . str_pad(rand(10, 99), 2, '0') . '@' . $dominiosFake[0];
        $falso2 = $nomeParte . str_pad(rand(10, 99), 2, '0') . '@' . ($dominiosFake[1] ?? $dominiosFake[0]);

        $opcoes = [$email, $falso1, $falso2];
        shuffle($opcoes);

        return [
            'texto' => "Qual é o seu e-mail cadastrado?",
            'opcoes' => $opcoes,
        ];
    }

    private function perguntaCpfCnpj(object $cliente): array
    {
        $doc = $cliente->cpf_cnpj ?: $cliente->cpf ?: $cliente->cnpj ?: '';
        $docLimpo = preg_replace('/\D/', '', $doc);

        if (strlen($docLimpo) < 4) {
            return ['texto' => 'Erro', 'opcoes' => ['A', 'B', 'C']];
        }

        $ultimos4 = substr($docLimpo, -4);

        // Gerar 2 finais falsos
        $falso1 = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $falso2 = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        while ($falso1 === $ultimos4) $falso1 = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        while ($falso2 === $ultimos4 || $falso2 === $falso1) $falso2 = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);

        $tipo = strlen($docLimpo) > 11 ? 'CNPJ' : 'CPF';
        $opcoes = ["***{$ultimos4}", "***{$falso1}", "***{$falso2}"];
        shuffle($opcoes);

        return [
            'texto' => "Quais são os últimos 4 dígitos do seu {$tipo}?",
            'opcoes' => $opcoes,
        ];
    }

    private function perguntaAnoNascimento(object $cliente): array
    {
        $dataNasc = $cliente->data_nascimento;
        if (is_string($dataNasc)) {
            try {
                $dataNasc = Carbon::parse($dataNasc);
            } catch (\Exception $e) {
                return ['texto' => 'Erro', 'opcoes' => ['A', 'B', 'C']];
            }
        }

        $anoReal = (int) $dataNasc->format('Y');
        $falso1 = $anoReal + rand(1, 4);
        $falso2 = $anoReal - rand(1, 4);

        $opcoes = [(string) $anoReal, (string) $falso1, (string) $falso2];
        shuffle($opcoes);

        return [
            'texto' => "Qual é o seu ano de nascimento?",
            'opcoes' => $opcoes,
        ];
    }

    private function perguntaNome(object $cliente): array
    {
        $nomeReal = trim($cliente->nome);
        $partes = explode(' ', $nomeReal);
        $primeiroNome = $partes[0];

        // Gerar nomes falsos com mesmo primeiro nome
        $sobrenomesFake = ['Santos', 'Oliveira', 'Silva', 'Souza', 'Pereira', 'Costa', 'Ferreira', 'Almeida', 'Rodrigues', 'Lima'];
        shuffle($sobrenomesFake);

        $falso1 = $primeiroNome . ' ' . $sobrenomesFake[0];
        $falso2 = $primeiroNome . ' ' . $sobrenomesFake[1];

        // Garantir que falsos são diferentes do real
        while (strtolower($falso1) === strtolower($nomeReal)) {
            $falso1 = $primeiroNome . ' ' . array_shift($sobrenomesFake);
        }
        while (strtolower($falso2) === strtolower($nomeReal) || $falso2 === $falso1) {
            $falso2 = $primeiroNome . ' ' . array_pop($sobrenomesFake);
        }

        $opcoes = [$nomeReal, $falso1, $falso2];
        shuffle($opcoes);

        return [
            'texto' => "Qual é o seu nome completo cadastrado?",
            'opcoes' => $opcoes,
        ];
    }

    private function validarResposta(object $cliente, string $campo, string $valor): bool
    {
        $valor = trim($valor);

        switch ($campo) {
            case 'email':
                return strtolower($valor) === strtolower(trim($cliente->email ?? ''));

            case 'cpf_cnpj':
                $doc = $cliente->cpf_cnpj ?: $cliente->cpf ?: $cliente->cnpj ?: '';
                $docLimpo = preg_replace('/\D/', '', $doc);
                $ultimos4 = substr($docLimpo, -4);
                // Valor vem como "***1234", extrair últimos 4
                $valorLimpo = preg_replace('/\D/', '', $valor);
                $valorLimpo = substr($valorLimpo, -4);
                return $valorLimpo === $ultimos4;

            case 'data_nascimento':
                $dataNasc = $cliente->data_nascimento;
                if (is_string($dataNasc)) {
                    try { $dataNasc = Carbon::parse($dataNasc); } catch (\Exception $e) { return false; }
                }
                return (string) $dataNasc->format('Y') === (string) $valor;

            case 'nome':
                return strtolower($valor) === strtolower(trim($cliente->nome ?? ''));

            default:
                return false;
        }
    }

    // ================================================================
        // ================================================================
    // MÉTODOS PRIVADOS — CONSULTA PROCESSOS
    // ================================================================
    private function montarRespostaProcesso(object $cliente, object $processo): string
    {
        $pasta = $processo->pasta ?? '?';
        $adverso = $processo->adverso_nome ?: 'N/A';

        try {
            // 1. Obter token DataJuri
            $token = $this->obterTokenDataJuri();
            if (!$token) {
                Log::warning('NEXO: Token DataJuri indisponível, usando fallback');
                return $this->respostaFallbackProcesso($cliente, $processo);
            }

            // 2. Buscar andamentos em tempo real via API
            $response = Http::withToken($token)
                ->timeout(15)
                ->get('https://api.datajuri.com.br/v1/entidades/AndamentoFase', [
                    'criterio' => "faseProcesso.processo.pasta | igual a | {$pasta}",
                    'ordenarPor' => 'data | desc',
                    'tamanhoPagina' => 15,
                ]);

            if (!$response->successful()) {
                Log::warning('NEXO: DataJuri API falhou', [
                    'status' => $response->status(),
                    'pasta' => $pasta,
                ]);
                return $this->respostaFallbackProcesso($cliente, $processo);
            }

            $dados = $response->json();
            $andamentos = $dados['rows'] ?? [];
            $totalAndamentos = $dados['listSize'] ?? count($andamentos);

            if (empty($andamentos)) {
                return "📋 *Processo: Pasta {$pasta}*\n"
                     . "👥 {$cliente->nome} × {$adverso}\n\n"
                     . "📌 Nenhum andamento encontrado para este processo.\n\n"
                     . "💡 Em caso de dúvidas, fale com nossa equipe.";
            }

            // 3. Enviar para OpenAI interpretar
            $textoIA = $this->interpretarAndamentosComIA($cliente, $processo, $andamentos, $totalAndamentos);

            if ($textoIA) {
                return $textoIA;
            }

            // 4. Fallback sem IA (se OpenAI falhar)
            return $this->respostaFallbackProcesso($cliente, $processo, $andamentos);

        } catch (\Exception $e) {
            Log::error('NEXO: Erro montarRespostaProcesso', [
                'error' => $e->getMessage(),
                'pasta' => $pasta,
            ]);
            return $this->respostaFallbackProcesso($cliente, $processo);
        }
    }

    /**
     * Obtém token OAuth2 da API DataJuri
     */
    private function obterTokenDataJuri(): ?string
    {
        try {
            $clientId = env('DATAJURI_CLIENT_ID');
            $secretId = env('DATAJURI_SECRET_ID');
            $basic = base64_encode($clientId . ':' . $secretId);

            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . $basic,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])->asForm()->timeout(10)->post('https://api.datajuri.com.br/oauth/token', [
                'grant_type' => 'password',
                'username'   => env('DATAJURI_USERNAME'),
                'password'   => env('DATAJURI_PASSWORD'),
            ]);

            $token = $response->json('access_token');

            if (empty($token)) {
                Log::error('NEXO: Token DataJuri vazio', ['body' => $response->body()]);
                return null;
            }

            return $token;

        } catch (\Exception $e) {
            Log::error('NEXO: Falha obterTokenDataJuri', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Envia andamentos para OpenAI gerar texto humanizado
     */
    private function interpretarAndamentosComIA(
        object $cliente,
        object $processo,
        array $andamentos,
        int $totalAndamentos
    ): ?string {
        try {
            $pasta = $processo->pasta ?? '?';
            $adverso = $processo->adverso_nome ?: 'N/A';

            // Preparar lista dos últimos andamentos para a IA
            $listaAndamentos = '';
            foreach (array_slice($andamentos, 0, 15) as $a) {
                $desc = strip_tags($a['descricao'] ?? '');
                $listaAndamentos .= "- {$a['data']} {$a['hora']}: {$desc}\n";
            }

            $systemPrompt = "Você é assistente jurídico do escritório Mayer Advogados. "
                . "Sua função é explicar andamentos processuais de forma clara e acessível "
                . "para clientes que não são advogados. Responda sempre em português brasileiro.";

            $userPrompt = "O cliente *{$cliente->nome}* consultou o status do processo:\n"
                . "- Pasta: {$pasta}\n"
                . "- Parte adversa: {$adverso}\n"
                . "- Total de andamentos: {$totalAndamentos}\n\n"
                . "Últimos andamentos (do mais recente ao mais antigo):\n\n"
                . "{$listaAndamentos}\n"
                . "INSTRUÇÕES OBRIGATÓRIAS:\n"
                . "1. Escreva um resumo claro do status ATUAL do processo.\n"
                . "2. Explique o que o andamento mais recente significa na prática.\n"
                . "3. Se houver prazos mencionados, destaque com as datas.\n"
                . "4. Use linguagem simples — o cliente não é advogado.\n"
                . "5. Use *negrito* para datas e destaques importantes (formato WhatsApp).\n"
                . "6. NÃO use emojis, saudações ou despedidas.\n"
                . "7. NÃO invente informações que não estejam nos andamentos.\n"
                . "8. Máximo 550 caracteres.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type'  => 'application/json',
            ])->timeout(25)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'  => 350,
                'temperature' => 0.3,
            ]);

            if (!$response->successful()) {
                Log::warning('NEXO: OpenAI falhou', [
                    'status' => $response->status(),
                    'body'   => mb_substr($response->body(), 0, 200),
                ]);
                return null;
            }

            $textoIA = trim($response->json('choices.0.message.content') ?? '');

            if (empty($textoIA)) {
                Log::warning('NEXO: OpenAI retornou vazio');
                return null;
            }

            // Montar resposta final: cabeçalho + IA + rodapé
            $header = "📋 *Processo: Pasta {$pasta}*\n👥 {$cliente->nome} × {$adverso}\n\n";
            $footer = "\n\n💡 Em caso de dúvidas, fale com nossa equipe.";

            $maxTextoIA = 950 - mb_strlen($header) - mb_strlen($footer);
            if (mb_strlen($textoIA) > $maxTextoIA) {
                $textoIA = mb_substr($textoIA, 0, $maxTextoIA - 3) . '...';
            }

            return $header . $textoIA . $footer;

        } catch (\Exception $e) {
            Log::error('NEXO: Erro interpretarAndamentosComIA', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Resposta de fallback quando DataJuri ou OpenAI estão indisponíveis
     */
    private function respostaFallbackProcesso(object $cliente, object $processo, array $andamentos = []): string
    {
        $pasta = $processo->pasta ?? '?';
        $adverso = $processo->adverso_nome ?: 'N/A';

        $resposta = "📋 *Processo: Pasta {$pasta}*\n";
        $resposta .= "👥 {$cliente->nome} × {$adverso}\n\n";

        if (!empty($andamentos)) {
            // Mostrar os 3 últimos andamentos de forma simples
            $resposta .= "📌 *Últimos andamentos:*\n";
            foreach (array_slice($andamentos, 0, 3) as $a) {
                $desc = strip_tags($a['descricao'] ?? '');
                if (mb_strlen($desc) > 120) {
                    $desc = mb_substr($desc, 0, 118) . '..';
                }
                $resposta .= "• *{$a['data']}* — {$desc}\n";
            }
            $resposta .= "\n";
        } else {
            $resposta .= "📌 Não foi possível consultar os andamentos neste momento.\n\n";
        }

        $resposta .= "💡 Em caso de dúvidas, fale com nossa equipe.";

        // Garantir limite SendPulse
        if (mb_strlen($resposta) > 950) {
            $resposta = mb_substr($resposta, 0, 945) . '...';
        }

        return $resposta;
    }

    // MÉTODOS PRIVADOS — UTILITÁRIOS
    // ================================================================

    private function normalizarTelefone(string $telefone): string
    {
        // Remove tudo que não é número
        $tel = preg_replace('/\D/', '', $telefone);

        // Garantir formato brasileiro com 55
        if (strlen($tel) === 11) {
            $tel = '55' . $tel;
        } elseif (strlen($tel) === 10) {
            $tel = '55' . $tel;
        }

        return $tel;
    }

    private function buscarClientePorTelefone(string $telefoneNorm): ?object
    {
        // Montar lista de formatos possíveis para busca
        $formatos = [$telefoneNorm];

        // Se começa com 55, tentar sem o código do país
        if (str_starts_with($telefoneNorm, '55') && strlen($telefoneNorm) > 10) {
            $formatos[] = substr($telefoneNorm, 2); // sem 55
        }

        // Se tem 10 dígitos (DDD+8), tentar com 9 adicionado
        $semPais = str_starts_with($telefoneNorm, '55') ? substr($telefoneNorm, 2) : $telefoneNorm;
        if (strlen($semPais) === 10) {
            $formatos[] = substr($semPais, 0, 2) . '9' . substr($semPais, 2);
            $formatos[] = '55' . substr($semPais, 0, 2) . '9' . substr($semPais, 2);
        }

        // Se tem 11 dígitos (DDD+9+8), tentar sem o 9
        if (strlen($semPais) === 11 && $semPais[2] === '9') {
            $formatos[] = substr($semPais, 0, 2) . substr($semPais, 3);
        }

        $formatos = array_unique($formatos);

        $cliente = DB::table('clientes')
            ->whereIn('telefone', $formatos)
            ->first();

        // Se não encontrou na tabela clientes, tentar via wa_conversations linkada
        if (!$cliente) {
            $conv = DB::table('wa_conversations')
                ->where('phone', $telefoneNorm)
                ->whereNotNull('linked_cliente_id')
                ->first();

            if ($conv) {
                $cliente = DB::table('clientes')->where('id', $conv->linked_cliente_id)->first();
            }
        }

        return $cliente;
    }

    private function mascararEmail(string $email): string
    {
        $parts = explode('@', $email);
        $local = $parts[0];
        $domain = $parts[1] ?? '';
        $masked = substr($local, 0, 2) . str_repeat('*', max(3, strlen($local) - 2));
        return $masked . '@' . $domain;
    }

    private function formatarData($data): string
    {
        if (!$data) return '—';

        try {
            if ($data instanceof \DateTimeInterface) {
                return $data->format('d/m/Y');
            }
            return Carbon::parse($data)->format('d/m/Y');
        } catch (\Exception $e) {
            return (string) $data;
        }
    }
}
