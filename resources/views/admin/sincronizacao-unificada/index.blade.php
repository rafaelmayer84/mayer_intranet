@extends('layouts.app')
@section('title', 'Sincronização Unificada')
@section('content')
<div class="space-y-6">
    {{-- HEADER --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">&#x1F504; Sincronização Unificada</h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Gerenciamento centralizado de dados DataJuri</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs text-gray-500">Última sync:</span>
            <span class="rounded bg-gray-100 px-2 py-1 text-xs font-medium dark:bg-gray-700">{{ $lastSyncDataJuri }}</span>
        </div>
    </div>

    {{-- TABS --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8">
            <button onclick="switchTab('dashboard')" id="tab-dashboard" class="tab-btn active border-blue-500 text-blue-600 dark:text-blue-400 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">&#x1F4CA; Dashboard</button>
            <button onclick="switchTab('classificacao')" id="tab-classificacao" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">&#x1F3F7;&#xFE0F; Classificação</button>
            <button onclick="switchTab('historico')" id="tab-historico" class="tab-btn border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">&#x1F4DC; Histórico</button>
        </nav>
    </div>

    {{-- ==================== TAB: DASHBOARD ==================== --}}
    <div id="content-dashboard" class="tab-content">

        {{-- CARDS DE CONTAGEM - 8 MÓDULOS --}}
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
            @foreach($modulosDisponiveis as $key => $mod)
            <div class="rounded-lg bg-white p-4 shadow dark:bg-gray-800 border-l-4
                @if($key == 'Pessoa') border-blue-500
                @elseif($key == 'Processo') border-purple-500
                @elseif($key == 'Fase') border-indigo-500
                @elseif($key == 'Movimento') border-green-500
                @elseif($key == 'Contrato') border-yellow-500
                @elseif($key == 'Atividade') border-orange-500
                @elseif($key == 'HoraTrabalhada') border-pink-500
                @elseif($key == 'OrdemServico') border-red-500
                @elseif($key == 'ContasReceber') border-teal-500
                @else border-gray-500
                @endif">
                <div class="flex items-center justify-between">
                    <span class="text-2xl">{{ $mod['icon'] }}</span>
                    <span class="text-xs text-gray-400">{{ $key }}</span>
                </div>
                <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white" id="count-{{ $mod['table'] }}">
                    {{ number_format($mod['count'], 0, ',', '.') }}
                </p>
                <p class="text-xs text-gray-600 dark:text-gray-400">{{ $mod['label'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- SEÇÃO DATAJURI --}}
        <div class="mt-6 rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">&#x1F5C4;&#xFE0F; DataJuri - Sincronização</h2>
                <button id="btn-refresh-counts" onclick="refreshCounts()" class="text-sm text-blue-600 hover:text-blue-800">&#x1F504; Atualizar contagens</button>
            </div>

            {{-- BOTÕES DE AÇÃO GLOBAL --}}
            <div class="flex flex-wrap gap-3 mb-4">
                <button id="btn-test-datajuri" class="rounded-lg bg-brand px-4 py-2 text-sm text-white hover-bg-brand-dark flex items-center gap-2">
                    <span>&#x1F50C;</span> Testar Conexão
                </button>
                <button id="btn-sync-all" class="rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700 flex items-center gap-2">
                    <span>&#x1F680;</span> Sincronizar TUDO
                </button>
                <button id="btn-cancel-sync" class="hidden rounded-lg bg-red-600 px-4 py-2 text-sm text-white hover:bg-red-700 flex items-center gap-2">
                    <span>&#x26D4;</span> Cancelar Sincronização
                </button>
                <button id="btn-reprocessar" class="rounded-lg bg-purple-600 px-4 py-2 text-sm text-white hover:bg-purple-700 flex items-center gap-2">
                    <span>&#x1F504;</span> Reprocessar Financeiro
                </button>
            </div>

            {{-- BOTÕES POR MÓDULO --}}
            <div class="border-t pt-4 mt-4">
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-3">Sincronizar módulos individuais:</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($modulosDisponiveis as $key => $mod)
                    <button onclick="syncModule('{{ $key }}')"
                            class="btn-sync-module rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600 flex items-center gap-1"
                            data-module="{{ $key }}">
                        <span>{{ $mod['icon'] }}</span> {{ $mod['label'] }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- BARRA DE PROGRESSO --}}
            <div id="progress-container" class="mt-6 hidden">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300" id="progress-label">Sincronizando...</span>
                    <span class="text-sm text-gray-500" id="progress-percent">0%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3 dark:bg-gray-700">
                    <div id="progress-bar" class="bg-brand h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p class="mt-2 text-xs text-gray-500" id="progress-detail"></p>
            </div>

            {{-- LOG EM TEMPO REAL DATAJURI --}}
            <div id="log-container" class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">&#x1F4CB; Log de Execução</span>
                    <button onclick="clearLog('log-output')" class="text-xs text-gray-500 hover:text-gray-700">Limpar</button>
                </div>
                <div id="log-output" class="h-48 overflow-y-auto rounded-lg bg-gray-900 p-3 font-mono text-xs text-green-400">
                    <div class="log-line text-gray-500">[AGUARDANDO] Clique em um botão para iniciar...</div>
                </div>
            </div>
        </div>

        {{-- SEÇÃO ESPOCRM REMOVIDA em 13/02/2026 - substituído por CRM Nativo --}}

                    {{-- LOG EM TEMPO REAL ESPOCRM --}}
            <div id="espo-log-container" class="mt-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">&#x1F4CB; Log de Execução</span>
                    <button onclick="clearLog('espo-log-output')" class="text-xs text-gray-500 hover:text-gray-700">Limpar</button>
                </div>
                <div id="espo-log-output" class="h-48 overflow-y-auto rounded-lg bg-gray-900 p-3 font-mono text-xs text-green-400">
                    <div class="log-line text-gray-500">[AGUARDANDO] Clique em um botão para iniciar...</div>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== TAB: CLASSIFICAÇÃO ==================== --}}
    <div id="content-classificacao" class="tab-content hidden">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4 mb-6">
            <div class="rounded-lg bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 p-4">
                <p class="text-xs text-emerald-600">Receita PF</p>
                <p class="text-2xl font-bold text-emerald-700" id="stat-receita-pf">-</p>
            </div>
            <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 p-4">
                <p class="text-xs text-blue-600">Receita PJ</p>
                <p class="text-2xl font-bold text-blue-700" id="stat-receita-pj">-</p>
            </div>
            <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 p-4">
                <p class="text-xs text-red-600">Despesa</p>
                <p class="text-2xl font-bold text-red-700" id="stat-despesa">-</p>
            </div>
            <div class="rounded-lg bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 p-4">
                <p class="text-xs text-yellow-600">Pendente</p>
                <p class="text-2xl font-bold text-yellow-700" id="stat-pendente">-</p>
            </div>
        </div>
        <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between mb-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Regras de Classificação</h2>
                <div class="flex flex-wrap gap-2">
                    <button id="btn-nova-regra" class="rounded-lg bg-brand px-4 py-2 text-sm text-white hover-bg-brand-dark">+ Nova Regra</button>
                    <button id="btn-importar-datajuri" class="rounded-lg bg-green-600 px-4 py-2 text-sm text-white hover:bg-green-700">&#x1F4E5; Importar do DataJuri</button>
                    <button id="btn-reclassificar" class="rounded-lg bg-purple-600 px-4 py-2 text-sm text-white hover:bg-purple-700">&#x1F504; Reclassificar</button>
                </div>
            </div>
            <div class="flex flex-wrap gap-4 mb-4">
                <input type="text" id="filtro-busca" placeholder="&#x1F50D; Buscar código ou nome..." class="flex-1 min-w-[200px] rounded-lg border px-4 py-2 text-sm dark:bg-gray-700 dark:text-white">
                <select id="filtro-classificacao" class="rounded-lg border px-4 py-2 text-sm dark:bg-gray-700 dark:text-white">
                    <option value="">Todas classificações</option>
                    <option value="RECEITA_PF">Receita PF</option>
                    <option value="RECEITA_PJ">Receita PJ</option>
                    <option value="RECEITA_FINANCEIRA">Receita Financeira</option>
                    <option value="DEDUCAO">Dedução</option>
                    <option value="DESPESA">Despesa</option>
                    <option value="PENDENTE_CLASSIFICACAO">Pendente</option>
                    <option value="IGNORAR">Ignorar</option>
                </select>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Código</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Nome</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Classificação</th>
                            <th class="px-4 py-3 text-center text-xs font-medium uppercase text-gray-500">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="regras-tbody" class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                        <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Carregando...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-sm text-gray-700" id="regras-info">Página 1</p>
                <div class="flex gap-2">
                    <button id="btn-prev" onclick="carregarRegras(paginaAtual-1)" class="rounded border px-3 py-1 text-sm disabled:opacity-50" disabled>◀ Anterior</button>
                    <button id="btn-next" onclick="carregarRegras(paginaAtual+1)" class="rounded border px-3 py-1 text-sm disabled:opacity-50" disabled>Próxima ▶</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ==================== TAB: HISTÓRICO ==================== --}}
    <div id="content-historico" class="tab-content hidden">
        <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">&#x1F4DC; Histórico de Sincronizações</h2>
                <button id="btn-limpar-historico" class="text-sm text-red-600 hover:text-red-800">&#x1F5D1;&#xFE0F; Limpar antigos</button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Tipo</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Registros</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Início</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Duração</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Mensagem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y bg-white dark:bg-gray-800">
                        @forelse($historico as $run)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-4 py-3 text-sm font-medium">{{ $run->tipo }}</td>
                            <td class="px-4 py-3 text-sm">
                                <span class="rounded-full px-2 py-0.5 text-xs
                                    @if($run->status === 'completed') bg-green-100 text-green-800
                                    @elseif($run->status === 'running') bg-blue-100 text-blue-800
                                    @elseif($run->status === 'failed') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $run->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">
                                @if($run->registros_processados)
                                    {{ number_format($run->registros_processados, 0, ',', '.') }}
                                    @if($run->registros_criados) <span class="text-green-600">+{{ $run->registros_criados }}</span> @endif
                                    @if($run->registros_atualizados) <span class="text-blue-600">~{{ $run->registros_atualizados }}</span> @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $run->started_at ? \Carbon\Carbon::parse($run->started_at)->format('d/m H:i') : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500">{{ $run->duracao ? $run->duracao.'s' : '-' }}</td>
                            <td class="px-4 py-3 text-sm text-gray-500 max-w-xs truncate" title="{{ $run->mensagem }}">{{ Str::limit($run->mensagem, 50) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Nenhum registro de sincronização</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE REGRA --}}
