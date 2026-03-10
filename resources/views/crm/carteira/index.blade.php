@extends('layouts.app')
@section('title', 'CRM - Carteira')

@php
    $currentSort = request('sort', 'name');
    $currentDir = request('dir', 'asc');
    $isAdmin = auth()->user()->isAdmin();

    function sortUrl($field) {
        $current = request()->all();
        $current['sort'] = $field;
        $current['dir'] = (request('sort') === $field && request('dir', 'asc') === 'asc') ? 'desc' : 'asc';
        return request()->url() . '?' . http_build_query($current);
    }

    function sortIcon($field) {
        if (request('sort') !== $field) return '<span class="text-gray-300 ml-1">⇅</span>';
        return request('dir', 'asc') === 'asc'
            ? '<span class="text-[#385776] ml-1">▲</span>'
            : '<span class="text-[#385776] ml-1">▼</span>';
    }
@endphp

@section('content')
<div class="max-w-[1400px] mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Carteira de Clientes</h1>
            <p class="text-sm text-gray-500 mt-1">Visão gerencial de toda a base CRM</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.distribution') }}" class="px-4 py-2 border border-[#385776] text-[#385776] rounded-lg text-sm hover:bg-[#385776] hover:text-white transition">
                Distribuição IA
            </a>
            <a href="{{ route('crm.pipeline') }}" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">
                Pipeline →
            </a>
        </div>
    </div>

    {{-- Cards KPI --}}
    <div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">
        @php
            $kpis = [
                ['label' => 'Total', 'value' => $totals['total'], 'color' => 'text-[#1B334A]', 'link' => route('crm.carteira', ['lifecycle' => 'todos'])],
                ['label' => 'Ativos', 'value' => $totals['ativos'], 'color' => 'text-green-600', 'link' => route('crm.carteira', ['lifecycle' => 'ativo'])],
                ['label' => 'Adormecidos', 'value' => $totals['adormecido'], 'color' => 'text-yellow-600', 'link' => route('crm.carteira', ['lifecycle' => 'adormecido'])],
                ['label' => 'Arquivados', 'value' => $totals['arquivado'], 'color' => 'text-gray-500', 'link' => route('crm.carteira', ['lifecycle' => 'arquivado'])],
                ['label' => 'Onboarding', 'value' => $totals['onboarding'], 'color' => 'text-blue-600', 'link' => route('crm.carteira', ['lifecycle' => 'onboarding'])],
                ['label' => 'Sem Contato 30d', 'value' => $totals['sem_contato_30d'], 'color' => 'text-red-600', 'link' => route('crm.carteira', ['lifecycle' => 'ativo', 'sem_contato_dias' => 30]), 'border' => 'border-red-200'],
            ];
        @endphp
        @foreach($kpis as $kpi)
        <a href="{{ $kpi['link'] }}" class="bg-white rounded-lg shadow-sm border {{ $kpi['border'] ?? '' }} p-4 hover:ring-2 hover:ring-[#385776]/30 transition">
            <p class="text-xs text-gray-500 uppercase">{{ $kpi['label'] }}</p>
            <p class="text-2xl font-bold {{ $kpi['color'] }}">{{ number_format($kpi['value']) }}</p>
        </a>
        @endforeach
    </div>

    {{-- Filtros --}}
    <form method="GET" action="{{ route('crm.carteira') }}" class="bg-white rounded-lg shadow-sm border p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar nome, doc, email..."
                   class="border rounded-lg px-3 py-2 text-sm col-span-2 focus:ring-2 focus:ring-[#385776]/30 focus:border-[#385776]">
            <select name="kind" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os tipos</option>
                <option value="client" {{ request('kind') === 'client' ? 'selected' : '' }}>Clientes</option>
                <option value="prospect" {{ request('kind') === 'prospect' ? 'selected' : '' }}>Prospects</option>
            </select>
            <select name="lifecycle" class="border rounded-lg px-3 py-2 text-sm">
                <option value="todos" {{ request('lifecycle') === 'todos' ? 'selected' : '' }}>Todos os ciclos</option>
                @foreach(['onboarding','ativo','adormecido','arquivado'] as $lc)
                    <option value="{{ $lc }}" {{ (request('lifecycle', 'ativo')) === $lc ? 'selected' : '' }}>{{ ucfirst($lc) }}</option>
                @endforeach
            </select>
            <select name="owner_user_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os responsáveis</option>
                @if($isAdmin)
                <option value="sem_responsavel" {{ request('owner_user_id') === 'sem_responsavel' ? 'selected' : '' }}>— Sem responsável</option>
                @endif
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
        </div>
        <div class="flex items-center gap-4 mt-3">
            <select name="sem_contato_dias" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Sem contato (todos)</option>
                @foreach([7,15,30,60,90] as $d)
                    <option value="{{ $d }}" {{ request('sem_contato_dias') == $d ? 'selected' : '' }}>+{{ $d }} dias</option>
                @endforeach
            </select>
            <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="overdue_only" value="1" {{ request()->boolean('overdue_only') ? 'checked' : '' }} class="w-4 h-4 rounded">
                Ação vencida
            </label>
            <input type="hidden" name="sort" value="{{ $currentSort }}">
            <input type="hidden" name="dir" value="{{ $currentDir }}">
            <button type="submit" class="px-5 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] font-medium transition">Filtrar</button>
            <a href="{{ route('crm.carteira') }}" class="text-sm text-gray-400 hover:text-gray-600">Limpar filtros</a>
        </div>
    </form>

    {{-- Barra de ações em massa (admin) --}}
    @if($isAdmin)
    <div id="bulk-bar" class="hidden mb-4 bg-[#1B334A] text-white rounded-lg px-5 py-3 items-center gap-4 flex-wrap shadow-lg">
        <span class="text-sm font-semibold"><span id="bulk-count">0</span> selecionada(s)</span>
        <div class="h-6 w-px bg-gray-500"></div>

        {{-- Transferir --}}
        <div class="flex items-center gap-2">
            <select id="bulk-owner-select" class="bg-[#385776] border border-gray-500 text-white rounded px-2 py-1.5 text-xs">
                <option value="">— Sem responsável</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
            <button onclick="doBulk('assign')" class="px-3 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded text-xs font-medium transition">
                Transferir
            </button>
        </div>
        <div class="h-6 w-px bg-gray-500"></div>

        {{-- Lifecycle --}}
        <button onclick="doBulk('activate')" class="px-3 py-1.5 bg-green-500 hover:bg-green-600 text-white rounded text-xs font-medium transition">Ativar</button>
        <button onclick="doBulk('archive')" class="px-3 py-1.5 bg-gray-500 hover:bg-gray-600 text-white rounded text-xs font-medium transition">Arquivar</button>
        <button onclick="doBulk('dormant')" class="px-3 py-1.5 bg-yellow-500 hover:bg-yellow-600 text-white rounded text-xs font-medium transition">Adormecido</button>
        <button onclick="doBulk('onboarding')" class="px-3 py-1.5 bg-blue-400 hover:bg-blue-500 text-white rounded text-xs font-medium transition">Onboarding</button>
        <button onclick="doBulk('delete_prospect')" class="px-3 py-1.5 bg-red-500 hover:bg-red-600 text-white rounded text-xs font-medium transition">Excluir Prospects</button>

        <button onclick="clearSelection()" class="text-gray-300 hover:text-white text-xs underline ml-auto">Limpar</button>
    </div>
    @endif

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    @if($isAdmin)
                    <th class="px-3 py-3 w-10">
                        <input type="checkbox" id="check-all" title="Selecionar todos desta página" class="w-4 h-4 cursor-pointer rounded">
                    </th>
                    @endif
                    <th class="text-left px-4 py-3">
                        <a href="{{ sortUrl('name') }}" class="font-medium text-gray-600 hover:text-[#385776] flex items-center">
                            Nome {!! sortIcon('name') !!}
                        </a>
                    </th>
                    <th class="text-left px-3 py-3 font-medium text-gray-600">Tipo</th>
                    <th class="text-left px-3 py-3 font-medium text-gray-600">Responsável</th>
                    <th class="text-left px-3 py-3">
                        <a href="{{ sortUrl('last_touch_at') }}" class="font-medium text-gray-600 hover:text-[#385776] flex items-center">
                            Últ. Contato {!! sortIcon('last_touch_at') !!}
                        </a>
                    </th>
                    <th class="text-left px-3 py-3">
                        <a href="{{ sortUrl('next_touch_at') }}" class="font-medium text-gray-600 hover:text-[#385776] flex items-center">
                            Próx. Ação {!! sortIcon('next_touch_at') !!}
                        </a>
                    </th>
                    <th class="text-center px-3 py-3">
                        <a href="{{ sortUrl('health_score') }}" class="font-medium text-gray-600 hover:text-[#385776] flex items-center justify-center">
                            Saúde {!! sortIcon('health_score') !!}
                        </a>
                    </th>
                    <th class="text-left px-3 py-3">
                        <a href="{{ sortUrl('lifecycle') }}" class="font-medium text-gray-600 hover:text-[#385776] flex items-center">
                            Ciclo {!! sortIcon('lifecycle') !!}
                        </a>
                    </th>
                    <th class="text-left px-3 py-3 font-medium text-gray-600">Segmento</th>
                    <th class="text-center px-3 py-3 font-medium text-gray-600">Opps</th>
                    <th class="px-3 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y" id="accounts-tbody">
                @forelse($accounts as $acc)
                <tr class="hover:bg-gray-50 account-row" data-id="{{ $acc->id }}">
                    @if($isAdmin)
                    <td class="px-3 py-2.5">
                        <input type="checkbox" class="row-check w-4 h-4 cursor-pointer rounded" value="{{ $acc->id }}">
                    </td>
                    @endif
                    <td class="px-4 py-2.5">
                        <a href="{{ route('crm.accounts.show', $acc->id) }}" class="font-medium text-[#385776] hover:underline">
                            {{ $acc->name }}
                        </a>
                        @if($acc->doc_digits)
                            <span class="text-xs text-gray-400 ml-1">{{ strlen($acc->doc_digits) === 11 ? 'PF' : 'PJ' }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5">
                        <span class="px-2 py-0.5 rounded text-xs {{ $acc->kind === 'client' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                            {{ $acc->kind === 'client' ? 'Cliente' : 'Prospect' }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5 text-gray-600">{{ $acc->owner?->name ?? '—' }}</td>
                    <td class="px-3 py-2.5">
                        @if($acc->last_touch_at)
                            @php $dias = $acc->last_touch_at->diffInDays(now()); @endphp
                            <span class="{{ $dias > 30 ? 'text-red-500 font-medium' : ($dias > 15 ? 'text-yellow-600' : 'text-gray-600') }}">
                                {{ $acc->last_touch_at->diffForHumans() }}
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5">
                        @if($acc->next_touch_at)
                            <span class="{{ $acc->next_touch_at->isPast() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                {{ $acc->next_touch_at->format('d/m') }}
                                @if($acc->next_touch_at->isPast())
                                    <span class="text-xs">({{ $acc->next_touch_at->diffInDays(now()) }}d)</span>
                                @endif
                            </span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center">
                        @if($acc->health_score !== null)
                            @php
                                $hc = $acc->health_score;
                                $hClass = $hc >= 70 ? 'bg-green-100 text-green-700' : ($hc >= 40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700');
                            @endphp
                            <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $hClass }}">{{ $hc }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5">
                        @php
                            $lcColors = ['onboarding' => 'bg-blue-100 text-blue-700', 'ativo' => 'bg-green-100 text-green-700', 'adormecido' => 'bg-yellow-100 text-yellow-700', 'arquivado' => 'bg-gray-200 text-gray-600'];
                        @endphp
                        <span class="px-2 py-0.5 rounded text-xs {{ $lcColors[$acc->lifecycle] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($acc->lifecycle) }}
                        </span>
                    </td>
                    <td class="px-3 py-2.5">
                        @if($acc->segment)
                            <span class="px-2 py-0.5 rounded text-xs bg-purple-50 text-purple-700">{{ $acc->segment }}</span>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2.5 text-center text-gray-600">{{ $acc->open_opps_count ?? 0 }}</td>
                    <td class="px-3 py-2.5 text-right">
                        <a href="{{ route('crm.accounts.show', $acc->id) }}" class="text-[#385776] hover:underline text-xs font-medium">360→</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $isAdmin ? 11 : 10 }}" class="px-4 py-8 text-center text-gray-400">Nenhum registro encontrado.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Paginação + contagem --}}
    <div class="mt-4 flex items-center justify-between">
        <p class="text-xs text-gray-400">{{ $accounts->total() }} registro(s) · Página {{ $accounts->currentPage() }}/{{ $accounts->lastPage() }}</p>
        <div>{{ $accounts->links() }}</div>
    </div>
