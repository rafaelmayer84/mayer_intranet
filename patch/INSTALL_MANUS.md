# INSTALL_MANUS.md — Patch DataJuri Sync v2.0
# Data: 2026-02-05
# Autor: Claude (Engenheiro de Software Sênior)
# Objetivo: Corrigir sincronização via UI (botão retorna "0 processados")

## RESUMO DAS CORREÇÕES

1. **removerHtml REMOVIDO** — Parâmetro quebrava a API (retornava 0 rows)
2. **parseDecimal()** — Agora trata HTML `<span class='valor-positivo'>830,09</span>`
3. **codigo_plano** — Extrai via regex do campo `planoConta.nomeCompleto` quando `.codigo` vem vazio
4. **ClassificacaoService** — Chamado automaticamente no upsert de movimentos
5. **Auto-cleanup** — sync_runs stuck >30min são marcadas como failed automaticamente
6. **Tipo receita/despesa** — Extraído do HTML do campo `valorComSinal`

## ARQUIVOS ALTERADOS

| Arquivo | Ação |
|---------|------|
| `app/Services/DataJuriSyncOrchestrator.php` | **SUBSTITUIR** (reescrito) |
| `app/Services/DataJuriService.php` | **SUBSTITUIR** (removido removerHtml) |
| `app/Http/Controllers/Admin/SincronizacaoUnificadaController.php` | **SUBSTITUIR** (auto-cleanup) |

## PASSO A PASSO

### 1. BACKUP (obrigatório)

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Backup dos 3 arquivos
cp app/Services/DataJuriSyncOrchestrator.php app/Services/DataJuriSyncOrchestrator.php.bak_pre_v2_$(date +%Y%m%d_%H%M%S)
cp app/Services/DataJuriService.php app/Services/DataJuriService.php.bak_pre_v2_$(date +%Y%m%d_%H%M%S)
cp app/Http/Controllers/Admin/SincronizacaoUnificadaController.php app/Http/Controllers/Admin/SincronizacaoUnificadaController.php.bak_pre_v2_$(date +%Y%m%d_%H%M%S)
```

### 2. UPLOAD E EXTRAÇÃO

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Upload do patch_datajuri_sync_v2.tar.gz para o diretório Intranet/
# Depois extrair:
tar -xzf patch_datajuri_sync_v2.tar.gz
```

### 3. COPIAR ARQUIVOS

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Copiar arquivos corrigidos
cp patch/app/Services/DataJuriSyncOrchestrator.php app/Services/DataJuriSyncOrchestrator.php
cp patch/app/Services/DataJuriService.php app/Services/DataJuriService.php
cp patch/app/Http/Controllers/Admin/SincronizacaoUnificadaController.php app/Http/Controllers/Admin/SincronizacaoUnificadaController.php
```

### 4. LIMPAR SYNC_RUNS TRAVADAS

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

php artisan tinker --execute="
DB::table('sync_runs')->where('status', 'running')->update([
    'status' => 'failed',
    'mensagem' => 'Limpo manualmente antes do patch v2.0',
    'finished_at' => now(),
    'updated_at' => now(),
]);
echo 'sync_runs limpas: OK';
"
```

