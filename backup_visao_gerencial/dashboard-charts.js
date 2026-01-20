(function () {
  'use strict';

  const fmtCurrency = (v) => {
    const n = Number(v || 0);
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  };

  const fmtPct = (v) => {
    const n = Number(v || 0);
    return n.toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
  };

  const pct = (val, meta) => {
    const v = Number(val || 0);
    const m = Number(meta || 0);
    if (!m) return 0;
    return (v / m) * 100;
  };

  const arrow = (n) => (Number(n) >= 0 ? '↑' : '↓');

  const progressColor = (metricId, percent) => {
    const p = Number(percent || 0);
    if (metricId === 'despesas') {
      if (p > 100) return 'bg-red-500';
      if (p >= 80) return 'bg-amber-500';
      return 'bg-emerald-500';
    }
    if (p >= 80) return 'bg-emerald-500';
    if (p >= 50) return 'bg-amber-500';
    return 'bg-red-500';
  };

  const trendClass = (metricId, trend) => {
    const t = Number(trend || 0);
    const increaseIsBad = ['despesas', 'atraso', 'dias'];
    if (increaseIsBad.includes(metricId)) {
      return t > 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400';
    }
    return t >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400';
  };

  const byId = (id) => document.getElementById(id);
  const charts = {};

  const buildReceitaLineChart = (ctxId, title, labels, meta, realizado) => {
    const ctx = byId(ctxId);
    if (!ctx) return null;
    return new Chart(ctx, {
      type: 'line',
      data: {
        labels,
        datasets: [
          {
            label: 'Meta',
            data: meta,
            borderColor: '#3B82F6',
            backgroundColor: 'rgba(59, 130, 246, 0.15)',
            borderWidth: 3,
            pointRadius: 2,
            tension: 0.3,
          },
          {
            label: 'Realizado',
            data: realizado,
            borderColor: '#10B981',
            backgroundColor: 'rgba(16, 185, 129, 0.12)',
            borderWidth: 3,
            pointRadius: 2,
            tension: 0.3,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          title: { display: false, text: title },
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${fmtCurrency(ctx.raw)}`,
            },
          },
        },
        scales: {
          y: {
            ticks: { callback: (v) => fmtCurrency(v) },
            grid: { color: 'rgba(156, 163, 175, 0.2)' },
          },
          x: { grid: { display: false } },
        },
      },
    });
  };

  const buildReceitaPFChart = (data) => {
    const d = data.receitaPF12Meses || {};
    const labels = d.meses || [];
    const meta = (d.meta || []).map((n) => Number(n));
    const real = (d.realizado || []).map((n) => Number(n));
    if (charts.receitaPF) charts.receitaPF.destroy();
    charts.receitaPF = buildReceitaLineChart('chart-receita-pf', 'Receita PF', labels, meta, real);
  };

  const buildReceitaPJChart = (data) => {
    const d = data.receitaPJ12Meses || {};
    const labels = d.meses || [];
    const meta = (d.meta || []).map((n) => Number(n));
    const real = (d.realizado || []).map((n) => Number(n));
    if (charts.receitaPJ) charts.receitaPJ.destroy();
    charts.receitaPJ = buildReceitaLineChart('chart-receita-pj', 'Receita PJ', labels, meta, real);
  };

  const buildLucratividadeChart = (data) => {
    const ctx = byId('chart-rentabilidade');
    if (!ctx) return;
    const d = data.lucratividade12Meses || {};
    const labels = d.meses || [];
    const receita = (d.receita || []).map((n) => Number(n));
    const despesas = (d.despesas || []).map((n) => Number(n));
    const lucro = (d.lucratividade || []).map((n) => Number(n));
    const colors = lucro.map((v) => (v > 0 ? '#10B981' : v < 0 ? '#EF4444' : '#9CA3AF'));
    if (charts.lucratividade) charts.lucratividade.destroy();
    charts.lucratividade = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Receita',
            data: receita,
            backgroundColor: 'rgba(59, 130, 246, 0.5)',
            borderColor: '#3B82F6',
            borderWidth: 1,
          },
          {
            label: 'Despesas',
            data: despesas,
            backgroundColor: 'rgba(239, 68, 68, 0.5)',
            borderColor: '#EF4444',
            borderWidth: 1,
          },
          {
            label: 'Lucro',
            data: lucro,
            backgroundColor: colors,
            borderWidth: 0,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.dataset.label}: ${fmtCurrency(ctx.raw)}`,
            },
          },
        },
        scales: {
          y: {
            ticks: { callback: (v) => fmtCurrency(v) },
            grid: { color: 'rgba(156, 163, 175, 0.2)' },
          },
          x: { grid: { display: false } },
        },
      },
    });
  };

  const buildDespesasChart = (data) => {
    const ctx = byId('chart-despesas-rubrica');
    if (!ctx) return;
    const d = data.despesasRubrica || [];
    const labels = d.map((r) => r.rubrica);
    const values = d.map((r) => Number(r.valor));
    if (charts.despesas) charts.despesas.destroy();
    charts.despesas = new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [
          {
            data: values,
            backgroundColor: [
              '#3B82F6',
              '#10B981',
              '#F59E0B',
              '#EF4444',
              '#8B5CF6',
              '#EC4899',
              '#14B8A6',
              '#F97316',
            ],
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' },
          tooltip: {
            callbacks: {
              label: (ctx) => `${ctx.label}: ${fmtCurrency(ctx.raw)}`,
            },
          },
        },
      },
    });
  };

  const buildAgingChart = (data) => {
    const ctx = byId('chart-aging-contas');
    if (!ctx) return;
    const d = data.agingContas || {};
    const labels = d.faixas || [];
    const values = d.valores || [];
    if (charts.aging) charts.aging.destroy();
    charts.aging = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          {
            label: 'Valor em Atraso',
            data: values,
            backgroundColor: [
              'rgba(34, 197, 94, 0.7)',
              'rgba(245, 158, 11, 0.7)',
              'rgba(239, 68, 68, 0.7)',
              'rgba(127, 29, 29, 0.7)',
            ],
            borderColor: ['#22C55E', '#F59E0B', '#EF4444', '#7F1D1D'],
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: (ctx) => `${fmtCurrency(ctx.raw)}`,
            },
          },
        },
        scales: {
          y: {
            ticks: { callback: (v) => fmtCurrency(v) },
            grid: { color: 'rgba(156, 163, 175, 0.2)' },
          },
          x: { grid: { display: false } },
        },
      },
    });
  };

  const renderKpis = (data) => {
    const s = data.resumoExecutivo || {};
    const renderKpi = (id, value, meta, trend, metricId) => {
      const el = byId(id);
      if (!el) return;
      const p = pct(value, meta);
      const pc = progressColor(metricId, p);
      const tc = trendClass(metricId, trend);
      el.innerHTML = `
        <div class="space-y-2">
          <div class="text-2xl font-bold text-gray-900 dark:text-white">${fmtCurrency(value)}</div>
          <div class="text-xs text-gray-500">Meta: ${fmtCurrency(meta)} (${fmtPct(p)})</div>
          <div class="flex items-center gap-1">
            <span class="inline-block px-2 py-1 rounded text-xs font-semibold ${pc}">
              ${p >= 80 ? 'OK' : p >= 50 ? 'ATENÇÃO' : 'CRÍTICO'}
            </span>
            <span class="${tc}">
              ${arrow(trend)} ${fmtPct(trend)} vs mês anterior
            </span>
          </div>
        </div>
      `;
    };
    renderKpi('kpi-receita', s.receitaTotal, s.receitaMeta, s.receitaTrend, 'receita');
    renderKpi('kpi-despesas', s.despesasTotal, s.despesasMeta, s.despesasTrend, 'despesas');
    renderKpi('kpi-resultado', s.resultadoLiquido, s.resultadoMeta, s.resultadoTrend, 'resultado');
    renderKpi('kpi-margem', s.margemLiquida, 100, s.margemTrend, 'margem');
    renderKpi('kpi-atraso', s.diasAtraso, s.diasAtrasoMeta, s.diasAtrasoTrend, 'dias');
  };

  const renderDespesasTable = (data) => {
    const el = byId('tbl-despesas-rubrica');
    if (!el) return;
    const d = data.despesasRubrica || [];
    el.innerHTML = d.map((r) => `
      <tr>
        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">${r.rubrica}</td>
        <td class="px-4 py-2 text-sm text-right font-semibold text-gray-900 dark:text-white">${fmtCurrency(r.valor)}</td>
      </tr>
    `).join('');
  };

  const renderAtrasosTable = (data) => {
    const el = byId('tbl-atrasos');
    if (!el) return;
    const d = data.contasAtrasoLista || [];
    el.innerHTML = d.slice(0, 5).map((c) => `
      <tr>
        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">${c.numero}</td>
        <td class="px-4 py-2 text-sm text-gray-700 dark:text-gray-300">${c.cliente}</td>
        <td class="px-4 py-2 text-sm text-right font-semibold text-red-600">${fmtCurrency(c.valor)}</td>
        <td class="px-4 py-2 text-sm text-right text-red-600">${c.diasAtraso} dias</td>
      </tr>
    `).join('');
  };

  const renderComparativo = (data) => {
    const el = byId('tbl-comparativo');
    if (!el) return;
    const s = data.resumoExecutivo || {};
    const metrics = [
      { label: 'Receita', atual: s.receitaTotal, meta: s.receitaMeta, trend: s.receitaTrend },
      { label: 'Despesas', atual: s.despesasTotal, meta: s.despesasMeta, trend: s.despesasTrend },
      { label: 'Resultado Líquido', atual: s.resultadoLiquido, meta: s.resultadoMeta, trend: s.resultadoTrend },
      { label: 'Margem Líquida', atual: s.margemLiquida, meta: 100, trend: s.margemTrend },
    ];
    el.innerHTML = metrics.map((m) => {
      const p = pct(m.atual, m.meta);
      return `
        <tr>
          <td class="px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-300">${m.label}</td>
          <td class="px-4 py-2 text-sm text-right text-gray-900 dark:text-white">${fmtCurrency(m.atual)}</td>
          <td class="px-4 py-2 text-sm text-right text-gray-600 dark:text-gray-400">${fmtCurrency(m.meta)}</td>
          <td class="px-4 py-2 text-sm text-right font-semibold">${fmtPct(p)}</td>
          <td class="px-4 py-2 text-sm text-right ${trendClass('', m.trend)}">${arrow(m.trend)} ${fmtPct(m.trend)}</td>
        </tr>
      `;
    }).join('');
  };

  const applyData = (data) => {
    console.log('[Dashboard] applyData chamado');
    renderKpis(data);
    renderDespesasTable(data);
    renderAtrasosTable(data);
    renderComparativo(data);
    buildReceitaPFChart(data);
    buildReceitaPJChart(data);
    buildLucratividadeChart(data);
    buildDespesasChart(data);
    buildAgingChart(data);
    console.log('[Dashboard] Todos os gráficos criados');
  };

  const resolveApiUrl = () => window.__DASHBOARD_API_URL__ || '/api/visao-gerencial';
  const resolveExportUrl = () => window.__DASHBOARD_EXPORT_URL__ || '/visao-gerencial/export';

  const setupFilters = () => {
    const selAno = byId('filter-ano');
    const selMes = byId('filter-mes');
    const doUpdate = async () => {
      if (!selAno || !selMes) return;
      const ano = Number(selAno.value);
      const mes = Number(selMes.value);
      const url = new URL(resolveApiUrl(), window.location.origin);
      url.searchParams.set('ano', String(ano));
      url.searchParams.set('mes', String(mes));
      const res = await fetch(url.toString());
      if (!res.ok) {
        console.error('[Dashboard] Erro ao buscar dados:', res.status);
        return;
      }
      const data = await res.json();
      applyData(data);
      const exp = byId('export-csv');
      if (exp) {
        const u = new URL(resolveExportUrl(), window.location.origin);
        u.searchParams.set('ano', String(ano));
        u.searchParams.set('mes', String(mes));
        exp.href = u.toString();
      }
    };
    if (selAno) selAno.addEventListener('change', doUpdate);
    if (selMes) selMes.addEventListener('change', doUpdate);
  };

  const setupExportMenu = () => {
    const btn = byId('btn-export');
    const menu = byId('export-menu');
    const pdf = byId('export-pdf');
    const csv = byId('export-csv');
    const selAno = byId('filter-ano');
    const selMes = byId('filter-mes');
    const syncCsvLink = () => {
      if (!csv || !selAno || !selMes) return;
      const u = new URL(resolveExportUrl(), window.location.origin);
      u.searchParams.set('ano', String(selAno.value));
      u.searchParams.set('mes', String(selMes.value));
      csv.href = u.toString();
    };
    syncCsvLink();
    if (btn && menu) {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('hidden');
      });
      document.addEventListener('click', () => menu.classList.add('hidden'));
    }
    if (pdf) {
      pdf.addEventListener('click', () => {
        if (menu) menu.classList.add('hidden');
        window.print();
      });
    }
  };

  // ===== INICIALIZAÇÃO SIMPLIFICADA E ROBUSTA =====
  const bootstrap = () => {
    console.log('[Dashboard] Bootstrap iniciado');
    const data = window.__DASHBOARD_EXEC_DATA__;
    
    if (!data) {
      console.error('[Dashboard] ❌ window.__DASHBOARD_EXEC_DATA__ não existe');
      return false;
    }
    
    console.log('[Dashboard] ✅ Dados disponíveis');
    
    try {
      applyData(data);
      setupFilters();
      setupExportMenu();
      console.log('[Dashboard] ✅ Bootstrap completo - todos os gráficos criados');
      return true;
    } catch (e) {
      console.error('[Dashboard] ❌ Erro durante bootstrap:', e.message);
      return false;
    }
  };

  // Inicialização com retry robusto
  const initWithRetry = (attempt = 1) => {
    const MAX = 20;
    const DELAY = 100;
    
    console.log(`[Dashboard] Tentativa ${attempt}/${MAX}`);
    
    // Verificar Chart.js
    if (typeof Chart !== 'function') {
      console.log('[Dashboard] ❌ Chart.js não carregado ainda');
      if (attempt < MAX) {
        setTimeout(() => initWithRetry(attempt + 1), DELAY);
      } else {
        console.error('[Dashboard] ❌ Chart.js nunca foi carregado');
      }
      return;
    }
    
    console.log('[Dashboard] ✅ Chart.js carregado');
    
    // Verificar dados
    if (!window.__DASHBOARD_EXEC_DATA__) {
      console.log('[Dashboard] ❌ Dados não disponíveis ainda');
      if (attempt < MAX) {
        setTimeout(() => initWithRetry(attempt + 1), DELAY);
      } else {
        console.error('[Dashboard] ❌ Dados nunca foram injetados');
      }
      return;
    }
    
    console.log('[Dashboard] ✅ Dados disponíveis');
    
    // Tudo pronto, executar bootstrap
    bootstrap();
  };

  // Garantir que DOM está pronto antes de iniciar
  if (document.readyState === 'loading') {
    console.log('[Dashboard] DOM ainda carregando, aguardando DOMContentLoaded');
    document.addEventListener('DOMContentLoaded', () => {
      console.log('[Dashboard] DOMContentLoaded disparado');
      setTimeout(initWithRetry, 100);
    });
  } else {
    console.log('[Dashboard] DOM já pronto, iniciando com delay');
    setTimeout(initWithRetry, 100);
  }
})();
