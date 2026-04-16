# Intranet RESULTADOS! — Mayer Sociedade de Advogados

## Visão geral
- **Framework:** Laravel (PHP 8.2)
- **Banco:** MariaDB 11.8
- **Path:** `~/domains/mayeradvogados.adv.br/public_html/Intranet`
- **GitHub:** `github.com/rafaelmayer84/mayer_intranet`
- **APP_URL SAGRADA:** `https://intranet.mayeradvogados.adv.br`
- **Schema:** `php artisan docs:schema` → `storage/app/docs/schema_latest.md` (147 tabelas, 1982 colunas)
- **Testes:** `php artisan test:negocios` (21 testes de regra de negócio, aceita `--modulo=X`)


## Módulos do sistema
| Módulo | Descrição |
|--------|-----------|
| **NEXO** | Comunicação WhatsApp/SendPulse com clientes |
| **SIPEX** | Precificação e geração de propostas |
| **VIGÍLIA** | Monitoramento de accountability processual |
| **JUSTUS** | Scraping de jurisprudência (TJSC eproc, TRF4, TRT12, STJ) |
| **GDP** | Gestão de desempenho profissional |
| **CRM** | Gestão de relacionamento e leads |
| **BSC Insights** | Dashboard estratégico / KPIs |
| **Evidentia** | Busca semântica (vetorial) |
| **SISRH** | RH interno |
| **Pulso do Cliente** | Indicadores de satisfação |
| **SIATE** | Ticketing interno (administração) |

## Integrações externas
- **DataJuri** (OAuth2): gestão processual — `base64(clientID:secretID)` com `:`, nunca `@`
  - `DATAJURI_CLIENT_ID`, `DATAJURI_SECRET_ID`, `EMAIL`, `USERNAME`, `PASSWORD`, `BASE_URL`
  - Endpoint atividades: `GET /v1/entidades/Atividade` via `DataJuriService::buscarModuloPagina()`
  - Campo `assunto` DEVE estar explicitamente nos `campos`
- **SendPulse WhatsApp Business API**
  - Bot ID: `66a7da52c355078bd90e984f`
  - `NexoConsultaController` → token `NEXO_CONSULTA_TOKEN`
  - `NexoAutoatendimentoController` → token `SENDPULSE_WEBHOOK_TOKEN` + whitelist IPv6
  - Timeout: 15s → usar Jobs assíncronos
  - Variáveis `{{var}}` NUNCA com aspas. Filtros: AMBAS saídas conectadas. Limite: 1024 chars
  - Arquivos de mídia: servir via `GET /api/nexo/media/{filename}`, NÃO via storage público (504)
  - Laravel 12: usar `$file->move(storage_path('app/public/nexo/media'), $filename)`
- **OpenAI**
  - SIRIC: modelo principal `gpt-5.2`, fallback `gpt-5`/`gpt-5.1`
  - Central de Leads: `gpt-5-mini` (sem `temperature`, usar `max_completion_tokens`)
  - `gpt-5`/`5.1`/`5.2`: suportam `temperature` e `max_tokens`
- **Anthropic Claude API**
  - Chave: `JUSTUS_ANTHROPIC_API_KEY`, modelo: `JUSTUS_CLAUDE_MODEL`
  - Usado em: JUSTUS (jurisprudência) e SIPEX `ProposalClaudeService.php`

## SIPEX — arquitetura AI
- `PricingAIService.php` → OpenAI (precificação)
- `ProposalClaudeService.php` → Anthropic Claude (texto da proposta)
- Campo descrição do caso OBRIGATÓRIO antes de gerar proposta (risco de alucinação)

## VIGÍLIA — regras críticas
- Fonte da verdade: DataJuri
- Assuntos covert que ativam follow-up obrigatório (48h escalation):
  "Análise de Decisão", "Providências Pós-Audiência", "Relatório de Ocorrência", "Verificação de Cumprimento"
- Coluna `assunto_original` preserva primeiro assunto sincronizado para detectar alterações
- Cross-reference: `atividades_datajuri` → `fases_processo.datajuri_id` → `andamentos_fase.fase_processo_id_datajuri`

## JUSTUS — scrapers
- Comando: `justus:sync-tjsc-eproc` (usa mecanismo eproc, substituiu busca.tjsc.jus.br)
- Data inicial: calculada dinamicamente de `max(created_at)` menos overlap de 7 dias
- Dashboard: GROUP BY coluna `tribunal`

## GDP — scanners
- `scanD01()`: mapeamento de IDs DataJuri
- `scanA04()`: verifica mensagem pendente do cliente ANTES de medir prazo 30min
- `scanA07()`: lógica estrita

## FinanceiroCalculatorService
- Fonte única de verdade para dados financeiros do home
- Coluna `data_movimento` é NULL em 100% dos registros — usar `mes`/`ano`

## Regras de schema (CRÍTICO)
- NUNCA inventar nomes de colunas/tabelas
- SEMPRE rodar `php artisan docs:schema` antes de escrever queries
- Zero tolerância para nomes inventados

## Arquivos estabilizados (PHPDoc obrigatório)
Arquivos marcados ESTÁVEL devem conter:
1. `// ESTÁVEL desde DD/MM/AAAA`
2. Documentação funcional completa (lógica de negócio, fontes, writes, crons, deps, fluxo)
3. Aviso de alteração
Arquivos já marcados: `JustusSyncTrf4Command`, `JustusSyncStjCommand`, `JustusSyncTrt12Command`

## Pendências conhecidas
- NEXO: notificação email/bell ao responsável ao criar/atribuir ticket
- SIPEX: campo descrição obrigatório no modal, Job assíncrono para geração via browser
- Evidentia: coluna `e.vector_bin` ausente (schema mismatch pós-v2.0)
- BSC Insights v2.0: validação de snapshots, métricas derivadas, aba Governança
- Lead filter: números de varas/tribunais NÃO devem criar leads (investigar `LeadProcessingService`)
- VIGÍLIA: movimento "Arquivado" + "ausência do reclamante" → tarefa obrigatória (pendente)
