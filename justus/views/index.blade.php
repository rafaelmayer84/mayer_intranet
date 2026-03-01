@extends('layouts.app')

@section('content')
<div x-data="justusApp()" class="flex flex-col" style="height: calc(100vh - 64px); background: #f3f4f6;">

    {{-- ===== HEADER DO MÓDULO ===== --}}
    <div class="flex items-center justify-between px-4 py-2 bg-white border-b" style="min-height:48px;">
        <div class="flex items-center gap-3">
            {{-- Avatar JUSTUS --}}
            <div class="w-8 h-8 rounded-full flex items-center justify-center" style="background:#1B334A;">
                <svg class="w-4 h-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
            </div>
            <span class="text-xl font-bold tracking-wide" style="color:#1B334A;">JUSTUS</span>
            {{-- Dropdown Advogado --}}
            <div class="flex items-center gap-1 ml-3 px-3 py-1 rounded-full border text-sm" style="color:#385776; border-color:#d1d5db;">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                <span>Advogado Interno</span>
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </div>
        </div>

        {{-- Banner de Budget --}}
        @if(($budget['alert_level'] ?? 'normal') !== 'normal')
        <div class="flex items-center gap-2 px-4 py-1.5 rounded-lg text-sm font-medium"
             style="background: {{ $budget['alert_level'] === 'critical' ? '#fef2f2' : '#fefce8' }}; color: {{ $budget['alert_level'] === 'critical' ? '#991b1b' : '#854d0e' }}; border: 1px solid {{ $budget['alert_level'] === 'critical' ? '#fecaca' : '#fde68a' }};">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
            <span>
                Alerta: Consumo alto em {{ now()->translatedFormat('F') }} –
                Tokens: {{ number_format($budget['global']['total_tokens'] ?? 0) }} /
                Limite: {{ number_format($budget['global']['limit_tokens'] ?? 200000) }} |
                Gasto: <strong>R$ {{ number_format($budget['global']['cost_brl'] ?? 0, 2, ',', '.') }}</strong> /
                Máx: R$ {{ number_format($budget['global']['limit_brl'] ?? 6000, 2, ',', '.') }}
            </span>
        </div>
        @endif
    </div>

    {{-- ===== CORPO PRINCIPAL: 3 COLUNAS ===== --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ===== COLUNA ESQUERDA: Lista de Conversas ===== --}}
        <div class="flex flex-col border-r bg-white" style="width: 300px; min-width: 300px;">
            {{-- Botão Nova Análise --}}
            <div class="p-3">
                <button @click="showNewModal = true"
                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-lg text-white font-semibold text-sm transition hover:opacity-90"
                    style="background: #385776;">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nova Análise
                </button>
            </div>

            {{-- Busca --}}
            <div class="px-3 pb-2">
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="searchQuery" placeholder="Search..."
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-blue-300 focus:ring-1 focus:ring-blue-200 outline-none">
                </div>
            </div>

            {{-- Contadores --}}
            <div class="flex items-center justify-between px-3 py-1 text-xs text-gray-500 border-b">
                <span>Processos <span class="font-semibold">{{ $conversations->count() }}</span></span>
                <span class="text-gray-400">{{ number_format(($budget['global']['total_tokens'] ?? 0) / 1000, 1) }}k</span>
            </div>

            {{-- Lista de Conversas --}}
            <div class="flex-1 overflow-y-auto">
                @forelse($conversations as $conv)
                <a href="{{ route('justus.index', ['c' => $conv->id]) }}"
                   class="flex items-start gap-3 px-3 py-3 border-b transition hover:bg-gray-50 {{ ($activeConversation && $activeConversation->id === $conv->id) ? 'bg-blue-50 border-l-4' : '' }}"
                   style="{{ ($activeConversation && $activeConversation->id === $conv->id) ? 'border-left-color:#385776;' : '' }}">
                    {{-- Avatar --}}
                    <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0 overflow-hidden">
                        <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between">
                            <p class="text-sm font-semibold truncate" style="color:#1B334A;">{{ $conv->title ?: 'Sem título' }}</p>
                            <span class="text-xs text-gray-400 flex-shrink-0 ml-1">{{ $conv->updated_at->format('H:i') }}</span>
                        </div>
                        <p class="text-xs text-gray-500 truncate">{{ $conv->type_label }}</p>
                        @if($conv->numero_cnj ?? false)
                        <p class="text-xs text-gray-400 truncate">Processo {{ $conv->processProfile->numero_cnj ?? '' }}</p>
                        @endif
                        <div class="flex items-center gap-2 mt-1">
                            @php
                                $tagColors = [
                                    'analise_estrategica' => 'bg-blue-100 text-blue-700',
                                    'analise_completa' => 'bg-purple-100 text-purple-700',
                                    'peca' => 'bg-amber-100 text-amber-700',
                                    'calculo_prazo' => 'bg-teal-100 text-teal-700',
                                    'higiene_autos' => 'bg-green-100 text-green-700',
                                ];
                                $tagClass = $tagColors[$conv->type] ?? 'bg-green-100 text-green-700';
                            @endphp
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $tagClass }}">
                                {{ $conv->type_label }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $conv->token_summary }}</span>
                        </div>
                    </div>
                </a>
                @empty
                <div class="p-4 text-center text-sm text-gray-400">
                    Nenhuma análise ainda. Clique em "+ Nova Análise" para começar.
                </div>
                @endforelse
            </div>
        </div>

        {{-- ===== COLUNA CENTRAL: Chat e Análise ===== --}}
        <div class="flex flex-col flex-1 overflow-hidden">
            @if($activeConversation)

            {{-- Card de Upload --}}
            <div class="px-4 pt-4">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full flex items-center justify-center" style="background:#e8eef4;">
                                <svg class="w-5 h-5" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </div>
                            <div>
                                @if($attachments->count() > 0)
                                    @php $lastAttach = $attachments->first(); @endphp
                                    <p class="text-sm font-medium" style="color:#1B334A;">{{ $lastAttach->original_name }}</p>
                                    <div class="flex items-center gap-2 text-xs text-gray-500">
                                        <span>{{ $lastAttach->file_size_human }}</span>
                                        @if($lastAttach->isProcessing())
                                            <span class="text-amber-600 font-medium">⏳ Processando...</span>
                                        @elseif($lastAttach->isReady())
                                            <span class="text-green-600 font-medium">✓ {{ $lastAttach->total_pages }} páginas</span>
                                        @elseif($lastAttach->isFailed())
                                            <span class="text-red-600 font-medium">✗ Falha na extração</span>
                                        @endif
                                    </div>
                                @else
                                    <p class="text-sm text-gray-500">Nenhum documento anexado</p>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 text-xs text-gray-400">
                            @if($attachments->count() > 0)
                            <span>{{ $attachments->first()->created_at->format('H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    {{-- Upload link --}}
                    <div class="mt-2 flex items-center gap-2">
                        <form action="{{ route('justus.upload', $activeConversation->id) }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-2" x-ref="uploadForm">
                            @csrf
                            <input type="file" name="pdf_file" accept=".pdf" class="hidden" x-ref="pdfInput" @change="$refs.uploadForm.submit()">
                            <button type="button" @click="$refs.pdfInput.click()" class="text-sm font-medium hover:underline" style="color:#385776;">
                                · Subir novo documento
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Card Processo / Perfil --}}
            @if($profile)
            <div class="px-4 pt-3">
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4">
                    <div class="flex items-center justify-between mb-3">
                        <p class="text-sm font-bold" style="color:#1B334A;">Processo {{ $profile->numero_cnj ?: '[CNJ não identificado]' }}</p>
                        <button class="text-gray-400 hover:text-gray-600">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="flex"><span class="font-semibold w-40 flex-shrink-0" style="color:#1B334A;">Fase Atual.</span> <span class="text-gray-600">{{ $profile->getFieldOrPlaceholder('fase_atual') }}</span></div>
                        <div class="flex"><span class="font-semibold w-40 flex-shrink-0" style="color:#1B334A;">Objetivo da Análise.</span> <span class="text-gray-600">{{ $profile->getFieldOrPlaceholder('objetivo_analise') }}</span></div>
                        <div class="flex"><span class="font-semibold w-40 flex-shrink-0" style="color:#1B334A;">Tese Principal</span> <span class="text-gray-600">{{ $profile->getFieldOrPlaceholder('tese_principal') }}</span></div>
                        <div class="flex"><span class="font-semibold w-40 flex-shrink-0" style="color:#1B334A;">Limites e Restrições.</span> <span class="text-gray-600">{{ $profile->getFieldOrPlaceholder('limites_restricoes') }}</span></div>
                    </div>
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t">
                        <input type="checkbox" id="manual_estilo" {{ $profile->manual_estilo_aceito ? 'checked' : '' }}
                            class="w-4 h-4 rounded" style="accent-color:#385776;">
                        <label for="manual_estilo" class="text-sm text-gray-600">Concordo que esta análise seguirá o Manual de Estilo Jurídico do Mayer</label>
                    </div>
                </div>
            </div>
            @endif

            {{-- Chat Messages --}}
            <div class="flex-1 overflow-y-auto px-4 py-3 space-y-4" id="chatMessages">
                @foreach($messages as $msg)
                    @if($msg->role === 'user')
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                            <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 max-w-2xl">
                            <p class="text-sm text-gray-700 whitespace-pre-wrap">{{ $msg->content }}</p>
                        </div>
                    </div>
                    @elseif($msg->role === 'assistant')
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0" style="background:#1B334A;">
                            <span class="text-white text-xs font-bold">J</span>
                        </div>
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 max-w-3xl flex-1">
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm" style="color:#1B334A;">JUSTUS</span>
                                    <span class="text-xs text-gray-400">Modelo: {{ $msg->model_used ?? 'N/A' }}</span>
                                </div>
                                <span class="text-xs text-gray-400">{{ $msg->created_at->format('H:i') }}</span>
                            </div>
                            <div class="prose prose-sm max-w-none text-gray-700 justus-response">
                                {!! nl2br(e($msg->content)) !!}
                            </div>
                            @if($msg->input_tokens || $msg->output_tokens)
                            <div class="mt-3 pt-2 border-t text-xs text-gray-400 flex items-center gap-3">
                                <span>Tokens: {{ number_format($msg->input_tokens + $msg->output_tokens) }}</span>
                                <span>Custo: R$ {{ number_format($msg->cost_brl, 4, ',', '.') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>

            {{-- Info bar --}}
            @if($activeConversation)
            <div class="px-4 py-1 text-xs text-gray-400 flex items-center gap-4 border-t bg-gray-50">
                <span>Análise: {{ $activeConversation->type_label }}</span>
                <span>Tokens: {{ number_format($activeConversation->total_input_tokens + $activeConversation->total_output_tokens) }}</span>
                <span>Custo: R$ {{ number_format($activeConversation->total_cost_brl, 2, ',', '.') }}</span>
            </div>
            @endif

            {{-- Barra de Input --}}
            <div class="px-4 py-3 bg-white border-t">
                <form action="{{ route('justus.message.send', $activeConversation->id) }}" method="POST" class="flex items-center gap-3">
                    @csrf
                    {{-- Botão Anexar --}}
                    <button type="button" @click="$refs.pdfInput.click()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </button>
                    {{-- Input de texto --}}
                    <input type="text" name="message" placeholder="Digite sua mensagem..."
                        class="flex-1 px-4 py-2.5 text-sm rounded-xl border border-gray-200 focus:border-blue-300 focus:ring-1 focus:ring-blue-200 outline-none"
                        required>
                    {{-- Ícone attachment --}}
                    <button type="button" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>
                    {{-- Botão Realizar Análise --}}
                    <button type="submit"
                        class="flex items-center gap-2 px-5 py-2.5 rounded-xl text-white font-semibold text-sm transition hover:opacity-90"
                        style="background: #385776;">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Realizar Análise
                    </button>
                </form>
            </div>

            @else
            {{-- Estado Vazio --}}
            <div class="flex-1 flex items-center justify-center">
                <div class="text-center">
                    <div class="w-20 h-20 mx-auto rounded-full flex items-center justify-center mb-4" style="background:#e8eef4;">
                        <svg class="w-10 h-10" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-semibold mb-1" style="color:#1B334A;">JUSTUS</h3>
                    <p class="text-sm text-gray-500 mb-4">Selecione uma análise existente ou crie uma nova.</p>
                    <button @click="showNewModal = true"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-white font-semibold text-sm"
                        style="background:#385776;">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Nova Análise
                    </button>
                </div>
            </div>
            @endif
        </div>

        {{-- ===== COLUNA DIREITA: Resumo + Atalhos + Anexos ===== --}}
        @if($activeConversation)
        <div class="border-l bg-white overflow-y-auto" style="width: 320px; min-width: 320px;">

            {{-- Resumo Processual Completo --}}
            <div class="p-4 border-b">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold" style="color:#1B334A;">Resumo Processual Completo</h3>
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"/></svg>
                    </button>
                </div>

                @if($profile)
                <div class="bg-gray-50 rounded-xl p-3 border">
                    <p class="font-bold text-sm mb-3" style="color:#1B334A;">Processo {{ $profile->numero_cnj ?: '[CNJ]' }}</p>
                    <div class="space-y-2.5 text-sm">
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span class="text-gray-600">{{ $profile->getFieldOrPlaceholder('autor') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="text-xs font-bold flex-shrink-0 mt-0.5" style="color:#385776;">VS.</span>
                            <span class="text-gray-600">{{ $profile->getFieldOrPlaceholder('reu') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <span class="text-gray-600">Phase: {{ $profile->getFieldOrPlaceholder('fase_atual') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <span class="text-gray-600">{{ $profile->getFieldOrPlaceholder('relator_vara') }}</span>
                        </div>
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <div>
                                <span class="text-gray-500">Data Intimação</span>
                                <span class="ml-2 text-gray-700">{{ $profile->data_intimacao ? $profile->data_intimacao->format('d/m/Y') : '[não localizada]' }}</span>
                            </div>
                        </div>
                        <div class="flex items-start gap-2">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <div>
                                <span class="text-gray-500">Prazo médio</span>
                                <span class="ml-2 text-gray-700">{{ $profile->prazo_medio ?: 'indefinido' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Acesso Rápido (Accordion) --}}
            <div class="p-4 border-b" x-data="{ openSection: null }">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold" style="color:#1B334A;">Acesso Rápido</h3>
                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </div>
                <div class="space-y-1">
                    @foreach(['Sentença', 'Petição Inicial', 'Despacho (14/04/2024)', 'Recursos', 'Contestações'] as $section)
                    <button @click="openSection = openSection === '{{ $section }}' ? null : '{{ $section }}'"
                        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm text-gray-700 hover:bg-gray-50 transition">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>{{ $section }}</span>
                        </div>
                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Anexos --}}
            <div class="p-4">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold" style="color:#1B334A;">ANEXOS</h3>
                    <span class="text-xs text-gray-400">
                        {{ $attachments->count() }} {{ $attachments->count() === 1 ? 'apensado' : 'apensados' }}
                        @if($attachments->count() > 0)
                        | {{ $attachments->first()->total_pages ?? '...' }} págs
                        @endif
                    </span>
                </div>

                @foreach($attachments as $att)
                <div class="bg-gray-50 rounded-xl p-3 border mb-3">
                    <div class="flex items-start gap-3">
                        <div class="w-10 h-12 bg-red-100 rounded flex items-center justify-center flex-shrink-0">
                            <span class="text-red-600 text-xs font-bold">PDF</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium truncate" style="color:#1B334A;">{{ $att->original_name }}</p>
                            <p class="text-xs text-gray-400">{{ $att->file_size_human }} | {{ $att->total_pages ?? '...' }} págs</p>
                        </div>
                    </div>
                </div>
                @endforeach

                {{-- Chance de revisão --}}
                @if($activeConversation->type === 'peca')
                <div class="bg-gray-50 rounded-xl p-3 border mb-3">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-gray-600">Chance de revisão</span>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                            Economia processual
                        </span>
                    </div>
                </div>
                @endif

                {{-- Botão Solicitar Peça --}}
                <div class="flex items-center justify-between mt-3 pt-3 border-t">
                    @if($activeConversation->type === 'peca')
                    <form action="{{ route('justus.approve', $activeConversation->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="action" value="request">
                        <button type="submit" class="text-sm font-semibold hover:opacity-80" style="color:#385776;">
                            Solicitar Peça Completa
                        </button>
                    </form>
                    @else
                    <span class="text-sm text-gray-400">Solicitar Peça Completa</span>
                    @endif
                    <span class="text-sm font-medium text-gray-500">R$ 930,00 p/h</span>
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- ===== MODAL: Nova Análise ===== --}}
    <div x-show="showNewModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50" @click.self="showNewModal = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 mx-4" @click.stop>
            <h2 class="text-lg font-bold mb-4" style="color:#1B334A;">Nova Análise</h2>
            <form action="{{ route('justus.conversations.create') }}" method="POST">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Título</label>
                    <input type="text" name="title" placeholder="Ex: Análise Recurso de Apelação..."
                        class="w-full px-3 py-2 text-sm rounded-lg border border-gray-200 focus:border-blue-300 focus:ring-1 focus:ring-blue-200 outline-none">
                </div>
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Análise</label>
                    <div class="space-y-2">
                        @foreach(\App\Models\JustusConversation::TYPE_LABELS as $value => $label)
                        <label class="flex items-center gap-3 px-3 py-2 rounded-lg border border-gray-200 hover:bg-gray-50 cursor-pointer transition">
                            <input type="radio" name="type" value="{{ $value }}" {{ $loop->first ? 'checked' : '' }}
                                class="w-4 h-4" style="accent-color:#385776;">
                            <span class="text-sm text-gray-700">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showNewModal = false"
                        class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">Cancelar</button>
                    <button type="submit"
                        class="px-5 py-2 rounded-lg text-white font-semibold text-sm"
                        style="background:#385776;">
                        Criar Análise
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<style>
    .justus-response p { margin-bottom: 0.5rem; }
    .justus-response ul, .justus-response ol { margin-left: 1.5rem; margin-bottom: 0.5rem; }
    .justus-response li { margin-bottom: 0.25rem; }
    .justus-response strong { color: #1B334A; }
    [x-cloak] { display: none !important; }
</style>

<script>
function justusApp() {
    return {
        showNewModal: false,
        searchQuery: '',

        init() {
            this.$nextTick(() => {
                const chat = document.getElementById('chatMessages');
                if (chat) chat.scrollTop = chat.scrollHeight;
            });
        }
    }
}
</script>
@endsection
