<?php

namespace App\Services\Nexo;

use App\Models\NexoClienteValidacao;
use App\Models\NexoAutomationLog;
use App\Models\Cliente;
use App\Models\Processo;
use App\Services\OpenAI\OpenAIService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NexoAutomationService
{
    private OpenAIService $openAIService;
    private string $dataJuriBaseUrl;
    private ?string $dataJuriToken = null;

    public function __construct(OpenAIService $openAIService)
    {
        $this->openAIService = $openAIService;
        $this->dataJuriBaseUrl = config('services.datajuri.base_url', 'https://api.datajuri.com.br');
    }

    // =====================================================
    // IDENTIFICAR CLIENTE (INALTERADO)
    // =====================================================

    public function identificarCliente(string $telefone): array
    {
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $validacao = NexoClienteValidacao::where('telefone', $telefoneNormalizado)->first();

        if ($validacao && $validacao->estaBloqueado()) {
            $this->logarAcao($telefoneNormalizado, 'auth_bloqueio', [
                'motivo' => 'Tentativas excedidas',
                'bloqueado_ate' => $validacao->bloqueado_ate
            ]);

            return [
                'encontrado' => false,
                'bloqueado' => true,
                'bloqueado_ate' => $validacao->bloqueado_ate->format('H:i')
            ];
        }

        $cliente = Cliente::where('telefone_normalizado', $telefoneNormalizado)->first();

        if ($cliente) {
            if (!$validacao) {
                $validacao = NexoClienteValidacao::create([
                    'telefone' => $telefoneNormalizado,
                    'cliente_id' => $cliente->id
                ]);
            }

            $this->logarAcao($telefoneNormalizado, 'identificacao', [
                'cliente_id' => $cliente->id,
                'nome' => $cliente->nome
            ]);

            return [
                'encontrado' => true,
                'nome' => $cliente->nome,
                'bloqueado' => false
            ];
        }

        $this->logarAcao($telefoneNormalizado, 'identificacao', ['resultado' => 'nao_encontrado']);

        return [
            'encontrado' => false,
            'bloqueado' => false
        ];
    }

    // =====================================================
    // GERAR PERGUNTAS AUTH (INALTERADO)
    // =====================================================

    public function gerarPerguntasAuth(string $telefone): array
    {
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $validacao = NexoClienteValidacao::where('telefone', $telefoneNormalizado)->first();

        if (!$validacao || !$validacao->cliente_id) {
            return ['erro' => 'Cliente não identificado'];
        }

        $cliente = Cliente::find($validacao->cliente_id);

        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        $perguntas = $this->gerarPerguntasAleatorias($cliente);

        if (empty($perguntas)) {
            return ['erro' => 'Dados insuficientes para autenticação'];
        }

        // Formato flat para SendPulse (sem arrays aninhados)
        $resultado = [
            'nome' => $cliente->nome,
            'pergunta1_texto' => $perguntas[0]['pergunta'] ?? '',
            'pergunta1_campo' => $perguntas[0]['campo'] ?? '',
            'pergunta1_opcao_a' => $perguntas[0]['opcoes'][0] ?? '',
            'pergunta1_opcao_b' => $perguntas[0]['opcoes'][1] ?? '',
            'pergunta1_opcao_c' => $perguntas[0]['opcoes'][2] ?? '',
            'pergunta2_texto' => $perguntas[1]['pergunta'] ?? '',
            'pergunta2_campo' => $perguntas[1]['campo'] ?? '',
            'pergunta2_opcao_a' => $perguntas[1]['opcoes'][0] ?? '',
            'pergunta2_opcao_b' => $perguntas[1]['opcoes'][1] ?? '',
            'pergunta2_opcao_c' => $perguntas[1]['opcoes'][2] ?? '',
        ];

        return $resultado;
    }

    // =====================================================
    // VALIDAR AUTH (INALTERADO)
    // =====================================================

    public function validarAuth(string $telefone, array $respostas): array
    {
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $validacao = NexoClienteValidacao::where('telefone', $telefoneNormalizado)->first();

        if (!$validacao || !$validacao->cliente_id) {
            return ['valido' => false, 'erro' => 'Cliente não identificado'];
        }

        if ($validacao->estaBloqueado()) {
            return [
                'valido' => false,
                'bloqueado' => true,
                'bloqueado_ate' => $validacao->bloqueado_ate->format('H:i')
            ];
        }

        $cliente = Cliente::find($validacao->cliente_id);

        // Aceitar formato flat do SendPulse OU formato array original
        $respostasNormalizadas = $respostas;
        if (isset($respostas['pergunta1_campo'])) {
            $respostasNormalizadas = [
                ['campo' => $respostas['pergunta1_campo'], 'valor' => $respostas['pergunta1_valor'] ?? ''],
                ['campo' => $respostas['pergunta2_campo'], 'valor' => $respostas['pergunta2_valor'] ?? ''],
            ];
        }

        $validacao_resultado = $this->validarRespostas($cliente, $respostasNormalizadas);

        if ($validacao_resultado) {
            $validacao->resetarTentativas();
            $this->logarAcao($telefoneNormalizado, 'auth_sucesso', ['cliente_id' => $cliente->id]);

            return [
                'valido' => true,
                'nome' => $cliente->nome,
                'cliente_id' => $cliente->id
            ];
        } else {
            $validacao->incrementarTentativa();
            $this->logarAcao($telefoneNormalizado, 'auth_falha', [
                'tentativas' => $validacao->tentativas_falhas,
                'bloqueado' => $validacao->tentativas_falhas >= 3
            ]);

            return [
                'valido' => false,
                'tentativas_restantes' => max(0, 3 - $validacao->tentativas_falhas),
                'bloqueado' => $validacao->tentativas_falhas >= 3
            ];
        }
    }

    // =====================================================
    // CONSULTAR STATUS PROCESSO (CORRIGIDO - ON-DEMAND API)
    // =====================================================

    public function consultarStatusProcesso(string $telefone): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $validacao = NexoClienteValidacao::where('telefone', $telefoneNormalizado)->first();

        if (!$validacao || !$validacao->cliente_id) {
            return ['erro' => 'Cliente não autenticado'];
        }

        $cliente = Cliente::find($validacao->cliente_id);

        $processos = Processo::where('cliente_datajuri_id', $cliente->datajuri_id)
            ->where('status', '!=', 'Encerrado')
            ->orderBy('data_abertura', 'desc')
            ->limit(10)
            ->get();

        if ($processos->isEmpty()) {
            return ['erro' => 'Nenhum processo ativo encontrado'];
        }

        // Múltiplos processos → retorna lista flat para SendPulse
        if ($processos->count() > 1) {
            $resultado = [
                'selecao_necessaria' => true,
                'mensagem' => "Olá {$cliente->nome}! Encontrei {$processos->count()} processos ativos no seu nome. Qual deseja consultar?",
                'total' => $processos->count(),
            ];

            foreach ($processos->values() as $i => $p) {
                $n = $i + 1;
                $resultado["proc{$n}_pasta"] = $p->pasta ?? '';
                $resultado["proc{$n}_adverso"] = $p->adverso_nome ?? 'Não informado';
                $resultado["proc{$n}_tipo"] = $p->tipo_acao ?? '';
                $resultado["proc{$n}_status"] = $p->status ?? '';
                $resultado["proc{$n}_descricao"] = ($p->pasta ?? '') . ' x ' . ($p->adverso_nome ?? 'Não informado');
            }

            $this->logarAcao($telefoneNormalizado, 'consulta_status_selecao', [
                'total_processos' => $processos->count(),
                'fonte' => 'datajuri_api_ondemand'
            ]);

            return $resultado;
        }

        // Processo único → consulta direto
        $processo = $processos->first();

        return $this->executarConsultaProcesso($telefoneNormalizado, $cliente, $processo);
    }

    public function consultarProcessoEspecifico(string $telefone, string $pasta): array
    {
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $validacao = NexoClienteValidacao::where('telefone', $telefoneNormalizado)->first();

        if (!$validacao || !$validacao->cliente_id) {
            return ['erro' => 'Cliente não autenticado'];
        }

        $cliente = Cliente::find($validacao->cliente_id);

        $processo = Processo::where('cliente_datajuri_id', $cliente->datajuri_id)
            ->where('pasta', $pasta)
            ->first();

        if (!$processo) {
            return ['erro' => 'Processo não encontrado ou não pertence a este cliente'];
        }

        return $this->executarConsultaProcesso($telefoneNormalizado, $cliente, $processo);
    }

    private function executarConsultaProcesso(string $telefoneNormalizado, Cliente $cliente, Processo $processo): array
    {
        $inicio = microtime(true);

        try {
            $andamentos = $this->buscarAndamentosDataJuri($processo->pasta);
        } catch (\Exception $e) {
            Log::error('NEXO: Erro ao consultar DataJuri API', [
                'processo' => $processo->pasta,
                'erro' => $e->getMessage()
            ]);
            $andamentos = [];
        }

        if (empty($andamentos)) {
            $resposta = "Olá {$cliente->nome}! Seu processo {$processo->pasta} x {$processo->adverso_nome} está ativo, mas não há andamentos recentes registrados no sistema. Se tiver dúvidas, nossa equipe está à disposição.";
        } else {
            $resposta = $this->openAIService->gerarRespostaStatusProcesso(
                $andamentos,
                $processo->pasta . ' x ' . ($processo->adverso_nome ?? ''),
                $cliente->nome
            );
        }

        $tempoResposta = (int)((microtime(true) - $inicio) * 1000);

        $this->logarAcao($telefoneNormalizado, 'consulta_status', [
            'processo_id' => $processo->id,
            'numero_processo' => $processo->pasta,
            'adverso' => $processo->adverso_nome,
            'total_andamentos' => count($andamentos),
            'fonte' => 'datajuri_api_ondemand'
        ], $resposta, $tempoResposta);

        return [
            'selecao_necessaria' => false,
            'resposta' => $resposta,
            'processo_pasta' => $processo->pasta,
            'processo_adverso' => $processo->adverso_nome ?? '',
            'processo_status' => $processo->status,
            'processo_tipo' => $processo->tipo_acao ?? '',
            'processo_descricao' => $processo->pasta . ' x ' . ($processo->adverso_nome ?? ''),
        ];
    }

    // =====================================================
    // DATAJURI API - CONSULTA ON-DEMAND
    // =====================================================

    private function buscarAndamentosDataJuri(string $numeroPasta): array
    {
        $this->autenticarDataJuri();

        if (!$this->dataJuriToken) {
            Log::error('NEXO: Falha na autenticação DataJuri');
            return [];
        }

        // Passo 1: Buscar faseProcessoId(s) do processo pela pasta
        $faseIds = $this->buscarFaseProcessoIds($numeroPasta);

        if (empty($faseIds)) {
            Log::warning('NEXO: Nenhuma fase encontrada para processo', ['pasta' => $numeroPasta]);
            return [];
        }

        // Passo 2: Buscar andamentos de cada fase
        $todosAndamentos = [];

        foreach ($faseIds as $faseId) {
            $andamentosFase = $this->buscarAndamentosPorFase($faseId);
            $todosAndamentos = array_merge($todosAndamentos, $andamentosFase);
        }

        // Ordenar por data decrescente e pegar os 10 mais recentes
        usort($todosAndamentos, function ($a, $b) {
            return strtotime($b['data_raw'] ?? '1970-01-01') - strtotime($a['data_raw'] ?? '1970-01-01');
        });

        return array_slice($todosAndamentos, 0, 10);
    }

    private function buscarFaseProcessoIds(string $numeroPasta): array
    {
        $url = $this->dataJuriBaseUrl . '/v1/entidades/FaseProcesso?' . http_build_query([
            'offset' => 0,
            'limit' => 20,
            'campos' => 'id,processo.pasta',
            'criterio' => 'processo.pasta | igual a | ' . $numeroPasta
        ]);

        $response = $this->dataJuriRequest($url);

        if (!$response || empty($response['rows'])) {
            return [];
        }

        return array_map(function ($row) {
            return (string)intval($row['id']);
        }, $response['rows']);
    }

    private function buscarAndamentosPorFase(string $faseProcessoId): array
    {
        $url = $this->dataJuriBaseUrl . '/v1/entidades/AndamentoFase?' . http_build_query([
            'offset' => 0,
            'limit' => 20,
            'campos' => 'id,descricao,data,hora,observacao,descricaoOriginal,faseProcesso.processo.pasta,faseProcesso.processo.cliente.nome,faseProcesso.processo.adverso.nome,faseProcesso.processo.proprietario.nome',
            'criterio' => 'faseProcessoId | igual a | ' . $faseProcessoId
        ]);

        $response = $this->dataJuriRequest($url);

        if (!$response || empty($response['rows'])) {
            return [];
        }

        return array_map(function ($row) {
            $dataRaw = $this->converterDataBR($row['data'] ?? '');

            return [
                'data' => $row['data'] ?? '',
                'data_raw' => $dataRaw,
                'hora' => $row['hora'] ?? '',
                'descricao' => $this->limparHtml($row['descricao'] ?? ''),
                'observacao' => $this->limparHtml($row['observacao'] ?? ''),
                'descricao_original' => $row['descricaoOriginal'] ?? '',
                'pasta' => $row['faseProcesso.processo.pasta'] ?? '',
                'cliente' => $row['faseProcesso.processo.cliente.nome'] ?? '',
                'adverso' => $row['faseProcesso.processo.adverso.nome'] ?? '',
                'responsavel' => $row['faseProcesso.processo.proprietario.nome'] ?? '',
            ];
        }, $response['rows']);
    }

    // =====================================================
    // DATAJURI API - AUTENTICAÇÃO E REQUISIÇÕES
    // =====================================================

    private function autenticarDataJuri(): void
    {
        $this->dataJuriToken = Cache::remember('datajuri_token_nexo', 3500, function () {
            $clientId = config('services.datajuri.client_id');
            $secretId = config('services.datajuri.secret_id');
            $email    = config('services.datajuri.email');
            $password = config('services.datajuri.password');

            if (!$clientId || !$secretId || !$email || !$password) {
                Log::error('NEXO: Credenciais DataJuri incompletas no .env');
                return null;
            }

            $credentials = base64_encode("{$clientId}:{$secretId}");

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$this->dataJuriBaseUrl}/oauth/token");
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'password',
                'username'   => $email,
                'password'   => $password,
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Basic {$credentials}",
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return $data['access_token'] ?? null;
            }

            Log::error('NEXO: Falha ao obter token DataJuri', [
                'http_code' => $httpCode,
                'response' => substr($response ?: '', 0, 200)
            ]);

            return null;
        });
    }

    private function dataJuriRequest(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->dataJuriToken}",
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('NEXO: cURL error DataJuri', ['erro' => $curlError, 'url' => $url]);
            return null;
        }

        if ($httpCode === 401) {
            // Token expirado, limpar cache e tentar novamente
            Cache::forget('datajuri_token_nexo');
            $this->autenticarDataJuri();

            if ($this->dataJuriToken) {
                return $this->dataJuriRequestSingle($url);
            }
            return null;
        }

        if ($httpCode !== 200) {
            Log::error('NEXO: HTTP error DataJuri', [
                'http_code' => $httpCode,
                'url' => $url,
                'response' => substr($response ?: '', 0, 300)
            ]);
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('NEXO: JSON decode error DataJuri', [
                'erro' => json_last_error_msg(),
                'response' => substr($response ?: '', 0, 300)
            ]);
            return null;
        }

        return $data;
    }

    private function dataJuriRequestSingle(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->dataJuriToken}",
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }

    // =====================================================
    // UTILITÁRIOS
    // =====================================================

    private function converterDataBR(string $dataBR): string
    {
        if (empty($dataBR)) {
            return '1970-01-01';
        }

        $partes = explode('/', $dataBR);
        if (count($partes) === 3) {
            return "{$partes[2]}-{$partes[1]}-{$partes[0]}";
        }

        return '1970-01-01';
    }

    private function limparHtml(string $texto): string
    {
        if (empty($texto)) {
            return '';
        }

        $texto = preg_replace('/<br\s*\/?>/', "\n", $texto);
        $texto = strip_tags($texto);
        $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
        $texto = preg_replace('/&nbsp;/', ' ', $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", $texto);

        return trim($texto);
    }

    // =====================================================
    // PERGUNTAS E VALIDAÇÃO AUTH (INALTERADOS)
    // =====================================================

    private function gerarPerguntasAleatorias(Cliente $cliente): array
    {
        $pool = [];

        if ($cliente->profissao) {
            $pool[] = [
                'campo' => 'profissao',
                'pergunta' => 'Qual é a sua profissão?',
                'opcoes' => $this->gerarOpcoesProfissao($cliente->profissao)
            ];
        }

        if ($cliente->endereco_cidade) {
            $pool[] = [
                'campo' => 'cidade',
                'pergunta' => 'Em qual cidade você reside?',
                'opcoes' => $this->gerarOpcoesCidade($cliente->endereco_cidade)
            ];
        }

        if ($cliente->data_nascimento) {
            $ano = date('Y', strtotime($cliente->data_nascimento));
            $pool[] = [
                'campo' => 'ano_nascimento',
                'pergunta' => 'Em que ano você nasceu?',
                'opcoes' => $this->gerarOpcoesAno($ano)
            ];
        }

        if ($cliente->email) {
            $pool[] = [
                'campo' => 'email',
                'pergunta' => 'Qual o seu e-mail cadastrado?',
                'opcoes' => $this->gerarOpcoesEmail($cliente->email)
            ];
        }

        if (count($pool) < 2) {
            return [];
        }

        shuffle($pool);
        return array_slice($pool, 0, 2);
    }

    private function validarRespostas(Cliente $cliente, array $respostas): bool
    {
        foreach ($respostas as $resposta) {
            $campo = $resposta['campo'];
            $valor = $resposta['valor'];

            $valido = match($campo) {
                'profissao' => strtolower($cliente->profissao) === strtolower($valor),
                'cidade' => strtolower($cliente->endereco_cidade) === strtolower($valor),
                'ano_nascimento' => date('Y', strtotime($cliente->data_nascimento)) === $valor,
                'email' => strtolower($cliente->email) === strtolower($valor),
                default => false
            };

            if (!$valido) {
                return false;
            }
        }

        return true;
    }

    private function gerarOpcoesProfissao(string $correta): array
    {
        $incorretas = ['Advogado', 'Engenheiro', 'Professor', 'Médico', 'Empresário', 'Autônomo'];
        $incorretas = array_diff($incorretas, [$correta]);
        shuffle($incorretas);

        $opcoes = [$correta, $incorretas[0], $incorretas[1]];
        shuffle($opcoes);

        return $opcoes;
    }

    private function gerarOpcoesCidade(string $correta): array
    {
        $incorretas = ['Itajaí', 'Balneário Camboriú', 'Florianópolis', 'Blumenau', 'Joinville', 'São Paulo'];
        $incorretas = array_diff($incorretas, [$correta]);
        shuffle($incorretas);

        $opcoes = [$correta, $incorretas[0], $incorretas[1]];
        shuffle($opcoes);

        return $opcoes;
    }

    private function gerarOpcoesAno(string $correto): array
    {
        $ano = (int)$correto;
        $opcoes = [
            $correto,
            (string)($ano - rand(1, 3)),
            (string)($ano + rand(1, 3))
        ];
        shuffle($opcoes);

        return $opcoes;
    }

    private function gerarOpcoesEmail(string $correto): array
    {
        $partes = explode('@', $correto);
        $usuario = $partes[0];
        $dominio = $partes[1] ?? 'gmail.com';

        $opcoes = [$correto];
        $tentativas = 0;
        while (count($opcoes) < 3 && $tentativas < 20) {
            $tipo = rand(0, 2);
            if ($tipo === 0) {
                $fake = $usuario . rand(10, 99) . '@' . $dominio;
            } elseif ($tipo === 1) {
                $fake = $usuario . '_' . rand(1, 99) . '@' . $dominio;
            } else {
                $fake = substr($usuario, 0, max(1, strlen($usuario) - 2)) . rand(10, 99) . '@' . $dominio;
            }
            if (!in_array($fake, $opcoes)) {
                $opcoes[] = $fake;
            }
            $tentativas++;
        }
        shuffle($opcoes);

        return $opcoes;
    }

    private function normalizarTelefone(string $telefone): string
    {
        return preg_replace('/\D/', '', $telefone);
    }

    private function logarAcao(string $telefone, string $acao, array $dados = [], ?string $respostaIA = null, ?int $tempoMs = null): void
    {
        NexoAutomationLog::create([
            'telefone' => $telefone,
            'acao' => $acao,
            'dados' => $dados,
            'resposta_ia' => $respostaIA,
            'tempo_resposta_ms' => $tempoMs
        ]);
    }
}
