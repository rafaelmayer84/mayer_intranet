# JUSTUS — Módulo de Assistente Jurídico IA

## Visão Geral
Módulo completo de chat jurídico com IA integrado à Intranet RESULTADOS! do Mayer Advogados.

## Componentes
- 9 migrations (tabelas justus_*)
- 8 models (JustusConversation, JustusMessage, JustusAttachment, etc.)
- 3 services (Budget, RAG, OpenAI)
- 1 job assíncrono (JustusProcessPdfJob)
- 1 controller (JustusController)
- 1 view Blade (justus/index.blade.php)
- 1 config (config/justus.php)
- 1 arquivo de rotas (_justus_routes.php)

## Variáveis .env Necessárias
```
JUSTUS_OPENAI_API_KEY=sk-...
JUSTUS_MODEL_DEFAULT=gpt-5.2
JUSTUS_BUDGET_MONTHLY_MAX=6000
JUSTUS_BUDGET_USER_MAX=2000
JUSTUS_TOKEN_MONTHLY_LIMIT=200000
JUSTUS_QUEUE_NAME=justus
JUSTUS_MAX_UPLOAD_MB=50
```

## Deploy
```bash
php artisan migrate
php artisan config:clear && php artisan route:clear && php artisan view:clear && php artisan cache:clear
chmod +x ~/queue_justus.sh
# Adicionar cron: a cada minuto ~/queue_justus.sh
```

## Dependência
```bash
composer require smalot/pdfparser --no-dev
```

## Rotas
- GET /justus — Interface principal
- POST /justus/conversations — Criar conversa
- POST /justus/{id}/message — Enviar mensagem
- POST /justus/{id}/upload — Upload PDF
- GET /justus/{id}/download/{att} — Download autorizado
- POST /justus/{id}/profile — Atualizar perfil processual
- POST /justus/{id}/approve — Workflow aprovação
