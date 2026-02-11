# SIRIC Fase 1 — Guia de Implantação SSH

**Módulo:** SIRIC (Sistema de Inteligência e Rating Interno de Crédito)  
**Fase:** 1 (Coleta Interna + Formulário + Decisão Humana)  
**Data:** 10/02/2026  
**Pré-requisito:** Acesso SSH ao servidor Hostinger

---

## Passo 0 — Backup

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
cp routes/web.php routes/web.php.bak.siric
cp resources/views/layouts/app.blade.php resources/views/layouts/app.blade.php.bak.siric
```

---

## Passo 1 — Upload dos arquivos

Fazer upload do pacote `siric_fase1.tar.gz` para o servidor via SFTP ou SCP, depois extrair:

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
tar -xzf ~/siric_fase1.tar.gz
```

Isso irá extrair os seguintes arquivos na estrutura correta do projeto:

```
database/migrations/2026_02_10_100000_create_siric_consultas_table.php
database/migrations/2026_02_10_100001_create_siric_evidencias_table.php
database/migrations/2026_02_10_100002_create_siric_relatorios_table.php
app/Models/SiricConsulta.php
app/Models/SiricEvidencia.php
app/Models/SiricRelatorio.php
app/Services/SiricService.php
app/Http/Controllers/SiricController.php
routes/_siric_routes.php
resources/views/siric/index.blade.php
resources/views/siric/create.blade.php
resources/views/siric/show.blade.php
resources/views/siric/partials/_metric-card.blade.php
deploy_siric.py
```

---

## Passo 2 — Executar script de deploy (patches)

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
python3 deploy_siric.py
```

O script aplica 2 patches automaticamente:
1. **routes/web.php** → adiciona `require __DIR__.'/_siric_routes.php';`
2. **layouts/app.blade.php** → adiciona item "SIRIC" no menu lateral (antes de Administração)

---

## Passo 3 — Rodar migrations

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
php artisan migrate
```

Deve criar 3 tabelas: `siric_consultas`, `siric_evidencias`, `siric_relatorios`.

---

## Passo 4 — Limpar caches

```bash
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

---

## Passo 5 — Verificar rotas

```bash
php artisan route:list --name=siric
```

Deve exibir 6 rotas:
| Método | URI | Nome |
|--------|-----|------|
| GET | /siric | siric.index |
| GET | /siric/nova | siric.create |
| POST | /siric | siric.store |
| GET | /siric/{id} | siric.show |
| POST | /siric/{id}/coletar | siric.coletarDados |
| POST | /siric/{id}/decisao | siric.salvarDecisao |

---

## Passo 6 — Testar no navegador

1. Acessar `https://intranet.mayeradvogados.adv.br/siric`
2. Verificar se o menu lateral mostra "🏦 SIRIC" antes da seção Administração
3. Clicar em "Nova Consulta" e preencher o formulário
4. Na tela de detalhe, clicar em "Coletar Dados Internos"
5. Verificar se a aba "Interno" mostra os dados coletados do BD
6. Registrar uma decisão humana (Aprovado/Condicionado/Negado)

---

## Passo 7 — Atualizar GitHub

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
git add -A
git commit -m "feat(siric): Fase 1 - Módulo SIRIC de análise de crédito interno

- 3 migrations (siric_consultas, siric_evidencias, siric_relatorios)
- 3 models (SiricConsulta, SiricEvidencia, SiricRelatorio)
- SiricService com coleta interna do BD (clientes, contas_receber, movimentos, processos, leads)
- SiricController com CRUD + coleta + decisão humana
- 3 views Blade (lista, formulário, detalhe com 5 abas)
- Rotas sob middleware auth com prefixo /siric
- Item de menu no sidebar"
git push origin main
```

---

## Rollback (se necessário)

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Reverter patches
cp routes/web.php.bak.siric routes/web.php
cp resources/views/layouts/app.blade.php.bak.siric resources/views/layouts/app.blade.php

# Reverter migrations
php artisan migrate:rollback --step=3

# Limpar caches
php artisan cache:clear && php artisan config:clear && php artisan view:clear && php artisan route:clear
```

---

## Fase 2 (Próxima Entrega)

- Integração OpenAI Responses API (rating A-E + score + recomendação)
- Integração Asaas/Serasa (Credit Bureau Report)
- Provider web_intel (stub plugável)
- Geração de relatório PDF
- Rotas: `/{id}/analisar-ia`, `/{id}/consultar-serasa`
