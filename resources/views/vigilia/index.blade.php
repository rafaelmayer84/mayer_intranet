@extends('layouts.app')
@section('title', 'VIGÍLIA — Controle de Compromissos')

@section('content')
<div class="space-y-6" id="vigilia-app">

    {{-- HEADER com identidade visual --}}
    <div class="rounded-xl p-5 shadow-sm" style="background: linear-gradient(135deg, #1B334A 0%, #385776 100%); border-bottom: 3px solid #E8B931;">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center text-lg font-black" style="background:#E8B931;color:#1B334A;">V</div>
                <div>
                    <h1 class="text-xl font-bold text-white tracking-tight">VIGÍLIA</h1>
                    <p class="text-xs" style="color:#8BA3BB;">Controle de Compromissos & Accountability</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select id="vigilia-periodo" onchange="loadDashboard();loadCompromissos();updateRelLinks();"
                    class="rounded-lg border px-3 py-1.5 text-sm shadow-sm" style="background:#162535;color:#E8EDF2;border-color:#4A7399;">
                    <option value="mes-atual">Mês Atual</option>
                    <option value="mes-anterior">Mês Anterior</option>
                    <option value="trimestre">Último Trimestre</option>
                    <option value="semestre">Último Semestre</option>
                    <option value="ano">Ano 2026</option>
                </select>
                <button id="btn-cruzar" onclick="executarCruzamento()"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-bold shadow-sm transition hover:opacity-90"
                    style="background:#E8B931;color:#1B334A;">
                    🔄 Cruzar Agora
                </button>
                <span id="sync-info" class="text-xs" style="color:#8BA3BB;">Carregando...</span>
            </div>
        </div>
    </div>

    {{-- TABS --}}
    <div class="border-b border-gray-200">
        <nav class="flex gap-0 -mb-px" id="vigilia-tabs">
            <button onclick="switchTab('dashboard')" data-tab="dashboard" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-[#385776] text-[#1B334A]">📊 Dashboard</button>
            <button onclick="switchTab('alertas')" data-tab="alertas" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">🚨 Alertas <span id="alertas-badge" class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-bold bg-red-500 text-white ml-1">0</span></button>
            <button onclick="switchTab('compromissos')" data-tab="compromissos" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">📋 Compromissos</button>
            <button onclick="switchTab('triggers')" data-tab="triggers" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">⚡ Cobranças <span id="triggers-badge" class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-white ml-1">0</span></button>
            <button onclick="switchTab('relatorios')" data-tab="relatorios" class="tab-btn px-4 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-400 hover:text-gray-600">📑 Relatórios</button>
        </nav>
    </div>

    {{-- ═══ TAB DASHBOARD ═══ --}}
    <div id="panel-dashboard" class="tab-panel">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-[#385776] p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Total</p>
                <p class="text-3xl font-extrabold text-[#1B334A] mt-1" id="kpi-total">—</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Concluídos</p>
                <p class="text-3xl font-extrabold text-green-600 mt-1" id="kpi-concluidos">—</p>
                <p class="text-xs text-gray-400 mt-0.5" id="kpi-taxa">—</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-amber-400 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Não Iniciados</p>
                <p class="text-3xl font-extrabold text-amber-500 mt-1" id="kpi-nao-iniciados">—</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-gray-300 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Cancelados</p>
                <p class="text-3xl font-extrabold text-gray-400 mt-1" id="kpi-cancelados">—</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Alertas</p>
                <p class="text-3xl font-extrabold text-red-600 mt-1" id="kpi-alertas">—</p>
                <p class="text-xs text-gray-400 mt-0.5">requerem atenção</p>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-sm border">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <span class="font-bold text-[#1B334A]">Performance por Responsável</span>
                <div class="flex gap-2">
                    <a href="/vigilia/export/excel" class="px-3 py-1 rounded-lg text-xs font-medium border border-green-500 text-green-600 hover:bg-green-50 transition">📊 Excel</a>
                    <a href="/vigilia/export/pdf?tipo=consolidado" class="px-3 py-1 rounded-lg text-xs font-medium border border-[#385776] text-[#385776] hover:bg-gray-50 transition">📄 PDF</a>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="text-left px-5 py-2.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Responsável</th>
                            <th class="text-center px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Total</th>
                            <th class="text-center px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Concl.</th>
                            <th class="text-center px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Não In.</th>
                            <th class="text-center px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Canc.</th>
                            <th class="text-center px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Alertas</th>
                            <th class="px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Taxa Cumpr.</th>
                        </tr>
                    </thead>
                    <tbody id="ranking-body">
                        <tr><td colspan="7" class="text-center py-6 text-gray-400">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ TAB ALERTAS ═══ --}}
    <div id="panel-alertas" class="tab-panel hidden">
        <div id="alertas-container">
            <p class="text-center text-gray-400 py-8">Carregando alertas...</p>
        </div>
    </div>

    {{-- ═══ TAB COMPROMISSOS ═══ --}}
    <div id="panel-compromissos" class="tab-panel hidden">
        <div class="flex flex-wrap gap-2 mb-4 items-center">
            <select id="filtro-responsavel" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-[#385776]">
                <option value="">Todos os responsáveis</option>
                @foreach($responsaveis as $r)
                    <option value="{{ $r }}">{{ $r }}</option>
                @endforeach
            </select>
            <select id="filtro-status" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-[#385776]">
                <option value="">Todas as situações</option>
                <option value="Concluído">Concluído</option>
                <option value="Não iniciado">Não iniciado</option>
                <option value="Cancelado">Cancelado</option>
            </select>
            <button onclick="loadCompromissos()" class="inline-flex items-center rounded-lg px-4 py-1.5 text-sm font-medium text-white shadow-sm transition hover:opacity-90" style="background:#385776;">Filtrar</button>
            <span class="ml-auto text-xs text-gray-400" id="compromissos-count">—</span>
        </div>
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b">
                        <th class="text-left px-4 py-2.5 text-xs font-bold text-gray-500 uppercase">Situação</th>
                        <th class="text-left px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Responsável</th>
                        <th class="text-left px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Tipo</th>
                        <th class="text-left px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Processo</th>
                        <th class="text-left px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Data</th>
                        <th class="text-left px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Prazo Fatal</th>
                        <th class="text-center px-3 py-2.5 text-xs font-bold text-gray-500 uppercase">Cruzamento</th>
                    </tr>
                </thead>
                <tbody id="compromissos-body">
                    <tr><td colspan="7" class="text-center py-6 text-gray-400">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="compromissos-pagination" class="mt-3 flex justify-center"></div>
    </div>

    {{-- ═══ TAB COBRANÇAS (TRIGGERS) ═══ --}}
    <div id="panel-triggers" class="tab-panel hidden">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6" id="trigger-kpis">
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-amber-400 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Total Tarefas Sensíveis</p>
                <p class="text-3xl font-extrabold text-[#1B334A] mt-1" id="trig-total">—</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-orange-500 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Pendentes</p>
                <p class="text-3xl font-extrabold text-orange-500 mt-1" id="trig-pendentes">—</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-red-500 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Vencidos (48h+)</p>
                <p class="text-3xl font-extrabold text-red-600 mt-1" id="trig-vencidos">—</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border-l-4 border-green-500 p-4">
                <p class="text-xs text-gray-500 uppercase font-bold tracking-wide">Concluídos</p>
                <p class="text-3xl font-extrabold text-green-600 mt-1" id="trig-concluidos">—</p>
            </div>
        </div>

        <div id="trigger-list">
            <p class="text-center text-gray-400 py-8">Carregando...</p>
        </div>
    </div>

    {{-- ═══ TAB RELATÓRIOS ═══ --}}
    <div id="panel-relatorios" class="tab-panel hidden">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" style="background:#E8B93120;color:#E8B931;">👤</span>
                    <h3 class="font-bold text-sm text-[#1B334A]">Relatório Individual</h3>
                </div>
                <p class="text-xs text-gray-500 mb-3">Compromissos, taxa, alertas e confiabilidade por advogado.</p>
                <select id="rel-adv" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm w-full mb-3">
                    @foreach($responsaveis as $r)
                        <option value="{{ $r }}">{{ $r }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <a id="link-rel-individual" href="/vigilia/relatorio/individual" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-[#385776] text-[#385776] hover:bg-gray-50 transition">🖥 Tela</a>
                    <a id="link-rel-individual-pdf" href="/vigilia/export/pdf?tipo=individual" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-blue-500 text-blue-600 hover:bg-blue-50 transition">📄 PDF</a>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" style="background:#DC354520;color:#DC3545;">⏰</span>
                    <h3 class="font-bold text-sm text-[#1B334A]">Prazos Críticos</h3>
                </div>
                <p class="text-xs text-gray-500 mb-3">Prazos vencidos e vencendo. <strong>Gerado diariamente 07:00.</strong></p>
                <div class="flex gap-2 mt-auto">
                    <a href="/vigilia/relatorio/prazos" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-[#385776] text-[#385776] hover:bg-gray-50 transition">🖥 Tela</a>
                    <a href="/vigilia/export/pdf?tipo=prazos" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-blue-500 text-blue-600 hover:bg-blue-50 transition">📄 PDF</a>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" style="background:#38577620;color:#385776;">📑</span>
                    <h3 class="font-bold text-sm text-[#1B334A]">Consolidado Mensal</h3>
                </div>
                <p class="text-xs text-gray-500 mb-3">Ranking, distribuição por tipo, resumo executivo para reunião.</p>
                <div class="flex gap-2">
                    <a href="/vigilia/relatorio/consolidado" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-[#385776] text-[#385776] hover:bg-gray-50 transition">🖥 Tela</a>
                    <a href="/vigilia/export/pdf?tipo=consolidado" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-blue-500 text-blue-600 hover:bg-blue-50 transition">📄 PDF</a>
                    <a href="/vigilia/export/excel" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-green-500 text-green-600 hover:bg-green-50 transition">📊 Excel</a>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
                <div class="flex items-center gap-2 mb-2">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-sm" style="background:#4A739920;color:#4A7399;">🔍</span>
                    <h3 class="font-bold text-sm text-[#1B334A]">Cruzamento</h3>
                </div>
                <p class="text-xs text-gray-500 mb-3">Auditoria conclusões vs. andamentos. Índice de confiabilidade.</p>
                <div class="flex gap-2">
                    <a href="/vigilia/relatorio/cruzamento" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-[#385776] text-[#385776] hover:bg-gray-50 transition">🖥 Tela</a>
                    <a href="/vigilia/export/pdf?tipo=cruzamento" class="px-3 py-1.5 rounded-lg text-xs font-medium border border-blue-500 text-blue-600 hover:bg-blue-50 transition">📄 PDF</a>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-[#385776]', 'text-[#1B334A]');
        b.classList.add('border-transparent', 'text-gray-400');
    });
    document.getElementById('panel-' + tabId).classList.remove('hidden');
    const btn = document.querySelector(`[data-tab="${tabId}"]`);
    btn.classList.add('border-[#385776]', 'text-[#1B334A]');
    btn.classList.remove('border-transparent', 'text-gray-400');
    if (tabId === 'alertas') loadAlertas();
    if (tabId === 'compromissos') loadCompromissos();
    if (tabId === 'triggers') loadTriggers();
}

const periodo = () => document.getElementById('vigilia-periodo').value;

function loadDashboard() {
    fetch(`/vigilia/api/resumo?periodo=${periodo()}`)
        .then(r => r.json())
        .then(d => {
            document.getElementById('kpi-total').textContent = d.resumo.total;
            document.getElementById('kpi-concluidos').textContent = d.resumo.concluidos;
            document.getElementById('kpi-taxa').textContent = d.resumo.taxa + '% taxa';
            document.getElementById('kpi-nao-iniciados').textContent = d.resumo.naoIniciados;
            document.getElementById('kpi-cancelados').textContent = d.resumo.cancelados;
            document.getElementById('kpi-alertas').textContent = d.resumo.alertas;
            document.getElementById('alertas-badge').textContent = d.resumo.alertas;
            document.getElementById('sync-info').textContent = 'Atualizado agora';

            let html = '';
            d.ranking.sort((a, b) => b.taxa - a.taxa).forEach((r, i) => {
                const cor = r.taxa >= 80 ? 'text-green-600' : (r.taxa >= 50 ? 'text-amber-500' : 'text-red-600');
                const bg = r.taxa >= 80 ? 'bg-green-500' : (r.taxa >= 50 ? 'bg-amber-400' : 'bg-red-500');
                const alertBadge = r.alertas > 0 ? `<span class="inline-flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold bg-red-100 text-red-600">${r.alertas}</span>` : '<span class="text-gray-300">—</span>';
                const rowBg = i % 2 === 0 ? '' : 'bg-gray-50/50';
                html += `<tr class="border-b hover:bg-blue-50/30 transition ${rowBg}">
                    <td class="px-5 py-3 font-semibold text-[#1B334A]">${r.responsavel_nome}</td>
                    <td class="text-center px-3 py-3 font-bold text-[#1B334A]">${r.total}</td>
                    <td class="text-center px-3 py-3 text-green-600 font-semibold">${r.concluidos}</td>
                    <td class="text-center px-3 py-3 text-amber-500 font-semibold">${r.nao_iniciados}</td>
                    <td class="text-center px-3 py-3 text-gray-400">${r.cancelados}</td>
                    <td class="text-center px-3 py-3">${alertBadge}</td>
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-2">
                            <div class="w-20 h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div class="h-full rounded-full ${bg}" style="width:${r.taxa}%"></div>
                            </div>
                            <span class="${cor} font-bold text-xs">${r.taxa}%</span>
                        </div>
                    </td>
                </tr>`;
            });
            document.getElementById('ranking-body').innerHTML = html || '<tr><td colspan="7" class="text-center py-6 text-gray-400">Nenhum dado.</td></tr>';
        });
}

function loadAlertas() {
    fetch('/vigilia/api/alertas')
        .then(r => r.json())
        .then(d => {
            if (d.alertas.length === 0) {
                document.getElementById('alertas-container').innerHTML = '<div class="text-center text-gray-400 py-12 bg-white rounded-xl border">✅ Nenhum alerta ativo. Todos os compromissos estão em dia.</div>';
                return;
            }
            let html = '<div class="space-y-2">';
            d.alertas.slice(0, 50).forEach(a => {
                const sevBorder = a.severidade === 'critico' ? 'border-l-red-500 bg-red-50/50' : (a.severidade === 'alto' ? 'border-l-amber-400 bg-amber-50/30' : 'border-l-gray-300 bg-gray-50/30');
                const sevBadge = a.severidade === 'critico' ? '<span class="text-xs font-bold text-white bg-red-500 px-2 py-0.5 rounded">CRÍTICO</span>' : (a.severidade === 'alto' ? '<span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">ALTO</span>' : '<span class="text-xs font-bold text-gray-500 bg-gray-200 px-2 py-0.5 rounded">MÉDIO</span>');
                const dias = a.dias_atraso > 0 ? `<span class="text-red-600 font-bold text-xs">${a.dias_atraso}d atraso</span>` : (a.dias_atraso !== null && a.dias_atraso < 0 ? `<span class="text-amber-500 font-bold text-xs">vence em ${Math.abs(a.dias_atraso)}d</span>` : '');
                const and_ = a.tem_andamento ? '<span class="text-green-600 font-semibold text-xs">✓ Há andamento</span>' : '<span class="text-red-500 font-semibold text-xs">✕ Sem andamento</span>';

                html += `<div class="rounded-xl border-l-4 ${sevBorder} bg-white shadow-sm p-4">
                    <div class="flex justify-between items-start mb-1.5">
                        <div class="flex items-center gap-2">${sevBadge} <strong class="text-sm text-[#1B334A]">${a.tipo_atividade}</strong></div>
                        ${dias}
                    </div>
                    <div class="flex flex-wrap gap-4 text-xs text-gray-500">
                        <span>👤 ${a.responsavel}</span>
                        <span>📁 ${a.processo || '—'}</span>
                        <span>📅 Prazo: ${a.prazo_fatal || '—'}</span>
                        ${and_}
                    </div>
                </div>`;
            });
            html += '</div>';
            document.getElementById('alertas-container').innerHTML = html;
        });
}

function loadCompromissos(page) {
    page = page || 1;
    const resp = document.getElementById('filtro-responsavel').value;
    const status = document.getElementById('filtro-status').value;
    const params = new URLSearchParams({periodo: periodo(), page});
    if (resp) params.set('responsavel', resp);
    if (status) params.set('status', status);

    fetch(`/vigilia/api/compromissos?${params}`)
        .then(r => r.json())
        .then(d => {
            document.getElementById('compromissos-count').textContent = `${d.total} registros`;
            let html = '';
            d.data.forEach((c, i) => {
                const isSusp = c.status === 'Concluído' && c.status_cruzamento === 'suspeito';
                const sitBg = c.status === 'Concluído' ? (isSusp ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700') : (c.status === 'Não iniciado' ? 'bg-red-100 text-red-600' : 'bg-gray-100 text-gray-500');
                const sitLabel = isSusp ? '⚠ Suspeito' : c.status;
                const cruz = !c.status_cruzamento ? '—' : {verificado:'<span class="text-green-600 font-bold">✓</span>', suspeito:'<span class="text-amber-500 font-bold">⚠</span>', sem_acao:'<span class="text-red-500 font-bold">✕</span>', nao_aplicavel:'<span class="text-gray-300">N/A</span>', futuro:'<span class="text-blue-400">⏳</span>', pendente:'…'}[c.status_cruzamento] || c.status_cruzamento;
                const rowBg = i % 2 === 0 ? '' : 'bg-gray-50/50';

                html += `<tr class="border-b hover:bg-blue-50/30 transition ${rowBg}">
                    <td class="px-4 py-2.5"><span class="text-xs font-semibold px-2 py-1 rounded-lg ${sitBg}">${sitLabel}</span></td>
                    <td class="px-3 py-2.5 font-semibold text-[#1B334A] text-xs">${c.responsavel_nome}</td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">${c.tipo_atividade || '—'}</td>
                    <td class="px-3 py-2.5 text-xs font-mono text-[#4A7399]">${c.processo_pasta || '—'}</td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">${c.data_hora ? new Date(c.data_hora).toLocaleDateString('pt-BR') : '—'}</td>
                    <td class="px-3 py-2.5 text-xs text-gray-600">${c.data_prazo_fatal || '—'}</td>
                    <td class="px-3 py-2.5 text-center">${cruz}</td>
                </tr>`;
            });
            document.getElementById('compromissos-body').innerHTML = html || '<tr><td colspan="7" class="text-center py-6 text-gray-400">Nenhum registro.</td></tr>';

            let pag = '';
            if (d.last_page > 1) {
                pag = '<div class="flex gap-1">';
                for (let i = 1; i <= Math.min(d.last_page, 10); i++) {
                    pag += `<button onclick="loadCompromissos(${i})" class="px-3 py-1.5 text-xs rounded-lg font-medium ${i === d.current_page ? 'text-white shadow-sm' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 transition'}" ${i === d.current_page ? 'style="background:#385776;"' : ''}>${i}</button>`;
                }
                pag += '</div>';
            }
            document.getElementById('compromissos-pagination').innerHTML = pag;
        });
}

function executarCruzamento() {
    const btn = document.getElementById('btn-cruzar');
    btn.disabled = true; btn.textContent = '⏳ Cruzando...';
    fetch('/vigilia/api/cruzar', {method:'POST', headers:{'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content}})
        .then(r => r.json())
        .then(d => {
            btn.disabled = false; btn.textContent = '🔄 Cruzar Agora';
            alert('Cruzamento concluído!\nVerificados: ' + (d.stats.verificado||0) + '\nSuspeitos: ' + (d.stats.suspeito||0) + '\nSem ação: ' + (d.stats.sem_acao||0));
            loadDashboard();
        })
        .catch(() => { btn.disabled = false; btn.textContent = '🔄 Cruzar Agora'; });
}

function updateRelLinks() {
    const adv = document.getElementById('rel-adv').value;
    const p = periodo();
    document.getElementById('link-rel-individual').href = `/vigilia/relatorio/individual?responsavel=${encodeURIComponent(adv)}&periodo=${p}`;
    document.getElementById('link-rel-individual-pdf').href = `/vigilia/export/pdf?tipo=individual&responsavel=${encodeURIComponent(adv)}&periodo=${p}`;
}

document.getElementById('rel-adv').addEventListener('change', updateRelLinks);
loadDashboard();
updateRelLinks();
loadTriggers();

function loadTriggers() {
    fetch('/vigilia/api/triggers')
        .then(r => r.json())
        .then(d => {
            document.getElementById('trig-total').textContent = d.total;
            document.getElementById('trig-pendentes').textContent = d.pendentes;
            document.getElementById('trig-vencidos').textContent = d.vencidos;
            document.getElementById('trig-concluidos').textContent = d.concluidos;
            document.getElementById('triggers-badge').textContent = d.pendentes + d.vencidos;

            let html = '';
            const todos = [...(d.detalhes_pendentes || []), ...(d.detalhes_vencidos || [])];

            if (todos.length === 0) {
                html = '<div class="bg-white rounded-xl border p-8 text-center text-gray-400">✅ Nenhuma tarefa sensível pendente.</div>';
            } else {
                html = '<div class="space-y-3">';
                todos.forEach(t => {
                    const isVencido = t.vencido;
                    const borderColor = isVencido ? 'border-l-red-500 bg-red-50/30' : 'border-l-amber-400 bg-amber-50/20';
                    const statusBadge = isVencido
                        ? '<span class="text-xs font-bold text-white bg-red-500 px-2 py-0.5 rounded">VENCIDO ' + Math.round(t.horas_desde_criacao) + 'h</span>'
                        : '<span class="text-xs font-bold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">PENDENTE ' + Math.round(t.horas_desde_criacao) + 'h/' + t.prazo_horas + 'h</span>';
                    const parecerBadge = t.tem_parecer
                        ? '<span class="text-green-600 font-semibold text-xs">✓ Parecer preenchido</span>'
                        : '<span class="text-red-500 font-semibold text-xs">✕ Sem parecer no DataJuri</span>';

                    html += '<div class="rounded-xl border-l-4 ' + borderColor + ' bg-white shadow-sm p-4">' +
                        '<div class="flex justify-between items-start mb-2">' +
                            '<div class="flex items-center gap-2">' + statusBadge + ' <strong class="text-sm text-[#1B334A]">' + t.assunto + '</strong></div>' +
                        '</div>' +
                        '<div class="flex flex-wrap gap-4 text-xs text-gray-500">' +
                            '<span>👤 ' + t.responsavel + '</span>' +
                            '<span>📁 ' + (t.processo_pasta || '—') + '</span>' +
                            '<span>📅 ' + (t.data_hora ? new Date(t.data_hora).toLocaleDateString('pt-BR') : '—') + '</span>' +
                            (t.assunto_alterado ? '<span class="text-purple-600 font-semibold text-xs">🔄 Assunto alterado de: ' + t.assunto_original + '</span>' : '') +
                            parecerBadge +
                        '</div>' +
                    '</div>';
                });
                html += '</div>';
            }
            document.getElementById('trigger-list').innerHTML = html;
        });
}
</script>
@endpush
@endsection
