<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

/**
 * DataJuriSyncService - Versão 3.0 com Auditoria
 *
 * NOVIDADES:
 * - Detecção de alterações via hash de conteúdo
 * - Detecção de exclusões (registros que sumiram da API)
 * - Auditoria completa de mudanças
 * - Marcação de sync_status para filtros
 *
 * @version 3.0 - Com Auditoria
 * @date 2026-02-05
 */
class DataJuriSyncService
{
    private ?string $token = null;
    private string $baseUrl = 'https://api.datajuri.com.br';
    private ?string $currentSyncId = null;

    // Credenciais OAuth2
    private string $clientId;
    private string $secretId;
    private string $username;
    private string $password;

    // Contadores para relatório
    private array $stats = [
        'inseridos' => 0,
        'atualizados' => 0,
        'removidos' => 0,
        'inalterados' => 0,
    ];

    public function __construct()
    {
        $this->clientId = config('services.datajuri.client_id', env('DATAJURI_CLIENT_ID'));
        $this->secretId = config('services.datajuri.secret_id', env('DATAJURI_SECRET_ID'));
        $this->username = config('services.datajuri.username', env('DATAJURI_USERNAME'));
        $this->password = config('services.datajuri.password', env('DATAJURI_PASSWORD'));
    }

    /**
     * Define o ID da sync atual (para rastreabilidade)
     */
    public function setSyncId(string $syncId): void
    {
        $this->currentSyncId = $syncId;
    }

    /**
     * Retorna estatísticas da última sync
     */
    public function getStats(): array
    {
        return $this->stats;
    }

    /**
     * Reseta contadores
     */
    private function resetStats(): void
    {
        $this->stats = [
            'inseridos' => 0,
            'atualizados' => 0,
            'removidos' => 0,
            'inalterados' => 0,
        ];
    }

    // =========================================================================
    // AUTENTICAÇÃO
    // =========================================================================

    public function authenticate(): bool
    {
        try {
            $credentials = base64_encode("{$this->clientId}:{$this->secretId}");

            $response = Http::asForm()->withHeaders([
                'Authorization' => "Basic {$credentials}"
            ])->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $this->token = $response->json()['access_token'];
                return true;
            }

            Log::error('DataJuri Auth Failed', ['response' => $response->json()]);
            return false;
        } catch (\Exception $e) {
            Log::error('DataJuri Auth Exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => "Bearer {$this->token}",
            'Content-Type' => 'application/json'
        ];
    }

    // =========================================================================
    // BUSCA GENÉRICA PAGINADA
    // =========================================================================

    private function fetchAllPages(string $modulo, string $campos, int $pageSize = 100): array
    {
        $allRows = [];
        $page = 1;
        $hasMore = true;

        while ($hasMore) {
            $response = Http::withHeaders($this->getHeaders())
                ->timeout(120)
                ->get("{$this->baseUrl}/v1/entidades/{$modulo}", [
                    'page' => $page,
                    'pageSize' => $pageSize,
                    'campos' => $campos
                ]);

            if (!$response->successful()) {
                Log::error("DataJuri Fetch Failed: {$modulo}", ['page' => $page, 'error' => $response->body()]);
                break;
            }

            $data = $response->json();
            $rows = $data['rows'] ?? [];
            $allRows = array_merge($allRows, $rows);

            $listSize = $data['listSize'] ?? 0;
            $hasMore = ($page * $pageSize) < $listSize;
            $page++;

            // Safety limit
            if ($page > 500) {
                Log::warning("DataJuri Safety Limit: {$modulo}", ['pages' => $page]);
                break;
            }
        }

        return $allRows;
    }

    // =========================================================================
    // FUNÇÕES DE HASH E AUDITORIA
    // =========================================================================

