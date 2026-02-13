# CRM V2 — DOCUMENTAÇÃO DE IMPLEMENTAÇÃO
## Intranet Mayer Advogados
**Data:** 13/02/2026 | **Versão:** 2.0 | **Status:** Pronto para deploy

---

## 1. VISÃO GERAL

O CRM V2 substitui integralmente o CRM V1 (5 tabelas, 28 arquivos). A nova arquitetura traz:

- **Identity Resolution:** Resolução multi-canal (telefone, email, CPF/CNPJ, DataJuri, ESPO, SendPulse) que identifica automaticamente se um contato já é cliente DataJuri ou prospect
- **Pipeline Kanban:** Gestão visual de oportunidades com stages configuráveis, drag conceptual via botões
- **Account 360:** Visão unificada com dados CRM gerenciais + contexto DataJuri (processos, contratos, financeiro) + timeline de eventos + atividades
- **Relatórios:** Funil, win rate por responsável, tempo médio por estágio, motivos de perda, valor projetado ponderado
- **Import ESPO direto:** Conexão MySQL direta ao banco ESPO CRM no mesmo servidor, sem depender da API REST

---

## 2. TABELAS DO BANCO

| Tabela | Propósito | Registros esperados |
|--------|-----------|---------------------|
| crm_stages | Colunas do kanban (6 padrão) | 6 |
| crm_accounts | Overlay DataJuri + prospects | ~3.000+ |
| crm_identities | Resolução multi-canal | ~10.000+ |
| crm_opportunities | Pipeline de oportunidades | Crescente |
| crm_activities | Tarefas, ligações, reuniões, notas | Crescente |
| crm_events | Timeline auditável (imutável) | Crescente |

**Tabelas V1 removidas:** crm_events(v1), crm_activities(v1), crm_opportunities(v1), crm_stages(v1), crm_accounts(v1)

---

## 3. IDENTITY RESOLVER — CORAÇÃO DO SISTEMA

O `CrmIdentityResolver` é o serviço central. Qualquer módulo que precise identificar um contato chama:

```php
$resolver = app(CrmIdentityResolver::class);
$account = $resolver->resolve(
    phone: '5547999887766',
    email: 'joao@email.com',
    doc: '12345678901',
    defaults: ['name' => 'João da Silva']
);
```

**Fluxo interno:**
1. Normaliza inputs (phone→E.164, email→lower, doc→digits)
2. Busca em `crm_identities` (prioridade: doc > email > phone)
3. Se não achar, tenta match na tabela `clientes` (cache DataJuri) por CPF/CNPJ, email ou telefone
4. Se encontrar match DataJuri → cria CrmAccount kind=client com datajuri_pessoa_id
5. Se não encontrar → cria CrmAccount kind=prospect
6. Registra todas as identities na tabela crm_identities

---

## 4. ROTAS

```
GET  /crm/carteira                          → CrmCarteiraController@index
GET  /crm/pipeline                          → CrmPipelineController@index
POST /crm/pipeline/{id}/move                → CrmPipelineController@moveStage
POST /crm/pipeline/{id}/won                 → CrmPipelineController@markWon
POST /crm/pipeline/{id}/lost                → CrmPipelineController@markLost
GET  /crm/accounts/{id}                     → CrmAccountController@show
PUT  /crm/accounts/{id}                     → CrmAccountController@update
POST /crm/accounts/{id}/opportunities       → CrmAccountController@createOpportunity
POST /crm/accounts/{id}/activities          → CrmAccountController@storeActivity
GET  /crm/oportunidades/{id}                → CrmOpportunityController@show
PUT  /crm/oportunidades/{id}                → CrmOpportunityController@update
POST /crm/oportunidades/{id}/activities     → CrmOpportunityController@storeActivity
POST /crm/oportunidades/{id}/activities/{a}/complete → CrmOpportunityController@completeActivity
GET  /crm/relatorios                        → CrmReportsController@index
```

---

## 5. COMANDOS ARTISAN

```bash
# Sync carteira DataJuri → crm_accounts (kind=client)
php artisan crm:sync-carteira
php artisan crm:sync-carteira --dry-run

# Import leads + oportunidades do ESPO CRM (MySQL direto)
php artisan crm:import-espo
php artisan crm:import-espo --conn=mysql_espo --dry-run
```

