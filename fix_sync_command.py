#!/usr/bin/env python3
"""
FIX: SyncDataJuriCompleto + Array to string conversion
=======================================================
Dois problemas:
  1. Comando chama 5 métodos inexistentes no DataJuriSyncService
  2. syncPessoas/syncProcessos faz substr() em campos que são arrays

Solução:
  - Reescrever SyncDataJuriCompleto.php para usar Orchestrator nos 5 módulos faltantes
  - Adicionar safe_string() no Service para tratar campos array

USO:
  cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
  python3 fix_sync_command.py
"""

import os
import sys
import shutil
from datetime import datetime

BASE_DIR = os.path.dirname(os.path.abspath(__file__))

def backup(filepath):
    full = os.path.join(BASE_DIR, filepath)
    if os.path.exists(full):
        bak = full + f'.bak_{datetime.now().strftime("%Y%m%d_%H%M%S")}'
        shutil.copy2(full, bak)
        print(f"  📦 Backup: {bak}")
        return True
    print(f"  ⚠ Arquivo não encontrado: {filepath}")
    return False


def fix_sync_command():
    """Reescreve SyncDataJuriCompleto.php para usar Orchestrator nos módulos sem método no Service"""
    filepath = os.path.join(BASE_DIR, 'app/Console/Commands/SyncDataJuriCompleto.php')

    new_content = r'''<?php

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
'''

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(new_content.lstrip('\n'))

    print("  ✔ SyncDataJuriCompleto.php REESCRITO (Service + Orchestrator híbrido)")


