# GDP Fase 5 — Acordo de Desempenho
# Deploy: 17/02/2026

## CONTEÚDO DO PACOTE

```
deploy_gdp_acordo.py                              → Script Python (patches rotas + controller)
database/seeders/GdpSeederInaugural.php            → Seeder inaugural (malha de metas)
resources/views/gdp/acordo.blade.php               → Tela admin: grid editável de metas
resources/views/gdp/acordo-visualizar.blade.php    → Tela advogado: visualiza e aceita
resources/views/gdp/acordo-print.blade.php         → Impressão: padrão jurídico Mayer Albanez
```

## DEPLOY — PASSO A PASSO

```bash
# 0. Conectar
ssh u492856976@153.92.223.23 -p 65002
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# 1. Upload do .tar (via scp de outra janela ou upload Hostinger)
# Depois extrair:
tar xf gdp_acordo_deploy.tar

# 2. Copiar views para o lugar certo
cp resources/views/gdp/acordo.blade.php resources/views/gdp/acordo.blade.php
cp resources/views/gdp/acordo-visualizar.blade.php resources/views/gdp/acordo-visualizar.blade.php
cp resources/views/gdp/acordo-print.blade.php resources/views/gdp/acordo-print.blade.php

# (Se extraiu na raiz do Intranet, os arquivos já vão para o lugar certo)

# 3. Copiar seeder
cp database/seeders/GdpSeederInaugural.php database/seeders/GdpSeederInaugural.php

# 4. Executar patches (rotas + controller)
python3 deploy_gdp_acordo.py

# 5. Executar seeder
php artisan db:seed --class=GdpSeederInaugural --force

# 6. Limpar caches
php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear

# 7. Verificar rotas
php artisan route:list | grep acordo

# 8. Verificar metas criadas
mysql -u u492856976_intranet -p u492856976_intranet -e "SELECT u.name, COUNT(*) as metas FROM gdp_metas_individuais gmi JOIN users u ON u.id = gmi.user_id GROUP BY u.name;"

# 9. Testar no browser
# https://intranet.mayeradvogados.adv.br/gdp/acordo

# 10. Git commit
git add -A
git commit -m "GDP Fase 5: Acordo de Desempenho + Seeder inaugural"
git push origin main
```

## ROTAS ADICIONADAS

| Método | Rota                           | Função                    |
|--------|--------------------------------|---------------------------|
| GET    | /gdp/acordo                    | Grid editável (admin)     |
| POST   | /gdp/acordo                    | Salvar metas (AJAX)       |
| GET    | /gdp/acordo/{userId}/visualizar| Advogado visualiza acordo |
| POST   | /gdp/acordo/{userId}/aceitar   | Advogado aceita (AJAX)    |
| GET    | /gdp/acordo/{userId}/print     | Página de impressão       |

## FUNCIONALIDADES

- Grid editável: 14 indicadores × 6 meses, organizado por eixo
- Formatação automática por unidade (reais, percentual, horas, etc)
- Salvamento AJAX com audit log
- Visualização readonly para advogado com botão de aceite
- Aceite gera hash SHA-256 do acordo + marca snapshot como congelado
- Impressão: HTML standalone com @media print, padrão jurídico completo
  (cabeçalho MAYER ALBANEZ, seções numeradas, assinaturas, hash)
- Seeder: popula 336 registros (4 users × 14 indicadores × 6 meses) com meta=0
