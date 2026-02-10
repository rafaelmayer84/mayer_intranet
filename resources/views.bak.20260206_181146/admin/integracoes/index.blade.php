@extends('layouts.app')

@section('title', 'Administração de Integrações')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                🔗 Administração de Integrações
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Controle e monitoramento das integrações com DataJuri e ESPO CRM</p>
        </div>
        <button onclick="checkStatus()" class="mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center gap-2 transition-colors">
            🔄 Verificar Status
        </button>
    </div>

    <!-- Contagem de Registros por Tabela -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-6 py-4">
            <h5 class="text-lg font-semibold flex items-center gap-2">
                📊 Registros no Banco de Dados
            </h5>
        </div>
        <div class="p-6">
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                @foreach($counts as $table => $count)
                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 text-center">
                    <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ number_format($count, 0, ',', '.') }}</p>
                    <p class="text-xs text-gray-600 dark:text-gray-400 mt-1">{{ str_replace('_', ' ', ucfirst($table)) }}</p>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Status das APIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- DataJuri API -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-blue-600 text-white px-6 py-4">
                <h5 class="text-lg font-semibold flex items-center gap-2">
                    🗄️ DataJuri API
                </h5>
            </div>
            <div class="p-6">
                <div id="datajuri-status" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <p class="mt-3 text-gray-600 dark:text-gray-400">Verificando conexão...</p>
                </div>
            </div>
        </div>

        <!-- ESPO CRM API -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-cyan-600 text-white px-6 py-4">
                <h5 class="text-lg font-semibold flex items-center gap-2">
                    ☁️ ESPO CRM API
                </h5>
            </div>
            <div class="p-6">
                <div id="espocrm-status" class="text-center py-4">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-600"></div>
                    <p class="mt-3 text-gray-600 dark:text-gray-400">Verificando conexão...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Sincronização DataJuri Completa -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
        <div class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-6 py-4">
            <h5 class="text-lg font-semibold flex items-center gap-2">
                🚀 Sincronização DataJuri Completa
            </h5>
            <p class="text-sm text-green-100 mt-1">Sincroniza todos os 8 módulos do DataJuri</p>
        </div>
        <div class="p-6">
            <!-- Botões de Módulos -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <button onclick="syncModulo('pessoas')" class="sync-btn bg-blue-100 dark:bg-blue-900 hover:bg-blue-200 dark:hover:bg-blue-800 text-blue-800 dark:text-blue-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    👥 Pessoas
                </button>
                <button onclick="syncModulo('processos')" class="sync-btn bg-purple-100 dark:bg-purple-900 hover:bg-purple-200 dark:hover:bg-purple-800 text-purple-800 dark:text-purple-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    ⚖️ Processos
                </button>
                <button onclick="syncModulo('fases')" class="sync-btn bg-indigo-100 dark:bg-indigo-900 hover:bg-indigo-200 dark:hover:bg-indigo-800 text-indigo-800 dark:text-indigo-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    📋 Fases
                </button>
                <button onclick="syncModulo('movimentos')" class="sync-btn bg-green-100 dark:bg-green-900 hover:bg-green-200 dark:hover:bg-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    💰 Movimentos
                </button>
                <button onclick="syncModulo('contratos')" class="sync-btn bg-yellow-100 dark:bg-yellow-900 hover:bg-yellow-200 dark:hover:bg-yellow-800 text-yellow-800 dark:text-yellow-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    📝 Contratos
                </button>
                <button onclick="syncModulo('atividades')" class="sync-btn bg-orange-100 dark:bg-orange-900 hover:bg-orange-200 dark:hover:bg-orange-800 text-orange-800 dark:text-orange-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    📅 Atividades
                </button>
                <button onclick="syncModulo('horas')" class="sync-btn bg-pink-100 dark:bg-pink-900 hover:bg-pink-200 dark:hover:bg-pink-800 text-pink-800 dark:text-pink-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    ⏱️ Horas
                </button>
                <button onclick="syncModulo('ordens')" class="sync-btn bg-red-100 dark:bg-red-900 hover:bg-red-200 dark:hover:bg-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg text-sm font-medium transition-colors">
                    📦 Ordens Serviço
                </button>
            </div>

            <!-- Botão Sync Completo -->
            <div class="flex justify-center mb-6">
                <button onclick="syncCompleto()" id="btn-sync-completo" class="bg-gradient-to-r from-green-600 to-emerald-600 hover:from-green-700 hover:to-emerald-700 text-white px-8 py-4 rounded-xl text-lg font-bold shadow-lg transition-all transform hover:scale-105 flex items-center gap-3">
                    <span class="text-2xl">🔄</span>
                    Sincronizar TUDO
                </button>
            </div>

            <!-- Barra de Progresso -->
            <div id="progress-container" class="hidden mb-6">
                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                    <span id="progress-label">Iniciando...</span>
                    <span id="progress-percent">0%</span>
                </div>
                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-4 overflow-hidden">
                    <div id="progress-bar" class="bg-gradient-to-r from-green-500 to-emerald-500 h-4 rounded-full transition-all duration-500" style="width: 0%"></div>
                </div>
            </div>

            <!-- Log em Tempo Real -->
            <div id="sync-log-container" class="hidden">
                <div class="flex justify-between items-center mb-2">
                    <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300">📝 Log de Sincronização</h6>
                    <button onclick="clearLog()" class="text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300">Limpar</button>
                </div>
                <div id="sync-log" class="bg-gray-900 text-green-400 font-mono text-xs p-4 rounded-lg h-64 overflow-y-auto"></div>
            </div>

            <!-- Resultado Final -->
            <div id="sync-result" class="hidden mt-6">
                <div class="bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg p-4">
                    <h6 class="font-semibold text-green-800 dark:text-green-200 mb-3">✅ Sincronização Concluída</h6>
                    <div id="sync-result-details" class="grid grid-cols-2 md:grid-cols-4 gap-3"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-blue-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total Sincronizações</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-2">{{ $stats['total_syncs'] }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-purple-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Hoje</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-2">{{ $stats['syncs_today'] }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-green-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Sucesso</p>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $stats['syncs_success'] }}</p>
            </div>
        </div>
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-red-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Erros</p>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-2">{{ $stats['syncs_error'] }}</p>
            </div>
        </div>
    </div>

    <!-- Histórico de Sincronizações -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
            <h5 class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                🕐 Histórico de Sincronizações
            </h5>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Data/Hora</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Fonte</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Registros</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Duração</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ ucfirst(str_replace('_', ' ', $log->tipo ?? '-')) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-gray-200">
                                {{ strtoupper($log->fonte ?? '-') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ ($log->status ?? '') == 'concluido' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' }}">
                                {{ ucfirst($log->status ?? '-') }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $log->registros_processados ?? 0 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $log->duracao_segundos ?? 0 }}s</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            Nenhuma sincronização registrada ainda
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
const csrfToken = '{{ csrf_token() }}';
let syncInProgress = false;

function checkStatus() {
    document.getElementById('datajuri-status').innerHTML = '<div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div><p class="mt-3 text-gray-600 dark:text-gray-400">Verificando...</p>';
    document.getElementById('espocrm-status').innerHTML = '<div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-600"></div><p class="mt-3 text-gray-600 dark:text-gray-400">Verificando...</p>';

    fetch('/admin/integracoes/status')
        .then(r => r.json())
        .then(data => {
            // DataJuri
            let djBg = data.datajuri.status === 'online' ? 'bg-green-100 dark:bg-green-900 border-green-500' : 'bg-red-100 dark:bg-red-900 border-red-500';
            let djText = data.datajuri.status === 'online' ? 'text-green-800 dark:text-green-200' : 'text-red-800 dark:text-red-200';
            let djIcon = data.datajuri.status === 'online' ? '✅' : '❌';
            document.getElementById('datajuri-status').innerHTML = `<div class="${djBg} ${djText} border-l-4 p-4 rounded"><div class="flex items-center gap-2"><span class="text-xl">${djIcon}</span><span class="font-medium">${data.datajuri.message}</span></div><p class="text-xs mt-2 opacity-75">${data.datajuri.last_check}</p></div>`;

            // ESPO CRM
            let espoBg = data.espocrm.status === 'online' ? 'bg-green-100 dark:bg-green-900 border-green-500' : 'bg-yellow-100 dark:bg-yellow-900 border-yellow-500';
            let espoText = data.espocrm.status === 'online' ? 'text-green-800 dark:text-green-200' : 'text-yellow-800 dark:text-yellow-200';
            let espoIcon = data.espocrm.status === 'online' ? '✅' : '⚠️';
            document.getElementById('espocrm-status').innerHTML = `<div class="${espoBg} ${espoText} border-l-4 p-4 rounded"><div class="flex items-center gap-2"><span class="text-xl">${espoIcon}</span><span class="font-medium">${data.espocrm.message}</span></div><p class="text-xs mt-2 opacity-75">${data.espocrm.last_check}</p></div>`;
        })
        .catch(err => {
            document.getElementById('datajuri-status').innerHTML = '<div class="bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 border-l-4 border-red-500 p-4 rounded">❌ Erro ao verificar</div>';
            document.getElementById('espocrm-status').innerHTML = '<div class="bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 border-l-4 border-red-500 p-4 rounded">❌ Erro ao verificar</div>';
        });
}

function addLog(message, type = 'info') {
    const log = document.getElementById('sync-log');
    const time = new Date().toLocaleTimeString('pt-BR');
    const colors = {
        'info': 'text-green-400',
        'success': 'text-emerald-400',
        'error': 'text-red-400',
        'warning': 'text-yellow-400'
    };
    log.innerHTML += `<div class="${colors[type] || colors.info}">[${time}] ${message}</div>`;
    log.scrollTop = log.scrollHeight;
}

function clearLog() {
    document.getElementById('sync-log').innerHTML = '';
}

function updateProgress(percent, label) {
    document.getElementById('progress-bar').style.width = percent + '%';
    document.getElementById('progress-percent').innerText = percent + '%';
    document.getElementById('progress-label').innerText = label;
}

function disableButtons(disabled) {
    document.querySelectorAll('.sync-btn, #btn-sync-completo').forEach(btn => {
        btn.disabled = disabled;
        btn.style.opacity = disabled ? '0.5' : '1';
        btn.style.cursor = disabled ? 'not-allowed' : 'pointer';
    });
}

function syncModulo(modulo) {
    if (syncInProgress) return;
    
    syncInProgress = true;
    disableButtons(true);
    
    document.getElementById('progress-container').classList.remove('hidden');
    document.getElementById('sync-log-container').classList.remove('hidden');
    document.getElementById('sync-result').classList.add('hidden');
    
    clearLog();
    addLog(`Iniciando sincronização: ${modulo}...`);
    updateProgress(10, `Sincronizando ${modulo}...`);

    fetch('/admin/integracoes/sync-datajuri', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ modulo: modulo })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateProgress(100, 'Concluído!');
            addLog(`✅ Sincronização concluída: ${data.total} registros em ${data.duration}s`, 'success');
            showResults(data.results);
        } else {
            addLog(`❌ Erro: ${data.message}`, 'error');
        }
    })
    .catch(err => {
        addLog(`❌ Erro: ${err.message}`, 'error');
    })
    .finally(() => {
        syncInProgress = false;
        disableButtons(false);
    });
}

