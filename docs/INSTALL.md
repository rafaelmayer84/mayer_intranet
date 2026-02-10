# INSTALL.md — NEXO Fase 1 (Infraestrutura)

## Pré-requisitos
- SSH ativo no Hostinger
- PHP 8.2.29 / Laravel 12.x / MariaDB
- Caminho: `~/domains/mayeradvogados.adv.br/public_html/Intranet`

---

## 1. BACKUP (OBRIGATÓRIO)

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Backup do banco
mysqldump -u u492856976_intranet -p u492856976_intranet > ~/backup_nexo_pre_$(date +%Y%m%d_%H%M%S).sql

# Backup dos arquivos que serão modificados
cp routes/web.php routes/web.php.bak_nexo
cp app/Http/Controllers/LeadController.php app/Http/Controllers/LeadController.php.bak_nexo
```

---

## 2. UPLOAD DOS ARQUIVOS

Primeiro, criar os diretórios necessários:

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
mkdir -p resources/views/nexo/atendimento
mkdir -p resources/views/nexo/gerencial
```

Fazer upload dos seguintes arquivos do pacote `nexo-fase1/` para o servidor, mantendo a estrutura:

| Arquivo local (pacote)                                | Destino no servidor                                          | Ação     |
|------------------------------------------------------|--------------------------------------------------------------|----------|
| `database/migrations/2026_02_06_000001_create_wa_conversations_table.php` | `database/migrations/`                       | CRIAR    |
| `database/migrations/2026_02_06_000002_create_wa_messages_table.php`      | `database/migrations/`                       | CRIAR    |
| `database/migrations/2026_02_06_000003_create_wa_events_table.php`        | `database/migrations/`                       | CRIAR    |
| `app/Models/WaConversation.php`                       | `app/Models/`                                                | CRIAR    |
| `app/Models/WaMessage.php`                            | `app/Models/`                                                | CRIAR    |
| `app/Models/WaEvent.php`                              | `app/Models/`                                                | CRIAR    |
| `app/Services/SendPulseWhatsAppService.php`           | `app/Services/`                                              | CRIAR    |
| `app/Services/NexoConversationSyncService.php`        | `app/Services/`                                              | CRIAR    |
| `app/Services/NexoGerencialService.php`               | `app/Services/`                                              | CRIAR    |
| `app/Http/Controllers/NexoAtendimentoController.php`  | `app/Http/Controllers/`                                      | CRIAR    |
| `app/Http/Controllers/NexoGerencialController.php`    | `app/Http/Controllers/`                                      | CRIAR    |
| `resources/views/nexo/atendimento/stub.blade.php`     | `resources/views/nexo/atendimento/`                          | CRIAR    |
| `resources/views/nexo/gerencial/stub.blade.php`       | `resources/views/nexo/gerencial/`                            | CRIAR    |
| `routes/_nexo_routes.php`                             | `routes/`                                                    | CRIAR    |

---

## 3. MODIFICAR `routes/web.php`

Adicionar **UMA LINHA** no final do arquivo `routes/web.php`, **ANTES** do fechamento (se houver), junto com os demais `require`:

```php
// Procurar a linha existente:
require __DIR__ . '/_leads_routes.php';

// Adicionar ABAIXO dela:
require __DIR__ . '/_nexo_routes.php';
```

---

## 4. MODIFICAR `LeadController.php` — WEBHOOK REUSO

No método `webhook()` do `LeadController.php`, adicionar **NO INÍCIO DO MÉTODO** (antes de qualquer processamento de lead):

```php
public function webhook(Request $request)
{
    // ════ INÍCIO BLOCO NEXO (inserir aqui) ════
    // Detectar se é incoming_message do SendPulse para o Nexo
    $rawPayload = $request->all();
    $event = is_array($rawPayload) && isset($rawPayload[0]) ? $rawPayload[0] : $rawPayload;

    if (data_get($event, 'title') === 'incoming_message') {
        try {
            $syncService = app(\App\Services\NexoConversationSyncService::class);
            $syncService->syncConversationFromWebhook($rawPayload);
        } catch (\Throwable $e) {
            \Log::error('Nexo webhook error', ['error' => $e->getMessage()]);
        }
        // NÃO retornar aqui — deixar o fluxo de leads continuar
        // para que o lead também seja processado se aplicável
    }
    // ════ FIM BLOCO NEXO ════

    // ... código existente do webhook de leads continua abaixo ...
```

