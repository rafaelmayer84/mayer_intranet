# ✅ Checklist de Deploy - NEXO Automação

## Antes de Começar

- [ ] Acesso SSH ao servidor Hostinger
- [ ] Acesso hPanel (File Manager)
- [ ] API Key OpenAI configurada
- [ ] Baixar e extrair `nexo-automacao-completo.tar.gz`

---

## 1. Backup (SSH)

```bash
cd /home/u283382835/domains/mayeradvogados.adv.br/public_html/intranet
php artisan down
tar -czf backup-nexo-$(date +%Y%m%d-%H%M%S).tar.gz app/ database/ routes/ config/ resources/
```

- [ ] Backup criado com sucesso
- [ ] Site em modo manutenção

---

## 2. Upload de Arquivos (hPanel)

Extrair o TAR.GZ e fazer upload via File Manager:

- [ ] `database/migrations/2026_02_07_000001_create_nexo_clientes_validacao_table.php`
- [ ] `database/migrations/2026_02_07_000002_create_nexo_automation_logs_table.php`
- [ ] `app/Models/NexoClienteValidacao.php`
- [ ] `app/Models/NexoAutomationLog.php`
- [ ] `app/Services/OpenAI/OpenAIService.php` (criar pasta OpenAI)
- [ ] `app/Services/Nexo/NexoAutomationService.php` (criar pasta Nexo)
- [ ] `app/Http/Controllers/Api/NexoWebhookController.php`
- [ ] `app/Http/Controllers/NexoMonitorController.php`
- [ ] `resources/views/nexo/monitor.blade.php` (criar pasta nexo)

---

## 3. Integração de Rotas (hPanel)

### 3.1 Arquivo: `routes/api.php`

Adicionar no **final** do arquivo:

```php
use App\Http\Controllers\Api\NexoWebhookController;

Route::prefix('nexo')->group(function () {
    Route::get('/identificar-cliente', [NexoWebhookController::class, 'identificarCliente']);
    Route::post('/perguntas-auth', [NexoWebhookController::class, 'perguntasAuth']);
    Route::post('/validar-auth', [NexoWebhookController::class, 'validarAuth']);
    Route::post('/consulta-status', [NexoWebhookController::class, 'consultaStatus']);
});
```

- [ ] Rotas API adicionadas

### 3.2 Arquivo: `routes/web.php`

Adicionar no **final** do arquivo:

```php
use App\Http\Controllers\NexoMonitorController;

Route::middleware(['auth'])->prefix('nexo')->group(function () {
    Route::get('/automacoes/monitor', [NexoMonitorController::class, 'index'])->name('nexo.monitor');
    Route::get('/automacoes/dados', [NexoMonitorController::class, 'dados'])->name('nexo.monitor.dados');
});
```

- [ ] Rotas web adicionadas

### 3.3 Arquivo: `config/services.php`

Adicionar **dentro do array 'services'**:

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],

'sendpulse' => [
    'webhook_token' => env('SENDPULSE_WEBHOOK_TOKEN'),
],
```

- [ ] Configurações adicionadas

---

## 4. Configurar .env (hPanel)

Editar arquivo `.env` e adicionar:

```ini
OPENAI_API_KEY=sk-proj-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
OPENAI_MODEL=gpt-4o-mini
SENDPULSE_WEBHOOK_TOKEN=
```

**Gerar token seguro (SSH):**
```bash
openssl rand -base64 32
```

- [ ] API Key OpenAI adicionada
- [ ] Token SendPulse gerado e adicionado

---

## 5. Executar Migrations (SSH)

```bash
cd /home/u283382835/domains/mayeradvogados.adv.br/public_html/intranet
php artisan migrate --force
```

- [ ] Migrations executadas sem erro
- [ ] Tabelas criadas: `nexo_clientes_validacao`, `nexo_automation_logs`

---

## 6. Limpar Cache (SSH)

```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
php artisan up
```

- [ ] Cache limpo
- [ ] Site voltou ao ar

---

## 7. Menu Sidebar (hPanel)

Editar: `resources/views/layouts/partials/sidebar.blade.php`

Adicionar:

```php
<a href="{{ route('nexo.monitor') }}" 
   class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
    <span class="mr-3">🤖</span>
    NEXO Automações
</a>
```

- [ ] Link adicionado ao menu

---

## 8. Verificação

### Acessar Monitor
- [ ] URL: `https://intranet.mayeradvogados.adv.br/nexo/automacoes/monitor`
- [ ] Página carrega sem erros
- [ ] Estatísticas aparecem (zeros)

### Testar Webhook
```bash
curl -X GET "https://intranet.mayeradvogados.adv.br/api/nexo/identificar-cliente?telefone=5547999999999" \
  -H "X-Sendpulse-Token: SEU_TOKEN_AQUI"
```

- [ ] Webhook responde (mesmo que "não encontrado")
- [ ] Log aparece no monitor

---

## 9. Próximos Passos

- [ ] Popular tabela `nexo_clientes_validacao` com dados reais
- [ ] Configurar webhooks no SendPulse
- [ ] Importar fluxo SendPulse
- [ ] Testar com cliente real

---

## 🆘 Troubleshooting

**Erro 500 no monitor:**
```bash
php artisan route:clear
php artisan config:clear
composer dump-autoload
```

**Migrations não rodaram:**
```bash
php artisan migrate:status
php artisan migrate --force
```

**Webhook 401:**
- Verificar token no .env
- Verificar header X-Sendpulse-Token

---

**Status Final:** ✅ Deploy Completo
