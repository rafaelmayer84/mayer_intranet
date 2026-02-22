@extends('layouts.app')
@section('title', 'GDP — Conformidade')
@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                <span class="text-lg">⚠️</span> GDP — Conformidade
            </h1>
            <p class="mt-1 text-xs text-gray-500">
                Gestão de conformidade — ocorrências automáticas e manuais | Competência: {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $ano }}
                @if($ciclo) | Ciclo: {{ $ciclo->nome }} @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" class="flex items-center gap-2">
                <select name="month" onchange="this.form.submit()" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $mes == $m ? 'selected' : '' }}>{{ \Carbon\Carbon::create()->month($m)->translatedFormat('F') }}</option>
                    @endfor
                </select>
                <select name="year" onchange="this.form.submit()" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm">
                    @for($y = 2026; $y <= now()->year + 1; $y++)
                        <option value="{{ $y }}" {{ $ano == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </form>
            @if(in_array($user->role, ['admin','coordenador']))
            <button onclick="executarScanner()" id="btn-scanner" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm" style="background-color:#385776">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Executar Scanner
            </button>
            <button onclick="document.getElementById('modal-nova').classList.remove('hidden')" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm" style="background-color:#16a34a">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Nova Manual
            </button>
            @endif
        </div>
    </div>

    {{-- CARDS RESUMO --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <p class="text-2xl font-bold text-gray-900">{{ $penalizacoes->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Total</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <p class="text-2xl font-bold text-red-600">{{ $penalizacoes->sum('pontos_desconto') }}</p>
            <p class="text-xs text-gray-500 mt-1">Pontos Descontados</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <p class="text-2xl font-bold text-amber-600">{{ $penalizacoes->where('contestacao_status', 'pendente')->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Contestações Pendentes</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <p class="text-2xl font-bold text-green-600">{{ $penalizacoes->where('contestacao_status', 'aceita')->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Contestações Aceitas</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <p class="text-2xl font-bold text-blue-600">{{ $penalizacoes->where('automatica', true)->count() }}</p>
            <p class="text-xs text-gray-500 mt-1">Automáticas</p>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <input type="hidden" name="month" value="{{ $mes }}">
            <input type="hidden" name="year" value="{{ $ano }}">
            @if(in_array($user->role, ['admin','coordenador']))
            <select name="user_id" onchange="this.form.submit()" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                <option value="">Todos os profissionais</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            @endif
            <select name="eixo_id" onchange="this.form.submit()" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                <option value="">Todos os eixos</option>
                @foreach($eixos as $e)
                    <option value="{{ $e->id }}" {{ request('eixo_id') == $e->id ? 'selected' : '' }}>{{ $e->nome }}</option>
                @endforeach
            </select>
            <select name="gravidade" onchange="this.form.submit()" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                <option value="">Todas gravidades</option>
                <option value="leve" {{ request('gravidade') == 'leve' ? 'selected' : '' }}>Leve</option>
                <option value="moderada" {{ request('gravidade') == 'moderada' ? 'selected' : '' }}>Moderada</option>
                <option value="grave" {{ request('gravidade') == 'grave' ? 'selected' : '' }}>Grave</option>
            </select>
            <select name="contestacao" onchange="this.form.submit()" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm">
                <option value="">Todas contestações</option>
                <option value="pendente" {{ request('contestacao') == 'pendente' ? 'selected' : '' }}>Pendente</option>
                <option value="aceita" {{ request('contestacao') == 'aceita' ? 'selected' : '' }}>Aceita</option>
                <option value="rejeitada" {{ request('contestacao') == 'rejeitada' ? 'selected' : '' }}>Rejeitada</option>
                <option value="nenhuma" {{ request('contestacao') == 'nenhuma' ? 'selected' : '' }}>Não contestada</option>
            </select>
        </form>
    </div>

    {{-- TABELA --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 uppercase border-b border-gray-100" style="background-color:rgba(56,87,118,0.05)">
                <tr>
                    <th class="px-4 py-3 text-left">Código</th>
                    <th class="px-4 py-3 text-left">Ocorrência</th>
                    @if(in_array($user->role, ['admin','coordenador']))
                    <th class="px-4 py-3 text-left">Profissional</th>
                    @endif
                    <th class="px-4 py-3 text-center">Gravidade</th>
                    <th class="px-4 py-3 text-center">Pontos</th>
                    <th class="px-4 py-3 text-center">Origem</th>
                    <th class="px-4 py-3 text-center">Contestação</th>
                    <th class="px-4 py-3 text-center">Data</th>
                    <th class="px-4 py-3 text-center">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($penalizacoes as $pen)
                <tr class="hover:bg-gray-50 transition-colors {{ $pen->contestacao_status === 'aceita' ? 'opacity-50' : '' }}">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $pen->tipo->codigo ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800 text-xs">{{ $pen->tipo->nome ?? 'Manual' }}</p>
                        <p class="text-xs text-gray-400 mt-0.5 truncate max-w-xs">{{ Str::limit($pen->descricao_automatica, 80) }}</p>
                    </td>
                    @if(in_array($user->role, ['admin','coordenador']))
                    <td class="px-4 py-3 text-xs text-gray-700">{{ $pen->usuario->name ?? '—' }}</td>
                    @endif
                    <td class="px-4 py-3 text-center">
                        @php $grav = $pen->tipo->gravidade ?? 'leve'; @endphp
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $grav === 'grave' ? 'bg-red-100 text-red-700' : ($grav === 'moderada' ? 'bg-amber-100 text-amber-700' : 'bg-yellow-100 text-yellow-700') }}">
                            {{ ucfirst($grav) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center font-bold text-red-600">-{{ $pen->pontos_desconto }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs {{ $pen->automatica ? 'text-blue-600' : 'text-purple-600' }}">
                            {{ $pen->automatica ? 'Auto' : 'Manual' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($pen->contestacao_status === 'pendente')
                            <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-xs font-medium">Pendente</span>
                        @elseif($pen->contestacao_status === 'aceita')
                            <span class="inline-flex items-center rounded-full bg-green-100 text-green-700 px-2 py-0.5 text-xs font-medium">Aceita</span>
                        @elseif($pen->contestacao_status === 'rejeitada')
                            <span class="inline-flex items-center rounded-full bg-red-100 text-red-700 px-2 py-0.5 text-xs font-medium">Rejeitada</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $pen->created_at ? $pen->created_at->format('d/m H:i') : '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-1">
                            <button onclick="verDetalhes({{ $pen->id }})" class="rounded p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50" title="Detalhes">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                            @if(in_array($user->role, ['admin','coordenador']) && $pen->contestacao_status === 'pendente')
                            <button onclick="avaliarContestacao({{ $pen->id }}, '{{ addslashes($pen->usuario->name ?? '') }}', '{{ addslashes($pen->contestacao_texto ?? '') }}')" class="rounded p-1 text-gray-400 hover:text-amber-600 hover:bg-amber-50" title="Avaliar">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ in_array($user->role, ['admin','coordenador']) ? 9 : 8 }}" class="px-4 py-12 text-center text-gray-400">
                        Nenhuma ocorrência registrada para este período.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- MODAL: Nova Manual --}}
<div id="modal-nova" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100" style="background-color:rgba(56,87,118,0.05)">
            <h3 class="text-lg font-semibold text-gray-800">Nova Ocorrência Manual</h3>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Profissional</label>
                <select id="nova-user" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de Ocorrência</label>
                <select id="nova-tipo" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm">
                    <option value="">Selecione...</option>
                    @foreach($tipos as $t)
                        <option value="{{ $t->id }}">{{ $t->codigo }} — {{ $t->nome }} ({{ $t->gravidade }}, -{{ $t->pontos_desconto }}pts)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Justificativa</label>
                <textarea id="nova-descricao" rows="3" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm" placeholder="Descreva o motivo..."></textarea>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
            <button onclick="document.getElementById('modal-nova').classList.add('hidden')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
            <button onclick="salvarManual()" id="btn-salvar-manual" class="rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm" style="background-color:#385776">Registrar</button>
        </div>
    </div>
</div>

{{-- MODAL: Detalhes --}}
<div id="modal-detalhes" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100" style="background-color:rgba(56,87,118,0.05)">
            <h3 class="text-lg font-semibold text-gray-800">Detalhes da Ocorrência</h3>
        </div>
        <div id="detalhes-body" class="px-6 py-5 text-sm text-gray-700 space-y-2">Carregando...</div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
            <button onclick="document.getElementById('modal-detalhes').classList.add('hidden')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Fechar</button>
        </div>
    </div>
</div>

{{-- MODAL: Avaliar Contestação --}}
<div id="modal-avaliar" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-100" style="background-color:rgba(56,87,118,0.05)">
            <h3 class="text-lg font-semibold text-gray-800">Avaliar Contestação</h3>
        </div>
        <div class="px-6 py-5 space-y-3">
            <p class="text-sm text-gray-600"><strong>Profissional:</strong> <span id="avaliar-nome"></span></p>
            <div>
                <p class="text-sm font-medium text-gray-700 mb-1">Argumentação:</p>
                <div id="avaliar-texto" class="rounded-lg bg-gray-50 p-3 text-sm text-gray-600 border border-gray-100"></div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
            <button onclick="document.getElementById('modal-avaliar').classList.add('hidden')" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100">Cancelar</button>
            <button onclick="enviarAvaliacao('rejeitada')" class="rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm bg-red-600 hover:bg-red-700">Rejeitar</button>
            <button onclick="enviarAvaliacao('aceita')" class="rounded-lg px-4 py-2 text-sm font-medium text-white shadow-sm bg-green-600 hover:bg-green-700">Aceitar</button>
        </div>
    </div>
</div>
<input type="hidden" id="avaliar-pen-id" value="">
@endsection

@push('scripts')
<script>
const csrfToken = '{{ csrf_token() }}';
const baseUrl = '{{ url("/gdp/penalizacoes") }}';

function executarScanner() {
    const btn = document.getElementById('btn-scanner');
    if (!confirm('Executar scanner de conformidade para {{ str_pad($mes, 2, "0", STR_PAD_LEFT) }}/{{ $ano }}?')) return;
    btn.disabled = true;
    btn.innerHTML = 'Escaneando...';
    fetch(baseUrl + '/scanner', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': csrfToken},
        credentials: 'same-origin',
        body: JSON.stringify({mes: {{ $mes }}, ano: {{ $ano }}})
    })
    .then(r => r.json())
    .then(data => {
        if (data.erro) alert('Erro: ' + data.erro);
        else { alert('Scanner concluído. Novas: ' + (data.novas || 0)); location.reload(); }
    })
    .catch(e => alert('Erro: ' + e.message))
    .finally(() => { btn.disabled = false; btn.innerHTML = 'Executar Scanner'; });
}

function salvarManual() {
    const userId = document.getElementById('nova-user').value;
    const tipoId = document.getElementById('nova-tipo').value;
    const descricao = document.getElementById('nova-descricao').value;
    if (!userId || !tipoId || !descricao.trim()) { alert('Preencha todos os campos.'); return; }
    document.getElementById('btn-salvar-manual').disabled = true;
    fetch(baseUrl + '/manual', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': csrfToken},
        credentials: 'same-origin',
        body: JSON.stringify({user_id: userId, tipo_id: tipoId, descricao: descricao, mes: {{ $mes }}, ano: {{ $ano }}})
    })
    .then(r => r.json())
    .then(data => {
        if (data.erro) alert('Erro: ' + data.erro);
        else { alert('Ocorrência registrada.'); location.reload(); }
    })
    .catch(e => alert('Erro: ' + e.message))
    .finally(() => { document.getElementById('btn-salvar-manual').disabled = false; });
}

function verDetalhes(id) {
    document.getElementById('detalhes-body').innerHTML = 'Carregando...';
    document.getElementById('modal-detalhes').classList.remove('hidden');
    fetch(baseUrl + '/' + id + '/detalhes', {headers: {'Accept':'application/json'}, credentials: 'same-origin'})
    .then(r => r.json())
    .then(d => {
        document.getElementById('detalhes-body').innerHTML =
            '<p><strong>Código:</strong> ' + (d.codigo||'—') + '</p>' +
            '<p><strong>Tipo:</strong> ' + (d.tipo||'—') + '</p>' +
            '<p><strong>Profissional:</strong> ' + (d.usuario||'—') + '</p>' +
            '<p><strong>Gravidade:</strong> ' + (d.gravidade||'—') + '</p>' +
            '<p><strong>Pontos:</strong> -' + (d.pontos||0) + '</p>' +
            '<p><strong>Origem:</strong> ' + (d.origem||'—') + '</p>' +
            '<p><strong>Descrição:</strong> ' + (d.descricao||'—') + '</p>' +
            (d.contestacao_texto ? '<hr class="my-2"><p><strong>Contestação:</strong> ' + d.contestacao_texto + '</p><p><strong>Status:</strong> ' + (d.contestacao_status||'Pendente') + '</p>' : '');
    })
    .catch(() => { document.getElementById('detalhes-body').innerHTML = 'Erro ao carregar.'; });
}

let _avaliarId = null;
function avaliarContestacao(id, nome, texto) {
    _avaliarId = id;
    document.getElementById('avaliar-pen-id').value = id;
    document.getElementById('avaliar-nome').textContent = nome;
    document.getElementById('avaliar-texto').textContent = texto || '(sem texto)';
    document.getElementById('modal-avaliar').classList.remove('hidden');
}

function enviarAvaliacao(decisao) {
    const id = document.getElementById('avaliar-pen-id').value;
    fetch(baseUrl + '/' + id + '/avaliar', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN': csrfToken},
        credentials: 'same-origin',
        body: JSON.stringify({decisao: decisao})
    })
    .then(r => r.json())
    .then(d => {
        if (d.erro) alert('Erro: ' + d.erro);
        else { alert('Contestação ' + decisao + '.'); location.reload(); }
    })
    .catch(e => alert('Erro: ' + e.message));
}
</script>
@endpush
