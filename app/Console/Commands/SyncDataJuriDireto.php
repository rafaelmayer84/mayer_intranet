<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriSyncService;

/**
 * Comando de sincronização DIRETA com DataJuri
 * 
 * Este comando NÃO depende do DataJuriSyncOrchestrator.
 * Usa diretamente o DataJuriSyncService que está funcional.
 * 
 * Criado: 05/02/2026
 * Motivo: Bypass do Orchestrator problemático
 */
class SyncDataJuriDireto extends Command
{
    protected $signature = 'sync:datajuri-direto 
                            {--modulo= : Módulo específico (pessoas, processos, movimentos, etc)}
                            {--apenas-movimentos : Sincronizar apenas movimentos}
                            {--dry-run : Apenas testar autenticação}';

    protected $description = 'Sincroniza dados do DataJuri usando DataJuriSyncService diretamente';

    public function handle(): int
    {
        $this->info('================================================');
        $this->info('  SYNC DATAJURI DIRETO (Bypass Orchestrator)');
        $this->info('================================================');
        $this->newLine();

        $service = new DataJuriSyncService();

        // Dry run - apenas testar autenticação
        if ($this->option('dry-run')) {
            $this->info('Testando autenticação...');
            if ($service->authenticate()) {
                $this->info('✅ Autenticação OK!');
                return Command::SUCCESS;
            } else {
                $this->error('❌ Falha na autenticação');
                return Command::FAILURE;
            }
        }

        // Apenas movimentos
        if ($this->option('apenas-movimentos')) {
            return $this->syncMovimentos($service);
        }

        // Módulo específico
        if ($modulo = $this->option('modulo')) {
            return $this->syncModulo($service, $modulo);
        }

        // Sync completo
        return $this->syncAll($service);
    }

    private function syncMovimentos(DataJuriSyncService $service): int
    {
        $this->info('Sincronizando MOVIMENTOS...');
        $this->newLine();

        $results = $service->syncAll();

        if (!$results['success']) {
            $this->error('❌ Falha na sincronização');
            foreach ($results['errors'] as $error) {
                $this->error("   - {$error}");
            }
            return Command::FAILURE;
        }

        $this->info("✅ Movimentos sincronizados: {$results['movimentos']}");
        return Command::SUCCESS;
    }

    private function syncModulo(DataJuriSyncService $service, string $modulo): int
    {
        $modulo = strtolower($modulo);
        $this->info("Sincronizando módulo: {$modulo}");
        $this->newLine();

        if (!$service->authenticate()) {
            $this->error('❌ Falha na autenticação');
            return Command::FAILURE;
        }

        $count = 0;
        switch ($modulo) {
            case 'pessoas':
            case 'pessoa':
            case 'clientes':
                $count = $service->syncPessoas();
                break;
            case 'processos':
            case 'processo':
                $count = $service->syncProcessos();
                break;
            case 'movimentos':
            case 'movimento':
                $count = $service->syncMovimentos();
                break;
            case 'contratos':
            case 'contrato':
                $count = $service->syncContratos();
                break;
            case 'atividades':
            case 'atividade':
                $count = $service->syncAtividades();
                break;
            case 'horas':
            case 'horas_trabalhadas':
                $count = $service->syncHorasTrabalhadas();
                break;
            case 'ordens':
            case 'ordens_servico':
                $count = $service->syncOrdensServico();
                break;
            case 'fases':
            case 'fases_processo':
                $count = $service->syncFasesProcesso();
                break;
            default:
                $this->error("Módulo desconhecido: {$modulo}");
                $this->line('Módulos disponíveis: pessoas, processos, movimentos, contratos, atividades, horas, ordens, fases');
                return Command::FAILURE;
        }

        $this->info("✅ Sincronizados: {$count} registros");
        return Command::SUCCESS;
    }

    private function syncAll(DataJuriSyncService $service): int
    {
        $this->info('Executando sincronização COMPLETA...');
        $this->newLine();

        $results = $service->syncAll();

        if (!$results['success']) {
            $this->error('❌ Falha na sincronização');
            foreach ($results['errors'] as $error) {
                $this->error("   - {$error}");
            }
            return Command::FAILURE;
        }

        $this->info('✅ Sincronização concluída!');
        $this->newLine();
        $this->table(
            ['Módulo', 'Registros'],
            [
                ['Pessoas/Clientes', $results['pessoas']],
                ['Processos', $results['processos']],
                ['Fases', $results['fases']],
                ['Movimentos', $results['movimentos']],
                ['Contratos', $results['contratos']],
                ['Atividades', $results['atividades']],
                ['Horas Trabalhadas', $results['horas']],
                ['Ordens de Serviço', $results['ordens_servico']],
            ]
        );

        return Command::SUCCESS;
    }
}
