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
