<!-- Cards de API (mantém o conteúdo original) -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <!-- DataJuri API Card -->
    <div class="rounded-2xl border-t-4 border-blue-500 bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">⚖️ DataJuri API</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Sistema de processos jurídicos</p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 dark:bg-blue-900">
                <span class="text-2xl">⚖️</span>
            </div>
        </div>

        <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Total de sincronizações:</span>
                <span class="font-semibold text-gray-900 dark:text-white" id="datajuri-total">0</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Sucesso:</span>
                <span class="font-semibold text-green-600 dark:text-green-400" id="datajuri-sucesso">0</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Erros:</span>
                <span class="font-semibold text-red-600 dark:text-red-400" id="datajuri-erros">0</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Última sincronização:</span>
                <span class="font-semibold text-gray-900 dark:text-white" id="datajuri-ultima">Nunca</span>
            </div>
        </div>

        <div class="flex gap-2">
            <button
                onclick="sincronizarDataJuri()"
                class="flex-1 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-blue-500 dark:hover:bg-blue-600">
                🔄 Sincronizar Tudo
            </button>
            <button
                onclick="testarDataJuri()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                ✅ Testar API
            </button>
        </div>
    </div>

    <!-- ESPO CRM API Card -->
    <div class="rounded-2xl border-t-4 border-green-500 bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">💼 ESPO CRM API</h3>
                <p class="text-sm text-gray-600 dark:text-gray-400">Sistema de CRM e vendas</p>
            </div>
            <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 dark:bg-green-900">
                <span class="text-2xl">💼</span>
            </div>
        </div>

        <div class="space-y-2 mb-4">
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Total de sincronizações:</span>
                <span class="font-semibold text-gray-900 dark:text-white" id="espo-total">0</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Sucesso:</span>
                <span class="font-semibold text-green-600 dark:text-green-400" id="espo-sucesso">0</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Erros:</span>
                <span class="font-semibold text-red-600 dark:text-red-400" id="espo-erros">0</span>
            </div>
            <div class="flex justify-between text-sm">
                <span class="text-gray-600 dark:text-gray-400">Última sincronização:</span>
                <span class="font-semibold text-gray-900 dark:text-white" id="espo-ultima">Nunca</span>
            </div>
        </div>

        <div class="flex gap-2">
            <button
                onclick="sincronizarEspo()"
                class="flex-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:bg-green-500 dark:hover:bg-green-600">
                🔄 Sincronizar Tudo
            </button>
            <button
                onclick="testarEspo()"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                ✅ Testar API
            </button>
        </div>
    </div>
</div>

<!-- Histórico de Sincronizações -->
<div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-gray-800">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">📜 Histórico de Sincronizações</h3>
        <button
            onclick="limparLogs()"
            class="rounded-lg bg-red-100 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-200 dark:bg-red-900 dark:text-red-300 dark:hover:bg-red-800">
            🗑️ Limpar Logs Antigos
        </button>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
            <thead>
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Sistema</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Mensagem</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Data</th>
                </tr>
            </thead>
            <tbody id="logs-tbody" class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                <tr>
                    <td colspan="5" class="px-4 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nenhum log encontrado
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
// Funções de sincronização (mantém as originais do sistema)
function sincronizarDataJuri() {
    alert('Função de sincronização DataJuri (manter implementação original)');
}

function sincronizarEspo() {
    alert('Função de sincronização ESPO (manter implementação original)');
}

function testarDataJuri() {
    alert('Função de teste DataJuri (manter implementação original)');
}

function testarEspo() {
    alert('Função de teste ESPO (manter implementação original)');
}

function limparLogs() {
    if (confirm('Deseja realmente limpar os logs antigos?')) {
        alert('Função de limpeza de logs (manter implementação original)');
    }
}

// Carrega estatísticas ao abrir a aba
document.addEventListener('DOMContentLoaded', function() {
    // Aqui vai a lógica de carregamento de dados (manter implementação original)
});
</script>
