#!/usr/bin/env python3
"""
FASE 2 - Remover/Arquivar arquivos PHP dedicados ao ESPO CRM
Move para pasta _archived_espo/ em vez de deletar
"""
import os, shutil

BASE = os.path.expanduser('~/domains/mayeradvogados.adv.br/public_html/Intranet')
os.chdir(BASE)

ARCHIVE_DIR = '_archived_espo_20260213'
os.makedirs(ARCHIVE_DIR, exist_ok=True)

print('='*60)
print('FASE 2 - ARQUIVAR ARQUIVOS ESPO DEDICADOS')
print('='*60)

# Arquivos a mover (não deletar, para segurança)
FILES_TO_ARCHIVE = [
    'app/Console/Commands/SyncEspoCrmCommand.php',
    'app/Console/Commands/CronSyncEspoCrm.php',
    'app/Console/Commands/CronSyncEspoCrm.php.bak_v2',
    'app/Console/Commands/CrmImportEspo.php',
    'app/Console/Commands/SyncOportunidadesCommand.php',
    'app/Services/Integration/EspoCrmService.php',
    'app/Services/EspoCrmService.php',
    'app/Services/EspoCrmOportunidadeService.php',
    'app/Services/EspoCrmSyncService.php',
    'app/Services/ETL/DataTransformerService.php',
    'app/Http/Controllers/Admin/SincronizacaoUnificadaController.php.bak_pre_espo',
    'config/espocrm.php',
    'config/espo_connection_snippet.php',
]

archived = 0
for filepath in FILES_TO_ARCHIVE:
    if os.path.exists(filepath):
        dest_dir = os.path.join(ARCHIVE_DIR, os.path.dirname(filepath))
        os.makedirs(dest_dir, exist_ok=True)
        dest = os.path.join(ARCHIVE_DIR, filepath)
        shutil.move(filepath, dest)
        print(f'  [MOVED] {filepath} → {ARCHIVE_DIR}/')
        archived += 1
    else:
        print(f'  [SKIP] {filepath} não existe')

print(f'\n  Total arquivados: {archived}')

# ── Verificar que nada quebrou (imports) ──
print('\n[2.2] Verificando se algum controller importa serviços removidos...')

# Os controllers que referenciam ESPO serão tratados na Fase 3
# Aqui só verificamos imports diretos dos arquivos removidos
critical_imports = [
    ('app/Services/Orchestration/IntegrationOrchestrator.php', 'EspoCrmService'),
    ('app/Http/Controllers/Admin/SincronizacaoController.php', 'EspoCrmSyncService'),
    ('app/Http/Controllers/Admin/SincronizacaoUnificadaController.php', 'EspoCrmService'),
    ('app/Services/LeadProcessingService.php', 'sendToEspoCRM'),
]

for filepath, search in critical_imports:
    if os.path.exists(filepath):
        with open(filepath, 'r') as f:
            if search in f.read():
                print(f'  [WARN] {filepath} ainda referencia {search} → será tratado na Fase 3')

print('\n' + '='*60)
print(f'✅ FASE 2 CONCLUÍDA - {archived} arquivos arquivados em {ARCHIVE_DIR}/')
print('='*60)
