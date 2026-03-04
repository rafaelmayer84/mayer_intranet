# EVIDENTIA — Busca Inteligente de Jurisprudência

## Módulo da Intranet RESULTADOS! — Mayer Advogados

---

## 1. Visão Geral

O EVIDENTIA é um módulo de busca híbrida (fulltext + semântica) de jurisprudência com IA, integrado à intranet RESULTADOS!. Ele reutiliza o acervo existente nos bancos `justus_tjsc`, `justus_stj`, `justus_falcao` e principal, adicionando camadas de busca semântica por embeddings, reranking via GPT-5.2 e geração de blocos de citação prontos para petições.

### Pipeline de Busca

1. **Query Understanding** — GPT-5.2 extrai termos, sinônimos e filtros sugeridos
2. **Fulltext** — MATCH AGAINST cross-database nos bancos de jurisprudência
3. **Semântica** — Embedding da query → cosine similarity contra chunks pré-embedzados
4. **Mistura** — Score final = 55% semântico + 45% fulltext (configurável)
5. **Rerank** — GPT-5.2 reordena top 30 candidatos com justificativa
6. **Resultado** — Top K ranqueados com highlights e scores detalhados
7. **Citação** — Geração opcional de síntese + bloco de precedentes para petição

### Modo Degradado

Se a OpenAI estiver fora do ar ou o budget diário for excedido, o sistema automaticamente cai para busca apenas fulltext e avisa o usuário.

---

## 2. Requisitos

- PHP 8.2+
- Laravel 12
- MariaDB 10.6+ (bancos justus_* já configurados)
- Chave API OpenAI com acesso a `gpt-5.2` e `text-embedding-3-small`
- Queue worker rodando (fila `evidentia`)
- Conexões de banco `justus_tjsc`, `justus_stj`, `justus_falcao` no `config/database.php`

---

## 3. Configuração do .env

Adicionar ao `.env`:

```
# EVIDENTIA
EVIDENTIA_MODEL_RERANK=gpt-5.2
EVIDENTIA_MODEL_WRITER=gpt-5.2
EVIDENTIA_MODEL_QUERY=gpt-5.2
EVIDENTIA_EMBEDDING_MODEL=text-embedding-3-small
EVIDENTIA_EMBEDDING_DIMS=1536
EVIDENTIA_DAILY_BUDGET=5.00
```

A chave `OPENAI_API_KEY` já existente será reutilizada.

---

## 4. Instalação — Passo a Passo

### 4.1 Copiar Arquivos

Os arquivos devem ser copiados para os diretórios da intranet:

| Origem                          | Destino no projeto                                     |
|---------------------------------|--------------------------------------------------------|
| `migrations/*.php`              | `database/migrations/`                                 |
| `config/evidentia.php`          | `config/evidentia.php`                                 |
| `models/*.php`                  | `app/Models/`                                          |
| `services/*.php`                | `app/Services/Evidentia/`                              |
| `controllers/*.php`             | `app/Http/Controllers/`                                |
| `jobs/*.php`                    | `app/Jobs/Evidentia/`                                  |
| `commands/*.php`                | `app/Console/Commands/Evidentia/`                      |
| `views/evidentia/*.blade.php`   | `resources/views/evidentia/`                           |
| `routes/evidentia.php`          | `routes/evidentia.php`                                 |
| `tests/EvidentiaSearchTest.php` | `tests/Feature/EvidentiaSearchTest.php`                |

### 4.2 Registrar Rotas

No `routes/web.php`, dentro do grupo `auth`:

```php
require __DIR__ . '/evidentia.php';
```

### 4.3 Rodar Migrations

```bash
php artisan migrate
```

Tabelas criadas:
- `evidentia_chunks`
- `evidentia_embeddings`
- `evidentia_searches`
- `evidentia_search_results`
- `evidentia_citation_blocks`

### 4.4 Registrar Commands

Os commands são auto-discovered pelo Laravel se estiverem no namespace correto (`App\Console\Commands\Evidentia`).

---

## 5. Preparação do Acervo (Chunking + Embeddings)

### 5.1 Gerar Chunks

Cria chunks das ementas para busca semântica:

