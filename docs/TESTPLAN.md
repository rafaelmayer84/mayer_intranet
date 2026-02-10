# TESTPLAN.md — NEXO Fase 1 (Validação)

## Checklist de Validação

### 1. MIGRATIONS E BANCO

| # | Teste | Comando | Resultado Esperado | Status |
|---|-------|---------|-------------------|--------|
| 1.1 | Migrations executam sem erro | `php artisan migrate --force` | 3 tabelas criadas | ☐ |
| 1.2 | Tabela wa_conversations existe | `SHOW CREATE TABLE wa_conversations;` | Tabela com 15+ colunas | ☐ |
| 1.3 | Tabela wa_messages existe | `SHOW CREATE TABLE wa_messages;` | Tabela com 10+ colunas | ☐ |
| 1.4 | Tabela wa_events existe | `SHOW CREATE TABLE wa_events;` | Tabela com 5 colunas | ☐ |
| 1.5 | FK wa_conversations.assigned_user_id → users | Ver DDL | FK existe | ☐ |
| 1.6 | FK wa_conversations.linked_lead_id → leads | Ver DDL | FK existe | ☐ |
| 1.7 | FK wa_conversations.linked_cliente_id → clientes | Ver DDL | FK existe | ☐ |
| 1.8 | FK wa_messages.conversation_id → wa_conversations | Ver DDL | FK existe com CASCADE | ☐ |
| 1.9 | Índices criados | `SHOW INDEX FROM wa_conversations;` | Índices em phone, status, etc. | ☐ |
| 1.10 | Rollback funciona | `php artisan migrate:rollback --step=3` | 3 tabelas removidas | ☐ |

### 2. MODELS

| # | Teste | Comando Tinker | Resultado Esperado | Status |
|---|-------|---------------|-------------------|--------|
| 2.1 | WaConversation instancia | `new \App\Models\WaConversation;` | Sem erro | ☐ |
| 2.2 | WaMessage instancia | `new \App\Models\WaMessage;` | Sem erro | ☐ |
| 2.3 | WaEvent instancia | `new \App\Models\WaEvent;` | Sem erro | ☐ |
| 2.4 | normalizePhone com DDD | `WaConversation::normalizePhone('(47) 99999-1234')` | `5547999991234` | ☐ |
| 2.5 | normalizePhone já com 55 | `WaConversation::normalizePhone('5547999991234')` | `5547999991234` | ☐ |
| 2.6 | normalizePhone internacional | `WaConversation::normalizePhone('+5547999991234')` | `5547999991234` | ☐ |
| 2.7 | Scopes open/closed | `WaConversation::open()->toSql()` | SQL com `where status = 'open'` | ☐ |
| 2.8 | Constantes direction | `WaMessage::DIRECTION_INCOMING` | `1` | ☐ |
| 2.9 | WaEvent::log não trava | `WaEvent::log('test', null, ['x' => 1])` | Registro criado | ☐ |
| 2.10 | Relacionamento messages | `WaConversation::first()?->messages` | Sem erro (pode ser null) | ☐ |

### 3. SERVICES

| # | Teste | Comando/Ação | Resultado Esperado | Status |
|---|-------|-------------|-------------------|--------|
| 3.1 | SendPulseWhatsAppService instancia | `app(SendPulseWhatsAppService::class)` | Sem erro | ☐ |
| 3.2 | getToken() retorna token | `$svc->getToken()` | String não-vazia | ☐ |
| 3.3 | Token é cacheado | `Cache::get('sendpulse_wa_token')` | Mesmo token | ☐ |
| 3.4 | clearTokenCache() funciona | `$svc->clearTokenCache()` | Cache limpo | ☐ |
| 3.5 | extractText formato SendPulse | `extractText(['data'=>['text'=>['body'=>'oi']]])` | `'oi'` | ☐ |
| 3.6 | extractText fallback | `extractText(['body'=>'oi'])` | `'oi'` | ☐ |
| 3.7 | parseWebhookIncomingMessage | Ver JSON abaixo | Retorna array parseado | ☐ |
| 3.8 | NexoConversationSyncService instancia | `app(NexoConversationSyncService::class)` | Sem erro | ☐ |
| 3.9 | NexoGerencialService instancia | `app(NexoGerencialService::class)` | Sem erro | ☐ |
| 3.10 | getKpis() retorna array | `$svc->getKpis()` | Array com 8 chaves | ☐ |

