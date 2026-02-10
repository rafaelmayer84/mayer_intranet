# IMPLEMENTAÇÃO — Processos Internos (BSC)
**Data:** 10/02/2026 | **Módulo:** RESULTADOS! > BSC > Processos Internos

---

## ARQUIVOS ENTREGUES

### Bloco 1 — Migrations + Models

| Arquivo | Descrição |
|---------|-----------|
| `database/migrations/2026_02_10_010000_create_andamentos_fase_table.php` | Nova tabela `andamentos_fase` (módulo DataJuri: AndamentoFase) |
| `database/migrations/2026_02_10_020000_add_processos_internos_fields.php` | Campos extras: `tipo_atividade`, `data_vencimento`, `responsavel_nome` em `atividades_datajuri`; `area_atuacao`, `grupo_responsavel` em `processos`; `descricao_fase` em `fases_processo` |
| `app/Models/AndamentoFase.php` | Model Eloquent para nova tabela |
| `app/Models/FaseProcesso.php` | Model Eloquent (tabela já existe, model pode ser novo) |

### Bloco 2 — Service + Controller + Rotas

| Arquivo | Descrição |
|---------|-----------|
| `app/Services/ProcessosInternosService.php` | Lógica de cálculo de todos os KPIs (SLA, Backlog, WIP, sem andamento, throughput, horas) |
| `app/Http/Controllers/Dashboard/ProcessosInternosController.php` | Controller com index, drilldown, export |
| `routes/_processos_internos_routes.php` | Arquivo de rotas (incluir via require no web.php) |

### Bloco 3 — View + Config + Deploy

| Arquivo | Descrição |
|---------|-----------|
| `resources/views/dashboard/processos-internos/index.blade.php` | View completa: filtros, cards, gráficos Chart.js, tabela equipe, processos por fase, top riscos, modal drilldown |
| `config/_andamento_fase_modulo.php` | Config do módulo AndamentoFase para datajuri.php |
| `deploy_processos_internos.py` | Script Python de deploy (patches cirúrgicos em web.php, app.blade.php, datajuri.php) |

---

## CHECKLIST DE DEPLOY

```
[ ] 1. BACKUP
    cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
    tar -czf backup-processos-internos-$(date +%Y%m%d_%H%M%S).tar.gz .

[ ] 2. COPIAR ARQUIVOS (via SCP ou upload)
    - database/migrations/2026_02_10_*.php
    - app/Models/AndamentoFase.php
    - app/Models/FaseProcesso.php (verificar se já existe, se sim NÃO sobrescrever)
    - app/Services/ProcessosInternosService.php
    - app/Http/Controllers/Dashboard/ProcessosInternosController.php
    - routes/_processos_internos_routes.php
    - resources/views/dashboard/processos-internos/index.blade.php
    - deploy_processos_internos.py

[ ] 3. EXECUTAR DEPLOY SCRIPT
    python3 deploy_processos_internos.py

[ ] 4. MIGRATIONS
    php artisan migrate --force

[ ] 5. LIMPAR CACHE
    php artisan cache:clear && php artisan view:clear && php artisan config:clear && php artisan route:clear

[ ] 6. SINCRONIZAR ANDAMENTOFASE
    php artisan sync:datajuri-completo
    # OU via UI: /admin/sincronizacao-unificada → Sincronizar AndamentoFase

[ ] 7. VERIFICAR NO NAVEGADOR
    https://intranet.mayeradvogados.adv.br/resultados/bsc/processos-internos

[ ] 8. COMMIT NO GITHUB
    git add -A
    git commit -m "feat: módulo Processos Internos (BSC) - dashboard completo"
    git push origin main
```

---

## QUERIES DE VALIDAÇÃO

### Verificar tabela criada e com dados
```sql
-- Tabelas necessárias com contagens
SELECT 'processos' as tabela, COUNT(*) as total FROM processos WHERE status = 'Ativo'
UNION ALL SELECT 'atividades_datajuri', COUNT(*) FROM atividades_datajuri
UNION ALL SELECT 'fases_processo', COUNT(*) FROM fases_processo
UNION ALL SELECT 'andamentos_fase', COUNT(*) FROM andamentos_fase
UNION ALL SELECT 'horas_trabalhadas', COUNT(*) FROM horas_trabalhadas_datajuri;
```

### Verificar novos campos das migrations
```sql
-- Campos novos em atividades_datajuri
SHOW COLUMNS FROM atividades_datajuri WHERE Field IN ('tipo_atividade','data_vencimento','responsavel_nome');

-- Campos novos em processos
SHOW COLUMNS FROM processos WHERE Field IN ('area_atuacao','grupo_responsavel');

-- Campos novos em fases_processo
SHOW COLUMNS FROM fases_processo WHERE Field IN ('descricao_fase');
```

