@extends('layouts.app')
@section('title', $processo->protocolo . ' — ' . $processo->titulo)

@section('content')
@php
    $atrasado = $processo->prazo_final && $processo->prazo_final->isPast() && !in_array($processo->status, ['concluido','cancelado']);
    $comigo = $processo->com_user_id === auth()->id();
@endphp

<div class="max-w-full mx-auto px-6 py-6"
     x-data="{ showAtoModal: false, showTramitarModal: false, showStatusModal: false, sidePanel: '', showAddChecklist: false, showImportDocx: false }">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.dashboard') }}" class="hover:text-[#385776]">CRM</a>
        <span>></span>
        <a href="{{ route('crm.admin-processes.index') }}" class="hover:text-[#385776]">Processos Administrativos</a>
        <span>></span>
        <span class="text-gray-700 font-medium">{{ $processo->protocolo }}</span>
    </div>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- HEADER --}}
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
                        <span>Responsavel: <strong class="text-white">{{ $processo->owner->name ?? '-' }}</strong></span>
                        @if($processo->orgao_destino)
                            <span>Orgao: {{ $processo->orgao_destino }}</span>
                        @endif
                        @if($processo->numero_externo)
                            <span>No ext.: {{ $processo->numero_externo }}</span>
                        @endif
                    </div>
                </div>
                <div class="shrink-0 text-right">
                    <div class="px-3 py-2 rounded-lg {{ $comigo ? 'bg-green-500/20 border border-green-400/30' : 'bg-white/10' }}">
                        <p class="text-xs text-blue-200">Na mesa de</p>
                        <p class="text-sm font-bold text-white">{{ $processo->comUsuario->name ?? $processo->owner->name ?? '-' }}</p>
                        @if($comigo)
                            <p class="text-xs text-green-300 mt-0.5">Esta com voce</p>
                        @endif
                    </div>
                    @if($processo->prazo_final)
                        <p class="text-xs text-blue-300 mt-2">Prazo: <span class="{{ $atrasado ? 'text-red-300 font-bold' : 'text-white' }}">{{ $processo->prazo_final->format('d/m/Y') }}</span></p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Barra de acoes --}}
        <div class="px-6 py-3 bg-gray-50 border-t flex flex-wrap gap-2">
            <button @click="showAtoModal = true"
                    class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-[#1B334A] text-white rounded-lg hover:bg-[#385776] font-medium shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Movimentar
            </button>
            <button @click="showTramitarModal = true"
                    class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 font-medium shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Tramitar
            </button>
            <button @click="showStatusModal = true"
                    class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-amber-500 text-white rounded-lg hover:bg-amber-600 font-medium shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>
                Alterar status
            </button>
            <a href="{{ route('crm.admin-processes.edit', $processo->id) }}"
               class="inline-flex items-center gap-1.5 text-sm px-3 py-1.5 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600 font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Editar dados
            </a>

            <div class="ml-auto flex gap-2">
                <button @click="sidePanel = sidePanel === 'tramitacoes' ? '' : 'tramitacoes'"
                        :class="sidePanel==='tramitacoes' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-300'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-indigo-50 hover:text-indigo-700 transition-colors font-medium">
                    📨 Tramitações ({{ $processo->tramitacoes->count() }})
                </button>
                <button @click="sidePanel = sidePanel === 'etapas' ? '' : 'etapas'"
                        :class="sidePanel==='etapas' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-300'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-blue-50 hover:text-blue-700 transition-colors font-medium">
                    📊 Etapas ({{ $processo->steps->count() }})
                </button>
                <button @click="sidePanel = sidePanel === 'checklist' ? '' : 'checklist'"
                        :class="sidePanel==='checklist' ? 'bg-amber-500 text-white border-amber-500' : 'bg-white text-gray-600 border-gray-300'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-amber-50 hover:text-amber-700 transition-colors font-medium">
                    ✅ Checklist
                    @if($processo->checklist->where('status','pendente')->count() > 0)
                        <span class="ml-1 inline-flex items-center justify-center w-4 h-4 rounded-full bg-red-500 text-white text-[9px] font-bold">{{ $processo->checklist->where('status','pendente')->count() }}</span>
                    @endif
                </button>
                <button @click="sidePanel = sidePanel === 'timeline' ? '' : 'timeline'"
                        :class="sidePanel==='timeline' ? 'bg-violet-600 text-white border-violet-600' : 'bg-white text-gray-600 border-gray-300'"
                        class="text-xs px-3 py-1.5 border rounded-lg hover:bg-violet-50 hover:text-violet-700 transition-colors font-medium">
                    🕐 Timeline
                </button>
                <a href="{{ route('crm.accounts.show', $processo->account_id) }}"
                   class="text-xs px-3 py-1.5 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600 font-medium">
                    👤 Ficha do cliente
                </a>
            </div>
        </div>
    </div>

    {{-- CONTEUDO: ARVORE DE ATOS + SIDE PANEL --}}
    <div class="flex gap-6">

        {{-- Arvore de Atos (principal) --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-[#1B334A]">Arvore do Processo</h2>
                <span class="text-xs text-gray-400">{{ $processo->atos->count() }} movimentacao(oes)</span>
            </div>

            @if($processo->atos->isEmpty())
                <div class="bg-white rounded-xl border shadow-sm py-16 text-center text-gray-400">
                    <p class="text-sm">Nenhuma movimentacao registrada neste processo.</p>
                    <button @click="showAtoModal = true" class="mt-3 text-[#385776] hover:underline text-sm font-medium">
                        Registrar primeira movimentacao
                    </button>
                </div>
            @else
                {{-- Tabela da arvore --}}
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b text-xs text-gray-500 uppercase tracking-wide">
                                <th class="text-left px-4 py-2.5 w-48">Movimentacao</th>
                                <th class="text-left px-4 py-2.5">Conteudo</th>
                                <th class="text-left px-4 py-2.5 w-80">Documentos</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($processo->atos as $ato)
                            @php
                                $colors = $ato->tipoColor();
                                $hasAnexos = $ato->anexos->isNotEmpty();
                            @endphp
                            <tr class="align-top hover:bg-gray-50/50">
                                {{-- Col 1: No, Tipo, Data, Autor --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-start gap-2.5">
                                        <div class="shrink-0 w-8 h-8 rounded-lg border-2 flex items-center justify-center text-xs font-bold {{ $colors }}">
                                            {{ $ato->numero }}
                                        </div>
                                        <div>
                                            <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $colors }}">
                                                {{ $ato->tipoLabel() }}
                                            </span>
                                            <p class="text-xs text-gray-400 mt-1">{{ $ato->created_at->format('d/m/Y H:i') }}</p>
                                            <p class="text-xs text-gray-500">{{ $ato->autor->name ?? '-' }}</p>
                                            @if($ato->assinado_por_user_id)
                                                <p class="text-xs text-green-600 mt-0.5">Assinado: {{ $ato->assinadoPor->name ?? '-' }}</p>
                                            @endif
                                            @if($ato->is_client_visible)
                                                <p class="text-[10px] text-blue-400 mt-0.5">Visivel ao cliente</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Col 2: Titulo + Corpo (sempre visivel) --}}
                                <td class="px-4 py-3">
                                    <p class="font-semibold text-gray-800 leading-snug">{{ $ato->titulo }}</p>
                                    @if($ato->corpo)
                                        <div class="mt-1.5 text-sm text-gray-600 whitespace-pre-line leading-relaxed">{{ $ato->corpo }}</div>
                                    @endif
                                </td>

                                {{-- Col 3: Documentos inline --}}
                                <td class="px-4 py-3">
                                    @if($hasAnexos)
                                        <div class="space-y-2">
                                            @foreach($ato->anexos as $anexo)
                                            @php
                                                $isPdf = Str::endsWith(strtolower($anexo->original_name), '.pdf');
                                                $isImage = in_array(strtolower(pathinfo($anexo->original_name, PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']);
                                                $url = route('secure-storage', $anexo->disk_path);
                                            @endphp
                                            <div class="border rounded-lg overflow-hidden bg-gray-50" x-data="{ preview: {{ $isPdf || $isImage ? 'true' : 'false' }} }">
                                                {{-- Header do anexo --}}
                                                <div class="flex items-center gap-2 px-3 py-1.5 text-xs">
                                                    @if($isPdf)
                                                        <svg class="w-4 h-4 text-red-500 shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6zm-1 2l5 5h-5V4zM6 20V4h6v7h6v9H6z"/></svg>
                                                    @elseif($isImage)
                                                        <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                                    @else
                                                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                    @endif
                                                    <span class="text-gray-700 truncate flex-1" title="{{ $anexo->original_name }}">{{ $anexo->original_name }}</span>
                                                    <span class="text-gray-400 shrink-0">{{ $anexo->sizeFmt() }}</span>
                                                    @if($isPdf || $isImage)
                                                        <button @click="preview = !preview" class="text-[10px] px-1.5 py-0.5 rounded bg-gray-200 hover:bg-gray-300 text-gray-600 shrink-0"
                                                                x-text="preview ? 'Ocultar' : 'Ver'"></button>
                                                    @endif
                                                    <a href="{{ $url }}" target="_blank" class="text-[10px] px-1.5 py-0.5 rounded bg-blue-50 hover:bg-blue-100 text-blue-700 shrink-0">Abrir</a>
                                                </div>

                                                {{-- Preview inline --}}
                                                <div x-show="preview" x-cloak>
                                                    @if($isPdf)
                                                        <iframe src="{{ $url }}#toolbar=0&navpanes=0" class="w-full border-t" style="height: 360px;" loading="lazy"></iframe>
                                                    @elseif($isImage)
                                                        <div class="border-t p-2 bg-white flex justify-center">
                                                            <img src="{{ $url }}" alt="{{ $anexo->original_name }}" class="max-w-full max-h-72 rounded object-contain" loading="lazy">
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-300">--</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- SIDE PANEL (conditionally shown) --}}
        <div x-show="sidePanel !== ''" x-cloak class="w-80 shrink-0">

            {{-- Tramitacoes --}}
            <div x-show="sidePanel === 'tramitacoes'" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="h-1 bg-indigo-500 rounded-t-xl"></div>
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-indigo-700 mb-3 flex items-center gap-1.5">
                        📨 Histórico de Tramitação
                    </h3>
                    @forelse($processo->tramitacoes as $tram)
                    <div class="mb-3 pb-3 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0">
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="font-semibold text-gray-700 bg-gray-100 px-1.5 py-0.5 rounded">{{ $tram->de->name ?? '-' }}</span>
                            <svg class="w-3 h-3 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                            <span class="font-semibold text-indigo-700 bg-indigo-50 px-1.5 py-0.5 rounded">{{ $tram->para->name ?? '-' }}</span>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1">{{ $tram->created_at->format('d/m/Y H:i') }} · {{ $tram->tipoLabel() }}</p>
                        @if($tram->despacho)
                            <p class="text-xs text-gray-600 mt-1 italic bg-gray-50 rounded px-2 py-1">"{{ $tram->despacho }}"</p>
                        @endif
                    </div>
                    @empty
                    <p class="text-xs text-gray-400">Nenhuma tramitação registrada.</p>
                    @endforelse
                </div>
            </div>

            {{-- Etapas --}}
            <div x-show="sidePanel === 'etapas'" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="h-1 bg-blue-500 rounded-t-xl"></div>
                <div class="p-4">
                <h3 class="text-sm font-semibold text-blue-700 mb-3 flex items-center gap-1.5">📊 Guia de Etapas</h3>
                @if($processo->etapasTotal > 0)
                    <div class="mb-3">
                        <div class="flex items-center justify-between text-[10px] text-gray-500 mb-1">
                            <span>{{ $processo->etapasConcluidas }}/{{ $processo->etapasTotal }} concluidas</span>
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
                            {{-- Botoes de acao --}}
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
                </div>{{-- /p-4 --}}
            </div>

            {{-- Checklist --}}
            <div x-show="sidePanel === 'checklist'" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="h-1 bg-amber-500 rounded-t-xl"></div>
                <div class="p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-amber-700 flex items-center gap-1.5">✅ Checklist de Documentos</h3>
                        <div class="flex items-center gap-2">
                            <button @click="showImportDocx = !showImportDocx; showAddChecklist = false"
                                    class="text-xs text-violet-600 hover:underline font-medium">📄 Importar Word</button>
                            <button @click="showAddChecklist = !showAddChecklist; showImportDocx = false"
                                    class="text-xs text-amber-700 hover:underline font-medium">+ Adicionar</button>
                        </div>
                    </div>

                    {{-- Formulário de importação Word --}}
                    <div x-show="showImportDocx" x-cloak class="mb-3 p-2.5 bg-violet-50 border border-violet-200 rounded-lg"
                         x-data="{ uploading: false }">
                        <p class="text-[11px] text-violet-700 font-medium mb-1.5">📄 Importar checklist de arquivo Word</p>
                        <p class="text-[10px] text-violet-500 mb-2">O Claude Opus vai ler o documento e extrair os itens automaticamente.</p>
                        <form method="POST" action="{{ route('crm.admin-processes.import-checklist-docx', $processo->id) }}"
                              enctype="multipart/form-data"
                              @submit="uploading = true; $dispatch('checklist-import-started')">
                            @csrf
                            <input type="file" name="docx" accept=".doc,.docx" required
                                   class="w-full text-xs border rounded px-2 py-1 mb-1.5 bg-white focus:outline-none focus:ring-1 focus:ring-violet-400">
                            <div class="flex justify-end gap-1.5">
                                <button type="button" @click="showImportDocx = false"
                                        class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700">Cancelar</button>
                                <button type="submit" :disabled="uploading"
                                        class="text-xs px-2.5 py-1 bg-violet-600 text-white rounded hover:bg-violet-700 font-medium disabled:opacity-50">
                                    <span x-show="!uploading">Importar</span>
                                    <span x-show="uploading" class="flex items-center gap-1">
                                        <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/></svg>
                                        Enviando...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Formulário de adição manual --}}
                    <div x-show="showAddChecklist" x-cloak class="mb-3 p-2.5 bg-amber-50 border border-amber-200 rounded-lg">
                        <form method="POST" action="{{ route('crm.admin-processes.store-checklist-item', $processo->id) }}"
                              @submit="showAddChecklist = false">
                            @csrf
                            <input type="text" name="nome" required placeholder="Ex: Certidão de matrícula atualizada"
                                   class="w-full border rounded px-2 py-1.5 text-xs mb-1.5 focus:outline-none focus:ring-1 focus:ring-amber-400">
                            <div class="flex justify-end gap-1.5">
                                <button type="button" @click="showAddChecklist = false"
                                        class="text-xs px-2 py-1 text-gray-500 hover:text-gray-700">Cancelar</button>
                                <button type="submit"
                                        class="text-xs px-2.5 py-1 bg-amber-500 text-white rounded hover:bg-amber-600 font-medium">Adicionar</button>
                            </div>
                        </form>
                    </div>

                    <div class="space-y-2">
                        @foreach($processo->checklist as $item)
                        <div class="flex items-start gap-2 group p-1.5 rounded-lg hover:bg-gray-50" x-data="{ showActions: false }">
                            @if($item->status === 'recebido')
                                <span class="shrink-0 mt-0.5 w-5 h-5 rounded-full bg-green-500 text-white flex items-center justify-center text-[11px] font-bold">✓</span>
                            @elseif($item->status === 'dispensado')
                                <span class="shrink-0 mt-0.5 w-5 h-5 rounded-full bg-gray-200 text-gray-500 flex items-center justify-center text-[10px]">–</span>
                            @else
                                <span class="shrink-0 mt-0.5 w-5 h-5 rounded-full bg-red-100 border-2 border-red-300 flex items-center justify-center text-[10px] text-red-500 font-bold">!</span>
                            @endif
                            <div class="flex-1 min-w-0">
                                <p class="text-xs leading-snug cursor-pointer
                                    {{ $item->status === 'pendente' ? 'text-gray-800 font-medium' :
                                       ($item->status === 'recebido' ? 'text-gray-500' : 'text-gray-400 line-through') }}"
                                   @click="showActions = !showActions">
                                    {{ $item->nome }}
                                </p>
                                @if($item->received_at)
                                    <p class="text-[10px] text-green-600 font-medium">Recebido em {{ $item->received_at->format('d/m/Y') }}</p>
                                @endif
                                @if($item->dispensed_reason)
                                    <p class="text-[10px] text-gray-400 italic">{{ $item->dispensed_reason }}</p>
                                @endif
                                <div x-show="showActions" x-cloak class="mt-1.5 flex gap-1">
                                    @if($item->status === 'pendente')
                                        <form method="POST" action="{{ route('crm.admin-processes.update-checklist', [$processo->id, $item->id]) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="action" value="receber">
                                            <button type="submit" class="text-[10px] px-2 py-0.5 rounded bg-green-500 text-white hover:bg-green-600 font-medium">✓ Recebido</button>
                                        </form>
                                        <form method="POST" action="{{ route('crm.admin-processes.update-checklist', [$processo->id, $item->id]) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="action" value="dispensar">
                                            <button type="submit" class="text-[10px] px-2 py-0.5 rounded bg-gray-100 text-gray-500 hover:bg-gray-200 border border-gray-300">Dispensar</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('crm.admin-processes.update-checklist', [$processo->id, $item->id]) }}" class="inline">
                                            @csrf
                                            <input type="hidden" name="action" value="pendente">
                                            <button type="submit" class="text-[10px] px-2 py-0.5 rounded bg-amber-50 text-amber-700 hover:bg-amber-100 border border-amber-200">↩ Pendente</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Timeline --}}
            <div x-show="sidePanel === 'timeline'" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="h-1 bg-violet-500 rounded-t-xl"></div>
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-violet-700 mb-3">🕐 Linha do Tempo</h3>
                    @forelse($processo->timeline as $event)
                    <div class="mb-3 pb-3 border-b border-gray-100 last:border-0 last:mb-0 last:pb-0 flex items-start gap-2">
                        <div class="shrink-0 mt-0.5 w-6 h-6 rounded-full bg-violet-100 flex items-center justify-center text-sm">{{ $event->tipoIcon() }}</div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-700 leading-snug">{{ $event->titulo }}</p>
                            @if($event->corpo)
                                <p class="text-[10px] text-gray-500 mt-0.5">{{ Str::limit($event->corpo, 80) }}</p>
                            @endif
                            <div class="flex items-center gap-2 mt-1 text-[10px] text-gray-400">
                                <span class="bg-gray-100 rounded px-1">{{ $event->happened_at->format('d/m/Y H:i') }}</span>
                                @if($event->user)
                                    <span>{{ $event->user->name }}</span>
                                @endif
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

    {{-- MODAL: MOVIMENTAR --}}
    <div x-show="showAtoModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="showAtoModal = false">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="px-6 py-4 border-b flex items-center justify-between sticky top-0 bg-white z-10">
                <h3 class="font-semibold text-gray-800">Nova Movimentacao</h3>
                <button @click="showAtoModal = false" class="text-gray-400 hover:text-gray-600 text-lg">&times;</button>
            </div>
            <form method="POST" action="{{ route('crm.admin-processes.store-ato', $processo->id) }}" enctype="multipart/form-data"
                  class="px-6 py-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Tipo de movimentacao *</label>
                    <select name="tipo" required class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                        <option value="">Selecionar...</option>
                        @php $movs = \App\Models\Crm\CrmAdminProcessAto::movimentacoesManuais(); @endphp

                        <optgroup label="Atos internos">
                            @foreach(['despacho','parecer','nota_interna'] as $k)
                                <option value="{{ $k }}">{{ $movs[$k] }}</option>
                            @endforeach
                        </optgroup>

                        <optgroup label="Documentos elaborados">
                            @foreach(['juntada','elaboracao_minuta','elaboracao_peticao','elaboracao_contrato','elaboracao_procuracao','elaboracao_escritura','elaboracao_oficio','elaboracao_requerimento'] as $k)
                                <option value="{{ $k }}">{{ $movs[$k] }}</option>
                            @endforeach
                        </optgroup>

                        <optgroup label="Atos externos / Cartorio / Orgao">
                            @foreach(['protocolo_orgao','diligencia_externa','certidao_obtida','recebimento_documento','assinatura','registro_cartorio','averbacao'] as $k)
                                <option value="{{ $k }}">{{ $movs[$k] }}</option>
                            @endforeach
                        </optgroup>

                        <optgroup label="Financeiro">
                            @foreach(['pagamento_taxa','comprovante_pagamento'] as $k)
                                <option value="{{ $k }}">{{ $movs[$k] }}</option>
                            @endforeach
                        </optgroup>

                        <optgroup label="Comunicacao">
                            @foreach(['comunicacao_cliente','comunicacao_terceiro','envio_documento'] as $k)
                                <option value="{{ $k }}">{{ $movs[$k] }}</option>
                            @endforeach
                        </optgroup>

                        <optgroup label="Aguardando">
                            @foreach(['aguardando_cliente','aguardando_orgao','aguardando_terceiro'] as $k)
                                <option value="{{ $k }}">{{ $movs[$k] }}</option>
                            @endforeach
                        </optgroup>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observacoes do advogado</label>
                    <textarea name="corpo" rows="4" placeholder="Detalhes, anotacoes, instrucoes..."
                              class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Anexar arquivo(s)</label>
                    <input type="file" name="anexos[]" multiple
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx,.odt"
                           class="w-full border rounded-lg px-3 py-2 text-sm file:mr-2 file:py-1 file:px-3 file:rounded-lg file:border-0 file:bg-[#1B334A] file:text-white file:text-xs file:font-medium file:cursor-pointer">
                    <p class="text-xs text-gray-400 mt-1">PDF, DOC, DOCX, imagens. Max. 20MB por arquivo.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_client_visible" value="1" id="ato_visible" class="rounded text-[#385776]">
                    <label for="ato_visible" class="text-sm text-gray-700">Visivel ao cliente</label>
                </div>
                <div class="flex gap-2 justify-end pt-2">
                    <button type="button" @click="showAtoModal = false"
                            class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">Cancelar</button>
                    <button type="submit"
                            class="px-4 py-2 text-sm text-white bg-[#1B334A] rounded-lg hover:bg-[#385776] font-medium">Registrar</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL: TRAMITAR --}}
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
                    <span class="text-gray-500">Processo esta com:</span>
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
                        <option value="tramitacao">Tramitacao</option>
                        <option value="encaminhamento">Encaminhamento</option>
                        <option value="devolucao">Devolucao</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Despacho</label>
                    <textarea name="despacho" rows="3" placeholder="Instrucoes, observacoes ao destinatario..."
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

    {{-- MODAL: ALTERAR STATUS --}}
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
                        @foreach(['aberto'=>'Aberto','em_andamento'=>'Em andamento','aguardando_cliente'=>'Aguardando cliente','aguardando_terceiro'=>'Aguardando terceiro','suspenso'=>'Suspenso','concluido'=>'Concluido','cancelado'=>'Cancelado'] as $v=>$l)
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

