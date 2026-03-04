@extends('layouts.app')
@section('title', 'CRM - Leads')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Leads</h1>
            <p class="text-sm text-gray-500 mt-1">Central de Leads + Pipeline CRM unificados</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('leads.index') }}" class="px-3 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm hover:bg-gray-200 transition">Central de Leads</a>
            <a href="{{ route('crm.pipeline') }}" class="px-3 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">Oportunidades</a>
            <button onclick="document.getElementById('modalLeadManual').classList.remove('hidden')" class="px-3 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition">+ Novo Lead</button>
        </div>
    </div>

    <div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-6">
        <a href="{{ route('crm.leads', ['status' => 'novo']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('status') === 'novo' ? 'ring-2 ring-blue-400' : '' }}">
            <p class="text-[10px] text-gray-500 uppercase tracking-wider">Novos</p>
            <p class="text-xl font-bold text-blue-600">{{ number_format($totals['novo']) }}</p>
        </a>
        <a href="{{ route('crm.leads', ['status' => 'em_contato']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('status') === 'em_contato' ? 'ring-2 ring-green-400' : '' }}">
            <p class="text-[10px] text-gray-500 uppercase tracking-wider">Em Contato</p>
            <p class="text-xl font-bold text-green-600">{{ number_format($totals['em_contato']) }}</p>
        </a>
        <a href="{{ route('crm.leads', ['status' => 'perdido']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('status') === 'perdido' ? 'ring-2 ring-red-400' : '' }}">
            <p class="text-[10px] text-gray-500 uppercase tracking-wider">Perdidos</p>
            <p class="text-xl font-bold text-red-600">{{ number_format($totals['perdido']) }}</p>
        </a>
        <a href="{{ route('crm.leads', ['origem' => 'marketing']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('origem') === 'marketing' ? 'ring-2 ring-amber-400' : '' }}">
            <p class="text-[10px] text-gray-500 uppercase tracking-wider">Marketing</p>
            <p class="text-xl font-bold text-amber-600">{{ number_format($totals['marketing']) }}</p>
        </a>
        <a href="{{ route('crm.leads', ['origem' => 'crm']) }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ request('origem') === 'crm' ? 'ring-2 ring-purple-400' : '' }}">
            <p class="text-[10px] text-gray-500 uppercase tracking-wider">CRM Direto</p>
            <p class="text-xl font-bold text-purple-600">{{ number_format($totals['crm']) }}</p>
        </a>
        <a href="{{ route('crm.leads') }}" class="bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition {{ !request('status') && !request('origem') ? 'ring-2 ring-gray-400' : '' }}">
            <p class="text-[10px] text-gray-500 uppercase tracking-wider">Total</p>
            <p class="text-xl font-bold text-[#1B334A]">{{ number_format($totals['total']) }}</p>
        </a>
    </div>

    <form method="GET" action="{{ route('crm.leads') }}" class="bg-white rounded-lg shadow-sm border p-4 mb-6">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Nome, email, telefone..."
                   class="border rounded-lg px-3 py-2 text-sm col-span-2">
            <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos os status</option>
                <option value="novo" {{ request('status') === 'novo' ? 'selected' : '' }}>Novo</option>
                <option value="em_contato" {{ request('status') === 'em_contato' ? 'selected' : '' }}>Em Contato</option>
                <option value="perdido" {{ request('status') === 'perdido' ? 'selected' : '' }}>Perdido</option>
            </select>
            <select name="origem" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todas origens</option>
                <option value="marketing" {{ request('origem') === 'marketing' ? 'selected' : '' }}>Marketing</option>
                <option value="crm" {{ request('origem') === 'crm' ? 'selected' : '' }}>CRM Direto</option>
            </select>
            <select name="owner_user_id" class="border rounded-lg px-3 py-2 text-sm">
                <option value="">Todos respons.</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ request('owner_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Filtrar</button>
        </div>
    </form>

    <div class="bg-white rounded-lg shadow-sm border overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">
                        <a href="{{ route('crm.leads', array_merge(request()->query(), ['sort' => 'nome', 'dir' => (request('sort') === 'nome' && request('dir','desc') === 'desc') ? 'asc' : 'desc'])) }}"
                           class="hover:text-[#385776] {{ request('sort') === 'nome' ? 'text-[#385776] font-bold' : '' }}">
                            Nome @if(request('sort') === 'nome'){{ request('dir','desc') === 'asc' ? chr(9650) : chr(9660) }}@endif
                        </a>
                    </th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Contato</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Origem</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">
                        <a href="{{ route('crm.leads', array_merge(request()->query(), ['sort' => 'lifecycle', 'dir' => (request('sort') === 'lifecycle' && request('dir','desc') === 'desc') ? 'asc' : 'desc'])) }}"
                           class="hover:text-[#385776] {{ request('sort') === 'lifecycle' ? 'text-[#385776] font-bold' : '' }}">
                            Status @if(request('sort') === 'lifecycle'){{ request('dir','desc') === 'asc' ? chr(9650) : chr(9660) }}@endif
                        </a>
                    </th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Interesse</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Potencial</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">
                        <a href="{{ route('crm.leads', array_merge(request()->query(), ['sort' => 'data', 'dir' => (request('sort','data') === 'data' && request('dir','desc') === 'desc') ? 'asc' : 'desc'])) }}"
                           class="hover:text-[#385776] {{ request('sort','data') === 'data' ? 'text-[#385776] font-bold' : '' }}">
                            Data @if(request('sort','data') === 'data'){{ request('dir','desc') === 'asc' ? chr(9650) : chr(9660) }}@endif
                        </a>
                    </th>
                    <th class="text-left px-4 py-3 font-medium text-gray-600 text-xs">Acoes</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($results as $row)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        @if($row->origem === 'crm')
                            <a href="{{ route('crm.accounts.show', $row->id) }}" class="font-medium text-[#385776] hover:underline">{{ $row->nome }}</a>
                        @else
                            <span class="font-medium text-gray-800">{{ $row->nome }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($row->telefone)<span class="block text-xs">{{ $row->telefone }}</span>@endif
                        @if($row->email)<span class="block text-xs text-gray-400">{{ $row->email }}</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        @if($row->origem === 'marketing')
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-medium">Marketing</span>
                            @if($row->crm_account_id)
                                <span class="text-[10px] text-green-600 ml-1" title="Promovido ao CRM">&#10003; CRM</span>
                            @endif
                        @else
                            <span class="text-[10px] px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium">CRM</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php
                            $stMap = [
                                'onboarding' => ['Novo', 'bg-blue-100 text-blue-700'],
                                'ativo'      => ['Em Contato', 'bg-green-100 text-green-700'],
                                'adormecido' => ['Adormecido', 'bg-yellow-100 text-yellow-700'],
                                'risco'      => ['Perdido', 'bg-red-100 text-red-700'],
                            ];
                            $st = $stMap[$row->lifecycle] ?? ['--', 'bg-gray-100 text-gray-600'];
                        @endphp
                        <span class="text-[10px] px-2 py-0.5 rounded-full {{ $st[1] }} font-medium">{{ $st[0] }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-600 max-w-[120px] truncate" title="{{ $row->area_interesse }}">{{ $row->area_interesse ?: '--' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-600">{{ $row->potencial_honorarios ?: '--' }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ $row->data ? \Carbon\Carbon::parse($row->data)->format('d/m/Y') : '--' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex gap-1">
                            @if($row->origem === 'marketing' && !$row->crm_account_id)
                                <button onclick="promoverLead({{ $row->id }})" class="text-[10px] px-2 py-1 bg-[#385776] text-white rounded hover:bg-[#1B334A]" title="Promover ao CRM">CRM</button>
                            @endif
                            @if($row->origem === 'marketing')
                                <button onclick="abrirSipexModal({{ $row->id }})" class="text-[10px] px-2 py-1 bg-amber-600 text-white rounded hover:bg-amber-700" title="Cotar SIPEX">SIPEX</button>
                            @endif
                            @if($row->origem === 'crm')
                                <a href="{{ route('crm.accounts.show', $row->id) }}" class="text-[10px] px-2 py-1 bg-gray-100 text-gray-700 rounded hover:bg-gray-200">360</a>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">Nenhum lead encontrado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $results->links() }}</div>
</div>


<!-- Modal Lead Manual -->
<div id="modalLeadManual" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50" onclick="if(event.target===this)this.classList.add('hidden')">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="bg-[#385776] px-6 py-4 flex items-center justify-between">
            <h2 class="text-white font-semibold text-lg">Novo Lead Manual</h2>
            <button onclick="document.getElementById('modalLeadManual').classList.add('hidden')" class="text-white/70 hover:text-white text-xl">&times;</button>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Nome *</label>
                <input type="text" id="ml_nome" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Nome completo do lead">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Telefone</label>
                    <input type="text" id="ml_telefone" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="(XX) XXXXX-XXXX">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Email</label>
                    <input type="text" id="ml_email" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="email@exemplo.com">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Origem *</label>
                    <select id="ml_origem" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="relacionamento">Relacionamento</option>
                        <option value="indicacao">Indicação</option>
                        <option value="telefone">Telefone</option>
                        <option value="presencial">Presencial</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Responsável *</label>
                    <select id="ml_owner" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Área de Interesse</label>
                <input type="text" id="ml_area" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Ex: Trabalhista, Cível, Família...">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Resumo da Demanda</label>
                <textarea id="ml_resumo" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Descreva brevemente a demanda do lead..."></textarea>
            </div>
        </div>
        <div class="bg-gray-50 px-6 py-4 flex justify-end gap-2">
            <button onclick="document.getElementById('modalLeadManual').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
            <button onclick="salvarLeadManual()" id="btnSalvarLead" class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 font-medium">Salvar e Promover ao CRM</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
function promoverLead(leadId) {
    const btn = event.currentTarget;
    btn.disabled = true;
    btn.textContent = '...';
    btn.classList.add('opacity-50', 'cursor-not-allowed');
    if (!confirm('Promover este lead para o pipeline CRM?')) return;
    fetch('/nexo/atendimento/leads/' + leadId + '/promover-crm', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify({})
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) { alert('Lead promovido! Account #' + d.account_id); location.reload(); }
        else { alert(d.error || 'Erro ao promover.'); btn.disabled = false; btn.textContent = 'CRM'; btn.classList.remove('opacity-50','cursor-not-allowed'); }
    })
    .catch(e => alert('Erro: ' + e.message));
}

function salvarLeadManual() {
    const nome = document.getElementById('ml_nome').value.trim();
    if (!nome) { alert('Nome é obrigatório.'); return; }

    const btn = document.getElementById('btnSalvarLead');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    const payload = {
        nome: nome,
        telefone: document.getElementById('ml_telefone').value.trim() || null,
        email: document.getElementById('ml_email').value.trim() || null,
        origem_canal: document.getElementById('ml_origem').value,
        owner_user_id: document.getElementById('ml_owner').value,
        area_interesse: document.getElementById('ml_area').value.trim() || null,
        resumo_demanda: document.getElementById('ml_resumo').value.trim() || null,
    };

    fetch('{{ url("/crm/leads/manual") }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(d => {
        if (d.ok) {
            alert('Lead criado e promovido ao CRM! Account #' + d.account_id);
            location.reload();
        } else {
            alert(d.error || 'Erro ao salvar lead.');
            btn.disabled = false;
            btn.textContent = 'Salvar e Promover ao CRM';
        }
    })
    .catch(e => {
        alert('Erro: ' + e.message);
        btn.disabled = false;
        btn.textContent = 'Salvar e Promover ao CRM';
    });
}
</script>
@endpush
@endsection