    /**
     * Gera hash dos campos críticos para detectar alterações
     */
    private function gerarHashMovimento(array $dados): string
    {
        // Campos que importam para detectar mudança
        $camposCriticos = [
            'valor' => $dados['valor'] ?? 0,
            'data' => $dados['data'] ?? '',
            'plano_contas' => $dados['plano_contas'] ?? '',
            'codigo_plano' => $dados['codigo_plano'] ?? '',
            'descricao' => $dados['descricao'] ?? '',
            'conciliado' => $dados['conciliado'] ?? false,
        ];
        
        return hash('sha256', json_encode($camposCriticos));
    }

    /**
     * Registra alteração na tabela de auditoria
     */
    private function registrarAuditoria(
        string $tipoAlteracao,
        int $datajuriId,
        ?int $movimentoId = null,
        ?array $dadosAntes = null,
        ?array $dadosDepois = null
    ): void {
        try {
            DB::table('movimentos_audit')->insert([
                'movimento_id' => $movimentoId,
                'datajuri_id' => $datajuriId,
                'tipo_alteracao' => $tipoAlteracao,
                'dados_antes' => $dadosAntes ? json_encode($dadosAntes) : null,
                'dados_depois' => $dadosDepois ? json_encode($dadosDepois) : null,
                'valor_antes' => $dadosAntes['valor'] ?? null,
                'valor_depois' => $dadosDepois['valor'] ?? null,
                'plano_antes' => $dadosAntes['codigo_plano'] ?? null,
                'plano_depois' => $dadosDepois['codigo_plano'] ?? null,
                'classificacao_antes' => $dadosAntes['classificacao'] ?? null,
                'classificacao_depois' => $dadosDepois['classificacao'] ?? null,
                'sync_run_id' => $this->currentSyncId,
                'detectado_em' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao registrar auditoria', [
                'tipo' => $tipoAlteracao,
                'datajuri_id' => $datajuriId,
                'error' => $e->getMessage()
            ]);
        }
    }

    // =========================================================================
    // SYNC: MOVIMENTO → MOVIMENTOS (COM AUDITORIA)
    // =========================================================================

