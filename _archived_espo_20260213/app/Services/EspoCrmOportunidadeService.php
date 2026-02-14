<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Oportunidade;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EspoCrmOportunidadeService
{
    private $baseUrl;
    private $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.espocrm.url', 'https://mayeradvogados.adv.br/CRM'), '/');
        $this->apiKey = config('services.espocrm.api_key');
    }

    /**
     * Buscar todas as oportunidades do ESPO CRM
     */
    public function getAllOportunidades(int $maxResults = 200): array
    {
        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$this->baseUrl}/api/v1/Opportunity", [
                'maxResults' => $maxResults,
                'orderBy' => 'createdAt',
                'order' => 'desc'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['list'] ?? [];
            }

            Log::error('Erro ao buscar oportunidades do ESPO CRM', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return [];

        } catch (Exception $e) {
            Log::error('Exceção ao buscar oportunidades do ESPO CRM', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Buscar uma oportunidade específica por ID
     */
    public function getOportunidade(string $id): ?array
    {
        try {
            $response = Http::withHeaders([
                'X-Api-Key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->get("{$this->baseUrl}/api/v1/Opportunity/{$id}");

            if ($response->successful()) {
                return $response->json();
            }

            return null;

        } catch (Exception $e) {
            Log::error('Exceção ao buscar oportunidade específica', [
                'id' => $id,
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Sincronizar todas as oportunidades
     */
    public function syncAllOportunidades(): array
    {
        $logData = [
            'tipo' => 'ESPO CRM Oportunidades',
            'status' => 'iniciado',
            'data_inicio' => now(),
            'total_registros' => 0,
            'registros_sincronizados' => 0,
            'erros' => 0,
            'mensagem' => ''
        ];

        DB::beginTransaction();

        try {
            // Buscar todas as oportunidades
            $oportunidadesData = $this->getAllOportunidades(500);
            $logData['total_registros'] = count($oportunidadesData);

            $sincronizados = 0;
            $erros = 0;

            foreach ($oportunidadesData as $oportunidadeData) {
                try {
                    $this->syncOportunidade($oportunidadeData);
                    $sincronizados++;
                } catch (Exception $e) {
                    $erros++;
                    Log::error('Erro ao sincronizar oportunidade individual', [
                        'oportunidade_id' => $oportunidadeData['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }

            DB::commit();

            $logData['registros_sincronizados'] = $sincronizados;
            $logData['erros'] = $erros;
            $logData['status'] = 'concluído';
            $logData['mensagem'] = "Sincronização concluída. {$sincronizados} oportunidades sincronizadas, {$erros} erros.";

        } catch (Exception $e) {
            DB::rollBack();

            $logData['status'] = 'erro';
            $logData['mensagem'] = 'Erro: ' . $e->getMessage();
            
            Log::error('Erro fatal na sincronização de oportunidades', [
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
     * Sincronizar uma única oportunidade
     */
    private function syncOportunidade(array $data): void
    {
        // Validar dados essenciais
        if (!isset($data['id'])) {
            throw new Exception('ID da oportunidade não informado');
        }

        // Buscar lead relacionado se existir
        $leadId = null;
        if (isset($data['leadId']) && $data['leadId']) {
            $lead = Lead::where('espocrm_id', $data['leadId'])->first();
            if ($lead) {
                $leadId = $lead->id;
            }
        }

        // Buscar cliente relacionado se existir
        $clienteId = null;
        if (isset($data['accountId']) && $data['accountId']) {
            $cliente = \App\Models\Cliente::where('espocrm_id', $data['accountId'])->first();
            if ($cliente) {
                $clienteId = $cliente->id;
            }
        }

        // Preparar dados para salvar
        $oportunidadeData = [
            'espocrm_id' => $data['id'],
            'lead_id' => $leadId,
            'cliente_id' => $clienteId,
            'nome' => $data['name'] ?? 'Sem nome',
            'estagio' => $data['stage'] ?? 'Prospecting',
            'valor' => isset($data['amount']) ? (float) $data['amount'] : 0,
            'tipo' => $data['type'] ?? null,
            'responsavel_id' => $data['assignedUserId'] ?? null,
            'data_criacao' => isset($data['createdAt']) ? 
                              \Carbon\Carbon::parse($data['createdAt'])->format('Y-m-d H:i:s') : 
                              now(),
            'data_fechamento' => isset($data['closeDate']) ? 
                                 \Carbon\Carbon::parse($data['closeDate'])->format('Y-m-d') : 
                                 null,
            'observacoes' => $data['description'] ?? null,
            'metadata' => json_encode($data)
        ];

        // Usar updateOrCreate para evitar duplicatas
        Oportunidade::updateOrCreate(
            ['espocrm_id' => $data['id']],
            $oportunidadeData
        );
    }

    /**
     * Calcular valor total do pipeline (oportunidades abertas)
     */
    public function calcularPipeline(): float
    {
        return Oportunidade::whereNotIn('estagio', ['Closed Won', 'Closed Lost', 'Ganha', 'Perdida'])
            ->sum('valor');
    }

    /**
     * Calcular valor ponderado do pipeline (valor * probabilidade)
     */
    public function calcularPipelinePonderado(): float
    {
        $oportunidades = Oportunidade::whereNotIn('estagio', ['Closed Won', 'Closed Lost', 'Ganha', 'Perdida'])
            ->get();

        $valorPonderado = 0;

        foreach ($oportunidades as $oportunidade) {
            // Determinar probabilidade baseado no estágio
            $probabilidade = $this->getProbabilidadePorEstagio($oportunidade->estagio);
            $valorPonderado += $oportunidade->valor * ($probabilidade / 100);
        }

        return $valorPonderado;
    }

    /**
     * Obter probabilidade baseado no estágio
     */
    private function getProbabilidadePorEstagio(string $estagio): int
    {
        $probabilidades = [
            'Prospecting' => 10,
            'Qualification' => 20,
            'Proposal' => 40,
            'Negotiation' => 60,
            'Closed Won' => 100,
            'Closed Lost' => 0,
            'Ganha' => 100,
            'Perdida' => 0,
        ];

        return $probabilidades[$estagio] ?? 50;
    }

    /**
     * Obter distribuição de oportunidades por estágio
     */
    public function getDistribuicaoPorEstagio(): array
    {
        return Oportunidade::select('estagio', DB::raw('COUNT(*) as total'), DB::raw('SUM(valor) as valor_total'))
            ->groupBy('estagio')
            ->get()
            ->toArray();
    }
}