### 5. LIMPAR CACHE

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Limpar token OAuth antigo do cache
php artisan tinker --execute="
\Illuminate\Support\Facades\Cache::forget('datajuri_access_token');
\Illuminate\Support\Facades\Cache::forget('datajuri.access_token');
echo 'Cache OAuth limpo';
"
```

### 6. VALIDAÇÃO — SMOKE TEST

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

php artisan tinker --execute="
\$orch = new \App\Services\DataJuriSyncOrchestrator();
\$result = \$orch->smokeTest();
echo 'Token: ' . (\$result['token'] ? 'OK' : 'FALHA') . PHP_EOL;
echo 'Modulos: ' . (\$result['modulos'] ? 'OK' : 'FALHA') . PHP_EOL;
echo 'Pessoa: ' . (\$result['pessoa'] ? 'OK (' . \$result['pessoa_count'] . ')' : 'FALHA') . PHP_EOL;
echo 'Movimento: ' . (\$result['movimento'] ? 'OK (' . \$result['movimento_count'] . ')' : 'FALHA') . PHP_EOL;
echo 'Sample valorComSinal: ' . (\$result['sample_raw_valorComSinal'] ?? 'N/A') . PHP_EOL;
echo 'Sample valor parsed: ' . (\$result['sample_parsed_valor'] ?? 'N/A') . PHP_EOL;
echo 'Sample codigo parsed: ' . (\$result['sample_codigoParsed'] ?? 'N/A') . PHP_EOL;
"
```

**Resultado esperado:**
```
Token: OK
Modulos: OK
Pessoa: OK (~3000)
Movimento: OK (~5855)
Sample valorComSinal: <span class='valor-positivo'>XXX,XX</span>
Sample valor parsed: XXX.XX
Sample codigo parsed: 3.XX.XX.XX
```

### 7. VALIDAÇÃO — SYNC DE 1 MÓDULO (Pessoa, rápido)

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

php artisan tinker --execute="
\$orch = new \App\Services\DataJuriSyncOrchestrator();
\$orch->startRun('test_pessoa');
\$result = \$orch->syncModule('Pessoa');
\$orch->finishRun('completed');
echo 'Pessoa sync: ' . \$result['processados'] . ' processados, ' . \$result['criados'] . ' criados, ' . \$result['atualizados'] . ' atualizados' . PHP_EOL;
"
```

**Resultado esperado:** ~3000 processados

### 8. VALIDAÇÃO — SYNC MOVIMENTOS (crítico)

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

php artisan tinker --execute="
\$orch = new \App\Services\DataJuriSyncOrchestrator();
\$orch->startRun('test_movimento');
\$result = \$orch->syncModule('Movimento');
\$orch->finishRun('completed');
echo 'Movimento sync: ' . \$result['processados'] . ' processados, ' . \$result['criados'] . ' criados, ' . \$result['atualizados'] . ' atualizados, ' . \$result['erros'] . ' erros' . PHP_EOL;

// Verificar classificação
\$stats = DB::table('movimentos')->where('origem', 'datajuri')->selectRaw('classificacao, count(*) as total')->groupBy('classificacao')->get();
echo 'Classificações:' . PHP_EOL;
foreach (\$stats as \$s) { echo '  ' . \$s->classificacao . ': ' . \$s->total . PHP_EOL; }
"
```

**Resultado esperado:** ~5855 processados, classificações RECEITA_PF, RECEITA_PJ, DESPESA, etc.

### 9. VALIDAÇÃO — VIA NAVEGADOR (UI)

1. Acessar `https://intranet.mayeradvogados.adv.br/admin/sincronizacao-unificada`
2. Clicar em **"Smoke Test"** → deve retornar Token OK, Módulos OK
3. Clicar em **"Sincronizar Tudo"** → deve processar todos os módulos

### 10. LIMPEZA

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Remover patch extraído
rm -rf patch/
rm -f patch_datajuri_sync_v2.tar.gz
```

## ROLLBACK (se necessário)

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Restaurar backups (substituir TIMESTAMP pelo valor correto)
cp app/Services/DataJuriSyncOrchestrator.php.bak_pre_v2_TIMESTAMP app/Services/DataJuriSyncOrchestrator.php
cp app/Services/DataJuriService.php.bak_pre_v2_TIMESTAMP app/Services/DataJuriService.php
cp app/Http/Controllers/Admin/SincronizacaoUnificadaController.php.bak_pre_v2_TIMESTAMP app/Http/Controllers/Admin/SincronizacaoUnificadaController.php

php artisan config:clear && php artisan cache:clear
```
