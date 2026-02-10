<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriSyncService;
use App\Services\DataJuriSyncOrchestrator;
use Illuminate\Support\Facades\Log;

/**
 * Comando de sincronização completa do DataJuri
 *
 * Usa DataJuriSyncService para Pessoas/Processos/Movimentos (métodos dedicados)
 * Usa DataJuriSyncOrchestrator para os demais módulos (motor genérico via config)
 */
class SyncDataJuriCompleto extends Command
{
    protected $signature = 'sync:datajuri-completo
                            {--modulo= : Sincronizar módulo específico (pessoas, processos, fases, movimentos, contratos, atividades, horas, os, contasreceber, andamentos)}
                            {--silent : Modo silencioso}';

    protected $description = 'Sincroniza TODOS os módulos do DataJuri com o banco local';

    /**
     * Mapa de módulos:
     *   'alias' => [tipo, label, identificador]
     *
     *   tipo 'service' = usa DataJuriSyncService->$metodo()
     *   tipo 'orchestrator' = usa DataJuriSyncOrchestrator->syncModule($modulo)
     */
    private function getModulosMap(): array
    {
        return [
            'pessoas'       => ['service',      'syncPessoas',      '👥 Pessoas',              'clientes'],
            'processos'     => ['service',      'syncProcessos',    '⚖️ Processos',            'processos'],
            'movimentos'    => ['service',      'syncMovimentos',   '💰 Movimentos',           'movimentos'],
            'fases'         => ['orchestrator', 'Fase',             '📋 Fases do Processo',    'fases_processo'],
            'contratos'     => ['orchestrator', 'Contrato',         '📝 Contratos',            'contratos'],
            'atividades'    => ['orchestrator', 'Atividade',        '📅 Atividades',           'atividades_datajuri'],
            'horas'         => ['orchestrator', 'HoraTrabalhada',   '⏱️ Horas Trabalhadas',    'horas_trabalhadas_datajuri'],
            'os'            => ['orchestrator', 'OrdemServico',     '📦 Ordens de Serviço',    'ordens_servico'],
            'contasreceber' => ['orchestrator', 'ContasReceber',    '💳 Contas a Receber',     'contas_receber'],
            'andamentos'    => ['orchestrator', 'AndamentoFase',    '📄 Andamentos de Fase',   'andamentos_fase'],
        ];
    }

    public function handle(DataJuriSyncService $service)
    {
        $modulo = $this->option('modulo');
        $silent = $this->option('silent');

        if (!$silent) {
            $this->info('🔄 Iniciando sincronização DataJuri COMPLETA...');
        }

        // Autenticação via Service
        if (!$service->authenticate()) {
            $this->error('❌ Falha na autenticação com DataJuri');
            return 1;
        }

        if (!$silent) {
            $this->info('✅ Autenticado com sucesso');
        }

        $map = $this->getModulosMap();

        if ($modulo) {
            // Módulo específico
            if (!isset($map[$modulo])) {
                $this->error("❌ Módulo inválido: {$modulo}");
                $this->info("Módulos válidos: " . implode(', ', array_keys($map)));
                return 1;
            }
            $this->executarModulo($service, $map[$modulo], $silent);
        } else {
            // Todos os módulos
            foreach ($map as $alias => $config) {
                $this->executarModulo($service, $config, $silent);
            }
        }

        if (!$silent) {
            $this->info('');
            $this->info('✅ Sincronização concluída!');
        }

        return 0;
    }

    /**
     * Executa sync de um módulo usando Service ou Orchestrator conforme tipo
     */
    private function executarModulo(DataJuriSyncService $service, array $config, bool $silent): void
    {
        [$tipo, $identificador, $label, $tabela] = $config;

        if (!$silent) {
            $this->info("{$label}...");
        }

        try {
            if ($tipo === 'service') {
                // Usa método dedicado do DataJuriSyncService
                $result = $service->$identificador();
                $count = $result['count'] ?? 0;
                $errors = $result['errors'] ?? 0;

                if (!$silent) {
                    $msg = "   ✅ {$count} registros → {$tabela}";
                    if ($errors > 0) {
                        $msg .= " ({$errors} erros)";
                    }
                    $this->info($msg);
                }
            } else {
                // Usa Orchestrator genérico (config/datajuri.php)
                $orchestrator = app(DataJuriSyncOrchestrator::class);
                $result = $orchestrator->syncModule($identificador);
                $count = $result['processados'] ?? $result['count'] ?? 0;
                $created = $result['criados'] ?? 0;
                $updated = $result['atualizados'] ?? 0;
                $errors = $result['erros'] ?? 0;

                if (!$silent) {
                    $msg = "   ✅ {$count} processados";
                    if ($created > 0) $msg .= ", {$created} novos";
                    if ($updated > 0) $msg .= ", {$updated} atualizados";
                    if ($errors > 0) $msg .= ", {$errors} erros";
                    $msg .= " → {$tabela}";
                    $this->info($msg);
                }
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Erro: " . $e->getMessage());
            Log::error("sync:datajuri-completo [{$label}]: " . $e->getMessage());
        }
    }
}
