# IMPLEMENTAÇÃO — LOTE 1: Dashboard Financeiro
## KPI Card Universal v2 + Waterfall + Pareto + Insights + Sparklines
### Data: 2026-02-10

---

## VISÃO GERAL

Este lote entrega 4 melhorias ao Dashboard Financeiro (Visão Gerencial):

1. **KPI Card v2** — componente universal com suporte a meta null ("Meta: —"), status visual (ok/atenção/crítico/sem_meta), sparkline SVG inline (12 pontos), e trend com seta colorida. Retrocompatível com chamadas v1.

2. **Waterfall DRE** — gráfico de barras flutuantes mostrando Receita → Deduções → Despesas → Resultado. Usa dados já existentes em `resumoExecutivo`.

3. **Pareto Inadimplência** — barras com top clientes em atraso + linha de % acumulado (eixo Y duplo). Usa dados já existentes em `topAtrasoClientes`.

4. **Insights Automáticos** — bloco com 3 bullets baseados em regras objetivas: maior alta de despesa MoM, concentração de inadimplência, e expense ratio. Sem IA.

5. **Sparklines** — mini gráficos SVG nos KPI cards de Receita e Despesas, mostrando evolução 12 meses.

---

## ARQUIVOS

### Criados (3 novos)

| Arquivo | Descrição |
|---------|-----------|
| `resources/views/dashboard/partials/_kpi-card.blade.php` | Substituição — v2 com sparkline+meta null |
| `resources/views/dashboard/partials/_insights-financeiro.blade.php` | Novo — bloco de insights |
| `resources/views/dashboard/partials/_charts-financeiro-extra.blade.php` | Novo — waterfall + pareto |

### Modificados (3 patches cirúrgicos)

| Arquivo | Alteração |
|---------|-----------|
| `resources/views/dashboard/visao-gerencial.blade.php` | Include dos novos partials + sparkline params nos cards |
| `app/Services/DashboardFinanceProdService.php` | Método `getSparklineData()` adicionado |
| `app/Http/Controllers/DashboardController.php` | Passa `sparklines` para a view |

### NÃO alterados

Nenhuma alteração em: rotas, models, migrations, outros dashboards, menu, CSS, ou qualquer área não solicitada.

---

## COMANDOS SSH — DEPLOY COMPLETO

Execute na ordem, um bloco por vez.

### Passo 0: Navegar e backup

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Backups
cp resources/views/dashboard/partials/_kpi-card.blade.php resources/views/dashboard/partials/_kpi-card.blade.php.bak_$(date +%Y%m%d_%H%M%S)
cp resources/views/dashboard/visao-gerencial.blade.php resources/views/dashboard/visao-gerencial.blade.php.bak_$(date +%Y%m%d_%H%M%S)
cp app/Services/DashboardFinanceProdService.php app/Services/DashboardFinanceProdService.php.bak_$(date +%Y%m%d_%H%M%S)
cp app/Http/Controllers/DashboardController.php app/Http/Controllers/DashboardController.php.bak_$(date +%Y%m%d_%H%M%S)
```

### Passo 1: Upload dos 3 novos arquivos

Os 3 arquivos novos estão no ZIP entregue. Fazer upload via File Manager ou SCP e copiar para os caminhos corretos:

```bash
# Se fez upload para ~/uploads/:
cp ~/uploads/_kpi-card-v2.blade.php resources/views/dashboard/partials/_kpi-card.blade.php
cp ~/uploads/_insights-financeiro.blade.php resources/views/dashboard/partials/_insights-financeiro.blade.php
cp ~/uploads/_charts-financeiro-extra.blade.php resources/views/dashboard/partials/_charts-financeiro-extra.blade.php
```

### Passo 2: Executar script Python de patches

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Upload deploy_lote1_financeiro.py para o diretório e executar:
python3 deploy_lote1_financeiro.py
```

O script faz:
- Backup automático de todos os arquivos modificados
- Patches cirúrgicos no service (getSparklineData), controller (sparklines), e view (includes)
- Cada patch é idempotente (não aplica se já aplicado)

### Passo 3: Verificar e limpar cache

```bash
php artisan cache:clear
php artisan view:clear
php artisan config:clear
```

### Passo 4: Testar no navegador

Acessar: `https://mayeradvogados.adv.br/Intranet/visao-gerencial`

