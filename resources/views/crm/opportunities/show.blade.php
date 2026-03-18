@extends('layouts.app')
@section('title', 'CRM - ' . $opp->title)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.pipeline') }}" class="hover:text-[#385776]">Pipeline</a>
        <span>›</span>
        <a href="{{ route('crm.accounts.show', $opp->account_id) }}" class="hover:text-[#385776]">{{ $opp->account?->name }}</a>
        <span>›</span>
        <span class="text-gray-700">{{ $opp->title }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Coluna principal (2/3) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Header --}}
            
            {{-- Cadência de Follow-up --}}
            @if(isset($cadenceTasks) && $cadenceTasks->isNotEmpty())
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">📋 Cadência de Follow-up</h2>
                    <span class="text-xs text-gray-400">{{ $cadenceTasks->where('completed_at', '!=', null)->count() }}/{{ $cadenceTasks->count() }} concluídas</span>
                </div>
                {{-- Progress bar --}}
                @php $cadPct = $cadenceTasks->count() > 0 ? round($cadenceTasks->whereNotNull('completed_at')->count() / $cadenceTasks->count() * 100) : 0; @endphp
                <div class="w-full bg-gray-100 rounded-full h-2 mb-4">
                    <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $cadPct }}%"></div>
                </div>
                <div class="space-y-2">
                    @foreach($cadenceTasks as $ct)
                    @php
                        $isDone = $ct->completed_at !== null;
                        $isOverdue = !$isDone && $ct->due_date && $ct->due_date->isPast();
                        $isToday = !$isDone && $ct->due_date && $ct->due_date->isToday();
                    @endphp
                    <div class="flex items-center gap-3 p-3 rounded-lg border {{ $isDone ? 'bg-green-50 border-green-200' : ($isOverdue ? 'bg-red-50 border-red-200' : ($isToday ? 'bg-blue-50 border-blue-200' : 'bg-white')) }}">
                        @if($isDone)
                            <span class="text-green-500 text-lg flex-shrink-0">✅</span>
                        @elseif($isOverdue)
                            <button onclick="completeCadenceTask({{ $ct->id }})" class="w-6 h-6 rounded-full border-2 border-red-400 hover:bg-red-100 flex-shrink-0 transition" title="Marcar concluída"></button>
                        @else
                            <button onclick="completeCadenceTask({{ $ct->id }})" class="w-6 h-6 rounded-full border-2 border-gray-300 hover:bg-gray-100 flex-shrink-0 transition" title="Marcar concluída"></button>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="font-medium text-sm {{ $isDone ? 'text-green-700 line-through' : 'text-gray-800' }}">
                                Passo {{ $ct->step_number }}: {{ $ct->title }}
                            </p>
                            @if($ct->description)<p class="text-xs text-gray-500 mt-0.5">{{ $ct->description }}</p>@endif
                        </div>
                        <div class="text-right flex-shrink-0 text-xs">
                            @if($isDone)
                                <span class="text-green-600">{{ $ct->completed_at->format('d/m') }}</span>
                            @elseif($isOverdue)
                                <span class="text-red-600 font-medium">Vencida {{ $ct->due_date->diffForHumans() }}</span>
                            @elseif($isToday)
                                <span class="text-blue-600 font-medium">Hoje</span>
                            @else
                                <span class="text-gray-400">{{ $ct->due_date->format('d/m') }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

<div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-[#1B334A]">{{ $opp->title }}</h1>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="text-xs px-2 py-0.5 rounded {{ $opp->type === 'aquisicao' ? 'bg-blue-100 text-blue-600' : 'bg-purple-100 text-purple-600' }}">
                                {{ $opp->type === 'aquisicao' ? 'Aquisição' : 'Carteira' }}
                            </span>
                            <span class="text-xs px-2 py-0.5 rounded {{ $opp->status === 'won' ? 'bg-green-100 text-green-700' : ($opp->status === 'lost' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                {{ ucfirst($opp->status) }}
                            </span>
                            @if($opp->isOverdue())
                                <span class="text-xs text-red-600 font-medium">{{ $opp->overdueDays() }} dias em atraso</span>
                            @endif
                        </div>
                    </div>
                    @if($opp->value_estimated)
                        <p class="text-xl font-bold text-[#1B334A]">R$ {{ number_format($opp->value_estimated, 2, ',', '.') }}</p>
                    @endif
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div><span class="text-gray-500">Cliente:</span> <a href="{{ route('crm.accounts.show', $opp->account_id) }}" class="text-[#385776] hover:underline">{{ $opp->account?->name ?? '—' }}</a></div>
                    <div><span class="text-gray-500">Área:</span> <span class="text-gray-700">{{ $opp->area ?? '—' }}</span></div>
                    <div><span class="text-gray-500">Fonte:</span> <span class="text-gray-700">{{ $opp->source ?? '—' }}</span></div>
                    <div><span class="text-gray-500">Responsável:</span> <span class="text-gray-700">{{ $opp->owner?->name ?? '—' }}</span></div>
                </div>
            </div>

            {{-- Stage Progress --}}
            @if($opp->isOpen())
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-[#1B334A] mb-3">Estágio Atual</h2>
                <div class="flex gap-1">
                    @foreach($stages as $st)
                        <button onclick="moveStage({{ $st->id }})"
                                class="flex-1 py-2 text-xs text-center rounded transition
                                {{ $opp->stage_id === $st->id ? 'text-white font-bold' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' }}"
                                style="{{ $opp->stage_id === $st->id ? 'background-color:' . $st->color : '' }}">
                            {{ $st->name }}
                        </button>
                    @endforeach
                </div>
                @if($opp->isOpen())
                <div class="flex gap-2 mt-3">
                    <button onclick="markWon()" class="px-4 py-2 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">✓ Marcar Ganho</button>
                    <button onclick="document.getElementById('modal-lost').classList.remove('hidden')" class="px-4 py-2 bg-red-600 text-white rounded-lg text-xs hover:bg-red-700">✗ Marcar Perdido</button>
                </div>
                @endif
            </div>
            @endif

            {{-- Atividades --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">Atividades</h2>
                </div>
                <form id="form-opp-activity" class="flex gap-2 mb-4">
                    @csrf
                    <select name="type" class="border rounded-lg px-2 py-1.5 text-sm">
                        <option value="note">Nota</option>
                        <option value="call">Ligação</option>
                        <option value="meeting">Reunião</option>
                        <option value="task">Tarefa</option>
                    </select>
                    <input type="text" name="title" placeholder="Título da atividade" required class="flex-1 border rounded-lg px-3 py-1.5 text-sm">
                    <button type="button" onclick="addActivity()" class="px-4 py-1.5 bg-[#385776] text-white rounded-lg text-sm">Adicionar</button>
                </form>
                <div class="space-y-2" id="activities-list">
                    @forelse($opp->activities as $act)
                    <div class="flex items-center gap-3 text-sm border rounded p-2 {{ $act->isDone() ? 'bg-gray-50 opacity-60' : '' }}">
                        @if(!$act->isDone())
                            <button onclick="completeActivity({{ $act->id }})" class="text-gray-400 hover:text-green-600" title="Concluir">○</button>
                        @else
                            <span class="text-green-600">●</span>
                        @endif
                        <span class="px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500">{{ $act->type }}</span>
                        <span class="flex-1 {{ $act->isDone() ? 'line-through text-gray-400' : 'text-gray-700' }}">{{ $act->title }}</span>
                        <span class="text-xs text-gray-400">{{ $act->created_at->format('d/m H:i') }}</span>
                    </div>
                    @empty
                    <p class="text-gray-400 text-sm">Nenhuma atividade registrada.</p>
                    @endforelse
                </div>
            </div>

            {{-- Events Timeline --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Histórico</h2>
                <div class="space-y-3">
                    @forelse($events as $ev)
                    <div class="flex gap-3 text-sm border-l-2 border-blue-300 pl-3 py-1">
                        <div class="flex-1">
                            <p class="text-gray-700">{{ ucfirst(str_replace('_', ' ', $ev->type)) }}</p>
                            @if($ev->payload)
                                <p class="text-xs text-gray-400">{{ json_encode($ev->payload, JSON_UNESCAPED_UNICODE) }}</p>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400 flex-shrink-0">{{ $ev->happened_at?->format('d/m H:i') }}</span>
                    </div>
                    @empty
                    <p class="text-gray-400 text-sm">Nenhum evento registrado.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Painel direito (1/3) --}}
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-[#1B334A] mb-3">Editar Oportunidade</h2>
                <form id="form-opp-edit" class="space-y-3">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Título</label>
                        <input type="text" name="title" value="{{ $opp->title }}" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Valor Estimado</label>
                        <input type="number" name="value_estimated" step="0.01" value="{{ $opp->value_estimated }}" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Área</label>
                        <input type="text" name="area" value="{{ $opp->area }}" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Responsável</label>
                        <select name="owner_user_id" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                            <option value="">—</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $opp->owner_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Próxima Ação</label>
                        <input type="date" name="next_action_at" value="{{ $opp->next_action_at?->format('Y-m-d') }}" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <button type="button" onclick="saveOpp()" class="w-full px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Salvar</button>
                    <p id="opp-feedback" class="text-xs text-green-600 hidden text-center">Salvo!</p>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal Perdido --}}
<div id="modal-lost" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-sm">
        <h3 class="text-lg font-semibold text-[#1B334A] mb-3">Motivo da Perda</h3>
        <input type="text" id="lost-reason" placeholder="Ex: Preço, Concorrência, Desistiu..." class="w-full border rounded-lg px-3 py-2 text-sm mb-3">
        <div class="flex gap-2">
            <button onclick="markLost()" class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm">Confirmar</button>
            <button onclick="document.getElementById('modal-lost').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm">Cancelar</button>
        </div>
    </div>
</div>

<script>
const csrf = '{{ csrf_token() }}';
const oppId = {{ $opp->id }};

function moveStage(stageId) {
    fetch(`/crm/pipeline/${oppId}/move`, {
        method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
        body: JSON.stringify({stage_id: stageId})
    }).then(r => r.json()).then(d => { if(d.ok) location.reload(); });
}

function markWon() {
    if (!confirm('Confirmar oportunidade como GANHA?')) return;
    fetch(`/crm/pipeline/${oppId}/won`, {
        method: 'POST', headers: {'X-CSRF-TOKEN':csrf}
    }).then(r => r.json()).then(d => { if(d.ok) location.reload(); });
}

function markLost() {
    const reason = document.getElementById('lost-reason').value;
    fetch(`/crm/pipeline/${oppId}/lost`, {
        method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
        body: JSON.stringify({reason})
    }).then(r => r.json()).then(d => { if(d.ok) location.reload(); });
}

function saveOpp() {
    const form = document.getElementById('form-opp-edit');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token' && v) body[k] = v; });
    fetch(`/crm/oportunidades/${oppId}`, {
        method: 'PUT', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => {
        if(d.ok) { const fb = document.getElementById('opp-feedback'); fb.classList.remove('hidden'); setTimeout(()=>fb.classList.add('hidden'),2000); }
    });
}

function addActivity() {
    const form = document.getElementById('form-opp-activity');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token') body[k] = v; });
    fetch(`/crm/oportunidades/${oppId}/activities`, {
        method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':csrf},
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => { if(d.ok) location.reload(); });
}

function completeActivity(actId) {
    fetch(`/crm/oportunidades/${oppId}/activities/${actId}/complete`, {
        method: 'POST', headers: {'X-CSRF-TOKEN':csrf}
    }).then(r => r.json()).then(d => { if(d.ok) location.reload(); });
}

// ── Modal Cadência Follow-up (DOR 1 — Accountability) ──
var _cadTaskId = null;

function completeCadenceTask(taskId) {
    _cadTaskId = taskId;
    document.getElementById('modal-cadencia').classList.remove('hidden');
    document.getElementById('modal-cadencia').classList.add('flex');
    document.getElementById('cad-notas').value = '';
    document.getElementById('cad-objecao-wrap').classList.add('hidden');
    document.getElementById('cad-resultado').value = 'contato_realizado';
    document.getElementById('cad-notas').focus();
}

function fecharModalCadencia() {
    document.getElementById('modal-cadencia').classList.add('hidden');
    document.getElementById('modal-cadencia').classList.remove('flex');
    _cadTaskId = null;
}

function onResultadoChange() {
    var val = document.getElementById('cad-resultado').value;
    var wrap = document.getElementById('cad-objecao-wrap');
    if (val === 'objecao' || val === 'recusa') {
        wrap.classList.remove('hidden');
    } else {
        wrap.classList.add('hidden');
    }
}

function confirmarCadencia() {
    var resultado = document.getElementById('cad-resultado').value;
    var notas = document.getElementById('cad-notas').value.trim();
    var objecao = document.getElementById('cad-objecao') ? document.getElementById('cad-objecao').value : '';

    if (!notas) {
        document.getElementById('cad-notas').classList.add('border-red-400');
        document.getElementById('cad-notas').placeholder = 'Campo obrigatório — descreva o que aconteceu';
        document.getElementById('cad-notas').focus();
        return;
    }

    var btn = document.querySelector('#modal-cadencia button[onclick="confirmarCadencia()"]');
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    fetch('{{ url("/crm/oportunidades") }}/{{ $opp->id }}/cadence/' + _cadTaskId + '/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ resultado: resultado, notas: notas, objecao: objecao })
    }).then(r => r.json()).then(d => {
        if (d.ok) { fecharModalCadencia(); location.reload(); }
        else { alert(d.error || 'Erro'); btn.disabled = false; btn.textContent = 'Confirmar'; }
    }).catch(e => { alert('Erro: ' + e.message); btn.disabled = false; btn.textContent = 'Confirmar'; });
}

document.getElementById('modal-cadencia').addEventListener('click', function(e) {
    if (e.target === this) fecharModalCadencia();
});


</script>

{{-- Modal Cadência Follow-up --}}
<div id="modal-cadencia" class="hidden fixed inset-0 bg-black/50 z-50 items-center justify-center" style="backdrop-filter: blur(2px);">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 overflow-hidden">
        <div class="px-6 py-4 border-b" style="background: linear-gradient(135deg, #1B334A, #385776);">
            <h3 class="text-white font-semibold text-sm">📋 Concluir Etapa da Cadência</h3>
        </div>
        <div class="p-6 space-y-4">
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Resultado *</label>
                <select id="cad-resultado" onchange="onResultadoChange()" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="contato_realizado">Contato realizado</option>
                    <option value="sem_resposta">Sem resposta</option>
                    <option value="reagendado">Reagendado</option>
                    <option value="objecao">Objeção do cliente</option>
                    <option value="recusa">Recusa</option>
                    <option value="avancar_proposta">Avançar para proposta</option>
                </select>
            </div>
            <div id="cad-objecao-wrap" class="hidden">
                <label class="text-xs font-medium text-gray-600 block mb-1">Motivo da objeção *</label>
                <select id="cad-objecao" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="preco">Preço</option>
                    <option value="prazo">Prazo</option>
                    <option value="concorrencia">Concorrência</option>
                    <option value="desinteresse">Desinteresse</option>
                    <option value="momento">Não é o momento</option>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">O que aconteceu? *</label>
                <textarea id="cad-notas" rows="3" placeholder="Descreva o resultado do contato, objeções, próximos passos..." class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
        </div>
        <div class="px-6 py-3 border-t bg-gray-50 flex justify-end gap-3">
            <button onclick="fecharModalCadencia()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
            <button onclick="confirmarCadencia()" class="px-4 py-2 bg-[#385776] text-white text-sm font-medium rounded-lg hover:bg-[#1B334A]">Confirmar</button>
        </div>
    </div>
</div>

@endsection
