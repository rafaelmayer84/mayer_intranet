@extends('layouts.app')

@section('content')
<div x-data="justusApp()" x-init="init()" class="flex flex-col" style="height:100%;overflow:hidden;">

    {{-- ===== HEADER PREMIUM ===== --}}
    <div class="flex items-center justify-between px-5 py-2.5 flex-shrink-0" style="background:linear-gradient(135deg,#1B334A 0%,#2a4a6b 50%,#385776 100%);">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <span class="text-lg font-bold text-white tracking-wide">JUSTUS</span>
                <span class="ml-2 text-[10px] px-2 py-0.5 rounded-full font-semibold" style="background:rgba(255,255,255,0.2);color:#fbbf24;">IA</span>
            </div>
            <span class="text-xs text-white/50 ml-2">Assistente Juridico Inteligente</span>
        </div>
        <div class="flex items-center gap-4">
            @if(($budget['alert_level'] ?? 'normal') !== 'normal')
            <div class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,0.1);color:#fbbf24;">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                {{ number_format($budget['global']['total_tokens'] ?? 0) }} tokens · R$ {{ number_format($budget['global']['cost_brl'] ?? 0, 2, ',', '.') }}
            </div>
            @endif
            <div class="text-xs text-white/40">
                {{ number_format(($budget['global']['total_tokens'] ?? 0) / 1000, 1) }}k tokens usados
            </div>
            @if(auth()->user()->role === 'admin')
            <a href="{{ route('justus.admin.config') }}" class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs text-white/60 hover:text-white hover:bg-white/10 transition-all" title="Configuracao JUSTUS">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Config
            </a>
            @endif
        </div>
    </div>

    {{-- ===== CORPO: 3 COLUNAS ===== --}}
    <div class="flex flex-1 overflow-hidden min-h-0" style="background:#f0f2f5;">

        {{-- === COL ESQUERDA === --}}
        <div class="flex flex-col flex-shrink-0 bg-white/80" style="width:280px;backdrop-filter:blur(20px);border-right:1px solid rgba(0,0,0,0.06);">
            <div class="p-3">
                <button @click="showNewModal = true"
                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-white font-semibold text-sm transition-all duration-300 hover:shadow-lg hover:scale-[1.02]"
                    style="background:linear-gradient(135deg,#1B334A,#385776);">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Nova Analise</span>
                </button>
            </div>
            <div class="px-3 pb-2">
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="searchQuery" placeholder="Buscar analise..."
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border-0 bg-gray-100/80 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                @forelse($conversations as $conv)
                <a href="{{ route('justus.index', ['c' => $conv->id]) }}"
                   class="group block mx-2 mb-1 px-3 py-3 rounded-xl transition-all duration-200 {{ ($activeConversation && $activeConversation->id === $conv->id) ? 'shadow-md' : 'hover:bg-gray-50' }}"
                   style="{{ ($activeConversation && $activeConversation->id === $conv->id) ? 'background:linear-gradient(135deg,#eef2ff,#e8eef4);border-left:3px solid #385776;' : '' }}"
                   x-show="!searchQuery || '{{ strtolower($conv->title ?? '') }}'.includes(searchQuery.toLowerCase())">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold truncate flex-1" style="color:#1B334A;">{{ $conv->title ?: 'Sem titulo' }}</p>
                        <button onclick="event.preventDefault();event.stopPropagation();if(confirm('Excluir esta analise?'))deleteConversation({{ $conv->id }})"
                            class="p-1 rounded-lg text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all opacity-0 group-hover:opacity-100 flex-shrink-0" title="Excluir">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-2 mt-1.5">
                        <span class="text-[9px] px-1.5 py-0.5 rounded font-semibold {{ ($conv->mode ?? 'consultor') === 'consultor' ? 'bg-blue-50 text-blue-600' : 'bg-emerald-50 text-emerald-600' }}">{{ ($conv->mode ?? 'consultor') === 'consultor' ? 'CONS' : 'ASSES' }}</span>
                        @php
                            $tagStyles = [
                                'analise_estrategica' => 'background:linear-gradient(135deg,#dbeafe,#bfdbfe);color:#1e40af;',
                                'analise_completa' => 'background:linear-gradient(135deg,#ede9fe,#ddd6fe);color:#6d28d9;',
                                'peca' => 'background:linear-gradient(135deg,#fef3c7,#fde68a);color:#92400e;',
                                'calculo_prazo' => 'background:linear-gradient(135deg,#ccfbf1,#99f6e4);color:#0f766e;',
                                'higiene_autos' => 'background:linear-gradient(135deg,#dcfce7,#bbf7d0);color:#15803d;',
                            ];
                        @endphp
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold" style="{{ $tagStyles[$conv->type] ?? 'background:#f3f4f6;color:#6b7280;' }}">{{ $conv->type_label }}</span>
                        <span class="text-[10px] text-gray-400">{{ $conv->updated_at->format('d/m H:i') }}</span>
                    </div>
                </a>
                @empty
                <div class="p-6 text-center">
                    <div class="w-12 h-12 mx-auto mb-3 rounded-full flex items-center justify-center" style="background:linear-gradient(135deg,#e8eef4,#dbeafe);">
                        <svg class="w-6 h-6" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <p class="text-sm text-gray-400">Nenhuma analise</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- === COL CENTRAL: Chat === --}}
        <div class="flex flex-col flex-1 overflow-hidden min-h-0">
            @if($activeConversation)

            {{-- Status do documento (compacto) --}}
            @if($attachments->isNotEmpty())
            <div class="px-4 pt-3 flex-shrink-0">
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#15803d;border:1px solid #bbf7d0;">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $attachments->first()->original_name }}
                    @if($attachments->first()->isReady())
                     · {{ $attachments->first()->total_pages }} pags
                    @elseif($attachments->first()->isProcessing())
                     · Processando...
                    @endif
                </div>
            </div>
            @endif

            {{-- Mensagens --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-5 min-h-0" id="chatMessages">
                @if($messages->isEmpty())
                <div class="flex items-center justify-center h-full">
                    <div class="text-center max-w-md">
                        <div class="w-20 h-20 mx-auto mb-5 rounded-2xl flex items-center justify-center" style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 20px 40px rgba(27,51,74,0.3);">
                            <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <h3 class="text-xl font-bold mb-2" style="color:#1B334A;">Como posso ajudar?</h3>
                        <p class="text-sm text-gray-400 mb-6">Envie um documento PDF ou faca uma pergunta juridica.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="messageText = 'Analise os pontos fortes e fracos deste processo'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02]" style="background:white;border:1px solid #e5e7eb;">
                                <span class="font-semibold block mb-1" style="color:#385776;">Analise estrategica</span>
                                Pontos fortes e fracos
                            </button>
                            <button @click="messageText = 'Identifique os prazos processuais pendentes'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02]" style="background:white;border:1px solid #e5e7eb;">
                                <span class="font-semibold block mb-1" style="color:#385776;">Prazos</span>
                                Prazos pendentes
                            </button>
                            <button @click="messageText = 'Elabore um projeto de contestacao'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02]" style="background:white;border:1px solid #e5e7eb;">
                                <span class="font-semibold block mb-1" style="color:#385776;">Peca processual</span>
                                Projeto de contestacao
                            </button>
                            <button @click="messageText = 'Faca uma higiene dos autos e organize cronologicamente'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02]" style="background:white;border:1px solid #e5e7eb;">
                                <span class="font-semibold block mb-1" style="color:#385776;">Higiene de autos</span>
                                Organizar cronologia
                            </button>
                        </div>
                    </div>
                </div>
                @endif

                @foreach($messages as $msg)
                    @if($msg->role === 'user')
                    <div class="flex items-start gap-3 justify-end">
                        <div class="rounded-2xl rounded-tr-sm p-3 max-w-2xl shadow-sm" style="background:linear-gradient(135deg,#385776,#2a4a6b);">
                            <p class="text-sm text-white whitespace-pre-wrap">{{ $msg->content }}</p>
                        </div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                            <span class="text-xs font-bold text-white">{{ substr(auth()->user()->name, 0, 1) }}</span>
                        </div>
                    </div>
                    @elseif($msg->role === 'assistant')
                    <div class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 4px 12px rgba(27,51,74,0.3);">
                            <span class="text-white text-xs font-bold">J</span>
                        </div>
                        <div class="bg-white rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 p-4 max-w-3xl flex-1">
                            <div class="flex items-center gap-2 mb-2 pb-2 border-b border-gray-50">
                                <span class="font-bold text-xs" style="color:#1B334A;">JUSTUS</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background:#f0f2f5;color:#6b7280;">{{ $msg->model_used ?? '' }}</span>
                                <span class="text-[10px] text-gray-300 ml-auto">{{ $msg->created_at->format('H:i') }}</span>
                            </div>
                            <div class="prose prose-sm max-w-none text-gray-700 justus-md" x-data x-init="renderMarkdown($el)">{{ $msg->content }}</div>
                            @if($msg->input_tokens || $msg->output_tokens)
                            <div class="mt-3 pt-2 border-t border-gray-50 text-[10px] text-gray-300 flex items-center gap-3">
                                <span>{{ number_format($msg->input_tokens + $msg->output_tokens) }} tokens</span>
                                <span>R$ {{ number_format($msg->cost_brl, 4, ',', '.') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                @endforeach
            </div>

            {{-- Info bar --}}
            <div class="px-4 py-1.5 text-[10px] text-gray-400 flex items-center gap-4 border-t flex-shrink-0" style="background:rgba(255,255,255,0.7);">
                <span>{{ $activeConversation->type_label }}</span>
                <span>{{ number_format($activeConversation->total_input_tokens + $activeConversation->total_output_tokens) }} tokens</span>
                <span>R$ {{ number_format($activeConversation->total_cost_brl, 2, ',', '.') }}</span>
            </div>

            {{-- Input Premium --}}
            <div class="px-4 py-3 flex-shrink-0" style="background:linear-gradient(180deg,rgba(240,242,245,0),rgba(240,242,245,1));">
                <form action="{{ route('justus.upload', $activeConversation->id) }}" method="POST" enctype="multipart/form-data" x-ref="uploadForm" class="hidden">
                    @csrf
                    <input type="file" name="pdf_file" accept=".pdf" x-ref="pdfInput" @change="$refs.uploadForm.submit()">
                </form>
                <div class="flex items-end gap-2 bg-white rounded-2xl shadow-lg border border-gray-100 px-4 py-3 transition-all focus-within:shadow-xl focus-within:border-blue-200">
                    <button type="button" @click="$refs.pdfInput.click()"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all flex-shrink-0 mb-0.5" title="Enviar PDF">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>
                    <textarea x-model="messageText" placeholder="Pergunte sobre o processo, solicite uma analise ou peca..."
                        class="flex-1 px-2 py-2 text-sm border-0 outline-none bg-transparent resize-none"
                        style="min-height:44px;max-height:120px;"
                        :disabled="sending"
                        @keydown.enter.exact.prevent="sendMessage"
                        @input="$el.style.height='auto';$el.style.height=Math.min($el.scrollHeight,120)+'px'"
                        x-ref="msgInput"
                        rows="1"></textarea>
                    <button @click="sendMessage"
                        class="p-3 rounded-xl text-white transition-all duration-300 flex-shrink-0 mb-0.5"
                        :style="sending ? 'background:#94a3b8' : 'background:linear-gradient(135deg,#1B334A,#385776)'"
                        :disabled="sending"
                        :class="{'hover:shadow-lg hover:scale-105': !sending}">
                        <template x-if="!sending">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                        </template>
                        <template x-if="sending">
                            <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        </template>
                    </button>
                </div>
            </div>


            @else
            {{-- Estado Vazio Premium --}}
            <div class="flex-1 flex items-center justify-center" style="background:linear-gradient(180deg,#f0f2f5 0%,#e8eef4 100%);">
                <div class="text-center max-w-lg">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-3xl flex items-center justify-center" style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 25px 50px rgba(27,51,74,0.35);">
                        <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold mb-2" style="color:#1B334A;">JUSTUS</h2>
                    <p class="text-sm text-gray-500 mb-1">Assistente juridico com inteligencia artificial</p>
                    <p class="text-xs text-gray-400 mb-8">Analise processos, redija pecas e calcule prazos com IA</p>
                    <button @click="showNewModal = true"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white font-semibold text-sm transition-all duration-300 hover:shadow-xl hover:scale-105"
                        style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 10px 30px rgba(27,51,74,0.3);">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        <span>Iniciar Nova Analise</span>
                    </button>
                </div>
            </div>
            @endif
        </div>

        {{-- === COL DIREITA === --}}
        @if($activeConversation)
        <div class="flex-shrink-0 overflow-y-auto" style="width:300px;background:rgba(255,255,255,0.8);backdrop-filter:blur(20px);border-left:1px solid rgba(0,0,0,0.06);">

            <div class="p-4 border-b border-gray-100">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#e8eef4,#dbeafe);">
                        <svg class="w-3.5 h-3.5" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <h3 class="text-sm font-bold" style="color:#1B334A;">Dados do Processo</h3>
                </div>
                @if($profile)
                <div class="space-y-2.5" x-data="profileEditor()" x-init="loadProfile()">
                    <div>
                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">CNJ</label>
                        <input type="text" x-model="fields.numero_cnj" @change="saveProfile()"
                            class="w-full text-sm px-2.5 py-1.5 rounded-lg border-0 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all"
                            placeholder="Nao identificado">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Autor</label>
                            <input type="text" x-model="fields.autor" @change="saveProfile()"
                                class="w-full text-xs px-2.5 py-1.5 rounded-lg border-0 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all"
                                placeholder="—">
                        </div>
                        <div>
                            <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Reu</label>
                            <input type="text" x-model="fields.reu" @change="saveProfile()"
                                class="w-full text-xs px-2.5 py-1.5 rounded-lg border-0 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all"
                                placeholder="—">
                        </div>
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Fase</label>
                        <input type="text" x-model="fields.fase_atual" @change="saveProfile()"
                            class="w-full text-sm px-2.5 py-1.5 rounded-lg border-0 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all"
                            placeholder="—">
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Vara / Relator</label>
                        <input type="text" x-model="fields.relator_vara" @change="saveProfile()"
                            class="w-full text-sm px-2.5 py-1.5 rounded-lg border-0 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all"
                            placeholder="—">
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Objetivo</label>
                        <textarea x-model="fields.objetivo_analise" @change="saveProfile()" rows="2"
                            class="w-full text-xs px-2.5 py-1.5 rounded-lg border-0 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all resize-none"
                            placeholder="Descreva o objetivo..."></textarea>
                    </div>
                    <div>
                        <label class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Tese Principal</label>
                        <textarea x-model="fields.tese_principal" @change="saveProfile()" rows="2"
                            class="w-full text-xs px-2.5 py-1.5 rounded-lg border-0 bg-gray-50 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all resize-none"
                            placeholder="Descreva a tese..."></textarea>
                    </div>
                    <div x-show="saveStatus" x-transition class="text-[10px] text-green-500 font-medium" x-text="saveStatus"></div>
                </div>
                @else
                <p class="text-xs text-gray-400">Envie um PDF para preencher automaticamente.</p>
                @endif
            </div>

            {{-- Anexos --}}
            <div class="p-4">
                <div class="flex items-center gap-2 mb-3">
                    <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#fef2f2,#fecaca);">
                        <svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </div>
                    <h3 class="text-sm font-bold" style="color:#1B334A;">Anexos</h3>
                    <span class="text-[10px] text-gray-400 ml-auto">{{ $attachments->count() }}</span>
                </div>
                @forelse($attachments as $att)
                <div class="flex items-center gap-2.5 py-2 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                    <div class="w-8 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#fef2f2,#fecaca);">
                        <span class="text-red-500 text-[9px] font-bold">PDF</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium truncate" style="color:#1B334A;">{{ $att->original_name }}</p>
                        <p class="text-[10px] text-gray-400">{{ $att->file_size_human }}{{ $att->total_pages ? ' · '.$att->total_pages.' pags' : '' }}</p>
                    </div>
                    <a href="{{ route('justus.download', [$activeConversation->id, $att->id]) }}" class="p-1.5 rounded-lg hover:bg-gray-100 transition" title="Baixar">
                        <svg class="w-3.5 h-3.5" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </a>
                </div>
                @empty
                <p class="text-xs text-gray-400">Nenhum anexo.</p>
                @endforelse
            </div>
        </div>
        @endif
    </div>


<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<style>
    .justus-md p { margin-bottom: 0.5rem; }
    .justus-md ul, .justus-md ol { margin-left: 1.5rem; margin-bottom: 0.5rem; list-style: revert; }
    .justus-md li { margin-bottom: 0.25rem; }
    .justus-md strong { color: #1B334A; }
    .justus-md h1,.justus-md h2,.justus-md h3 { font-weight: 700; color: #1B334A; margin-top: 0.75rem; margin-bottom: 0.5rem; }
    .justus-md h1 { font-size: 1.1rem; }
    .justus-md h2 { font-size: 1rem; }
    .justus-md h3 { font-size: 0.9rem; }
    .justus-md code { background: #f3f4f6; padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-size: 0.85em; }
    .justus-md pre { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 0.75rem; overflow-x: auto; margin: 0.5rem 0; }
    .justus-md pre code { background: transparent; padding: 0; color: inherit; }
    .justus-md blockquote { border-left: 3px solid #385776; padding-left: 0.75rem; color: #6b7280; margin: 0.5rem 0; font-style: italic; }
    [x-cloak] { display: none !important; }
</style>

<script>
function justusApp() {
    return {
        searchQuery: '',
        messageText: '',
        sending: false,
        creating: false,

        init() {
            const flexRoot = document.querySelector('body > div.flex');
            if (flexRoot) { flexRoot.style.height = '100vh'; flexRoot.style.minHeight = '100vh'; flexRoot.style.maxHeight = '100vh'; flexRoot.style.overflow = 'hidden'; }
            const main = document.getElementById('main-content');
            if (main) { main.style.overflow = 'hidden'; main.style.display = 'flex'; main.style.flexDirection = 'column'; main.style.height = '100%'; main.style.maxHeight = '100%'; }
            const wrapper = this.$el.parentElement;
            if (wrapper) { wrapper.style.padding = '0'; wrapper.style.flex = '1'; wrapper.style.overflow = 'hidden'; wrapper.style.minHeight = '0'; wrapper.style.maxHeight = '100%'; }
            this.$el.style.height = '100%';
            this.$el.style.maxHeight = '100%';
            this.$nextTick(() => this.scrollToBottom());
        },

        scrollToBottom() {
            const c = document.getElementById('chatMessages');
            if (c) c.scrollTop = c.scrollHeight;
        },

        async sendMessage() {
            if (!this.messageText.trim() || this.sending) return;
            this.sending = true;
            const text = this.messageText.trim();
            this.messageText = '';

            const chat = document.getElementById('chatMessages');
            if (chat) {
                const initials = '{{ substr(auth()->user()->name ?? "U", 0, 1) }}';
                chat.insertAdjacentHTML('beforeend', `
                    <div class="flex items-start gap-3 justify-end">
                        <div class="rounded-2xl rounded-tr-sm p-3 max-w-2xl shadow-sm" style="background:linear-gradient(135deg,#385776,#2a4a6b);">
                            <p class="text-sm text-white whitespace-pre-wrap">${this.esc(text)}</p>
                        </div>
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                            <span class="text-xs font-bold text-white">${initials}</span>
                        </div>
                    </div>`);

                chat.insertAdjacentHTML('beforeend', `
                    <div id="justus-typing" class="flex items-start gap-3">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#1B334A,#385776);">
                            <span class="text-white text-xs font-bold">J</span>
                        </div>
                        <div class="bg-white rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 p-4">
                            <div class="flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full animate-bounce" style="background:#385776;animation-delay:0s"></span>
                                <span class="w-2 h-2 rounded-full animate-bounce" style="background:#385776;animation-delay:.15s"></span>
                                <span class="w-2 h-2 rounded-full animate-bounce" style="background:#385776;animation-delay:.3s"></span>
                                <span class="text-xs text-gray-400 ml-2">Analisando...</span>
                            </div>
                        </div>
                    </div>`);
                this.scrollToBottom();
            }

            try {
                const convId = '{{ $activeConversation ? $activeConversation->id : "" }}';
                const resp = await fetch(`/justus/${convId}/message`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ message: text }),
                });
                const result = await resp.json();
                const typing = document.getElementById('justus-typing');
                if (typing) typing.remove();

                if (result.success && result.message) {
                    const msg = result.message;
                    const mdHtml = (typeof marked !== 'undefined' && marked.parse) ? marked.parse(msg.content || '') : this.esc(msg.content || '').replace(/\n/g, '<br>');
                    const feedbackHtml = msg.role === 'assistant' ? `<div class="mt-2 flex items-center gap-2"><button onclick="sendFeedback(${msg.conversation_id || ''}, ${msg.id}, 'positive', this)" class="p-1 rounded text-gray-300 hover:text-green-500 hover:bg-green-50 transition-all" title="Boa resposta"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg></button><button onclick="sendFeedback(${msg.conversation_id || ''}, ${msg.id}, 'negative', this)" class="p-1 rounded text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all" title="Resposta ruim"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018c.163 0 .326.02.485.06L17 4m-7 10v2a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-6h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg></button></div>` : '';
                    const tokensInfo = msg.input_tokens ? `<div class="mt-3 pt-2 border-t border-gray-50 text-[10px] text-gray-300 flex items-center gap-3"><span>${(msg.input_tokens+msg.output_tokens).toLocaleString()} tokens</span><span>R$ ${parseFloat(msg.cost_brl).toFixed(4).replace('.',',')}</span></div>` : '';

                    chat.insertAdjacentHTML('beforeend', `
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 4px 12px rgba(27,51,74,0.3);">
                                <span class="text-white text-xs font-bold">J</span>
                            </div>
                            <div class="bg-white rounded-2xl rounded-tl-sm shadow-sm border border-gray-100 p-4 max-w-3xl flex-1">
                                <div class="flex items-center gap-2 mb-2 pb-2 border-b border-gray-50">
                                    <span class="font-bold text-xs" style="color:#1B334A;">JUSTUS</span>
                                    <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background:#f0f2f5;color:#6b7280;">${msg.model_used || ''}</span>
                                </div>
                                <div class="prose prose-sm max-w-none text-gray-700 justus-md">${mdHtml}</div>
                                ${feedbackHtml}
                                ${tokensInfo}
                            </div>
                        </div>`);
                    this.scrollToBottom();
                } else {
                    alert(result.error || 'Erro ao processar.');
                }
            } catch (err) {
                const typing = document.getElementById('justus-typing');
                if (typing) typing.remove();
                alert('Erro: ' + err.message);
            } finally {
                this.sending = false;
                if (this.$refs.msgInput) this.$refs.msgInput.focus();
            }
        },

        showNewModal: false,
        newMode: 'consultor',
        newType: 'analise_estrategica',

        async createConversation() {
            if (this.creating) return;
            this.creating = true;
            try {
                const resp = await fetch('/justus/conversations', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify({ title: 'Nova Analise', type: this.newType, mode: this.newMode }),
                });
                const data = await resp.json();
                if (data.success && data.conversation_id) {
                    window.location.href = '/justus?c=' + data.conversation_id;
                } else if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.error || 'Erro ao criar');
                    this.creating = false;
                }
            } catch (e) { alert('Erro: ' + e.message); this.creating = false; }
        },

        esc(t) {
            const m = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"};
            return t.replace(/[&<>"']/g, c => m[c]);
        }
    }
}

function profileEditor() {
    return {
        fields: {
            numero_cnj: '{{ $profile->numero_cnj ?? "" }}',
            autor: '{{ $profile->autor ?? "" }}',
            reu: '{{ $profile->reu ?? "" }}',
            fase_atual: '{{ $profile->fase_atual ?? "" }}',
            relator_vara: '{{ $profile->relator_vara ?? "" }}',
            objetivo_analise: `{{ addslashes($profile->objetivo_analise ?? "") }}`,
            tese_principal: `{{ addslashes($profile->tese_principal ?? "") }}`,
        },
        saveStatus: '',
        loadProfile() {},
        async saveProfile() {
            this.saveStatus = 'Salvando...';
            try {
                const convId = '{{ $activeConversation ? $activeConversation->id : "" }}';
                await fetch(`/justus/${convId}/profile`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
                    body: JSON.stringify(this.fields),
                });
                this.saveStatus = 'Salvo!';
                setTimeout(() => this.saveStatus = '', 2000);
            } catch (e) { this.saveStatus = 'Erro ao salvar'; }
        }
    }
}

function deleteConversation(id) {
    fetch(`/justus/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
    }).then(r => r.json()).then(data => {
        if (data.success) window.location.href = '/justus';
        else alert(data.error || 'Erro ao excluir');
    }).catch(e => alert('Erro: ' + e.message));
}

function renderMarkdown(el) {
    if (typeof marked !== 'undefined' && marked.parse) {
        const raw = el.textContent || el.innerText;
        el.innerHTML = marked.parse(raw);
    }
}
</script>

{{-- === MODAL NOVA ANALISE === --}}
<template x-if="showNewModal">
<div class="fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);" @click.self="showNewModal = false">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>
        <div class="px-6 py-4 border-b border-gray-100" style="background:linear-gradient(135deg,#1B334A,#385776);">
            <h3 class="text-base font-bold text-white">Nova Analise</h3>
            <p class="text-xs text-white/60 mt-0.5">Selecione o modo e o tipo de analise</p>
        </div>
        <div class="p-6 space-y-5">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Modo de Operacao</label>
                <div class="grid grid-cols-2 gap-3">
                    <button @click="newMode = 'consultor'" :class="newMode === 'consultor' ? 'ring-2 ring-blue-500 bg-blue-50' : 'bg-gray-50 hover:bg-gray-100'" class="p-3 rounded-xl text-left transition-all">
                        <div class="text-sm font-bold" :class="newMode === 'consultor' ? 'text-blue-700' : 'text-gray-700'">Consultor</div>
                        <div class="text-[10px] mt-1" :class="newMode === 'consultor' ? 'text-blue-500' : 'text-gray-400'">Analise de casos, pareceres, diagnosticos</div>
                    </button>
                    <button @click="newMode = 'assessor'" :class="newMode === 'assessor' ? 'ring-2 ring-emerald-500 bg-emerald-50' : 'bg-gray-50 hover:bg-gray-100'" class="p-3 rounded-xl text-left transition-all">
                        <div class="text-sm font-bold" :class="newMode === 'assessor' ? 'text-emerald-700' : 'text-gray-700'">Assessor</div>
                        <div class="text-[10px] mt-1" :class="newMode === 'assessor' ? 'text-emerald-500' : 'text-gray-400'">Processos, pecas, calculos, execucao</div>
                    </button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Tipo de Analise</label>
                <select x-model="newType" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-400 outline-none">
                    <option value="analise_estrategica">Analise Estrategica</option>
                    <option value="analise_completa">Analise Completa</option>
                    <option value="peca">Projeto de Peca</option>
                    <option value="higiene_autos">Higiene de Autos</option>
                    <option value="calculo_prazo">Calculo de Prazo</option>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-end gap-3 bg-gray-50/50">
            <button @click="showNewModal = false" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Cancelar</button>
            <button @click="showNewModal = false; createConversation()" :disabled="creating"
                class="px-5 py-2 rounded-xl text-white text-sm font-semibold transition-all hover:shadow-lg"
                style="background:linear-gradient(135deg,#1B334A,#385776);"
                :class="{'opacity-50 cursor-wait': creating}">
                <span x-text="creating ? 'Criando...' : 'Criar'"></span>
            </button>
        </div>
    </div>
</div>
</template>

<script>
async function sendFeedback(convId, msgId, type, btn) {
    if (!convId) convId = window.location.search.match(/c=(\d+)/)?.[1];
    if (!convId || !msgId) return;
    const parent = btn.parentElement;
    parent.querySelectorAll('button').forEach(b => b.disabled = true);
    try {
        const res = await fetch(`/justus/${convId}/messages/${msgId}/feedback`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ feedback: type }),
        });
        if (res.ok) {
            btn.classList.remove('text-gray-300');
            if (type === 'positive') { btn.classList.add('bg-green-100', 'text-green-600'); }
            else { btn.classList.add('bg-red-100', 'text-red-600'); }
        }
    } catch (e) { parent.querySelectorAll('button').forEach(b => b.disabled = false); }
}
</script>
@endsection
