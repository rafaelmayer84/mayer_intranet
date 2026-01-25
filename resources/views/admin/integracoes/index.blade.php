@extends('layouts.app')

@section('title', 'Administração de Integrações')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <div>
            <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 
4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 
001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 
1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 
00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 
1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 
3 3 0 016 0z"/>
                </svg>
                Administração de Integrações
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-1">Controle e monitoramento das integrações com 
DataJuri e ESPO CRM</p>
        </div>
        <button onclick="checkStatus()" class="mt-4 md:mt-0 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 
rounded-lg flex items-center gap-2 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 
2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Verificar Status
        </button>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Sincronizações -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-blue-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Total Sincronizações</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-2">{{ $stats['total_syncs'] 
}}</p>
            </div>
        </div>

        <!-- Hoje -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-purple-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Hoje</p>
                <p class="text-3xl font-bold text-gray-800 dark:text-gray-100 mt-2">{{ $stats['syncs_today'] 
}}</p>
            </div>
        </div>

        <!-- Sucesso -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-green-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Sucesso</p>
                <p class="text-3xl font-bold text-green-600 dark:text-green-400 mt-2">{{ $stats['syncs_success'] 
}}</p>
            </div>
        </div>

        <!-- Erros -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border-t-4 border-red-500 p-6">
            <div class="text-center">
                <p class="text-sm text-gray-600 dark:text-gray-400 font-medium">Erros</p>
                <p class="text-3xl font-bold text-red-600 dark:text-red-400 mt-2">{{ $stats['syncs_error'] 
}}</p>
            </div>
        </div>
    </div>

    <!-- Status das APIs -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <!-- DataJuri API -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-blue-600 text-white px-6 py-4">
                <h5 class="text-lg font-semibold flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 
3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"/>
                    </svg>
                    DataJuri API
                </h5>
            </div>
            <div class="p-6">
                <div id="datajuri-status" class="text-center py-6">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 
border-blue-600"></div>
                    <p class="mt-3 text-gray-600 dark:text-gray-400">Verificando conexão...</p>
                </div>
                <hr class="my-4 border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Última verificação: <span 
id="datajuri-last-check" class="font-medium">-</span></span>
                </div>
            </div>
        </div>

        <!-- ESPO CRM API -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
            <div class="bg-cyan-600 text-white px-6 py-4">
                <h5 class="text-lg font-semibold flex items-center gap-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 
4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/>
                    </svg>
                    ESPO CRM API
                </h5>
            </div>
            <div class="p-6">
                <div id="espocrm-status" class="text-center py-6">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 
border-cyan-600"></div>
                    <p class="mt-3 text-gray-600 dark:text-gray-400">Verificando conexão...</p>
                </div>
                <hr class="my-4 border-gray-200 dark:border-gray-700">
                <div class="flex justify-between items-center text-sm">
                    <span class="text-gray-600 dark:text-gray-400">Última verificação: <span 
id="espocrm-last-check" class="font-medium">-</span></span>
                    <span class="text-gray-600 dark:text-gray-400">API Key: <code class="text-xs bg-gray-100 
dark:bg-gray-700 px-2 py-1 rounded">{{ substr(env('ESPOCRM_API_KEY', 'não configurada'), 0, 8) 
}}...</code></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Ações de Sincronização -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden mb-6">
        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
            <h5 class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 
2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Sincronização Manual
            </h5>
        </div>
        <div class="p-6">
            <div class="flex flex-wrap gap-3">
                <button onclick="triggerSync('full')" class="bg-green-600 hover:bg-green-700 text-white px-4 
py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 
4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Sincronização Completa
                </button>
                <button onclick="triggerSync('clientes')" class="bg-white hover:bg-gray-50 dark:bg-gray-700 
dark:hover:bg-gray-600 text-green-600 dark:text-green-400 border-2 border-green-600 dark:border-green-400 px-4 
py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 
0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 
20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 
0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    Apenas Clientes
                </button>
                <button onclick="triggerSync('leads')" class="bg-white hover:bg-gray-50 dark:bg-gray-700 
dark:hover:bg-gray-600 text-green-600 dark:text-green-400 border-2 border-green-600 dark:border-green-400 px-4 
py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 
0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                    </svg>
                    Apenas Leads
                </button>
                <button onclick="triggerSync('oportunidades')" class="bg-white hover:bg-gray-50 dark:bg-gray-700 
dark:hover:bg-gray-600 text-green-600 dark:text-green-400 border-2 border-green-600 dark:border-green-400 px-4 
py-2 rounded-lg flex items-center gap-2 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 
4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                    </svg>
                    Apenas Oportunidades
                </button>
            </div>
            <div id="sync-output" class="mt-4 hidden">
                <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-4 rounded">
                    <p class="font-semibold text-blue-800 dark:text-blue-300 mb-2">Saída da Sincronização:</p>
                    <pre id="sync-output-text" class="text-sm text-gray-700 dark:text-gray-300 bg-white 
