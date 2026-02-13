@extends('layouts.app')

@section('title', 'CRM - ' . $opportunity->title)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('crm.pipeline') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <h1 class="text-xl font-bold text-gray-800">{{ $opportunity->title }}</h1>
                <p class="text-sm text-gray-500">{{ $opportunity->account->name }} &middot; {{ $opportunity->account->type }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2 mt-3 md:mt-0">
            @if($opportunity->isOpen())
                {{-- Mover estágio --}}
                <form method="POST" action="{{ route('crm.opportunity.move-stage', $opportunity->id) }}" class="flex items-center gap-1">
                    @csrf
                    <select name="stage_id" class="text-sm border border-gray-300 rounded-lg px-2 py-1.5">
                        @foreach($stages as $stg)
                            @if(!$stg->isTerminal())
                                <option value="{{ $stg->id }}" {{ $opportunity->stage_id == $stg->id ? 'selected' : '' }}>{{ $stg->name }}</option>
                            @endif
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1.5 text-sm bg-[#385776] text-white rounded-lg hover:bg-[#1B334A]">Mover</button>
                </form>

                {{-- Ganho --}}
                <button onclick="document.getElementById('modal-won').classList.remove('hidden')"
                        class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Ganho</button>

                {{-- Perda --}}
                <button onclick="document.getElementById('modal-lost').classList.remove('hidden')"
                        class="px-3 py-1.5 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600">Perda</button>
            @else
                <span class="px-3 py-1.5 text-sm rounded-lg font-medium {{ $opportunity->status === 'won' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700' }}">
                    {{ $opportunity->status === 'won' ? 'GANHO' : 'PERDIDO' }}
                    {{ $opportunity->status === 'lost' && $opportunity->lost_reason ? '- ' . $opportunity->lost_reason : '' }}
                </span>
            @endif
        </div>
    </div>

    {{-- Status badge --}}
    <div class="flex items-center gap-3 mb-6">
        <span class="text-xs px-2.5 py-1 rounded-full font-medium" style="background-color: {{ $opportunity->stage->color }}20; color: {{ $opportunity->stage->color }};">
            {{ $opportunity->stage->name }}
        </span>
        @if($opportunity->isOverdue())
            <span class="text-xs px-2.5 py-1 rounded-full bg-red-100 text-red-700 font-medium">Ação atrasada</span>
        @endif
        @if($opportunity->owner)
            <span class="text-xs text-gray-500">Responsável: {{ $opportunity->owner->name }}</span>
        @endif
    </div>

    {{-- Grid 3 colunas --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Coluna 1: Resumo --}}
        <div class="space-y-4">
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Resumo</h3>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Área</dt><dd class="font-medium">{{ $opportunity->area ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Origem</dt><dd class="font-medium">{{ $opportunity->source ?? '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Valor estimado</dt><dd class="font-medium">{{ $opportunity->value_estimated ? 'R$ ' . number_format($opportunity->value_estimated, 2, ',', '.') : '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Próx. ação</dt><dd class="font-medium {{ $opportunity->isOverdue() ? 'text-red-600' : '' }}">{{ $opportunity->next_action_at ? $opportunity->next_action_at->format('d/m/Y H:i') : '-' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Criada em</dt><dd>{{ $opportunity->created_at->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Dias no estágio</dt><dd>{{ $opportunity->daysInCurrentStage() }}d</dd></div>
                </dl>
            </div>

            {{-- Identidades do Account --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Contato</h3>
                <div class="space-y-1.5 text-sm">
                    @foreach($opportunity->account->identities as $ident)
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] uppercase bg-gray-100 text-gray-500 rounded px-1.5 py-0.5 w-16 text-center">{{ $ident->kind }}</span>
                            <span class="text-gray-700">{{ $ident->value }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- DataJuri --}}
            @if($datajuriData['has_datajuri'])
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">DataJuri</h3>
                    @if(count($datajuriData['processos']) > 0)
                        <p class="text-xs text-gray-500 mb-2">Processos ({{ count($datajuriData['processos']) }})</p>
                        @foreach($datajuriData['processos'] as $proc)
                            <div class="text-xs border-b border-gray-100 py-1.5">
                                <p class="font-medium text-gray-700">{{ $proc->numero ?? $proc->pasta ?? 'S/N' }}</p>
                                <p class="text-gray-500">{{ $proc->titulo ?? $proc->status ?? '' }}</p>
                            </div>
                        @endforeach
                    @endif
                    @if(count($datajuriData['contas_receber']) > 0)
                        <p class="text-xs text-gray-500 mt-3 mb-2">Contas a receber ({{ count($datajuriData['contas_receber']) }})</p>
                        @foreach(array_slice($datajuriData['contas_receber'], 0, 5) as $cr)
                            <div class="text-xs border-b border-gray-100 py-1.5 flex justify-between">
                                <span>{{ isset($cr->data_vencimento) ? \Carbon\Carbon::parse($cr->data_vencimento)->format('d/m/Y') : '-' }}</span>
                                <span class="font-medium">R$ {{ number_format($cr->valor ?? 0, 2, ',', '.') }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>
            @endif
        </div>

        {{-- Coluna 2: Timeline --}}
        <div>
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Timeline</h3>
                <div class="space-y-3 max-h-[60vh] overflow-y-auto">
                    @forelse($opportunity->events as $event)
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 mt-1">
                                @switch($event->type)
                                    @case('stage_changed') <span class="text-blue-500">&#9679;</span> @break
                                    @case('won') <span class="text-emerald-500">&#9679;</span> @break
                                    @case('lost') <span class="text-red-500">&#9679;</span> @break
                                    @case('activity_created') <span class="text-yellow-500">&#9679;</span> @break
                                    @case('activity_completed') <span class="text-green-500">&#9679;</span> @break
                                    @default <span class="text-gray-400">&#9679;</span>
                                @endswitch
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-700">
                                    @switch($event->type)
                                        @case('opportunity_created') Oportunidade criada @break
                                        @case('stage_changed') Movido de <strong>{{ $event->payload['from'] ?? '?' }}</strong> para <strong>{{ $event->payload['to'] ?? '?' }}</strong> @break
                                        @case('won') Marcado como <strong class="text-emerald-600">GANHO</strong> @if($event->payload['value'] ?? null) (R$ {{ number_format($event->payload['value'], 2, ',', '.') }}) @endif @break
                                        @case('lost') Marcado como <strong class="text-red-600">PERDIDO</strong> @if($event->payload['reason'] ?? null) — {{ $event->payload['reason'] }} @endif @break
                                        @case('activity_created') Atividade: {{ $event->payload['title'] ?? '' }} @break
                                        @case('activity_completed') Concluída: {{ $event->payload['title'] ?? '' }} @break
                                        @case('opportunity_updated') Oportunidade atualizada @break
                                        @default {{ $event->type }}
                                    @endswitch
                                </p>
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $event->happened_at->format('d/m/Y H:i') }}</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-4">Nenhum evento registrado</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Coluna 3: Atividades --}}
        <div class="space-y-4">
            {{-- Formulário nova atividade --}}
            @if($opportunity->isOpen())
                <div class="bg-white rounded-xl border border-gray-200 p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Nova Atividade</h3>
                    <form method="POST" action="{{ route('crm.activity.store') }}">
                        @csrf
                        <input type="hidden" name="opportunity_id" value="{{ $opportunity->id }}">
                        <div class="space-y-2">
                            <select name="type" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2">
                                <option value="task">Tarefa</option>
                                <option value="call">Ligação</option>
                                <option value="meeting">Reunião</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="email">E-mail</option>
                                <option value="note">Nota</option>
                            </select>
                            <input type="text" name="title" placeholder="Título da atividade" required
                                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#385776] focus:border-[#385776]">
                            <textarea name="body" rows="2" placeholder="Detalhes (opcional)"
                                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#385776] focus:border-[#385776]"></textarea>
                            <input type="datetime-local" name="due_at"
                                   class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 focus:ring-[#385776] focus:border-[#385776]">
                            <button type="submit" class="w-full px-4 py-2 text-sm bg-[#385776] text-white rounded-lg hover:bg-[#1B334A]">Criar Atividade</button>
                        </div>
                    </form>
                </div>
            @endif

            {{-- Lista de atividades --}}
            <div class="bg-white rounded-xl border border-gray-200 p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">Atividades</h3>
                <div class="space-y-2 max-h-[50vh] overflow-y-auto">
                    @forelse($opportunity->activities as $act)
                        <div class="flex items-start gap-2 p-2 rounded-lg {{ $act->isDone() ? 'bg-gray-50 opacity-60' : ($act->isOverdue() ? 'bg-red-50' : 'bg-white') }} border border-gray-100">
                            @if(!$act->isDone() && $opportunity->isOpen())
                                <form method="POST" action="{{ route('crm.activity.complete', $act->id) }}" class="flex-shrink-0 mt-0.5">
                                    @csrf
                                    <button type="submit" class="w-5 h-5 rounded border-2 border-gray-300 hover:border-emerald-500 hover:bg-emerald-50 transition-colors" title="Concluir"></button>
                                </form>
                            @else
                                <span class="flex-shrink-0 w-5 h-5 rounded bg-emerald-100 text-emerald-600 flex items-center justify-center text-xs mt-0.5">&#10003;</span>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="text-sm {{ $act->isDone() ? 'line-through text-gray-400' : 'text-gray-700' }}">{{ $act->title }}</p>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[10px] bg-gray-100 text-gray-500 rounded px-1.5 py-0.5">{{ $act->type }}</span>
                                    @if($act->due_at)
                                        <span class="text-[10px] {{ $act->isOverdue() ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                                            {{ $act->due_at->format('d/m H:i') }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-400 text-center py-4">Nenhuma atividade</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Ganho --}}
<div id="modal-won" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Marcar como Ganho</h3>
        <form method="POST" action="{{ route('crm.opportunity.won', $opportunity->id) }}">
            @csrf
            <label class="block text-sm text-gray-600 mb-1">Valor final (opcional)</label>
            <input type="number" name="final_value" step="0.01" value="{{ $opportunity->value_estimated }}"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4 focus:ring-emerald-500 focus:border-emerald-500">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-700">Confirmar Ganho</button>
                <button type="button" onclick="document.getElementById('modal-won').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal Perda --}}
<div id="modal-lost" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Marcar como Perdido</h3>
        <form method="POST" action="{{ route('crm.opportunity.lost', $opportunity->id) }}">
            @csrf
            <label class="block text-sm text-gray-600 mb-1">Motivo da perda</label>
            <select name="lost_reason" required class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4">
                <option value="">Selecione...</option>
                <option value="Preço alto">Preço alto</option>
                <option value="Concorrência">Concorrência</option>
                <option value="Sem retorno">Sem retorno do cliente</option>
                <option value="Desistiu da ação">Desistiu da ação judicial</option>
                <option value="Não qualificado">Não qualificado</option>
                <option value="Outro">Outro</option>
            </select>
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">Confirmar Perda</button>
                <button type="button" onclick="document.getElementById('modal-lost').classList.add('hidden')"
                        class="px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">Cancelar</button>
            </div>
        </form>
    </div>
</div>
@endsection
