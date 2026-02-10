# Deploy: Sistema de Automação NEXO

## 📋 Pré-requisitos

- Acesso SSH ao servidor Hostinger
- Acesso ao hPanel (File Manager)
- API Key da OpenAI configurada
- Token de segurança para webhooks SendPulse

---

## 🚀 Passo a Passo

### 1. SSH - Backup

```bash
cd /home/u283382835/domains/mayeradvogados.adv.br/public_html/intranet
php artisan down
tar -czf backup-nexo-$(date +%Y%m%d-%H%M%S).tar.gz app/ database/ routes/ config/ resources/
```

### 2. hPanel - Upload dos Arquivos

1. Extrair o ZIP `nexo-automacao-completo.zip` no computador local
2. Acessar File Manager no hPanel
3. Fazer upload dos arquivos nas respectivas pastas:
   - `database/migrations/` → subir os 2 arquivos de migration
   - `app/Models/` → subir NexoClienteValidacao.php e NexoAutomationLog.php
   - `app/Services/OpenAI/` → criar pasta e subir OpenAIService.php
   - `app/Services/Nexo/` → criar pasta e subir NexoAutomationService.php
   - `app/Http/Controllers/Api/` → subir NexoWebhookController.php
   - `app/Http/Controllers/` → subir NexoMonitorController.php
   - `resources/views/nexo/` → criar pasta e subir monitor.blade.php

### 3. Integração Manual de Arquivos

#### 3.1 Rotas API (routes/api.php)

Editar o arquivo `routes/api.php` via hPanel e **adicionar no final**:

```php
use App\Http\Controllers\Api\NexoWebhookController;

Route::prefix('nexo')->group(function () {
    Route::get('/identificar-cliente', [NexoWebhookController::class, 'identificarCliente']);
    Route::post('/perguntas-auth', [NexoWebhookController::class, 'perguntasAuth']);
    Route::post('/validar-auth', [NexoWebhookController::class, 'validarAuth']);
    Route::post('/consulta-status', [NexoWebhookController::class, 'consultaStatus']);
});
```

#### 3.2 Rotas Web (routes/web.php)

Editar o arquivo `routes/web.php` via hPanel e **adicionar no final**:

```php
use App\Http\Controllers\NexoMonitorController;

Route::middleware(['auth'])->prefix('nexo')->group(function () {
    Route::get('/automacoes/monitor', [NexoMonitorController::class, 'index'])->name('nexo.monitor');
    Route::get('/automacoes/dados', [NexoMonitorController::class, 'dados'])->name('nexo.monitor.dados');
});
```

#### 3.3 Configuração de Serviços (config/services.php)

Editar o arquivo `config/services.php` via hPanel e **adicionar dentro do array 'services'**:

```php
'openai' => [
    'api_key' => env('OPENAI_API_KEY'),
    'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
],

'sendpulse' => [
    'webhook_token' => env('SENDPULSE_WEBHOOK_TOKEN'),
],
```

### 4. Configurar .env

Editar o arquivo `.env` via hPanel e adicionar:

```ini
OPENAI_API_KEY=sk-proj-XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
OPENAI_MODEL=gpt-4o-mini
SENDPULSE_WEBHOOK_TOKEN=seu_token_secreto_aqui
```

**Como gerar token seguro:**
```bash
openssl rand -base64 32
```

### 5. SSH - Executar Migrations

```bash
cd /home/u283382835/domains/mayeradvogados.adv.br/public_html/intranet
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
php artisan up
```

### 6. Adicionar Link no Menu Sidebar

Editar `resources/views/layouts/partials/sidebar.blade.php` via hPanel:

Adicionar este bloco onde desejar no menu:

```php
<a href="{{ route('nexo.monitor') }}" 
   class="flex items-center px-4 py-2 text-gray-700 hover:bg-gray-100">
    <span class="mr-3">🤖</span>
    NEXO Automações
</a>
```

---

## ✅ Verificação Pós-Deploy

1. Acessar: `https://intranet.mayeradvogados.adv.br/nexo/automacoes/monitor`
2. Verificar se a página carrega sem erros
3. Testar endpoint de teste:
   ```bash
   curl -X GET "https://intranet.mayeradvogados.adv.br/api/nexo/identificar-cliente?telefone=5547999999999" \
     -H "X-Sendpulse-Token: SEU_TOKEN"
   ```

---

## 📊 Próximos Passos

1. Популar tabela `nexo_clientes_validacao` com dados de clientes
2. Configurar webhooks no SendPulse (ver INSTRUCOES_SENDPULSE.md)
3. Importar fluxo SendPulse (usar arquivo sendpulse/fluxo_autenticacao_consultas.json)

---

## 🆘 Troubleshooting

**Erro 500 ao acessar monitor:**
```bash
php artisan route:clear
php artisan config:clear
composer dump-autoload
```

**Tabelas não criadas:**
```bash
php artisan migrate:status
php artisan migrate --force
```

**Webhook retorna 401:**
- Verificar se `SENDPULSE_WEBHOOK_TOKEN` está configurado no .env
- Verificar se o token no SendPulse é o mesmo

---

**Data de criação:** 07/02/2026
**Versão:** 1.0.0
