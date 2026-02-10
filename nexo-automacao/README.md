# Sistema de Automação NEXO - WhatsApp

Automação de atendimento via WhatsApp com autenticação multifator e consultas processuais automatizadas usando IA.

## 📋 Visão Geral

Este sistema permite que clientes do escritório Mayer Advogados consultem automaticamente o status de seus processos via WhatsApp, após passarem por autenticação de segurança.

### Funcionalidades

- ✅ Identificação automática de cliente por telefone
- ✅ Autenticação multifator com perguntas dinâmicas
- ✅ Consulta de status processual com resposta em linguagem natural (OpenAI)
- ✅ Dashboard de monitoramento em tempo real
- ✅ Logs completos de todas as interações
- ✅ Sistema de bloqueio por tentativas incorretas

## 🏗️ Arquitetura

```
Cliente WhatsApp
    ↓
SendPulse Bot (fluxo de conversação)
    ↓
Webhooks → Laravel/NEXO (validação e processamento)
    ↓
DataJuri (dados processuais) + OpenAI (formatação)
    ↓
Resposta ao cliente via SendPulse
```

## 📦 Estrutura de Arquivos

```
nexo-automacao/
├── database/migrations/          # Tabelas do banco de dados
├── app/
│   ├── Models/                   # NexoClienteValidacao, NexoAutomationLog
│   ├── Services/
│   │   ├── OpenAI/               # Integração OpenAI
│   │   └── Nexo/                 # Lógica de automação
│   └── Http/Controllers/         # API e Interface
├── routes/                       # Rotas web e API
├── resources/views/nexo/         # Interface de monitoramento
├── config/                       # Configurações de serviços
├── sendpulse/                    # Fluxo e documentação SendPulse
├── DEPLOY.md                     # Instruções completas de deploy
└── README.md                     # Este arquivo
```

## 🚀 Deploy Rápido

1. **Backup:**
   ```bash
   php artisan down
   tar -czf backup-nexo-$(date +%Y%m%d-%H%M%S).tar.gz app/ database/ routes/ config/
   ```

2. **Upload:** Fazer upload dos arquivos via hPanel

3. **Integração:** Adicionar rotas em `api.php`, `web.php` e config em `services.php`

4. **Migrations:**
   ```bash
   php artisan migrate --force
   php artisan config:clear
   php artisan route:clear
   composer dump-autoload
   php artisan up
   ```

5. **Configurar .env:**
   ```ini
   OPENAI_API_KEY=sk-proj-xxxxx
   SENDPULSE_WEBHOOK_TOKEN=token_secreto
   ```

Ver `DEPLOY.md` para instruções detalhadas.

## 🔐 Segurança

- Autenticação multifator com perguntas dinâmicas
- Bloqueio após 3 tentativas incorretas (30 minutos)
- Token de segurança nos webhooks
- Logs completos de todas as ações
- Validação de telefone normalizada

## 📊 Monitoramento

Acesse: `https://intranet.mayeradvogados.adv.br/nexo/automacoes/monitor`

- Estatísticas em tempo real
- Logs das últimas automações
- Gráfico de atividade
- Auto-refresh a cada 10 segundos

## 🔧 Tecnologias

- Laravel 12
- PHP 8.2
- MySQL
- OpenAI API (gpt-4o-mini)
- SendPulse WhatsApp
- Tailwind CSS
- Chart.js

## 📝 Tabelas Criadas

- `nexo_clientes_validacao` - Dados de autenticação dos clientes
- `nexo_automation_logs` - Logs de todas as interações

## 🔗 Endpoints API

- `GET /api/nexo/identificar-cliente` - Identifica cliente por telefone
- `POST /api/nexo/perguntas-auth` - Gera perguntas de autenticação
- `POST /api/nexo/validar-auth` - Valida respostas de autenticação
- `POST /api/nexo/consulta-status` - Consulta status do processo

## 📞 Próximos Passos

1. Popular tabela `nexo_clientes_validacao` com dados reais
2. Configurar webhooks no SendPulse (ver `sendpulse/INSTRUCOES_SENDPULSE.md`)
3. Importar fluxo no SendPulse
4. Testar com clientes reais
5. Expandir para outras consultas (boletos, agendamentos, etc)

## 🆘 Suporte

Consultar:
- `DEPLOY.md` - Instruções de deploy
- `sendpulse/INSTRUCOES_SENDPULSE.md` - Configuração SendPulse
- Logs do Laravel: `storage/logs/laravel.log`
- Monitor NEXO: `/nexo/automacoes/monitor`

---

**Versão:** 1.0.0  
**Data:** 07/02/2026  
**Desenvolvido para:** Mayer Albanez Sociedade de Advogados
