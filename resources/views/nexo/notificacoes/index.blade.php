@extends('layouts.app')

@section('title', 'Notificações WhatsApp')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Notificações WhatsApp</h1>
            <p class="text-sm text-gray-500 mt-1">Andamentos processuais para envio aos clientes</p>
        </div>
        <div class="flex gap-3">
            <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-2 text-center">
                <div class="text-2xl font-bold text-yellow-600">{{ $countPendentes }}</div>
                <div class="text-xs text-yellow-700">Pendentes</div>
            </div>
            <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-2 text-center">
                <div class="text-2xl font-bold text-green-600">{{ $countEnviados }}</div>
                <div class="text-xs text-green-700">Enviados</div>
            </div>
            <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-2 text-center">
                <div class="text-2xl font-bold text-red-600">{{ $countFalha }}</div>
                <div class="text-xs text-red-700">Falhas</div>
            </div>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="border-b border-gray-200 mb-4">
        <nav class="flex gap-4" id="tabNav">
            <button onclick="switchTab('pendentes')" id="tabPendentes" class="tab-btn pb-3 px-1 text-sm font-medium border-b-2 border-blue-600 text-blue-600">
                Pendentes ({{ $countPendentes }})
            </button>
            <button onclick="switchTab('historico')" id="tabHistorico" class="tab-btn pb-3 px-1 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                Histórico
            </button>
        </nav>
    </div>

    {{-- Tab Pendentes --}}
    <div id="panelPendentes">
        @if($countPendentes > 0)
        <div class="flex items-center justify-between mb-4">
            <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" id="selectAll" onclick="toggleSelectAll()" class="rounded">
                Selecionar todos
            </label>
            <button onclick="aprovarSelecionados()" id="btnAprovarMassa" class="btn-mayer text-white text-sm px-4 py-2 rounded-lg disabled:opacity-50" disabled style="background:#385776">
                Enviar selecionados
            </button>
        </div>
        @endif

        <div class="space-y-3" id="listaPendentes">
        @forelse($pendentes as $n)
            <div class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-sm transition" id="card-{{ $n->id }}">
                <div class="flex items-start gap-3">
                    <input type="checkbox" class="notif-check rounded mt-1" value="{{ $n->id }}" onchange="updateMassBtn()">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $n->tipo === 'audiencia' ? 'bg-purple-100 text-purple-700' : ($n->tipo === 'andamento' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700') }}">
                                    {{ $n->tipo === 'audiencia' ? 'Audiência' : ($n->tipo === 'andamento' ? 'Andamento' : 'OS') }}
                                </span>
                                <span class="text-sm font-semibold text-gray-800">{{ $n->cliente_nome }}</span>
                            </div>
                            <span class="text-xs text-gray-400">{{ \Carbon\Carbon::parse($n->created_at)->format('d/m H:i') }}</span>
                        </div>
                        <div class="text-xs text-gray-500 mb-2">
                            Processo: <span class="font-mono">{{ $n->processo_pasta }}</span>
                            · Tel: {{ $n->telefone }}
                        </div>
                        <div class="bg-gray-50 rounded p-3 text-sm text-gray-700 mb-3">
                            @if($n->tipo === 'os')
                                {{ $n->error_message ?? 'Ordem de Serviço' }}
                            @else
                                @php $vars = json_decode($n->template_vars, true); @endphp
                                {{ $vars[2]['text'] ?? 'Sem descrição' }}
                            @endif
                        </div>
                        @if($n->tipo === 'os')
                        {{-- OS: formulário com autocomplete cliente + mensagem --}}
                        <div class="border-t border-gray-100 pt-3 mt-1 space-y-2" id="os-form-{{ $n->id }}">
                            <div class="relative">
                                <input type="text" id="os-cliente-search-{{ $n->id }}" placeholder="Buscar cliente (nome, CPF ou telefone)..."
                                    class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500"
                                    oninput="buscarClienteOS({{ $n->id }}, this.value)" autocomplete="off">
                                <input type="hidden" id="os-cliente-id-{{ $n->id }}">
                                <div id="os-dropdown-{{ $n->id }}" class="absolute z-50 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-48 overflow-y-auto hidden"></div>
                            </div>
                            <textarea id="os-msg-{{ $n->id }}" rows="2" placeholder="Mensagem de atualização para o cliente..."
                                class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" maxlength="300"></textarea>
                            <div class="flex items-center gap-2">
                                <button onclick="aprovarOS({{ $n->id }})" class="text-sm px-3 py-1.5 rounded text-white font-medium" style="background:#385776">
                                    Enviar ao cliente
                                </button>
                                <button onclick="descartarNotificacao({{ $n->id }})" class="text-sm px-3 py-1.5 rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                                    Descartar
                                </button>
                            </div>
                        </div>
                        @elseif($n->tipo === 'andamento')
                        {{-- Andamento: textarea editável para complementar --}}
                        <div class="space-y-2">
                            <textarea id="and-msg-{{ $n->id }}" rows="2" placeholder="Complementar ou reescrever a mensagem para o cliente..."
                                class="w-full text-sm border border-gray-300 rounded px-3 py-2 focus:border-blue-500 focus:ring-1 focus:ring-blue-500" maxlength="300">{{ $vars[2]['text'] ?? '' }}</textarea>
                            <div class="flex items-center gap-2">
                                <button onclick="aprovarAndamento({{ $n->id }})" class="text-sm px-3 py-1.5 rounded text-white font-medium" style="background:#385776">
                                    Enviar ao cliente
                                </button>
                                <button onclick="descartarNotificacao({{ $n->id }})" class="text-sm px-3 py-1.5 rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                                    Descartar
                                </button>
                            </div>
                        </div>
                        @else
                        {{-- Audiência: envio direto --}}
                        <div class="flex items-center gap-2">
                            <button onclick="aprovarNotificacao({{ $n->id }})" class="text-sm px-3 py-1.5 rounded text-white font-medium" style="background:#385776">
                                Enviar ao cliente
                            </button>
                            <button onclick="descartarNotificacao({{ $n->id }})" class="text-sm px-3 py-1.5 rounded border border-gray-300 text-gray-600 hover:bg-gray-50">
                                Descartar
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="text-center py-12 text-gray-400">
                <div class="text-4xl mb-2">✅</div>
                <p>Nenhuma notificação pendente</p>
            </div>
        @endforelse
        </div>
    </div>

    {{-- Tab Histórico --}}
    <div id="panelHistorico" class="hidden">
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left px-4 py-3 text-gray-600 font-medium">Cliente</th>
                        <th class="text-left px-4 py-3 text-gray-600 font-medium">Processo</th>
                        <th class="text-left px-4 py-3 text-gray-600 font-medium">Tipo</th>
                        <th class="text-left px-4 py-3 text-gray-600 font-medium">Status</th>
                        <th class="text-left px-4 py-3 text-gray-600 font-medium">Data</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($historico as $h)
                    <tr>
                        <td class="px-4 py-3">{{ $h->cliente_nome }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $h->processo_pasta }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded {{ $h->tipo === 'audiencia' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ ucfirst($h->tipo) }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            @if($h->status === 'sent')
                                <span class="text-green-600 font-medium">Enviado</span>
                            @elseif($h->status === 'failed')
                                <span class="text-red-600 font-medium" title="{{ $h->error_message }}">Falhou</span>
                            @else
                                <span class="text-gray-400">{{ ucfirst($h->status) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ \Carbon\Carbon::parse($h->updated_at)->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">Nenhum registro</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function switchTab(tab) {
    document.getElementById('panelPendentes').classList.toggle('hidden', tab !== 'pendentes');
    document.getElementById('panelHistorico').classList.toggle('hidden', tab !== 'historico');
    document.querySelectorAll('.tab-btn').forEach(b => {
        b.classList.remove('border-blue-600', 'text-blue-600');
        b.classList.add('border-transparent', 'text-gray-500');
    });
    const active = document.getElementById('tab' + tab.charAt(0).toUpperCase() + tab.slice(1));
    active.classList.remove('border-transparent', 'text-gray-500');
    active.classList.add('border-blue-600', 'text-blue-600');
}

function aprovarNotificacao(id) {
    if (!confirm('Enviar notificação WhatsApp ao cliente?')) return;
    fetch(`/nexo/notificacoes/${id}/aprovar`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json'},
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('card-' + id);
            card.style.opacity = '0.5';
            card.innerHTML = '<div class="p-4 text-green-600 font-medium text-center">✅ Enviado com sucesso</div>';
            setTimeout(() => card.remove(), 2000);
        } else {
            alert('Erro: ' + (data.error || 'Falha no envio'));
        }
    })
    .catch(e => alert('Erro de rede: ' + e.message));
}

function descartarNotificacao(id) {
    if (!confirm('Descartar esta notificação? O cliente NÃO será notificado.')) return;
    fetch(`/nexo/notificacoes/${id}/descartar`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json'},
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('card-' + id);
            card.style.opacity = '0.3';
            setTimeout(() => card.remove(), 500);
        }
    });
}