<div id="modal-regra" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="fecharModal()"></div>
        <div class="relative w-full max-w-lg transform rounded-lg bg-white shadow-xl transition-all dark:bg-gray-800">
            <div class="border-b px-6 py-4 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="modal-titulo">Nova Regra</h3>
            </div>
            <form id="form-regra" class="px-6 py-4 space-y-4">
                <input type="hidden" id="regra-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Código do Plano *</label>
                    <input type="text" id="regra-codigo" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-gray-700 dark:text-white" placeholder="Ex: 3.01.01.01">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                    <input type="text" id="regra-nome" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-gray-700 dark:text-white" placeholder="Ex: Receita bruta - Contrato PF">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Classificação *</label>
                    <select id="regra-classificacao" required class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-gray-700 dark:text-white">
                        <option value="">Selecione...</option>
                        <option value="RECEITA_PF">RECEITA_PF</option>
                        <option value="RECEITA_PJ">RECEITA_PJ</option>
                        <option value="RECEITA_FINANCEIRA">RECEITA_FINANCEIRA</option>
                        <option value="DESPESA">DESPESA</option>
                        <option value="PENDENTE_CLASSIFICACAO">PENDENTE</option>
                        <option value="IGNORAR">IGNORAR</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Grupo DRE</label>
                    <input type="text" id="regra-grupo-dre" class="w-full rounded-lg border px-3 py-2 text-sm dark:bg-gray-700 dark:text-white" placeholder="Ex: 3.01.01">
                </div>
            </form>
            <div class="border-t px-6 py-4 flex gap-3 dark:border-gray-700">
                <button onclick="fecharModal()" class="flex-1 rounded-lg border px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Cancelar</button>
                <button onclick="salvarRegra()" class="flex-1 rounded-lg bg-brand px-4 py-2 text-sm text-white hover-bg-brand-dark">Salvar</button>
            </div>
        </div>
    </div>