### Testar KPIs manualmente
```sql
-- SLA: Atividades concluídas no prazo (últimos 30 dias)
SELECT
    COUNT(*) as total_concluidas,
    SUM(CASE WHEN data_conclusao <= COALESCE(data_vencimento, data_prazo_fatal) THEN 1 ELSE 0 END) as no_prazo,
    ROUND(SUM(CASE WHEN data_conclusao <= COALESCE(data_vencimento, data_prazo_fatal) THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as sla_percent
FROM atividades_datajuri
WHERE data_conclusao IS NOT NULL
  AND data_conclusao >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Backlog: Vencidas e abertas
SELECT COUNT(*) as backlog_total,
       AVG(DATEDIFF(NOW(), COALESCE(data_vencimento, data_prazo_fatal))) as media_dias_atraso
FROM atividades_datajuri
WHERE data_conclusao IS NULL
  AND COALESCE(data_vencimento, data_prazo_fatal) < NOW()
  AND (status != 'Concluída' OR status IS NULL);

-- WIP: Em andamento
SELECT COUNT(*) as wip
FROM atividades_datajuri
WHERE data_conclusao IS NULL
  AND (status NOT IN ('Concluída', 'Cancelada', 'Cancelado') OR status IS NULL);

-- Processos sem andamento > 30 dias
SELECT COUNT(*) as sem_andamento
FROM fases_processo fp
JOIN processos p ON fp.processo_pasta = p.pasta
WHERE fp.fase_atual = 1
  AND p.status = 'Ativo'
  AND (fp.data_ultimo_andamento < DATE_SUB(NOW(), INTERVAL 30 DAY) OR fp.data_ultimo_andamento IS NULL);

-- Throughput (últimos 30 dias)
SELECT COUNT(*) as throughput
FROM atividades_datajuri
WHERE data_conclusao IS NOT NULL
  AND data_conclusao >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Horas (últimos 30 dias)
SELECT ROUND(SUM(total_hora_trabalhada), 1) as total_horas
FROM horas_trabalhadas_datajuri
WHERE data >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Rotas registradas (verificar via artisan)
-- php artisan route:list --path=processos-internos
```

### Verificar rotas
```bash
php artisan route:list --path=processos-internos
# Deve retornar:
# GET  resultados/bsc/processos-internos ............. index
# GET  resultados/bsc/processos-internos/drilldown/{tipo} .. drilldown
# GET  resultados/bsc/processos-internos/export ...... export
```

---

## ESQUEMA DE DADOS

### Nova tabela: `andamentos_fase`
```
id, datajuri_id (UNIQUE), fase_processo_id_datajuri, processo_id_datajuri,
processo_pasta, data_andamento, descricao, tipo, parecer, parecer_revisado,
parecer_revisado_por, data_parecer_revisado, proprietario_id, proprietario_nome,
created_at, updated_at
```

### Campos adicionados em tabelas existentes
- **atividades_datajuri**: `tipo_atividade`, `data_vencimento`, `responsavel_nome`
- **processos**: `area_atuacao`, `grupo_responsavel`
- **fases_processo**: `descricao_fase`

---

## KPIs IMPLEMENTADOS

| KPI | Fórmula | Tabela fonte |
|-----|---------|--------------|
| SLA (%) | concluídas_no_prazo / total_concluídas × 100 | atividades_datajuri |
| Backlog Vencido | COUNT vencidas abertas + AVG dias atraso | atividades_datajuri |
| WIP | COUNT atividades não concluídas | atividades_datajuri |
| Sem Andamento | Processos com fase ativa e último andamento > X dias | fases_processo + processos |
| Throughput | COUNT concluídas no período | atividades_datajuri |
| Horas | SUM horas trabalhadas no período | horas_trabalhadas_datajuri |

---

## NOTAS TÉCNICAS

1. **Cache**: TTL 300s (mesmo padrão dos outros dashboards). Cache key inclui hash dos filtros.

2. **Filtros persistidos**: Session key `filtros_processos_internos`. Querystring tem prioridade sobre session.

3. **SLA sem data_vencimento**: Atividades sem prazo definido são consideradas "no prazo" (sem SLA configurado).

4. **Score de Risco**: `valor_provisionado × dias_fase_ativa`. Ordena Top 20 desc.

5. **Evolução**: Buckets temporais (dia/semana/mês). Para backlog, usa snapshot no fim de cada bucket.

6. **Model FaseProcesso**: Verificar se já existe antes de copiar. Se existir, apenas adicionar os métodos `scopeParados()` e `scopeAtual()` e o relationship `andamentos()`.

7. **Campos data_vencimento**: A migration adiciona `data_vencimento` na tabela `atividades_datajuri`. Se o campo não for populado via sync, o SLA usará `data_prazo_fatal` como fallback. Para garantir, o sync do módulo Atividade deve mapear o campo correto da API.

---

## ÁREAS NÃO ALTERADAS

- ✅ Dashboard Financeiro — INTACTO
- ✅ Dashboard Clientes & Mercado — INTACTO
- ✅ NEXO Atendimento — INTACTO
- ✅ Central de Leads — INTACTO
- ✅ Sincronização existente (8 módulos) — INTACTO (apenas adição do 9º módulo)