**IMPORTANTE:** O bloco Nexo NÃO dá `return`. Ele processa a mensagem para o sistema de atendimento E deixa o fluxo de leads continuar normalmente. Assim ambos os sistemas recebem a informação.

---

## 5. VARIÁVEIS DE AMBIENTE (.env)

Adicionar (se não existirem):

```env
# Webhook secret para validação (opcional, para segurança adicional)
SENDPULSE_WEBHOOK_SECRET=

# As variáveis abaixo JÁ EXISTEM no .env:
# SENDPULSE_API_ID=4a868a32763f862737ff967b8cf1b547
# SENDPULSE_API_SECRET=9e7d6dca459f680f32d5c775cd7d5616
# SENDPULSE_BOT_ID=66a7da52c355078bd90e984f
```

---

## 6. EXECUTAR MIGRATIONS

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

php artisan migrate --force
```

**Resultado esperado:**
```
Migrating: 2026_02_06_000001_create_wa_conversations_table
Migrated:  2026_02_06_000001_create_wa_conversations_table
Migrating: 2026_02_06_000002_create_wa_messages_table
Migrated:  2026_02_06_000002_create_wa_messages_table
Migrating: 2026_02_06_000003_create_wa_events_table
Migrated:  2026_02_06_000003_create_wa_events_table
```

---

## 7. LIMPAR CACHE

```bash
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan view:clear
```

---

## 8. VALIDAÇÃO DA FASE 1

### 8.1 Verificar tabelas criadas
```bash
php artisan tinker --execute="echo implode(', ', \Schema::getColumnListing('wa_conversations'));"
# Esperado: id, provider, contact_id, chat_id, phone, name, status, assigned_user_id, ...

php artisan tinker --execute="echo implode(', ', \Schema::getColumnListing('wa_messages'));"
# Esperado: id, conversation_id, provider_message_id, direction, is_human, ...

php artisan tinker --execute="echo implode(', ', \Schema::getColumnListing('wa_events'));"
# Esperado: id, conversation_id, type, payload, created_at
```

### 8.2 Verificar rotas registradas
```bash
php artisan route:list --path=nexo
```
**Esperado:** Deve listar todas as rotas `/nexo/atendimento/*` e `/nexo/gerencial/*`

### 8.3 Verificar service SendPulse
```bash
php artisan tinker --execute="
\$svc = app(\App\Services\SendPulseWhatsAppService::class);
\$token = \$svc->getToken();
echo \$token ? 'TOKEN OK (' . strlen(\$token) . ' chars)' : 'FALHOU';
"
```

### 8.4 Verificar models
```bash
php artisan tinker --execute="echo \App\Models\WaConversation::normalizePhone('(47) 99999-1234');"
# Esperado: 5547999991234
```

---

## 9. ROLLBACK (SE NECESSÁRIO)

```bash
# Reverter migrations
php artisan migrate:rollback --step=3

# Restaurar arquivos
cp routes/web.php.bak_nexo routes/web.php
cp app/Http/Controllers/LeadController.php.bak_nexo app/Http/Controllers/LeadController.php

# Restaurar banco (último recurso)
mysql -u u492856976_intranet -p u492856976_intranet < ~/backup_nexo_pre_YYYYMMDD_HHMMSS.sql

# Limpar cache
php artisan config:clear && php artisan route:clear && php artisan cache:clear
```

---

## STATUS APÓS FASE 1

| Componente               | Status          |
|--------------------------|-----------------|
| Tabelas wa_*             | ✅ Criadas       |
| Models                   | ✅ Funcionais    |
| Services                 | ✅ Funcionais    |
| Rotas registradas        | ✅ Registradas   |
| Webhook reuso            | ✅ Configurado   |
| Token SendPulse          | ✅ Com cache     |
| Controllers (JSON APIs)  | ✅ Funcionais    |
| Views stub               | ✅ Placeholder   |
| Views completas (3 col)  | ⏳ Fase 2        |
| Menu Nexo na sidebar     | ⏳ Fase 2        |
| Gráficos Chart.js        | ⏳ Fase 3        |

**Próximo passo:** Fase 2 — Controllers + Views (NexoAtendimentoController, NexoGerencialController, views Blade)

---

**Tempo estimado de instalação da Fase 1:** 10-15 minutos