    public function syncMovimentos(): array
    {
        $this->resetStats();
        
        $campos = implode(',', [
            'id', 'data', 'valorComSinal', 'descricao',
            'planoConta.nomeCompleto', 'planoContaId',
            'pessoaId', 'contratoId', 'processo.pasta',
            'proprietario.nome', 'proprietarioId', 'conciliado'
        ]);

        $rows = $this->fetchAllPages('Movimento', $campos);
        
        // Coletar todos os IDs que vieram da API
        $idsRecebidos = [];
        
        foreach ($rows as $mov) {
            $datajuriId = $mov['id'] ?? null;
            if (!$datajuriId) continue;
            
            $idsRecebidos[] = $datajuriId;
        }

        // FASE 1: Processar registros recebidos (inserir/atualizar)
        foreach ($rows as $mov) {
            try {
                $datajuriId = $mov['id'] ?? null;
                if (!$datajuriId) continue;

                $valor = $this->parseValorBrasileiro($mov['valorComSinal'] ?? 0);
                $planoContas = $mov['planoConta.nomeCompleto'] ?? '';
                $codigoPlano = $this->extrairCodigoPlano($planoContas);
                $dataMovimento = $this->parseDataBrasileira($mov['data'] ?? null);

                $dadosNovos = [
                    'datajuri_id' => $datajuriId,
                    'data' => $dataMovimento,
                    'valor' => $valor,
                    'descricao' => $mov['descricao'] ?? null,
                    'plano_contas' => $planoContas,
                    'codigo_plano' => $codigoPlano,
                    'plano_conta_id' => $mov['planoContaId'] ?? null,
                    'pessoa_id_datajuri' => $mov['pessoaId'] ?? null,
                    'contrato_id_datajuri' => $mov['contratoId'] ?? null,
                    'processo_pasta' => $mov['processo.pasta'] ?? null,
                    'proprietario_nome' => $mov['proprietario.nome'] ?? null,
                    'proprietario_id' => $mov['proprietarioId'] ?? null,
                    'conciliado' => ($mov['conciliado'] ?? 'Não') === 'Sim',
                    'ano' => $dataMovimento ? (int) date('Y', strtotime($dataMovimento)) : null,
                    'mes' => $dataMovimento ? (int) date('n', strtotime($dataMovimento)) : null,
                    'classificacao' => $this->inferirClassificacao($codigoPlano, $valor),
                ];

                // Gerar hash do conteúdo
                $novoHash = $this->gerarHashMovimento($dadosNovos);

                // Verificar se já existe
                $existente = DB::table('movimentos')
                    ->where('datajuri_id', $datajuriId)
                    ->first();

                if ($existente) {
                    // EXISTE - verificar se mudou
                    $hashAntigo = $existente->payload_hash;
                    
                    if ($hashAntigo !== $novoHash) {
                        // MUDOU! Registrar auditoria e atualizar
                        $dadosAntes = [
                            'valor' => (float) $existente->valor,
                            'data' => $existente->data,
                            'plano_contas' => $existente->plano_contas,
                            'codigo_plano' => $existente->codigo_plano,
                            'classificacao' => $existente->classificacao,
                            'descricao' => $existente->descricao,
                        ];

                        $this->registrarAuditoria(
                            'alterado',
                            $datajuriId,
                            $existente->id,
                            $dadosAntes,
                            $dadosNovos
                        );

                        // Atualizar registro
                        DB::table('movimentos')
                            ->where('id', $existente->id)
                            ->update(array_merge($dadosNovos, [
                                'payload_hash' => $novoHash,
                                'sync_status' => 'alterado',
                                'ultima_sync_id' => $this->currentSyncId,
                                'updated_at' => now(),
                                'is_stale' => false,
                            ]));

                        $this->stats['atualizados']++;
                        
                        Log::info('Movimento ALTERADO detectado', [
                            'datajuri_id' => $datajuriId,
                            'valor_antes' => $existente->valor,
                            'valor_depois' => $valor,
                        ]);
                    } else {
                        // Não mudou, apenas atualizar sync_id
                        DB::table('movimentos')
                            ->where('id', $existente->id)
                            ->update([
                                'ultima_sync_id' => $this->currentSyncId,
                                'sync_status' => 'ativo',
                                'is_stale' => false,
                                'updated_at' => now(),
                            ]);
                        
                        $this->stats['inalterados']++;
                    }
                } else {
                    // NOVO - inserir
                    $novoId = DB::table('movimentos')->insertGetId(array_merge($dadosNovos, [
                        'origem' => 'datajuri',
                        'payload_hash' => $novoHash,
                        'sync_status' => 'novo',
                        'ultima_sync_id' => $this->currentSyncId,
                        'created_at' => now(),
                        'updated_at' => now(),
                        'is_stale' => false,
                    ]));

                    $this->registrarAuditoria(
                        'criado',
                        $datajuriId,
                        $novoId,
                        null,
                        $dadosNovos
                    );

                    $this->stats['inseridos']++;
                    
                    Log::info('Movimento NOVO inserido', [
                        'datajuri_id' => $datajuriId,
                        'valor' => $valor,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error('Sync Movimento Error', [
                    'id' => $mov['id'] ?? 'N/A', 
                    'error' => $e->getMessage()
                ]);
            }
        }

        // FASE 2: Detectar registros REMOVIDOS (estão no banco mas não vieram da API)
        if (!empty($idsRecebidos)) {
            $removidos = DB::table('movimentos')
                ->where('origem', 'datajuri')
                ->where('sync_status', '!=', 'removido')
                ->whereNotIn('datajuri_id', $idsRecebidos)
                ->get();

            foreach ($removidos as $rem) {
                // Registrar auditoria de remoção
                $dadosAntes = [
                    'valor' => (float) $rem->valor,
                    'data' => $rem->data,
                    'plano_contas' => $rem->plano_contas,
                    'codigo_plano' => $rem->codigo_plano,
                    'classificacao' => $rem->classificacao,
                    'descricao' => $rem->descricao,
                ];

                $this->registrarAuditoria(
                    'removido',
                    $rem->datajuri_id,
                    $rem->id,
                    $dadosAntes,
                    null
                );

                // Marcar como removido (não deletamos para manter histórico)
                DB::table('movimentos')
                    ->where('id', $rem->id)
                    ->update([
                        'sync_status' => 'removido',
                        'is_stale' => true,
                        'ultima_sync_id' => $this->currentSyncId,
                        'updated_at' => now(),
                    ]);

                $this->stats['removidos']++;
                
                Log::warning('Movimento REMOVIDO detectado', [
                    'datajuri_id' => $rem->datajuri_id,
                    'valor' => $rem->valor,
                    'descricao' => $rem->descricao,
                ]);
            }
        }

        // Log do resumo
        Log::info('Sync Movimentos Completa', $this->stats);

        return $this->stats;
    }

    // =========================================================================
    // SYNC: PESSOA → CLIENTES
    // =========================================================================

    public function syncPessoas(): int
    {
        $campos = implode(',', [
            'id', 'nome', 'numeroDocumento', 'tipoPessoa', 'dataCadastro',
            'cliente', 'statusPessoa', 'codigoPessoa', 'valorHora',
            'totalContasReceber', 'totalContasReceberVencidas', 'valorTotalContasAbertas',
            'cpf', 'cnpj', 'dataNascimento', 'email', 'telefone', 'celular'
        ]);

        $rows = $this->fetchAllPages('Pessoa', $campos);
        $count = 0;

        foreach ($rows as $pessoa) {
            try {
                $datajuriId = $pessoa['id'] ?? null;
                if (!$datajuriId) continue;

                $data = [
                    'datajuri_id' => $datajuriId,
                    'nome' => $pessoa['nome'] ?? null,
                    'documento' => $pessoa['numeroDocumento'] ?? null,
                    'cpf_cnpj' => $pessoa['numeroDocumento'] ?? null,
                    'cpf' => $pessoa['cpf'] ?? null,
                    'cnpj' => $pessoa['cnpj'] ?? null,
                    'tipo' => $this->inferirTipoPessoa($pessoa['tipoPessoa'] ?? ''),
                    'is_cliente' => ($pessoa['cliente'] ?? '') === 'Sim',
                    'status_pessoa' => $pessoa['statusPessoa'] ?? null,
                    'valor_hora' => $this->parseValorBrasileiro($pessoa['valorHora'] ?? 0),
                    'total_contas_receber' => $this->parseValorBrasileiro($pessoa['totalContasReceber'] ?? 0),
                    'total_contas_vencidas' => $this->parseValorBrasileiro($pessoa['totalContasReceberVencidas'] ?? 0),
                    'valor_contas_abertas' => $this->parseValorBrasileiro($pessoa['valorTotalContasAbertas'] ?? 0),
                    'data_nascimento' => $this->parseDataBrasileira($pessoa['dataNascimento'] ?? null),
                    'data_primeiro_contato' => $this->parseDataBrasileira($pessoa['dataCadastro'] ?? null),
                    'email' => $pessoa['email'] ?? null,
                    'telefone' => $pessoa['telefone'] ?? $pessoa['celular'] ?? null,
                    'updated_at' => now(),
                ];

                DB::table('clientes')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync Pessoa Error', ['id' => $pessoa['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC: PROCESSO → PROCESSOS
    // =========================================================================

    public function syncProcessos(): int
    {
        $campos = implode(',', [
            'id', 'pasta', 'status', 'assunto', 'natureza',
            'valorCausa', 'valorProvisionado', 'valorSentenca',
            'possibilidade', 'ganhoCausa', 'tipoEncerramento', 'dataCadastro',
            'proprietario.nome', 'proprietarioId',
            'cliente.nome', 'clienteId', 'cliente.numeroDocumento'
        ]);

        $rows = $this->fetchAllPages('Processo', $campos);
        $count = 0;

        foreach ($rows as $processo) {
            try {
                $datajuriId = $processo['id'] ?? null;
                if (!$datajuriId) continue;

                // Buscar cliente_id local pelo datajuri_id do cliente
                $clienteIdLocal = null;
                if (!empty($processo['clienteId'])) {
                    $cliente = DB::table('clientes')->where('datajuri_id', $processo['clienteId'])->first();
                    $clienteIdLocal = $cliente?->id;
                }

                $data = [
                    'datajuri_id' => $datajuriId,
                    'pasta' => $processo['pasta'] ?? null,
                    'status' => $processo['status'] ?? null,
                    'assunto' => $processo['assunto'] ?? null,
                    'natureza' => $processo['natureza'] ?? null,
                    'valor_causa' => $this->parseValorBrasileiro($processo['valorCausa'] ?? 0),
                    'valor_provisionado' => $this->parseValorBrasileiro($processo['valorProvisionado'] ?? 0),
                    'valor_sentenca' => $this->parseValorBrasileiro($processo['valorSentenca'] ?? 0),
                    'possibilidade' => $processo['possibilidade'] ?? null,
                    'ganho_causa' => $processo['ganhoCausa'] ?? null,
                    'tipo_encerramento' => $processo['tipoEncerramento'] ?? null,
                    'cliente_nome' => $processo['cliente.nome'] ?? null,
                    'cliente_id' => $clienteIdLocal,
                    'cliente_documento' => $processo['cliente.numeroDocumento'] ?? null,
                    'proprietario_nome' => $processo['proprietario.nome'] ?? null,
                    'proprietario_id' => $processo['proprietarioId'] ?? null,
                    'data_cadastro_dj' => $this->parseDataBrasileira($processo['dataCadastro'] ?? null),
                    'updated_at' => now(),
                ];

                DB::table('processos')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync Processo Error', ['id' => $processo['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC: FASEPROCESSO → FASES_PROCESSO
    // =========================================================================

    public function syncFases(): int
    {
        $campos = implode(',', [
            'id', 'processo.pasta', 'processoId', 'tipoFase', 'localidade',
            'instancia', 'data', 'faseAtual', 'diasFaseAtiva', 'dataUltimoAndamento',
            'proprietario.nome', 'proprietarioId'
        ]);

        $rows = $this->fetchAllPages('FaseProcesso', $campos);
        $count = 0;

        foreach ($rows as $fase) {
            try {
                $datajuriId = $fase['id'] ?? null;
                if (!$datajuriId) continue;

                $data = [
                    'datajuri_id' => $datajuriId,
                    'processo_pasta' => $fase['processo.pasta'] ?? null,
                    'processo_id_datajuri' => $fase['processoId'] ?? null,
                    'tipo_fase' => $fase['tipoFase'] ?? null,
                    'localidade' => $fase['localidade'] ?? null,
                    'instancia' => $fase['instancia'] ?? null,
                    'data' => $this->parseDataBrasileira($fase['data'] ?? null),
                    'fase_atual' => ($fase['faseAtual'] ?? 'Não') === 'Sim',
                    'dias_fase_ativa' => (int) ($fase['diasFaseAtiva'] ?? 0),
                    'data_ultimo_andamento' => $this->parseDataBrasileira($fase['dataUltimoAndamento'] ?? null),
                    'proprietario_nome' => $fase['proprietario.nome'] ?? null,
                    'proprietario_id' => $fase['proprietarioId'] ?? null,
                    'updated_at' => now(),
                ];

                DB::table('fases_processo')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync FaseProcesso Error', ['id' => $fase['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC: CONTRATO → CONTRATOS
    // =========================================================================

    public function syncContratos(): int
    {
        $campos = implode(',', [
            'id', 'numero', 'valor', 'dataAssinatura',
            'contratante.nome', 'contratanteId',
            'proprietario.nome', 'proprietarioId'
        ]);

        $rows = $this->fetchAllPages('Contrato', $campos);
        $count = 0;

        foreach ($rows as $contrato) {
            try {
                $datajuriId = $contrato['id'] ?? null;
                if (!$datajuriId) continue;

                $data = [
                    'datajuri_id' => $datajuriId,
                    'numero' => $contrato['numero'] ?? null,
                    'valor' => $this->parseValorBrasileiro($contrato['valor'] ?? 0),
                    'data_assinatura' => $this->parseDataBrasileira($contrato['dataAssinatura'] ?? null),
                    'contratante_nome' => $contrato['contratante.nome'] ?? null,
                    'contratante_id_datajuri' => $contrato['contratanteId'] ?? null,
                    'proprietario_nome' => $contrato['proprietario.nome'] ?? null,
                    'proprietario_id' => $contrato['proprietarioId'] ?? null,
                    'updated_at' => now(),
                ];

                DB::table('contratos')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync Contrato Error', ['id' => $contrato['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC: ATIVIDADE → ATIVIDADES_DATAJURI
    // =========================================================================

    public function syncAtividades(): int
    {
        $campos = implode(',', [
            'id', 'status', 'dataHora', 'dataConclusao', 'dataPrazoFatal',
            'processo.pasta', 'proprietarioId', 'particular'
        ]);

        $rows = $this->fetchAllPages('Atividade', $campos);
        $count = 0;

        foreach ($rows as $atividade) {
            try {
                $datajuriId = $atividade['id'] ?? null;
                if (!$datajuriId) continue;

                $data = [
                    'datajuri_id' => $datajuriId,
                    'status' => $atividade['status'] ?? null,
                    'data_hora' => $this->parseDataHoraBrasileira($atividade['dataHora'] ?? null),
                    'data_conclusao' => $this->parseDataHoraBrasileira($atividade['dataConclusao'] ?? null),
                    'data_prazo_fatal' => $this->parseDataHoraBrasileira($atividade['dataPrazoFatal'] ?? null),
                    'processo_pasta' => $atividade['processo.pasta'] ?? null,
                    'proprietario_id' => $atividade['proprietarioId'] ?? null,
                    'particular' => ($atividade['particular'] ?? 'Não') === 'Sim',
                    'updated_at' => now(),
                ];

                DB::table('atividades_datajuri')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync Atividade Error', ['id' => $atividade['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC: HORATRABALHADA → HORAS_TRABALHADAS_DATAJURI
    // =========================================================================

    public function syncHorasTrabalhadas(): int
    {
        $campos = implode(',', [
            'id', 'data', 'duracao', 'totalHoraTrabalhada', 'horaInicial', 'horaFinal',
            'valorHora', 'valorTotalOriginal', 'assunto', 'tipo', 'status',
            'proprietarioId', 'particular', 'dataFaturado'
        ]);

        $rows = $this->fetchAllPages('HoraTrabalhada', $campos);
        $count = 0;

        foreach ($rows as $hora) {
            try {
                $datajuriId = $hora['id'] ?? null;
                if (!$datajuriId) continue;

                $data = [
                    'datajuri_id' => $datajuriId,
                    'data' => $this->parseDataBrasileira($hora['data'] ?? null),
                    'duracao_original' => $hora['duracao'] ?? null,
                    'total_hora_trabalhada' => $this->parseValorBrasileiro($hora['totalHoraTrabalhada'] ?? 0),
                    'hora_inicial' => $hora['horaInicial'] ?? null,
                    'hora_final' => $hora['horaFinal'] ?? null,
                    'valor_hora' => $this->parseValorBrasileiro($hora['valorHora'] ?? 0),
                    'valor_total_original' => $this->parseValorBrasileiro($hora['valorTotalOriginal'] ?? 0),
                    'assunto' => $hora['assunto'] ?? null,
                    'tipo' => $hora['tipo'] ?? null,
                    'status' => $hora['status'] ?? null,
                    'proprietario_id' => $hora['proprietarioId'] ?? null,
                    'particular' => ($hora['particular'] ?? 'Não') === 'Sim',
                    'data_faturado' => $this->parseDataBrasileira($hora['dataFaturado'] ?? null),
                    'updated_at' => now(),
                ];

                DB::table('horas_trabalhadas_datajuri')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync HoraTrabalhada Error', ['id' => $hora['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC: ORDEMSERVICO → ORDENS_SERVICO
    // =========================================================================

    public function syncOrdensServico(): int
    {
        $campos = implode(',', [
            'id', 'numero', 'situacao', 'dataConclusao', 'dataUltimoAndamento',
            'advogado.nome', 'advogadoId'
        ]);

        $rows = $this->fetchAllPages('OrdemServico', $campos);
        $count = 0;

        foreach ($rows as $os) {
            try {
                $datajuriId = $os['id'] ?? null;
                if (!$datajuriId) continue;

                $data = [
                    'datajuri_id' => $datajuriId,
                    'numero' => $os['numero'] ?? null,
                    'situacao' => $os['situacao'] ?? null,
                    'data_conclusao' => $this->parseDataBrasileira($os['dataConclusao'] ?? null),
                    'data_ultimo_andamento' => $this->parseDataBrasileira($os['dataUltimoAndamento'] ?? null),
                    'advogado_nome' => $os['advogado.nome'] ?? null,
                    'advogado_id' => $os['advogadoId'] ?? null,
                    'updated_at' => now(),
                ];

                DB::table('ordens_servico')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync OrdemServico Error', ['id' => $os['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC: CONTASRECEBER
    // =========================================================================

    public function syncContasReceber(): int
    {
        $campos = implode(',', [
            'id', 'dataVencimento', 'valor', 'status', 
            'pessoa.nome', 'descricao', 'dataPagamento'
        ]);

        $rows = $this->fetchAllPages('ContasReceber', $campos);
        $count = 0;

        foreach ($rows as $conta) {
            try {
                $datajuriId = $conta['id'] ?? null;
                if (!$datajuriId) continue;

                $data = [
                    'datajuri_id' => $datajuriId,
                    'cliente' => $conta['pessoa.nome'] ?? '(Sem cliente)',
                    'valor' => $this->parseValorBrasileiro($conta['valor'] ?? 0),
                    'data_vencimento' => $this->parseDataBrasileira($conta['dataVencimento'] ?? null),
                    'data_pagamento' => $this->parseDataBrasileira($conta['dataPagamento'] ?? null),
                    'status' => $conta['status'] ?? null,
                    'descricao' => $conta['descricao'] ?? null,
                    'updated_at' => now(),
                ];

                DB::table('contas_receber')->updateOrInsert(
                    ['datajuri_id' => $datajuriId],
                    array_merge($data, ['created_at' => now()])
                );
                $count++;
            } catch (\Exception $e) {
                Log::error('Sync ContasReceber Error', ['id' => $conta['id'] ?? 'N/A', 'error' => $e->getMessage()]);
            }
        }

        return $count;
    }

    // =========================================================================
    // SYNC COMPLETA (TODOS OS MÓDULOS)
    // =========================================================================

    public function syncAll(): array
    {
        $results = [
            'success' => false,
            'modules' => [],
            'movimentos_stats' => [],
            'error' => null
        ];

        if (!$this->authenticate()) {
            $results['error'] = 'Falha na autenticação';
            return $results;
        }

        try {
            // Sync na ordem correta (dependências primeiro)
            $results['modules']['pessoas'] = $this->syncPessoas();
            $results['modules']['processos'] = $this->syncProcessos();
            $results['modules']['fases'] = $this->syncFases();
            $results['modules']['contratos'] = $this->syncContratos();
            $results['modules']['atividades'] = $this->syncAtividades();
            $results['modules']['horas'] = $this->syncHorasTrabalhadas();
            $results['modules']['ordens'] = $this->syncOrdensServico();
            $results['modules']['contas_receber'] = $this->syncContasReceber();
            
            // Movimentos por último (com auditoria)
            $results['movimentos_stats'] = $this->syncMovimentos();
            $results['modules']['movimentos'] = array_sum($results['movimentos_stats']);
            
            $results['success'] = true;
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
            Log::error('Sync All Failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function parseValorBrasileiro($valor): float
    {
        if (is_numeric($valor)) return (float) $valor;
        if (!is_string($valor)) return 0.0;

        // Detectar negativo por classe CSS ou sinal
        $negativo = (stripos($valor, 'valor-negativo') !== false) ||
                    (preg_match('/^-|^\(-/', strip_tags($valor)));

        // Remover HTML
        $valor = strip_tags($valor);

        // Remover pontos de milhar, trocar vírgula por ponto
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);

        $float = (float) preg_replace('/[^0-9.\-]/', '', $valor);

        return $negativo && $float > 0 ? -$float : $float;
    }

    private function parseDataBrasileira(?string $data): ?string
    {
        if (empty($data)) return null;

        // DD/MM/YYYY
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        // Já está em formato ISO
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $data)) {
            return substr($data, 0, 10);
        }

        return null;
    }

    private function parseDataHoraBrasileira(?string $dataHora): ?string
    {
        if (empty($dataHora)) return null;

        // DD/MM/YYYY HH:MM
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})/', $dataHora, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]} {$m[4]}:{$m[5]}:00";
        }

        // DD/MM/YYYY
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $dataHora, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }

        return null;
    }

    /**
     * Extrai código do plano de contas da hierarquia completa
     * Ex: "3.01 RESULTADO:3.02.01.07.01 Tarifas" → "3.02.01.07.01"
     */
    private function extrairCodigoPlano(?string $nomeCompleto): ?string
    {
        if (empty($nomeCompleto)) return null;

        // Tentar extrair código com 5 níveis (ex: 3.02.01.07.01)
        if (preg_match('/(\d+\.\d+\.\d+\.\d+\.\d+)/', $nomeCompleto, $m)) {
            return $m[1];
        }

        // Tentar extrair código com 4 níveis (ex: 3.01.01.01)
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $nomeCompleto, $m)) {
            return $m[1];
        }

        // Tentar extrair código com 3 níveis (ex: 3.01.01)
        if (preg_match('/(\d+\.\d+\.\d+)/', $nomeCompleto, $m)) {
            return $m[1];
        }

        return null;
    }

    private function inferirTipoPessoa(?string $tipo): string
    {
        if (empty($tipo)) return 'PF';

        $tipo = strtoupper($tipo);
        if (str_contains($tipo, 'JUR') || str_contains($tipo, 'PJ')) {
            return 'PJ';
        }
        return 'PF';
    }

    /**
     * Infere classificação baseada no código do plano de contas
     */
    private function inferirClassificacao(?string $codigoPlano, float $valor): ?string
    {
        if (empty($codigoPlano)) {
            // Sem plano = TRANSITO (não entra na DRE)
            return 'TRANSITO';
        }

        // Buscar regra na tabela
        $regra = DB::table('classificacao_regras')
            ->where('ativo', true)
            ->where('codigo_plano', $codigoPlano)
            ->first();

        if ($regra) {
            return $regra->classificacao;
        }

        // Inferência por padrão contábil
        // 3.01.01.01, 3.01.01.03 = Receita PF
        if (in_array($codigoPlano, ['3.01.01.01', '3.01.01.03'])) {
            return 'RECEITA_PF';
        }

        // 3.01.01.02, 3.01.01.05 = Receita PJ
        if (in_array($codigoPlano, ['3.01.01.02', '3.01.01.05'])) {
            return 'RECEITA_PJ';
        }

        // 3.01.02.* ou 3.01.03.* = Deduções
        if (preg_match('/^3\.01\.0[23]/', $codigoPlano)) {
            return 'DEDUCAO';
        }

        // 3.02.* = Despesas
        if (str_starts_with($codigoPlano, '3.02')) {
            return 'DESPESA';
        }

        // 3.03.* = Receitas financeiras
        if (str_starts_with($codigoPlano, '3.03')) {
            return 'RECEITA_FINANCEIRA';
        }

        // 3.04.* = Despesas financeiras
        if (str_starts_with($codigoPlano, '3.04')) {
            return 'DESPESA_FINANCEIRA';
        }

        return 'PENDENTE';
    }
}