```bash
# Todos os tribunais
php artisan evidentia:chunk

# Tribunal específico, 1000 por lote
php artisan evidentia:chunk --tribunal=TJSC --limit=1000

# Com geração de embeddings automática
php artisan evidentia:chunk --tribunal=TJSC --limit=500 --with-embeddings
```

### 5.2 Gerar Embeddings

Envia chunks para a API OpenAI e armazena vetores:

```bash
# Disparar jobs na fila (recomendado para grandes volumes)
php artisan evidentia:embed

# Tribunal específico
php artisan evidentia:embed --tribunal=TJSC

# Limitar quantidade (bom para testes)
php artisan evidentia:embed --tribunal=STJ --limit=100

# Modo síncrono (sem fila, bom para debug)
php artisan evidentia:embed --tribunal=STJ --limit=50 --sync
```

### 5.3 Worker da Fila

```bash
php artisan queue:work database --queue=evidentia --timeout=120 --tries=2
```

Para cron permanente, criar `queue_evidentia.sh`:

```bash
#!/bin/bash
cd /path/to/project
php artisan queue:work database --queue=evidentia --timeout=120 --tries=2 --stop-when-empty
```

E no crontab:

```
*/3 * * * * /path/to/queue_evidentia.sh >> /dev/null 2>&1
```

### 5.4 Verificar Status

```bash
php artisan evidentia:stats
```

---

## 6. Uso

### 6.1 Interface Web

- **Busca:** `/evidentia` — caixa de busca com filtros avançados
- **Resultados:** `/evidentia/resultados/{id}` — lista ranqueada com scores
- **Gerar Citação:** Botão na página de resultados gera síntese + bloco de precedentes
- **Documento:** `/evidentia/juris/{tribunal}/{id}` — visualização completa do acórdão
- **Custos:** `/evidentia/admin/custos` — painel admin com gastos diários

### 6.2 Custo Estimado

- **Embedding do acervo (one-time):** ~$5-10 USD para 266k ementas
- **Busca individual:** ~$0.005-0.02 USD (query understanding + rerank)
- **Geração de citação:** ~$0.01-0.03 USD adicional
- **Budget diário padrão:** $5.00 (configurável via .env)

---

## 7. Arquitetura Técnica

### Tabelas Novas (banco principal)

| Tabela                       | Propósito                                  |
|-----------------------------|--------------------------------------------|
| `evidentia_chunks`          | Chunks de texto das ementas/decisões       |
| `evidentia_embeddings`      | Vetores de embedding (JSON, 1536 dims)     |
| `evidentia_searches`        | Log de buscas com tokens/custo/latência    |
| `evidentia_search_results`  | Resultados ranqueados por busca            |
| `evidentia_citation_blocks` | Blocos de citação gerados pela IA          |

### Services

| Service                      | Responsabilidade                                |
|-----------------------------|-------------------------------------------------|
| `EvidentiaOpenAIService`    | Todas as interações OpenAI + budget guard       |
| `EvidentiaChunkService`     | Chunking de texto com overlap                   |
| `EvidentiaSearchService`    | Pipeline completo de busca híbrida              |
| `EvidentiaCitationService`  | Geração de blocos de citação para petição       |

### Commands Artisan

| Command                | Descrição                                    |
|-----------------------|----------------------------------------------|
| `evidentia:chunk`     | Gera chunks das jurisprudências              |
| `evidentia:embed`     | Gera embeddings via OpenAI                   |
| `evidentia:stats`     | Exibe estatísticas do sistema                |

---

## 8. Segurança

- Autenticação via middleware `auth` (reutiliza login da intranet)
- Rota de custos protegida por `can:admin`
- Budget guard impede gastos acima do limite diário
- Output sempre escapado (sem renderização de HTML do acervo)
- Rate limiting pode ser adicionado via middleware padrão Laravel

---

## 9. Regras Invioláveis

1. **NUNCA fabricar jurisprudência** — toda citação vem exclusivamente do acervo
2. **NUNCA citar fora do contexto** — o bloco de citação só usa IDs retornados na busca
3. **Modo degradado explícito** — se IA indisponível, cai para fulltext e avisa
4. **Auditoria completa** — toda busca registra query, filtros, tokens, custo e latência