</div>

{{-- TOAST CONTAINER --}}
<div id="toast-container" class="fixed bottom-4 right-4 z-50 space-y-2"></div>

@push('scripts')
<script>
// ==================== VARIÁVEIS GLOBAIS ====================
let paginaAtual = 1, totalPaginas = 1;
let syncInProgress = false;

// ==================== TABS ====================
function switchTab(t) {
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('content-' + t).classList.remove('hidden');
    const btn = document.getElementById('tab-' + t);
    btn.classList.remove('border-transparent', 'text-gray-500');
    btn.classList.add('border-blue-500', 'text-blue-600', 'dark:text-blue-400');
    if (t === 'classificacao') {
        carregarRegras(1);
        carregarStats();
    }
}

// ==================== TOAST ====================
function showToast(msg, type = 'info') {
    const colors = {
        success: 'bg-green-500',
        error: 'bg-red-500',
        info: 'bg-blue-500',
        warning: 'bg-yellow-500'
    };
    const div = document.createElement('div');
    div.className = `${colors[type] || colors.info} text-white px-4 py-3 rounded-lg shadow-lg flex items-center gap-2 animate-fade-in`;
    div.innerHTML = `<span>${msg}</span>`;
    document.getElementById('toast-container').appendChild(div);
    setTimeout(() => {
        div.classList.add('opacity-0', 'transition-opacity');
        setTimeout(() => div.remove(), 300);
    }, 4000);
}