### Passo 5: Commit no GitHub

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
git add -A
git status
git commit -m "feat(dashboard): Lote 1 - KPI Card v2 + Waterfall DRE + Pareto + Insights + Sparklines"
git push origin main
```

---

## CHECKLIST DE VALIDAÇÃO

Após o deploy, verificar visualmente cada item:

- [ ] **KPI Cards** — Receita Total mostra sparkline (mini gráfico verde 12 pontos)
- [ ] **KPI Cards** — Despesas mostra sparkline (mini gráfico azul)
- [ ] **KPI Cards** — Cards sem meta exibem "Meta: —" e badge cinza
- [ ] **KPI Cards** — Cards com meta exibem badge colorido (OK verde / Atenção amarelo / Crítico vermelho)
- [ ] **KPI Cards** — Trend com seta ↑/↓ colorida ao lado da meta
- [ ] **Waterfall** — Gráfico com 4 barras: Receita (verde), Deduções (amarelo), Despesas (vermelho), Resultado (azul)
- [ ] **Waterfall** — Barras flutuantes (deduções e despesas "caem" da receita)
- [ ] **Waterfall** — Tooltip mostra valor formatado em R$
- [ ] **Pareto** — Barras vermelhas com top clientes em atraso
- [ ] **Pareto** — Linha amarela de % acumulado no eixo Y direito (0-100%)
- [ ] **Pareto** — Se não houver inadimplência, mostra mensagem "Sem dados"
- [ ] **Insights** — Bloco com 3 itens coloridos (verde/amarelo/laranja/vermelho conforme gravidade)
- [ ] **Insights** — Textos mudam conforme dados reais (não são estáticos)
- [ ] **Gráficos existentes** — Todos os gráficos anteriores continuam funcionando
- [ ] **Filtros** — Mudar ano/mês atualiza waterfall, pareto e insights
- [ ] **Exportar** — CSV/PDF continua funcionando
- [ ] **Dark Mode** — Todos os novos elementos respeitam dark mode
- [ ] **Mobile** — Cards e gráficos responsivos

---

## ROLLBACK

Se algo quebrar:

```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet

# Listar backups disponíveis
ls -la resources/views/dashboard/partials/_kpi-card.blade.php.bak_*
ls -la resources/views/dashboard/visao-gerencial.blade.php.bak_*
ls -la app/Services/DashboardFinanceProdService.php.bak_*
ls -la app/Http/Controllers/DashboardController.php.bak_*

# Restaurar (ajustar timestamp):
cp resources/views/dashboard/partials/_kpi-card.blade.php.bak_YYYYMMDD_HHMMSS resources/views/dashboard/partials/_kpi-card.blade.php
cp resources/views/dashboard/visao-gerencial.blade.php.bak_YYYYMMDD_HHMMSS resources/views/dashboard/visao-gerencial.blade.php
cp app/Services/DashboardFinanceProdService.php.bak_YYYYMMDD_HHMMSS app/Services/DashboardFinanceProdService.php
cp app/Http/Controllers/DashboardController.php.bak_YYYYMMDD_HHMMSS app/Http/Controllers/DashboardController.php

# Remover partials novos (se necessário):
rm -f resources/views/dashboard/partials/_insights-financeiro.blade.php
rm -f resources/views/dashboard/partials/_charts-financeiro-extra.blade.php

php artisan cache:clear && php artisan view:clear
```

---

## NOTAS TÉCNICAS

**Sparkline SVG inline**: Escolhi SVG puro ao invés de Chart.js para os sparklines por 3 razões: (a) zero dependência JavaScript adicional, (b) renderiza instantaneamente sem esperar DOMContentLoaded, (c) peso negligível (~200 bytes por sparkline).

**Waterfall como floating bars**: Chart.js não tem tipo "waterfall" nativo. Implementei usando bar chart com arrays `[base, topo]` para cada barra, que é a técnica padrão aceita pela documentação do Chart.js.

**Pareto com eixo Y duplo**: Eixo esquerdo para valores em R$ (barras), eixo direito para % acumulado (linha). O `yAxisID` diferencia os datasets.

**Insights sem IA**: As 3 regras são puramente aritméticas: (1) maior diff positiva em `rubricasMoM.topAumentos`, (2) `top3SharePct > 50%` de `topAtrasoClientes`, (3) `expenseRatio.pct` vs limiares 50/70%. Quando não há dado suficiente, exibe texto neutro positivo.

**Cache**: O `getSparklineData()` usa cache de 1h (mesmo TTL do `getDashboardData`). A chave é `dash_fin_sparklines:{ano}`.

**Retrocompatibilidade**: O novo `_kpi-card.blade.php` aceita todas as chamadas antigas. Variáveis novas (`sparkline`, `status`, `invertTrend`) são opcionais com defaults seguros.
