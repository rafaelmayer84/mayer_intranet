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
    // 0. VERIFICAR SESSÃO AUTENTICADA
    // ================================================================

    /**
     * Verifica se o cliente já está autenticado (sessão ativa de 30min).
     * Retorna: sessao_ativa (sim/nao), nome
     */
    public function verificarSessao(string $telefone): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);

        $attempt = NexoAuthAttempt::where('telefone', $telefoneNorm)->first();

        if (!$attempt || !$attempt->autenticado_ate) {
            return ['sessao_ativa' => 'nao', 'nome' => ''];
        }

        if (Carbon::now()->lt(Carbon::parse($attempt->autenticado_ate))) {
            // Sessão ainda válida — buscar nome do cliente
            $cliente = $this->buscarClientePorTelefone($telefoneNorm);
            $nome = $cliente ? ($cliente->nome ?? '') : '';

            Log::info('[NEXO-CONSULTA] Sessão ativa', [
                'telefone' => $telefoneNorm,
                'expira' => $attempt->autenticado_ate,
            ]);

            return ['sessao_ativa' => 'sim', 'nome' => $nome];
        }

        // Sessão expirada — limpar
        $attempt->update(['autenticado_ate' => null]);

        return ['sessao_ativa' => 'nao', 'nome' => ''];
    }

    // ================================================================
    // 1. IDENTIFICAR CLIENTE
    // ================================================================

    /**
     * Identifica cliente pelo telefone.
     * Retorna: encontrado, nome, bloqueado
     */
    public function identificarCliente(string $telefone, ?string $cpf = null): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);

        Log::info('[NEXO-CONSULTA] identificarCliente', ['telefone' => $telefoneNorm, 'cpf' => $cpf ?? 'nao informado']);

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

        // Fallback: buscar por CPF/CNPJ se informado
        // SEGURANÇA: não atualizar celular aqui — identificação não é autenticação
        if (!$cliente && !empty($cpf)) {
            Log::info('[NEXO-CONSULTA] Telefone nao encontrado, tentando CPF');
            $cliente = $this->buscarClientePorCpf($cpf);
        }

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
    public function gerarPerguntasAuth(string $telefone, ?string $cpf = null): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        // Fallback: buscar por CPF/CNPJ
        if (!$cliente && !empty($cpf)) {
            Log::info('[NEXO-CONSULTA] consultaStatus: tentando CPF', ['cpf' => $cpf]);
            $cliente = $this->buscarClientePorCpf($cpf);
        }

        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        // Selecionar 2 campos disponíveis (que tenham dado preenchido)
        $camposDisponiveis = $this->getCamposDisponiveis($cliente);

        if (count($camposDisponiveis) < 4) {
            Log::warning('[NEXO-CONSULTA] Menos de 4 campos disponíveis para auth', [
                'cliente_id' => $cliente->id,
                'campos' => $camposDisponiveis,
            ]);
            return ['erro' => 'Dados insuficientes para autenticação. Entre em contato com o escritório.'];
        }

        // Sortear 4 campos aleatórios do pool disponível
        $camposSorteados = collect($camposDisponiveis)->shuffle()->take(4)->values()->all();

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
    public function validarAuth(string $telefone, array $respostas, ?string $cpf = null): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        // Fallback: buscar por CPF/CNPJ
        if (!$cliente && !empty($cpf)) {
            Log::info('[NEXO-CONSULTA] validarAuth: tentando CPF', ['cpf' => $cpf]);
            $cliente = $this->buscarClientePorCpf($cpf);
        }

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

        // Validar cada pergunta (4 perguntas)
        $acertos = 0;
        $totalPerguntas = 0;
        foreach ([1, 2, 3, 4] as $n) {
            $campo = $respostas["pergunta{$n}_campo"] ?? '';
            $valor = $respostas["pergunta{$n}_valor"] ?? '';

            if (empty($campo)) continue;
            $totalPerguntas++;

            if ($this->validarResposta($cliente, $campo, $valor)) {
                $acertos++;
            }
        }

        Log::info('[NEXO-AUTH-DEBUG]', [
            'campos_enviados' => array_keys(array_filter($respostas, fn($v, $k) => str_ends_with($k, '_campo') && !empty($v), ARRAY_FILTER_USE_BOTH)),
            'cliente_id' => $cliente->id,
            'acertos' => $acertos,
            'total_perguntas' => $totalPerguntas,
        ]);
        // Exige exatamente 4 perguntas respondidas — menos que isso é tentativa de bypass
        $valido = ($totalPerguntas === 4 && $acertos === 4);

        if ($valido) {
            // Reset tentativas e gravar sessão autenticada (30 min)
            $attempt->update([
                'tentativas' => 0,
                'bloqueado' => false,
                'bloqueado_ate' => null,
                'autenticado_ate' => Carbon::now()->addMinutes(30),
            ]);

            Log::info('[NEXO-CONSULTA] Auth OK - sessão 30min', ['cliente_id' => $cliente->id]);

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
            'bloqueado' => $bloqueado ? 'sim' : 'nao',
        ];
    }

    // ================================================================
    // 4. CONSULTA STATUS (lista processos ou responde direto)
    // ================================================================

    /**
     * Lista processos do cliente ou retorna status direto (se 1 processo).
     */
    public function consultaStatus(string $telefone, ?string $cpf = null): array
    {
        $telefoneNorm = $this->normalizarTelefone($telefone);
        $cliente = $this->buscarClientePorTelefone($telefoneNorm);

        // Fallback: buscar por CPF/CNPJ
        if (!$cliente && !empty($cpf)) {
            Log::info('[NEXO-CONSULTA] consultaStatus: tentando CPF', ['cpf' => $cpf]);
            $cliente = $this->buscarClientePorCpf($cpf);
        }

        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        // Buscar processos ativos
        $processos = DB::table('processos')
            ->where('cliente_datajuri_id', $cliente->datajuri_id)
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
                ->where('cliente_datajuri_id', $cliente->datajuri_id)
                ->where('status', 'Ativo')
                ->orderBy('pasta')
                ->get();
            $idx = (int)$pasta - 1;
            $processo = $processos[$idx] ?? null;
        } else {
            $processo = DB::table('processos')
                ->where('cliente_datajuri_id', $cliente->datajuri_id)
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
        if (!empty($cliente->endereco_bairro)) {
            $campos[] = 'bairro';
        }
        if (!empty($cliente->endereco_cep) && strlen(preg_replace('/\D/', '', $cliente->endereco_cep)) >= 5) {
            $campos[] = 'cep';
        }
        if (!empty($cliente->profissao) && !in_array(strtolower(trim($cliente->profissao)), ['-', 'n/a', 'nao informado', 'não informado', ''])) {
            $campos[] = 'profissao';
        }
        // Nome como fallback se ainda não tem campos suficientes
        if (!empty($cliente->nome) && count($campos) < 7) {
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
            case 'bairro':
                return $this->perguntaBairro($cliente);
            case 'cep':
                return $this->perguntaCep($cliente);
            case 'profissao':
                return $this->perguntaProfissao($cliente);
            default:
                return ['texto' => 'Erro', 'opcoes' => ['A', 'B', 'C']];
        }
    }

    private function perguntaEmail(object $cliente): array
    {
        $email = strtolower(trim($cliente->email));
        $parts = explode('@', $email);
        $usuario = $parts[0];
        $dominio = $parts[1] ?? 'gmail.com';

        // Gerar alternativas realistas baseadas no padrão do email real
        $dominiosFake = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'terra.com.br', 'live.com', 'icloud.com'];
        $dominiosFake = array_values(array_diff($dominiosFake, [$dominio]));
        shuffle($dominiosFake);

        // Estratégias de falsificação realista:
        $estrategias = [];
        // 1. Mesmo usuário, domínio diferente
        $estrategias[] = $usuario . '@' . $dominiosFake[0];
        // 2. Usuário similar (troca de caracteres)
        if (strlen($usuario) > 3) {
            $pos = rand(1, strlen($usuario) - 2);
            $charSwap = $usuario;
            $charSwap[$pos] = chr(ord($charSwap[$pos]) === ord('9') ? ord('0') : ord($charSwap[$pos]) + 1);
            $estrategias[] = $charSwap . '@' . $dominio;
        }
        // 3. Usuário com ponto/underscore inserido
        if (strlen($usuario) > 4) {
            $mid = (int)(strlen($usuario) / 2);
            $estrategias[] = substr($usuario, 0, $mid) . '.' . substr($usuario, $mid) . '@' . $dominio;
        }
        // 4. Usuário com número adicionado
        $estrategias[] = $usuario . rand(1, 99) . '@' . $dominio;

        // Garantir unicidade e diferença do real
        $estrategias = array_values(array_filter(array_unique($estrategias), fn($e) => $e !== $email));
        shuffle($estrategias);

        $opcoes = [$email, $estrategias[0] ?? ($usuario . '1@' . $dominio), $estrategias[1] ?? ($usuario . '@' . $dominiosFake[1])];
        shuffle($opcoes);

        return [
            'texto' => 'Qual é o seu e-mail cadastrado?',
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
        // Anos falsos mais próximos (1-2 anos) para dificultar
        $offsets = [1, -1, 2, -2];
        shuffle($offsets);
        $falso1 = $anoReal + $offsets[0];
        $falso2 = $anoReal + $offsets[1];
        // Garantir que falsos são diferentes entre si e do real
        while ($falso2 === $falso1 || $falso2 === $anoReal) {
            $falso2 = $anoReal + $offsets[2];
        }

        $opcoes = [(string) $anoReal, (string) $falso1, (string) $falso2];
        shuffle($opcoes);

        return [
            'texto' => 'Qual é o seu ano de nascimento?',
            'opcoes' => $opcoes,
        ];
    }

    private function perguntaNome(object $cliente): array
    {
        $nomeReal = trim($cliente->nome);
        $partes = explode(' ', $nomeReal);
        $primeiroNome = $partes[0];
        $ultimoSobrenome = end($partes);

        // Gerar nomes falsos mais realistas: mesmo primeiro nome + sobrenome similar
        $sobrenomesFake = [
            'Santos', 'Oliveira', 'Silva', 'Souza', 'Pereira', 'Costa',
            'Ferreira', 'Almeida', 'Rodrigues', 'Lima', 'Nascimento',
            'Carvalho', 'Ribeiro', 'Martins', 'Gomes', 'Barbosa',
            'Araújo', 'Moreira', 'Cardoso', 'Mendes', 'Vieira',
        ];

        // Manter o último sobrenome real e trocar o(s) do meio
        // para gerar alternativas mais confusas
        $sobrenomesFake = array_values(array_filter($sobrenomesFake, fn($s) =>
            strtolower($s) !== strtolower($ultimoSobrenome)
        ));
        shuffle($sobrenomesFake);

        if (count($partes) >= 3) {
            // Nome composto: manter primeiro + último, trocar meio
            $falso1 = $primeiroNome . ' ' . $sobrenomesFake[0] . ' ' . $ultimoSobrenome;
            $falso2 = $primeiroNome . ' ' . $sobrenomesFake[1] . ' ' . $ultimoSobrenome;
        } else {
            $falso1 = $primeiroNome . ' ' . $sobrenomesFake[0];
            $falso2 = $primeiroNome . ' ' . $sobrenomesFake[1];
        }

        // Garantir que falsos são diferentes do real
        $nomeRealLower = strtolower($nomeReal);
        while (strtolower($falso1) === $nomeRealLower) {
            array_shift($sobrenomesFake);
            $falso1 = $primeiroNome . ' ' . ($sobrenomesFake[0] ?? 'Santos');
        }
        while (strtolower($falso2) === $nomeRealLower || strtolower($falso2) === strtolower($falso1)) {
            $falso2 = $primeiroNome . ' ' . array_pop($sobrenomesFake);
        }

        $opcoes = [$nomeReal, $falso1, $falso2];
        shuffle($opcoes);

        return [
            'texto' => 'Qual é o seu nome completo cadastrado?',
            'opcoes' => $opcoes,
        ];
    }


    private function perguntaBairro(object $cliente): array
    {
        $bairroReal = trim($cliente->endereco_bairro);

        // Pool de bairros reais de SC e região para gerar alternativas realistas
        $poolBairros = [
            'Centro', 'Fazenda', 'Cordeiros', 'São Vicente', 'Dom Bosco',
            'Vila Nova', 'Ressacada', 'São João', 'Itaipava', 'Espinheiros',
            'Cabeçudas', 'Praia Brava', 'Municípios', 'Cidade Nova', 'Barra do Rio',
            'Costa e Silva', 'Vila Operária', 'Jardim Vitória', 'Tabuleiro',
            'Pioneiros', 'Nações', 'Das Nações', 'Campinas', 'Kobrasol',
            'Barreiros', 'Estreito', 'Coqueiros', 'Trindade', 'Ingleses',
            'Canasvieiras', 'Jurerê', 'Campeche', 'Rio Tavares', 'Saco dos Limões',
            'Bom Retiro', 'Garcia', 'Velha', 'Itoupava Norte', 'Vorstadt',
            'Glória', 'Boa Vista', 'América', 'Bucarein', 'Anita Garibaldi',
            'Saguaçu', 'Iririú', 'Aventureiro', 'Comasa', 'Floresta',
        ];

        // Remover o bairro real do pool
        $poolBairros = array_values(array_filter($poolBairros, function($b) use ($bairroReal) {
            return strtolower($b) !== strtolower($bairroReal);
        }));
        shuffle($poolBairros);

        $opcoes = [$bairroReal, $poolBairros[0] ?? 'Vila Nova', $poolBairros[1] ?? 'Centro'];
        shuffle($opcoes);

        return [
            'texto' => 'Qual bairro consta no seu cadastro?',
            'opcoes' => $opcoes,
        ];
    }

    private function perguntaCep(object $cliente): array
    {
        $cepReal = preg_replace('/\D/', '', $cliente->endereco_cep ?? '');
        $prefixo5Real = substr($cepReal, 0, 5);

        // Pool de prefixos CEP reais de SC e região para alternativas realistas
        $poolPrefixos = [
            '88301', '88302', '88303', '88304', '88305', // Itajaí
            '88330', '88331', '88332', '88333', '88334', // Balneário Camboriú
            '88340', '88341', '88342',                   // Camboriú
            '88310', '88311', '88312',                   // Penha
            '88350', '88351', '88352',                   // Brusque
            '89010', '89012', '89015',                   // Blumenau
            '89201', '89202', '89204',                   // Joinville
            '88015', '88020', '88035',                   // Florianópolis
            '88101', '88102', '88103',                   // São José
            '89560', '89564',                            // Videira
            '88501', '88502',                            // Lages
            '88701', '88702',                            // Tubarão
            '88801', '88802',                            // Criciúma
            '88201', '88202',                            // Tijucas
        ];

        // Remover prefixo real e os muito próximos (mesmo grupo)
        $grupoReal = substr($prefixo5Real, 0, 3);
        $poolFiltrado = array_values(array_filter($poolPrefixos, function($p) use ($prefixo5Real, $grupoReal) {
            return $p !== $prefixo5Real && substr($p, 0, 3) !== $grupoReal;
        }));
        shuffle($poolFiltrado);

        // Se não sobrou alternativas suficientes (CEP fora de SC), usar genéricos
        if (count($poolFiltrado) < 2) {
            $poolFiltrado = ['88301', '89012', '88020', '89201'];
            $poolFiltrado = array_values(array_filter($poolFiltrado, fn($p) => $p !== $prefixo5Real));
            shuffle($poolFiltrado);
        }

        $mascaraReal = substr($prefixo5Real, 0, 5) . '-***';
        $mascaraFalsa1 = substr($poolFiltrado[0], 0, 5) . '-***';
        $mascaraFalsa2 = substr($poolFiltrado[1] ?? $poolFiltrado[0], 0, 5) . '-***';

        $opcoes = [$mascaraReal, $mascaraFalsa1, $mascaraFalsa2];
        shuffle($opcoes);

        return [
            'texto' => 'Qual o início do seu CEP cadastrado?',
            'opcoes' => $opcoes,
        ];
    }

    private function perguntaProfissao(object $cliente): array
    {
        $profReal = trim($cliente->profissao);

        // Pool de profissões reais extraídas do banco para alternativas realistas
        $poolProfissoes = [
            'Empresário(a)', 'Vendedor(a)', 'Advogado(a)', 'Secretário(a)',
            'Médico(a)', 'Engenheiro(a)', 'Professor(a)', 'Autônomo(a)',
            'Aposentado(a)', 'Auxiliar Administrativo', 'Operador de Máquinas',
            'Analista de Logística', 'Operador(a) de Caixa', 'Gerente',
            'Funcionário(a) Público(a)', 'Motorista', 'Técnico(a) em Enfermagem',
            'Pedreiro', 'Eletricista', 'Mecânico(a)', 'Cozinheiro(a)',
            'Atendente', 'Contador(a)', 'Designer', 'Programador(a)',
            'Enfermeiro(a)', 'Dentista', 'Farmacêutico(a)', 'Vigilante',
            'Soldador', 'Pintor(a)', 'Repositor(a)', 'Entregador(a)',
            'Recepcionista', 'Almoxarife', 'Caldeireiro', 'Armador',
            'Estoquista', 'Zelador(a)', 'Porteiro(a)', 'Frentista',
        ];

        // Remover a profissão real do pool (case-insensitive)
        $profRealLower = strtolower($profReal);
        $poolFiltrado = array_values(array_filter($poolProfissoes, function($p) use ($profRealLower) {
            return strtolower($p) !== $profRealLower
                && strtolower(str_replace(['(a)', '(o)'], '', $p)) !== strtolower(str_replace(['(a)', '(o)'], '', $profRealLower));
        }));
        shuffle($poolFiltrado);

        $opcoes = [$profReal, $poolFiltrado[0] ?? 'Autônomo(a)', $poolFiltrado[1] ?? 'Empresário(a)'];
        shuffle($opcoes);

        return [
            'texto' => 'Qual a sua profissão cadastrada?',
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

            case 'bairro':
                return strtolower($valor) === strtolower(trim($cliente->endereco_bairro ?? ''));

            case 'cep':
                $cepReal = preg_replace('/\D/', '', $cliente->endereco_cep ?? '');
                $prefixoReal = substr($cepReal, 0, 5);
                $valorLimpo = preg_replace('/\D/', '', $valor);
                $prefixoValor = substr($valorLimpo, 0, 5);
                return $prefixoReal === $prefixoValor;

            case 'profissao':
                return strtolower($valor) === strtolower(trim($cliente->profissao ?? ''));

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

            $systemPrompt = "Você é LEXUS, assistente jurídico digital do escritório Mayer Advogados.\n\n"
                . "IDENTIDADE:\n"
                . "- Você representa um escritório de advocacia sério e competente\n"
                . "- Tom: profissional, seguro, empático — nunca robótico ou genérico\n"
                . "- Trate o cliente pelo nome e transmita que o caso dele é acompanhado de perto\n\n"
                . "ESTRUTURA DA RESPOSTA:\n"
                . "1. SITUAÇÃO ATUAL: O que está acontecendo no processo agora\n"
                . "2. SIGNIFICADO PRÁTICO: O que isso representa para o cliente no dia a dia\n"
                . "3. PRÓXIMOS PASSOS: O que tende a acontecer a seguir (sem prometer resultados)\n"
                . "4. ALERTA DE PRAZO: Se houver prazo ou intimação, destaque com ⏳\n\n"
                . "REGRAS:\n"
                . "- Use *negrito* para datas, valores e destaques (formato WhatsApp)\n"
                . "- Traduza TODOS os termos jurídicos para linguagem leiga\n"
                . "- Use no máximo 2 emojis estratégicos (⚖️ 📌 ⏳ ✅) — nunca excessivo\n"
                . "- NUNCA invente informações que não estejam nos andamentos\n"
                . "- NUNCA dê conselho jurídico ou opiniões sobre resultado\n"
                . "- Máximo 700 caracteres\n"
                . "- Responda em português brasileiro";

            $userPrompt = "DADOS DO PROCESSO:\n"
                . "- Cliente: {$cliente->nome}\n"
                . "- Pasta: {$pasta}\n"
                . "- Parte adversa: {$adverso}\n"
                . "- Total de andamentos: {$totalAndamentos}\n\n"
                . "ÚLTIMOS ANDAMENTOS (mais recente primeiro):\n"
                . "{$listaAndamentos}\n\n"
                . "Gere o resumo seguindo a estrutura definida no system prompt.";

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENAI_API_KEY'),
                'Content-Type'  => 'application/json',
            ])->timeout(25)->post('https://api.openai.com/v1/chat/completions', [
                'model'       => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $userPrompt],
                ],
                'max_tokens'  => 500,
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

            $maxTextoIA = 1000 - mb_strlen($header) - mb_strlen($footer);
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

    /**
     * Busca cliente por CPF/CNPJ (normaliza removendo mascara)
     */
    private function buscarClientePorCpf(string $cpf): ?object
    {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

        if (empty($cpfLimpo) || strlen($cpfLimpo) < 11) {
            return null;
        }

        $cliente = DB::table('clientes')
            ->where(function ($q) use ($cpfLimpo) {
                $q->where('cpf_cnpj', $cpfLimpo);

                if (strlen($cpfLimpo) === 11) {
                    $mascarado = substr($cpfLimpo, 0, 3) . '.' . substr($cpfLimpo, 3, 3) . '.' . substr($cpfLimpo, 6, 3) . '-' . substr($cpfLimpo, 9, 2);
                    $q->orWhere('cpf_cnpj', $mascarado);
                }

                if (strlen($cpfLimpo) === 14) {
                    $mascarado = substr($cpfLimpo, 0, 2) . '.' . substr($cpfLimpo, 2, 3) . '.' . substr($cpfLimpo, 5, 3) . '/' . substr($cpfLimpo, 8, 4) . '-' . substr($cpfLimpo, 12, 2);
                    $q->orWhere('cpf_cnpj', $mascarado);
                }
            })
            ->first();

        if ($cliente) {
            Log::info('[NEXO-CONSULTA] Cliente encontrado por CPF/CNPJ', ['cpf' => $cpfLimpo, 'nome' => $cliente->nome]);
        }

        return $cliente;
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

        // Fallback: buscar removendo máscara do banco (parênteses, hífens, espaços)
        if (!$cliente) {
            $cliente = DB::table('clientes')
                ->whereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone, '(', ''), ')', ''), '-', ''), ' ', ''), '+', '') IN (" . implode(',', array_fill(0, count($formatos), '?')) . ")",
                    $formatos
                )
                ->first();
        }

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