function toggleSelectAll() {
    const checked = document.getElementById('selectAll').checked;
    document.querySelectorAll('.notif-check').forEach(c => c.checked = checked);
    updateMassBtn();
}

function updateMassBtn() {
    const checked = document.querySelectorAll('.notif-check:checked').length;
    const btn = document.getElementById('btnAprovarMassa');
    if (btn) {
        btn.disabled = checked === 0;
        btn.textContent = checked > 0 ? `Enviar selecionados (${checked})` : 'Enviar selecionados';
    }
}

function aprovarSelecionados() {
    const ids = Array.from(document.querySelectorAll('.notif-check:checked')).map(c => parseInt(c.value));
    if (ids.length === 0) return;
    if (!confirm(`Enviar ${ids.length} notificações WhatsApp?`)) return;

    fetch('/nexo/notificacoes/aprovar-massa', {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json'},
        body: JSON.stringify({ids: ids}),
    })
    .then(r => r.json())
    .then(data => {
        alert(`Enviados: ${data.enviados} | Falhas: ${data.falhas}`);
        location.reload();
    })
    .catch(e => alert('Erro: ' + e.message));
}

// === OS: Autocomplete cliente + envio ===
let osDebounce = {};
function buscarClienteOS(notifId, termo) {
    clearTimeout(osDebounce[notifId]);
    const dropdown = document.getElementById('os-dropdown-' + notifId);
    if (termo.length < 2) { dropdown.classList.add('hidden'); return; }
    osDebounce[notifId] = setTimeout(() => {
        fetch('/nexo/notificacoes/buscar-clientes?q=' + encodeURIComponent(termo))
        .then(r => r.json())
        .then(clientes => {
            if (clientes.length === 0) {
                dropdown.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">Nenhum cliente encontrado</div>';
            } else {
                dropdown.innerHTML = clientes.map(c =>
                    `<div class="px-3 py-2 text-sm hover:bg-blue-50 cursor-pointer border-b border-gray-50" onclick="selecionarClienteOS(${notifId}, ${c.id}, '${c.nome.replace(/'/g,"\'")}', '${c.telefone}')">
                        <div class="font-medium text-gray-800">${c.nome}</div>
                        <div class="text-xs text-gray-500">${c.cpf_cnpj || ''} · ${c.telefone || 'sem telefone'}</div>
                    </div>`
                ).join('');
            }
            dropdown.classList.remove('hidden');
        });
    }, 300);
}
function selecionarClienteOS(notifId, clienteId, nome, telefone) {
    document.getElementById('os-cliente-search-' + notifId).value = nome + (telefone ? ' (' + telefone + ')' : '');
    document.getElementById('os-cliente-id-' + notifId).value = clienteId;
    document.getElementById('os-dropdown-' + notifId).classList.add('hidden');
}
function aprovarOS(notifId) {
    const clienteId = document.getElementById('os-cliente-id-' + notifId).value;
    const mensagem = document.getElementById('os-msg-' + notifId).value.trim();
    if (!clienteId) { alert('Selecione um cliente antes de enviar.'); return; }
    if (!mensagem) { alert('Escreva a mensagem de atualização.'); return; }
    if (!confirm('Enviar notificação de OS ao cliente via WhatsApp?')) return;
    fetch(`/nexo/notificacoes/${notifId}/aprovar-os`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json'},
        body: JSON.stringify({cliente_id: parseInt(clienteId), mensagem: mensagem}),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('card-' + notifId);
            card.style.opacity = '0.5';
            card.innerHTML = '<div class="p-4 text-green-600 font-medium text-center">✅ ' + data.message + '</div>';
            setTimeout(() => card.remove(), 2000);
        } else {
            alert('Erro: ' + (data.message || 'Falha no envio'));
        }
    })
    .catch(e => alert('Erro de rede: ' + e.message));
}
// Fechar dropdowns ao clicar fora
document.addEventListener('click', function(e) {
    if (!e.target.closest('[id^="os-cliente-search-"]') && !e.target.closest('[id^="os-dropdown-"]')) {
        document.querySelectorAll('[id^="os-dropdown-"]').forEach(d => d.classList.add('hidden'));
    }
});


function aprovarAndamento(id) {
    const msg = document.getElementById('and-msg-' + id).value.trim();
    if (!msg) { alert('Escreva ou confirme a mensagem para o cliente.'); return; }
    if (!confirm('Enviar notificação WhatsApp ao cliente?')) return;
    fetch(`/nexo/notificacoes/${id}/aprovar`, {
        method: 'POST',
        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Content-Type': 'application/json'},
        body: JSON.stringify({descricao_custom: msg}),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('card-' + id);
            card.style.opacity = '0.5';
            card.innerHTML = '<div class="p-4 text-green-600 font-medium text-center">✅ Enviado com sucesso</div>';
            setTimeout(() => card.remove(), 2000);
        } else {
            alert('Erro: ' + (data.error || data.message || 'Falha no envio'));
        }
    })
    .catch(e => alert('Erro de rede: ' + e.message));
}

</script>
@endpush
