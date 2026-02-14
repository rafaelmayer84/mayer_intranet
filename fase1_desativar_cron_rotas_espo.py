#!/usr/bin/env python3
"""
FASE 1 - Desativar Cron Jobs ESPO e Rotas ESPO
Impacto: Para a sincronização automática e remove endpoints ESPO
"""
import os, sys, shutil

BASE = os.path.expanduser('~/domains/mayeradvogados.adv.br/public_html/Intranet')
os.chdir(BASE)
erros = []

def patch(filepath, old, new, label):
    with open(filepath, 'r') as f:
        content = f.read()
    if old not in content:
        print(f'  [SKIP] {label} - pattern not found in {filepath}')
        return False
    content = content.replace(old, new)
    with open(filepath, 'w') as f:
        f.write(content)
    print(f'  [OK] {label}')
    return True

def backup(filepath):
    bak = filepath + '.bak_pre_espo_removal'
    if not os.path.exists(bak):
        shutil.copy2(filepath, bak)
        print(f'  [BACKUP] {filepath}')

print('='*60)
print('FASE 1 - DESATIVAR CRON JOBS E ROTAS ESPO')
print('='*60)

# ── 1.1 routes/console.php: remover cron ESPO ──
print('\n[1.1] Removendo cron jobs ESPO de routes/console.php...')
f = 'routes/console.php'
backup(f)

OLD_CRON = """// ESPO CRM → 2x/dia (9h, 17h) - Horário de Brasília
Schedule::command('cron:sync-espo')
    ->dailyAt('09:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-espo.log'));

Schedule::command('cron:sync-espo')
    ->dailyAt('17:00')
    ->timezone('America/Sao_Paulo')
    ->appendOutputTo(storage_path('logs/cron-espo.log'));"""

NEW_CRON = """// ESPO CRM removido em 13/02/2026 - substituído por CRM Nativo"""

if not patch(f, OLD_CRON, NEW_CRON, 'Cron ESPO removido'):
    erros.append('Cron ESPO não encontrado no console.php')

# ── 1.2 routes/sincronizacao-unificada.php: remover rotas ESPO ──
print('\n[1.2] Removendo rotas ESPO de sincronizacao-unificada.php...')
f = 'routes/sincronizacao-unificada.php'
backup(f)

OLD_ROUTES_ESPO = """    Route::post('/espocrm/test', [SincronizacaoUnificadaController::class, 'espocrmTest'])->name('espocrm-test');
    Route::post('/espocrm/sync', [SincronizacaoUnificadaController::class, 'espocrmSync'])->name('espocrm-sync');"""

NEW_ROUTES_ESPO = """    // ESPO CRM rotas removidas em 13/02/2026 - substituído por CRM Nativo"""

if not patch(f, OLD_ROUTES_ESPO, NEW_ROUTES_ESPO, 'Rotas ESPO sync-unificada removidas'):
    erros.append('Rotas ESPO não encontradas em sincronizacao-unificada.php')

# ── 1.3 routes/web.php: remover rota ESPO ──
print('\n[1.3] Removendo rota ESPO de routes/web.php...')
f = 'routes/web.php'
backup(f)

OLD_WEB_ESPO = '    Route::post("/integracao/sincronizar-espocrm", [App\\Http\\Controllers\\IntegracaoController::class, "sincronizarEspoCrm"])->name("integration.sync.espocrm");'
NEW_WEB_ESPO = '    // ESPO CRM rota removida em 13/02/2026'

if not patch(f, OLD_WEB_ESPO, NEW_WEB_ESPO, 'Rota web.php ESPO removida'):
    # Tentar variação com aspas simples
    OLD_WEB_ESPO2 = """    Route::post("/integracao/sincronizar-espocrm", [App\Http\Controllers\IntegracaoController::class, "sincronizarEspoCrm"])->name("integration.sync.espocrm");"""
    if not patch(f, OLD_WEB_ESPO2, NEW_WEB_ESPO, 'Rota web.php ESPO removida (v2)'):
        print('  [WARN] Rota ESPO em web.php precisa remoção manual')

# ── 1.4 routes/api.php: remover rota API ESPO ──
print('\n[1.4] Removendo rota ESPO de routes/api.php...')
f = 'routes/api.php'
if os.path.exists(f):
    backup(f)
    with open(f, 'r') as fh:
        content = fh.read()
    if "sincronizar-espocrm" in content:
        # Remover a linha
        lines = content.split('\n')
        new_lines = []
        for line in lines:
            if 'sincronizar-espocrm' in line or 'sincronizarEspoCrm' in line:
                new_lines.append('    // ESPO CRM rota removida em 13/02/2026')
                print('  [OK] Rota api.php ESPO comentada')
            else:
                new_lines.append(line)
        with open(f, 'w') as fh:
            fh.write('\n'.join(new_lines))
    else:
        print('  [SKIP] Nenhuma rota ESPO em api.php')

# ── 1.5 Kernel.php: remover registro do command ──
print('\n[1.5] Removendo CronSyncEspoCrm do Kernel.php...')
f = 'app/Console/Kernel.php'
if os.path.exists(f):
    backup(f)
    with open(f, 'r') as fh:
        content = fh.read()
    
    # Remover a linha do registro do command
    if 'CronSyncEspoCrm' in content:
        content = content.replace("        Commands\\CronSyncEspoCrm::class,\n", "        // CronSyncEspoCrm removido em 13/02/2026\n")
        
        # Remover schedule do ESPO no Kernel (se existir duplicado)
        # Bloco completo do ESPO no Kernel
        old_kernel_espo = """        // ESPO CRM → 2x/dia (9h, 17h)
        $schedule->command('cron:sync-espo')
            ->dailyAt('09:00')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-espo.log'));

        $schedule->command('cron:sync-espo')
            ->dailyAt('17:00')
            ->timezone('America/Sao_Paulo')
            ->appendOutputTo(storage_path('logs/cron-espo.log'));"""
        
        if old_kernel_espo in content:
            content = content.replace(old_kernel_espo, "        // ESPO CRM cron removido em 13/02/2026")
            print('  [OK] Schedule ESPO removido do Kernel.php')
        
        with open(f, 'w') as fh:
            fh.write(content)
        print('  [OK] CronSyncEspoCrm removido do Kernel.php')
    else:
        print('  [SKIP] CronSyncEspoCrm não encontrado no Kernel')

# ── RELATÓRIO ──
print('\n' + '='*60)
if erros:
    print(f'⚠ FASE 1 concluída com {len(erros)} avisos:')
    for e in erros:
        print(f'  - {e}')
else:
    print('✅ FASE 1 CONCLUÍDA COM SUCESSO')
print('='*60)