function syncCompleto() {
    if (syncInProgress) return;
    if (!confirm('Iniciar sincronização COMPLETA de todos os módulos do DataJuri?\n\nIsso pode levar alguns minutos.')) return;
    
    syncInProgress = true;
    disableButtons(true);
    
    document.getElementById('progress-container').classList.remove('hidden');
    document.getElementById('sync-log-container').classList.remove('hidden');
    document.getElementById('sync-result').classList.add('hidden');
    
    clearLog();
    addLog('🚀 Iniciando sincronização COMPLETA...');
    
    const modulos = [
        { key: 'pessoas', label: '👥 Pessoas' },
        { key: 'processos', label: '⚖️ Processos' },
        { key: 'fases', label: '📋 Fases' },
        { key: 'movimentos', label: '💰 Movimentos' },
        { key: 'contratos', label: '📝 Contratos' },
        { key: 'atividades', label: '📅 Atividades' },
        { key: 'horas', label: '⏱️ Horas' },
        { key: 'ordens', label: '📦 Ordens' }
    ];
    
    updateProgress(5, 'Autenticando...');
    addLog('🔐 Autenticando com DataJuri...');

    fetch('/admin/integracoes/sync-datajuri', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken
        },
        body: JSON.stringify({ modulo: 'all' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            updateProgress(100, 'Concluído!');
            
            // Log cada módulo
            Object.entries(data.results).forEach(([key, info]) => {
                addLog(`✅ ${info.label}: ${info.count} registros → ${info.table}`, 'success');
            });
            
            addLog(`\n🎉 TOTAL: ${data.total} registros sincronizados em ${data.duration}s`, 'success');
            showResults(data.results);
        } else {
            addLog(`❌ Erro: ${data.message}`, 'error');
            updateProgress(0, 'Erro!');
        }
    })
    .catch(err => {
        addLog(`❌ Erro de conexão: ${err.message}`, 'error');
        updateProgress(0, 'Erro!');
    })
    .finally(() => {
        syncInProgress = false;
        disableButtons(false);
    });
}

function showResults(results) {
    const container = document.getElementById('sync-result-details');
    container.innerHTML = '';
    
    Object.entries(results).forEach(([key, info]) => {
        container.innerHTML += `
            <div class="bg-white dark:bg-gray-800 rounded-lg p-3 text-center border border-green-200 dark:border-green-800">
                <p class="text-lg font-bold text-green-700 dark:text-green-300">${info.count}</p>
                <p class="text-xs text-gray-600 dark:text-gray-400">${info.label}</p>
            </div>
        `;
    });
    
    document.getElementById('sync-result').classList.remove('hidden');
}

// Verificar status ao carregar
document.addEventListener('DOMContentLoaded', checkStatus);
</script>
@endsection
