@extends('layouts.app')
@section('title', 'CRM - Carteira')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Carteira de Clientes</h1>
            <p class="text-sm text-gray-500 mt-1">Visão gerencial de toda a base CRM</p>
        </div>
        <a href="{{ route('crm.pipeline') }}" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">
            Ver Pipeline →
        </a>
    </div>

    {{-- Cards KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase">Total</p>
            <p class="text-2xl font-bold text-[#1B334A]">{{ number_format($totals['total']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-green-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'ativo']) }}'">
            <p class="text-xs text-gray-500 uppercase">Ativos</p>
            <p class="text-2xl font-bold text-green-600">{{ number_format($totals['ativos']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-yellow-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'adormecido']) }}'">
            <p class="text-xs text-gray-500 uppercase">Adormecidos</p>
            <p class="text-2xl font-bold text-yellow-600">{{ number_format($totals['adormecido']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-gray-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'arquivado']) }}'">
            <p class="text-xs text-gray-500 uppercase">Arquivados</p>
            <p class="text-2xl font-bold text-gray-500">{{ number_format($totals['arquivado']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 cursor-pointer hover:ring-2 hover:ring-blue-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'onboarding']) }}'">
            <p class="text-xs text-gray-500 uppercase">Onboarding</p>
            <p class="text-2xl font-bold text-blue-600">{{ number_format($totals['onboarding']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 border-red-200 cursor-pointer hover:ring-2 hover:ring-red-300" onclick="window.location='{{ route('crm.carteira', ['lifecycle' => 'ativo', 'sem_contato_dias' => 30]) }}'">
            <p class="text-xs text-red-500 uppercase">Sem Contato 30d</p>
            <p class="text-2xl font-bold text-red-600">{{ number_format($totals['sem_contato_30d']) }}</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('crm.carteira') }}" class="bg-white rounded-lg shadow-sm border p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar nome, doc, email..."
                   class="border rounded-lg px-3 py-2 text-sm col-span-2">
            <select name="kind" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os tipos</option>
                <option value="client" {{ request('kind') === 'client' ? 'selected' : '' }}>Clientes</option>
                <option value="prospect" {{ request('kind') === 'prospect' ? 'selected' : '' }}>Prospects</option>
            </select>
            <select name="lifecycle" class="border rounded-lg px-3 py-2 text-sm">
                <option value="todos" {{ request('lifecycle') === 'todos' ? 'selected' : '' }}>Todos os ciclos</option>
                @foreach(['onboarding','ativo','adormecido','arquivado','risco'] as $lc)
                    <option value="{{ $lc }}" {{ (request('lifecycle', 'ativo')) === $lc ? 'selected' : '' }}>{{ ucfirst($lc) }}</option>
                @endforeach
            </select>
            <select name="owner_user_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os responsáveis</option>
                @can('admin')
                <option value="sem_responsavel" {{ request('owner_user_id') === 'sem_responsavel' ? 'selected' : '' }}>— Sem responsável</option>
                @endcan
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('owner_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <select name="segment" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os segmentos</option>
                @foreach($segments as $seg)
                    <option value="{{ $seg }}" {{ request('segment') === $seg ? 'selected' : '' }}>{{ $seg }}</option>
                @endforeach
            </select>
            <div class="flex gap-2">
                <select name="sem_contato_dias" class="border rounded-lg px-3 py-2 text-sm flex-1">
                    <option value="">Sem contato</option>
                    <option value="7" {{ request('sem_contato_dias') == '7' ? 'selected' : '' }}>+7 dias</option>
                    <option value="15" {{ request('sem_contato_dias') == '15' ? 'selected' : '' }}>+15 dias</option>
                    <option value="30" {{ request('sem_contato_dias') == '30' ? 'selected' : '' }}>+30 dias</option>
                    <option value="60" {{ request('sem_contato_dias') == '60' ? 'selected' : '' }}>+60 dias</option>
                    <option value="90" {{ request('sem_contato_dias') == '90' ? 'selected' : '' }}>+90 dias</option>
                </select>
                <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Filtrar</button>
            </div>
        </div>
        <div class="flex gap-3 mt-2">
            <label class="flex items-center gap-1 text-sm">
                <input type="checkbox" name="overdue_only" value="1" {{ request()->boolean('overdue_only') ? 'checked' : '' }}>
                Ação vencida
            </label>
        </div>
    </form>

    {{-- Barra de ação em massa (admin only) --}}
    @if(auth()->user()->isAdmin())
    <div id="bulk-bar" class="hidden mb-4 bg-[#1B334A] text-white rounded-lg px-4 py-3 flex items-center gap-4 flex-wrap">
        <span class="text-sm font-medium"><span id="bulk-count">0</span> conta(s) selecionada(s)</span>
        <div class="flex items-center gap-2 flex-1">
            <label class="text-sm text-gray-300 whitespace-nowrap">Atribuir responsável:</label>
            <select id="bulk-owner-select" class="border border-gray-500 bg-[#385776] text-white rounded-lg px-3 py-1.5 text-sm min-w-[180px]">
                <option value="">— Remover responsável</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <button onclick="openBulkModal()" class="px-4 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded-lg text-sm font-medium transition">
                Aplicar
            </button>
        </div>
        <button onclick="clearSelection()" class="text-gray-300 hover:text-white text-sm underline ml-auto">
            Limpar seleção
        </button>
    </div>
    @endif

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    @if(auth()->user()->isAdmin())
                    <th class="px-4 py-3 w-10">
                        <input type="checkbox" id="check-all" title="Selecionar todos" class="w-4 h-4 cursor-pointer">
                    </th>
                    @endif
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Nome</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Tipo</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Responsável</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Último contato</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Próxima ação</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600">Saúde</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Ciclo</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600">Segmento</th>
                    <th class="text-center px-4 py-3 font-medium text-gray-600">Opps</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y" id="accounts-tbody">
                @forelse($accounts as $acc)
                <tr class="hover:bg-gray-50 account-row" data-id="{{ $acc->id }}">
                    @if(auth()->user()->isAdmin())
                    <td class="px-4 py-3">
                        <input type="checkbox" class="row-check w-4 h-4 cursor-pointer" value="{{ $acc->id }}">
                    </td>
                    @endif
                    <td class="px-4 py-3">
                        <a href="{{ route('crm.accounts.show', $acc->id) }}" class="font-medium text-[#385776] hover:underline">
                            {{ $acc->name }}
                        </a>
                        @if($acc->doc_digits)
                            <span class="text-xs text-gray-400 ml-1">{{ strlen($acc->doc_digits) === 11 ? 'PF' : 'PJ' }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded text-xs {{ $acc->kind === 'client' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $acc->kind === 'client' ? 'Cliente' : 'Prospect' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $acc->owner?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($acc->last_touch_at)
                            <span class="{{ $acc->last_touch_at->diffInDays(now()) > 30 ? 'text-red-500' : '' }}">
                                {{ $acc->last_touch_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-gray-400">Nunca</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($acc->next_touch_at)
                            <span class="{{ $acc->next_touch_at->isPast() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                {{ $acc->next_touch_at->format('d/m/Y') }}
                                @if($acc->next_touch_at->isPast())
                                    <span class="text-xs">({{ $acc->next_touch_at->diffInDays(now()) }}d atraso)</span>
                                @endif
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($acc->health_score !== null)
                            <span class="px-2 py-0.5 rounded text-xs font-medium
                                {{ $acc->health_score >= 70 ? 'bg-green-100 text-green-700' : ($acc->health_score >= 40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ $acc->health_score }}
                            </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $lcColors = ['onboarding' => 'bg-blue-100 text-blue-700', 'ativo' => 'bg-green-100 text-green-700', 'adormecido' => 'bg-yellow-100 text-yellow-700', 'risco' => 'bg-red-100 text-red-700', 'arquivado' => 'bg-gray-200 text-gray-600'];
                        @endphp
                        <span class="px-2 py-0.5 rounded text-xs {{ $lcColors[$acc->lifecycle] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($acc->lifecycle) }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($acc->segment)
                            <span class="px-2 py-0.5 rounded text-xs bg-purple-100 text-purple-700">{{ $acc->segment }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $acc->open_opps_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('crm.accounts.show', $acc->id) }}" class="text-[#385776] hover:underline text-xs">360 →</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ auth()->user()->isAdmin() ? 11 : 10 }}" class="px-4 py-8 text-center text-gray-400">Nenhum registro encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginação --}}
    <div class="mt-4">{{ $accounts->links() }}</div>
</div>

{{-- Modal de confirmação bulk assign (admin only) --}}
@if(auth()->user()->isAdmin())
<div id="bulk-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black bg-opacity-50">
    <div class="bg-white rounded-xl shadow-xl p-6 w-full max-w-md mx-4">
        <h2 class="text-lg font-bold text-[#1B334A] mb-2">Confirmar atribuição em massa</h2>
        <p class="text-sm text-gray-600 mb-4" id="bulk-modal-text"></p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeBulkModal()" class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">
                Cancelar
            </button>
            <button onclick="executeBulkAssign()" id="bulk-confirm-btn" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] font-medium">
                Confirmar
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const bulkUrl = "{{ route('crm.carteira.bulk-assign') }}";
    const csrfToken = "{{ csrf_token() }}";

    let selectedIds = new Set();

    // Checkbox "selecionar todos"
    document.getElementById('check-all').addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => {
            cb.checked = this.checked;
            if (this.checked) {
                selectedIds.add(parseInt(cb.value));
            } else {
                selectedIds.delete(parseInt(cb.value));
            }
        });
        updateBulkBar();
    });

    // Checkboxes individuais
    document.addEventListener('change', function (e) {
        if (e.target.classList.contains('row-check')) {
            const id = parseInt(e.target.value);
            if (e.target.checked) {
                selectedIds.add(id);
            } else {
                selectedIds.delete(id);
                document.getElementById('check-all').checked = false;
            }
            updateBulkBar();
        }
    });

    function updateBulkBar() {
        const bar = document.getElementById('bulk-bar');
        const countEl = document.getElementById('bulk-count');
        countEl.textContent = selectedIds.size;
        if (selectedIds.size > 0) {
            bar.classList.remove('hidden');
            bar.classList.add('flex');
        } else {
            bar.classList.add('hidden');
            bar.classList.remove('flex');
        }
    }

    window.clearSelection = function () {
        selectedIds.clear();
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
        document.getElementById('check-all').checked = false;
        updateBulkBar();
    };

    window.openBulkModal = function () {
        if (selectedIds.size === 0) return;
        const sel = document.getElementById('bulk-owner-select');
        const ownerName = sel.value ? sel.options[sel.selectedIndex].text : 'Nenhum (remover responsável)';
        document.getElementById('bulk-modal-text').textContent =
            'Atribuir ' + selectedIds.size + ' conta(s) para: ' + ownerName + '. Esta ação não pode ser desfeita nesta tela.';
        document.getElementById('bulk-modal').classList.remove('hidden');
    };

    window.closeBulkModal = function () {
        document.getElementById('bulk-modal').classList.add('hidden');
    };

    window.executeBulkAssign = function () {
        const btn = document.getElementById('bulk-confirm-btn');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        const ownerUserId = document.getElementById('bulk-owner-select').value;

        fetch(bulkUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                account_ids: Array.from(selectedIds),
                owner_user_id: ownerUserId || null,
            }),
        })
        .then(r => r.json())
        .then(data => {
            closeBulkModal();
            if (data.success) {
                // Atualiza células de responsável na tabela sem reload (fallback: reload)
                const ownerName = ownerUserId
                    ? document.getElementById('bulk-owner-select').options[document.getElementById('bulk-owner-select').selectedIndex].text
                    : '—';
                selectedIds.forEach(id => {
                    const row = document.querySelector('.account-row[data-id="' + id + '"]');
                    if (row) {
                        // Coluna responsável: 4a td (índice 3 sem checkbox = 3, com checkbox = 4)
                        const tds = row.querySelectorAll('td');
                        const ownerTd = tds[3]; // com checkbox admin sempre está na posição 3
                        if (ownerTd) ownerTd.textContent = ownerName;
                    }
                });
                clearSelection();
                // Toast
                showToast(data.message, 'success');
            } else {
                showToast('Erro ao salvar. Tente novamente.', 'error');
            }
        })
        .catch(() => {
            closeBulkModal();
            showToast('Erro de comunicação com o servidor.', 'error');
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = 'Confirmar';
        });
    };

    function showToast(msg, type) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-6 right-6 z-50 px-5 py-3 rounded-lg text-white text-sm shadow-lg transition-opacity '
            + (type === 'success' ? 'bg-green-600' : 'bg-red-600');
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 400); }, 3500);
    }
})();
</script>
@endif
@endsection
