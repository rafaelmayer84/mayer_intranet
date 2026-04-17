<?php

namespace App\Services\Nexo;

use App\Mail\NexoTicketAtribuido;
use App\Models\NexoAutomationLog;
use App\Models\NexoClienteValidacao;
use App\Models\NexoPublicToken;
use App\Models\NexoTicket;
use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmAdminProcess;
use App\Models\Crm\CrmServiceRequest;
use App\Models\Cliente;
use App\Models\NotificationIntranet;
use App\Models\Processo;
use App\Models\User;
use App\Services\OpenAI\ClaudeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class NexoAutoatendimentoService
{
    private ClaudeService $openAIService;
    private string $dataJuriBaseUrl;
    private ?string $dataJuriToken = null;

    public function __construct(ClaudeService $openAIService)
    {
        $this->openAIService = $openAIService;
        $this->dataJuriBaseUrl = config('services.datajuri.base_url', 'https://api.datajuri.com.br');
    }

    // =====================================================
    // 1. FINANCEIRO — TÍTULOS ABERTOS
    // =====================================================

    public function titulosAbertos(string $telefone): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) {
            return $cliente;
        }

        // Cache 60s por cliente
        $cacheKey = "nexo_financeiro_{$cliente->id}";
        $resultado = Cache::remember($cacheKey, 60, function () use ($cliente) {
            return $this->buscarTitulosAbertosHibrido($cliente);
        });

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);

        $this->logarAcao($telefoneNormalizado, 'consulta_financeiro', [
            'cliente_id' => $cliente->id,
            'total_titulos' => $resultado['total'] ?? 0,
            'fonte' => $resultado['fonte'] ?? 'desconhecida',
        ], null, $tempoMs);

        // V2: gerar token público + teaser
        if ($resultado['encontrado'] ?? false) {
            try {
                $payload = array_merge($resultado, ['nome_cliente' => $cliente->nome]);
                $token = $this->gerarTokenPublico('financeiro', $cliente->id, $telefoneNormalizado, $payload);
                $resultado['link_visual'] = $token->getUrl();
                $resultado['teaser'] = $this->gerarTeaser('financeiro', $resultado);
            } catch (\Throwable $e) {
                Log::warning('NexoAutoatendimento: falha ao gerar token público financeiro', ['erro' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    private function buscarTitulosAbertosHibrido(Cliente $cliente): array
    {
        // Tentativa 1: tabela local (sync 3x/dia)
        $titulos = DB::table('contas_receber')
            ->where('cliente_datajuri_id', $cliente->datajuri_id)
            ->where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->orderBy('data_vencimento', 'asc')
            ->limit(10)
            ->get();

        if ($titulos->isNotEmpty()) {
            return $this->formatarTitulosLocal($titulos, $cliente->nome, 'local');
        }

        // Fallback: API DataJuri on-demand
        if ($cliente->datajuri_id) {
            $titulosApi = $this->buscarContasReceberDataJuri($cliente->datajuri_id);
            if (!empty($titulosApi)) {
                return $this->formatarTitulosApi($titulosApi, $cliente->nome);
            }
        }

        return [
            'encontrado' => false,
            'total' => 0,
            'mensagem' => "Olá {$cliente->nome}! Não encontrei títulos em aberto no seu nome. Se acredita que há algo pendente, nossa equipe pode verificar.",
            'fonte' => 'nenhuma',
        ];
    }

    private function formatarTitulosLocal($titulos, string $nomeCliente, string $fonte): array
    {
        $totalValor = 0;
        $lista = [];

        foreach ($titulos as $i => $t) {
            $n = $i + 1;
            $vencimento = $t->data_vencimento
                ? \Carbon\Carbon::parse($t->data_vencimento)->format('d/m/Y')
                : 'Sem data';

            $valor = number_format((float)$t->valor, 2, ',', '.');
            $totalValor += (float)$t->valor;

            $situacao = 'Em aberto';
            if ($t->data_vencimento && \Carbon\Carbon::parse($t->data_vencimento)->isPast()) {
                $situacao = 'Vencido';
            }

            $lista[] = [
                'numero' => $n,
                'descricao' => $t->descricao ?? 'Título',
                'valor' => "R$ {$valor}",
                'vencimento' => $vencimento,
                'situacao' => $situacao,
            ];
        }

        $totalFormatado = number_format($totalValor, 2, ',', '.');
        $qtd = count($lista);

        // Mensagem flat para SendPulse
        $resultado = [
            'encontrado' => true,
            'total' => $qtd,
            'valor_total' => "R$ {$totalFormatado}",
            'fonte' => $fonte,
            'mensagem' => "Olá {$nomeCliente}! Encontrei {$qtd} título(s) em aberto, totalizando R$ {$totalFormatado}.",
        ];

        // Flat para variáveis SendPulse (máximo 5)
        foreach (array_slice($lista, 0, 5) as $i => $item) {
            $n = $i + 1;
            $resultado["titulo{$n}_desc"] = $item['descricao'];
            $resultado["titulo{$n}_valor"] = $item['valor'];
            $resultado["titulo{$n}_vencimento"] = $item['vencimento'];
            $resultado["titulo{$n}_situacao"] = $item['situacao'];
        }

        return $resultado;
    }

    private function formatarTitulosApi(array $titulosApi, string $nomeCliente): array
    {
        $lista = [];
        $totalValor = 0;

        foreach ($titulosApi as $i => $t) {
            $valor = $this->parseValorBR($t['valor'] ?? '0');
            $totalValor += $valor;

            $vencimento = isset($t['dataVencimento'])
                ? $this->formatarDataBRparaExibicao($t['dataVencimento'])
                : 'Sem data';

            $lista[] = [
                'numero' => $i + 1,
                'descricao' => $t['descricao'] ?? 'Título',
                'valor' => 'R$ ' . number_format($valor, 2, ',', '.'),
                'vencimento' => $vencimento,
                'situacao' => (isset($t['dataVencimento']) && $this->dataPassada($t['dataVencimento'])) ? 'Vencido' : 'Em aberto',
            ];
        }

        $totalFormatado = number_format($totalValor, 2, ',', '.');
        $qtd = count($lista);

        $resultado = [
            'encontrado' => true,
            'total' => $qtd,
            'valor_total' => "R$ {$totalFormatado}",
            'fonte' => 'datajuri_api',
            'mensagem' => "Olá {$nomeCliente}! Encontrei {$qtd} título(s) em aberto, totalizando R$ {$totalFormatado}.",
        ];

        foreach (array_slice($lista, 0, 5) as $i => $item) {
            $n = $i + 1;
            $resultado["titulo{$n}_desc"] = $item['descricao'];
            $resultado["titulo{$n}_valor"] = $item['valor'];
            $resultado["titulo{$n}_vencimento"] = $item['vencimento'];
            $resultado["titulo{$n}_situacao"] = $item['situacao'];
        }

        return $resultado;
    }

    private function buscarContasReceberDataJuri(int $datajuriId): array
    {
        $this->autenticarDataJuri();
        if (!$this->dataJuriToken) {
            return [];
        }

        $url = $this->dataJuriBaseUrl . '/v1/entidades/ContasReceber?' . http_build_query([
            'offset' => 0,
            'limit' => 10,
            'campos' => 'id,descricao,valor,dataVencimento,dataPagamento,status,cliente.nome',
            'criterio' => 'pessoaId | igual a | ' . $datajuriId,
        ]);

        $response = $this->dataJuriRequest($url);

        if (!$response || empty($response['rows'])) {
            return [];
        }

        // Filtrar apenas não pagos
        return array_filter($response['rows'], function ($row) {
            $status = $row['status'] ?? '';
            return stripos($status, 'conclu') === false && stripos($status, 'exclu') === false;
        });
    }

    // =====================================================
    // 2. SEGUNDA VIA (informativo, sem PDF)
    // =====================================================

    public function segundaVia(string $telefone): array
    {
        // Reutiliza titulosAbertos — a resposta já contém status+vencimento+valor
        $resultado = $this->titulosAbertos($telefone);

        if (!($resultado['encontrado'] ?? false)) {
            return $resultado;
        }

        $resultado['mensagem'] .= "\n\n📌 Para solicitar segunda via do boleto ou informações de pagamento, entre em contato com nossa equipe financeira.";

        return $resultado;
    }

    // =====================================================
    // 3. COMPROMISSOS — PRÓXIMAS AUDIÊNCIAS/PRAZOS
    // =====================================================

    public function proximosCompromissos(string $telefone): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) {
            return $cliente;
        }

        // Cache 120s por cliente (compromissos mudam menos)
        $cacheKey = "nexo_compromissos_{$cliente->id}";
        $resultado = Cache::remember($cacheKey, 120, function () use ($cliente) {
            return $this->buscarCompromissosCliente($cliente);
        });

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);

        $this->logarAcao($telefoneNormalizado, 'consulta_compromissos', [
            'cliente_id' => $cliente->id,
            'total_compromissos' => $resultado['total'] ?? 0,
        ], null, $tempoMs);

        // V2: gerar token público + teaser
        if (($resultado['encontrado'] ?? '') === 'sim') {
            try {
                $payload = array_merge($resultado, ['nome_cliente' => $cliente->nome]);
                $token = $this->gerarTokenPublico('compromissos', $cliente->id, $telefoneNormalizado, $payload);
                $resultado['link_visual'] = $token->getUrl();
                $resultado['teaser'] = $this->gerarTeaser('compromissos', $resultado);
            } catch (\Throwable $e) {
                Log::warning('NexoAutoatendimento: falha ao gerar token público compromissos', ['erro' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    private function buscarCompromissosCliente(Cliente $cliente): array
    {
        $processos = Processo::where('cliente_datajuri_id', $cliente->datajuri_id)
            ->where('status', '!=', 'Encerrado')
            ->get();

        if ($processos->isEmpty()) {
            return [
                'encontrado' => 'nao',
                'total' => 0,
                'mensagem' => "Olá {$cliente->nome}! Não encontrei processos ativos para buscar compromissos.",
            ];
        }

        $this->autenticarDataJuri();
        if (!$this->dataJuriToken) {
            return [
                'encontrado' => 'nao',
                'total' => 0,
                'mensagem' => "Olá {$cliente->nome}! Não foi possível consultar seus compromissos neste momento. Tente novamente em alguns minutos.",
            ];
        }

        $compromissos = [];
        $keywords = [
            'audiência', 'audiencia', 'perícia', 'pericia',
            'liminar', 'prazo', 'despacho', 'designad',
            'intimação', 'intimacao', 'pauta', 'sessão', 'sessao',
            'julgamento', 'sustentação', 'sustentacao',
        ];

        foreach ($processos->take(5) as $processo) {
            $faseIds = $this->buscarFaseProcessoIds($processo->pasta);

            foreach ($faseIds as $faseId) {
                $andamentos = $this->buscarAndamentosPorFase($faseId, 30);

                foreach ($andamentos as $and) {
                    $textoCompleto = strtolower(($and['descricao'] ?? '') . ' ' . ($and['observacao'] ?? ''));

                    foreach ($keywords as $kw) {
                        if (strpos($textoCompleto, $kw) !== false) {
                            $compromissos[] = [
                                'processo' => $processo->pasta,
                                'adverso' => $processo->adverso_nome ?? 'Não informado',
                                'data' => $and['data'] ?? '',
                                'data_raw' => $and['data_raw'] ?? '1970-01-01',
                                'hora' => $and['hora'] ?? '',
                                'descricao' => $and['descricao'] ?? '',
                                'tipo_detectado' => $kw,
                            ];
                            break; // Uma keyword por andamento basta
                        }
                    }
                }
            }
        }

        if (empty($compromissos)) {
            return [
                'encontrado' => 'nao',
                'total' => 0,
                'mensagem' => "Olá {$cliente->nome}! Consultei seus processos ativos e não identifiquei audiências, perícias ou prazos futuros registrados no momento. Se tiver dúvidas sobre datas específicas, nossa equipe pode verificar.",
            ];
        }

        // Ordenar por data (mais recentes primeiro — podem ser futuros)
        usort($compromissos, function ($a, $b) {
            return strcmp($b['data_raw'], $a['data_raw']);
        });

        $compromissos = array_slice($compromissos, 0, 5);

        // Montar resposta flat para SendPulse
        $resultado = [
            'encontrado' => 'sim',
            'total' => count($compromissos),
            'mensagem' => "Olá {$cliente->nome}! Encontrei " . count($compromissos) . " compromisso(s) relevante(s):",
        ];

        foreach ($compromissos as $i => $c) {
            $n = $i + 1;
            $resultado["comp{$n}_processo"] = $c['processo'];
            $resultado["comp{$n}_data"] = $c['data'];
            $resultado["comp{$n}_hora"] = $c['hora'];
            $resultado["comp{$n}_desc"] = mb_substr($c['descricao'], 0, 200);
            $resultado["comp{$n}_tipo"] = ucfirst($c['tipo_detectado']);
        }

        return $resultado;
    }

    // =====================================================
    // 4. TICKETS DE RETORNO
    // =====================================================

    public function abrirTicket(string $telefone, string $assunto, ?string $mensagem = null): array
    {
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);

        // Ticket pode ser aberto mesmo sem auth completo (pré-triagem)
        $clienteId = null;
        $datajuriId = null;
        $nomeCliente = null;

        if (!isset($cliente['erro'])) {
            $clienteId = $cliente->id;
            $datajuriId = $cliente->datajuri_id;
            $nomeCliente = $cliente->nome;
        }

        // Rate limit: máximo 3 tickets abertos por telefone (CRM service requests)
        $ticketsAbertos = CrmServiceRequest::where('phone_contato', $telefoneNormalizado)
            ->whereIn('status', ['aberto', 'em_andamento'])
            ->where('origem', 'autoatendimento')
            ->count();

        if ($ticketsAbertos >= 3) {
            return [
                'sucesso' => false,
                'mensagem' => 'Você já possui 3 solicitações em aberto. Aguarde o retorno da nossa equipe.',
            ];
        }

        // Buscar responsavel via CRM account
        $responsavelId = null;
        $statusInicial = 'aberto';

        $crmAccount = null;
        if ($datajuriId) {
            $crmAccount = DB::table('crm_accounts')
                ->where('datajuri_pessoa_id', $datajuriId)
                ->whereNotNull('owner_user_id')
                ->first(['owner_user_id', 'name']);
        }
        if (!$crmAccount) {
            $crmAccount = DB::table('crm_accounts')
                ->where('phone_e164', $telefoneNormalizado)
                ->whereNotNull('owner_user_id')
                ->first(['owner_user_id', 'name']);
        }
        if (!$crmAccount && $telefoneNormalizado) {
            // Tentar com ultimos 8 digitos (resolve problema do 9 faltante/sobrante)
            $parcial = substr($telefoneNormalizado, -8);
            $crmAccount = DB::table('crm_accounts')
                ->where('phone_e164', 'LIKE', '%' . $parcial)
                ->whereNotNull('owner_user_id')
                ->where('lifecycle', '!=', 'arquivado')
                ->first(['owner_user_id', 'name']);
        }

        // Fallback: buscar por nome aproximado (quando cliente autenticado)
        if (!$crmAccount && $nomeCliente && mb_strlen($nomeCliente) >= 5) {
            $crmAccount = DB::table('crm_accounts')
                ->where('name', 'LIKE', '%' . trim($nomeCliente) . '%')
                ->whereNotNull('owner_user_id')
                ->where('lifecycle', '!=', 'arquivado')
                ->first(['owner_user_id', 'name']);
        }

        // Fallback: buscar por datajuri_pessoa_id via tabela clientes
        if (!$crmAccount && !$datajuriId && $telefoneNormalizado) {
            $parcial8 = substr($telefoneNormalizado, -8);
            $clienteLocal = DB::table('clientes')
                ->where(function ($q) use ($telefoneNormalizado, $parcial8) {
                    $q->where('telefone_normalizado', 'LIKE', '%' . $parcial8)
                      ->orWhere('celular', 'LIKE', '%' . $parcial8);
                })
                ->whereNotNull('datajuri_id')
                ->first(['datajuri_id']);
            if ($clienteLocal) {
                $crmAccount = DB::table('crm_accounts')
                    ->where('datajuri_pessoa_id', $clienteLocal->datajuri_id)
                    ->whereNotNull('owner_user_id')
                    ->where('lifecycle', '!=', 'arquivado')
                    ->first(['owner_user_id', 'name']);
            }
        }

        if ($crmAccount && $crmAccount->owner_user_id) {
            $responsavelId = $crmAccount->owner_user_id;
            $statusInicial = 'em_andamento';
            Log::info('NEXO-TICKET: responsavel atribuido via CRM', [
                'owner_user_id' => $responsavelId,
                'crm_account' => $crmAccount->name ?? 'N/I',
                'telefone' => $telefoneNormalizado,
            ]);
        }

        $ticket = NexoTicket::create([
            'protocolo'      => $this->gerarProtocoloAutoatendimento('TK'),
            'telefone'       => $telefoneNormalizado,
            'nome_cliente'   => $nomeCliente,
            'datajuri_id'    => $datajuriId,
            'tipo'           => 'geral',
            'assunto'        => mb_substr($assunto, 0, 255),
            'mensagem'       => $mensagem ? mb_substr($mensagem, 0, 2000) : $assunto,
            'status'         => $statusInicial === 'em_andamento' ? 'em_andamento' : 'aberto',
            'prioridade'     => 'normal',
            'responsavel_id' => $responsavelId,
            'origem'         => 'autoatendimento',
        ]);

        $this->logarAcao($telefoneNormalizado, 'ticket_aberto', [
            'ticket_id' => $ticket->id,
            'protocolo' => $ticket->protocolo,
            'assunto'   => $assunto,
            'responsavel_id' => $responsavelId,
        ]);

        // Notificação bell + email para o responsável atribuído via CRM
        if ($responsavelId) {
            $this->notificarResponsavelTicket($ticket, $nomeCliente, $telefoneNormalizado);
        }

        $saudacao = $nomeCliente ? "Olá {$nomeCliente}!" : "Olá!";

        return [
            'sucesso' => true,
            'ticket_id' => $ticket->id,
            'protocolo' => $ticket->protocolo,
            'mensagem' => "{$saudacao} Sua solicitação {$ticket->protocolo} foi registrada com sucesso. Assunto: \"{$assunto}\". Nossa equipe entrará em contato em breve.",
        ];
    }

    public function listarTickets(?string $telefone = null, ?string $status = null, int $limit = 20): array
    {
        $query = NexoTicket::where('origem', 'autoatendimento')
            ->orderBy('created_at', 'desc');

        if ($telefone) {
            $query->where('telefone', $this->normalizarTelefone($telefone));
        }

        if ($status) {
            $query->where('status', $status);
        }

        $tickets = $query->limit($limit)->get();

        $listaTickets = $tickets->map(function ($t) {
            return [
                'id'           => $t->id,
                'protocolo'    => $t->protocolo,
                'nome_cliente' => $t->nome_cliente ?? '(Sem identificação)',
                'telefone'     => $t->telefone,
                'assunto'      => $t->assunto,
                'mensagem'     => $t->mensagem,
                'status'       => $t->status,
                'data'         => $t->created_at->format('d/m/Y H:i'),
            ];
        })->toArray();

        $resultado = [
            'total'   => $tickets->count(),
            'tickets' => $listaTickets,
        ];

        // V2: gerar token público + teaser (apenas se chamado com telefone específico)
        if ($telefone && $tickets->isNotEmpty()) {
            try {
                $telefoneNormalizado = $this->normalizarTelefone($telefone);
                $clienteAuth = $this->obterClienteAutenticado($telefoneNormalizado);
                $clienteId = isset($clienteAuth['erro']) ? null : $clienteAuth->id;
                $payload = array_merge($resultado, ['nome_cliente' => (!isset($clienteAuth['erro']) ? $clienteAuth->nome : null)]);
                $token = $this->gerarTokenPublico('tickets', $clienteId, $telefoneNormalizado, $payload);
                $resultado['link_visual'] = $token->getUrl();
                $resultado['teaser'] = $this->gerarTeaser('tickets', $resultado);
            } catch (\Throwable $e) {
                Log::warning('NexoAutoatendimento: falha ao gerar token público tickets', ['erro' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    // =====================================================
    // 5. RESUMO LEIGO APRIMORADO (com guarda LGPD)
    // =====================================================

    public function resumoLeigo(string $telefone, ?string $pasta = null): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) {
            return $cliente;
        }

        // Determinar processo
        if ($pasta) {
            $processo = Processo::where('cliente_datajuri_id', $cliente->datajuri_id)
                ->where('pasta', $pasta)
                ->first();
        } else {
            $processo = Processo::where('cliente_datajuri_id', $cliente->datajuri_id)
                ->where('status', '!=', 'Encerrado')
                ->orderBy('data_abertura', 'desc')
                ->first();
        }

        if (!$processo) {
            return ['erro' => 'Nenhum processo encontrado'];
        }

        // Cache 60s por processo
        $cacheKey = "nexo_resumo_{$processo->id}";
        $resultado = Cache::remember($cacheKey, 60, function () use ($cliente, $processo) {
            return $this->gerarResumoLeigo($cliente, $processo);
        });

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);

        $this->logarAcao($telefoneNormalizado, 'consulta_resumo', [
            'processo_id' => $processo->id,
            'pasta' => $processo->pasta,
        ], $resultado['resumo'] ?? null, $tempoMs);

        // V2: gerar token público com snapshot enriquecido para a página visual
        if (!isset($resultado['erro'])) {
            try {
                // Enriquecer andamentos com explicações leigas via Claude
                $andamentosEnriquecidos = $this->openAIService->explicarAndamentos(
                    $resultado['_andamentos'] ?? []
                );

                // Buscar atividades do processo e estatísticas do portfólio
                $dadosAtividades = $this->buscarAtividadesProcesso($processo->id, $processo->cliente_datajuri_id);

                $payload = array_merge($resultado, [
                    'nome_cliente'         => $cliente->nome,
                    'processo_status'      => $processo->status ?? '',
                    'tipo_acao'            => $processo->tipo_acao ?? '',
                    'tipo_processo'        => $processo->tipo_processo ?? '',
                    'area_atuacao'         => $processo->area_atuacao ?? '',
                    'natureza'             => $processo->natureza ?? '',
                    'assunto'              => $processo->assunto ?? '',
                    'advogado_responsavel' => $processo->advogado_responsavel ?? '',
                    'posicao_cliente'      => $processo->posicao_cliente ?? '',
                    'posicao_adverso'      => $processo->posicao_adverso ?? '',
                    'fase_vara'            => $processo->fase_atual_vara ?? '',
                    'fase_instancia'       => $processo->fase_atual_instancia ?? '',
                    'fase_orgao'           => $processo->fase_atual_orgao ?? '',
                    'data_abertura'        => $processo->data_abertura
                                                ? \Carbon\Carbon::parse($processo->data_abertura)->format('d/m/Y')
                                                : '',
                    'andamentos'              => $andamentosEnriquecidos,
                    'atividades_concluidas'   => $dadosAtividades['concluidas'],
                    'atividades_pendentes'    => $dadosAtividades['pendentes'],
                    'total_horas_minutos'     => $dadosAtividades['total_minutos'],
                    'portfolio_total'         => $dadosAtividades['portfolio_total'],
                    'portfolio_ativos'        => $dadosAtividades['portfolio_ativos'],
                ]);
                // Remover campo interno do retorno V1
                unset($payload['_andamentos'], $resultado['_andamentos']);

                $token = $this->gerarTokenPublico('processo-judicial', $cliente->id, $telefoneNormalizado, $payload);
                $resultado['link_visual'] = $token->getUrl();
                $resultado['teaser'] = $this->gerarTeaser('processo-judicial', $resultado);
                // Embutir URL direto no resumo — o flow SendPulse exibe só o campo `resumo`
                $resultado['resumo'] = ($resultado['resumo'] ?? '') . "\n\n🔗 " . $resultado['link_visual'];
            } catch (\Throwable $e) {
                Log::warning('NexoAutoatendimento: falha ao gerar token público processo-judicial', ['erro' => $e->getMessage()]);
            }
        }

        return $resultado;
    }

    private function gerarResumoLeigo(Cliente $cliente, Processo $processo): array
    {
        $this->autenticarDataJuri();
        if (!$this->dataJuriToken) {
            return [
                'erro' => 'Não foi possível consultar dados neste momento. Tente novamente em alguns minutos.',
            ];
        }

        $faseIds = $this->buscarFaseProcessoIds($processo->pasta);
        $andamentos = [];

        foreach ($faseIds as $faseId) {
            $and = $this->buscarAndamentosPorFase($faseId, 10);
            $andamentos = array_merge($andamentos, $and);
        }

        // Ordenar e pegar 5 mais recentes
        usort($andamentos, function ($a, $b) {
            return strcmp($b['data_raw'] ?? '1970-01-01', $a['data_raw'] ?? '1970-01-01');
        });
        $andamentos = array_slice($andamentos, 0, 5);

        if (empty($andamentos)) {
            return [
                'resumo'          => "Olá {$cliente->nome}! Seu processo {$processo->pasta} está ativo, mas não há movimentações recentes registradas.",
                'processo_pasta'  => $processo->pasta,
                '_andamentos'     => [],
            ];
        }

        // Chamar Claude com guarda LGPD
        $resumo = $this->openAIService->gerarResumoLeigo(
            $andamentos,
            $processo->pasta . ' x ' . ($processo->adverso_nome ?? ''),
            $cliente->nome
        );

        return [
            'resumo'           => $resumo,
            'processo_pasta'   => $processo->pasta,
            'processo_adverso' => $processo->adverso_nome ?? '',
            '_andamentos'      => $andamentos, // campo interno — usado pelo token V2, removido do retorno V1
        ];
    }

    // =====================================================
    // 7. CHAT IA — CONVERSA LIVRE SOBRE O CASO (FASE 2)
    // =====================================================

    public function chatIA(string $telefone, string $pergunta, ?string $processoPasta = null): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);
        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) { return $cliente; }

        $perguntaLimpa = trim(strip_tags($pergunta));
        if (empty($perguntaLimpa) || mb_strlen($perguntaLimpa) < 3) {
            return ['erro' => 'Por favor, digite uma pergunta válida.'];
        }
        if (mb_strlen($perguntaLimpa) > 1000) {
            $perguntaLimpa = mb_substr($perguntaLimpa, 0, 1000);
        }

        $pastaFiltro = $processoPasta ? trim($processoPasta) : null;
        $cacheKey = "nexo_chatia_{$cliente->id}_" . md5($perguntaLimpa . ($pastaFiltro ?? ''));
        $resultado = Cache::remember($cacheKey, 30, function () use ($cliente, $perguntaLimpa, $pastaFiltro) {
            return $this->executarChatIA($cliente, $perguntaLimpa, $pastaFiltro);
        });

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);
        $this->logarAcao($telefoneNormalizado, 'chat_ia', [
            'cliente_id' => $cliente->id, 'pergunta' => $perguntaLimpa,
        ], $resultado['resposta'] ?? null, $tempoMs);
        return $resultado;
    }

    private function executarChatIA(Cliente $cliente, string $pergunta, ?string $processoPasta = null): array
    {
        $djId = $cliente->datajuri_id;
        $nomeCliente = $cliente->nome;

        // Se processo_pasta fornecido, resolver número da opção para pasta real
        if ($processoPasta && is_numeric($processoPasta)) {
            $idx = (int)$processoPasta - 1;
            $todasPastas = DB::table('processos')
                ->where('cliente_datajuri_id', $djId)
                ->orderByDesc('data_abertura')
                ->pluck('pasta')->toArray();
            if (isset($todasPastas[$idx])) {
                $processoPasta = $todasPastas[$idx];
                Log::info('ChatIA: pasta resolvida', ['indice' => $idx + 1, 'pasta' => $processoPasta]);
            }
        }

        $qProcessos = DB::table('processos')->where('cliente_datajuri_id', $djId);
        if ($processoPasta) {
            $qProcessos->where('pasta', $processoPasta);
        }
        $processos = $qProcessos->select('pasta', 'titulo', 'status', 'tipo_acao', 'area_atuacao',
                     'data_abertura', 'adverso_nome', 'fase_atual_instancia',
                     'fase_atual_vara', 'advogado_responsavel', 'valor_causa', 'possibilidade')
            ->orderByDesc('data_abertura')->limit(10)->get()->toArray();

        $contas = DB::table('contas_receber')
            ->where('cliente_datajuri_id', $djId)
            ->select('descricao', 'valor', 'data_vencimento', 'data_pagamento', 'status')
            ->orderByDesc('data_vencimento')->limit(10)->get()->toArray();

        $contratos = DB::table('contratos')
            ->where('contratante_id_datajuri', $djId)
            ->select('numero', 'valor', 'data_assinatura')
            ->orderByDesc('data_assinatura')->limit(5)->get()->toArray();

        $andamentosRecentes = [];
        $qAtivos = DB::table('processos')
            ->where('cliente_datajuri_id', $djId)
            ->where('status', '!=', 'Encerrado');
        if ($processoPasta) {
            $qAtivos->where('pasta', $processoPasta);
        }
        $processosAtivos = $qAtivos->pluck('pasta')->toArray();

        $this->autenticarDataJuri();
        if ($this->dataJuriToken) {
            foreach (array_slice($processosAtivos, 0, 3) as $pasta) {
                $faseIds = $this->buscarFaseProcessoIds($pasta);
                foreach ($faseIds as $faseId) {
                    $ands = $this->buscarAndamentosPorFase($faseId, 5);
                    foreach ($ands as &$a) { $a['processo_pasta'] = $pasta; }
                    $andamentosRecentes = array_merge($andamentosRecentes, $ands);
                }
            }
            usort($andamentosRecentes, function ($a, $b) {
                return strcmp($b['data_raw'] ?? '1970-01-01', $a['data_raw'] ?? '1970-01-01');
            });
            $andamentosRecentes = array_slice($andamentosRecentes, 0, 15);
        }

        $compromissos = [];
        try {
            $compResult = $this->buscarCompromissosCliente($cliente);
            if (!empty($compResult['compromissos'])) {
                $compromissos = array_slice($compResult['compromissos'], 0, 5);
            }
        } catch (\Throwable $e) {
            Log::warning('ChatIA: erro compromissos', ['erro' => $e->getMessage()]);
        }

        $snapshot = [
            'cliente' => [
                'nome' => $nomeCliente,
                'cpf_cnpj' => $cliente->cpf_cnpj ?? $cliente->cpf ?? $cliente->cnpj ?? 'N/I',
            ],
            'processos' => array_map(fn($p) => (array)$p, $processos),
            'andamentos_recentes' => $andamentosRecentes,
            'compromissos_futuros' => $compromissos,
            'contas_receber' => array_map(fn($c) => (array)$c, $contas),
            'contratos' => array_map(fn($c) => (array)$c, $contratos),
        ];

        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if (mb_strlen($snapshotJson) > 12000) {
            $snapshotJson = mb_substr($snapshotJson, 0, 12000) . "\n... (dados truncados)";
        }

        $systemPrompt = "Você é LEXUS, assistente jurídico digital do escritório Mayer Advogados.\n\n"
            . "IDENTIDADE:\n"
            . "- Você é a ponte entre o escritório e o cliente via WhatsApp\n"
            . "- Tom: profissional, acolhedor e seguro — como um advogado explicando para seu cliente\n"
            . "- Nunca soe como chatbot genérico ou atendimento automático\n\n"
            . "REGRAS OBRIGATÓRIAS:\n"
            . "1. Responda EXCLUSIVAMENTE com base nos dados do CONTEXTO JSON abaixo\n"
            . "2. Traduza TODOS os termos jurídicos para linguagem acessível\n"
            . "3. NUNCA invente informações, números, datas ou valores\n"
            . "4. Se o dado não estiver no contexto: \"Essa informação não está disponível no momento. Posso encaminhar para o advogado responsável.\"\n"
            . "5. NÃO dê conselhos jurídicos — informe status e explique termos\n"
            . "6. NUNCA exponha IDs internos, datajuri_id ou campos técnicos\n"
            . "7. Use *negrito* para datas e valores importantes (formato WhatsApp)\n"
            . "8. Use no máximo 2 emojis estratégicos por resposta\n"
            . "9. Máximo 350 palavras\n"
            . "10. " . ($processoPasta ? "O cliente selecionou o processo pasta {$processoPasta}. Foque APENAS neste processo.\n" : "Se múltiplos processos, identifique qual. Se não for claro, liste e peça para especificar.\n")
            . "\nESTRUTURA DE RESPOSTA:\n"
            . "- Cumprimente pelo nome (breve, uma linha)\n"
            . "- Responda a pergunta de forma direta e objetiva\n"
            . "- Se relevante, explique o que isso significa na prática\n"
            . "- Finalize com: \"Posso ajudar com mais alguma dúvida?\"\n\n"
            . "CONTEXTO DO CLIENTE:\n" . $snapshotJson;

        try {
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key'         => config('services.anthropic.api_key'),
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model'      => config('services.anthropic.model', 'claude-sonnet-4-6'),
                'max_tokens' => 600,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => $pergunta],
                ],
            ]);

            if ($response->successful()) {
                $resposta = $response->json('content.0.text', '');
                if (!empty($resposta)) {
                    return [
                        'encontrado' => true,
                        'resposta' => trim($resposta),
                        'processos_count' => count($processos),
                        'nome_cliente' => $nomeCliente,
                        'pasta_selecionada' => $processoPasta,
                    ];
                }
            }
            Log::error('ChatIA: Claude resposta inválida', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (\Throwable $e) {
            Log::error('ChatIA: erro Claude', ['erro' => $e->getMessage()]);
        }

        return [
            'encontrado' => false,
            'resposta' => "Desculpe, {$nomeCliente}, não consegui processar sua pergunta neste momento. Vou encaminhar para o advogado responsável.",
            'processos_count' => count($processos),
            'nome_cliente' => $nomeCliente,
        ];
    }

    // =====================================================
    // 8. SOLICITAR DOCUMENTO (FASE 2)
    // =====================================================

    public function solicitarDocumento(string $telefone, string $tipoDocumento, ?string $observacao = null): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);
        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) { return $cliente; }

        $tiposValidos = ['contrato', 'procuracao', 'certidao', 'declaracao', 'outro'];
        $tipoLimpo = strtolower(trim($tipoDocumento));
        if (!in_array($tipoLimpo, $tiposValidos)) { $tipoLimpo = 'outro'; }

        $tipoLabels = [
            'contrato' => 'Contrato', 'procuracao' => 'Procuração',
            'certidao' => 'Certidão / Declaração', 'declaracao' => 'Certidão / Declaração',
            'outro' => 'Outro documento',
        ];
        $tipoLabel = $tipoLabels[$tipoLimpo];

        $crmAccountId = $this->resolverCrmAccountId($cliente->datajuri_id, $telefoneNormalizado);

        $sr = CrmServiceRequest::create([
            'protocolo'            => $this->gerarProtocoloAutoatendimento('DOC'),
            'account_id'           => $crmAccountId,
            'phone_contato'        => $telefoneNormalizado,
            'category'             => 'cliente_documento',
            'origem'               => 'autoatendimento',
            'subject'              => "Solicitação de documento: {$tipoLabel}",
            'description'          => "Tipo: {$tipoLabel}\nObservação: " . ($observacao ?? 'Nenhuma'),
            'priority'             => 'normal',
            'status'               => 'aberto',
            'requested_by_user_id' => null,
            'ai_triage'            => ['nome_cliente' => $cliente->nome, 'tipo_documento' => $tipoLimpo],
        ]);

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);
        $this->logarAcao($telefoneNormalizado, 'solicitar_documento', ['tipo' => $tipoLimpo, 'protocolo' => $sr->protocolo], null, $tempoMs);

        return [
            'sucesso' => true, 'protocolo' => $sr->protocolo,
            'mensagem' => "Solicitação registrada com sucesso!\n\n📋 Protocolo: {$sr->protocolo}\n📄 Documento: {$tipoLabel}\n\nNossa equipe providenciará o documento e entrará em contato em até 24 horas úteis.",
            'nome_cliente' => $cliente->nome,
        ];
    }

    // =====================================================
    // 9. ENVIAR DOCUMENTO AO ESCRITÓRIO (FASE 2)
    // =====================================================

    public function enviarDocumento(string $telefone, ?string $urlArquivo = null, ?string $observacao = null): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);
        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) { return $cliente; }

        $descricao = "Documento enviado pelo cliente via WhatsApp";
        if ($urlArquivo) { $descricao .= "\nArquivo: {$urlArquivo}"; }
        if ($observacao) { $descricao .= "\nObservação: {$observacao}"; }

        $crmAccountId = $this->resolverCrmAccountId($cliente->datajuri_id, $telefoneNormalizado);

        $sr = CrmServiceRequest::create([
            'protocolo'            => $this->gerarProtocoloAutoatendimento('ENV'),
            'account_id'           => $crmAccountId,
            'phone_contato'        => $telefoneNormalizado,
            'category'             => 'cliente_documento_envio',
            'origem'               => 'autoatendimento',
            'subject'              => 'Documento recebido do cliente',
            'description'          => $descricao,
            'priority'             => 'normal',
            'status'               => 'aberto',
            'requested_by_user_id' => null,
            'ai_triage'            => ['nome_cliente' => $cliente->nome, 'tem_arquivo' => !empty($urlArquivo)],
        ]);

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);
        $this->logarAcao($telefoneNormalizado, 'enviar_documento', ['protocolo' => $sr->protocolo, 'tem_arquivo' => !empty($urlArquivo)], null, $tempoMs);

        return [
            'sucesso' => true, 'protocolo' => $sr->protocolo,
            'mensagem' => "Documento recebido com sucesso!\n\n📋 Protocolo: {$sr->protocolo}\n\nNossa equipe analisará o documento e entrará em contato caso necessite de informações adicionais.",
            'nome_cliente' => $cliente->nome,
        ];
    }

    // =====================================================
    // 10. SOLICITAR AGENDAMENTO (FASE 2)
    // =====================================================

    public function solicitarAgendamento(string $telefone, string $motivo, string $urgencia = 'normal', string $preferencia = 'sem_preferencia', ?string $observacao = null): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);
        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) { return $cliente; }

        $motivosValidos = [
            'reuniao_advogado' => 'Reunião com advogado', 'assinatura' => 'Assinatura de documento',
            'esclarecimento' => 'Esclarecimento sobre processo', 'outro' => 'Outro assunto',
        ];
        $motivoLimpo = strtolower(trim($motivo));
        $motivoLabel = $motivosValidos[$motivoLimpo] ?? $motivosValidos['outro'];

        $urgenciaLabel = ($urgencia === 'urgente') ? '🔴 Urgente (até 48h)' : '🟢 Normal (até 5 dias úteis)';

        $prefLabels = ['manha' => '🌅 Manhã (8h-12h)', 'tarde' => '🌆 Tarde (13h-18h)', 'sem_preferencia' => '🕐 Sem preferência'];
        $preferenciaLabel = $prefLabels[$preferencia] ?? $prefLabels['sem_preferencia'];

        $descricao = "Motivo: {$motivoLabel}\nUrgência: {$urgenciaLabel}\nPreferência: {$preferenciaLabel}";
        if ($observacao && !in_array(strtolower(trim($observacao)), ['não', 'nao', ''])) {
            $descricao .= "\nObservação: {$observacao}";
        }

        $prioridade = $urgencia === 'urgente' ? 'alta' : 'normal';
        $crmAccountId = $this->resolverCrmAccountId($cliente->datajuri_id, $telefoneNormalizado);

        $sr = CrmServiceRequest::create([
            'protocolo'            => $this->gerarProtocoloAutoatendimento('AGD'),
            'account_id'           => $crmAccountId,
            'phone_contato'        => $telefoneNormalizado,
            'category'             => 'cliente_agendamento',
            'origem'               => 'autoatendimento',
            'subject'              => "Agendamento: {$motivoLabel}",
            'description'          => $descricao,
            'priority'             => $prioridade,
            'status'               => 'aberto',
            'requested_by_user_id' => null,
            'ai_triage'            => ['nome_cliente' => $cliente->nome, 'motivo' => $motivoLimpo, 'urgencia' => $urgencia],
        ]);

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);
        $this->logarAcao($telefoneNormalizado, 'solicitar_agendamento', [
            'motivo' => $motivoLimpo, 'urgencia' => $urgencia, 'protocolo' => $sr->protocolo,
        ], null, $tempoMs);

        return [
            'sucesso' => true, 'protocolo' => $sr->protocolo,
            'mensagem' => "Solicitação de agendamento registrada!\n\n📋 Protocolo: {$sr->protocolo}\n📌 Motivo: {$motivoLabel}\n⏰ Urgência: {$urgenciaLabel}\n🕐 Preferência: {$preferenciaLabel}\n\nNossa equipe entrará em contato para confirmar a melhor data e horário.",
            'nome_cliente' => $cliente->nome,
        ];
    }

    // =====================================================
    // V2 — TOKEN PÚBLICO + TEASER
    // =====================================================

    public function gerarTokenPublico(string $tipo, ?int $clienteId, string $telefone, array $payload): NexoPublicToken
    {
        return NexoPublicToken::create([
            'token'      => (string) Str::uuid(),
            'tipo'       => $tipo,
            'cliente_id' => $clienteId,
            'telefone'   => $telefone,
            'payload'    => $payload,
            'expires_at' => now()->addHours(6),
        ]);
    }

    private function gerarTeaser(string $tipo, array $resultado): string
    {
        return match($tipo) {
            'financeiro' => sprintf(
                'Você tem %d título(s) em aberto totalizando %s. Toque no link para ver o detalhamento completo.',
                $resultado['total'] ?? 0,
                $resultado['valor_total'] ?? 'R$ 0,00'
            ),
            'compromissos' => sprintf(
                'Encontrei %d compromisso(s) nos seus processos. Toque para ver datas, horários e detalhes.',
                $resultado['total'] ?? 0
            ),
            'processo-judicial' => 'Confira o status atualizado do seu processo e a linha do tempo de andamentos. Toque no link para ver.',
            'tickets' => sprintf(
                'Você tem %d solicitação(ões) registrada(s). Toque para acompanhar o status de cada uma.',
                $resultado['total'] ?? 0
            ),
            'processo-admin' => 'Acompanhe o andamento do seu processo administrativo com todas as etapas e documentos. Toque no link.',
            default => 'Toque no link para ver os detalhes completos.',
        };
    }

    // =====================================================
    // V2 — PROCESSOS ADMINISTRATIVOS
    // =====================================================

    public function processosAdministrativos(string $telefone): array
    {
        $inicio = microtime(true);
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        $cliente = $this->obterClienteAutenticado($telefoneNormalizado);
        if (isset($cliente['erro'])) {
            return $cliente;
        }

        // Localizar conta CRM
        $crmAccount = CrmAccount::where('datajuri_pessoa_id', $cliente->datajuri_id)
            ->orWhere('phone_e164', $telefoneNormalizado)
            ->first();

        if (!$crmAccount && $cliente->datajuri_id) {
            // Tentativa com últimos 8 dígitos
            $parcial = substr($telefoneNormalizado, -8);
            $crmAccount = CrmAccount::where('phone_e164', 'LIKE', '%' . $parcial)
                ->where('lifecycle', '!=', 'arquivado')
                ->first();
        }

        if (!$crmAccount) {
            return [
                'encontrado' => false,
                'total' => 0,
                'mensagem' => "Olá {$cliente->nome}! Não encontrei processos administrativos vinculados ao seu cadastro.",
            ];
        }

        $processos = CrmAdminProcess::where('account_id', $crmAccount->id)
            ->whereNotIn('status', ['rascunho', 'cancelado'])
            ->orderByDesc('created_at')
            ->get();

        if ($processos->isEmpty()) {
            return [
                'encontrado' => false,
                'total' => 0,
                'mensagem' => "Olá {$cliente->nome}! Não há processos administrativos ativos para o seu cadastro.",
            ];
        }

        $lista = $processos->map(function ($p) use ($telefoneNormalizado, $cliente) {
            $token = $this->gerarTokenPublico('processo-admin', $cliente->id, $telefoneNormalizado, [
                'processo_id' => $p->id,
                'protocolo'   => $p->protocolo,
            ]);
            return [
                'protocolo'   => $p->protocolo,
                'titulo'      => $p->titulo,
                'tipo'        => $p->tipoLabel(),
                'status'      => $p->statusLabel(),
                'link_visual' => $token->getUrl(),
                'teaser'      => $this->gerarTeaser('processo-admin', []),
            ];
        })->toArray();

        $total = count($lista);
        $resultado = [
            'encontrado' => true,
            'total'      => $total,
            'mensagem'   => "Olá {$cliente->nome}! Encontrei {$total} processo(s) administrativo(s):",
            'processos'  => $lista,
        ];

        // Flat para variáveis SendPulse (máximo 3)
        foreach (array_slice($lista, 0, 3) as $i => $p) {
            $n = $i + 1;
            $resultado["proc{$n}_protocolo"] = $p['protocolo'];
            $resultado["proc{$n}_titulo"]    = $p['titulo'];
            $resultado["proc{$n}_status"]    = $p['status'];
            $resultado["proc{$n}_link"]      = $p['link_visual'];
        }

        $tempoMs = (int)((microtime(true) - $inicio) * 1000);
        $this->logarAcao($telefoneNormalizado, 'consulta_admin_processos', [
            'cliente_id' => $cliente->id,
            'total'      => $total,
        ], null, $tempoMs);

        return $resultado;
    }

    // =====================================================
    // ATIVIDADES DataJuri — trabalho realizado no processo
    // =====================================================

    private function buscarAtividadesProcesso(int $processoId, ?int $clienteDatajuriId): array
    {
        // Atividades concluídas neste processo
        $rowsConcluidas = DB::table('atividades')
            ->where('processo_id', $processoId)
            ->where('status', 'Concluído')
            ->orderByDesc('data_conclusao')
            ->get(['status', 'responsavel_nome', 'data_conclusao', 'payload_raw']);

        $concluidas = [];
        $totalMinutos = 0;

        foreach ($rowsConcluidas as $a) {
            $p = json_decode($a->payload_raw ?? '', true) ?? [];
            $duracao = (int)($p['duracao'] ?? 0);
            $totalMinutos += $duracao;

            $concluidas[] = [
                'data'         => $p['data'] ?? '',
                'duracao_min'  => $duracao,
                'duracao_fmt'  => $duracao >= 60
                    ? round($duracao / 60, 1) . 'h'
                    : ($duracao > 0 ? $duracao . 'min' : '—'),
                'responsavel'  => $p['proprietario.nome'] ?? $a->responsavel_nome ?? '',
            ];
        }

        // Atividades pendentes neste processo
        $rowsPendentes = DB::table('atividades')
            ->where('processo_id', $processoId)
            ->whereIn('status', ['Em andamento', 'Não iniciado'])
            ->orderByDesc('data_vencimento')
            ->limit(5)
            ->get(['status', 'responsavel_nome', 'data_vencimento', 'payload_raw']);

        $pendentes = [];
        foreach ($rowsPendentes as $a) {
            $p = json_decode($a->payload_raw ?? '', true) ?? [];
            $pendentes[] = [
                'status'      => $a->status,
                'data'        => $p['data'] ?? '',
                'responsavel' => $p['proprietario.nome'] ?? $a->responsavel_nome ?? '',
            ];
        }

        // Portfólio: total e ativos do mesmo cliente (via datajuri_id)
        $portfolioTotal  = 0;
        $portfolioAtivos = 0;
        if ($clienteDatajuriId) {
            $portfolioTotal  = DB::table('processos')->where('cliente_datajuri_id', $clienteDatajuriId)->count();
            $portfolioAtivos = DB::table('processos')->where('cliente_datajuri_id', $clienteDatajuriId)
                ->where('status', '!=', 'Encerrado')->count();
        }

        return [
            'concluidas'       => $concluidas,
            'pendentes'        => $pendentes,
            'total_minutos'    => $totalMinutos,
            'portfolio_total'  => $portfolioTotal,
            'portfolio_ativos' => $portfolioAtivos,
        ];
    }

    // =====================================================
    // DATAJURI API — reutilização do padrão existente
    // =====================================================

    private function autenticarDataJuri(): void
    {
        $this->dataJuriToken = Cache::remember('datajuri_token_nexo', 3500, function () {
            $clientId = config('services.datajuri.client_id');
            $secretId = config('services.datajuri.secret_id');
            $email    = config('services.datajuri.email');
            $password = config('services.datajuri.password');

            if (!$clientId || !$secretId || !$email || !$password) {
                Log::error('NEXO-Autoatendimento: Credenciais DataJuri incompletas');
                return null;
            }

            $credentials = base64_encode("{$clientId}:{$secretId}");

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => "{$this->dataJuriBaseUrl}/oauth/token",
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'grant_type' => 'password',
                    'username'   => $email,
                    'password'   => $password,
                ]),
                CURLOPT_HTTPHEADER => [
                    "Authorization: Basic {$credentials}",
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $data = json_decode($response, true);
                return $data['access_token'] ?? null;
            }

            Log::error('NEXO-Autoatendimento: Falha token DataJuri', ['http_code' => $httpCode]);
            return null;
        });
    }

    private function dataJuriRequest(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->dataJuriToken}",
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error('NEXO-Autoatendimento: cURL error', ['erro' => $curlError]);
            return null;
        }

        if ($httpCode === 401) {
            Cache::forget('datajuri_token_nexo');
            $this->autenticarDataJuri();
            if ($this->dataJuriToken) {
                return $this->dataJuriRequestRetry($url);
            }
            return null;
        }

        if ($httpCode !== 200) {
            Log::error('NEXO-Autoatendimento: HTTP error DataJuri', ['http_code' => $httpCode]);
            return null;
        }

        $data = json_decode($response, true);
        return json_last_error() === JSON_ERROR_NONE ? $data : null;
    }

    private function dataJuriRequestRetry(string $url): ?array
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$this->dataJuriToken}",
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }

    private function buscarFaseProcessoIds(string $numeroPasta): array
    {
        $url = $this->dataJuriBaseUrl . '/v1/entidades/FaseProcesso?' . http_build_query([
            'offset' => 0,
            'limit' => 20,
            'campos' => 'id,processo.pasta',
            'criterio' => 'processo.pasta | igual a | ' . $numeroPasta,
        ]);

        $response = $this->dataJuriRequest($url);

        if (!$response || empty($response['rows'])) {
            return [];
        }

        return array_map(function ($row) {
            return (string)intval($row['id']);
        }, $response['rows']);
    }

    private function buscarAndamentosPorFase(string $faseProcessoId, int $limit = 20): array
    {
        $url = $this->dataJuriBaseUrl . '/v1/entidades/AndamentoFase?' . http_build_query([
            'offset' => 0,
            'limit' => $limit,
            'campos' => 'id,descricao,data,hora,observacao,descricaoOriginal,faseProcesso.processo.pasta,faseProcesso.processo.cliente.nome,faseProcesso.processo.adverso.nome,faseProcesso.processo.proprietario.nome',
            'criterio' => 'faseProcessoId | igual a | ' . $faseProcessoId,
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
    // UTILITÁRIOS
    // =====================================================

    private function obterClienteAutenticado(string $telefoneNormalizado)
    {
        // Verificar sessão ativa em nexo_auth_attempts (tabela do fluxo de auth)
        $auth = DB::table('nexo_auth_attempts')
            ->where('telefone', $telefoneNormalizado)
            ->first();

        if (!$auth) {
            return ['erro' => 'Cliente não autenticado. Por favor, faça a verificação de identidade primeiro.'];
        }

        // Verificar bloqueio
        if ($auth->bloqueado && $auth->bloqueado_ate && \Carbon\Carbon::parse($auth->bloqueado_ate)->isFuture()) {
            return ['erro' => 'Acesso temporariamente bloqueado. Tente novamente em alguns minutos.'];
        }

        // Verificar se sessão está ativa (autenticado_ate no futuro)
        if (!$auth->autenticado_ate || \Carbon\Carbon::parse($auth->autenticado_ate)->isPast()) {
            return ['erro' => 'Sua sessão de segurança expirou (30 minutos). Para continuar, digite *oi* e faça a verificação novamente. É rápido! 🔐'];
        }

        // Buscar cliente por telefone na tabela clientes
        $viaFallback = false;

        $cliente = Cliente::where('telefone_normalizado', $telefoneNormalizado)->first();

        if (!$cliente) {
            // Últimos 10 dígitos — busca parcial para cobrir variações de DDI
            $ultimos10 = substr($telefoneNormalizado, -10);
            $cliente = Cliente::where('telefone_normalizado', 'LIKE', '%' . $ultimos10)->first();
            if ($cliente) $viaFallback = true;
        }

        // Fallback: celular (campo raw, com máscara)
        // Testa os últimos 8 dígitos para cobrir a transição 8→9 dígitos
        if (!$cliente) {
            $ultimos8 = substr($telefoneNormalizado, -8);
            $cliente = Cliente::whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(celular,'(',''),')',''),'-',''),' ','') LIKE ?",
                ['%' . $ultimos8]
            )->first();
            if ($cliente) $viaFallback = true;
        }

        // Fallback: telefone principal (campo raw)
        if (!$cliente) {
            $cliente = Cliente::whereRaw(
                "REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ','') LIKE ?",
                ['%' . $ultimos8]
            )->first();
            if ($cliente) $viaFallback = true;
        }

        // Fallback: wa_conversations vinculada (mesma lógica do NexoConsultaService)
        if (!$cliente) {
            $conv = \DB::table('wa_conversations')
                ->where('phone', $telefoneNormalizado)
                ->whereNotNull('linked_cliente_id')
                ->orderByDesc('updated_at')
                ->first();
            if ($conv) {
                $cliente = Cliente::find($conv->linked_cliente_id);
                if ($cliente) {
                    $viaFallback = true;
                    \Log::info('Nexo: cliente encontrado via wa_conversations', [
                        'telefone' => $telefoneNormalizado,
                        'conv_id'  => $conv->id,
                        'cliente_id' => $cliente->id,
                    ]);
                }
            }
        }

        // Fallback: CPF/CNPJ via variável do SendPulse
        if (!$cliente) {
            try {
                $sp = app(\App\Services\SendPulseWhatsAppService::class);
                $contact = $sp->getContactByPhone($telefoneNormalizado);
                $contactId = $contact['id'] ?? null;
                if ($contactId) {
                    $info = $sp->getContactInfo($contactId);
                    $vars = $info['data']['variables'] ?? $info['variables'] ?? [];

                    $cpfInformado = $vars['cpf'] ?? $vars['CPF'] ?? $vars['cpf_cnpj'] ?? $vars['documento'] ?? '';
                    if (!empty($cpfInformado)) {
                        $cpfLimpo = preg_replace('/\D/', '', $cpfInformado);
                        $cliente = Cliente::where(function ($q) use ($cpfLimpo) {
                            $q->where('cpf', $cpfLimpo)
                              ->orWhere('cnpj', $cpfLimpo)
                              ->orWhere('cpf_cnpj', $cpfLimpo)
                              ->orWhere('cpf_cnpj', 'LIKE', '%' . $cpfLimpo . '%');
                        })->first();
                        if ($cliente) {
                            $viaFallback = true;
                            \Log::info('Nexo fallback: cliente encontrado por CPF/CNPJ', ['cpf' => $cpfLimpo, 'cliente_id' => $cliente->id]);
                        }
                    }

                    if (!$cliente) {
                        $nomeInformado = $vars['nomecompleto'] ?? $vars['Nomecompleto'] ?? $vars['nome'] ?? '';
                        if (!empty($nomeInformado) && strlen($nomeInformado) >= 5) {
                            $cliente = Cliente::where('nome', 'LIKE', '%' . trim($nomeInformado) . '%')->first();
                            if ($cliente) {
                                $viaFallback = true;
                                \Log::info('Nexo fallback: cliente encontrado por nome', ['nome' => $nomeInformado, 'cliente_id' => $cliente->id]);
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Nexo fallback: erro ao buscar dados SendPulse', ['error' => $e->getMessage()]);
            }
        }

        if (!$cliente) {
            \Log::info('Nexo: cliente nao encontrado por nenhum metodo', ['telefone' => $telefoneNormalizado]);
            return ['erro' => 'Nao conseguimos localizar seu cadastro. Por favor, entre em contato com nosso escritorio pelo telefone (47) 3349-7979 para atualizar seus dados.'];
        }

        // Quando encontrado via fallback com número diferente do cadastrado:
        // atualiza o celular para garantir que próximas buscas funcionem direto.
        if ($viaFallback && $cliente->telefone_normalizado !== $telefoneNormalizado) {
            $ddd    = substr($telefoneNormalizado, 2, 2);
            $numero = substr($telefoneNormalizado, 4);
            $celFormatado = strlen($numero) === 9
                ? "({$ddd}) " . substr($numero, 0, 5) . '-' . substr($numero, 5)
                : "({$ddd}) " . substr($numero, 0, 4) . '-' . substr($numero, 4);

            \DB::table('clientes')->where('id', $cliente->id)
                ->update(['celular' => $celFormatado, 'updated_at' => now()]);

            \Log::info('Nexo: celular do cliente atualizado via fallback WA', [
                'cliente_id' => $cliente->id,
                'celular_novo' => $celFormatado,
            ]);

            $cliente->celular = $celFormatado;
        }

        return $cliente;
    }

    private function normalizarTelefone(string $telefone): string
    {
        return \App\Helpers\PhoneHelper::normalize($telefone) ?? preg_replace('/\D/', '', $telefone);
    }

    /**
     * Resolve o crm_accounts.id a partir do datajuri_id ou telefone.
     * Usado para vincular tickets de autoatendimento à conta CRM correta.
     */
    /**
     * Gera protocolo sequencial diário para tickets de autoatendimento (WhatsApp).
     * Formato: PREFIX-YYYYMMDD-NNN (ex: TK-20260416-001)
     * TK: conta de nexo_tickets; outros prefixos (DOC/ENV/AGD): contam de crm_service_requests.
     */
    private function gerarProtocoloAutoatendimento(string $prefix): string
    {
        $hoje = date('Ymd');
        $padrao = $prefix . '-' . $hoje . '-%';
        $count = $prefix === 'TK'
            ? NexoTicket::where('protocolo', 'LIKE', $padrao)->count()
            : CrmServiceRequest::where('protocolo', 'LIKE', $padrao)->count();
        return $prefix . '-' . $hoje . '-' . str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }

    private function resolverCrmAccountId(?int $datajuriId, string $telefone): ?int
    {
        if ($datajuriId) {
            $id = DB::table('crm_accounts')
                ->where('datajuri_pessoa_id', $datajuriId)
                ->value('id');
            if ($id) return (int) $id;
        }

        // Fallback via phone (últimos 8 dígitos)
        $parcial = substr(preg_replace('/\D/', '', $telefone), -8);
        $id = DB::table('crm_accounts')
            ->where('phone_e164', 'LIKE', '%' . $parcial)
            ->where('lifecycle', '!=', 'arquivado')
            ->value('id');

        return $id ? (int) $id : null;
    }

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

    private function parseValorBR(string $valor): float
    {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
        return (float)$valor;
    }

    private function formatarDataBRparaExibicao(string $dataBR): string
    {
        // Data pode vir DD/MM/YYYY ou YYYY-MM-DD
        if (strpos($dataBR, '/') !== false) {
            return $dataBR; // Já está em formato BR
        }

        try {
            return \Carbon\Carbon::parse($dataBR)->format('d/m/Y');
        } catch (\Exception $e) {
            return $dataBR;
        }
    }

    private function dataPassada(string $dataBR): bool
    {
        $dataISO = $this->converterDataBR($dataBR);
        if ($dataISO === '1970-01-01') {
            return false;
        }

        try {
            return \Carbon\Carbon::parse($dataISO)->isPast();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function logarAcao(string $telefone, string $acao, array $dados = [], ?string $respostaIA = null, ?int $tempoMs = null): void
    {
        NexoAutomationLog::create([
            'telefone' => $telefone,
            'acao' => $acao,
            'dados' => $dados,
            'resposta_ia' => $respostaIA,
            'tempo_resposta_ms' => $tempoMs,
        ]);
    }
    // =====================================================
    // RESUMIR CONTEXTO PARA TICKET (IA)
    // =====================================================

    public function resumirContexto(string $telefone, ?string $contexto = null): array
    {
        $telefoneNormalizado = $this->normalizarTelefone($telefone);

        // Se contexto fornecido diretamente pelo SendPulse, usar sem buscar no banco
        if (!empty($contexto) && mb_strlen(trim($contexto)) >= 5) {
            $contextoTexto = trim($contexto);
            \Illuminate\Support\Facades\Log::info('NEXO-TICKET: usando contexto direto do SendPulse', [
                'telefone' => $telefoneNormalizado,
                'contexto_len' => mb_strlen($contextoTexto),
            ]);
        } else {
            // Fallback: buscar mensagens recentes da conversa no banco
            $conversa = \App\Models\WaConversation::where('phone', $telefoneNormalizado)->first();
            if (!$conversa) {
                $conversa = \App\Models\WaConversation::where('phone', '+' . $telefoneNormalizado)->first();
            }

            if (!$conversa) {
                return [
                    'sucesso' => true,
                    'ticket_resumo' => 'Cliente entrou em contato via WhatsApp.',
                    'fonte' => 'padrao',
                ];
            }

            $mensagens = \App\Models\WaMessage::where('conversation_id', $conversa->id)
                ->where('direction', 1)
                ->whereNotNull('body')
                ->where('body', '!=', '')
                ->where('body', 'NOT LIKE', '/start%')
                ->where('body', 'NOT LIKE', '/stop%')
                ->orderByDesc('sent_at')
                ->limit(10)
                ->get(['body', 'sent_at']);

            if ($mensagens->isEmpty()) {
                return [
                    'sucesso' => true,
                    'ticket_resumo' => 'Cliente entrou em contato via WhatsApp.',
                    'fonte' => 'padrao',
                ];
            }

            $contextoTexto = $mensagens->reverse()->map(function ($m) {
                return '[' . $m->sent_at->format('H:i') . '] ' . mb_substr($m->body, 0, 300);
            })->implode("\n");
        }

        try {
            $resumo = $this->openAIService->resumirContexto($contextoTexto);

            \Illuminate\Support\Facades\Log::info('NEXO-TICKET: contexto resumido', [
                'telefone' => $telefoneNormalizado,
                'msgs'     => isset($mensagens) ? $mensagens->count() : 0,
                'resumo'   => $resumo,
            ]);

            return [
                'sucesso'      => true,
                'ticket_resumo' => $resumo,
                'fonte'        => 'ia',
            ];
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('NEXO-TICKET: erro ao resumir', [
                'msg' => $e->getMessage(),
            ]);
        }

        // Fallback: usar contexto direto ou ultima mensagem do banco
        if (!empty($contextoTexto)) {
            $fallback = mb_substr($contextoTexto, 0, 200);
        } elseif (isset($mensagens) && $mensagens->isNotEmpty()) {
            $fallback = mb_substr($mensagens->first()->body, 0, 200);
        } else {
            $fallback = 'Cliente entrou em contato via WhatsApp.';
        }

        return [
            'sucesso' => true,
            'ticket_resumo' => $fallback,
            'fonte' => 'fallback',
        ];
    }

    /**
     * Envia notificação bell + email para o advogado responsável
     * quando um ticket de autoatendimento (WhatsApp) é criado.
     */
    private function notificarResponsavelTicket(
        NexoTicket $ticket,
        ?string $nomeCliente,
        ?string $telefone
    ): void {
        $responsavel = $ticket->responsavel;
        if (!$responsavel) return;

        $protocolo  = $ticket->protocolo ?? "#{$ticket->id}";
        $cliente    = $nomeCliente ?? 'Cliente não identificado';
        $linkTicket = route('nexo.tickets') . '?busca=' . urlencode($protocolo) . '&abrir=' . $ticket->id;

        NotificationIntranet::enviar(
            userId: $responsavel->id,
            titulo: "🎫 Novo ticket: {$protocolo}",
            mensagem: "{$cliente} — {$ticket->assunto}",
            link: $linkTicket,
            icone: 'ticket'
        );

        try {
            if ($responsavel->email) {
                Mail::to($responsavel->email)->send(new NexoTicketAtribuido($responsavel, [
                    'protocolo'    => $protocolo,
                    'assunto'      => $ticket->assunto,
                    'nome_cliente' => $nomeCliente,
                    'telefone'     => $telefone,
                    'tipo'         => null,
                    'prioridade'   => $ticket->prioridade ?? 'normal',
                    'mensagem'     => $ticket->mensagem,
                    'link'         => $linkTicket,
                ]));
            }
        } catch (\Exception $e) {
            Log::warning('NEXO-AUTOATENDIMENTO: Falha ao enviar email de atribuição', [
                'ticket_id'  => $ticket->id,
                'erro'       => $e->getMessage(),
            ]);
        }
    }

}
