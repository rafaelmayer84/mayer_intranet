<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriSyncService;

/**
 * Comando de sincronização completa do DataJuri
 * 
 * Sincroniza TODOS os módulos:
 * - Pessoas → clientes
 * - Processos → processos
 * - Fases → fases_processo
 * - Movimentos → movimentos
 * - Contratos → contratos
 * - Atividades → atividades_datajuri
 * - Horas Trabalhadas → horas_trabalhadas_datajuri
 * - Ordens de Serviço → ordens_servico
 */
class SyncDataJuriCompleto extends Command
{
    protected $signature = 'sync:datajuri-completo 
                            {--modulo= : Sincronizar módulo específico (pessoas, processos, fases, movimentos, contratos, atividades, horas, os)}
                            {--silent : Modo silencioso}';
    
    protected $description = 'Sincroniza TODOS os módulos do DataJuri com o banco local';

    public function handle(DataJuriSyncService $service)
    {
        $modulo = $this->option('modulo');
        $silent = $this->option('silent');

        if (!$silent) {
            $this->info('🔄 Iniciando sincronização DataJuri COMPLETA...');
        }

        // Autenticação
        if (!$service->authenticate()) {
            $this->error('❌ Falha na autenticação com DataJuri');
            return 1;
        }

        if (!$silent) {
            $this->info('✅ Autenticado com sucesso');
        }

        // Sincronização por módulo ou completa
        if ($modulo) {
            $this->syncModuloEspecifico($service, $modulo, $silent);
        } else {
            $this->syncTodosModulos($service, $silent);
        }

        if (!$silent) {
            $this->info('');
            $this->info('✅ Sincronização concluída!');
        }

        return 0;
    }

    private function syncModuloEspecifico(DataJuriSyncService $service, string $modulo, bool $silent)
    {
        $map = [
            'pessoas' => ['syncPessoas', '👥 Pessoas', 'clientes'],
            'processos' => ['syncProcessos', '⚖️ Processos', 'processos'],
            'fases' => ['syncFasesProcesso', '📋 Fases', 'fases_processo'],
            'movimentos' => ['syncMovimentos', '💰 Movimentos', 'movimentos'],
            'contratos' => ['syncContratos', '📝 Contratos', 'contratos'],
            'atividades' => ['syncAtividades', '📅 Atividades', 'atividades_datajuri'],
            'horas' => ['syncHorasTrabalhadas', '⏱️ Horas Trabalhadas', 'horas_trabalhadas_datajuri'],
            'os' => ['syncOrdensServico', '📦 Ordens de Serviço', 'ordens_servico'],
        ];

        if (!isset($map[$modulo])) {
            $this->error("❌ Módulo inválido: {$modulo}");
            $this->info("Módulos válidos: " . implode(', ', array_keys($map)));
            return;
        }

        [$method, $label, $table] = $map[$modulo];

        if (!$silent) {
            $this->info("{$label}...");
        }

        $count = $service->$method();

        if (!$silent) {
            $this->info("   ✅ Processados: {$count} registros → {$table}");
        }
    }

    private function syncTodosModulos(DataJuriSyncService $service, bool $silent)
    {
        $modulos = [
            ['syncPessoas', '👥 Pessoas', 'clientes'],
            ['syncProcessos', '⚖️ Processos', 'processos'],
            ['syncFasesProcesso', '📋 Fases do Processo', 'fases_processo'],
            ['syncMovimentos', '💰 Movimentos Financeiros', 'movimentos'],
            ['syncContratos', '📝 Contratos', 'contratos'],
            ['syncAtividades', '📅 Atividades', 'atividades_datajuri'],
            ['syncHorasTrabalhadas', '⏱️ Horas Trabalhadas', 'horas_trabalhadas_datajuri'],
            ['syncOrdensServico', '📦 Ordens de Serviço', 'ordens_servico'],
        ];

        foreach ($modulos as [$method, $label, $table]) {
            if (!$silent) {
                $this->info("{$label}...");
            }

            try {
                $count = $service->$method();
                if (!$silent) {
                    $this->info("   ✅ Processados: {$count} registros → {$table}");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Erro: " . $e->getMessage());
            }
        }
    }
}
