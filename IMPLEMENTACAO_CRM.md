# IMPLEMENTAÇÃO CRM COMERCIAL
## Intranet Mayer Advogados

**Data:** 13/02/2026  
**Módulo:** CRM Comercial (substitui ESPO CRM free)  
**Status:** Pronto para deploy

---

## 1. RESUMO

Módulo CRM nativo integrado à Intranet, substituindo o ESPO CRM gratuito com vantagens:

- **Identity Resolution:** Unifica contatos por phone/email/doc/datajuri/sendpulse
- **Pipeline Kanban:** Visualização drag-free com cards coloridos e KPIs
- **Timeline completa:** Eventos com payload JSON para auditoria
- **DataJuri integrado:** Processos e contas a receber na tela da oportunidade
- **Relatórios:** 6 blocos (funil, conversão, tempo médio, win rate, perdas, receita projetada)
- **Atividades:** Tasks/calls/meetings com recálculo automático de next_action

---

## 2. ARQUIVOS (26 total)

### Migrations (6)
```
database/migrations/2026_02_13_010000_create_crm_accounts_table.php
database/migrations/2026_02_13_020000_create_crm_identities_table.php
database/migrations/2026_02_13_030000_create_crm_stages_table.php
database/migrations/2026_02_13_040000_create_crm_opportunities_table.php
database/migrations/2026_02_13_050000_create_crm_activities_table.php
database/migrations/2026_02_13_060000_create_crm_events_table.php
```

### Models (6)
```
app/Models/Crm/Account.php
app/Models/Crm/Identity.php
app/Models/Crm/Stage.php
app/Models/Crm/Opportunity.php
app/Models/Crm/Activity.php
app/Models/Crm/Event.php
```

### Services (4)
```
app/Services/Crm/CrmIdentityResolver.php
app/Services/Crm/CrmOpportunityService.php
app/Services/Crm/CrmActivityService.php
app/Services/Crm/CrmMetricsService.php
```

### Controllers (4)
```
app/Http/Controllers/Crm/PipelineController.php
app/Http/Controllers/Crm/OpportunityController.php
app/Http/Controllers/Crm/ActivityController.php
app/Http/Controllers/Crm/ReportsController.php
```

### Views (4)
```
resources/views/crm/pipeline.blade.php
resources/views/crm/opportunity_show.blade.php
resources/views/crm/opportunity_create.blade.php
resources/views/crm/reports.blade.php
```

### Rotas (1)
```
routes/_crm_routes.php    (13 rotas nomeadas)
```

### Seeder (1)
```
database/seeders/CrmStagesSeeder.php
```

### Deploy (1)
```
deploy_crm.py
```

---

## 3. DEPLOY — COMANDOS SSH

```bash
# 1. Conectar
ssh u492856976@153.92.223.23 -p 65002
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# 2. Upload do pacote (via hPanel File Manager ou scp)
# Fazer upload de crm_module.tar.gz para o diretório Intranet/

# 3. Extrair (preserva estrutura de diretórios)
tar -xzvf crm_module.tar.gz

# 4. Executar script de deploy (patcha web.php e app.blade.php)
python3 deploy_crm.py

# 5. Migrations
php artisan migrate --force

# 6. Seeder (cria 6 estágios do pipeline)
php artisan db:seed --class=CrmStagesSeeder --force

# 7. Limpar caches
php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear

# 8. Verificar rotas
php artisan route:list | grep crm

# 9. Testar acesso
curl -s -o /dev/null -w "%{http_code}" https://intranet.mayeradvogados.adv.br/crm

# 10. Git
git add -A
git commit -m "feat: CRM Comercial - pipeline kanban, identity resolution, reports"
git push origin main
```

---

## 4. ROTAS

| Método | URI | Nome | Controller |
|--------|-----|------|------------|
| GET | /crm | crm.pipeline | PipelineController@index |
| GET | /crm/oportunidades/criar | crm.opportunity.create | OpportunityController@create |
| POST | /crm/oportunidades | crm.opportunity.store | OpportunityController@store |
| GET | /crm/oportunidades/{id} | crm.opportunity.show | OpportunityController@show |
| PUT | /crm/oportunidades/{id} | crm.opportunity.update | OpportunityController@update |
| POST | /crm/oportunidades/{id}/mover-estagio | crm.opportunity.move-stage | OpportunityController@moveStage |
| POST | /crm/oportunidades/{id}/ganho | crm.opportunity.won | OpportunityController@markWon |
| POST | /crm/oportunidades/{id}/perda | crm.opportunity.lost | OpportunityController@markLost |
| POST | /crm/atividades | crm.activity.store | ActivityController@store |
| POST | /crm/atividades/{id}/concluir | crm.activity.complete | ActivityController@complete |
| GET | /crm/relatorios | crm.reports | ReportsController@index |
| GET | /crm/api/pipeline-kpis | crm.api.kpis | PipelineController@kpisJson |
| GET | /crm/api/accounts/search | crm.accounts.search | PipelineController@searchAccounts |

---

## 5. TABELAS

