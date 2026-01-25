@extends('layouts.app')

@section('title', 'Integrações - Intranet Mayer')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">🔗 Integrações</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Gerenciamento de sincronização de dados entre sistemas</p>
        </div>
        <button onclick="openSyncModal()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition">
            ⚡ Sincronizar Agora
        </button>
    </div>

    <!-- Mensagens de Status -->
    @if ($message = Session::get('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
            {{ $message }}
        </div>
    @endif

    @if ($message = Session::get('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            {{ $message }}
        </div>
    @endif

    <!-- Cards de Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <!-- Total de Sincronizações -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">Total de Sincronizações</p>
                    <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['total_syncs'] }}</p>
                </div>
                <span class="text-4xl">📊</span>
            </div>
        </div>

        <!-- Sincronizações Bem-sucedidas -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-green-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">Bem-sucedidas</p>
                    <p class="text-3xl font-bold text-green-600">{{ $stats['successful_syncs'] }}</p>
                </div>
                <span class="text-4xl">✅</span>
            </div>
        </div>

        <!-- Sincronizações Falhadas -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-red-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">Falhadas</p>
                    <p class="text-3xl font-bold text-red-600">{{ $stats['failed_syncs'] }}</p>
                </div>
                <span class="text-4xl">❌</span>
            </div>
        </div>

        <!-- Última Sincronização -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 border-l-4 border-purple-500">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">Última Sincronização</p>
                    @if($stats['last_sync'])
                        <p class="text-lg font-bold text-gray-900 dark:text-white">
                            {{ $stats['last_sync']->created_at->diffForHumans() }}
                        </p>
                    @else
                        <p class="text-lg font-bold text-gray-900 dark:text-white">Nunca</p>
                    @endif
                </div>
                <span class="text-4xl">🕐</span>
            </div>
        </div>
    </div>

    <!-- Seção de Integrações -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- DataJuri -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">📊 DataJuri</h2>
                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">Conectado</span>
            </div>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Sistema central de gestão de clientes, contratos e processos
            </p>
            <div class="space-y-2 mb-4">
                <p class="text-sm"><strong>Última sincronização:</strong> 
                    @if($stats['last_sync']?->source === 'datajuri')
                        {{ $stats['last_sync']->created_at->format('d/m/Y H:i') }}
                    @else
                        Não sincronizado
                    @endif
                </p>
                <p class="text-sm"><strong>Registros sincronizados:</strong> 302 clientes + 418 contratos</p>
                <p class="text-sm"><strong>Frequência:</strong> A cada 12 horas</p>
            </div>
            <button onclick="syncDataJuri()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                Sincronizar DataJuri
            </button>
        </div>

        <!-- ESPO CRM -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-xl font-bold text-gray-900 dark:text-white">🎯 ESPO CRM</h2>
                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">Conectado</span>
            </div>
            <p class="text-gray-600 dark:text-gray-400 mb-4">
                Sistema de gestão de relacionamento com clientes (CRM)
            </p>
            <div class="space-y-2 mb-4">
                <p class="text-sm"><strong>Última sincronização:</strong> 
                    @if($stats['last_sync']?->source === 'espocrm')
                        {{ $stats['last_sync']->created_at->format('d/m/Y H:i') }}
                    @else
                        Não sincronizado
                    @endif
                </p>
                <p class="text-sm"><strong>Registros sincronizados:</strong> 186 leads + 84 oportunidades</p>
                <p class="text-sm"><strong>Frequência:</strong> A cada 4 horas</p>
            </div>
            <button onclick="syncEspoCRM()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg transition">
                Sincronizar ESPO CRM
            </button>
        </div>
    </div>

    <!-- Histórico de Sincronizações -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">📋 Histórico de Sincronizações</h2>
        
        @if($recentLogs->count() > 0)
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Data/Hora</th>
                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Fonte</th>
                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Status</th>
                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Registros</th>
                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Duração</th>
                            <th class="px-4 py-2 text-left text-gray-900 dark:text-white">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($recentLogs as $log)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-4 py-3 text-gray-900 dark:text-white">
                                    {{ $log->created_at->format('d/m/Y H:i:s') }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($log->source === 'datajuri')
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">📊 DataJuri</span>
                                    @else
                                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">🎯 ESPO CRM</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if($log->status === 'success')
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">✅ Sucesso</span>
                                    @elseif($log->status === 'failed')
                                        <span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">❌ Falha</span>
                                    @else
                                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">⏳ Pendente</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white">
                                    {{ $log->records_processed ?? '-' }}
                                </td>
                                <td class="px-4 py-3 text-gray-900 dark:text-white">
                                    @if($log->duration_seconds)
                                        {{ $log->duration_seconds }}s
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('integration.show', $log) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                        Ver Detalhes
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-gray-600 dark:text-gray-400 text-center py-8">
                Nenhuma sincronização realizada ainda. Clique em "Sincronizar Agora" para começar.
            </p>
        @endif
    </div>
</div>

<!-- Modal de Sincronização -->
<div id="syncModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 max-w-md w-full mx-4">
        <h2 class="text-2xl font-bold text-gray-900 dark:text-white mb-4">⚡ Sincronizar Dados</h2>
        <p class="text-gray-600 dark:text-gray-400 mb-6">Escolha qual sistema sincronizar:</p>
        
        <div class="space-y-3">
            <button onclick="syncAll()" class="w-full bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-lg font-medium transition">
                Sincronizar Tudo
            </button>
            <button onclick="syncDataJuri()" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-3 rounded-lg font-medium transition">
                Apenas DataJuri
            </button>
            <button onclick="syncEspoCRM()" class="w-full bg-purple-600 hover:bg-purple-700 text-white px-4 py-3 rounded-lg font-medium transition">
                Apenas ESPO CRM
            </button>
            <button onclick="closeSyncModal()" class="w-full bg-gray-300 hover:bg-gray-400 text-gray-900 px-4 py-3 rounded-lg font-medium transition">
                Cancelar
            </button>
        </div>
    </div>
</div>

<script>
    function openSyncModal() {
        document.getElementById('syncModal').classList.remove('hidden');
    }

    function closeSyncModal() {
        document.getElementById('syncModal').classList.add('hidden');
    }

    function syncAll() {
        window.location.href = "{{ route('integration.sync', ['type' => 'all']) }}";
    }

    function syncDataJuri() {
        window.location.href = "{{ route('integration.sync', ['type' => 'datajuri']) }}";
    }

    function syncEspoCRM() {
        window.location.href = "{{ route('integration.sync', ['type' => 'espocrm']) }}";
    }

    // Fechar modal ao clicar fora
    document.getElementById('syncModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeSyncModal();
        }
    });
</script>
@endsection
