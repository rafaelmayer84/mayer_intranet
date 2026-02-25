<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriSyncService;

class SyncDataJuriDireto extends Command
{
    protected $signature = 'sync:datajuri-direto
                            {--modulo= : Modulo especifico (pessoas, processos, movimentos, contratos, atividades, horas, fases, ordens, contasreceber)}
                            {--apenas-movimentos : Sincronizar apenas movimentos}
                            {--dry-run : Apenas testar autenticacao}';

    protected $description = 'Sincroniza dados do DataJuri usando DataJuriSyncService';

    public function handle(): int
    {
        $this->info('================================================');
        $this->info('  SYNC DATAJURI (SyncService_NOVO)');
        $this->info('================================================');
        $this->newLine();

        $service = new DataJuriSyncService();

        if (!$service->authenticate()) {
            $this->error('Falha na autenticacao');
            return Command::FAILURE;
        }
        $this->info('Autenticado com sucesso');

        if ($this->option('dry-run')) {
            return Command::SUCCESS;
        }

        $syncId = 'manual_' . now()->format('Ymd_His');
        $service->setSyncId($syncId);

        if ($this->option('apenas-movimentos')) {
            $this->info('Sincronizando MOVIMENTOS...');
            $result = $service->syncMovimentos();
            $this->info('Movimentos: ' . json_encode($result));
            return Command::SUCCESS;
        }

        if ($modulo = $this->option('modulo')) {
            return $this->syncModulo($service, strtolower($modulo));
        }

        $this->info('Executando sincronizacao COMPLETA...');
        $results = $service->syncAll();
        $stats = $service->getStats();
        $this->info('Sincronizacao concluida!');
        $this->table(['Metrica', 'Valor'], collect($stats)->map(fn($v, $k) => [$k, $v])->toArray());
        return Command::SUCCESS;
    }

    private function syncModulo(DataJuriSyncService $service, string $modulo): int
    {
        $this->info("Sincronizando: {$modulo}");
        $map = [
            'pessoas' => 'syncPessoas', 'pessoa' => 'syncPessoas', 'clientes' => 'syncPessoas',
            'processos' => 'syncProcessos', 'processo' => 'syncProcessos',
            'movimentos' => 'syncMovimentos', 'movimento' => 'syncMovimentos',
            'contratos' => 'syncContratos', 'contrato' => 'syncContratos',
            'atividades' => 'syncAtividades', 'atividade' => 'syncAtividades',
            'horas' => 'syncHorasTrabalhadas', 'horas_trabalhadas' => 'syncHorasTrabalhadas',
            'fases' => 'syncFases', 'fases_processo' => 'syncFases',
            'ordens' => 'syncOrdensServico', 'ordens_servico' => 'syncOrdensServico',
            'contasreceber' => 'syncContasReceber', 'contas_receber' => 'syncContasReceber',
        ];

        $method = $map[$modulo] ?? null;
        if (!$method) {
            $this->error("Modulo desconhecido: {$modulo}");
            $this->line('Disponiveis: ' . implode(', ', array_unique(array_values($map))));
            return Command::FAILURE;
        }

        $result = $service->$method();
        $this->info('Resultado: ' . json_encode($result));
        return Command::SUCCESS;
    }
}
