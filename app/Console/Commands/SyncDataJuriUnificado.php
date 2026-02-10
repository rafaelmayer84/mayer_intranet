<?php

namespace App\Console\Commands;

use App\Services\DataJuriSyncOrchestrator;
use Illuminate\Console\Command;

class SyncDataJuriUnificado extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:datajuri-unificado 
                            {--modulo= : Sincronizar apenas um módulo específico}
                            {--reprocessar-financeiro : Executar full refresh do financeiro}
                            {--smoke-test : Apenas testar conexão com a API}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincronização unificada com API DataJuri';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orchestrator = new DataJuriSyncOrchestrator();
        
        // Smoke test
        if ($this->option('smoke-test')) {
            return $this->runSmokeTest($orchestrator);
        }
        
        // Reprocessar financeiro
        if ($this->option('reprocessar-financeiro')) {
            return $this->runReprocessarFinanceiro($orchestrator);
        }
        
        // Módulo específico
        if ($modulo = $this->option('modulo')) {
            return $this->runSyncModule($orchestrator, $modulo);
        }
        
        // Sincronização completa
        return $this->runSyncAll($orchestrator);
    }
    
    /**
     * Executar smoke test
     */
    protected function runSmokeTest(DataJuriSyncOrchestrator $orchestrator): int
    {
        $this->info('== DataJuri Smoke Test ==');
        
        $results = $orchestrator->smokeTest();
        
        $this->line('Token: ' . ($results['token'] ? '✅ OK' : '❌ FALHOU'));
        $this->line('Módulos: ' . ($results['modulos'] ? '✅ OK' : '❌ FALHOU'));
        $this->line('Pessoa: ' . ($results['pessoa'] ? "✅ OK ({$results['pessoa_count']} registros)" : '❌ FALHOU'));
        $this->line('Movimento: ' . ($results['movimento'] ? "✅ OK ({$results['movimento_count']} registros)" : '❌ FALHOU'));
        
        if (!empty($results['errors'])) {
            $this->error('Erros: ' . implode(', ', $results['errors']));
        }
        
        return $results['token'] && $results['modulos'] ? 0 : 1;
    }
    
    /**
     * Executar reprocessamento financeiro
     */
    protected function runReprocessarFinanceiro(DataJuriSyncOrchestrator $orchestrator): int
    {
        $this->info('== Reprocessar Financeiro ==');
        $this->warn('Este processo irá:');
        $this->line('  1. Marcar todos os movimentos DataJuri como stale');
        $this->line('  2. Sincronizar todos os movimentos da API');
        $this->line('  3. Deletar movimentos que não existem mais na API');
        
        if (!$this->confirm('Deseja continuar?', true)) {
            $this->info('Operação cancelada.');
            return 0;
        }
        
        $progressCallback = function ($modulo, $page, $total, $status) {
            if ($status === 'marking_stale') {
                $this->line('Marcando registros como stale...');
            } elseif ($status === 'processing') {
                $this->line("Processando página {$page}/{$total}...");
            } elseif ($status === 'cleanup_complete') {
                $this->line('Limpeza concluída.');
            }
        };
        
        try {
            $results = $orchestrator->reprocessarFinanceiro($progressCallback);
            
            $this->newLine();
            $this->info('== Resultado ==');
            $this->line("Processados: {$results['processados']}");
            $this->line("Criados: {$results['criados']}");
            $this->line("Atualizados: {$results['atualizados']}");
            $this->line("Deletados: {$results['deletados']}");
            $this->line("Erros: {$results['erros']}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Sincronizar módulo específico
     */
    protected function runSyncModule(DataJuriSyncOrchestrator $orchestrator, string $modulo): int
    {
        $this->info("== Sincronizando módulo: {$modulo} ==");
        
        $progressCallback = function ($mod, $page, $total, $status) {
            if ($status === 'processing') {
                $this->line("Página {$page}/{$total}...");
            }
        };
        
        try {
            $orchestrator->startRun('incremental');
            $results = $orchestrator->syncModule($modulo, $progressCallback);
            $orchestrator->finishRun('completed');
            
            $this->newLine();
            $this->info('== Resultado ==');
            $this->line("Processados: {$results['processados']}");
            $this->line("Criados: {$results['criados']}");
            $this->line("Atualizados: {$results['atualizados']}");
            $this->line("Erros: {$results['erros']}");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            return 1;
        }
    }
    
    /**
     * Sincronização completa
     */
    protected function runSyncAll(DataJuriSyncOrchestrator $orchestrator): int
    {
        $this->info('== Sincronização Completa DataJuri ==');
        
        $modulos = collect(config('datajuri.modulos'))
            ->filter(fn($c) => $c['enabled'])
            ->keys()
            ->toArray();
        
        $this->line('Módulos a sincronizar: ' . implode(', ', $modulos));
        $this->newLine();
        
        $progressCallback = function ($modulo, $page, $total, $status) {
            if ($status === 'starting') {
                $this->info("→ Iniciando: {$modulo}");
            } elseif ($status === 'processing' && $page % 5 === 0) {
                $this->line("  Página {$page}/{$total}...");
            } elseif ($status === 'completed') {
                $this->line("  ✅ Concluído");
            }
        };
        
        try {
            $results = $orchestrator->syncAll($progressCallback);
            
            $this->newLine();
            $this->info('== Resultado Final ==');
            $this->line("Total processados: {$results['total_processados']}");
            $this->line("Total criados: {$results['total_criados']}");
            $this->line("Total atualizados: {$results['total_atualizados']}");
            $this->line("Total erros: {$results['total_erros']}");
            
            $this->newLine();
            $this->info('Por módulo:');
            foreach ($results['modulos'] as $modulo => $r) {
                $this->line("  {$modulo}: {$r['processados']} processados, {$r['criados']} criados, {$r['atualizados']} atualizados");
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Erro: ' . $e->getMessage());
            return 1;
        }
    }
}
