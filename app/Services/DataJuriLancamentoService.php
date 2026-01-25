<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Lancamento;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class DataJuriLancamentoService
{
    private $baseUrl;
    private $token;
    private $clientId;
    private $secretId;
    private $username;
    private $password;

    public function __construct()
    {
        $this->baseUrl = config('services.datajuri.url', 'https://api.datajuri.com.br');
        $this->clientId = config('services.datajuri.client_id');
        $this->secretId = config('services.datajuri.secret_id');
        $this->username = config('services.datajuri.username');
        $this->password = config('services.datajuri.password');
    }

    /**
     * Autenticar com DataJuri API
     */
    public function authenticate(): bool
    {
        try {
            $credentials = base64_encode("{$this->clientId}@{$this->secretId}");

            $response = Http::withHeaders([
                'Authorization' => "Basic {$credentials}",
                'Content-Type' => 'application/x-www-form-urlencoded'
            ])->asForm()->post("{$this->baseUrl}/oauth/token", [
                'grant_type' => 'password',
                'username' => $this->username,
                'password' => $this->password
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->token = $data['access_token'] ?? null;
                
                if (!$this->token) {
                    throw new Exception('Token não retornado pela API');
                }

                return true;
            }

            Log::error('DataJuri Auth Failed', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return false;

        } catch (Exception $e) {
            Log::error('DataJuri Auth Exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Buscar lançamentos de um cliente específico
     */
    public function getLancamentosByCliente(string $datajuriClienteId): array
    {
        if (!$this->token) {
            throw new Exception('Não autenticado. Execute authenticate() primeiro.');
        }

        try {
            // Buscar lançamentos vinculados ao cliente
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Content-Type' => 'application/json'
            ])->get("{$this->baseUrl}/v1/lancamentos", [
                'pessoa_id' => $datajuriClienteId,
                'limit' => 500
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            Log::warning('Erro ao buscar lançamentos do cliente', [
                'datajuri_cliente_id' => $datajuriClienteId,
                'status' => $response->status()
            ]);

            return [];

        } catch (Exception $e) {
            Log::error('Exceção ao buscar lançamentos', [
                'datajuri_cliente_id' => $datajuriClienteId,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Buscar todos os lançamentos
     */
    public function getAllLancamentos(int $limit = 1000): array
    {
        if (!$this->token) {
            throw new Exception('Não autenticado. Execute authenticate() primeiro.');
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->token}",
                'Content-Type' => 'application/json'
            ])->get("{$this->baseUrl}/v1/lancamentos", [
                'limit' => $limit
            ]);

            if ($response->successful()) {
                return $response->json()['data'] ?? [];
            }

            Log::error('Erro ao buscar todos os lançamentos', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];

        } catch (Exception $e) {
            Log::error('Exceção ao buscar todos os lançamentos', [
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Sincronizar lançamentos de todos os clientes
     */
    public function syncAllLancamentos(): array
    {
        $logData = [
            'tipo' => 'DataJuri Lançamentos',
            'status' => 'iniciado',
            'data_inicio' => now(),
            'total_registros' => 0,
            'registros_sincronizados' => 0,
            'erros' => 0,
            'mensagem' => ''
        ];

        DB::beginTransaction();

        try {
            // Autenticar
            if (!$this->authenticate()) {
                throw new Exception('Falha na autenticação com DataJuri');
            }

            // Buscar todos os lançamentos
            $lancamentosData = $this->getAllLancamentos(5000);
            $logData['total_registros'] = count($lancamentosData);

            $sincronizados = 0;
            $erros = 0;

            foreach ($lancamentosData as $lancamentoData) {
                try {
                    $this->syncLancamento($lancamentoData);
                    $sincronizados++;
                } catch (Exception $e) {
                    $erros++;
                    Log::error('Erro ao sincronizar lançamento individual', [
                        'lancamento_id' => $lancamentoData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Recalcular valores de carteira após sincronização
            $this->recalcularValoresCarteira();

            DB::commit();

            $logData['registros_sincronizados'] = $sincronizados;
            $logData['erros'] = $erros;
            $logData['status'] = 'concluído';
            $logData['mensagem'] = "Sincronização concluída. {$sincronizados} lançamentos sincronizados, {$erros} erros.";

        } catch (Exception $e) {
            DB::rollBack();

            $logData['status'] = 'erro';
            $logData['mensagem'] = 'Erro: ' . $e->getMessage();
            
            Log::error('Erro fatal na sincronização de lançamentos', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        $logData['data_fim'] = now();

        // Registrar log
        IntegrationLog::create($logData);

        return $logData;
    }

    /**
     * Sincronizar um único lançamento
     */
    private function syncLancamento(array $data): void
    {
        // Validar dados essenciais
        if (!isset($data['id']) || !isset($data['valor'])) {
            throw new Exception('Dados de lançamento inválidos');
        }

        // Buscar cliente pelo datajuri_id
        $cliente = Cliente::where('datajuri_id', $data['pessoa_id'] ?? null)->first();
        
        if (!$cliente) {
            // Cliente não encontrado - criar registro temporário ou pular
            Log::warning('Cliente não encontrado para lançamento', [
                'pessoa_id' => $data['pessoa_id'] ?? 'não informado',
                'lancamento_id' => $data['id']
            ]);
            return;
        }

        // Determinar tipo (Receita ou Despesa)
        $tipo = $this->determinarTipo($data);

        // Preparar dados para salvar
        $lancamentoData = [
            'datajuri_id' => $data['id'],
            'cliente_id' => $cliente->id,
            'valor' => abs((float) $data['valor']),
            'tipo' => $tipo,
            'descricao' => $data['descricao'] ?? $data['historico'] ?? null,
            'referencia' => $data['referencia'] ?? $data['numero_documento'] ?? null,
            'data_lancamento' => isset($data['data_lancamento']) ? 
                                 \Carbon\Carbon::parse($data['data_lancamento'])->format('Y-m-d') : 
                                 now()->format('Y-m-d'),
            'data_recebimento' => isset($data['data_recebimento']) ? 
                                  \Carbon\Carbon::parse($data['data_recebimento'])->format('Y-m-d') : 
                                  null,
            'usuario_responsavel' => $data['usuario_responsavel'] ?? $data['responsavel'] ?? null,
            'metadata' => json_encode($data)
        ];

        // Usar updateOrCreate para evitar duplicatas
        Lancamento::updateOrCreate(
            ['datajuri_id' => $data['id']],
            $lancamentoData
        );
    }

    /**
     * Determinar se é Receita ou Despesa baseado nos dados
     */
    private function determinarTipo(array $data): string
    {
        // Lógica para determinar tipo
        // Adaptar conforme estrutura real da API DataJuri

        $valor = (float) $data['valor'];
        $tipo = $data['tipo'] ?? $data['tipo_lancamento'] ?? null;

        // Se tiver campo tipo explícito
        if ($tipo) {
            if (stripos($tipo, 'receita') !== false || stripos($tipo, 'entrada') !== false) {
                return 'Receita';
            }
            if (stripos($tipo, 'despesa') !== false || stripos($tipo, 'saída') !== false || stripos($tipo, 'saida') !== false) {
                return 'Despesa';
            }
        }

        // Se valor for positivo, assumir como Receita
        // Se negativo, assumir como Despesa
        return $valor >= 0 ? 'Receita' : 'Despesa';
    }

    /**
     * Recalcular valores de carteira para todos os clientes
     */
    public function recalcularValoresCarteira(): void
    {
        $clientes = Cliente::all();

        foreach ($clientes as $cliente) {
            $valorCarteira = Lancamento::where('cliente_id', $cliente->id)
                ->where('tipo', 'Receita')
                ->sum('valor');

            $receitaMesAtual = Lancamento::where('cliente_id', $cliente->id)
                ->where('tipo', 'Receita')
                ->mesAtual()
                ->sum('valor');

            $receitaMesAnterior = Lancamento::where('cliente_id', $cliente->id)
                ->where('tipo', 'Receita')
                ->mesAnterior()
                ->sum('valor');

            $receitaAcumuladaAno = Lancamento::where('cliente_id', $cliente->id)
                ->where('tipo', 'Receita')
                ->anoAtual()
                ->sum('valor');

            $cliente->update([
                'valor_carteira' => $valorCarteira,
                'receita_mes_atual' => $receitaMesAtual,
                'receita_mes_anterior' => $receitaMesAnterior,
                'receita_acumulada_ano' => $receitaAcumuladaAno,
            ]);
        }
    }
}
