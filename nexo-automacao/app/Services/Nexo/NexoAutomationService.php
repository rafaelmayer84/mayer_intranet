<?php

namespace App\Services\Nexo;

use App\Models\NexoClienteValidacao;
use App\Models\NexoAutomationLog;
use App\Services\OpenAI\OpenAIService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NexoAutomationService
{
    private OpenAIService $openAI;

    public function __construct(OpenAIService $openAI)
    {
        $this->openAI = $openAI;
    }

    public function identificarCliente(string $telefone): array
    {
        $inicio = microtime(true);

        try {
            $cliente = NexoClienteValidacao::where('telefone', $telefone)->first();

            $resultado = [
                'encontrado' => (bool)$cliente,
                'cpf_cnpj' => $cliente?->cpf_cnpj_mascarado ?? null,
                'bloqueado' => $cliente?->estaBloqueado() ?? false
            ];

            NexoAutomationLog::create([
                'telefone' => $telefone,
                'acao' => 'identificacao',
                'dados' => $resultado,
                'tempo_resposta_ms' => (int)((microtime(true) - $inicio) * 1000)
            ]);

            return $resultado;

        } catch (\Exception $e) {
            Log::error('Erro identificação cliente', [
                'telefone' => $telefone,
                'erro' => $e->getMessage()
            ]);

            return [
                'encontrado' => false,
                'cpf_cnpj' => null,
                'bloqueado' => false,
                'erro' => 'Erro ao buscar cadastro'
            ];
        }
    }

    public function gerarPerguntasAuth(string $telefone): array
    {
        try {
            $cliente = NexoClienteValidacao::where('telefone', $telefone)->firstOrFail();

            if ($cliente->estaBloqueado()) {
                return [
                    'erro' => 'Cliente bloqueado temporariamente',
                    'bloqueado_ate' => $cliente->bloqueado_ate->format('d/m/Y H:i')
                ];
            }

            $perguntasDisponiveis = $this->obterPerguntasDisponiveis($cliente);
            $selecionadas = collect($perguntasDisponiveis)->random(2);

            return [
                'pergunta1' => $selecionadas[0]['pergunta'],
                'opcoes1' => $selecionadas[0]['opcoes'],
                'pergunta2' => $selecionadas[1]['pergunta'],
                'opcoes2' => $selecionadas[1]['opcoes'],
                'chaves' => [
                    'correta1' => $selecionadas[0]['correta'],
                    'correta2' => $selecionadas[1]['correta']
                ]
            ];

        } catch (\Exception $e) {
            Log::error('Erro gerar perguntas', [
                'telefone' => $telefone,
                'erro' => $e->getMessage()
            ]);

            return ['erro' => 'Não foi possível gerar perguntas de autenticação'];
        }
    }

    private function obterPerguntasDisponiveis(NexoClienteValidacao $cliente): array
    {
        $perguntas = [];

        if ($cliente->nome_mae) {
            $perguntas[] = [
                'pergunta' => 'Qual o nome completo da sua mãe?',
                'opcoes' => $this->gerarOpcoesFalsas($cliente->nome_mae, 'nome'),
                'correta' => $cliente->nome_mae
            ];
        }

        if ($cliente->cidade_nascimento) {
            $perguntas[] = [
                'pergunta' => 'Em qual cidade você nasceu?',
                'opcoes' => $this->gerarOpcoesFalsas($cliente->cidade_nascimento, 'cidade'),
                'correta' => $cliente->cidade_nascimento
            ];
        }

        if ($cliente->cidade_primeiro_processo) {
            $perguntas[] = [
                'pergunta' => 'Em qual cidade foi iniciado seu primeiro processo?',
                'opcoes' => $this->gerarOpcoesFalsas($cliente->cidade_primeiro_processo, 'cidade'),
                'correta' => $cliente->cidade_primeiro_processo
            ];
        }

        if ($cliente->ano_inicio_processo) {
            $perguntas[] = [
                'pergunta' => 'Em que ano você iniciou seu processo principal?',
                'opcoes' => $this->gerarOpcoesFalsas($cliente->ano_inicio_processo, 'ano'),
                'correta' => (string)$cliente->ano_inicio_processo
            ];
        }

        if ($cliente->tipo_acao) {
            $perguntas[] = [
                'pergunta' => 'Qual o tipo da sua ação judicial?',
                'opcoes' => $this->gerarOpcoesFalsas($cliente->tipo_acao, 'tipo_acao'),
                'correta' => $cliente->tipo_acao
            ];
        }

        return $perguntas;
    }

    private function gerarOpcoesFalsas(string $correta, string $tipo): array
    {
        $opcoes = [$correta];

        $falsas = match($tipo) {
            'nome' => ['Maria da Silva Santos', 'Ana Paula Costa', 'Joana de Souza Lima'],
            'cidade' => ['Itajaí', 'Balneário Camboriú', 'Navegantes', 'Florianópolis', 'Blumenau'],
            'ano' => ['2022', '2023', '2024', '2025'],
            'tipo_acao' => ['Trabalhista', 'Cível', 'Previdenciário', 'Penal'],
            default => ['Opção A', 'Opção B', 'Opção C']
        };

        $falsasSelecionadas = collect($falsas)
            ->reject(fn($f) => $f === $correta)
            ->random(2)
            ->values()
            ->toArray();

        $opcoes = array_merge($opcoes, $falsasSelecionadas);
        shuffle($opcoes);

        return array_values($opcoes);
    }

    public function validarAuth(string $telefone, string $resposta1, string $resposta2, array $chaves): array
    {
        $inicio = microtime(true);

        try {
            $cliente = NexoClienteValidacao::where('telefone', $telefone)->firstOrFail();

            if ($cliente->estaBloqueado()) {
                return [
                    'auth_ok' => false,
                    'tentativas_restantes' => 0,
                    'bloqueado' => true,
                    'mensagem' => 'Muitas tentativas incorretas. Aguarde 30 minutos.'
                ];
            }

            $correta1 = $chaves['correta1'];
            $correta2 = $chaves['correta2'];

            $acertou = (
                trim(strtolower($resposta1)) === trim(strtolower($correta1)) &&
                trim(strtolower($resposta2)) === trim(strtolower($correta2))
            );

            if ($acertou) {
                $cliente->resetarTentativas();

                NexoAutomationLog::create([
                    'telefone' => $telefone,
                    'acao' => 'auth_sucesso',
                    'dados' => ['respostas' => [$resposta1, $resposta2]],
                    'tempo_resposta_ms' => (int)((microtime(true) - $inicio) * 1000)
                ]);

                return [
                    'auth_ok' => true,
                    'tentativas_restantes' => 3,
                    'bloqueado' => false
                ];
            }

            $cliente->incrementarTentativa();

            NexoAutomationLog::create([
                'telefone' => $telefone,
                'acao' => $cliente->estaBloqueado() ? 'auth_bloqueio' : 'auth_falha',
                'dados' => [
                    'tentativa' => $cliente->tentativas_falhas,
                    'respostas' => [$resposta1, $resposta2]
                ],
                'tempo_resposta_ms' => (int)((microtime(true) - $inicio) * 1000)
            ]);

            return [
                'auth_ok' => false,
                'tentativas_restantes' => max(0, 3 - $cliente->tentativas_falhas),
                'bloqueado' => $cliente->estaBloqueado(),
                'mensagem' => $cliente->estaBloqueado() 
                    ? 'Muitas tentativas incorretas. Aguarde 30 minutos.'
                    : 'Resposta incorreta. Tente novamente.'
            ];

        } catch (\Exception $e) {
            Log::error('Erro validação auth', [
                'telefone' => $telefone,
                'erro' => $e->getMessage()
            ]);

            return [
                'auth_ok' => false,
                'erro' => 'Erro ao validar autenticação'
            ];
        }
    }

    public function consultarStatusProcesso(string $telefone): array
    {
        $inicio = microtime(true);

        try {
            $cliente = NexoClienteValidacao::where('telefone', $telefone)->firstOrFail();

            if (!$cliente->numero_processo) {
                return [
                    'sucesso' => false,
                    'mensagem' => 'Nenhum processo vinculado a este telefone.'
                ];
            }

            // Buscar andamentos da tabela andamentos_da_fase
            $andamentos = DB::table('andamentos_da_fase')
                ->where('numero_processo', $cliente->numero_processo)
                ->orderBy('data_andamento', 'desc')
                ->limit(5)
                ->get(['data_andamento as data', 'descricao'])
                ->map(function($a) {
                    return [
                        'data' => \Carbon\Carbon::parse($a->data)->format('d/m/Y'),
                        'descricao' => $a->descricao
                    ];
                })
                ->toArray();

            if (empty($andamentos)) {
                return [
                    'sucesso' => true,
                    'resposta_ia' => "Olá! Consultei seu processo {$cliente->numero_processo}, mas não há andamentos recentes registrados no momento."
                ];
            }

            // Obter nome do cliente (usar primeiro nome do campo nome_mae como fallback)
            $nomeCliente = explode(' ', $cliente->nome_mae ?? 'Cliente')[0];

            $respostaIA = $this->openAI->gerarRespostaStatusProcesso(
                $andamentos,
                $cliente->numero_processo,
                $nomeCliente
            );

            NexoAutomationLog::create([
                'telefone' => $telefone,
                'acao' => 'consulta_status',
                'dados' => [
                    'processo' => $cliente->numero_processo,
                    'andamentos_count' => count($andamentos)
                ],
                'resposta_ia' => $respostaIA,
                'tempo_resposta_ms' => (int)((microtime(true) - $inicio) * 1000)
            ]);

            return [
                'sucesso' => true,
                'resposta_ia' => $respostaIA
            ];

        } catch (\Exception $e) {
            Log::error('Erro consulta status', [
                'telefone' => $telefone,
                'erro' => $e->getMessage()
            ]);

            NexoAutomationLog::create([
                'telefone' => $telefone,
                'acao' => 'erro',
                'erro' => $e->getMessage(),
                'tempo_resposta_ms' => (int)((microtime(true) - $inicio) * 1000)
            ]);

            return [
                'sucesso' => false,
                'mensagem' => 'Erro ao consultar processo. Tente novamente ou fale com nossa equipe.'
            ];
        }
    }
}
