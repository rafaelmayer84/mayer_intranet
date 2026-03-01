@extends('layouts.app')

@section('title', 'BSC Insights — IA')

@section('content')
<style>
    .insight-card {
        border-radius: 16px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        position: relative;
    }
    .insight-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px rgba(0,0,0,0.12);
    }
    .insight-card .card-accent {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }
    .card-critico .card-accent { background: linear-gradient(90deg, #ef4444, #f97316); }
    .card-atencao .card-accent { background: linear-gradient(90deg, #f59e0b, #eab308); }
    .card-info .card-accent { background: linear-gradient(90deg, #3b82f6, #6366f1); }

    .card-critico { background: linear-gradient(135deg, #fff5f5 0%, #fef2f2 100%); border: 1px solid #fecaca; }
    .card-atencao { background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); border: 1px solid #fde68a; }
    .card-info { background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%); border: 1px solid #bfdbfe; }

    .card-icon {
        width: 48px; height: 48px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
    }
    .card-critico .card-icon { background: linear-gradient(135deg, #fee2e2, #fecaca); }
    .card-atencao .card-icon { background: linear-gradient(135deg, #fef3c7, #fde68a); }
    .card-info .card-icon { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }

    .sev-pill {
        font-size: 10px; font-weight: 700; letter-spacing: 0.5px;
        padding: 2px 8px; border-radius: 6px; text-transform: uppercase;
    }
    .sev-critico { background: #fecaca; color: #991b1b; }
    .sev-atencao { background: #fde68a; color: #92400e; }
    .sev-info { background: #bfdbfe; color: #1e40af; }

    .metric-value {
        font-size: 1.6rem; font-weight: 800; line-height: 1.1;
        background: linear-gradient(135deg, #1e293b, #334155);
        -webkit-background-clip: text; -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .card-critico .metric-value {
        background: linear-gradient(135deg, #991b1b, #dc2626);
        -webkit-background-clip: text; background-clip: text;
    }

    .conclusion-text {
        font-size: 0.9rem; line-height: 1.5; color: #374151;
        font-weight: 500;
    }

    .action-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 14px; border-radius: 10px;
        font-size: 0.78rem; font-weight: 600;
        cursor: pointer; transition: all 0.2s;
    }
    .action-badge:hover { filter: brightness(0.95); transform: scale(1.02); }
    .card-critico .action-badge { background: #fee2e2; color: #991b1b; }
    .card-atencao .action-badge { background: #fef3c7; color: #92400e; }
    .card-info .action-badge { background: #dbeafe; color: #1e40af; }

    .detail-panel {
        max-height: 0; overflow: hidden;
        transition: max-height 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .detail-panel.open { max-height: 500px; }

    .confidence-ring {
        width: 36px; height: 36px; position: relative;
    }
    .confidence-ring svg { transform: rotate(-90deg); }
    .confidence-ring .ring-text {
        position: absolute; inset: 0;
        display: flex; align-items: center; justify-content: center;
        font-size: 10px; font-weight: 700; color: #64748b;
    }

    .summary-card {
        border-radius: 16px; padding: 20px;
        position: relative; overflow: hidden;
    }
    .summary-card::before {
        content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px;
    }
    .summary-riscos { background: linear-gradient(135deg, #fef2f2, #fff5f5); border: 1px solid #fecaca; }
    .summary-riscos::before { background: linear-gradient(90deg, #ef4444, #f97316); }
    .summary-oportunidades { background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border: 1px solid #a7f3d0; }
    .summary-oportunidades::before { background: linear-gradient(90deg, #10b981, #34d399); }
    .summary-apostas { background: linear-gradient(135deg, #eff6ff, #e0e7ff); border: 1px solid #bfdbfe; }
    .summary-apostas::before { background: linear-gradient(90deg, #3b82f6, #8b5cf6); }

    .universo-tab.active { border-color: #385776; color: #385776; font-weight: 600; }

    @keyframes fadeSlideUp {
        from { opacity: 0; transform: translateY(20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .animate-card { animation: fadeSlideUp 0.5s cubic-bezier(0.4, 0, 0.2, 1) both; }

    .impact-bar { height: 3px; border-radius: 2px; background: #e2e8f0; overflow: hidden; }
    .impact-bar-fill { height: 100%; border-radius: 2px; transition: width 0.6s ease; }
</style>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6 gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-2">
                <span class="text-3xl">🧠</span> BSC Insights
                @if($isDemo)
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200">DEMO</span>
                @endif
            </h1>
            <p class="text-sm text-gray-500 mt-1">Conclusões e recomendações automáticas dos seus indicadores</p>
        </div>
        <div class="flex items-center gap-4">
            <div class="text-right text-xs text-gray-400 hidden sm:block">
                <div class="flex items-center gap-2 justify-end">
                    <span>${{ number_format($budgetInfo['spent_usd'], 2) }} / ${{ number_format($budgetInfo['limit_usd'], 2) }}</span>
                </div>
                <div class="w-28 bg-gray-200 rounded-full h-1.5 mt-1">
                    <div class="h-1.5 rounded-full transition-all {{ $budgetInfo['usage_pct'] > 80 ? 'bg-red-500' : ($budgetInfo['usage_pct'] > 50 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                         style="width: {{ min(100, $budgetInfo['usage_pct']) }}%"></div>
                </div>
            </div>
            @if(in_array(Auth::user()->role, ['admin', 'socio', 'coordenador']))
            <button id="btn-generate" onclick="generateInsights()"
                    class="inline-flex items-center px-5 py-2.5 text-sm font-semibold text-white rounded-xl shadow-lg transition-all hover:shadow-xl hover:scale-[1.02] active:scale-[0.98]"
                    style="background: linear-gradient(135deg, #385776, #1B334A);"
                    {{ !$budgetInfo['can_run'] && !$isDemo ? 'disabled' : '' }}>
                <svg id="icon-generate" class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                <svg id="icon-loading" class="w-4 h-4 mr-2 animate-spin hidden" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                <span id="btn-text">Gerar Insights</span>
            </button>
            @endif
        </div>
    </div>

    {{-- Last run info --}}
    @if($lastRun)
    <div class="mb-6 px-4 py-2 bg-gray-50/80 border border-gray-100 rounded-xl text-xs text-gray-400 flex items-center justify-between">
        <span>🕐 {{ $snapshotDate->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $lastRun->model }} · {{ $lastRun->input_tokens + $lastRun->output_tokens }} tokens · ${{ number_format($lastRun->estimated_cost_usd, 4) }}</span>
        <span>Run #{{ $lastRun->id }}</span>
    </div>
    @endif

    {{-- Summary --}}
    <div id="summary-section" class="hidden mb-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <div class="summary-card summary-riscos">
                <div class="flex items-center gap-2 mb-3"><span class="text-lg">🔴</span><h3 class="text-sm font-bold text-red-800">Principais Riscos</h3></div>
                <ul id="summary-riscos" class="text-sm text-red-700 space-y-2"></ul>
            </div>
            <div class="summary-card summary-oportunidades">
                <div class="flex items-center gap-2 mb-3"><span class="text-lg">🟢</span><h3 class="text-sm font-bold text-emerald-800">Oportunidades</h3></div>
                <ul id="summary-oportunidades" class="text-sm text-emerald-700 space-y-2"></ul>
            </div>
            <div class="summary-card summary-apostas">
                <div class="flex items-center gap-2 mb-3"><span class="text-lg">🚀</span><h3 class="text-sm font-bold text-blue-800">Apostas Recomendadas</h3></div>
                <ul id="summary-apostas" class="text-sm text-blue-700 space-y-2"></ul>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="mb-5 border-b border-gray-200">
        <nav class="flex space-x-1 overflow-x-auto pb-px" id="universo-tabs">
            <button onclick="showUniverso('all')" class="universo-tab active px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition-colors" data-tab="all">Todos</button>
            <button onclick="showUniverso('FINANCEIRO')" class="universo-tab px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap transition-colors" data-tab="FINANCEIRO">💰 Financeiro <span class="tab-count text-xs" data-universo="FINANCEIRO"></span></button>
            <button onclick="showUniverso('CLIENTES_MERCADO')" class="universo-tab px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap transition-colors" data-tab="CLIENTES_MERCADO">👥 Clientes <span class="tab-count text-xs" data-universo="CLIENTES_MERCADO"></span></button>
            <button onclick="showUniverso('PROCESSOS_INTERNOS')" class="universo-tab px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap transition-colors" data-tab="PROCESSOS_INTERNOS">⚙️ Processos <span class="tab-count text-xs" data-universo="PROCESSOS_INTERNOS"></span></button>
            <button onclick="showUniverso('TIMES_EVOLUCAO')" class="universo-tab px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 whitespace-nowrap transition-colors" data-tab="TIMES_EVOLUCAO">👤 Times <span class="tab-count text-xs" data-universo="TIMES_EVOLUCAO"></span></button>
        </nav>
    </div>

    {{-- Cards --}}
    <div id="cards-container">
        @if($cards->isEmpty())
            <div class="text-center py-20">
                <div class="w-20 h-20 mx-auto mb-5 rounded-2xl bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center text-3xl">🧠</div>
                <p class="text-lg font-semibold text-gray-600">Nenhum insight gerado ainda</p>
                <p class="text-sm text-gray-400 mt-2">Clique em "Gerar Insights" para a IA analisar seus indicadores</p>
            </div>
        @else
            @foreach(['FINANCEIRO', 'CLIENTES_MERCADO', 'PROCESSOS_INTERNOS', 'TIMES_EVOLUCAO'] as $uIdx => $universo)
                @if(isset($cards[$universo]))
                <div class="universo-group mb-8" data-universo="{{ $universo }}">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-xl">
                            @switch($universo)
                                @case('FINANCEIRO') 💰 @break
                                @case('CLIENTES_MERCADO') 👥 @break
                                @case('PROCESSOS_INTERNOS') ⚙️ @break
                                @case('TIMES_EVOLUCAO') 👤 @break
                            @endswitch
                        </span>
                        <h2 class="text-lg font-bold text-gray-700">
                            @switch($universo)
                                @case('FINANCEIRO') Financeiro @break
                                @case('CLIENTES_MERCADO') Clientes & Mercado @break
                                @case('PROCESSOS_INTERNOS') Processos Internos @break
                                @case('TIMES_EVOLUCAO') Times & Evolução @break
                            @endswitch
                        </h2>
                        <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ $cards[$universo]->count() }}</span>
                    </div>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                        @foreach($cards[$universo] as $cIdx => $card)
                            @include('bsc-insights._card', ['card' => $card, 'delay' => ($uIdx * 3 + $cIdx) * 80, 'universo' => $universo])
                        @endforeach
                    </div>
                </div>
                @endif
            @endforeach
        @endif
    </div>
</div>

{{-- Toast --}}
<div id="toast" class="fixed bottom-4 right-4 z-50 hidden">
    <div id="toast-inner" class="px-5 py-3 rounded-xl shadow-2xl text-sm font-semibold text-white backdrop-blur"></div>
</div>
@endsection

@push('scripts')
<script>
const ICONS = {FINANCEIRO:['💸','📊','💳','📈','🏦','💰'],CLIENTES_MERCADO:['🎯','📋','🤝','👥','📞','🔍'],PROCESSOS_INTERNOS:['⚡','📁','⏱️','⚙️','📌','🔧'],TIMES_EVOLUCAO:['🏆','⏰','💬','📱','🎓','👤']};

function getIcon(universo, idx) { const arr = ICONS[universo] || ICONS.FINANCEIRO; return arr[idx % arr.length]; }

function showUniverso(tab) {
    document.querySelectorAll('.universo-tab').forEach(t => { t.classList.remove('active'); t.style.borderColor = 'transparent'; t.style.color = ''; });
    const a = document.querySelector(`.universo-tab[data-tab="${tab}"]`);
    if (a) { a.classList.add('active'); a.style.borderColor = '#385776'; a.style.color = '#385776'; }
    document.querySelectorAll('.universo-group').forEach(g => { g.style.display = (tab === 'all' || g.dataset.universo === tab) ? '' : 'none'; });
}

function updateTabCounts() {
    document.querySelectorAll('.tab-count').forEach(s => {
        const c = document.querySelectorAll(`.universo-group[data-universo="${s.dataset.universo}"] .insight-card`).length;
        s.textContent = c > 0 ? `(${c})` : '';
    });
}

function toggleDetail(id) {
    const el = document.getElementById(id);
    if (el) el.classList.toggle('open');
}

function generateInsights() {
    const btn = document.getElementById('btn-generate');
    btn.disabled = true;
    document.getElementById('icon-generate').classList.add('hidden');
    document.getElementById('icon-loading').classList.remove('hidden');
    document.getElementById('btn-text').textContent = 'Analisando...';

    doGenerate(false);
}

function doGenerate(force) {
    fetch('{{ route("bsc-insights.generate") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: JSON.stringify({ force: force })
    })
    .then(r => {
        if (r.status === 429) return r.json().then(d => {
            if (d.can_force && confirm(d.message + '\n\nForçar nova geração?')) return doGenerate(true);
            throw new Error(d.message);
        });
        if (!r.ok) return r.json().then(d => { throw new Error(d.message || 'Erro'); });
        return r.json();
    })
    .then(data => {
        if (!data || !data.success) return;
        if (data.async) {
            showToast('🔄 Analise em andamento... aguarde.', 'success');
            pollStatus(data.run_id);
            return;
        }
        showToast(`🧠 ${data.meta?.total_cards ?? '?'} insights gerados`, 'success');
        if (data.summary) renderSummary(data.summary);
        if (data.cards) renderCards(data.cards);
        updateTabCounts();
    })
    .catch(err => showToast(err.message || 'Erro inesperado', 'error'))
    .finally(resetBtn);
}

function pollStatus(runId) {
    const interval = setInterval(() => {
        fetch(`/bsc-insights/status/${runId}`, { headers: { 'Accept': 'application/json' } })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                clearInterval(interval);
                showToast(`🧠 ${data.meta?.total_cards ?? '?'} insights gerados`, 'success');
                if (data.summary) renderSummary(data.summary);
                if (data.cards) renderCards(data.cards);
                updateTabCounts();
                resetBtn();
            } else if (data.status === 'failed') {
                clearInterval(interval);
                showToast(data.error || 'Falha na geracao', 'error');
                resetBtn();
            } else {
                document.getElementById('btn-text').textContent = data.message || 'Processando...';
            }
        })
        .catch(() => { clearInterval(interval); resetBtn(); });
    }, 3000);
}

function resetBtn() {
    const btn = document.getElementById('btn-generate');
    if (btn) { btn.disabled = false; document.getElementById('icon-generate').classList.remove('hidden'); document.getElementById('icon-loading').classList.add('hidden'); document.getElementById('btn-text').textContent = 'Gerar Insights'; }
}

function renderSummary(s) {
    document.getElementById('summary-section').classList.remove('hidden');
    document.getElementById('summary-riscos').innerHTML = (s.principais_riscos||[]).map(r=>`<li class="flex items-start gap-2"><span class="mt-0.5">⚠️</span><span>${esc(r)}</span></li>`).join('');
    document.getElementById('summary-oportunidades').innerHTML = (s.principais_oportunidades||[]).map(r=>`<li class="flex items-start gap-2"><span class="mt-0.5">✅</span><span>${esc(r)}</span></li>`).join('');
    document.getElementById('summary-apostas').innerHTML = (s.apostas_recomendadas||[]).map(a=>`<li class="flex items-start gap-2"><span class="mt-0.5">🎯</span><div><span class="font-semibold">${esc(a.descricao)}</span><br><span class="text-xs opacity-80">${esc(a.impacto_esperado)} · ${esc(a.esforco)}</span></div></li>`).join('');
}

function renderCards(grouped) {
    const container = document.getElementById('cards-container');
    const universos = ['FINANCEIRO','CLIENTES_MERCADO','PROCESSOS_INTERNOS','TIMES_EVOLUCAO'];
    const labels = {FINANCEIRO:'Financeiro',CLIENTES_MERCADO:'Clientes & Mercado',PROCESSOS_INTERNOS:'Processos Internos',TIMES_EVOLUCAO:'Times & Evolução'};
    const emojis = {FINANCEIRO:'💰',CLIENTES_MERCADO:'👥',PROCESSOS_INTERNOS:'⚙️',TIMES_EVOLUCAO:'👤'};
    let html = '', cardIdx = 0;

    universos.forEach(u => {
        const cards = grouped[u] || [];
        if (!cards.length) return;
        html += `<div class="universo-group mb-8" data-universo="${u}">`;
        html += `<div class="flex items-center gap-2 mb-4"><span class="text-xl">${emojis[u]}</span><h2 class="text-lg font-bold text-gray-700">${labels[u]}</h2><span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">${cards.length}</span></div>`;
        html += '<div class="grid grid-cols-1 lg:grid-cols-2 gap-5">';
        cards.forEach((c, ci) => { html += buildCard(c, u, ci, cardIdx++); });
        html += '</div></div>';
    });
    container.innerHTML = html;
}

function buildCard(c, universo, ci, gIdx) {
    const sev = (c.severidade||'INFO').toUpperCase();
    const sevClass = sev === 'CRITICO' ? 'card-critico' : sev === 'ATENCAO' ? 'card-atencao' : 'card-info';
    const sevPill = sev === 'CRITICO' ? 'sev-critico' : sev === 'ATENCAO' ? 'sev-atencao' : 'sev-info';
    const icon = getIcon(universo, ci);
    const conf = c.confidence || 50;
    const impact = c.impact_score || 0;
    const delay = gIdx * 80;
    const detId = 'det-' + gIdx;

    // Extrair valor numérico principal da primeira evidência
    let mainValue = '';
    let mainLabel = '';
    if (c.evidences && c.evidences.length > 0) {
        mainValue = c.evidences[0].value || '';
        mainLabel = c.evidences[0].metric || '';
    }

    const circumference = 2 * Math.PI * 14;
    const offset = circumference - (conf / 100) * circumference;
    const ringColor = conf >= 70 ? '#10b981' : conf >= 40 ? '#f59e0b' : '#ef4444';

    let evidHtml = '';
    if (c.evidences && c.evidences.length > 0) {
        evidHtml = c.evidences.slice(0, 3).map(e =>
            `<div class="flex items-center justify-between text-xs py-1"><span class="text-gray-500">${esc(e.metric)}</span><span class="font-bold text-gray-700">${esc(e.value)}${e.variation ? ' <span class="text-gray-400">('+esc(e.variation)+')</span>' : ''}</span></div>`
        ).join('');
    }

    return `
    <div class="insight-card ${sevClass} animate-card" style="animation-delay:${delay}ms" onclick="toggleDetail('${detId}')">
        <div class="card-accent"></div>
        <div class="p-5">
            <div class="flex items-start gap-4">
                <div class="card-icon">${icon}</div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="sev-pill ${sevPill}">${sev === 'CRITICO' ? '🔴 Crítico' : sev === 'ATENCAO' ? '🟡 Atenção' : '🔵 Info'}</span>
                        <div class="confidence-ring ml-auto" title="Confiança ${conf}%">
                            <svg width="36" height="36"><circle cx="18" cy="18" r="14" fill="none" stroke="#e2e8f0" stroke-width="3"/><circle cx="18" cy="18" r="14" fill="none" stroke="${ringColor}" stroke-width="3" stroke-dasharray="${circumference}" stroke-dashoffset="${offset}" stroke-linecap="round"/></svg>
                            <span class="ring-text">${conf}</span>
                        </div>
                    </div>
                    <h3 class="font-bold text-gray-800 text-base leading-tight mb-2">${esc(c.title)}</h3>
                    ${mainValue ? `<div class="metric-value mb-1">${esc(mainValue)}</div><div class="text-xs text-gray-400 mb-2">${esc(mainLabel)}</div>` : ''}
                    <p class="conclusion-text">${esc(c.what_changed)}</p>
                </div>
            </div>

            ${evidHtml ? `<div class="mt-3 mx-1 p-2.5 rounded-xl bg-white/60 border border-gray-100">${evidHtml}</div>` : ''}

            <div class="flex items-center justify-between mt-4">
                <div class="action-badge" title="Clique para ver detalhes">
                    💡 ${esc((c.recommendation||'').substring(0, 60))}${(c.recommendation||'').length > 60 ? '...' : ''}
                </div>
                <div class="impact-bar w-16 ml-3" title="Impacto: ${impact}/10">
                    <div class="impact-bar-fill" style="width:${impact*10}%;background:${impact >= 7 ? '#ef4444' : impact >= 4 ? '#f59e0b' : '#3b82f6'}"></div>
                </div>
            </div>

            <div id="${detId}" class="detail-panel">
                <div class="mt-4 pt-4 border-t border-gray-200/60 space-y-3">
                    <div class="flex items-start gap-2">
                        <span class="text-sm mt-0.5">🎯</span>
                        <div><div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Por que importa</div><p class="text-sm text-gray-600">${esc(c.why_it_matters)}</p></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-sm mt-0.5">✅</span>
                        <div><div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Recomendação</div><p class="text-sm font-semibold text-gray-700">${esc(c.recommendation)}</p></div>
                    </div>
                    <div class="flex items-start gap-2">
                        <span class="text-sm mt-0.5">📋</span>
                        <div><div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Próximo passo</div><p class="text-sm text-gray-700">${esc(c.next_step)}</p></div>
                    </div>
                    ${c.questions && c.questions.length ? '<div class="flex items-start gap-2"><span class="text-sm mt-0.5">❓</span><div><div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Perguntas a investigar</div>' + c.questions.map(q=>`<p class="text-sm text-gray-500 italic">• ${esc(q)}</p>`).join('') + '</div></div>' : ''}
                </div>
            </div>
        </div>
    </div>`;
}

function esc(s) { if (!s) return ''; const d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
function showToast(msg, type) {
    const t = document.getElementById('toast'), i = document.getElementById('toast-inner');
    i.textContent = msg;
    i.style.background = type === 'success' ? 'linear-gradient(135deg,#059669,#10b981)' : 'linear-gradient(135deg,#dc2626,#ef4444)';
    t.classList.remove('hidden');
    setTimeout(() => t.classList.add('hidden'), 5000);
}
document.addEventListener('DOMContentLoaded', () => { showUniverso('all'); updateTabCounts(); });
</script>
@endpush