</div>

{{-- Modal de confirmação (admin) --}}
@if($isAdmin)
<div id="bulk-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-lg font-bold text-[#1B334A] mb-2" id="modal-title">Confirmar ação</h2>
        <p class="text-sm text-gray-600 mb-4" id="bulk-modal-text"></p>
        <div class="flex gap-3 justify-end">
            <button onclick="closeBulkModal()" class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
            <button onclick="executeBulkAction()" id="bulk-confirm-btn" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] font-medium">Confirmar</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const bulkUrl = "{{ route('crm.carteira.bulk-action') }}";
    const csrfToken = "{{ csrf_token() }}";

    let selectedIds = new Set();
    let pendingAction = null;

    const actionLabels = {
        assign: 'Transferir responsável',
        archive: 'Arquivar',
        activate: 'Reativar',
        dormant: 'Marcar como adormecido',
        onboarding: 'Mover para onboarding',
        delete_prospect: 'Excluir prospects sem vínculo'
    };

    const actionColors = {
        assign: 'bg-blue-600 hover:bg-blue-700',
        archive: 'bg-gray-600 hover:bg-gray-700',
        activate: 'bg-green-600 hover:bg-green-700',
        dormant: 'bg-yellow-600 hover:bg-yellow-700',
        onboarding: 'bg-blue-500 hover:bg-blue-600',
        delete_prospect: 'bg-red-600 hover:bg-red-700'
    };

    document.getElementById('check-all').addEventListener('change', function () {
        document.querySelectorAll('.row-check').forEach(cb => {
            cb.checked = this.checked;
            this.checked ? selectedIds.add(+cb.value) : selectedIds.delete(+cb.value);
        });
        updateBar();
    });

    document.addEventListener('change', function (e) {
        if (!e.target.classList.contains('row-check')) return;
        e.target.checked ? selectedIds.add(+e.target.value) : selectedIds.delete(+e.target.value);
        if (!e.target.checked) document.getElementById('check-all').checked = false;
        updateBar();
    });

    function updateBar() {
        const bar = document.getElementById('bulk-bar');
        document.getElementById('bulk-count').textContent = selectedIds.size;
        bar.classList.toggle('hidden', selectedIds.size === 0);
        bar.classList.toggle('flex', selectedIds.size > 0);
    }

    window.clearSelection = function () {
        selectedIds.clear();
        document.querySelectorAll('.row-check').forEach(cb => cb.checked = false);
        document.getElementById('check-all').checked = false;
        updateBar();
    };

    window.doBulk = function (action) {
        if (!selectedIds.size) return;
        pendingAction = action;

        let desc = actionLabels[action] || action;
        let extra = '';
        if (action === 'assign') {
            const sel = document.getElementById('bulk-owner-select');
            extra = sel.value ? ' para ' + sel.options[sel.selectedIndex].text : ' (remover responsável)';
        }
        if (action === 'delete_prospect') {
            desc = 'EXCLUIR permanentemente prospects sem vínculo DataJuri';
        }

        document.getElementById('modal-title').textContent = desc;
        document.getElementById('bulk-modal-text').textContent =
            'Aplicar "' + desc + extra + '" em ' + selectedIds.size + ' conta(s)? Esta ação não pode ser desfeita.';

        const btn = document.getElementById('bulk-confirm-btn');
        btn.className = 'px-4 py-2 text-white rounded-lg text-sm font-medium ' + (actionColors[action] || 'bg-[#385776]');

        document.getElementById('bulk-modal').classList.remove('hidden');
    };

    window.closeBulkModal = function () {
        document.getElementById('bulk-modal').classList.add('hidden');
        pendingAction = null;
    };

    window.executeBulkAction = function () {
        if (!pendingAction || !selectedIds.size) return;
        const btn = document.getElementById('bulk-confirm-btn');
        btn.disabled = true;
        btn.textContent = 'Processando...';

        const body = {
            account_ids: Array.from(selectedIds),
            action: pendingAction,
        };

        if (pendingAction === 'assign') {
            body.owner_user_id = document.getElementById('bulk-owner-select').value || null;
        }

        fetch(bulkUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: JSON.stringify(body),
        })
        .then(r => r.json())
        .then(data => {
            closeBulkModal();
            if (data.success) {
                toast(data.message, 'success');
                setTimeout(() => location.reload(), 800);
            } else {
                toast(data.message || 'Erro ao processar.', 'error');
            }
        })
        .catch(() => { closeBulkModal(); toast('Erro de comunicação.', 'error'); })
        .finally(() => { btn.disabled = false; btn.textContent = 'Confirmar'; });
    };

    function toast(msg, type) {
        const t = document.createElement('div');
        t.className = 'fixed bottom-6 right-6 z-50 px-5 py-3 rounded-lg text-white text-sm shadow-lg transition-all '
            + (type === 'success' ? 'bg-green-600' : 'bg-red-600');
        t.textContent = msg;
        document.body.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 3000);
    }
})();
</script>
@endpush
@endif
@endsection
