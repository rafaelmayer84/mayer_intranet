<!-- Quadros de Prévia de Dados -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <!-- Prévia da API -->
    <div class="rounded-2xl border-l-4 border-blue-500 bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">📊 Prévia da API (Movimentos)</h3>
            <button
                onclick="atualizarPreviaApi()"
                class="rounded-lg bg-blue-100 px-3 py-1.5 text-sm font-medium text-blue-700 hover:bg-blue-200 dark:bg-blue-900 dark:text-blue-300">
                Atualizar
            </button>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Últimos movimentos sincronizados da API DataJuri
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">API ID</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">DATA</th>
                        <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">VALOR</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">CÓDIGO</th>
                    </tr>
                </thead>
                <tbody id="previa-api-tbody" class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr>
                        <td colspan="4" class="px-2 py-4 text-center text-gray-500 dark:text-gray-400">
                            Carregando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-center" id="previa-api-count">
            Registros 0-0 de 0
        </div>
    </div>

    <!-- Banco de Dados -->
    <div class="rounded-2xl border-l-4 border-green-500 bg-white p-6 shadow-sm dark:bg-gray-800">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">💾 Banco (Movimentos)</h3>
            <button
                onclick="atualizarPreviaBanco()"
                class="rounded-lg bg-green-100 px-3 py-1.5 text-sm font-medium text-green-700 hover:bg-green-200 dark:bg-green-900 dark:text-green-300">
                Atualizar
            </button>
        </div>

        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            Movimentos já processados no banco de dados
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="border-b border-gray-200 dark:border-gray-700">
                    <tr>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">ID</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">DATA</th>
                        <th class="px-2 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400">VALOR</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">CÓD</th>
                        <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400">CLASS.</th>
                    </tr>
                </thead>
                <tbody id="previa-banco-tbody" class="divide-y divide-gray-100 dark:divide-gray-700">
                    <tr>
                        <td colspan="5" class="px-2 py-4 text-center text-gray-500 dark:text-gray-400">
                            Carregando...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400 text-center" id="previa-banco-count">
            Registros 0-0 de 0
        </div>
    </div>
</div>

<!-- Info Box -->
<div class="rounded-lg bg-blue-50 p-4 mb-6 dark:bg-blue-900/20">
    <div class="flex">
        <div class="flex-shrink-0">
            <span class="text-2xl">💡</span>
        </div>
        <div class="ml-3">
            <p class="text-sm text-blue-800 dark:text-blue-200">
                Use esses quadros para conferir se a sincronização está funcionando e se as classificações estão corretas.
                Movimentos sem classificação não aparecerão nos dashboards.
            </p>
        </div>
    </div>
</div>

<!-- Card de Classificação -->
<div class="rounded-2xl bg-white p-6 shadow-sm dark:bg-gray-800 mb-6">
    <div class="flex items-center justify-between cursor-pointer" onclick="toggleCard('classificacao')">
        <div>
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🎯 Classificação de Planos de Contas</h3>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                Configure se cada plano de contas representa uma RECEITA (PF/PJ) ou DESPESA
            </p>
        </div>
        <span id="icon-classificacao" class="text-gray-400 text-xl">▼</span>
    </div>

    <div id="content-classificacao" class="mt-6">
        <!-- Status -->
        <div class="rounded-lg bg-yellow-50 p-4 mb-4 dark:bg-yellow-900/20">
            <div class="flex">
                <div class="flex-shrink-0">
                    <span class="text-2xl">⚠️</span>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                        Status: <span id="status-pendentes" class="font-semibold">Carregando...</span> movimentos sem classificação
                    </p>
                </div>
            </div>
        </div>

        <!-- Botões de Ação -->
        <div class="flex flex-wrap gap-3 mb-4">
            <button
                onclick="importarDoDataJuri()"
                class="flex-1 min-w-[200px] rounded-lg bg-blue-600 px-4 py-3 text-sm font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                📥 Importar do DataJuri
            </button>
            <button
                onclick="reclassificarTudo()"
                class="flex-1 min-w-[200px] rounded-lg bg-orange-600 px-4 py-3 text-sm font-medium text-white hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-orange-500">
                🔄 Reclassificar Tudo
            </button>
        </div>

        <button
            onclick="abrirModalRegras()"
            class="w-full rounded-lg border-2 border-dashed border-gray-300 px-4 py-3 text-sm font-medium text-gray-700 hover:border-gray-400 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:border-gray-500 dark:hover:bg-gray-800">
            📝 Gerenciar Regras
        </button>
    </div>
</div>

<!-- Cards Futuros (Em Breve) -->
<div class="space-y-4">
    <!-- Mapeamento de Campos -->
    <div class="rounded-2xl bg-gray-100 p-6 shadow-sm dark:bg-gray-900 opacity-60">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-400">🔜 Mapeamento de Campos</h3>
                <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">
                    Em breve - Para próximos módulos
                </p>
            </div>
            <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                Bloqueado
            </span>
        </div>
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-500">
            Aqui você poderá mapear campos personalizados entre os sistemas.
        </p>
    </div>

    <!-- Regras de Transformação -->
    <div class="rounded-2xl bg-gray-100 p-6 shadow-sm dark:bg-gray-900 opacity-60">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-400">🔜 Regras de Transformação</h3>
                <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">
                    Em breve - Para próximos módulos
                </p>
            </div>
            <span class="rounded-full bg-gray-200 px-3 py-1 text-xs font-medium text-gray-700 dark:bg-gray-700 dark:text-gray-400">
                Bloqueado
            </span>
        </div>
    </div>
</div>

<script>
// Toggle de cards expansíveis
function toggleCard(cardId) {
    const content = document.getElementById('content-' + cardId);
    const icon = document.getElementById('icon-' + cardId);
    
    if (content.classList.contains('hidden')) {
        content.classList.remove('hidden');
        icon.textContent = '▼';
    } else {
        content.classList.add('hidden');
        icon.textContent = '▶';
    }
}

// Atualizar prévias
function atualizarPreviaApi() {
    // Implementar chamada AJAX para buscar dados da API
    console.log('Atualizando prévia da API...');
}

function atualizarPreviaBanco() {
    // Implementar chamada AJAX para buscar dados do banco
    console.log('Atualizando prévia do banco...');
}

// Ações de classificação
function importarDoDataJuri() {
    if (confirm('Importar planos de contas do DataJuri?\n\nIsso criará regras pendentes para todos os códigos encontrados nos movimentos.')) {
        fetch('/admin/classificacao/importar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                carregarEstatisticas();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao importar planos de contas.');
        });
    }
}

function reclassificarTudo() {
    if (confirm('Reclassificar todos os movimentos?\n\nIsso aplicará as regras configuradas em todos os movimentos pendentes.')) {
        fetch('/admin/classificacao/reclassificar', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                carregarEstatisticas();
                atualizarPreviaBanco();
            } else {
                alert('Erro: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao reclassificar movimentos.');
        });
    }
}

function abrirModalRegras() {
    document.getElementById('modal-regras').classList.remove('hidden');
    carregarRegras();
}

function carregarEstatisticas() {
    fetch('/admin/classificacao/estatisticas')
        .then(response => response.json())
        .then(data => {
            document.getElementById('status-pendentes').textContent = data.movimentos_pendentes;
        })
        .catch(error => console.error('Erro ao carregar estatísticas:', error));
}

// Carregar estatísticas ao abrir a aba
document.addEventListener('DOMContentLoaded', function() {
    carregarEstatisticas();
});
</script>
