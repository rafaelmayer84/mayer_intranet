#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost}"
ANO="${ANO:-2026}"
MES="${MES:-1}"

echo "== PHP =="
php -v || true

echo
echo "== PHP LINT (arquivos do patch) =="
php -l app/Services/SyncService.php
php -l app/Http/Controllers/SyncController.php
php -l routes/web.php
php -l app/Console/Commands/FinanceiroBackfillClassificacao.php
php -l app/Console/Kernel.php

echo
echo "== Artisan =="
php artisan about
php artisan optimize:clear

echo
echo "== Rotas (filtro) =="
php artisan route:list | egrep -i "visao-gerencial|kpis|metas|sync/contas-receber|home" || true

echo
echo "== SQL sanity (requer env DB_*) =="
if command -v mysql >/dev/null 2>&1; then
  mysql -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -e "SELECT COUNT(*) AS movimentos_total FROM movimentos;"
  mysql -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -e "SELECT COUNT(*) AS contas_receber_total FROM contas_receber;"
  mysql -h "${DB_HOST}" -u "${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" -e "SELECT COUNT(*) AS metas_total FROM configuracoes WHERE chave LIKE 'meta\_%';"
else
  echo "mysql client não encontrado; pulei SQL sanity."
fi

echo
echo "== API check (/api/visao-gerencial) =="
curl -s "${BASE_URL}/api/visao-gerencial?ano=${ANO}&mes=${MES}" | head -c 2000 && echo

echo
echo "== Stress 50x =="
ok=0
fail=0
for i in $(seq 1 50); do
  code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}/api/visao-gerencial?ano=${ANO}&mes=${MES}")
  if [ "$code" = "200" ]; then ok=$((ok+1)); else fail=$((fail+1)); fi
  echo "$i $code"
done
echo "OK=$ok FAIL=$fail"
test "$fail" -eq 0
