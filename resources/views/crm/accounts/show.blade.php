@extends('layouts.app')
@section('title', 'CRM - ' . $account->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.carteira') }}" class="hover:text-[#385776]">Carteira</a>
        <span>›</span>
        <span class="text-gray-700">{{ $account->name }}</span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Coluna principal (2/3) --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Header do account --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h1 class="text-2xl font-bold text-[#1B334A]">{{ $account->name }}</h1>
                        <div class="flex items-center gap-3 mt-2">
                            <span class="px-2 py-0.5 rounded text-xs {{ $account->kind === 'client' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $account->kind === 'client' ? 'Cliente DataJuri' : 'Prospect' }}
                            </span>
                            @php $lcColors = ['onboarding'=>'bg-blue-100 text-blue-700','ativo'=>'bg-green-100 text-green-700','adormecido'=>'bg-yellow-100 text-yellow-700','risco'=>'bg-red-100 text-red-700']; @endphp
                            <span class="px-2 py-0.5 rounded text-xs {{ $lcColors[$account->lifecycle] ?? '' }}">{{ ucfirst($account->lifecycle) }}</span>
                            @if($account->health_score !== null)
                                <span class="px-2 py-0.5 rounded text-xs font-medium {{ $account->health_score >= 70 ? 'bg-green-100 text-green-700' : ($account->health_score >= 40 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                    Saúde: {{ $account->health_score }}
                                </span>
                            @endif
                        </div>
                    </div>
                    <button onclick="document.getElementById('modal-new-opp').classList.remove('hidden')"
                            class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">
                        + Nova Oportunidade
                    </button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div><span class="text-gray-500">Doc:</span> <span class="text-gray-700">{{ $account->doc_digits ?? '—' }}</span></div>
                    <div><span class="text-gray-500">Email:</span> <span class="text-gray-700">{{ $account->email ?? '—' }}</span></div>
                    <div><span class="text-gray-500">Telefone:</span> <span class="text-gray-700">{{ $account->phone_e164 ?? '—' }}</span></div>
                    <div><span class="text-gray-500">Responsável:</span> <span class="text-gray-700">{{ $account->owner?->name ?? '—' }}</span></div>
                </div>
            </div>

            {{-- Oportunidades ativas --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Oportunidades</h2>
                @if($account->opportunities->isEmpty())
                    <p class="text-gray-400 text-sm">Nenhuma oportunidade registrada.</p>
                @else
                    <div class="space-y-3">
                        @foreach($account->opportunities as $opp)
                        <a href="{{ route('crm.opportunities.show', $opp->id) }}"
                           class="block border rounded-lg p-3 hover:bg-gray-50 transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-medium text-gray-800">{{ $opp->title }}</span>
                                    <div class="flex gap-2 mt-1">
                                        <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: {{ $opp->stage?->color ?? '#eee' }}20; color: {{ $opp->stage?->color ?? '#666' }}">
                                            {{ $opp->stage?->name ?? '?' }}
                                        </span>
                                        <span class="text-xs px-1.5 py-0.5 rounded {{ $opp->status === 'won' ? 'bg-green-100 text-green-700' : ($opp->status === 'lost' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                            {{ ucfirst($opp->status) }}
                                        </span>
                                    </div>
                                </div>
                                @if($opp->value_estimated)
                                    <span class="text-sm font-medium text-gray-700">R$ {{ number_format($opp->value_estimated, 2, ',', '.') }}</span>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Timeline --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Timeline</h2>
                @if($timeline->isEmpty())
                    <p class="text-gray-400 text-sm">Nenhum evento registrado.</p>
                @else
                    <div class="space-y-3">
                        @foreach($timeline as $item)
                        <div class="flex gap-3 text-sm border-l-2 {{ $item['type'] === 'event' ? 'border-blue-300' : 'border-green-300' }} pl-3 py-1">
                            <div class="flex-1">
                                <p class="text-gray-800">{{ $item['title'] }}</p>
                                @if(!empty($item['body']))
                                    <p class="text-gray-500 text-xs mt-0.5">{{ \Illuminate\Support\Str::limit($item['body'], 120) }}</p>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 flex-shrink-0">
                                {{ $item['date']?->format('d/m H:i') ?? '' }}
                                @if($item['user'] ?? null)
                                    <br>{{ $item['user'] }}
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- DataJuri Context --}}
            @if($djContext['available'])
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Contexto DataJuri</h2>

                {{-- Financeiro --}}
                @if(!empty($djContext['financeiro']))
                <div class="grid grid-cols-3 gap-4 mb-4">
                    <div class="bg-gray-50 rounded p-3">
                        <p class="text-xs text-gray-500">Contas a Receber</p>
                        <p class="font-medium">R$ {{ number_format($djContext['financeiro']['total_contas_receber'] ?? 0, 2, ',', '.') }}</p>
                    </div>
                    <div class="bg-gray-50 rounded p-3">
                        <p class="text-xs text-gray-500">Contas Vencidas</p>
                        <p class="font-medium text-red-600">R$ {{ number_format($djContext['financeiro']['total_contas_vencidas'] ?? 0, 2, ',', '.') }}</p>
                    </div>
                    <div class="bg-gray-50 rounded p-3">
                        <p class="text-xs text-gray-500">Contas Abertas</p>
                        <p class="font-medium">R$ {{ number_format($djContext['financeiro']['valor_contas_abertas'] ?? 0, 2, ',', '.') }}</p>
                    </div>
                </div>
                @endif

                {{-- Processos --}}
                @if(!empty($djContext['processos']))
                <h3 class="text-sm font-medium text-gray-600 mb-2">Processos ({{ count($djContext['processos']) }})</h3>
                <div class="space-y-2 mb-4">
                    @foreach(array_slice($djContext['processos'], 0, 5) as $proc)
                    <div class="text-sm border rounded p-2">
                        <span class="text-gray-700">{{ $proc->numero ?? $proc->pasta ?? '#' . $proc->id }}</span>
                        @if($proc->status ?? null)
                            <span class="text-xs text-gray-500 ml-2">{{ $proc->status }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif

                {{-- Contratos --}}
                @if(!empty($djContext['contratos']))
                <h3 class="text-sm font-medium text-gray-600 mb-2">Contratos ({{ count($djContext['contratos']) }})</h3>
                <div class="space-y-2">
                    @foreach(array_slice($djContext['contratos'], 0, 5) as $ct)
                    <div class="text-sm border rounded p-2">
                        <span class="text-gray-700">{{ $ct->numero ?? '#' . $ct->id }}</span>
                        @if($ct->valor ?? null)
                            <span class="text-xs text-gray-500 ml-2">R$ {{ number_format($ct->valor, 2, ',', '.') }}</span>
                        @endif
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif
        </div>

        {{-- Painel direito (1/3) — Gerencial CRM --}}
        <div class="space-y-6">
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Gestão CRM</h2>
                <form id="form-crm-update" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Responsável</label>
                        <select name="owner_user_id" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                            <option value="">Sem responsável</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $account->owner_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Ciclo de Vida</label>
                        <select name="lifecycle" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                            @foreach(['onboarding','ativo','adormecido','risco'] as $lc)
                                <option value="{{ $lc }}" {{ $account->lifecycle === $lc ? 'selected' : '' }}>{{ ucfirst($lc) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Saúde (0-100)</label>
                        <input type="number" name="health_score" min="0" max="100" value="{{ $account->health_score }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Próxima ação</label>
                        <input type="date" name="next_touch_at" value="{{ $account->next_touch_at?->format('Y-m-d') }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Notas</label>
                        <textarea name="notes" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">{{ $account->notes }}</textarea>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Tags (separar por vírgula)</label>
                        <input type="text" name="tags" value="{{ implode(', ', $account->getTagsArray()) }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <button type="button" onclick="saveAccountCrm()" class="w-full px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">
                        Salvar Alterações
                    </button>
                    <p id="save-feedback" class="text-xs text-green-600 hidden text-center">Salvo com sucesso!</p>
                </form>
            </div>

            {{-- Quick Activity --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-[#1B334A] mb-3">Registrar Atividade</h2>
                <form id="form-activity" class="space-y-3">
                    @csrf
                    <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="note">Nota</option>
                        <option value="call">Ligação</option>
                        <option value="meeting">Reunião</option>
                        <option value="task">Tarefa</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                    <input type="text" name="title" placeholder="Título" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <textarea name="body" placeholder="Detalhes (opcional)" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                    <button type="button" onclick="saveActivity()" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                        Registrar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- Modal Nova Oportunidade --}}
<div id="modal-new-opp" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-[#1B334A] mb-4">Nova Oportunidade</h3>
        <form method="POST" action="{{ route('crm.accounts.create-opp', $account->id) }}" class="space-y-3">
            @csrf
            <input type="text" name="title" placeholder="Título" class="w-full border rounded-lg px-3 py-2 text-sm">
            <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="aquisicao">Aquisição</option>
                <option value="carteira">Carteira</option>
            </select>
            <input type="text" name="area" placeholder="Área do Direito (opcional)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <input type="text" name="source" placeholder="Fonte (WhatsApp, Indicação...)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#385776] text-white rounded-lg text-sm">Criar</button>
                <button type="button" onclick="document.getElementById('modal-new-opp').classList.add('hidden')"
                        class="px-4 py-2 border rounded-lg text-sm">Cancelar</button>
            </div>
        </form>
    </div>
</div>

<script>
function saveAccountCrm() {
    const form = document.getElementById('form-crm-update');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token') body[k] = v; });

    // Converter tags string para JSON
    if (body.tags) body.tags = JSON.stringify(body.tags.split(',').map(t => t.trim()).filter(Boolean));

    fetch('{{ route("crm.accounts.update", $account->id) }}', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            const fb = document.getElementById('save-feedback');
            fb.classList.remove('hidden');
            setTimeout(() => fb.classList.add('hidden'), 2000);
        }
    });
}

function saveActivity() {
    const form = document.getElementById('form-activity');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token') body[k] = v; });

    fetch('{{ route("crm.accounts.store-activity", $account->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
    });
}
</script>
@endsection