| Tabela | Campos chave | Índices |
|--------|-------------|---------|
| crm_accounts | id, type(PF/PJ), name, doc_digits, owner_user_id | type, owner |
| crm_identities | account_id, kind, value, value_norm | unique(kind, value_norm) |
| crm_stages | name, sort, color, is_won, is_lost, is_active | sort |
| crm_opportunities | account_id, stage_id, title, area, source, value_estimated, status, next_action_at, lead_id | status, stage_id, owner, next_action_at |
| crm_activities | opportunity_id, type, title, due_at, done_at | opportunity_id, type |
| crm_events | type, opportunity_id, account_id, payload(JSON), happened_at | type, opportunity_id, happened_at |

---

## 6. PIPELINE STAGES (Seeder)

| # | Nome | Sort | Cor | Terminal |
|---|------|------|-----|----------|
| 1 | Lead Novo | 10 | #9CA3AF | Não |
| 2 | Em Contato | 20 | #3B82F6 | Não |
| 3 | Proposta | 30 | #F59E0B | Não |
| 4 | Negociação | 40 | #8B5CF6 | Não |
| 5 | Ganho | 90 | #10B981 | Sim (won) |
| 6 | Perdido | 99 | #EF4444 | Sim (lost) |

---

## 7. IDENTITY RESOLUTION

O `CrmIdentityResolver` unifica contatos de múltiplas fontes:

```
Phone: (47) 99999-9999 → normalizado para 554799999999 (E.164)
Email: User@Email.COM → normalizado para user@email.com
Doc: 123.456.789-00 → normalizado para 12345678900

Kinds suportados: phone, email, doc, datajuri, sendpulse, espocrm
```

**Fluxo:**
1. Normaliza inputs
2. Busca identity existente (phone → email → doc → extras)
3. Se encontrou: retorna Account + adiciona identidades faltantes
4. Se não: cria Account + identidades em transação atômica

**Race condition safe:** catch de QueryException em unique constraint.

---

## 8. INTEGRAÇÃO FUTURA (Fase 5)

### Lead → CRM (LeadProcessingService)
Localizar `sendToEspoCRM()` e adicionar chamada ao resolver:
```php
$resolver = app(CrmIdentityResolver::class);
$account = $resolver->resolve($lead->telefone, $lead->email, null, [
    'name' => $lead->nome,
    'owner_user_id' => $ownerUserId,
]);
$oppService = app(CrmOpportunityService::class);
$oppService->createOrGetOpen($account, [
    'title' => $lead->nome . ' - ' . ($lead->area_interesse ?? 'Consulta'),
    'area' => $lead->area_interesse,
    'source' => 'whatsapp',
    'lead_id' => $lead->id,
]);
```

### NEXO → CRM (Event logging)
Ao resolver conversa por phone, registrar evento:
```php
$account = $resolver->resolve($phone);
Event::log('nexo_interaction', null, $account->id, [
    'conversation_id' => $conversation->id,
    'direction' => 'inbound',
]);
```

---

## 9. CHECKLIST DE VALIDAÇÃO PÓS-DEPLOY

```bash
# 1. Tabelas criadas
mysql -u USER -p DB -e "SHOW TABLES LIKE 'crm_%';"
# Esperado: 6 tabelas

# 2. Stages seedados
mysql -u USER -p DB -e "SELECT * FROM crm_stages ORDER BY sort;"
# Esperado: 6 registros

# 3. Rotas registradas
php artisan route:list | grep crm
# Esperado: 13 rotas

# 4. Acesso web (logado)
# https://intranet.mayeradvogados.adv.br/crm
# Esperado: Kanban vazio com 4 KPIs e botão "Nova Oportunidade"

# 5. Criar oportunidade teste
# Clicar "Nova Oportunidade", preencher dados, salvar
# Esperado: Redirect para detalhe com timeline mostrando "Oportunidade criada"

# 6. Mover estágio
# Na tela de detalhe, mudar dropdown e clicar "Mover"
# Esperado: Timeline mostra "Movido de X para Y"

# 7. Criar atividade
# Na tela de detalhe, criar tarefa com due_at
# Esperado: next_action_at da oportunidade atualizado

# 8. Marcar ganho
# Clicar "Ganho", confirmar
# Esperado: Status GANHO, badge verde, timeline registra

# 9. Relatórios
# Acessar /crm/relatorios
# Esperado: 4 KPIs + 6 blocos de relatório

# 10. Menu sidebar
# Verificar que item "CRM Comercial" aparece no menu
```

---

## 10. OBSERVAÇÕES

- **Nenhuma dependência do ESPO CRM:** Módulo 100% independente
- **Layout:** Usa @extends('layouts.app') existente, cores #385776/#1B334A, Montserrat
- **Middleware:** Apenas 'auth', mesmo padrão dos demais módulos
- **Chart.js:** Carregado via CDN na view de relatórios (mesmo padrão do dashboard financeiro)
- **Sem novas dependências Composer:** Tudo com Laravel nativo

---

**Documento gerado em:** 13/02/2026