</div>

{{-- ── POPUP DE ANDAMENTO: Importação Word ──────────────────────────────── --}}
<div x-data="checklistImportProgress()"
     @checklist-import-started.window="start()"
     x-show="visible" x-cloak
     class="fixed inset-0 z-[200] flex items-center justify-center bg-black/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm mx-4 overflow-hidden">
        <div class="h-1.5 bg-gray-100 rounded-t-2xl overflow-hidden">
            <div class="h-full bg-violet-500 transition-all duration-700 ease-out" :style="`width: ${progress}%`"></div>
        </div>
        <div class="px-6 py-5">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-violet-100 flex items-center justify-center text-xl shrink-0">📄</div>
                <div>
                    <p class="font-semibold text-gray-800 text-sm">Importando checklist</p>
                    <p class="text-xs text-gray-400">Aguarde, isso leva alguns segundos</p>
                </div>
            </div>
            <div class="space-y-2.5">
                <template x-for="step in steps" :key="step.id">
                    <div class="flex items-center gap-2.5">
                        <div class="shrink-0 w-5 h-5 rounded-full flex items-center justify-center text-[11px]"
                             :class="{
                                 'bg-green-100 text-green-600': step.status === 'done',
                                 'bg-violet-100 text-violet-600 animate-pulse': step.status === 'active',
                                 'bg-gray-100 text-gray-400': step.status === 'pending'
                             }">
                            <span x-show="step.status === 'done'">✓</span>
                            <span x-show="step.status === 'active'">
                                <svg class="animate-spin w-3 h-3" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                                </svg>
                            </span>
                            <span x-show="step.status === 'pending'">·</span>
                        </div>
                        <span class="text-xs"
                              :class="{
                                  'text-green-700 font-medium': step.status === 'done',
                                  'text-violet-700 font-semibold': step.status === 'active',
                                  'text-gray-400': step.status === 'pending'
                              }"
                              x-text="step.label"></span>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script>
