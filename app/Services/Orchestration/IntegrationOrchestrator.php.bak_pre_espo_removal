<?php

namespace App\Services\Orchestration;

use App\Services\Integration\{DataJuriService, EspoCrmService};
use App\Services\ETL\DataTransformerService;
use App\Models\{Cliente, Lead, Oportunidade, IntegrationLog};
use Illuminate\Support\Facades\{DB, Log};

class IntegrationOrchestrator
{
    public function __construct(
        protected DataJuriService $dataJuri,
        protected EspoCrmService $espo,
        protected DataTransformerService $transformer
    ) {}

    public function syncAll(): array
    {
        $syncId = 'SYNC_' . now()->format('YmdHis');
        
        $log = IntegrationLog::create([
            'sync_id' => $syncId,
            'tipo' => 'sync_full',
            'fonte' => 'manual',
            'status' => 'iniciado',
            'inicio' => now()
        ]);
        
        try {
            $rC = $this->syncClientes($syncId);
            $rL = $this->syncLeads($syncId);
            $rO = $this->syncOportunidades($syncId);
            
            $log->update([
                'status' => 'concluido',
                'fim' => now(),
                'duracao_segundos' => now()->diffInSeconds($log->inicio),
                'registros_processados' => $rC['processados'] + $rL['processados'] + $rO['processados'],
                'registros_criados' => $rC['criados'] + $rL['criados'] + $rO['criados']
            ]);
            
            return [
                'success' => true,
                'sync_id' => $syncId,
                'clientes' => $rC,
                'leads' => $rL,
                'oportunidades' => $rO
            ];
        } catch (\Exception $e) {
            $log->update([
                'status' => 'erro',
                'fim' => now(),
                'mensagem_erro' => $e->getMessage()
            ]);
            Log::error("Erro sync full: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncClientes(?string $parentSyncId = null): array
    {
        $syncId = $parentSyncId ?? 'SYNC_CLI_' . now()->format('YmdHis');
        $criados = 0;
        $atualizados = 0;
        $ignorados = 0;
        
        DB::beginTransaction();
        try {
            $pessoas = $this->dataJuri->buscarModulo('Pessoa');
            foreach ($pessoas as $p) {
                $data = $this->transformer->normalizeCliente($p, 'datajuri');
                if (empty($data['datajuri_id'])) {
                    $ignorados++;
                    continue;
                }
                
                $cliente = Cliente::where('datajuri_id', $data['datajuri_id'])->first();
                if ($cliente) {
                    $cliente->update($data);
                    $atualizados++;
                } else {
                    Cliente::create($data);
                    $criados++;
                }
            }
            
            $contas = $this->espo->getAllEntities('Account');
            foreach ($contas['data'] as $c) {
                $data = $this->transformer->normalizeCliente($c, 'espocrm');
                if (empty($data['espocrm_id'])) {
                    $ignorados++;
                    continue;
                }
                
                $cliente = Cliente::where('espocrm_id', $data['espocrm_id'])->first();
                if (!$cliente) {
                    $clienteExistente = Cliente::where('nome', $data['nome'])->first();
                    if ($clienteExistente) {
                        $clienteExistente->update(['espocrm_id' => $data['espocrm_id']]);
                        $atualizados++;
                    } else {
                        Cliente::create($data);
                        $criados++;
                    }
                } else {
                    $cliente->update($data);
                    $atualizados++;
                }
            }
            
            DB::commit();
            
            return [
                'success' => true,
                'sync_id' => $syncId,
                'processados' => count($pessoas) + count($contas['data']),
                'criados' => $criados,
                'atualizados' => $atualizados,
                'ignorados' => $ignorados,
                'erros' => []
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro sync clientes: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processados' => 0,
                'criados' => 0,
                'atualizados' => 0
            ];
        }
    }

    public function syncLeads(?string $parentSyncId = null): array
    {
        $syncId = $parentSyncId ?? 'SYNC_LEADS_' . now()->format('YmdHis');
        $criados = 0;
        $atualizados = 0;
        $ignorados = 0;
        $erros = [];
        
        DB::beginTransaction();
        try {
            $leads = $this->espo->getAllEntities('Lead');
            
            Log::info("Sync Leads: Recebidos " . count($leads['data']) . " leads da API");
            
            foreach ($leads['data'] as $l) {
                try {
                    $data = $this->mapearLeadEspoCrm($l);
                    
                    if (empty($data['espocrm_id'])) {
                        $ignorados++;
                        continue;
                    }
                    
                    $lead = Lead::where('espocrm_id', $data['espocrm_id'])->first();
                    
                    if ($lead) {
                        $lead->update($data);
                        $atualizados++;
                    } else {
                        Lead::create($data);
                        $criados++;
                    }
                } catch (\Exception $e) {
                    $erros[] = "Lead {$l['id']}: " . $e->getMessage();
                    Log::warning("Erro ao processar lead", ['id' => $l['id'] ?? 'unknown', 'error' => $e->getMessage()]);
                }
            }
            
            DB::commit();
            
            Log::info("Sync Leads concluído", [
                'criados' => $criados,
                'atualizados' => $atualizados,
                'ignorados' => $ignorados,
                'erros' => count($erros)
            ]);
            
            return [
                'success' => true,
                'sync_id' => $syncId,
                'processados' => count($leads['data']),
                'criados' => $criados,
                'atualizados' => $atualizados,
                'ignorados' => $ignorados,
                'erros' => $erros
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro sync leads: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processados' => 0,
                'criados' => 0,
                'atualizados' => 0
            ];
        }
    }

    private function mapearLeadEspoCrm(array $l): array
    {
        $statusMap = [
            'New' => 'novo',
            'Assigned' => 'atribuido',
            'In Process' => 'em_andamento',
            'Converted' => 'convertido',
            'Recycled' => 'reciclado',
            'Dead' => 'perdido'
        ];
        
        $origemMap = [
            'WhatsApp Bot' => 'WhatsApp Bot',
            'Partner' => 'Parceiros',
            'Web Site' => 'Website',
            'Website' => 'Website',
            'Email Campaign' => 'Email',
            'Existing Customer' => 'Cliente Existente',
            'Public Relations' => 'Relações Públicas',
            'Direct Mail' => 'Mala Direta',
            'Conference' => 'Conferência',
            'Trade Show' => 'Feira',
            'Other' => 'Outro'
        ];
        
        $motivoPerda = $l['cMotivoDePerdaLead'] ?? null;
        if ($motivoPerda === 'Selecione' || empty($motivoPerda)) {
            $motivoPerda = null;
        }
        
        $statusApi = $l['status'] ?? 'New';
        $statusLocal = $statusMap[$statusApi] ?? 'novo';
        
        $origemApi = $l['source'] ?? 'Other';
        $origemLocal = $origemMap[$origemApi] ?? 'Outro';
        
        $dataConversao = null;
        if (!empty($l['convertedAt'])) {
            $dataConversao = $this->parseDateTime($l['convertedAt']);
        } elseif ($statusApi === 'Converted' && !empty($l['modifiedAt'])) {
            $dataConversao = $this->parseDateTime($l['modifiedAt']);
        }
        
        $dataCriacaoLead = null;
        if (!empty($l['createdAt'])) {
            $dataCriacaoLead = $this->parseDateTime($l['createdAt']);
        }
        
        return [
            'espocrm_id' => $l['id'] ?? null,
            'nome' => $l['name'] ?? trim(($l['firstName'] ?? '') . ' ' . ($l['lastName'] ?? '')) ?: 'Sem nome',
            'email' => $l['emailAddress'] ?? null,
            'telefone' => $this->limparTelefone($l['phoneNumber'] ?? ''),
            'origem' => $origemLocal,
            'cidade' => $l['addressCity'] ?? null,
            'status' => $statusLocal,
            'motivo_perda' => $motivoPerda,
            'responsavel_id' => $this->buscarResponsavelId($l['assignedUserId'] ?? null),
            'data_criacao_lead' => $dataCriacaoLead,
            'data_conversao' => $dataConversao,
            'metadata' => json_encode([
                'espo_status_original' => $statusApi,
                'espo_source_original' => $origemApi,
                'description' => $l['description'] ?? null,
                'addressState' => $l['addressState'] ?? null,
                'addressCountry' => $l['addressCountry'] ?? null,
                'createdById' => $l['createdById'] ?? null,
                'createdByName' => $l['createdByName'] ?? null,
                'modifiedAt' => $l['modifiedAt'] ?? null,
                'cTipoDeDemanda' => $l['cTipoDeDemanda'] ?? null,
                'createdOpportunityId' => $l['createdOpportunityId'] ?? null,
                'createdAccountId' => $l['createdAccountId'] ?? null
            ])
        ];
    }

    public function syncOportunidades(?string $parentSyncId = null): array
    {
        $syncId = $parentSyncId ?? 'SYNC_OP_' . now()->format('YmdHis');
        $criados = 0;
        $atualizados = 0;
        $ignorados = 0;
        $erros = [];
        
        DB::beginTransaction();
        try {
            $ops = $this->espo->getAllEntities('Opportunity');
            
            Log::info("Sync Oportunidades: Recebidas " . count($ops['data']) . " oportunidades da API");
            
            foreach ($ops['data'] as $o) {
                try {
                    $data = $this->mapearOportunidadeEspoCrm($o);
                    
                    if (empty($data['espocrm_id'])) {
                        $ignorados++;
                        continue;
                    }
                    
                    $op = Oportunidade::where('espocrm_id', $data['espocrm_id'])->first();
                    
                    if ($op) {
                        $op->update($data);
                        $atualizados++;
                    } else {
                        Oportunidade::create($data);
                        $criados++;
                    }
                } catch (\Exception $e) {
                    $erros[] = "Oportunidade {$o['id']}: " . $e->getMessage();
                    Log::warning("Erro ao processar oportunidade", ['id' => $o['id'] ?? 'unknown', 'error' => $e->getMessage()]);
                }
            }
            
            DB::commit();
            
            Log::info("Sync Oportunidades concluído", [
                'criados' => $criados,
                'atualizados' => $atualizados,
                'ignorados' => $ignorados,
                'erros' => count($erros)
            ]);
            
            return [
                'success' => true,
                'sync_id' => $syncId,
                'processados' => count($ops['data']),
                'criados' => $criados,
                'atualizados' => $atualizados,
                'ignorados' => $ignorados,
                'erros' => $erros
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro sync oportunidades: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'processados' => 0,
                'criados' => 0,
                'atualizados' => 0
            ];
        }
    }

    private function mapearOportunidadeEspoCrm(array $o): array
    {
        $estagioMap = [
            'Prospecting' => 'prospectando',
            'Qualification' => 'qualificacao',
            'Proposal' => 'proposta',
            'Negotiation' => 'negociacao',
            'Closed Won' => 'ganha',
            'Closed Lost' => 'perdida',
            'Prospecção' => 'prospectando',
            'Qualificação' => 'qualificacao',
            'Proposta' => 'proposta',
            'Negociação' => 'negociacao',
            'Ganha' => 'ganha',
            'Perdida' => 'perdida'
        ];
        
        $estagioApi = $o['stage'] ?? 'Prospecting';
        $estagioLocal = $estagioMap[$estagioApi] ?? 'prospectando';
        
        $tipo = 'PF';
        if (!empty($o['accountId'])) {
            $cliente = Cliente::where('espocrm_id', $o['accountId'])->first();
            if ($cliente && $cliente->tipo) {
                $tipo = $cliente->tipo;
            }
        }
        
        $leadId = null;
        if (!empty($o['leadId'])) {
            $lead = Lead::where('espocrm_id', $o['leadId'])->first();
            if ($lead) {
                $leadId = $lead->id;
            }
        }
        
        $clienteId = null;
        if (!empty($o['accountId'])) {
            $cliente = Cliente::where('espocrm_id', $o['accountId'])->first();
            if ($cliente) {
                $clienteId = $cliente->id;
            }
        }
        
        $dataCriacao = null;
        if (!empty($o['createdAt'])) {
            $dataCriacao = $this->parseDateTime($o['createdAt']);
        }
        
        $dataFechamento = null;
        if (!empty($o['closeDate'])) {
            $dataFechamento = $this->parseDate($o['closeDate']);
        }
        
        return [
            'espocrm_id' => $o['id'] ?? null,
            'nome' => $o['name'] ?? 'Sem nome',
            'estagio' => $estagioLocal,
            'valor' => (float)($o['amount'] ?? 0),
            'tipo' => $tipo,
            'lead_id' => $leadId,
            'cliente_id' => $clienteId,
            'responsavel_id' => $this->buscarResponsavelId($o['assignedUserId'] ?? null),
            'data_criacao' => $dataCriacao,
            'data_fechamento' => $dataFechamento,
            'observacoes' => $o['description'] ?? null,
            'metadata' => json_encode([
                'espo_stage_original' => $estagioApi,
                'probability' => $o['probability'] ?? null,
                'leadSource' => $o['leadSource'] ?? null,
                'accountId' => $o['accountId'] ?? null,
                'leadId' => $o['leadId'] ?? null,
                'modifiedAt' => $o['modifiedAt'] ?? null,
                'assignedUserName' => $o['assignedUserName'] ?? null,
                'accountName' => $o['accountName'] ?? null
            ])
        ];
    }

    private function limparTelefone(?string $telefone): ?string
    {
        if (empty($telefone)) {
            return null;
        }
        return preg_replace('/[^0-9]/', '', $telefone);
    }

    private function parseDateTime(?string $datetime): ?string
    {
        if (empty($datetime)) {
            return null;
        }
        
        try {
            return \Carbon\Carbon::parse($datetime)->format('Y-m-d H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }
        
        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }

    private function buscarResponsavelId(?string $espoUserId): ?int
    {
        return null;
    }
}
