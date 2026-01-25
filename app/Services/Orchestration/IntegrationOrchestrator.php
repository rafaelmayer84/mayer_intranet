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

    public function syncAll(): array {
        $syncId = 'SYNC_' . now()->format('YmdHis');
        $log = IntegrationLog::create(['sync_id' => $syncId, 'tipo' => 'sync_full', 'fonte' => 'manual', 'status' => 'iniciado', 'inicio' => now()]);
        try {
            $rC = $this->syncClientes($syncId);
            $rL = $this->syncLeads($syncId);
            $rO = $this->syncOportunidades($syncId);
            $log->update(['status' => 'concluido', 'fim' => now(), 'duracao_segundos' => now()->diffInSeconds($log->inicio),
                'registros_processados' => $rC['processados'] + $rL['processados'] + $rO['processados'],
                'registros_criados' => $rC['criados'] + $rL['criados'] + $rO['criados']]);
            return ['success' => true, 'sync_id' => $syncId, 'clientes' => $rC, 'leads' => $rL, 'oportunidades' => $rO];
        } catch (\Exception $e) {
            $log->update(['status' => 'erro', 'fim' => now(), 'mensagem_erro' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncClientes(?string $parentSyncId = null): array {
        $syncId = $parentSyncId ?? 'SYNC_CLI_' . now()->format('YmdHis');
        $criados = 0; $atualizados = 0; $ignorados = 0;
        DB::beginTransaction();
        try {
            $pessoas = $this->dataJuri->buscarModulo('Pessoa');
            foreach ($pessoas as $p) {
                $data = $this->transformer->normalizeCliente($p, 'datajuri');
                if (empty($data['datajuri_id'])) { $ignorados++; continue; }
                $cliente = Cliente::where('datajuri_id', $data['datajuri_id'])->first();
                $cliente ? ($cliente->update($data) && $atualizados++) : (Cliente::create($data) && $criados++);
            }
            $contas = $this->espo->getAllEntities('Account');
            foreach ($contas['data'] as $c) {
                $data = $this->transformer->normalizeCliente($c, 'espocrm');
                if (empty($data['espocrm_id'])) { $ignorados++; continue; }
                $cliente = Cliente::where('espocrm_id', $data['espocrm_id'])->first();
                if (!$cliente) {
                    $clienteExistente = Cliente::where('nome', $data['nome'])->first();
                    $clienteExistente ? ($clienteExistente->update(['espocrm_id' => $data['espocrm_id']]) && $atualizados++) : (Cliente::create($data) && $criados++);
                } else {
                    $cliente->update($data) && $atualizados++;
                }
            }
            DB::commit();
            return ['success' => true, 'sync_id' => $syncId, 'processados' => count($pessoas) + count($contas['data']),
                'criados' => $criados, 'atualizados' => $atualizados, 'ignorados' => $ignorados, 'erros' => []];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro sync clientes: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'processados' => 0, 'criados' => 0, 'atualizados' => 0];
        }
    }

    public function syncLeads(?string $parentSyncId = null): array {
        $syncId = $parentSyncId ?? 'SYNC_LEADS_' . now()->format('YmdHis');
        $criados = 0; $atualizados = 0; $ignorados = 0;
        DB::beginTransaction();
        try {
            $leads = $this->espo->getAllEntities('Lead');
            foreach ($leads['data'] as $l) {
                $data = ['nome' => $l['name'] ?? 'Sem nome', 'email' => $l['emailAddress'] ?? null,
                    'telefone' => preg_replace('/[^0-9]/', '', $l['phoneNumber'] ?? ''),
                    'origem' => 'Outro', 'status' => 'novo', 'espocrm_id' => $l['id'] ?? null];
                if (empty($data['espocrm_id'])) { $ignorados++; continue; }
                $lead = Lead::where('espocrm_id', $data['espocrm_id'])->first();
                $lead ? ($lead->update($data) && $atualizados++) : (Lead::create($data) && $criados++);
            }
            DB::commit();
            return ['success' => true, 'sync_id' => $syncId, 'processados' => count($leads['data']),
                'criados' => $criados, 'atualizados' => $atualizados, 'ignorados' => $ignorados];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function syncOportunidades(?string $parentSyncId = null): array {
        $syncId = $parentSyncId ?? 'SYNC_OP_' . now()->format('YmdHis');
        $criados = 0; $atualizados = 0; $ignorados = 0;
        DB::beginTransaction();
        try {
            $ops = $this->espo->getAllEntities('Opportunity');
            foreach ($ops['data'] as $o) {
                $data = ['nome' => $o['name'] ?? 'Sem nome', 'estagio' => 'prospectando',
                    'valor' => (float)($o['amount'] ?? 0), 'tipo' => 'PF', 'espocrm_id' => $o['id'] ?? null];
                if (empty($data['espocrm_id'])) { $ignorados++; continue; }
                $op = Oportunidade::where('espocrm_id', $data['espocrm_id'])->first();
                $op ? ($op->update($data) && $atualizados++) : (Oportunidade::create($data) && $criados++);
            }
            DB::commit();
            return ['success' => true, 'sync_id' => $syncId, 'processados' => count($ops['data']),
                'criados' => $criados, 'atualizados' => $atualizados, 'ignorados' => $ignorados];
        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
