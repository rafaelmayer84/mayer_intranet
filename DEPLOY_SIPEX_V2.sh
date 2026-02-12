# =============================================================
# DEPLOY SIPEX HONORÁRIOS - MELHORIAS FORMULÁRIO v2
# Data: 11/02/2026
# Alterações:
#   1. Área do Direito → dropdown OAB/SC (25 categorias)
#   2. Tipo de Ação → dropdown dinâmico filtrado por área
#   3. Remoção de todas as menções a IA
#   4. Botão excluir propostas (admin/sócio)
#   5. Nova coluna tipo_acao no banco
# =============================================================

# =============================================================
# PASSO 0: BACKUP
# =============================================================
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

cp resources/views/precificacao/index.blade.php resources/views/precificacao/index.blade.php.bak.20260211v2
cp resources/views/precificacao/show.blade.php resources/views/precificacao/show.blade.php.bak.20260211v2
cp resources/views/precificacao/historico.blade.php resources/views/precificacao/historico.blade.php.bak.20260211v2
cp app/Http/Controllers/PrecificacaoController.php app/Http/Controllers/PrecificacaoController.php.bak.20260211v2
cp routes/_precificacao_routes.php routes/_precificacao_routes.php.bak.20260211v2
cp app/Models/PricingProposal.php app/Models/PricingProposal.php.bak.20260211v2

echo "=== Backup concluído ==="

# =============================================================
# PASSO 1: UPLOAD dos 4 scripts Python (via WinSCP para /tmp/)
# Copie os 4 arquivos para /tmp/ no servidor:
#   patch_01_view_index.py
#   patch_02_controller.py
#   patch_03_routes_migration_model.py
#   patch_04_views_show_historico.py
# =============================================================

# =============================================================
# PASSO 2: EXECUTAR Patch 03 (rotas + migration + model) PRIMEIRO
# (cria a migration e atualiza o model antes dos outros patches)
# =============================================================
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
python3 /tmp/patch_03_routes_migration_model.py

# =============================================================
# PASSO 3: EXECUTAR Migration
# =============================================================
php artisan migrate --force

# Verificar:
php artisan tinker --execute="echo Schema::hasColumn('pricing_proposals', 'tipo_acao') ? 'OK: coluna tipo_acao existe' : 'ERRO: coluna não criada';"

# =============================================================
# PASSO 4: EXECUTAR Patch 02 (controller)
# =============================================================
python3 /tmp/patch_02_controller.py

# =============================================================
# PASSO 5: EXECUTAR Patch 01 (view principal index.blade.php)
# =============================================================
python3 /tmp/patch_01_view_index.py

# =============================================================
# PASSO 6: EXECUTAR Patch 04 (views show + historico)
# =============================================================
python3 /tmp/patch_04_views_show_historico.py

# =============================================================
# PASSO 7: LIMPAR CACHE
# =============================================================
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

echo "=== Cache limpo ==="

# =============================================================
# PASSO 8: VERIFICAÇÃO
# =============================================================
# Verificar rota de exclusão:
php artisan route:list --name=precificacao.excluir

# Verificar que IA não aparece mais na view:
grep -c "com IA" resources/views/precificacao/index.blade.php
# Resultado esperado: 0

grep -c "tipo-acao" resources/views/precificacao/index.blade.php
# Resultado esperado: >0

echo "=== Verificação concluída ==="

# =============================================================
# PASSO 9: COMMIT NO GITHUB
# =============================================================
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
git add -A
git commit -m "feat: SIPEX Honorários v2 - dropdowns OAB/SC, tipo ação dinâmico, remoção IA, exclusão admin"
git push origin main

# =============================================================
# VERIFICAÇÃO FINAL:
# 1. Acessar /precificacao
# 2. Verificar dropdown "Área do Direito" com 25 opções
# 3. Selecionar uma área → "Tipo de Ação" deve popular dinamicamente
# 4. Verificar que não há menção a "IA" visível
# 5. Na tabela de propostas, admin deve ver botão "✕" para excluir
# 6. Testar exclusão de uma proposta
# =============================================================