// ==================== LOG ====================
function addLog(message, type = 'info', target = 'log-output') {
    const output = document.getElementById(target);
    if (!output) return;

    const colors = {
        info: 'text-green-400',
        error: 'text-red-400',
        warning: 'text-yellow-400',
        success: 'text-blue-400'
    };

    const time = new Date().toLocaleTimeString('pt-BR');
    const line = document.createElement('div');
    line.className = `log-line ${colors[type] || colors.info}`;
    line.textContent = `[${time}] ${message}`;
    output.appendChild(line);
    output.scrollTop = output.scrollHeight;
}

function clearLog(targetId) {
    const output = document.getElementById(targetId || 'log-output');
    if (output) {
        output.innerHTML = '<div class="log-line text-gray-500">[AGUARDANDO] Log limpo...</div>';
    }
}

// ==================== PROGRESSO ====================
function showProgress(label, percent, detail = '') {
    const container = document.getElementById('progress-container');
    container.classList.remove('hidden');
    document.getElementById('progress-label').textContent = label;
    document.getElementById('progress-percent').textContent = percent + '%';
    document.getElementById('progress-bar').style.width = percent + '%';
    document.getElementById('progress-detail').textContent = detail;
}

function hideProgress() {
    document.getElementById('progress-container').classList.add('hidden');
}

// ==================== REFRESH COUNTS ====================
async function refreshCounts() {
    try {
        const r = await fetch('{{ route("admin.sincronizacao-unificada.index") }}/counts', {
            headers: { 'Accept': 'application/json' }
        });
        const d = await r.json();
        if (d.success && d.counts) {
            for (const [table, count] of Object.entries(d.counts)) {
                const el = document.getElementById('count-' + table);
                if (el) el.textContent = count.toLocaleString('pt-BR');
            }
            showToast('Contagens atualizadas!', 'success');
        }
    } catch (e) {
        showToast('Erro ao atualizar contagens', 'error');
    }
}