function checklistImportProgress() {
    return {
        visible: false,
        progress: 0,
        steps: [
            { id: 1, label: 'Enviando arquivo para o servidor', status: 'pending' },
            { id: 2, label: 'Lendo conteúdo do documento Word',  status: 'pending' },
            { id: 3, label: 'Fazendo a magia acontecer!',         status: 'pending' },
            { id: 4, label: 'Extraindo itens de checklist',      status: 'pending' },
            { id: 5, label: 'Salvando no processo',              status: 'pending' },
        ],
        timers: [],

        start() {
            this.visible  = true;
            this.progress = 0;
            this.steps.forEach(s => s.status = 'pending');

            const schedule = [
                { delay: 100,  step: 0, prog: 10 },
                { delay: 800,  step: 1, prog: 25 },
                { delay: 2500, step: 2, prog: 50 },
                { delay: 5000, step: 3, prog: 75 },
                { delay: 9000, step: 4, prog: 92 },
            ];

            schedule.forEach(({ delay, step, prog }) => {
                const t = setTimeout(() => {
                    if (step > 0) this.steps[step - 1].status = 'done';
                    this.steps[step].status = 'active';
                    this.progress = prog;
                }, delay);
                this.timers.push(t);
            });
        },
    }
}
</script>

@endsection