**JSON para teste 3.7:**
```php
$payload = [
    [
        'title' => 'incoming_message',
        'contact' => ['id' => 'abc123', 'name' => 'João', 'last_message' => 'Oi'],
        'info' => ['message' => ['channel_data' => ['message' => [
            'from' => '5547999991234',
            'text' => ['body' => 'Preciso de um advogado'],
            'type' => 'text',
            'id' => 'wamid.test123'
        ]]]],
        'bot' => ['id' => 'bot123', 'name' => 'MayerBot'],
        'date' => time()
    ]
];
$result = SendPulseWhatsAppService::parseWebhookIncomingMessage($payload);
// Esperado: ['contact_id' => 'abc123', 'phone' => '5547999991234', 'text' => 'Preciso de um advogado', ...]
```

### 4. ROTAS

| # | Teste | Comando | Resultado Esperado | Status |
|---|-------|---------|-------------------|--------|
| 4.1 | Rotas Nexo registradas | `php artisan route:list --path=nexo` | 11+ rotas listadas | ☐ |
| 4.2 | Rota nexo.atendimento existe | `php artisan route:list --name=nexo.atendimento` | GET /nexo/atendimento | ☐ |
| 4.3 | Rota nexo.gerencial existe | `php artisan route:list --name=nexo.gerencial` | GET /nexo/gerencial | ☐ |
| 4.4 | Middleware auth aplicado | Verificar output route:list | Todas com middleware auth | ☐ |
| 4.5 | Nenhum conflito com rotas existentes | `php artisan route:list` (completo) | Sem duplicatas | ☐ |

### 5. WEBHOOK REUSO

| # | Teste | Ação | Resultado Esperado | Status |
|---|-------|------|-------------------|--------|
| 5.1 | Webhook leads ainda funciona | Enviar payload de lead normal | Lead criado normalmente | ☐ |
| 5.2 | incoming_message cria conversa | Enviar payload incoming_message | wa_conversations com 1 registro | ☐ |
| 5.3 | incoming_message cria mensagem | Idem | wa_messages com 1 registro | ☐ |
| 5.4 | incoming_message cria evento | Idem | wa_events com 1 registro | ☐ |
| 5.5 | Duplicata de msg não duplica | Reenviar mesmo payload | wa_messages sem duplicata | ☐ |
| 5.6 | Auto-link a lead funciona | Telefone de lead existente | linked_lead_id preenchido | ☐ |

### 6. INTEGRAÇÃO (NÃO QUEBROU NADA)

| # | Teste | Ação | Resultado Esperado | Status |
|---|-------|------|-------------------|--------|
| 6.1 | /dashboard carrega | Acessar no browser | Dashboard normal | ☐ |
| 6.2 | /leads carrega | Acessar no browser | Central de Leads normal | ☐ |
| 6.3 | /visao-gerencial carrega | Acessar no browser | Dashboard Financeiro normal | ☐ |
| 6.4 | /clientes-mercado carrega | Acessar no browser | Clientes & Mercado normal | ☐ |
| 6.5 | Webhook leads continua processando | Novo lead via WhatsApp | Lead criado com IA | ☐ |
| 6.6 | Nenhum erro no log | `tail -f storage/logs/laravel.log` | Sem erros novos | ☐ |

---

## Resumo

- **Total de testes:** 36
- **Críticos (bloqueantes):** 1.1, 3.1, 3.2, 4.1, 5.1, 6.1-6.5
- **Tempo estimado de validação:** 20-30 minutos

---

**Fase 1 aprovada quando:** Todos os testes de 1 a 6 passarem sem erro.
**Próximo passo:** Solicitar entrega da Fase 2 (Controllers + Views).