def fix_array_to_string():
    """Corrige 'Array to string conversion' no syncPessoasComPaginacao e syncProcessosComPaginacao"""
    filepath = os.path.join(BASE_DIR, 'app/Services/DataJuriSyncService.php')

    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    # ─── FIX 1: syncPessoas - campo 'telefone' pode ser array ───
    old_pessoas = "'telefone' => substr($pessoa['telefone'] ?? '', 0, 20),"
    new_pessoas = "'telefone' => substr(is_array($pessoa['telefone'] ?? null) ? ($pessoa['telefone'][0]['numero'] ?? json_encode($pessoa['telefone'])) : ($pessoa['telefone'] ?? ''), 0, 20),"

    if old_pessoas in content:
        content = content.replace(old_pessoas, new_pessoas, 1)
        print("  ✔ FIX telefone (Pessoas): tratamento de array")
    else:
        # Tentar variação com aspas duplas ou espaçamento diferente
        # Buscar o padrão com regex-like approach
        import re
        pattern = r"'telefone'\s*=>\s*substr\(\s*\$pessoa\['telefone'\]\s*\?\?\s*'',\s*0,\s*20\)"
        match = re.search(pattern, content)
        if match:
            content = content[:match.start()] + "'telefone' => substr(is_array($pessoa['telefone'] ?? null) ? ($pessoa['telefone'][0]['numero'] ?? json_encode($pessoa['telefone'])) : ($pessoa['telefone'] ?? ''), 0, 20)" + content[match.end():]
            print("  ✔ FIX telefone (Pessoas): tratamento de array (regex)")
        else:
            print("  ⚠ Trecho 'telefone' não encontrado no syncPessoas — verificar manualmente")

    # ─── FIX 2: syncPessoas - campo 'tipo' pode ser array/objeto ───
    old_tipo = "'tipo' => substr($pessoa['tipo'] ?? 'PF', 0, 50),"
    new_tipo = "'tipo' => substr(is_string($pessoa['tipo'] ?? null) ? $pessoa['tipo'] : ($pessoa['tipo']['descricao'] ?? 'PF'), 0, 50),"

    if old_tipo in content:
        content = content.replace(old_tipo, new_tipo, 1)
        print("  ✔ FIX tipo (Pessoas): tratamento de objeto")
    else:
        print("  ⚠ Trecho 'tipo' não encontrado no syncPessoas — pode já estar corrigido")

    # ─── FIX 3: syncProcessos - campo 'cliente' pode ser array/objeto ───
    old_cliente = "$clienteNome = $processo['cliente.nome'] ?? $processo['cliente'] ?? null;"
    new_cliente = """$clienteRaw = $processo['cliente.nome'] ?? $processo['cliente'] ?? null;
                            $clienteNome = is_array($clienteRaw) ? ($clienteRaw['nome'] ?? null) : $clienteRaw;"""

    if old_cliente in content:
        content = content.replace(old_cliente, new_cliente, 1)
        print("  ✔ FIX cliente (Processos): tratamento de objeto")
    else:
        # Variação: verificar se o trecho existe com espaçamento diferente
        if "processo['cliente.nome']" in content and "processo['cliente']" in content:
            # Approach mais flexível
            import re
            pattern = r"\$clienteNome\s*=\s*\$processo\['cliente\.nome'\]\s*\?\?\s*\$processo\['cliente'\]\s*\?\?\s*null;"
            match = re.search(pattern, content)
            if match:
                replacement = """$clienteRaw = $processo['cliente.nome'] ?? $processo['cliente'] ?? null;
                            $clienteNome = is_array($clienteRaw) ? ($clienteRaw['nome'] ?? null) : $clienteRaw;"""
                content = content[:match.start()] + replacement + content[match.end():]
                print("  ✔ FIX cliente (Processos): tratamento de objeto (regex)")
            else:
                print("  ⚠ Trecho 'clienteNome' não encontrado no syncProcessos — verificar manualmente")
        else:
            print("  ⚠ Trecho 'clienteNome' não encontrado no syncProcessos")

    # ─── FIX 4: syncProcessos - campos 'descricao' e 'status' podem ser arrays ───
    old_descricao = "'descricao' => substr($processo['descricao'] ?? '', 0, 500),"
    new_descricao = "'descricao' => substr(is_string($processo['descricao'] ?? null) ? ($processo['descricao'] ?? '') : json_encode($processo['descricao']), 0, 500),"

    if old_descricao in content:
        content = content.replace(old_descricao, new_descricao, 1)
        print("  ✔ FIX descricao (Processos): tratamento de array")

    old_status = "'status' => substr($processo['status'] ?? $processo['situacao'] ?? 'Ativo', 0, 50),"
    new_status = """'status' => substr(
                                        is_string($processo['status'] ?? null) ? $processo['status'] :
                                        (is_string($processo['situacao'] ?? null) ? $processo['situacao'] : 'Ativo'),
                                        0, 50
                                    ),"""

    if old_status in content:
        content = content.replace(old_status, new_status, 1)
        print("  ✔ FIX status (Processos): tratamento de array")

    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)


def main():
    print("=" * 60)
    print("FIX: Sync DataJuri — Command + Array to String")
    print("=" * 60)

    # Backups
    print("\n[PASSO 0] Backups...")
    backup('app/Console/Commands/SyncDataJuriCompleto.php')
    backup('app/Services/DataJuriSyncService.php')

    # Fix 1: Command
    print("\n[PASSO 1] Reescrevendo SyncDataJuriCompleto.php...")
    fix_sync_command()

    # Fix 2: Array to string
    print("\n[PASSO 2] Corrigindo 'Array to string conversion' no DataJuriSyncService.php...")
    fix_array_to_string()

    # Instruções finais
    print("\n" + "=" * 60)
    print("PRÓXIMOS PASSOS:")
    print("=" * 60)
    print("""
1. Validar sintaxe:
   php -l app/Console/Commands/SyncDataJuriCompleto.php
   php -l app/Services/DataJuriSyncService.php

2. Limpar cache:
   php artisan cache:clear && php artisan config:clear

3. Testar sync completo:
   php artisan sync:datajuri-completo

4. Testar módulo específico:
   php artisan sync:datajuri-completo --modulo=fases
   php artisan sync:datajuri-completo --modulo=andamentos

5. Se tudo ok, commit:
   git add -A && git commit -m "fix: sync command usa Orchestrator + array-to-string" && git push
""")


if __name__ == '__main__':
    main()
