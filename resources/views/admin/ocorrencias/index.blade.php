@extends('layouts.app')

@section('title', 'Log de Ocorrências')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: #1B334A;">
                <i class="fas fa-clipboard-list mr-2"></i>Log de Ocorrências
            </h1>
            <p class="text-sm text-gray-500 mt-1">Monitoramento de eventos operacionais do sistema</p>
        </div>
        <div class="text-sm text-gray-400">
            Atualizado: {{ now()->format('d/m/Y H:i') }}
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 mb-6">
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Hoje</div>
            <div class="text-2xl font-bold" style="color: #1B334A;">{{ $totalHoje }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Erros</div>
            <div class="text-2xl font-bold text-red-600">{{ $errorsHoje }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-500">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Avisos</div>
            <div class="text-2xl font-bold text-yellow-600">{{ $warningsHoje }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-400">
            <div class="text-xs text-gray-500 uppercase tracking-wide">7 dias</div>
            <div class="text-2xl font-bold text-gray-700">{{ $total7dias }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-indigo-500">
            <div class="text-xs text-gray-500 uppercase tracking-wide">GDP</div>
            <div class="text-2xl font-bold text-indigo-600">{{ $porCategoria['gdp'] ?? 0 }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Financeiro</div>
            <div class="text-2xl font-bold text-green-600">{{ $porCategoria['financeiro'] ?? 0 }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-orange-500">
            <div class="text-xs text-gray-500 uppercase tracking-wide">CRM</div>
            <div class="text-2xl font-bold text-orange-600">{{ $porCategoria['crm'] ?? 0 }}</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">
            <div class="text-xs text-gray-500 uppercase tracking-wide">Sistema</div>
            <div class="text-2xl font-bold text-gray-600">{{ $porCategoria['sistema'] ?? 0 }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">Tendência (30 dias)</h3>
            <canvas id="chartTendencia" height="160"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">Por Categoria</h3>
            <canvas id="chartCategoria" height="160"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-600 mb-3">Por Severidade</h3>
            <canvas id="chartSeveridade" height="160"></canvas>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <form method="GET" action="{{ route('admin.ocorrencias') }}" class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[150px]">
                <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" class="w-full border rounded px-3 py-2 text-sm" placeholder="Título, descrição, usuário...">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Categoria</label>
                <select name="category" class="border rounded px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    <option value="gdp" {{ request('category') == 'gdp' ? 'selected' : '' }}>GDP</option>
                    <option value="financeiro" {{ request('category') == 'financeiro' ? 'selected' : '' }}>Financeiro</option>
                    <option value="crm" {{ request('category') == 'crm' ? 'selected' : '' }}>CRM</option>
                    <option value="sistema" {{ request('category') == 'sistema' ? 'selected' : '' }}>Sistema</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Severidade</label>
                <select name="severity" class="border rounded px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    <option value="info" {{ request('severity') == 'info' ? 'selected' : '' }}>Info</option>
                    <option value="warning" {{ request('severity') == 'warning' ? 'selected' : '' }}>Warning</option>
                    <option value="error" {{ request('severity') == 'error' ? 'selected' : '' }}>Error</option>
                    <option value="critical" {{ request('severity') == 'critical' ? 'selected' : '' }}>Critical</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">De</label>
                <input type="date" name="data_inicio" value="{{ request('data_inicio') }}" class="border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Até</label>
                <input type="date" name="data_fim" value="{{ request('data_fim') }}" class="border rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Tipo</label>
                <input type="text" name="event_type" value="{{ request('event_type') }}" class="border rounded px-3 py-2 text-sm w-36" placeholder="ex: penalidade">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="text-white px-4 py-2 rounded text-sm font-medium" style="background-color: #385776;"><i class="fas fa-search mr-1"></i>Filtrar</button>
                <a href="{{ route('admin.ocorrencias') }}" class="bg-gray-200 text-gray-700 px-4 py-2 rounded text-sm font-medium">Limpar</a>
            </div>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-500" style="background-color: #f8fafc;">
                        <th class="px-4 py-3">Data/Hora</th>
                        <th class="px-4 py-3">Severidade</th>
                        <th class="px-4 py-3">Categoria</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Título</th>
                        <th class="px-4 py-3">Usuário</th>
                        <th class="px-4 py-3 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($eventos as $evento)
                    <tr class="hover:bg-gray-50 transition-colors {{ $evento->severity === 'critical' ? 'bg-purple-50' : ($evento->severity === 'error' ? 'bg-red-50' : '') }}">
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $evento->created_at->format('d/m H:i:s') }}</td>
                        <td class="px-4 py-3">
                            @php $sevColors = ['info'=>'bg-blue-100 text-blue-700','warning'=>'bg-yellow-100 text-yellow-700','error'=>'bg-red-100 text-red-700','critical'=>'bg-purple-100 text-purple-700']; @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $sevColors[$evento->severity] ?? 'bg-gray-100 text-gray-700' }}">{{ strtoupper($evento->severity) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @php $catColors = ['gdp'=>'bg-indigo-100 text-indigo-700','financeiro'=>'bg-green-100 text-green-700','crm'=>'bg-orange-100 text-orange-700','sistema'=>'bg-gray-100 text-gray-700']; @endphp
                            <span class="px-2 py-1 rounded-full text-xs font-medium {{ $catColors[$evento->category] ?? 'bg-gray-100 text-gray-700' }}">{{ $evento->category_label }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $evento->event_type }}</td>
                        <td class="px-4 py-3 text-gray-800 max-w-xs truncate" title="{{ $evento->title }}">{{ $evento->title }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $evento->user_name ?? '⚙️ Sistema' }}</td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="verDetalhes({{ $evento->id }})" class="text-blue-600 hover:text-blue-800 text-xs font-medium"><i class="fas fa-eye mr-1"></i>Detalhes</button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            <i class="fas fa-inbox text-3xl mb-2 block"></i>
                            Nenhum evento registrado com os filtros selecionados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($eventos->hasPages())
        <div class="px-4 py-3 border-t bg-gray-50">{{ $eventos->links() }}</div>
        @endif
    </div>
</div>

<div id="modalDetalhes" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black bg-opacity-50" onclick="fecharModal()"></div>
    <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[80vh] overflow-auto">
        <div class="flex items-center justify-between px-6 py-4 border-b" style="background-color: #1B334A;">
            <h3 class="text-white font-semibold"><i class="fas fa-info-circle mr-2"></i>Detalhes do Evento</h3>
            <button onclick="fecharModal()" class="text-white hover:text-gray-300"><i class="fas fa-times text-lg"></i></button>
        </div>
        <div id="modalBody" class="p-6">
            <div class="text-center text-gray-400 py-8"><i class="fas fa-spinner fa-spin text-2xl"></i></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
const tendenciaData = @json($tendencia);
const tendenciaLabels = Object.keys(tendenciaData).map(d => { const p = d.split('-'); return p[2]+'/'+p[1]; });
new Chart(document.getElementById('chartTendencia'), {
    type: 'line',
    data: { labels: tendenciaLabels, datasets: [{ label: 'Eventos', data: Object.values(tendenciaData), borderColor: '#385776', backgroundColor: 'rgba(56,87,118,0.1)', fill: true, tension: 0.3, pointRadius: 2 }] },
    options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } }, x: { ticks: { maxTicksLimit: 10, font: { size: 10 } } } } }
});