dark:bg-gray-800 p-3 rounded max-h-48 overflow-y-auto"></pre>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico de Sincronizações -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md overflow-hidden">
        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 border-b border-gray-200 dark:border-gray-600">
            <h5 class="text-lg font-semibold text-gray-800 dark:text-gray-100 flex items-center gap-2">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 
0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                Histórico de Sincronizações
            </h5>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Data/Hora</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Fonte</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Processados</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Criados</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Atualizados</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 
uppercase tracking-wider">Duração</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($logs as $log)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ 
$log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ 
ucfirst($log->tipo) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-200 
dark:bg-gray-600 text-gray-800 dark:text-gray-200">
                                {{ strtoupper($log->fonte) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ 
$log->status == 'concluido' ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 
dark:bg-red-900 text-red-800 dark:text-red-200' }}">
                                {{ ucfirst($log->status) }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ 
$log->registros_processados }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600 dark:text-green-400 
font-medium">{{ $log->registros_criados }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-blue-600 dark:text-blue-400 
font-medium">{{ $log->registros_atualizados }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ 
$log->duracao_segundos ?? 0 }}s</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                            <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" 
viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 
5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 
012-2h2a2 2 0 012 2"/>
                            </svg>
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
function checkStatus() {
    document.getElementById('datajuri-status').innerHTML = '<div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div><p class="mt-3 text-gray-600 dark:text-gray-400">Verificando...</p>';
    document.getElementById('espocrm-status').innerHTML = '<div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-cyan-600"></div><p class="mt-3 text-gray-600 dark:text-gray-400">Verificando...</p>';
    
    fetch('/admin/integracoes/status')
        .then(r => r.json())
        .then(data => {
            // DataJuri
            let djClass = data.datajuri.status === 'online' ? 'green' : (data.datajuri.status === 'offline' ? 
'red' : 'yellow');
            let djBg = data.datajuri.status === 'online' ? 'bg-green-100 dark:bg-green-900 border-green-500' : 
(data.datajuri.status === 'offline' ? 'bg-red-100 dark:bg-red-900 border-red-500' : 'bg-yellow-100 
dark:bg-yellow-900 border-yellow-500');
            let djText = data.datajuri.status === 'online' ? 'text-green-800 dark:text-green-200' : 
(data.datajuri.status === 'offline' ? 'text-red-800 dark:text-red-200' : 'text-yellow-800 
dark:text-yellow-200');
            document.getElementById('datajuri-status').innerHTML = 
                `<div class="${djBg} ${djText} border-l-4 p-4 rounded"><div class="flex items-center gap-2"><svg 
class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/></svg><span 
class="font-medium">${data.datajuri.message}</span></div></div>`;
            document.getElementById('datajuri-last-check').innerText = data.datajuri.last_check;
            
            // ESPO CRM
            let espoBg = data.espocrm.status === 'online' ? 'bg-green-100 dark:bg-green-900 border-green-500' : 
(data.espocrm.status === 'offline' ? 'bg-red-100 dark:bg-red-900 border-red-500' : 'bg-yellow-100 
dark:bg-yellow-900 border-yellow-500');
            let espoText = data.espocrm.status === 'online' ? 'text-green-800 dark:text-green-200' : 
(data.espocrm.status === 'offline' ? 'text-red-800 dark:text-red-200' : 'text-yellow-800 dark:text-yellow-200');
            document.getElementById('espocrm-status').innerHTML = 
                `<div class="${espoBg} ${espoText} border-l-4 p-4 rounded"><div class="flex items-center 
gap-2"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8"/></svg><span 
class="font-medium">${data.espocrm.message}</span></div></div>`;
            document.getElementById('espocrm-last-check').innerText = data.espocrm.last_check;
        })
        .catch(err => {
            document.getElementById('datajuri-status').innerHTML = '<div class="bg-red-100 dark:bg-red-900 
text-red-800 dark:text-red-200 border-l-4 border-red-500 p-4 rounded"><span class="font-medium">Erro ao 
verificar status</span></div>';
            document.getElementById('espocrm-status').innerHTML = '<div class="bg-red-100 dark:bg-red-900 
text-red-800 dark:text-red-200 border-l-4 border-red-500 p-4 rounded"><span class="font-medium">Erro ao 
verificar status</span></div>';
        });
}

function triggerSync(tipo) {
    if (!confirm(`Iniciar sincronização de ${tipo}?`)) return;
    
    document.getElementById('sync-output').classList.remove('hidden');
    document.getElementById('sync-output-text').innerText = 'Iniciando sincronização...';
    
    fetch('/admin/integracoes/sync', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ tipo: tipo })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.getElementById('sync-output-text').innerText = data.output;
            setTimeout(() => location.reload(), 3000);
        } else {
            document.getElementById('sync-output-text').innerText = 'Erro: ' + data.message;
        }
    })
    .catch(err => {
        document.getElementById('sync-output-text').innerText = 'Erro: ' + err.message;
    });
}

// Verificar status ao carregar
document.addEventListener('DOMContentLoaded', checkStatus);
</script>
@endsection

