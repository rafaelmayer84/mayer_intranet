@extends('layouts.app')

@section('title', 'NEXO — Painel Gerencial')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- HEADER --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">NEXO — Painel Gerencial</h1>
            <p class="text-sm text-gray-500 mt-1" id="periodo-label">Carregando...</p>
        </div>
        <div class="flex flex-wrap items-center gap-3 mt-3 md:mt-0">
            {{-- Filtro de período --}}
            <div class="flex items-center bg-white border rounded-lg shadow-sm overflow-hidden text-sm">
                <button onclick="NexoGerencial.setPeriodo('hoje')" class="px-3 py-2 hover:bg-gray-100 periodo-btn" data-p="hoje">Hoje</button>
                <button onclick="NexoGerencial.setPeriodo('7d')" class="px-3 py-2 hover:bg-gray-100 periodo-btn border-l" data-p="7d">7 dias</button>
                <button onclick="NexoGerencial.setPeriodo('30d')" class="px-3 py-2 hover:bg-gray-100 periodo-btn border-l" data-p="30d">30 dias</button>
                <button onclick="NexoGerencial.setPeriodo('custom')" class="px-3 py-2 hover:bg-gray-100 periodo-btn border-l" data-p="custom">Custom</button>
            </div>
            {{-- Custom dates --}}
            <div id="custom-dates" class="hidden flex items-center gap-2">
                <input type="date" id="date-de" class="border rounded px-2 py-1.5 text-sm">
                <span class="text-gray-400">a</span>
                <input type="date" id="date-ate" class="border rounded px-2 py-1.5 text-sm">
                <button onclick="NexoGerencial.carregarDados()" class="bg-[#385776] text-white px-3 py-1.5 rounded text-sm hover:bg-[#1B334A]">Aplicar</button>
            </div>
            {{-- Toggle janela --}}
            <label class="flex items-center gap-2 text-sm text-gray-600 bg-white border rounded-lg px-3 py-2 shadow-sm cursor-pointer">
                <input type="checkbox" id="toggle-janela" checked class="rounded" onchange="NexoGerencial.carregarDados()">
                <span>Somente 09–18h</span>
            </label>
        </div>
    </div>

    {{-- LOADING --}}
    <div id="loading" class="text-center py-12">
        <div class="inline-block w-8 h-8 border-4 border-gray-200 border-t-[#385776] rounded-full animate-spin"></div>
        <p class="mt-3 text-gray-500">Carregando dados...</p>
    </div>

    {{-- CONTEÚDO (hidden até carregar) --}}
    <div id="conteudo" class="hidden">

        {{-- ══════════════════════════════════════════════════ --}}
        {{-- ABAS: WhatsApp | Tickets | Consolidado --}}
        {{-- ══════════════════════════════════════════════════ --}}
        <div class="flex border-b mb-6">
            <button onclick="NexoGerencial.setAba('whatsapp')" class="px-4 py-2 text-sm font-medium border-b-2 aba-btn" data-aba="whatsapp">WhatsApp</button>
            <button onclick="NexoGerencial.setAba('tickets')" class="px-4 py-2 text-sm font-medium border-b-2 aba-btn" data-aba="tickets">Tickets</button>
        </div>

        {{-- ══════════════════════════════════════════════════ --}}
        {{-- KPI CARDS — WHATSAPP --}}
        {{-- ══════════════════════════════════════════════════ --}}
        <div id="aba-whatsapp">
            <div class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-6 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-4 cursor-pointer hover:shadow-md transition" onclick="NexoGerencial.drill('entradas')">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Entradas</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1" id="kpi-entradas">—</p>
                    <p class="text-xs text-gray-400 mt-1">conversas no período</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 cursor-pointer hover:shadow-md transition" onclick="NexoGerencial.drill('resolvidos')">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Resolvidos</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1" id="kpi-resolvidos">—</p>
                    <p class="text-xs text-gray-400 mt-1">fechados no período</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 cursor-pointer hover:shadow-md transition" onclick="NexoGerencial.drill('backlog')">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Backlog</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1" id="kpi-backlog">—</p>
                    <p class="text-xs text-gray-400 mt-1">abertos agora</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 cursor-pointer hover:shadow-md transition" onclick="NexoGerencial.drill('sem_resposta')">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Sem Resposta</p>
                    <p class="text-2xl font-bold text-red-600 mt-1" id="kpi-sem-resposta">—</p>
                    <p class="text-xs text-gray-400 mt-1">aguardando 1ª resposta</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">1ª Resp. (mediana)</p>
                    <p class="text-2xl font-bold text-blue-600 mt-1" id="kpi-mediana-resp">—</p>
                    <p class="text-xs text-gray-400 mt-1">p90: <span id="kpi-p90-resp">—</span></p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 cursor-pointer hover:shadow-md transition" onclick="NexoGerencial.drill('sla_estourado')">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">SLA (<span id="kpi-sla-limite">10</span>min)</p>
                    <p class="text-2xl font-bold mt-1" id="kpi-taxa-sla">—</p>
                    <p class="text-xs text-gray-400 mt-1"><span id="kpi-sla-ok">0</span> ok · <span id="kpi-sla-nok" class="text-red-500">0</span> estourados</p>
                </div>
            </div>

            {{-- MENSAGENS BREAKDOWN --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Msgs Total</p>
                    <p class="text-xl font-bold text-gray-900 mt-1" id="kpi-total-msgs">—</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Msgs Cliente</p>
                    <p class="text-xl font-bold text-blue-600 mt-1" id="kpi-msgs-cliente">—</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Msgs Humano</p>
                    <p class="text-xl font-bold text-emerald-600 mt-1" id="kpi-msgs-humanas">—</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Msgs Bot</p>
                    <p class="text-xl font-bold text-purple-600 mt-1" id="kpi-msgs-bot">—</p>
                </div>
            </div>

            {{-- GRÁFICOS --}}
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Volume por Dia</h3>
                    <canvas id="chart-volume-dia" height="200"></canvas>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">SLA por Dia</h3>
                    <canvas id="chart-sla-dia" height="200"></canvas>
                </div>
            </div>
            <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Volume por Hora</h3>
                    <canvas id="chart-volume-hora" height="200"></canvas>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-5">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Mensagens por Dia</h3>
                    <canvas id="chart-msgs-dia" height="200"></canvas>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════ --}}
        {{-- KPI CARDS — TICKETS --}}
        {{-- ══════════════════════════════════════════════════ --}}
        <div id="aba-tickets" class="hidden">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-xl shadow-sm border p-4 cursor-pointer hover:shadow-md transition" onclick="NexoGerencial.drill('tickets_abertos')">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Tickets Abertos</p>
                    <p class="text-2xl font-bold text-gray-900 mt-1" id="kpi-tk-abertos">—</p>
                    <p class="text-xs text-gray-400 mt-1">no período</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4 cursor-pointer hover:shadow-md transition" onclick="NexoGerencial.drill('tickets_resolvidos')">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Resolvidos</p>
                    <p class="text-2xl font-bold text-emerald-600 mt-1" id="kpi-tk-resolvidos">—</p>
                    <p class="text-xs text-gray-400 mt-1">no período</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Pendentes</p>
                    <p class="text-2xl font-bold text-amber-600 mt-1" id="kpi-tk-pendentes">—</p>
                    <p class="text-xs text-gray-400 mt-1">acumulado aberto</p>
                </div>
                <div class="bg-white rounded-xl shadow-sm border p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wide">Resolução (mediana)</p>
                    <p class="text-xl font-bold text-blue-600 mt-1" id="kpi-tk-mediana">—</p>
                    <p class="text-xs text-gray-400 mt-1">p90: <span id="kpi-tk-p90">—</span></p>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════ --}}
        {{-- BLOCO ESCALA --}}
        {{-- ══════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-8">
            <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-700">Escala de Atendimento</h2>
                @if(in_array(auth()->user()->role, ['admin', 'coordenador', 'socio']))
                <a href="{{ route('nexo.gerencial.escala') }}" class="text-xs text-[#385776] hover:underline font-medium">Gerenciar Escala</a>
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm" id="tabela-escala">
                    <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Data</th>
                            <th class="px-4 py-3 text-left">Responsável</th>
                            <th class="px-4 py-3 text-center">Horário</th>
                            <th class="px-4 py-3 text-center">Entradas</th>
                            <th class="px-4 py-3 text-center">SLA OK</th>
                            <th class="px-4 py-3 text-center">SLA Estourado</th>
                            <th class="px-4 py-3 text-center">Mediana Resp.</th>
                            <th class="px-4 py-3 text-center">Herdadas 09h</th>
                            <th class="px-4 py-3 text-center">Deixadas 18h</th>
                            <th class="px-4 py-3 text-center">Atend. Outro</th>
                            <th class="px-4 py-3 text-left">Obs.</th>
                        </tr>
                    </thead>
                    <tbody id="escala-tbody" class="divide-y"></tbody>
                </table>
                <div id="escala-vazio" class="hidden text-center py-8 text-gray-400 text-sm">
                    Nenhuma escala cadastrada para o período selecionado.
                </div>
            </div>
        </div>

    </div>

    {{-- ══════════════════════════════════════════════════ --}}
    {{-- MODAL DRILL-DOWN --}}
    {{-- ══════════════════════════════════════════════════ --}}
    <div id="modal-drill" class="hidden fixed inset-0 z-50 bg-black/50 flex items-start justify-center pt-20 px-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl max-h-[70vh] flex flex-col">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <h3 class="font-semibold text-gray-800" id="drill-title">Detalhes</h3>
                <button onclick="NexoGerencial.fecharDrill()" class="text-gray-400 hover:text-gray-600 text-xl">&times;</button>
            </div>
            <div class="overflow-auto flex-1 px-5 py-4" id="drill-body">
                <p class="text-gray-400 text-center py-8">Carregando...</p>
            </div>
        </div>
    </div>

</div>

{{-- Chart.js CDN (já carregado no layout, mas seguro incluir) --}}
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>

<script>
const NexoGerencial = {
    periodo: '7d',
    dados: null,
    charts: {},

    init() {
        this.setPeriodo('7d');
    },

    setPeriodo(p) {
        this.periodo = p;
        document.querySelectorAll('.periodo-btn').forEach(b => {
            b.classList.toggle('bg-[#385776]', b.dataset.p === p);
            b.classList.toggle('text-white', b.dataset.p === p);
        });
        document.getElementById('custom-dates').classList.toggle('hidden', p !== 'custom');
        if (p !== 'custom') this.carregarDados();
    },

    setAba(aba) {
        document.getElementById('aba-whatsapp').classList.toggle('hidden', aba !== 'whatsapp');
        document.getElementById('aba-tickets').classList.toggle('hidden', aba !== 'tickets');
        document.querySelectorAll('.aba-btn').forEach(b => {
            const ativo = b.dataset.aba === aba;
            b.classList.toggle('border-[#385776]', ativo);
            b.classList.toggle('text-[#385776]', ativo);
            b.classList.toggle('border-transparent', !ativo);
            b.classList.toggle('text-gray-500', !ativo);
        });
    },

    async carregarDados() {
        document.getElementById('loading').classList.remove('hidden');
        document.getElementById('conteudo').classList.add('hidden');

        const params = new URLSearchParams({
            periodo: this.periodo,
            somente_janela: document.getElementById('toggle-janela').checked ? '1' : '0',
        });
        if (this.periodo === 'custom') {
            params.set('de', document.getElementById('date-de').value);
            params.set('ate', document.getElementById('date-ate').value);
        }

        try {
            const resp = await fetch(`{{ route('nexo.gerencial.data') }}?${params}`);
            this.dados = await resp.json();
            this.renderizar();
        } catch (e) {
            console.error('Erro ao carregar dados:', e);
        } finally {
            document.getElementById('loading').classList.add('hidden');
            document.getElementById('conteudo').classList.remove('hidden');
        }
    },

    renderizar() {
        const k = this.dados.kpis;
        const g = this.dados.graficos;

        // Período label
        document.getElementById('periodo-label').textContent = this.dados.periodo?.label || '';

        // KPIs WhatsApp
        document.getElementById('kpi-entradas').textContent = k.total_entradas;
        document.getElementById('kpi-resolvidos').textContent = k.resolvidos;
        document.getElementById('kpi-backlog').textContent = k.backlog;
        document.getElementById('kpi-sem-resposta').textContent = k.sem_resposta;
        document.getElementById('kpi-mediana-resp').textContent = k.mediana_primeira_resp || '—';
        document.getElementById('kpi-p90-resp').textContent = k.p90_primeira_resp || '—';
        document.getElementById('kpi-taxa-sla').textContent = k.taxa_sla + '%';
        document.getElementById('kpi-taxa-sla').className = 'text-2xl font-bold mt-1 ' + (k.taxa_sla >= 80 ? 'text-emerald-600' : k.taxa_sla >= 50 ? 'text-amber-600' : 'text-red-600');
        document.getElementById('kpi-sla-ok').textContent = k.sla_cumprido;
        document.getElementById('kpi-sla-nok').textContent = k.sla_estourado;
        document.getElementById('kpi-sla-limite').textContent = k.sla_limite_min;
        document.getElementById('kpi-total-msgs').textContent = k.total_mensagens;
        document.getElementById('kpi-msgs-cliente').textContent = k.msgs_cliente;
        document.getElementById('kpi-msgs-humanas').textContent = k.msgs_humanas;
        document.getElementById('kpi-msgs-bot').textContent = k.msgs_bot;

        // KPIs Tickets
        document.getElementById('kpi-tk-abertos').textContent = k.tickets_abertos;
        document.getElementById('kpi-tk-resolvidos').textContent = k.tickets_resolvidos;
        document.getElementById('kpi-tk-pendentes').textContent = k.tickets_pendentes;
        document.getElementById('kpi-tk-mediana').textContent = k.mediana_resolucao || '—';
        document.getElementById('kpi-tk-p90').textContent = k.p90_resolucao || '—';

        // Gráficos
        this.renderGraficos(g);

        // Escala
        this.renderEscala(this.dados.escala);

        // Aba padrão
        this.setAba('whatsapp');
    },

    renderGraficos(g) {
        // Destruir anteriores
        Object.values(this.charts).forEach(c => c.destroy());
        this.charts = {};

        // Volume por dia
        const diasLabels = Object.keys(g.volume_por_dia);
        const diasEntradas = diasLabels.map(d => g.volume_por_dia[d].entradas);

        this.charts.volumeDia = new Chart(document.getElementById('chart-volume-dia'), {
            type: 'bar',
            data: {
                labels: diasLabels,
                datasets: [{
                    label: 'Conversas Iniciadas',
                    data: diasEntradas,
                    backgroundColor: '#385776',
                    borderRadius: 4,
                }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        // SLA por dia
        const slaLabels = Object.keys(g.sla_por_dia);
        this.charts.slaDia = new Chart(document.getElementById('chart-sla-dia'), {
            type: 'bar',
            data: {
                labels: slaLabels,
                datasets: [
                    { label: 'SLA OK', data: slaLabels.map(d => g.sla_por_dia[d].cumprido), backgroundColor: '#10b981', borderRadius: 4 },
                    { label: 'Estourado', data: slaLabels.map(d => g.sla_por_dia[d].estourado), backgroundColor: '#ef4444', borderRadius: 4 },
                    { label: 'Sem Resp.', data: slaLabels.map(d => g.sla_por_dia[d].sem_resp), backgroundColor: '#9ca3af', borderRadius: 4 },
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        // Volume por hora
        const horas = Object.keys(g.volume_por_hora).map(h => h + 'h');
        this.charts.volumeHora = new Chart(document.getElementById('chart-volume-hora'), {
            type: 'bar',
            data: {
                labels: horas,
                datasets: [{ label: 'Conversas', data: Object.values(g.volume_por_hora), backgroundColor: '#6366f1', borderRadius: 3 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });

        // Mensagens por dia
        this.charts.msgsDia = new Chart(document.getElementById('chart-msgs-dia'), {
            type: 'line',
            data: {
                labels: diasLabels,
                datasets: [
                    { label: 'Cliente', data: diasLabels.map(d => g.volume_por_dia[d].msgs_cliente), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3 },
                    { label: 'Humano', data: diasLabels.map(d => g.volume_por_dia[d].msgs_humanas), borderColor: '#10b981', tension: 0.3 },
                    { label: 'Bot', data: diasLabels.map(d => g.volume_por_dia[d].msgs_bot), borderColor: '#8b5cf6', tension: 0.3, borderDash: [5,5] },
                ]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { boxWidth: 12 } } }, scales: { y: { beginAtZero: true } } }
        });
    },

    renderEscala(escala) {
        const tbody = document.getElementById('escala-tbody');
        const vazio = document.getElementById('escala-vazio');

        if (!escala || escala.length === 0) {
            tbody.innerHTML = '';
            vazio.classList.remove('hidden');
            return;
        }

        vazio.classList.add('hidden');
        tbody.innerHTML = escala.map(e => `
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="NexoGerencial.drill('entradas', '${e.data_iso}')">
                <td class="px-4 py-3 font-medium">${e.data}</td>
                <td class="px-4 py-3">${e.responsavel}</td>
                <td class="px-4 py-3 text-center text-gray-500">${e.horario}</td>
                <td class="px-4 py-3 text-center font-semibold">${e.entradas}</td>
                <td class="px-4 py-3 text-center text-emerald-600 font-medium">${e.sla_cumprido}</td>
                <td class="px-4 py-3 text-center text-red-500 font-medium">${e.sla_estourado}</td>
                <td class="px-4 py-3 text-center">${e.mediana_resp || '—'}</td>
                <td class="px-4 py-3 text-center text-amber-600">${e.herdadas_inicio}</td>
                <td class="px-4 py-3 text-center text-amber-600">${e.deixadas_fim}</td>
                <td class="px-4 py-3 text-center ${e.atendido_outro > 0 ? 'text-orange-500 font-medium' : 'text-gray-400'}">${e.atendido_outro}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">${e.observacao || ''}</td>
            </tr>
        `).join('');
    },

    async drill(tipo, data = null) {
        document.getElementById('modal-drill').classList.remove('hidden');
        document.getElementById('drill-title').textContent = 'Carregando...';
        document.getElementById('drill-body').innerHTML = '<p class="text-gray-400 text-center py-8">Carregando...</p>';

        const params = new URLSearchParams({
            periodo: this.periodo,
            somente_janela: document.getElementById('toggle-janela').checked ? '1' : '0',
        });
        if (this.periodo === 'custom') {
            params.set('de', document.getElementById('date-de').value);
            params.set('ate', document.getElementById('date-ate').value);
        }
        if (data) params.set('data', data);

        try {
            const resp = await fetch(`/nexo/gerencial/drill/${tipo}?${params}`);
            const result = await resp.json();
            this.renderDrill(result);
        } catch (e) {
            document.getElementById('drill-body').innerHTML = '<p class="text-red-500 text-center py-8">Erro ao carregar dados.</p>';
        }
    },

    renderDrill(result) {
        const titulos = {
            entradas: 'Conversas — Entradas', resolvidos: 'Conversas — Resolvidos',
            backlog: 'Conversas — Backlog Aberto', sla_estourado: 'SLA Estourado',
            sla_cumprido: 'SLA Cumprido', sem_resposta: 'Sem Resposta Humana',
            tickets_abertos: 'Tickets Abertos', tickets_resolvidos: 'Tickets Resolvidos',
        };
        document.getElementById('drill-title').textContent = titulos[result.tipo] || result.tipo;

        if (!result.items || result.items.length === 0) {
            document.getElementById('drill-body').innerHTML = '<p class="text-gray-400 text-center py-8">Nenhum registro encontrado.</p>';
            return;
        }

        // Detectar se é ticket ou conversa
        const isTicket = result.tipo.startsWith('tickets');
        let html = '<table class="w-full text-sm"><thead class="text-xs text-gray-500 uppercase bg-gray-50"><tr>';

        if (isTicket) {
            html += '<th class="px-3 py-2 text-left">Protocolo</th><th class="px-3 py-2 text-left">Nome</th><th class="px-3 py-2 text-left">Assunto</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Criado</th><th class="px-3 py-2"></th>';
        } else {
            html += '<th class="px-3 py-2 text-left">Nome</th><th class="px-3 py-2 text-left">Telefone</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Responsável</th><th class="px-3 py-2">Criado</th><th class="px-3 py-2">Tempo Resp.</th><th class="px-3 py-2"></th>';
        }
        html += '</tr></thead><tbody class="divide-y">';

        result.items.forEach(item => {
            if (isTicket) {
                html += `<tr class="hover:bg-gray-50">
                    <td class="px-3 py-2 font-mono text-xs">${item.protocolo || '-'}</td>
                    <td class="px-3 py-2">${item.nome || '-'}</td>
                    <td class="px-3 py-2 text-gray-600">${item.assunto || '-'}</td>
                    <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs ${item.status === 'resolvido' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}">${item.status}</span></td>
                    <td class="px-3 py-2 text-center text-xs text-gray-500">${item.criado_em}</td>
                    <td class="px-3 py-2 text-center"><a href="${item.link}" class="text-[#385776] hover:underline text-xs">Ver</a></td>
                </tr>`;
            } else {
                html += `<tr class="hover:bg-gray-50">
                    <td class="px-3 py-2">${item.nome}</td>
                    <td class="px-3 py-2 font-mono text-xs text-gray-500">${item.telefone}</td>
                    <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs ${item.status === 'open' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600'}">${item.status}</span></td>
                    <td class="px-3 py-2 text-center text-xs">${item.responsavel || '<span class=text-gray-300>—</span>'}</td>
                    <td class="px-3 py-2 text-center text-xs text-gray-500">${item.criado_em}</td>
                    <td class="px-3 py-2 text-center text-xs font-medium ${item.tempo_resposta ? '' : 'text-red-400'}">${item.tempo_resposta || 'Sem resp.'}</td>
                    <td class="px-3 py-2 text-center"><a href="${item.link}" class="text-[#385776] hover:underline text-xs">Ver</a></td>
                </tr>`;
            }
        });

        html += '</tbody></table>';
        document.getElementById('drill-body').innerHTML = html;
    },

    fecharDrill() {
        document.getElementById('modal-drill').classList.add('hidden');
    },
};

document.addEventListener('DOMContentLoaded', () => NexoGerencial.init());
document.addEventListener('keydown', e => { if (e.key === 'Escape') NexoGerencial.fecharDrill(); });
</script>
@endsection
