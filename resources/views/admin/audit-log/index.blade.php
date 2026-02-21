@extends('layouts.app')
@section('title', 'Audit Log')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: #1B334A;">Audit Log</h1>
            <p class="text-sm text-gray-500">Registro de ações críticas do sistema</p>
        </div>
    </div>

    <!-- Cards Resumo -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6" id="summary-cards">
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold" style="color: #385776;" id="card-total">-</div>
            <div class="text-xs text-gray-500">Total Registros</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-green-600" id="card-today">-</div>
            <div class="text-xs text-gray-500">Ações Hoje</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-blue-600" id="card-logins">-</div>
            <div class="text-xs text-gray-500">Logins Hoje</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold text-red-600" id="card-denied">-</div>
            <div class="text-xs text-gray-500">Bloqueios Hoje</div>
        </div>
        <div class="bg-white rounded-lg shadow p-4 text-center">
            <div class="text-2xl font-bold" style="color: #385776;" id="card-7d">-</div>
            <div class="text-xs text-gray-500">Ações 7 dias</div>
        </div>
    </div>

    <!-- Filtros -->
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Usuário</label>
                <select id="filter-user" class="w-full border rounded px-2 py-1.5 text-sm">
                    <option value="">Todos</option>
                    @foreach(\App\Models\User::orderBy('name')->get() as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Ação</label>
                <select id="filter-action" class="w-full border rounded px-2 py-1.5 text-sm">
                    <option value="">Todas</option>
                    <option value="login">Login</option>
                    <option value="logout">Logout</option>
                    <option value="login_failed">Login Falhou</option>
                    <option value="access_denied">Acesso Negado</option>
                    <option value="create">Criar</option>
                    <option value="update">Editar</option>
                    <option value="delete">Excluir</option>
                    <option value="sync">Sincronização</option>
                    <option value="export">Exportação</option>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Módulo</label>
                <input type="text" id="filter-module" class="w-full border rounded px-2 py-1.5 text-sm" placeholder="Ex: gdp, nexo...">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">De</label>
                <input type="date" id="filter-from" class="w-full border rounded px-2 py-1.5 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Até</label>
                <input type="date" id="filter-to" class="w-full border rounded px-2 py-1.5 text-sm">
            </div>
        </div>
        <div class="mt-3 flex gap-2">
            <button onclick="loadData()" class="px-4 py-1.5 rounded text-white text-sm font-medium" style="background-color: #385776;">Filtrar</button>
            <button onclick="clearFilters()" class="px-4 py-1.5 rounded border text-sm text-gray-600">Limpar</button>
        </div>
    </div>

    <!-- Gráficos -->
    <div class="grid md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Ações por Dia (30d)</h3>
            <canvas id="chartDaily" height="180"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Por Usuário (30d)</h3>
            <canvas id="chartUsers" height="180"></canvas>
        </div>
        <div class="bg-white rounded-lg shadow p-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-2">Por Tipo de Ação (30d)</h3>
            <canvas id="chartActions" height="180"></canvas>
        </div>
    </div>

    <!-- Tabela -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs text-white" style="background-color: #1B334A;">
                    <tr>
                        <th class="px-3 py-2 text-left">Data/Hora</th>
                        <th class="px-3 py-2 text-left">Usuário</th>
                        <th class="px-3 py-2 text-left">Role</th>
                        <th class="px-3 py-2 text-left">Ação</th>
                        <th class="px-3 py-2 text-left">Módulo</th>
                        <th class="px-3 py-2 text-left">Descrição</th>
                        <th class="px-3 py-2 text-left">IP</th>
                    </tr>
                </thead>
                <tbody id="logs-body" class="divide-y divide-gray-100">
                    <tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">Carregando...</td></tr>
                </tbody>
            </table>
        </div>
        <div id="pagination" class="px-4 py-3 border-t flex items-center justify-between text-sm text-gray-500"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
let currentPage = 1;
let chartDaily, chartUsers, chartActions;

const actionBadges = {
    login: 'bg-green-100 text-green-700',
    logout: 'bg-gray-100 text-gray-600',
    login_failed: 'bg-red-100 text-red-700',
    access_denied: 'bg-red-200 text-red-800',
    create: 'bg-blue-100 text-blue-700',
    update: 'bg-yellow-100 text-yellow-700',
    delete: 'bg-red-100 text-red-700',
    sync: 'bg-purple-100 text-purple-700',
    export: 'bg-indigo-100 text-indigo-700',
};

function loadData(page = 1) {
    currentPage = page;
    const params = new URLSearchParams();
    params.set('page', page);
    const userId = document.getElementById('filter-user').value;
    const action = document.getElementById('filter-action').value;
    const module = document.getElementById('filter-module').value;
    const from = document.getElementById('filter-from').value;
    const to = document.getElementById('filter-to').value;
    if (userId) params.set('user_id', userId);
    if (action) params.set('action', action);
    if (module) params.set('module', module);
    if (from) params.set('date_from', from);
    if (to) params.set('date_to', to);

    fetch(`{{ route('admin.audit-log.data') }}?${params}`)
        .then(r => r.json())
        .then(data => {
            renderSummary(data.summary);
            renderCharts(data);
            renderTable(data.logs);
        })
        .catch(e => console.error('Erro audit log:', e));
}

function renderSummary(s) {
    document.getElementById('card-total').textContent = s.total.toLocaleString('pt-BR');
    document.getElementById('card-today').textContent = s.today;
    document.getElementById('card-logins').textContent = s.logins_today;
    document.getElementById('card-denied').textContent = s.denied_today;
    document.getElementById('card-7d').textContent = s.actions_7d.toLocaleString('pt-BR');
}

function renderCharts(data) {
    const dailyCtx = document.getElementById('chartDaily').getContext('2d');
    if (chartDaily) chartDaily.destroy();
    chartDaily = new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: data.chart_daily.map(d => d.dia.substring(5)),
            datasets: [{
                label: 'Ações',
                data: data.chart_daily.map(d => d.total),
                borderColor: '#385776',
                backgroundColor: 'rgba(56,87,118,0.1)',
                fill: true,
                tension: 0.3,
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    const usersCtx = document.getElementById('chartUsers').getContext('2d');
    if (chartUsers) chartUsers.destroy();
    chartUsers = new Chart(usersCtx, {
        type: 'bar',
        data: {
            labels: data.chart_users.map(d => d.user_name.split(' ')[0]),
            datasets: [{
                label: 'Ações',
                data: data.chart_users.map(d => d.total),
                backgroundColor: '#385776',
            }]
        },
        options: { responsive: true, indexAxis: 'y', plugins: { legend: { display: false } } }
    });

    const actCtx = document.getElementById('chartActions').getContext('2d');
    if (chartActions) chartActions.destroy();
    const colors = ['#385776','#1B334A','#10B981','#EF4444','#F59E0B','#8B5CF6','#6366F1','#EC4899','#14B8A6'];
    chartActions = new Chart(actCtx, {
        type: 'doughnut',
        data: {
            labels: data.chart_actions.map(d => d.action),
            datasets: [{
                data: data.chart_actions.map(d => d.total),
                backgroundColor: colors.slice(0, data.chart_actions.length),
            }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } } }
    });
}

function renderTable(paginated) {
    const tbody = document.getElementById('logs-body');
    if (!paginated.data || paginated.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="px-3 py-8 text-center text-gray-400">Nenhum registro encontrado</td></tr>';
        document.getElementById('pagination').innerHTML = '';
        return;
    }
    tbody.innerHTML = paginated.data.map(log => {
        const dt = new Date(log.created_at);
        const dateStr = dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', {hour:'2-digit',minute:'2-digit'});
        const badge = actionBadges[log.action] || 'bg-gray-100 text-gray-600';
        return `<tr class="hover:bg-gray-50">
            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-500">${dateStr}</td>
            <td class="px-3 py-2 whitespace-nowrap font-medium">${log.user_name || '<span class=text-gray-400>Sistema</span>'}</td>
            <td class="px-3 py-2 whitespace-nowrap text-xs">${log.user_role || '-'}</td>
            <td class="px-3 py-2 whitespace-nowrap"><span class="px-2 py-0.5 rounded-full text-xs font-medium ${badge}">${log.action}</span></td>
            <td class="px-3 py-2 whitespace-nowrap text-xs">${log.module || '-'}</td>
            <td class="px-3 py-2 text-xs text-gray-600 max-w-xs truncate">${log.description || '-'}</td>
            <td class="px-3 py-2 whitespace-nowrap text-xs text-gray-400">${log.ip_address || '-'}</td>
        </tr>`;
    }).join('');

    // Paginação
    const pg = document.getElementById('pagination');
    const totalPages = paginated.last_page;
    pg.innerHTML = `<span>Mostrando ${paginated.from}-${paginated.to} de ${paginated.total}</span>
        <div class="flex gap-1">
            ${currentPage > 1 ? `<button onclick="loadData(${currentPage-1})" class="px-2 py-1 border rounded text-xs">Anterior</button>` : ''}
            <span class="px-2 py-1 text-xs">Página ${currentPage} de ${totalPages}</span>
            ${currentPage < totalPages ? `<button onclick="loadData(${currentPage+1})" class="px-2 py-1 border rounded text-xs">Próxima</button>` : ''}
        </div>`;
}

function clearFilters() {
    document.getElementById('filter-user').value = '';
    document.getElementById('filter-action').value = '';
    document.getElementById('filter-module').value = '';
    document.getElementById('filter-from').value = '';
    document.getElementById('filter-to').value = '';
    loadData();
}

document.addEventListener('DOMContentLoaded', () => loadData());
</script>
@endpush