// ==================== SYNC MODULE ====================
async function syncModule(modulo) {
    if (syncInProgress) {
        showToast('Aguarde a sincronização atual terminar', 'warning');
        return;
    }

    syncInProgress = true;
    const btn = document.querySelector(`[data-module="${modulo}"]`);
    if (btn) btn.disabled = true;

    addLog(`Iniciando sincronização do módulo: ${modulo}`, 'info');
    showProgress(`Sincronizando ${modulo}...`, 10);

    try {
        const r = await fetch(`{{ url('admin/sincronizacao-unificada/sync') }}/${modulo}`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json'
            }
        });
        const d = await r.json();

        showProgress(`${modulo} concluído`, 100);

        if (d.success) {
            addLog(`✅ ${d.message}`, 'success');
            showToast(d.message, 'success');
            setTimeout(() => refreshCounts(), 500);
        } else {
            addLog(`❌ ${d.message}`, 'error');
            showToast(d.message, 'error');
        }
    } catch (e) {
        addLog(`❌ Erro: ${e.message}`, 'error');
        showToast('Erro na sincronização', 'error');
    } finally {
        syncInProgress = false;
        if (btn) btn.disabled = false;
        setTimeout(hideProgress, 2000);
    }
}

// ==================== EVENT LISTENERS ====================
document.addEventListener('DOMContentLoaded', function() {
    // Teste DataJuri
    document.getElementById('btn-test-datajuri').addEventListener('click', async () => {
        addLog('Testando conexão com DataJuri...', 'info');
        try {
            const r = await fetch('{{ route("admin.sincronizacao-unificada.smoke-test") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const d = await r.json();
            if (d.success) {
                addLog('✅ ' + d.message, 'success');
                if (d.results) {
                    addLog(`Pessoas: ${d.results.pessoa_count || '-'} | Movimentos: ${d.results.movimento_count || '-'}`, 'info');
                }
                showToast(d.message, 'success');
            } else {
                addLog('❌ ' + d.message, 'error');
                showToast(d.message, 'error');
            }
        } catch (e) {
            addLog('❌ Erro: ' + e.message, 'error');
            showToast('Erro ao testar conexão', 'error');
        }
    });

    // Sync ALL - Modulo a modulo com progresso visual (FIX 07/02/2026)
    let syncCancelled = false;
    const modulosParaSync = @json(array_keys($modulosDisponiveis));

    document.getElementById('btn-sync-all').addEventListener('click', async () => {
        if (syncInProgress) {
            showToast('Aguarde a sincronização atual terminar', 'warning');
            return;
        }
        if (!confirm('Sincronizar TODOS os módulos? Cada módulo será processado individualmente com progresso visual.')) return;

        syncInProgress = true;
        syncCancelled = false;
        document.getElementById('btn-sync-all').classList.add('hidden');
        document.getElementById('btn-cancel-sync').classList.remove('hidden');

        const modulos = modulosParaSync;
        const total = modulos.length;
        let tProc = 0, tCri = 0, tAtu = 0, tErr = 0;

        addLog('&#x1F680; Sincronização iniciada: ' + total + ' módulos', 'info');

        for (let i = 0; i < modulos.length; i++) {
            if (syncCancelled) {
                addLog('&#x26D4; Cancelado pelo usuário após ' + i + ' de ' + total + ' módulos', 'warning');
                break;
            }
            const mod = modulos[i];
            const pct = Math.round((i / total) * 100);
            showProgress('[' + (i+1) + '/' + total + '] Sincronizando ' + mod + '...', pct);
            addLog('&#x1F4E6; [' + (i+1) + '/' + total + '] Sincronizando ' + mod + '...', 'info');

            try {
                const r = await fetch('{{ url("admin/sincronizacao-unificada/sync") }}/' + mod, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                });
                const d = await r.json();
                if (d.success) {
                    addLog('✅ ' + d.message, 'success');
                    if (d.results) {
                        tProc += d.results.processados || 0;
                        tCri  += d.results.criados || 0;
                        tAtu  += d.results.atualizados || 0;
                        tErr  += d.results.erros || 0;
                    }
                } else {
                    addLog('❌ ' + mod + ': ' + d.message, 'error');
                    tErr++;
                }
            } catch (e) {
                addLog('❌ ' + mod + ': ' + e.message, 'error');
                tErr++;
            }
        }

        showProgress('Concluído!', 100);
        if (!syncCancelled) {
            const msg = tProc + ' processados, ' + tCri + ' criados, ' + tAtu + ' atualizados' + (tErr > 0 ? ', ' + tErr + ' erros' : '');
            addLog('&#x1F3C1; Sincronização completa: ' + msg, 'success');
            showToast('Concluído: ' + msg, 'success');
        }
        refreshCounts();
        syncInProgress = false;
        document.getElementById('btn-sync-all').classList.remove('hidden');
        document.getElementById('btn-cancel-sync').classList.add('hidden');
        setTimeout(hideProgress, 5000);
    });

    // Cancelar sincronização em andamento
    document.getElementById('btn-cancel-sync').addEventListener('click', () => {
        syncCancelled = true;
        showToast('Cancelando após módulo atual finalizar...', 'warning');
        addLog('&#x23F3; Cancelando... aguardando módulo atual finalizar', 'warning');
    });

    // Reprocessar Financeiro
    document.getElementById('btn-reprocessar').addEventListener('click', async () => {
        if (syncInProgress) {
            showToast('Aguarde a sincronização atual terminar', 'warning');
            return;
        }

        if (!confirm('Reprocessar todos os movimentos financeiros? Registros obsoletos serão removidos.')) return;

        syncInProgress = true;
        addLog('&#x1F504; Iniciando reprocessamento financeiro...', 'info');
        showProgress('Reprocessando movimentos...', 10);

        try {
            const r = await fetch('{{ route("admin.sincronizacao-unificada.reprocessar-financeiro") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const d = await r.json();

            showProgress('Concluído!', 100);

            if (d.success) {
                addLog('✅ ' + d.message, 'success');
                showToast(d.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                addLog('❌ ' + d.message, 'error');
                showToast(d.message, 'error');
            }
        } catch (e) {
            addLog('❌ Erro: ' + e.message, 'error');
            showToast('Erro no reprocessamento', 'error');
        } finally {
            syncInProgress = false;
            setTimeout(hideProgress, 3000);
        }
    });

    /* ESPOCRM REMOVIDO 13/02/2026
    document.getElementById('btn-test-espocrm').addEventListener('click', async () => {
        addLog('Testando conexão com ESPO CRM...', 'info', 'espo-log-output');
        try {
            const r = await fetch('#espocrm-removido', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const d = await r.json();
            if (d.success) {
                addLog('✅ ' + d.message, 'success', 'espo-log-output');
                showToast(d.message, 'success');
            } else {
                addLog('❌ ' + d.message, 'error', 'espo-log-output');
                showToast(d.message, 'error');
            }
        } catch (e) {
            addLog('❌ Erro: ' + e.message, 'error', 'espo-log-output');
            showToast('Erro ao testar ESPOCRM', 'error');
        }
    });

    // ESPOCRM - Sincronizar
    document.getElementById('btn-sync-espocrm').addEventListener('click', async () => {
        if (syncInProgress) {
            showToast('Aguarde a sincronização atual terminar', 'warning');
            return;
        }

        syncInProgress = true;
        addLog('&#x1F504; Iniciando sincronização ESPO CRM...', 'info', 'espo-log-output');
        showToast('Sincronizando ESPO CRM...', 'info');

        try {
            const r = await fetch('#espocrm-removido', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const d = await r.json();

            if (d.success) {
                addLog('✅ ' + d.message, 'success', 'espo-log-output');
                showToast(d.message, 'success');
                setTimeout(() => location.reload(), 2000);
            } else {
                addLog('❌ ' + d.message, 'error', 'espo-log-output');
                showToast(d.message, 'error');
            }
        } catch (e) {
            addLog('❌ Erro: ' + e.message, 'error', 'espo-log-output');
            showToast('Erro ao sincronizar ESPOCRM', 'error');
        } finally {
            syncInProgress = false;
        }
    });
    ESPOCRM JS REMOVIDO */

    // Limpar histórico
    document.getElementById('btn-limpar-historico').addEventListener('click', async () => {
        if (!confirm('Limpar registros de sincronização com mais de 30 dias?')) return;
        try {
            const r = await fetch('{{ route("admin.sincronizacao-unificada.clear-history") }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
            });
            const d = await r.json();
            showToast(d.message, d.success ? 'success' : 'error');
            if (d.success) setTimeout(() => location.reload(), 1000);
        } catch (e) {
            showToast('Erro ao limpar histórico', 'error');
        }
    });

    // Classificação
    document.getElementById('btn-nova-regra').addEventListener('click', novaRegra);
    document.getElementById('btn-importar-datajuri').addEventListener('click', importarRegras);
    document.getElementById('btn-reclassificar').addEventListener('click', reclassificar);
    document.getElementById('filtro-busca').addEventListener('input', () => carregarRegras(1));
    document.getElementById('filtro-classificacao').addEventListener('change', () => carregarRegras(1));
});

// ==================== CLASSIFICAÇÃO ====================
async function carregarStats() {
    try {
        const r = await fetch('{{ route("admin.sincronizacao-unificada.classificacao.stats") }}');
        const d = await r.json();
        document.getElementById('stat-receita-pf').textContent = d.RECEITA_PF || '0';
        document.getElementById('stat-receita-pj').textContent = d.RECEITA_PJ || '0';
        document.getElementById('stat-despesa').textContent = d.DESPESA || '0';
        document.getElementById('stat-pendente').textContent = d.PENDENTE_CLASSIFICACAO || '0';
    } catch (e) {}
}

async function carregarRegras(p = 1) {
    const busca = document.getElementById('filtro-busca').value;
    const classif = document.getElementById('filtro-classificacao').value;
    const url = new URL('{{ route("admin.sincronizacao-unificada.classificacao.index") }}');
    url.searchParams.set('page', p);
    if (busca) url.searchParams.set('busca', busca);
    if (classif) url.searchParams.set('classificacao', classif);

    try {
        const r = await fetch(url);
        const d = await r.json();
        paginaAtual = d.current_page;
        totalPaginas = d.last_page;
        const tbody = document.getElementById('regras-tbody');
        tbody.innerHTML = '';

        if (d.data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="px-4 py-8 text-center text-sm text-gray-500">Nenhuma regra encontrada</td></tr>';
            return;
        }

        d.data.forEach(r => {
            const tr = document.createElement('tr');
            tr.className = 'hover:bg-gray-50 dark:hover:bg-gray-700';
            const classColors = {
                'RECEITA_PF': 'bg-emerald-100 text-emerald-800',
                'RECEITA_PJ': 'bg-blue-100 text-blue-800',
                'DESPESA': 'bg-red-100 text-red-800',
                'PENDENTE_CLASSIFICACAO': 'bg-yellow-100 text-yellow-800',
                'IGNORAR': 'bg-gray-100 text-gray-500'
            };
            tr.innerHTML = `
                <td class="px-4 py-3 text-sm font-mono">${r.codigo_plano}</td>
                <td class="px-4 py-3 text-sm">${r.nome_plano || '-'}</td>
                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs ${classColors[r.classificacao] || 'bg-gray-100'}">${r.classificacao}</span></td>
                <td class="px-4 py-3 text-center">
                    <button onclick='editarRegra(${JSON.stringify(r)})' class="text-blue-600 hover:text-blue-800 mr-2">&#x270F;&#xFE0F;</button>
                    <button onclick='excluirRegra(${r.id})' class="text-red-600 hover:text-red-800">&#x1F5D1;&#xFE0F;</button>
                </td>`;
            tbody.appendChild(tr);
        });

        document.getElementById('regras-info').textContent = `Página ${paginaAtual} de ${totalPaginas} (${d.total} regras)`;
        document.getElementById('btn-prev').disabled = paginaAtual <= 1;
        document.getElementById('btn-next').disabled = paginaAtual >= totalPaginas;
    } catch (e) {
        showToast('Erro ao carregar regras', 'error');
    }
}

function novaRegra() {
    document.getElementById('modal-titulo').textContent = 'Nova Regra';
    document.getElementById('regra-id').value = '';
    document.getElementById('regra-codigo').value = '';
    document.getElementById('regra-codigo').disabled = false;
    document.getElementById('regra-nome').value = '';
    document.getElementById('regra-classificacao').value = '';
    document.getElementById('regra-grupo-dre').value = '';
    document.getElementById('modal-regra').classList.remove('hidden');
}

function editarRegra(r) {
    document.getElementById('modal-titulo').textContent = 'Editar Regra';
    document.getElementById('regra-id').value = r.id;
    document.getElementById('regra-codigo').value = r.codigo_plano;
    document.getElementById('regra-codigo').disabled = true;
    document.getElementById('regra-nome').value = r.nome_plano || '';
    document.getElementById('regra-classificacao').value = r.classificacao;
    document.getElementById('regra-grupo-dre').value = r.grupo_dre || '';
    document.getElementById('modal-regra').classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('modal-regra').classList.add('hidden');
}

async function salvarRegra() {
    const id = document.getElementById('regra-id').value;
    const dados = {
        codigo_plano: document.getElementById('regra-codigo').value,
        nome_plano: document.getElementById('regra-nome').value,
        classificacao: document.getElementById('regra-classificacao').value,
        grupo_dre: document.getElementById('regra-grupo-dre').value,
        ativo: true
    };

    if (!dados.codigo_plano || !dados.classificacao) {
        showToast('Preencha os campos obrigatórios', 'error');
        return;
    }

    try {
        const url = id
            ? '{{ url("admin/sincronizacao-unificada/classificacao") }}/' + id
            : '{{ route("admin.sincronizacao-unificada.classificacao.store") }}';
        const r = await fetch(url, {
            method: id ? 'PUT' : 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify(dados)
        });
        const d = await r.json();

        if (d.success) {
            showToast('Regra salva!', 'success');
            fecharModal();
            carregarRegras(paginaAtual);
            carregarStats();
        } else {
            showToast(d.message, 'error');
        }
    } catch (e) {
        showToast('Erro ao salvar', 'error');
    }
}

async function excluirRegra(id) {
    if (!confirm('Excluir esta regra?')) return;
    try {
        const r = await fetch('{{ url("admin/sincronizacao-unificada/classificacao") }}/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const d = await r.json();
        if (d.success) {
            showToast('Regra excluída!', 'success');
            carregarRegras(paginaAtual);
            carregarStats();
        }
    } catch (e) {
        showToast('Erro ao excluir', 'error');
    }
}

async function importarRegras() {
    if (!confirm('Importar planos de conta do DataJuri?')) return;
    showToast('Importando...', 'info');
    try {
        const r = await fetch('{{ route("admin.sincronizacao-unificada.classificacao.importar") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const d = await r.json();
        showToast(d.message, d.success ? 'success' : 'error');
        carregarRegras(1);
        carregarStats();
    } catch (e) {
        showToast('Erro ao importar', 'error');
    }
}

async function reclassificar() {
    if (!confirm('Reclassificar todos os movimentos com base nas regras atuais?')) return;
    showToast('Reclassificando...', 'info');
    try {
        const r = await fetch('{{ route("admin.sincronizacao-unificada.classificacao.reclassificar") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        });
        const d = await r.json();
        showToast(d.message, d.success ? 'success' : 'error');
    } catch (e) {
        showToast('Erro ao reclassificar', 'error');
    }
}
</script>

<style>
@keyframes fade-in {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}
.animate-fade-in {
    animation: fade-in 0.3s ease-out;
}
</style>
@endpush
@endsection