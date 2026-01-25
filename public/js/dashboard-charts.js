(function () {
  'use strict';

  // =====================================================================
  // Dashboard Charts (Visão Gerencial)
  // - Sem mudanças de layout (HTML/CSS). Tudo é injetado via JS.
  // - Inicialização com retry (Chart.js / DOM / __DASHBOARD_EXEC_DATA__).
  // - Debug visual opcional (overlay) para diagnosticar por que não renderiza.
  // =====================================================================

  var DEBUG_ENABLED = false;
  try {
    DEBUG_ENABLED = /(?:\?|&)chartsDebug=1(?:&|$)/i.test(location.search) || localStorage.getItem('chartsDebug') === '1';
  } catch (e) {
    DEBUG_ENABLED = false;
  }

  var DEBUG = {
    fileLoadedAt: new Date().toISOString(),
    bootstrapped: false,
    lastCheck: null,
    charts: [],
    logs: [],
    errors: []
  };
  window.__DASHBOARD_CHARTS_DEBUG__ = DEBUG;

  function nowHHMMSS() {
    var d = new Date();
    var hh = String(d.getHours()).padStart(2, '0');
    var mm = String(d.getMinutes()).padStart(2, '0');
    var ss = String(d.getSeconds()).padStart(2, '0');
    return hh + ':' + mm + ':' + ss;
  }

  function log(level, msg, extra) {
    try {
      var entry = { t: nowHHMMSS(), level: level, msg: msg };
      if (extra !== undefined) entry.extra = extra;
      DEBUG.logs.push(entry);
      if (DEBUG.logs.length > 200) DEBUG.logs.shift();
    } catch (e) {}

    try {
      var fn = console && console.log;
      if (console && level === 'error') fn = console.error;
      else if (console && level === 'warn') fn = console.warn;
      else if (console && level === 'info') fn = console.info;
      if (fn) fn('[dashboard-charts][' + level + ']', msg, extra || '');
    } catch (e2) {}

    if (DEBUG_ENABLED) updateDebugPanel();
  }

  function captureWindowError(evt) {
    try {
      var msg = (evt && evt.message) ? String(evt.message) : 'window.error';
      var stack = (evt && evt.error && evt.error.stack) ? String(evt.error.stack) : '';
      DEBUG.errors.push({ t: nowHHMMSS(), label: 'window.error', message: msg, stack: stack });
      if (DEBUG.errors.length > 50) DEBUG.errors.shift();
    } catch (e) {}
    if (DEBUG_ENABLED) updateDebugPanel();
  }

  if (DEBUG_ENABLED) {
    window.addEventListener('error', captureWindowError);
  }

  function drawCanvasMessage(canvasId, title, lines) {
    var el = null;
    try { el = document.getElementById(canvasId); } catch (e) { el = null; }
    if (!el || !el.getContext) return;
    var ctx = el.getContext('2d');
    if (!ctx) return;
    try {
      ctx.save();
      ctx.clearRect(0, 0, el.width, el.height);
      ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
      ctx.fillRect(0, 0, el.width, el.height);
      ctx.fillStyle = '#e2e8f0';
      ctx.font = '14px system-ui, -apple-system, Segoe UI, Roboto, Arial';
      ctx.fillText(title || 'DEBUG', 12, 24);
      ctx.font = '12px system-ui, -apple-system, Segoe UI, Roboto, Arial';
      var y = 44;
      (lines || []).slice(0, 10).forEach(function (ln) {
        ctx.fillText(String(ln).slice(0, 120), 12, y);
        y += 16;
      });
      ctx.restore();
    } catch (e2) {
      // silencioso
    }
  }

  function canvasHasInk(canvasEl) {
    // Heurística rápida: amostra alguns pixels e vê se há alfa/cor > 0.
    try {
      if (!canvasEl) return true;
      var ctx = canvasEl.getContext('2d');
      if (!ctx) return true;
      var w = canvasEl.width || 0;
      var h = canvasEl.height || 0;
      if (w < 10 || h < 10) return false;
      var img = ctx.getImageData(0, 0, w, h).data;
      var samples = 30;
      for (var i = 0; i < samples; i++) {
        var x = ((i * 997) % w) | 0;
        var y = ((i * 463) % h) | 0;
        var idx = (y * w + x) * 4;
        var a = img[idx + 3];
        if (a > 0) {
          var r = img[idx], g = img[idx + 1], b = img[idx + 2];
          if (r + g + b > 5) return true;
        }
      }
      return false;
    } catch (e) {
      // Se getImageData falhar por qualquer motivo, não assume falha do gráfico.
      return true;
    }
  }

  function scheduleInkCheck(canvasId, label) {
    try {
      setTimeout(function () {
        var el = byId(canvasId);
        if (!el) return;
        if (canvasHasInk(el)) return;
        log('error', 'Canvas parece vazio (sem pixels desenhados)', {
          canvasId: canvasId,
          label: label,
          w: el.width,
          h: el.height,
        });
        drawCanvasMessage(canvasId, 'Gráfico não renderizou', [
          label || canvasId,
          'Chart criado, mas canvas está vazio.',
          'Use o painel: Forçar render / Copiar diagnóstico.',
        ]);
      }, 650);
    } catch (e) {}
  }

  var _debugPanelEl = null;
  function ensureDebugPanel() {
    if (!DEBUG_ENABLED) return;
    if (_debugPanelEl) return;
    try {
      var el = document.createElement('div');
      el.id = 'charts-debug-panel';
      el.style.position = 'fixed';
      el.style.right = '16px';
      el.style.bottom = '16px';
      el.style.zIndex = '99999';
      el.style.width = '360px';
      el.style.maxWidth = '92vw';
      el.style.maxHeight = '70vh';
      el.style.overflow = 'hidden';
      el.style.borderRadius = '12px';
      el.style.border = '1px solid rgba(148, 163, 184, 0.25)';
      el.style.background = 'rgba(2, 6, 23, 0.88)';
      el.style.color = '#e2e8f0';
      el.style.fontFamily = 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace';
      el.style.fontSize = '12px';
      el.style.boxShadow = '0 10px 30px rgba(0,0,0,0.35)';

      el.innerHTML =
        '<div style="display:flex;align-items:center;gap:8px;padding:10px 10px 6px 10px;border-bottom:1px solid rgba(148,163,184,0.18)">' +
          '<div style="font-weight:700;flex:1">Charts Debug (PF/PJ/Rentabilidade)</div>' +
          '<button data-act="force" style="background:rgba(148,163,184,0.18);border:1px solid rgba(148,163,184,0.28);color:#e2e8f0;border-radius:8px;padding:6px 8px;cursor:pointer">Forçar render</button>' +
          '<button data-act="copy" style="background:rgba(148,163,184,0.18);border:1px solid rgba(148,163,184,0.28);color:#e2e8f0;border-radius:8px;padding:6px 8px;cursor:pointer">Copiar diagnóstico</button>' +
          '<button data-act="min" style="background:rgba(148,163,184,0.18);border:1px solid rgba(148,163,184,0.28);color:#e2e8f0;border-radius:8px;padding:6px 8px;cursor:pointer">Minimizar</button>' +
        '</div>' +
        '<div data-body style="padding:10px;max-height:60vh;overflow:auto"></div>';

      document.body.appendChild(el);
      _debugPanelEl = el;

      el.addEventListener('click', function (ev) {
        var t = ev && ev.target;
        if (!t || !t.getAttribute) return;
        var act = t.getAttribute('data-act');
        if (!act) return;
        if (act === 'force') {
          try { window.__DASHBOARD_CHARTS_FORCE_RENDER__ && window.__DASHBOARD_CHARTS_FORCE_RENDER__(); } catch (e) {}
        }
        if (act === 'copy') {
          try {
            var txt = JSON.stringify(getDiagnosticsSnapshot(), null, 2);
            navigator.clipboard.writeText(txt);
          } catch (e2) {
            try {
              var ta = document.createElement('textarea');
              ta.value = JSON.stringify(getDiagnosticsSnapshot(), null, 2);
              document.body.appendChild(ta);
              ta.select();
              document.execCommand('copy');
              ta.remove();
            } catch (e3) {}
          }
        }
        if (act === 'min') {
          try {
            var body = el.querySelector('[data-body]');
            if (!body) return;
            if (body.style.display === 'none') {
              body.style.display = 'block';
              t.textContent = 'Minimizar';
            } else {
              body.style.display = 'none';
              t.textContent = 'Expandir';
            }
          } catch (e4) {}
        }
      });

      updateDebugPanel();
    } catch (e) {
      _debugPanelEl = null;
    }
  }

  function updateDebugPanel() {
    if (!DEBUG_ENABLED) return;
    ensureDebugPanel();
    if (!_debugPanelEl) return;

    var body = _debugPanelEl.querySelector('[data-body]');
    if (!body) return;

    var snap = getDiagnosticsSnapshot();
    var okBadge = function (ok, label) {
      var bg = ok ? 'rgba(34,197,94,0.18)' : 'rgba(239,68,68,0.18)';
      var bd = ok ? 'rgba(34,197,94,0.35)' : 'rgba(239,68,68,0.35)';
      return '<span style="display:inline-block;padding:4px 8px;border-radius:999px;background:' + bg + ';border:1px solid ' + bd + ';margin-right:6px">' + label + '</span>';
    };

    var lines = [];
    lines.push('<div style="margin-bottom:8px">' + okBadge(snap.lastCheck.chartJs, 'Chart.js ' + (snap.lastCheck.chartJs ? 'OK' : 'NOK')) + okBadge(snap.lastCheck.data, '__DASHBOARD_EXEC_DATA__ ' + (snap.lastCheck.data ? 'OK' : 'NOK')) + '</div>');
    lines.push('<div style="margin-bottom:8px">' + okBadge(snap.lastCheck.keysOk, 'Estrutura dados ' + (snap.lastCheck.keysOk ? 'OK' : 'NOK')) + '</div>');
    lines.push('<div style="opacity:0.85;margin-bottom:8px">Arquivo carregado em: ' + snap.fileLoadedAt + '</div>');

    if (snap.lastCheck.canvases) {
      var pf = snap.lastCheck.canvases.pf ? (snap.lastCheck.canvases.pf.width + 'x' + snap.lastCheck.canvases.pf.height) : 'N/A';
      var pj = snap.lastCheck.canvases.pj ? (snap.lastCheck.canvases.pj.width + 'x' + snap.lastCheck.canvases.pj.height) : 'N/A';
      var rt = snap.lastCheck.canvases.rent ? (snap.lastCheck.canvases.rent.width + 'x' + snap.lastCheck.canvases.rent.height) : 'N/A';
      lines.push('<div style="margin-bottom:8px">Canvas</div>');
      lines.push('<div style="margin-bottom:10px">' + okBadge(!!snap.lastCheck.canvases.pf, 'PF ' + pf) + okBadge(!!snap.lastCheck.canvases.pj, 'PJ ' + pj) + okBadge(!!snap.lastCheck.canvases.rent, 'Rent. ' + rt) + '</div>');
    }

    lines.push('<div style="margin-bottom:6px">Charts criados</div>');
    lines.push('<div style="opacity:0.9;margin-bottom:10px">' + (snap.charts && snap.charts.length ? snap.charts.join(', ') : '(nenhum)') + '</div>');

    lines.push('<div style="margin-bottom:6px">Logs (últimos 12)</div>');
    lines.push('<div style="white-space:pre-wrap;line-height:1.35;background:rgba(148,163,184,0.08);border:1px solid rgba(148,163,184,0.18);border-radius:10px;padding:8px;max-height:180px;overflow:auto">' +
      (snap.logsTail || []).map(function (l) {
        return '[' + l.t + '][' + l.level + '] ' + l.msg + (l.extra ? ' ' + JSON.stringify(l.extra) : '');
      }).join('\n') +
    '</div>');

    lines.push('<div style="margin-top:10px;margin-bottom:6px">Erros capturados (últimos 6)</div>');
    lines.push('<div style="white-space:pre-wrap;line-height:1.35;background:rgba(148,163,184,0.08);border:1px solid rgba(148,163,184,0.18);border-radius:10px;padding:8px;max-height:170px;overflow:auto">' +
      (snap.errorsTail || []).map(function (e) {
        return '[' + e.t + '][' + e.label + '] ' + e.message;
      }).join('\n') +
    '</div>');

    body.innerHTML = lines.join('');
  }

  function getDiagnosticsSnapshot() {
    var out = {
      fileLoadedAt: DEBUG.fileLoadedAt,
      lastCheck: DEBUG.lastCheck,
      charts: DEBUG.charts.slice(0),
      logsTail: DEBUG.logs.slice(-12),
      errorsTail: DEBUG.errors.slice(-6)
    };
    return out;
  }

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

  function destroyAnyChartOnCanvas(canvasId) {
    // 1) Se já temos referência local, destrói primeiro
    try {
      Object.keys(charts).forEach(function (k) {
        if (charts[k] && charts[k].canvas && charts[k].canvas.id === canvasId) {
          charts[k].destroy();
          delete charts[k];
        }
      });
    } catch (_) {}

    // 2) Fallback: destrói via Chart.getChart (cobre casos fora do registry local)
    try {
      if (typeof Chart === 'function' && typeof Chart.getChart === 'function') {
        var ch = Chart.getChart(canvasId);
        if (!ch) {
          var el = byId(canvasId);
          if (el) ch = Chart.getChart(el);
        }
        if (ch) ch.destroy();
      }
    } catch (_) {}

    // 3) Limpa o canvas para evitar “fantasma” visual
    try {
      var c = byId(canvasId);
      if (c && c.getContext) {
        var g = c.getContext('2d');
        if (g) {
          g.clearRect(0, 0, c.width || 0, c.height || 0);
        }
      }
    } catch (_) {}
  }

  const buildReceitaLineChart = (ctxId, title, labels, meta, realizado) => {
    const ctx = byId(ctxId);
    if (!ctx) return null;
    const ch = new Chart(ctx, {
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

    // Em algumas renderizações (principalmente mobile), o layout ainda está
    // assentando quando o gráfico é criado. Forçamos uma rodada de resize/update.
    try {
      requestAnimationFrame(function () {
        try {
          ch.resize();
          ch.update();
        } catch (_) {}
      });
    } catch (_) {}

    return ch;
  };

  const buildReceitaPFChart = (data) => {
    const d = data.receitaPF12Meses || {};
    const labels = d.meses || [];
    const meta = (d.meta || []).map((n) => Number(n));
    const real = (d.realizado || []).map((n) => Number(n));
    destroyAnyChartOnCanvas('chart-receita-pf');
    charts.receitaPF = buildReceitaLineChart('chart-receita-pf', 'Receita PF', labels, meta, real);
    scheduleInkCheck('chart-receita-pf', 'Receita PF');
  };

  const buildReceitaPJChart = (data) => {
    const d = data.receitaPJ12Meses || {};
    const labels = d.meses || [];
    const meta = (d.meta || []).map((n) => Number(n));
    const real = (d.realizado || []).map((n) => Number(n));
    destroyAnyChartOnCanvas('chart-receita-pj');
    charts.receitaPJ = buildReceitaLineChart('chart-receita-pj', 'Receita PJ', labels, meta, real);
    scheduleInkCheck('chart-receita-pj', 'Receita PJ');
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
    destroyAnyChartOnCanvas('chart-rentabilidade');
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

    try {
      requestAnimationFrame(function () {
        try {
          charts.lucratividade.resize();
          charts.lucratividade.update();
        } catch (_) {}
      });
    } catch (_) {}

    scheduleInkCheck('chart-rentabilidade', 'Rentabilidade/Lucratividade');
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
