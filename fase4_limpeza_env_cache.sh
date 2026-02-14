#!/bin/bash
# FASE 4 - Limpeza de .env e cache
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

echo "============================================"
echo "FASE 4 - LIMPEZA .ENV E CACHE"
echo "============================================"

echo ""
echo "[4.1] Comentando variáveis ESPO no .env..."
cp .env .env.bak_pre_espo_removal

# Comentar linhas ESPO
sed -i 's/^ESPOCRM_URL=/#ESPO_REMOVED# ESPOCRM_URL=/' .env
sed -i 's/^ESPOCRM_API_KEY=/#ESPO_REMOVED# ESPOCRM_API_KEY=/' .env
sed -i 's/^# ESPO CRM MySQL/#ESPO_REMOVED# ESPO CRM MySQL/' .env
sed -i 's/^ESPO_DB_HOST=/#ESPO_REMOVED# ESPO_DB_HOST=/' .env
sed -i 's/^ESPO_DB_DATABASE=/#ESPO_REMOVED# ESPO_DB_DATABASE=/' .env
sed -i 's/^ESPO_DB_USERNAME=/#ESPO_REMOVED# ESPO_DB_USERNAME=/' .env
sed -i 's/^ESPO_DB_PASSWORD=/#ESPO_REMOVED# ESPO_DB_PASSWORD=/' .env

echo "[OK] Variáveis ESPO comentadas no .env"

echo ""
echo "[4.2] Limpando cache Laravel..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo ""
echo "[4.3] Verificando rotas (não deve ter espocrm)..."
php artisan route:list 2>/dev/null | grep -i espo || echo "  [OK] Nenhuma rota ESPO ativa"

echo ""
echo "[4.4] Verificando cron schedule..."
php artisan schedule:list 2>/dev/null | grep -i espo || echo "  [OK] Nenhum cron ESPO ativo"

echo ""
echo "============================================"
echo "FASE 4 CONCLUÍDA"
echo "============================================"
