@extends('layouts.app')
@section('title', $processo->protocolo . ' — ' . $processo->titulo)

@section('content')
@php
    $atrasado = $processo->prazo_final && $processo->prazo_final->isPast() && !in_array($processo->status, ['concluido','cancelado']);
    $comigo = $processo->com_user_id === auth()->id();
@endphp

<div class="max-w-full mx-auto px-6 py-6"
     x-data="{ showAtoModal: false, showTramitarModal: false, showStatusModal: false, sidePanel: '' }">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.dashboard') }}" class="hover:text-[#385776]">CRM</a>
        <span>›</span>
        <a href="{{ route('crm.admin-processes.index') }}" class="hover:text-[#385776]">Processos Administrativos</a>
        <span>›</span>
        <span class="text-gray-700 font-medium">{{ $processo->protocolo }}</span>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- ═══ HEADER ═══ --}}
    <div class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
        <div class="bg-gradient-to-r from-[#1B334A] to-[#385776] px-6 py-5">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-3 flex-wrap mb-1">
                        <span class="font-mono text-blue-200 text-sm font-semibold">{{ $processo->protocolo }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">{{ $processo->tipoLabel() }}</span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium
                            {{ $processo->status === 'concluido' ? 'bg-green-400/30 text-green-100' :
                               ($processo->status === 'cancelado' ? 'bg-gray-400/30 text-gray-200' :
                               ($processo->status === 'suspenso'  ? 'bg-red-400/30 text-red-100' :
                               'bg-blue-400/30 text-blue-100')) }}">
                            {{ $processo->statusLabel() }}
                        </span>
                        @if($atrasado)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/80 text-white">Atrasado</span>
                        @endif
                    </div>
                    <h1 class="text-xl font-bold text-white leading-tight">{{ $processo->titulo }}</h1>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-1 mt-2 text-sm text-blue-200">
                        <span>Cliente: <strong class="text-white">{{ $processo->account->name ?? '-' }}</strong></span>
                        <span>Responsável: <strong class="text-white">{{ $processo->owner->name ?? '-' }}</strong></span>
                        @if($processo->orgao_destino)
                            <span>Órgão: {{ $processo->orgao_destino }}</span>
                        @endif
                        @if($processo->numero_externo)
                            <span>Nº ext.: {{ $processo->numero_externo }}</span>
                        @endif
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <div class="px-3 py-2 rounded-lg {{ $comigo ? 'bg-green-500/20 border border-green-400/30' : 'bg-white/10' }}">
                        <p class="text-xs text-blue-200">Na mesa de</p>
                        <p class="text-sm font-bold text-white">{{ $processo->comUsuario->name ?? $processo->owner->name ?? '-' }}</p>
                        @if($comigo)
                            <p class="text-xs text-green-300 mt-0.5">Está com você</p>
                        @endif
                    </div>
                    @if($processo->prazo_final)
                        <p class="text-xs text-blue-300 mt-2">Prazo: <span class="{{ $atrasado ? 'text-red-300 font-bold' : 'text-white' }}">{{ $processo->prazo_final->format('d/m/Y') }}</span></p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Barra de ações --}}
        <div class="px-6 py-3 bg-gray-50 border-t flex flex-wrap gap-2">
            <button @click="showAtoModal = true"
                    class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-[#1B334A] text-white rounded-lg hover:bg-[#385776] font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Incluir Documento
            </button>
            <button @click="showTramitarModal = true"
                    class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-100 text-gray-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Tramitar
            </button>
            <button @click="showStatusModal = true"
                    class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-100 text-gray-700">
                Alterar status
            </button>
            <a href="{{ route('crm.admin-processes.edit', $processo->id) }}"
               class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-100 text-gray-700">
                Editar dados
            </a>

            <div class="ml-auto flex gap-2">
                <button @click="sidePanel = sidePanel === 'tramitacoes' ? '' : 'tramitacoes'"
                        :class="sidePanel==='tramitacoes' ? 'bg-[#1B334A] text-white' : 'bg-white text-gray-700'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-gray-100">
                    Tramitações ({{ $processo->tramitacoes->count() }})
                </button>
                <button @click="sidePanel = sidePanel === 'etapas' ? '' : 'etapas'"
                        :class="sidePanel==='etapas' ? 'bg-[#1B334A] text-white' : 'bg-white text-gray-700'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-gray-100">
                    Etapas ({{ $processo->steps->count() }})
                </button>
                <button @click="sidePanel = sidePanel === 'checklist' ? '' : 'checklist'"
                        :class="sidePanel==='checklist' ? 'bg-[#1B334A] text-white' : 'bg-white text-gray-700'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-gray-100">
                    Checklist ({{ $processo->checklist->where('status','pendente')->count() }} pend.)
                </button>
                <button @click="sidePanel = sidePanel === 'timeline' ? '' : 'timeline'"
                        :class="sidePanel==='timeline' ? 'bg-[#1B334A] text-white' : 'bg-white text-gray-700'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-gray-100">
                    Timeline
                </button>
                <a href="{{ route('crm.accounts.show', $processo->account_id) }}"
                   class="text-xs px-3 py-1.5 bg-white border rounded-lg hover:bg-gray-100 text-gray-700">
                    Ficha do cliente
                </a>
            </div>
        </div>
    </div>

    {{-- ═══ CONTEÚDO: ÁRVORE DE ATOS + SIDE PANEL ═══ --}}
    <div class="flex gap-6">

        {{-- Árvore de Atos (principal) --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-[#1B334A]">Árvore do Processo</h2>
                <span class="text-xs text-gray-400">{{ $processo->atos->count() }} documento(s)</span>
            </div>

            @if($processo->atos->isEmpty())
                <div class="bg-white rounded-xl border shadow-sm py-16 text-center text-gray-400">
                    <p class="text-sm">Nenhum documento registrado neste processo.</p>
                    <button @click="showAtoModal = true" class="mt-3 text-[#385776] hover:underline text-sm font-medium">
                        Incluir primeiro documento
                    </button>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($processo->atos as $ato)
                    @php
                        $colors = $ato->tipoColor();
                    @endphp
                    <div class="bg-white rounded-xl border shadow-sm overflow-hidden {{ $colors }}" x-data="{ expanded: false }">
                        {{-- Cabeçalho do ato --}}
                        <div class="flex items-start gap-3 px-4 py-3 cursor-pointer" @click="expanded = !expanded">
                            {{-- Número sequencial --}}
                            <div class="shrink-0 mt-0.5 w-8 h-8 rounded-lg border-2 flex items-center justify-center text-xs font-bold {{ $colors }}">
                                {{ $ato->numero }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-2 py-0.5 rounded text-xs font-semibold {{ $colors }}">
                                        {{ $ato->tipoLabel() }}
                                    </span>
                                    <span class="text-sm font-semibold text-gray-800">{{ $ato->titulo }}</span>
                                </div>
                                <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                                    <span>{{ $ato->created_at->format('d/m/Y H:i') }}</span>
                                    <span>{{ $ato->autor->name ?? '-' }}</span>
                                    @if($ato->assinado_por_user_id)
                                        <span class="text-green-600">Assinado por {{ $ato->assinadoPor->name ?? '-' }}</span>
                                    @endif
                                    @if($ato->is_client_visible)
                                        <span class="text-blue-400" title="Visível ao cliente via WhatsApp">Visível ao cliente</span>
                                    @endif
                                    @if($ato->anexos->isNotEmpty())
                                        <span class="text-gray-500 font-medium">{{ $ato->anexos->count() }} anexo(s)</span>
                                    @endif
                                </div>
                            </div>

                            {{-- Indicador de anexo --}}
                            @if($ato->anexos->isNotEmpty())
                                <div class="shrink-0 mt-1">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                </div>
                            @endif

                            <svg :class="expanded ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform shrink-0 mt-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>

                        {{-- Corpo expandido --}}
                        <div x-show="expanded" x-cloak class="border-t px-4 py-3 bg-white">
                            @if($ato->corpo)
                                <div class="text-sm text-gray-700 whitespace-pre-line leading-relaxed mb-3">{{ $ato->corpo }}</div>
                            @endif

                            {{-- Anexos --}}
                            @if($ato->anexos->isNotEmpty())
                                <div class="border-t pt-3">
                                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Anexos</p>
                                    <div class="space-y-1.5">
                                        @foreach($ato->anexos as $anexo)
                                        <a href="{{ route('secure-storage', $anexo->disk_path) }}" target="_blank"
                                           class="flex items-center gap-2 px-3 py-2 rounded-lg bg-gray-50 border hover:bg-blue-50 hover:border-blue-200 transition-colors group">
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                            </svg>
                                            <span class="text-sm text-gray-700 group-hover:text-blue-700">{{ $anexo->original_name }}</span>
                                            <span class="text-xs text-gray-400 ml-auto">{{ $anexo->sizeFmt() }}</span>
                                        </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- ═══ SIDE PANEL (conditionally shown) ═══ --}}
        <div x-show="sidePanel !== ''" x-cloak class="w-80 shrink-0">

            {{-- Tramitações --}}
            <div x-show="sidePanel === 'tramitacoes'" class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Histórico de Tramitação</h3>
                @forelse($processo->tramitacoes as $tram)
                <div class="mb-3 pb-3 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                    <div class="flex items-center gap-1.5 text-xs text-gray-500">
                        <span class="font-medium text-gray-700">{{ $tram->de->name ?? '-' }}</span>
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        <span class="font-medium text-gray-700">{{ $tram->para->name ?? '-' }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $tram->created_at->format('d/m/Y H:i') }} · {{ $tram->tipoLabel() }}</p>
                    @if($tram->despacho)
                        <p class="text-xs text-gray-600 mt-1 italic">"{{ $tram->despacho }}"</p>
                    @endif
                </div>
                @empty
                <p class="text-xs text-gray-400">Nenhuma tramitação registrada.</p>
                @endforelse
            </div>

            {{-- Etapas --}}
            <div x-show="sidePanel === 'etapas'" class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Guia de Etapas</h3>
                @if($processo->etapasTotal > 0)
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-[10px] text-gray-500 mb-1">
                            <span>{{ $processo->etapasConcluidas }}/{{ $processo->etapasTotal }} concluídas</span>
                            <span class="font-semibold">{{ $processo->progresso }}%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-1.5">
                            <div class="bg-green-500 h-1.5 rounded-full transition-all" style="width: {{ $processo->progresso }}%"></div>
                        </div>
                    </div>
                @endif
                <div class="space-y-2">
                    @foreach($processo->steps as $step)
                    <div class="flex items-start gap-2 group p-1.5 rounded-lg hover:bg-gray-50">
                        <div class="shrink-0 mt-0.5 w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold
                            {{ $step->status === 'concluido' ? 'bg-green-100 text-green-700' :
                               ($step->status === 'em_andamento' ? 'bg-blue-100 text-blue-700' :
                               ($step->status === 'aguardando' ? 'bg-yellow-100 text-yellow-700' :
                               ($step->isAtrasada() ? 'bg-red-100 text-red-600' :
                               'bg-gray-100 text-gray-400'))) }}">
                            {{ $step->status === 'concluido' ? '✓' : $step->order }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-gray-700 leading-tight {{ $step->status === 'concluido' ? 'line-through text-gray-400' : '' }}">
                                {{ $step->tipoIcon() }} {{ $step->titulo }}
                            </p>
                            <div class="flex items-center gap-2 text-[10px] text-gray-400">
                                @if($step->responsible)
                                    <span>{{ $step->responsible->name }}</span>
                                @endif
                                @if($step->isAtrasada())
                                    <span class="text-red-500 font-semibold">Atrasada</span>
                                @endif
                                <span class="px-1 py-px rounded {{ $step->statusColor() }}">{{ $step->statusLabel() }}</span>
                            </div>
                            {{-- Botões de ação --}}
                            @if(!in_array($processo->status, ['concluido','cancelado']))
                            <div class="mt-1 flex gap-1 flex-wrap">
                                @if($step->status === 'pendente')
                                    <form method="POST" action="{{ route('crm.admin-processes.update-step', [$processo->id, $step->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="iniciar">
                                        <button class="text-[10px] px-2 py-0.5 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200">Iniciar</button>
                                    </form>
                                    <form method="POST" action="{{ route('crm.admin-processes.update-step', [$processo->id, $step->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="nao_aplicavel">
                                        <button class="text-[10px] px-2 py-0.5 rounded bg-gray-50 text-gray-500 hover:bg-gray-100 border border-gray-200">N/A</button>
                                    </form>
                                @elseif($step->status === 'em_andamento')
                                    <form method="POST" action="{{ route('crm.admin-processes.update-step', [$processo->id, $step->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="concluir">
                                        <button class="text-[10px] px-2 py-0.5 rounded bg-green-50 text-green-700 hover:bg-green-100 border border-green-200">Concluir</button>
                                    </form>
                                    <form method="POST" action="{{ route('crm.admin-processes.update-step', [$processo->id, $step->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="aguardar">
                                        <button class="text-[10px] px-2 py-0.5 rounded bg-yellow-50 text-yellow-700 hover:bg-yellow-100 border border-yellow-200">Aguardar</button>
                                    </form>
                                @elseif($step->status === 'aguardando')
                                    <form method="POST" action="{{ route('crm.admin-processes.update-step', [$processo->id, $step->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="iniciar">
                                        <button class="text-[10px] px-2 py-0.5 rounded bg-blue-50 text-blue-700 hover:bg-blue-100 border border-blue-200">Retomar</button>
                                    </form>
                                    <form method="POST" action="{{ route('crm.admin-processes.update-step', [$processo->id, $step->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="concluir">
                                        <button class="text-[10px] px-2 py-0.5 rounded bg-green-50 text-green-700 hover:bg-green-100 border border-green-200">Concluir</button>
                                    </form>
                                @elseif(in_array($step->status, ['concluido','nao_aplicavel']))
                                    <form method="POST" action="{{ route('crm.admin-processes.update-step', [$processo->id, $step->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="reabrir">
                                        <button class="text-[10px] px-2 py-0.5 rounded bg-yellow-50 text-yellow-700 hover:bg-yellow-100 border border-yellow-200">Reabrir</button>
                                    </form>
                                @endif
                            </div>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Checklist --}}
            <div x-show="sidePanel === 'checklist'" class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Checklist de Documentos</h3>
                <div class="space-y-2">
                    @foreach($processo->checklist as $item)
                    <div class="flex items-start gap-2 group" x-data="{ showActions: false }">
                        @if($item->status === 'recebido')
                            <span class="shrink-0 mt-0.5 w-4 h-4 rounded-full bg-green-100 text-green-600 flex items-center justify-center text-[10px]">✓</span>
                        @elseif($item->status === 'dispensado')
                            <span class="shrink-0 mt-0.5 w-4 h-4 rounded-full bg-gray-100 text-gray-400 flex items-center justify-center text-[10px]">—</span>
                        @else
                            <span class="shrink-0 mt-0.5 w-4 h-4 rounded-full bg-red-50 border border-red-200 flex items-center justify-center text-[10px] text-red-400">○</span>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-xs {{ $item->status === 'pendente' ? 'text-gray-700' : ($item->status === 'recebido' ? 'text-gray-500' : 'text-gray-400 line-through') }} cursor-pointer"
                               @click="showActions = !showActions">
                                {{ $item->nome }}
                            </p>
                            @if($item->received_at)
                                <p class="text-[10px] text-green-500">Recebido {{ $item->received_at->format('d/m/Y') }}</p>
                            @endif
                            @if($item->dispensed_reason)
                                <p class="text-[10px] text-gray-400 italic">{{ $item->dispensed_reason }}</p>
                            @endif
                            <div x-show="showActions" x-cloak class="mt-1 flex gap-1">
                                @if($item->status === 'pendente')
                                    <form method="POST" action="{{ route('crm.admin-processes.update-checklist', [$processo->id, $item->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="receber">
                                        <button type="submit" class="text-[10px] px-2 py-0.5 rounded bg-green-50 text-green-700 hover:bg-green-100 border border-green-200">Recebido</button>
                                    </form>
                                    <form method="POST" action="{{ route('crm.admin-processes.update-checklist', [$processo->id, $item->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="dispensar">
                                        <button type="submit" class="text-[10px] px-2 py-0.5 rounded bg-gray-50 text-gray-500 hover:bg-gray-100 border border-gray-200">Dispensar</button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('crm.admin-processes.update-checklist', [$processo->id, $item->id]) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="pendente">
                                        <button type="submit" class="text-[10px] px-2 py-0.5 rounded bg-yellow-50 text-yellow-700 hover:bg-yellow-100 border border-yellow-200">Voltar p/ pendente</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- Timeline --}}
            <div x-show="sidePanel === 'timeline'" class="bg-white rounded-xl border shadow-sm p-4">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Linha do Tempo</h3>
                @forelse($processo->timeline as $event)
                <div class="mb-3 pb-3 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                    <div class="flex items-start gap-2">
                        <span class="shrink-0 text-sm">{{ $event->tipoIcon() }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-700">{{ $event->titulo }}</p>
                            @if($event->corpo)
                                <p class="text-[10px] text-gray-500 mt-0.5">{{ Str::limit($event->corpo, 80) }}</p>
                            @endif
                            <div class="flex items-center gap-2 mt-0.5 text-[10px] text-gray-400">
                                <span>{{ $event->happened_at->format('d/m/Y H:i') }}</span>
                                @if($event->user)
                                    <span>{{ $event->user->name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-xs text-gray-400">Nenhum evento registrado.</p>
                @endforelse
            </div>
        </div>
    </div>

</div>

{{-- ═══ MODAL: INCLUIR DOCUMENTO/ATO ═══ --}}
<div x-show="showAtoModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
     @keydown.escape.window="showAtoModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
        <div class="px-6 py-4 border-b flex items-center justify-between sticky top-0 bg-white z-10">
            <h3 class="font-semibold text-gray-800">Incluir Documento no Processo</h3>
            <button @click="showAtoModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>
        <form method="POST" action="{{ route('crm.admin-processes.store-ato', $processo->id) }}" enctype="multipart/form-data"
              class="px-6 py-4 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo do documento *</label>
                <select name="tipo" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    <option value="">Selecionar...</option>
                    <optgroup label="Atos internos">
                        <option value="despacho">Despacho</option>
                        <option value="parecer">Parecer</option>
                        <option value="minuta">Minuta</option>
                        <option value="nota_interna">Nota Interna</option>
                        <option value="relatorio">Relatório</option>
                    </optgroup>
                    <optgroup label="Peças e documentos">
                        <option value="requerimento">Requerimento</option>
                        <option value="peticao">Petição</option>
                        <option value="oficio">Ofício</option>
                        <option value="procuracao">Procuração</option>
                        <option value="contrato">Contrato</option>
                        <option value="escritura">Escritura</option>
                    </optgroup>
                    <optgroup label="Externos / Recebidos">
                        <option value="certidao">Certidão</option>
                        <option value="protocolo">Protocolo (cartório/órgão)</option>
                        <option value="recebimento">Documento Recebido</option>
                    </optgroup>
                    <optgroup label="Financeiro">
                        <option value="guia_pagamento">Guia de Pagamento</option>
                        <option value="comprovante">Comprovante</option>
                    </optgroup>
                    <optgroup label="Comunicação">
                        <option value="comunicacao">Comunicação</option>
                    </optgroup>
                    <option value="outro">Outro</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Título / Assunto *</label>
                <input type="text" name="titulo" required
                       placeholder="Ex: Certidão negativa de IPTU obtida"
                       class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo / Descrição</label>
                <textarea name="corpo" rows="4" placeholder="Texto do despacho, parecer, observações..."
                          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Anexar arquivo(s)</label>
                <input type="file" name="anexos[]" multiple
                       accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.odt"
                       class="w-full border rounded-lg px-3 py-2 text-sm file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-[#1B334A] file:text-white file:text-xs file:font-medium file:cursor-pointer">
                <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, imagens. Máx. 20MB por arquivo.</p>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_client_visible" value="1" id="ato_visible" class="rounded text-[#385776]">
                <label for="ato_visible" class="text-sm text-gray-700">Visível ao cliente via WhatsApp</label>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" @click="showAtoModal = false"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-[#1B334A] rounded-lg hover:bg-[#385776] font-medium">Incluir</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: TRAMITAR ═══ --}}
<div x-show="showTramitarModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
     @keydown.escape.window="showTramitarModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop>
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Tramitar Processo</h3>
            <button @click="showTramitarModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>
        <form method="POST" action="{{ route('crm.admin-processes.tramitar', $processo->id) }}" class="px-6 py-4 space-y-4">
            @csrf
            <div class="bg-gray-50 rounded-lg px-3 py-2 text-sm">
                <span class="text-gray-500">Processo está com:</span>
                <strong class="text-gray-800 ml-1">{{ $processo->comUsuario->name ?? $processo->owner->name ?? '-' }}</strong>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Enviar para *</label>
                <select name="para_user_id" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    <option value="">Selecionar advogado...</option>
                    @foreach($usuarios as $u)
                        @if($u->id !== ($processo->com_user_id ?? $processo->owner_user_id))
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endif
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                <select name="tipo" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    <option value="tramitacao">Tramitação</option>
                    <option value="encaminhamento">Encaminhamento</option>
                    <option value="devolucao">Devolução</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Despacho</label>
                <textarea name="despacho" rows="3" placeholder="Instruções, observações ao destinatário..."
                          class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]"></textarea>
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" @click="showTramitarModal = false"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-[#1B334A] rounded-lg hover:bg-[#385776] font-medium">Tramitar</button>
            </div>
        </form>
    </div>
</div>

{{-- ═══ MODAL: ALTERAR STATUS ═══ --}}
<div x-show="showStatusModal" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
     @keydown.escape.window="showStatusModal = false">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md" @click.stop
         x-data="{ status: '{{ $processo->status }}' }">
        <div class="px-6 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Alterar Status</h3>
            <button @click="showStatusModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
        </div>
        <form method="POST" action="{{ route('crm.admin-processes.update-status', $processo->id) }}" class="px-6 py-4 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Novo status</label>
                <select name="status" x-model="status" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    @foreach(['aberto'=>'Aberto','em_andamento'=>'Em andamento','aguardando_cliente'=>'Aguardando cliente','aguardando_terceiro'=>'Aguardando terceiro','suspenso'=>'Suspenso','concluido'=>'Concluído','cancelado'=>'Cancelado'] as $v=>$l)
                        <option value="{{ $v }}">{{ $l }}</option>
                    @endforeach
                </select>
            </div>
            <div x-show="['suspenso','cancelado'].includes(status)">
                <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                <textarea name="motivo" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]"></textarea>
            </div>
            <div x-show="status === 'suspenso'">
                <label class="block text-sm font-medium text-gray-700 mb-1">Retorno programado</label>
                <input type="date" name="suspended_until" class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
            </div>
            <div class="flex gap-2 justify-end pt-2">
                <button type="button" @click="showStatusModal = false"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-[#1B334A] rounded-lg hover:bg-[#385776] font-medium">Salvar</button>
            </div>
        </form>
    </div>
</div>

@endsection
