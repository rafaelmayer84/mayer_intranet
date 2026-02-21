#!/usr/bin/env bash
set -euo pipefail

echo "== Sintaxe =="
php -l app/Services/SyncService.php
php -l app/Console/Commands/FinanceiroSyncContasReceber.php

echo "== SQL sanity (via tinker) =="
php artisan tinker --execute="
echo 'movimentos_total='.\DB::table('movimentos')->count().PHP_EOL;
echo 'contas_receber_total='.\DB::table('contas_receber')->count().PHP_EOL;
echo 'metas_total='.\DB::table('configuracoes')->where('chave','like','meta_%')->count().PHP_EOL;
"

echo "== Sync dry-run (20 itens) =="
php artisan financeiro:sync-contas-receber --dry-run --limit=20

echo "== Sync real =="
php artisan financeiro:sync-contas-receber --chunk=200

echo "== Prova pós-sync =="
php artisan tinker --execute="
echo 'contas_receber_total='.\DB::table('contas_receber')->count().PHP_EOL;
echo 'min_venc='.(\DB::table('contas_receber')->min('data_vencimento') ?? 'null').PHP_EOL;
echo 'max_venc='.(\DB::table('contas_receber')->max('data_vencimento') ?? 'null').PHP_EOL;
"

echo "== Stress 50x /api/visao-gerencial (precisa estar autenticado se endpoint exigir) =="
URL="${BASE_URL:-http://127.0.0.1}"
for i in {1..50}; do
  code=$(curl -s -o /dev/null -w "%{http_code}" "$URL/api/visao-gerencial?ano=2026&mes=1" || echo "000")
  echo "$code"
done
