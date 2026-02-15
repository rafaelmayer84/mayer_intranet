<!-- Modal Gerenciar Regras -->
<div id="modal-regras" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <!-- Background overlay -->
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="fecharModalRegras()"></div>

        <!-- Center modal -->
        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

        <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:w-full sm:max-w-4xl sm:align-middle">
            <!-- Header -->
            <div class="border-b border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        📝 Gerenciar Regras de Classificação
                    </h3>
                    <button
                        onclick="fecharModalRegras()"
                        class="text-gray-400 hover:text-gray-500 focus:outline-none">
                        <span class="text-2xl">✕</span>
                    </button>
                </div>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Configure como cada plano de contas deve ser classificado
                </p>
            </div>

            <!-- Content -->
            <div class="bg-white px-6 py-4 dark:bg-gray-800">
                <!-- Ações Rápidas -->
                <div class="mb-4 flex flex-wrap gap-3">
                    <button
                        onclick="importarDoDataJuri()"
                        class="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover-bg-brand-dark">
                        📥 Importar do DataJuri
                    </button>
                    <button
                        onclick="reclassificarTudo()"
                        class="rounded-lg bg-orange-600 px-4 py-2 text-sm font-medium text-white hover:bg-orange-700">
                        🔄 Reclassificar Tudo
                    </button>
                </div>

                <!-- Busca -->
                <div class="mb-4">
                    <input
                        type="text"
                        id="busca-regras"
                        placeholder="🔍 Buscar regra..."
                        onkeyup="buscarRegras()"
                        class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                </div>

                <!-- Tabela de Regras -->
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Código
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Nome do Plano
                                </th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Classificação
                                </th>
                                <th class="px-4 py-3 text-center text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                    Ações
                                </th>
                            </tr>
                        </thead>
                        <tbody id="regras-tbody" class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Carregando regras...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginação -->
                <div class="mt-4 flex items-center justify-between">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        <span id="regras-info">Página 1 de 1</span>
                    </div>
                    <div class="flex gap-2">
                        <button
                            id="btn-prev"
                            onclick="paginaAnterior()"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            ◀
                        </button>
                        <button
                            id="btn-next"
                            onclick="proximaPagina()"
                            class="rounded-lg border border-gray-300 bg-white px-3 py-1 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                            ▶
                        </button>
                    </div>
                </div>

                <!-- Info -->
                <div class="mt-4 rounded-lg bg-blue-50 p-3 dark:bg-blue-900/20">
                    <p class="text-xs text-blue-800 dark:text-blue-200">
                        ℹ️ Movimentos com classificação PENDENTE não aparecerão nos dashboards até serem configurados.
                    </p>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-4 dark:bg-gray-900">
                <button
                    onclick="fecharModalRegras()"
                    class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar Regra -->
<div id="modal-editar-regra" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="fecharModalEditar()"></div>
        <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

        <div class="inline-block transform overflow-hidden rounded-lg bg-white text-left align-bottom shadow-xl transition-all dark:bg-gray-800 sm:my-8 sm:w-full sm:max-w-lg sm:align-middle">
            <div class="bg-white px-6 py-4 dark:bg-gray-800">
                <h3 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">✏️ Editar Classificação</h3>

                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">📋 Código:</label>
                        <input
                            type="text"
                            id="edit-codigo"
                            readonly
                            class="mt-1 w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm dark:border-gray-600 dark:bg-[#385776] dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">📝 Nome:</label>
                        <input
                            type="text"
                            id="edit-nome"
                            class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:outline-none focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">🎯 Classificação:</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="classificacao" value="RECEITA_PF" class="mr-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">RECEITA_PF - Receita Pessoa Física</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="classificacao" value="RECEITA_PJ" class="mr-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">RECEITA_PJ - Receita Pessoa Jurídica</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="classificacao" value="DESPESA" class="mr-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">DESPESA - Despesa Geral</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="classificacao" value="PENDENTE_CLASSIFICACAO" class="mr-2">
                                <span class="text-sm text-gray-700 dark:text-gray-300">PENDENTE - Classificar depois</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-gray-50 px-6 py-4 dark:bg-gray-900 flex gap-3">
                <button
                    onclick="fecharModalEditar()"
                    class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300">
                    Cancelar
                </button>
                <button
                    onclick="salvarRegra()"
                    class="flex-1 rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover-bg-brand-dark">
                    💾 Salvar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let paginaAtual = 1;
let totalPaginas = 1;
let regraEditando = null;

function fecharModalRegras() {
    document.getElementById('modal-regras').classList.add('hidden');
}

function fecharModalEditar() {
    document.getElementById('modal-editar-regra').classList.add('hidden');
}

function carregarRegras(pagina = 1) {
    const busca = document.getElementById('busca-regras').value;
    
    fetch(`/admin/classificacao?page=${pagina}&busca=${busca}`)
        .then(response => response.json())
        .then(data => {
            paginaAtual = data.current_page;
            totalPaginas = data.last_page;
            
            const tbody = document.getElementById('regras-tbody');
            tbody.innerHTML = '';
            
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Nenhuma regra encontrada</td></tr>';
                return;
            }
            
            data.data.forEach(regra => {
                const tr = document.createElement('tr');
                
                const badgeClass = regra.classificacao === 'PENDENTE_CLASSIFICACAO' 
                    ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200'
                    : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
                
                tr.innerHTML = `
                    <td class="px-4 py-3 text-sm font-mono text-gray-900 dark:text-white">${regra.codigo_plano}</td>
                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">${regra.nome_plano || '-'}</td>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${badgeClass}">
                            ${regra.classificacao === 'PENDENTE_CLASSIFICACAO' ? '⚠️' : '✅'} ${regra.classificacao}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <button
                            onclick='editarRegra(${JSON.stringify(regra)})'
                            class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                            ✏️
                        </button>
                    </td>
                `;
                
                tbody.appendChild(tr);
            });
            
            document.getElementById('regras-info').textContent = `Página ${paginaAtual} de ${totalPaginas}`;
            document.getElementById('btn-prev').disabled = paginaAtual === 1;
            document.getElementById('btn-next').disabled = paginaAtual === totalPaginas;
        })
        .catch(error => {
            console.error('Erro ao carregar regras:', error);
        });
}

function buscarRegras() {
    carregarRegras(1);
}

function paginaAnterior() {
    if (paginaAtual > 1) {
        carregarRegras(paginaAtual - 1);
    }
}

function proximaPagina() {
    if (paginaAtual < totalPaginas) {
        carregarRegras(paginaAtual + 1);
    }
}

function editarRegra(regra) {
    regraEditando = regra;
    
    document.getElementById('edit-codigo').value = regra.codigo_plano;
    document.getElementById('edit-nome').value = regra.nome_plano || '';
    
    const radios = document.querySelectorAll('input[name="classificacao"]');
    radios.forEach(radio => {
        radio.checked = radio.value === regra.classificacao;
    });
    
    document.getElementById('modal-editar-regra').classList.remove('hidden');
}

function salvarRegra() {
    const classificacao = document.querySelector('input[name="classificacao"]:checked')?.value;
    
    if (!classificacao) {
        alert('Selecione uma classificação');
        return;
    }
    
    const dados = {
        codigo_plano: document.getElementById('edit-codigo').value,
        nome_plano: document.getElementById('edit-nome').value,
        classificacao: classificacao,
    };
    
    fetch('/admin/classificacao', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(dados)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Regra salva com sucesso!');
            fecharModalEditar();
            carregarRegras(paginaAtual);
            carregarEstatisticas();
        } else {
            alert('Erro: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar regra.');
    });
}
</script>