const catData = @json($distCategoria);
const catLabels = { gdp: 'GDP', financeiro: 'Financeiro', crm: 'CRM', sistema: 'Sistema' };
const catCores = { gdp: '#6366f1', financeiro: '#22c55e', crm: '#f97316', sistema: '#6b7280' };
new Chart(document.getElementById('chartCategoria'), {
    type: 'doughnut',
    data: { labels: Object.keys(catData).map(k => catLabels[k]||k), datasets: [{ data: Object.values(catData), backgroundColor: Object.keys(catData).map(k => catCores[k]||'#ccc') }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

const sevData = @json($distSeveridade);
const sevLabels = { info: 'Info', warning: 'Warning', error: 'Error', critical: 'Critical' };
const sevCores = { info: '#3b82f6', warning: '#eab308', error: '#ef4444', critical: '#a855f7' };
new Chart(document.getElementById('chartSeveridade'), {
    type: 'doughnut',
    data: { labels: Object.keys(sevData).map(k => sevLabels[k]||k), datasets: [{ data: Object.values(sevData), backgroundColor: Object.keys(sevData).map(k => sevCores[k]||'#ccc') }] },
    options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
});

function verDetalhes(id) {
    document.getElementById('modalDetalhes').classList.remove('hidden');
    document.getElementById('modalBody').innerHTML = '<div class="text-center text-gray-400 py-8"><i class="fas fa-spinner fa-spin text-2xl"></i></div>';
    fetch('/admin/ocorrencias/' + id).then(r => r.json()).then(data => {
        const sb = { info:'bg-blue-100 text-blue-700', warning:'bg-yellow-100 text-yellow-700', error:'bg-red-100 text-red-700', critical:'bg-purple-100 text-purple-700' };
        const cb = { gdp:'bg-indigo-100 text-indigo-700', financeiro:'bg-green-100 text-green-700', crm:'bg-orange-100 text-orange-700', sistema:'bg-gray-100 text-gray-700' };
        let meta = data.metadata ? '<div class="mt-4"><h4 class="text-sm font-semibold text-gray-600 mb-2">Metadata</h4><pre class="bg-gray-50 rounded p-3 text-xs overflow-auto max-h-60 border">'+JSON.stringify(data.metadata,null,2)+'</pre></div>' : '';
        let desc = data.description ? '<div class="mt-4"><h4 class="text-sm font-semibold text-gray-600 mb-2">Descrição</h4><p class="text-sm text-gray-700 bg-gray-50 rounded p-3 border">'+data.description+'</p></div>' : '';
        let rel = data.related_model ? '<div><span class="text-xs text-gray-500">Model</span><p class="font-mono text-sm">'+data.related_model+'</p></div><div><span class="text-xs text-gray-500">ID</span><p class="font-mono text-sm">#'+data.related_id+'</p></div>' : '';
        let ip = data.ip_address ? '<div class="col-span-2"><span class="text-xs text-gray-500">IP</span><p class="font-mono text-sm">'+data.ip_address+'</p></div>' : '';
        document.getElementById('modalBody').innerHTML = '<div class="grid grid-cols-2 gap-4"><div><span class="text-xs text-gray-500">ID</span><p class="font-mono text-sm">#'+data.id+'</p></div><div><span class="text-xs text-gray-500">Data/Hora</span><p class="text-sm">'+data.created_at+'</p></div><div><span class="text-xs text-gray-500">Severidade</span><p><span class="px-2 py-1 rounded-full text-xs font-medium '+(sb[data.severity]||'')+'">'+data.severity.toUpperCase()+'</span></p></div><div><span class="text-xs text-gray-500">Categoria</span><p><span class="px-2 py-1 rounded-full text-xs font-medium '+(cb[data.category]||'')+'">'+data.category_label+'</span></p></div><div><span class="text-xs text-gray-500">Tipo</span><p class="font-mono text-sm">'+data.event_type+'</p></div><div><span class="text-xs text-gray-500">Usuário</span><p class="text-sm">'+(data.user_name||'⚙️ Sistema')+'</p></div><div class="col-span-2"><span class="text-xs text-gray-500">Título</span><p class="text-sm font-medium" style="color:#1B334A;">'+data.title+'</p></div>'+rel+ip+'</div>'+desc+meta;
    }).catch(() => { document.getElementById('modalBody').innerHTML = '<p class="text-red-500 text-center py-4">Erro ao carregar detalhes.</p>'; });
}
function fecharModal() { document.getElementById('modalDetalhes').classList.add('hidden'); }
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') fecharModal(); });
</script>
@endpush