---

## 6. INTEGRAÇÃO COM MÓDULOS EXISTENTES

### 6.1 Central de Leads
Ao qualificar um lead, chamar:
```php
$account = app(CrmIdentityResolver::class)->resolve(phone: $lead->telefone, email: $lead->email);
app(CrmOpportunityService::class)->createOrGetOpen($account->id, 'lead', 'aquisicao', $lead->area_interesse);
CrmEvent::create([...type => 'lead_qualified'...]);
```

### 6.2 NEXO WhatsApp
Ao abrir conversa, chamar:
```php
$account = app(CrmIdentityResolver::class)->resolve(phone: $phoneE164);
CrmEvent::create([...type => 'nexo_opened_chat'...]);
// NÃO criar oportunidade automaticamente — apenas registrar event
```

---

## 7. CONFIG ESPO MySQL

Adicionar em `config/database.php` → connections:
```php
'mysql_espo' => [
    'driver'    => 'mysql',
    'host'      => env('ESPO_DB_HOST', '127.0.0.1'),
    'port'      => env('ESPO_DB_PORT', '3306'),
    'database'  => env('ESPO_DB_DATABASE', ''),
    'username'  => env('ESPO_DB_USERNAME', ''),
    'password'  => env('ESPO_DB_PASSWORD', ''),
    'charset'   => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix'    => '',
    'strict'    => false,
],
```

E no `.env`:
```
ESPO_DB_HOST=127.0.0.1
ESPO_DB_DATABASE=nome_banco_espo
ESPO_DB_USERNAME=usuario
ESPO_DB_PASSWORD=senha
```

---

## 8. DEPLOY

### Passo 0: Backup
```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
git stash
```

### Passo 1: Upload e extrair
```bash
tar -xzf crm_v2.tar.gz
```

### Passo 2: Migrations
```bash
php artisan migrate
```

### Passo 3: Seeder (stages iniciais)
```bash
php artisan db:seed --class=CrmStagesV2Seeder
```

### Passo 4: Incluir rotas
Adicionar em `routes/web.php`:
```php
require __DIR__ . '/_crm_routes.php';
```

### Passo 5: Sidebar
Inserir snippet `_sidebar_snippet.blade.php` no menu de `layouts/app.blade.php`

### Passo 6: Config ESPO DB
Patch Python para adicionar conexão mysql_espo em config/database.php

### Passo 7: Sync inicial
```bash
php artisan crm:sync-carteira
php artisan crm:import-espo --conn=mysql_espo
```

### Passo 8: Cache + Git
```bash
php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear
git add -A && git commit -m "CRM V2: implementação completa" && git push
```

---

## 9. ESTRUTURA DE ARQUIVOS

```
app/
├── Console/Commands/
│   ├── CrmSyncCarteira.php
│   └── CrmImportEspo.php
├── Http/Controllers/Crm/
│   ├── CrmCarteiraController.php
│   ├── CrmPipelineController.php
│   ├── CrmAccountController.php
│   ├── CrmOpportunityController.php
│   └── CrmReportsController.php
├── Models/Crm/
│   ├── CrmStage.php
│   ├── CrmAccount.php
│   ├── CrmIdentity.php
│   ├── CrmOpportunity.php
│   ├── CrmActivity.php
│   └── CrmEvent.php
└── Services/Crm/
    ├── CrmIdentityResolver.php
    ├── CrmOpportunityService.php
    └── CrmMetricsService.php

database/
├── migrations/
│   ├── 2026_02_13_100000_drop_crm_v1_tables.php
│   ├── 2026_02_13_100001_create_crm_stages_table.php
│   ├── 2026_02_13_100002_create_crm_accounts_table.php
│   ├── 2026_02_13_100003_create_crm_identities_table.php
│   ├── 2026_02_13_100004_create_crm_opportunities_table.php
│   ├── 2026_02_13_100005_create_crm_activities_table.php
│   └── 2026_02_13_100006_create_crm_events_table.php
└── seeders/
    └── CrmStagesV2Seeder.php

resources/views/crm/
├── carteira/index.blade.php
├── pipeline/index.blade.php
├── accounts/show.blade.php
├── opportunities/show.blade.php
├── reports/index.blade.php
└── _sidebar_snippet.blade.php

routes/
└── _crm_routes.php
```
