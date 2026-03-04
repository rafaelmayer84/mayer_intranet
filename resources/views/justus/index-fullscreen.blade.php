<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>JUSTUS - Assistente Jurídico IA | Mayer Advogados</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Montserrat', system-ui, sans-serif; height: 100vh; overflow: hidden; background: #f0f2f5; }

        /* Scrollbar sutil */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: rgba(0,0,0,0.25); }

        /* Markdown rendered */
        .justus-md p { margin-bottom: 0.5rem; }
        .justus-md ul, .justus-md ol { margin-left: 1.5rem; margin-bottom: 0.5rem; list-style: revert; }
        .justus-md li { margin-bottom: 0.25rem; }
        .justus-md strong { color: #1B334A; }
        .justus-md h1,.justus-md h2,.justus-md h3 { font-weight: 700; color: #1B334A; margin-top: 0.75rem; margin-bottom: 0.5rem; }
        .justus-md h1 { font-size: 1.1rem; } .justus-md h2 { font-size: 1rem; } .justus-md h3 { font-size: 0.9rem; }
        .justus-md code { background: #f3f4f6; padding: 0.1rem 0.3rem; border-radius: 0.25rem; font-size: 0.85em; }
        .justus-md pre { background: #1e293b; color: #e2e8f0; padding: 1rem; border-radius: 0.75rem; overflow-x: auto; margin: 0.5rem 0; }
        .justus-md pre code { background: transparent; padding: 0; color: inherit; }
        .justus-md blockquote { border-left: 3px solid #385776; padding-left: 0.75rem; color: #6b7280; margin: 0.5rem 0; font-style: italic; }

        /* Upload progress bar */
        .upload-progress-bar { transition: width 0.3s ease; }
        .upload-overlay { backdrop-filter: blur(4px); }

        /* Pulse suave para processamento */
        @keyframes softPulse { 0%,100% { opacity: 0.6; } 50% { opacity: 1; } }
        .soft-pulse { animation: softPulse 2s ease-in-out infinite; }

        /* Insight card hover */
        .insight-card { transition: all 0.2s ease; }
        .insight-card:hover { background: rgba(56,87,118,0.04); }

        /* Collapse transition */
        .collapse-content { max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; }
        .collapse-content.open { max-height: 2000px; }

        [x-cloak] { display: none !important; }
    </style>
</head>
<body>
<div x-data="justusApp()" x-init="init()" class="flex flex-col h-screen">

    {{-- ===== HEADER ===== --}}
    <div class="flex items-center justify-between px-5 py-2.5 flex-shrink-0" style="background:linear-gradient(135deg,#1B334A 0%,#2a4a6b 50%,#385776 100%);">
        <div class="flex items-center gap-3">
            {{-- Botão Voltar --}}
            <a href="{{ url('/') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-white/60 hover:text-white hover:bg-white/10 transition-all text-xs" title="Voltar à Intranet">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span class="hidden sm:inline">Intranet</span>
            </a>
            <div class="w-px h-6 bg-white/20"></div>
            <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background:rgba(255,255,255,0.15);backdrop-filter:blur(10px);">
                <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
            <div>
                <span class="text-lg font-bold text-white tracking-wide">JUSTUS</span>
                <span class="ml-2 text-[10px] px-2 py-0.5 rounded-full font-semibold" style="background:rgba(255,255,255,0.2);color:#fbbf24;">IA</span>
            </div>
            <span class="text-xs text-white/50 ml-2 hidden md:inline">Assistente Jurídico Inteligente</span>
        </div>
        <div class="flex items-center gap-4">
            {{-- Homologação badge --}}
            <div class="hidden md:flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-semibold" style="background:rgba(245,158,11,0.2);color:#fbbf24;">
                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                HOMOLOGAÇÃO
            </div>
            @if(($budget['alert_level'] ?? 'normal') !== 'normal')
            <div class="flex items-center gap-2 px-3 py-1 rounded-lg text-xs font-medium" style="background:rgba(255,255,255,0.1);color:#fbbf24;">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01"/></svg>
                {{ number_format($budget['global']['total_tokens'] ?? 0) }} tokens · R$ {{ number_format($budget['global']['cost_brl'] ?? 0, 2, ',', '.') }}
            </div>
            @endif
            <div class="text-xs text-white/40">
                {{ number_format(($budget['global']['total_tokens'] ?? 0) / 1000, 1) }}k tokens
            </div>
            @if(auth()->user()->role === 'admin')
            <a href="{{ route('justus.admin.config') }}" class="flex items-center gap-1 px-2.5 py-1 rounded-lg text-xs text-white/60 hover:text-white hover:bg-white/10 transition-all" title="Configuração">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Config
            </a>
            @endif
            {{-- Avatar do usuário --}}
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white" style="background:linear-gradient(135deg,#6366f1,#8b5cf6);">
                {{ substr(auth()->user()->name, 0, 1) }}
            </div>
        </div>
    </div>

    {{-- ===== CORPO: 3 COLUNAS ===== --}}
    <div class="flex flex-1 overflow-hidden min-h-0">

        {{-- === COL ESQUERDA: Conversas === --}}
        <div class="flex flex-col flex-shrink-0 bg-white/80" style="width:280px;backdrop-filter:blur(20px);border-right:1px solid rgba(0,0,0,0.06);">
            <div class="p-3">
                @if(auth()->user()->is_admin)
                <a href="{{ route('justus.admin.config') }}" class="text-xs text-gray-400 hover:text-gray-600 mb-2 block" title="Administração">⚙ Admin</a>
                @endif

                <button onclick="openNewAnalysisModal()"
                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-xl text-white font-semibold text-sm transition-all duration-300 hover:shadow-lg hover:scale-[1.02]"
                    style="background:linear-gradient(135deg,#1B334A,#385776);">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Nova Análise
                </button>
            </div>
            <div class="px-3 pb-2">
                <div class="relative">
                    <svg class="w-4 h-4 absolute left-3 top-2.5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    <input type="text" x-model="searchQuery" placeholder="Buscar análise..."
                        class="w-full pl-9 pr-3 py-2 text-sm rounded-xl border-0 bg-gray-100/80 focus:bg-white focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                </div>
            </div>
            <div class="flex-1 overflow-y-auto">
                @forelse($conversations as $conv)
                <a href="{{ url('/justus/app?c=' . $conv->id) }}"
                   class="group block mx-2 mb-1 px-3 py-3 rounded-xl transition-all duration-200 {{ ($activeConversation && $activeConversation->id === $conv->id) ? 'shadow-md' : 'hover:bg-gray-50' }}"
                   style="{{ ($activeConversation && $activeConversation->id === $conv->id) ? 'background:linear-gradient(135deg,#eef2ff,#e8eef4);border-left:3px solid #385776;' : '' }}"
                   x-show="!searchQuery || '{{ strtolower($conv->title ?? '') }}'.includes(searchQuery.toLowerCase())">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold truncate flex-1" style="color:#1B334A;">{{ $conv->title ?: 'Sem título' }}</p>
                        <button onclick="event.preventDefault();event.stopPropagation();if(confirm('Excluir esta análise?'))deleteConversation({{ $conv->id }})"
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
                    <p class="text-sm text-gray-400">Nenhuma análise</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- === COL CENTRAL: Chat === --}}
        <div class="flex flex-col flex-1 overflow-hidden min-h-0">
            @if($activeConversation)

            {{-- Status do documento + Upload progress --}}
            <div class="px-4 pt-3 flex-shrink-0">
                {{-- Barra de progresso de upload (visível durante upload) --}}
                <div x-show="uploadState !== 'idle'" x-cloak class="mb-2">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3">
                        {{-- Estado: Uploading --}}
                        <template x-if="uploadState === 'uploading'">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-blue-500 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                                        <span class="text-sm font-medium" style="color:#1B334A;">Enviando <span x-text="uploadFileName" class="font-semibold"></span></span>
                                    </div>
                                    <span class="text-xs font-bold" style="color:#385776;" x-text="uploadPercent + '%'"></span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div class="h-full rounded-full upload-progress-bar" style="background:linear-gradient(90deg,#385776,#2a4a6b);" :style="'width:' + uploadPercent + '%'"></div>
                                </div>
                                <div class="flex justify-between mt-1.5 text-[10px] text-gray-400">
                                    <span x-text="uploadSizeSent + ' / ' + uploadSizeTotal"></span>
                                    <span>Upload em andamento...</span>
                                </div>
                            </div>
                        </template>
                        {{-- Estado: Processing (backend) --}}
                        <template x-if="uploadState === 'processing'">
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <div class="w-4 h-4 rounded-full soft-pulse" style="background:linear-gradient(135deg,#385776,#2a4a6b);"></div>
                                    <span class="text-sm font-medium" style="color:#1B334A;">Processando documento...</span>
                                </div>
                                <div class="w-full bg-gray-100 rounded-full h-2 overflow-hidden">
                                    <div class="h-full rounded-full w-full" style="background:linear-gradient(90deg,#385776,#2a4a6b,#385776);background-size:200% 100%;animation:shimmer 1.5s ease-in-out infinite;"></div>
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1.5">Extraindo texto, classificando páginas e preparando contexto para IA...</p>
                            </div>
                        </template>
                        {{-- Estado: Done --}}
                        <template x-if="uploadState === 'done'">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm font-medium text-green-600">Documento processado com sucesso!</span>
                                <span class="text-[10px] text-gray-400 ml-auto" x-text="uploadPagesInfo"></span>
                            </div>
                        </template>
                        {{-- Estado: Error --}}
                        <template x-if="uploadState === 'error'">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                <span class="text-sm font-medium text-red-600" x-text="uploadError"></span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Documento já processado (status estático) --}}
                @if($attachments->isNotEmpty() && $attachments->first()->isReady())
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium" style="background:linear-gradient(135deg,#f0fdf4,#dcfce7);color:#15803d;border:1px solid #bbf7d0;" x-show="uploadState === 'idle'">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $attachments->first()->original_name }} · {{ $attachments->first()->total_pages }} págs
                </div>
                @elseif($attachments->isNotEmpty() && $attachments->first()->isProcessing())
                <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;" x-show="uploadState === 'idle'" x-init="startPollingAttachment({{ $attachments->first()->id }})">
                    <svg class="w-3.5 h-3.5 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    {{ $attachments->first()->original_name }} · Processando...
                </div>
                @endif
            </div>

            {{-- Mensagens --}}
            <div class="flex-1 overflow-y-auto px-4 py-4 space-y-5 min-h-0" id="chatMessages">
                @if($messages->isEmpty())
                <div class="flex items-center justify-center h-full">
                    <div class="text-center max-w-md">
                        <div class="w-20 h-20 mx-auto mb-5 rounded-2xl flex items-center justify-center" style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 20px 40px rgba(27,51,74,0.3);">
                            <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <h3 class="text-xl font-bold mb-2" style="color:#1B334A;">Como posso ajudar?</h3>
                        <p class="text-sm text-gray-400 mb-6">Envie um documento PDF ou faça uma pergunta jurídica.</p>
                        <div class="grid grid-cols-2 gap-2">
                            <button @click="messageText = 'Analise os pontos fortes e fracos deste processo'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02] bg-white border border-gray-100">
                                <span class="font-semibold block mb-1" style="color:#385776;">Análise estratégica</span>
                                Pontos fortes e fracos
                            </button>
                            <button @click="messageText = 'Identifique os prazos processuais pendentes'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02] bg-white border border-gray-100">
                                <span class="font-semibold block mb-1" style="color:#385776;">Prazos</span>
                                Prazos pendentes
                            </button>
                            <button @click="messageText = 'Elabore um projeto de contestação'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02] bg-white border border-gray-100">
                                <span class="font-semibold block mb-1" style="color:#385776;">Peça processual</span>
                                Projeto de contestação
                            </button>
                            <button @click="messageText = 'Faça uma higiene dos autos e organize cronologicamente'" class="p-3 rounded-xl text-left text-xs text-gray-600 transition-all hover:shadow-md hover:scale-[1.02] bg-white border border-gray-100">
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
                            @php $msgMeta = json_decode($msg->metadata ?? '{}', true); @endphp
                            @if(!empty($msgMeta['doc_path']))
                            <div class="mt-3 mb-2">
                                <a href="{{ route('justus.message.document', [$activeConversation->id, $msg->id]) }}" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white hover:shadow-lg" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                    Baixar Peça (DOCX)
                                </a>
                            </div>
                            @endif
                            <div class="mt-2 flex items-center gap-2">
                                <button onclick="sendFeedback({{ $activeConversation->id }}, {{ $msg->id }}, 'positive', this)" class="p-1 rounded text-gray-300 hover:text-green-500 hover:bg-green-50 transition-all" title="Boa resposta">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
                                </button>
                                <button onclick="sendFeedback({{ $activeConversation->id }}, {{ $msg->id }}, 'negative', this)" class="p-1 rounded text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all" title="Resposta ruim">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018c.163 0 .326.02.485.06L17 4m-7 10v2a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-6h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg>
                                </button>
                            </div>
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
                <span><span id="justus-token-count">{{ number_format($activeConversation->total_input_tokens + $activeConversation->total_output_tokens) }}</span> tokens</span>
                <span>R$ {{ number_format($activeConversation->total_cost_brl, 2, ',', '.') }}</span>
            </div>

            {{-- Input Premium --}}
            <div class="px-4 py-3 flex-shrink-0" style="background:linear-gradient(180deg,rgba(240,242,245,0),rgba(240,242,245,1));">
                <input type="file" accept=".pdf" x-ref="pdfInput" @change="handleFileUpload($event)" class="hidden">
                <div class="flex items-end gap-2 bg-white rounded-2xl shadow-lg border border-gray-100 px-4 py-3 transition-all focus-within:shadow-xl focus-within:border-blue-200">
                    <button type="button" @click="$refs.pdfInput.click()"
                        class="p-2 rounded-xl text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-all flex-shrink-0 mb-0.5" title="Enviar PDF">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    </button>
                    <textarea x-model="messageText" @keydown.enter.exact.prevent="sendMessage()" placeholder="Pergunte sobre o processo, solicite uma análise ou peça..."
                        class="flex-1 px-2 py-2 text-sm border-0 outline-none bg-transparent resize-none"
                        style="min-height:44px;max-height:120px;"
                        :disabled="sending"
                        @input="$el.style.height='auto';$el.style.height=Math.min($el.scrollHeight,120)+'px'"
                        x-ref="msgInput"
                        rows="1"></textarea>
                    <button @click="sendMessage()"
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
            {{-- Estado Vazio --}}
            <div class="flex-1 flex items-center justify-center" style="background:linear-gradient(180deg,#f0f2f5 0%,#e8eef4 100%);">
                <div class="text-center max-w-lg">
                    <div class="w-24 h-24 mx-auto mb-6 rounded-3xl flex items-center justify-center" style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 25px 50px rgba(27,51,74,0.35);">
                        <svg class="w-12 h-12 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                    </div>
                    <h2 class="text-2xl font-bold mb-2" style="color:#1B334A;">JUSTUS</h2>
                    <p class="text-sm text-gray-500 mb-1">Assistente jurídico com inteligência artificial</p>
                    <p class="text-xs text-gray-400 mb-8">Analise processos, redija peças e calcule prazos com IA</p>
                    <button onclick="openNewAnalysisModal()"
                        class="inline-flex items-center gap-2 px-6 py-3 rounded-xl text-white font-semibold text-sm transition-all duration-300 hover:shadow-xl hover:scale-105"
                        style="background:linear-gradient(135deg,#1B334A,#385776);box-shadow:0 10px 30px rgba(27,51,74,0.3);">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Iniciar Nova Análise
                    </button>
                </div>
            </div>
            @endif
        </div>

        {{-- === COL DIREITA: Insights do Processo === --}}
        @if($activeConversation)
        <div class="flex-shrink-0 overflow-y-auto" style="width:320px;background:rgba(255,255,255,0.9);backdrop-filter:blur(20px);border-left:1px solid rgba(0,0,0,0.06);" x-data="insightsPanel()" x-init="initInsights()">

            {{-- SEÇÃO A: Dados Extraídos do Processo --}}
            <div class="border-b border-gray-100">
                <button @click="sections.dados = !sections.dados" class="w-full flex items-center justify-between p-4 hover:bg-gray-50/50 transition-all">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#e8eef4,#dbeafe);">
                            <svg class="w-3.5 h-3.5" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <h3 class="text-sm font-bold" style="color:#1B334A;">Dados do Processo</h3>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="sections.dados ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="collapse-content px-4 pb-4" :class="sections.dados ? 'open' : ''">
                    @if($profile && ($profile->numero_cnj || $profile->autor || $profile->reu))
                    <div class="space-y-3">
                        @if($profile->numero_cnj)
                        <div class="insight-card rounded-lg p-2.5">
                            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Número CNJ</div>
                            <div class="text-sm font-semibold" style="color:#1B334A;">{{ $profile->numero_cnj }}</div>
                        </div>
                        @endif
                        <div class="grid grid-cols-2 gap-2">
                            <div class="insight-card rounded-lg p-2.5">
                                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Autor</div>
                                <div class="text-xs font-medium" style="color:#1B334A;">{{ $profile->autor ?: '—' }}</div>
                            </div>
                            <div class="insight-card rounded-lg p-2.5">
                                <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Réu</div>
                                <div class="text-xs font-medium" style="color:#1B334A;">{{ $profile->reu ?: '—' }}</div>
                            </div>
                        </div>
                        @if($profile->classe)
                        <div class="insight-card rounded-lg p-2.5">
                            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Classe</div>
                            <div class="text-xs font-medium" style="color:#1B334A;">{{ $profile->classe }}</div>
                        </div>
                        @endif
                        @if($profile->relator_vara)
                        <div class="insight-card rounded-lg p-2.5">
                            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Vara / Relator</div>
                            <div class="text-xs font-medium" style="color:#1B334A;">{{ $profile->relator_vara }}</div>
                        </div>
                        @endif
                        @if($profile->fase_atual)
                        <div class="insight-card rounded-lg p-2.5">
                            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Fase</div>
                            <div class="text-xs font-medium" style="color:#1B334A;">{{ $profile->fase_atual }}</div>
                        </div>
                        @endif
                        @if($profile->data_intimacao)
                        <div class="insight-card rounded-lg p-2.5">
                            <div class="text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-0.5">Data Intimação</div>
                            <div class="text-xs font-medium" style="color:#1B334A;">{{ $profile->data_intimacao->format('d/m/Y') }}</div>
                        </div>
                        @endif
                    </div>
                    @else
                    <div class="text-center py-4">
                        <div class="w-10 h-10 mx-auto mb-2 rounded-full flex items-center justify-center bg-gray-50">
                            <svg class="w-5 h-5 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </div>
                        <p class="text-xs text-gray-400">Envie um PDF para extrair dados automaticamente</p>
                    </div>
                    @endif
                </div>
            </div>

            {{-- SEÇÃO B: Sugestões de Ações --}}
            <div class="border-b border-gray-100">
                <button @click="sections.acoes = !sections.acoes" class="w-full flex items-center justify-between p-4 hover:bg-gray-50/50 transition-all">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#fef3c7,#fde68a);">
                            <svg class="w-3.5 h-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                        </div>
                        <h3 class="text-sm font-bold" style="color:#1B334A;">Sugestões</h3>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="sections.acoes ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="collapse-content px-4 pb-4" :class="sections.acoes ? 'open' : ''">
                    @php
                        $suggestions = [];
                        $type = $activeConversation->type;
                        $hasDoc = $attachments->isNotEmpty() && $attachments->first()->isReady();
                        if (!$hasDoc) {
                            $suggestions[] = ['icon' => 'upload', 'text' => 'Envie o PDF do processo para análise completa', 'action' => 'upload'];
                        }
                        if ($type === 'analise_estrategica') {
                            $suggestions[] = ['icon' => 'search', 'text' => 'Peça para identificar pontos fortes e vulnerabilidades', 'action' => 'Identifique os pontos fortes e as vulnerabilidades processuais'];
                            $suggestions[] = ['icon' => 'scale', 'text' => 'Solicite avaliação de chances de êxito', 'action' => 'Avalie as chances de êxito neste processo, considerando a jurisprudência'];
                        } elseif ($type === 'analise_completa') {
                            $suggestions[] = ['icon' => 'doc', 'text' => 'Solicite resumo completo dos autos', 'action' => 'Faça um resumo completo e cronológico de todos os atos processuais'];
                            $suggestions[] = ['icon' => 'search', 'text' => 'Peça análise de todas as peças', 'action' => 'Analise cada peça processual identificando argumentos centrais'];
                        } elseif ($type === 'peca') {
                            $suggestions[] = ['icon' => 'edit', 'text' => 'Solicite o projeto da peça processual', 'action' => 'Elabore um projeto de peça processual adequada para este caso'];
                            $suggestions[] = ['icon' => 'search', 'text' => 'Peça fundamentação com jurisprudência', 'action' => 'Fundamente a tese com jurisprudência atualizada do TJSC e STJ'];
                        } elseif ($type === 'calculo_prazo') {
                            $suggestions[] = ['icon' => 'clock', 'text' => 'Calcule prazos a partir da intimação', 'action' => 'Calcule todos os prazos processuais a partir da última intimação'];
                            $suggestions[] = ['icon' => 'alert', 'text' => 'Verifique prazos críticos', 'action' => 'Identifique prazos que estão próximos de vencer ou já vencidos'];
                        } elseif ($type === 'higiene_autos') {
                            $suggestions[] = ['icon' => 'list', 'text' => 'Organize cronologicamente os autos', 'action' => 'Organize todos os documentos em ordem cronológica com classificação'];
                            $suggestions[] = ['icon' => 'filter', 'text' => 'Identifique documentos faltantes', 'action' => 'Identifique documentos que deveriam constar nos autos mas não foram localizados'];
                        }
                        if ($hasDoc) {
                            $suggestions[] = ['icon' => 'juris', 'text' => 'Busque jurisprudência relevante', 'action' => 'Busque jurisprudência relevante para fundamentar este caso'];
                        }
                    @endphp
                    <div class="space-y-1.5">
                        @foreach($suggestions as $sug)
                        <button @click="{{ $sug['action'] === 'upload' ? "\$refs.pdfInput.click()" : "messageText = '{$sug['action']}'; sendMessage()" }}"
                            class="w-full flex items-start gap-2.5 p-2.5 rounded-lg text-left hover:bg-gray-50 transition-all group">
                            <div class="w-5 h-5 rounded flex items-center justify-center flex-shrink-0 mt-0.5" style="background:rgba(56,87,118,0.08);">
                                @if($sug['icon'] === 'upload')
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                @elseif($sug['icon'] === 'search' || $sug['icon'] === 'juris')
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                                @elseif($sug['icon'] === 'scale')
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
                                @elseif($sug['icon'] === 'doc' || $sug['icon'] === 'edit')
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                @elseif($sug['icon'] === 'clock')
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                @elseif($sug['icon'] === 'alert')
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                @elseif($sug['icon'] === 'list')
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                @else
                                <svg class="w-3 h-3" style="color:#385776;" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                                @endif
                            </div>
                            <span class="text-xs text-gray-600 group-hover:text-gray-800 leading-relaxed">{{ $sug['text'] }}</span>
                        </button>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- SEÇÃO C: Jurisprudência Relacionada --}}
            <div class="border-b border-gray-100">
                <button @click="sections.juris = !sections.juris; if(sections.juris && !jurisLoaded) loadJurisprudencia()" class="w-full flex items-center justify-between p-4 hover:bg-gray-50/50 transition-all">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#ede9fe,#ddd6fe);">
                            <svg class="w-3.5 h-3.5 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
                        </div>
                        <h3 class="text-sm font-bold" style="color:#1B334A;">Jurisprudência</h3>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-50 text-purple-600 font-semibold" x-text="jurisCount + ' encontrados'" x-show="jurisLoaded" x-cloak></span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="sections.juris ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="collapse-content px-4 pb-4" :class="sections.juris ? 'open' : ''">
                    {{-- Loading --}}
                    <div x-show="jurisLoading" class="text-center py-4">
                        <svg class="w-5 h-5 mx-auto animate-spin text-purple-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                        <p class="text-[10px] text-gray-400 mt-2">Buscando jurisprudência nos 4 tribunais...</p>
                    </div>
                    {{-- Resultados --}}
                    <div x-show="jurisLoaded && !jurisLoading" x-cloak>
                        <template x-if="jurisResults.length === 0">
                            <div class="text-center py-4">
                                <p class="text-xs text-gray-400">Nenhuma jurisprudência encontrada para este processo.</p>
                                <p class="text-[10px] text-gray-300 mt-1">Envie um PDF ou faça uma pergunta para ativar a busca.</p>
                            </div>
                        </template>
                        <template x-for="(juris, idx) in jurisResults" :key="idx">
                            <div class="mb-2.5 p-2.5 rounded-lg border border-gray-100 hover:border-purple-200 hover:shadow-sm transition-all">
                                <div class="flex items-center gap-1.5 mb-1">
                                    <span class="text-[9px] px-1.5 py-0.5 rounded font-bold text-white" :style="'background:' + (juris.tribunal === 'STJ' ? '#1e40af' : juris.tribunal === 'TJSC' ? '#15803d' : juris.tribunal === 'TRF4' ? '#9333ea' : '#b45309')" x-text="juris.tribunal"></span>
                                    <span class="text-[10px] font-semibold" style="color:#1B334A;" x-text="juris.sigla_classe + ' ' + juris.numero_registro"></span>
                                </div>
                                <p class="text-[10px] text-gray-500 leading-relaxed line-clamp-3 cursor-pointer hover:text-gray-700" x-text="juris.ementa_resumida" @click="showJurisDetail(juris)" title="Clique para ver ementa completa"></p>
                                <div class="flex items-center gap-2 mt-1.5 text-[9px] text-gray-400">
                                    <span x-text="juris.relator ? 'Rel. ' + juris.relator : ''"></span>
                                    <span x-text="juris.data_decisao || ''"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            {{-- SEÇÃO D: Anexos --}}
            <div>
                <button @click="sections.anexos = !sections.anexos" class="w-full flex items-center justify-between p-4 hover:bg-gray-50/50 transition-all">
                    <div class="flex items-center gap-2">
                        <div class="w-6 h-6 rounded-lg flex items-center justify-center" style="background:linear-gradient(135deg,#fef2f2,#fecaca);">
                            <svg class="w-3.5 h-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </div>
                        <h3 class="text-sm font-bold" style="color:#1B334A;">Anexos</h3>
                        <span class="text-[10px] text-gray-400">{{ $attachments->count() }}</span>
                    </div>
                    <svg class="w-4 h-4 text-gray-400 transition-transform" :class="sections.anexos ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div class="collapse-content px-4 pb-4" :class="sections.anexos ? 'open' : ''">
                    @forelse($attachments as $att)
                    <div class="flex items-center gap-2.5 py-2 {{ !$loop->last ? 'border-b border-gray-50' : '' }}">
                        <div class="w-8 h-9 rounded-lg flex items-center justify-center flex-shrink-0" style="background:linear-gradient(135deg,#fef2f2,#fecaca);">
                            <span class="text-red-500 text-[9px] font-bold">PDF</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium truncate" style="color:#1B334A;">{{ $att->original_name }}</p>
                            <p class="text-[10px] text-gray-400">{{ $att->file_size_human }}{{ $att->total_pages ? ' · '.$att->total_pages.' págs' : '' }}</p>
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
        </div>
        @endif
    </div>


<style>
    @keyframes shimmer { 0% { background-position: -200% 0; } 100% { background-position: 200% 0; } }
    .line-clamp-3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
</style>

<script>
/* ========== JUSTUS APP (Alpine.js) ========== */
function justusApp() {
    return {
        searchQuery: '',
        messageText: '',
        sending: false,
        showNewModal: false,
        newMode: 'consultor',
        newType: 'analise_estrategica',
        creating: false,

        // Upload state
        uploadState: 'idle', // idle, uploading, processing, done, error
        uploadPercent: 0,
        uploadFileName: '',
        uploadSizeSent: '',
        uploadSizeTotal: '',
        uploadError: '',
        uploadPagesInfo: '',
        uploadAttachmentId: null,
        pollTimer: null,

        init() {
            this.$nextTick(() => this.scrollToBottom());
        },

        scrollToBottom() {
            const c = document.getElementById('chatMessages');
            if (c) c.scrollTop = c.scrollHeight;
        },

        formatBytes(bytes) {
            if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
            if (bytes >= 1024) return (bytes / 1024).toFixed(0) + ' KB';
            return bytes + ' B';
        },

        /* === UPLOAD COM PROGRESSO (XHR) === */
        handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            if (!file.name.toLowerCase().endsWith('.pdf')) {
                alert('Apenas arquivos PDF são aceitos.');
                event.target.value = '';
                return;
            }

            this.uploadState = 'uploading';
            this.uploadPercent = 0;
            this.uploadFileName = file.name;
            this.uploadSizeTotal = this.formatBytes(file.size);
            this.uploadSizeSent = '0 B';

            const formData = new FormData();
            formData.append('pdf_file', file);
            formData.append('_token', document.querySelector('meta[name="csrf-token"]').content);

            const convId = '{{ $activeConversation ? $activeConversation->id : "" }}';
            const xhr = new XMLHttpRequest();

            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    this.uploadPercent = Math.round((e.loaded / e.total) * 100);
                    this.uploadSizeSent = this.formatBytes(e.loaded);
                }
            });

            xhr.addEventListener('load', () => {
                try {
                    const result = JSON.parse(xhr.responseText);
                    if (result.success && result.attachment) {
                        this.uploadAttachmentId = result.attachment.id;
                        this.uploadState = 'processing';
                        this.startPollingAttachment(result.attachment.id);
                    } else {
                        this.uploadState = 'error';
                        this.uploadError = result.message || 'Erro ao enviar arquivo.';
                    }
                } catch (e) {
                    this.uploadState = 'error';
                    this.uploadError = 'Erro inesperado no upload.';
                }
            });

            xhr.addEventListener('error', () => {
                this.uploadState = 'error';
                this.uploadError = 'Falha na conexão durante o upload.';
            });

            xhr.open('POST', `/justus/${convId}/upload`);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.send(formData);

            // Reset input
            event.target.value = '';
        },

        /* === POLLING de status do processamento === */
        startPollingAttachment(attachmentId) {
            if (this.pollTimer) clearInterval(this.pollTimer);
            const convId = '{{ $activeConversation ? $activeConversation->id : "" }}';

            this.pollTimer = setInterval(async () => {
                try {
                    const resp = await fetch(`/justus/${convId}/attachment-status/${attachmentId}`, {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await resp.json();

                    if (data.status === 'completed') {
                        clearInterval(this.pollTimer);
                        this.uploadState = 'done';
                        this.uploadPagesInfo = (data.total_pages || 0) + ' páginas processadas';
                        // Reload após 2s para atualizar sidebar de insights
                        setTimeout(() => { window.location.href = window.location.href; }, 2000);
                    } else if (data.status === 'failed') {
                        clearInterval(this.pollTimer);
                        this.uploadState = 'error';
                        this.uploadError = data.error || 'Falha no processamento do PDF.';
                    }
                    // Se still processing, continua polling
                } catch (e) {
                    // Silencioso, tenta novamente
                }
            }, 3000);
        },

        /* === ENVIAR MENSAGEM === */
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
                    const docMeta = msg.metadata ? (typeof msg.metadata === 'string' ? JSON.parse(msg.metadata) : msg.metadata) : {};
                    const convId2 = msg.conversation_id || '{{ $activeConversation ? $activeConversation->id : "" }}';
                    const docBtnHtml = (msg.role === 'assistant' && docMeta.doc_path) ? `<div class="mt-3 mb-2"><a href="/justus/${convId2}/messages/${msg.id}/document" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white hover:shadow-lg" style="background:linear-gradient(135deg,#7c3aed,#6d28d9);"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>Baixar Peça (DOCX)</a></div>` : '';
                    const feedbackHtml = msg.role === 'assistant' ? `<div class="mt-2 flex items-center gap-2"><button onclick="sendFeedback(${convId2}, ${msg.id}, 'positive', this)" class="p-1 rounded text-gray-300 hover:text-green-500 hover:bg-green-50 transition-all" title="Boa resposta"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg></button><button onclick="sendFeedback(${convId2}, ${msg.id}, 'negative', this)" class="p-1 rounded text-gray-300 hover:text-red-500 hover:bg-red-50 transition-all" title="Resposta ruim"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14H5.236a2 2 0 01-1.789-2.894l3.5-7A2 2 0 018.736 3h4.018c.163 0 .326.02.485.06L17 4m-7 10v2a2 2 0 002 2h.095c.5 0 .905-.405.905-.905 0-.714.211-1.412.608-2.006L17 13V4m-7 10h2m5-6h2a2 2 0 012 2v6a2 2 0 01-2 2h-2.5"/></svg></button></div>` : '';
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
                                ${docBtnHtml}
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

        esc(t) {
            const m = {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#039;"};
            return t.replace(/[&<>"']/g, c => m[c]);
        }
    }
}

/* ========== INSIGHTS PANEL (Alpine.js) ========== */
function insightsPanel() {
    return {
        sections: { dados: true, acoes: true, juris: false, anexos: true },
        jurisLoading: false,
        jurisLoaded: false,
        jurisResults: [],
        jurisCount: 0,

        initInsights() {
            // Auto-load jurisprudência se há documento processado
            @if($attachments->isNotEmpty() && $attachments->first()->isReady())
            this.sections.juris = true;
            this.loadJurisprudencia();
            @endif
        },

        showJurisDetail(juris) {
            var t = (juris.tribunal||'').toUpperCase();
            var rl = (t==='STJ'||t==='STF'||t==='TST')?'Rel. Min.':(t.startsWith('TRF')?'Rel. Des. Federal':'Rel. Des.');
            var hdr = (juris.sigla_classe||'')+' '+(juris.numero_processo||juris.numero_registro||'');
            var meta = rl+' '+(juris.relator||'')+' | '+(juris.orgao_julgador||'')+' | j. '+(juris.data_decisao||'');
            var ov = document.createElement('div');
            ov.style.cssText = 'position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);';
            ov.onclick = function(e){ if(e.target===ov) ov.remove(); };
            ov.innerHTML = '<div style="background:white;border-radius:1rem;box-shadow:0 25px 50px rgba(0,0,0,0.3);width:90%;max-width:700px;max-height:80vh;display:flex;flex-direction:column;overflow:hidden;" onclick="event.stopPropagation()">'
                +'<div style="padding:1rem 1.5rem;border-bottom:1px solid #e5e7eb;background:linear-gradient(135deg,#1B334A,#385776);flex-shrink:0;">'
                +'<div style="display:flex;justify-content:space-between;align-items:start;">'
                +'<div><h3 style="font-size:0.95rem;font-weight:700;color:white;margin:0;">'+hdr+'</h3>'
                +'<p style="font-size:0.7rem;color:rgba(255,255,255,0.7);margin:0.25rem 0 0;">'+meta+'</p></div>'
                +'<button onclick="this.closest([].find.call(document.querySelectorAll(\x27[style*=fixed]\x27),function(x){return x.style.zIndex==99999})).remove()" style="color:rgba(255,255,255,0.6);background:none;border:none;font-size:1.5rem;cursor:pointer;line-height:1;">&times;</button>'
                +'</div></div>'
                +'<div style="padding:1.5rem;overflow-y:auto;flex:1;">'
                +'<p style="font-size:0.8rem;font-weight:600;color:#374151;margin:0 0 0.5rem;">EMENTA</p>'
                +'<p style="font-size:0.8rem;color:#4b5563;line-height:1.6;white-space:pre-wrap;">'+(juris.ementa_completa||juris.ementa_resumida||'')+'</p>'
                +'</div></div>';
            document.body.appendChild(ov);
            document.addEventListener('keydown',function esc(e){if(e.key==='Escape'){ov.remove();document.removeEventListener('keydown',esc);}});
        },
        async loadJurisprudencia() {
            if (this.jurisLoaded || this.jurisLoading) return;
            this.jurisLoading = true;

            const convId = '{{ $activeConversation ? $activeConversation->id : "" }}';
            try {
                const resp = await fetch(`/justus/${convId}/jurisprudencia-insights`, {
                    headers: { 'Accept': 'application/json' }
                });
                const data = await resp.json();
                if (data.success) {
                    this.jurisResults = data.results || [];
                    this.jurisCount = this.jurisResults.length;
                }
            } catch (e) {
                console.error('Erro ao buscar jurisprudência:', e);
            } finally {
                this.jurisLoading = false;
                this.jurisLoaded = true;
            }
        }
    }
}

/* ========== FUNÇÕES GLOBAIS ========== */
async function createNewConversation(mode, type) {
    try {
        const resp = await fetch('/justus/conversations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ title: 'Nova Análise', type: type, mode: mode }),
        });
        const data = await resp.json();
        if (data.success && data.conversation_id) {
            window.location.href = '/justus/app?c=' + data.conversation_id;
        } else if (data.redirect) {
            window.location.href = data.redirect;
        } else {
            alert(data.error || 'Erro ao criar');
        }
    } catch (e) { alert('Erro: ' + e.message); }
}

function deleteConversation(id) {
    fetch(`/justus/${id}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
    }).then(r => r.json()).then(data => {
        if (data.success) window.location.href = '/justus/app';
        else alert(data.error || 'Erro ao excluir');
    }).catch(e => alert('Erro: ' + e.message));
}

function renderMarkdown(el) {
    if (typeof marked !== 'undefined' && marked.parse) {
        const raw = el.textContent || el.innerText;
        el.innerHTML = marked.parse(raw);
    }
}

async function sendFeedback(convId, msgId, type, btn) {
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

function openNewAnalysisModal() {
    var old = document.getElementById('justus-new-modal');
    if (old) old.remove();

    var overlay = document.createElement('div');
    overlay.id = 'justus-new-modal';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);';
    overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };

    var html = '<div style="background:white;border-radius:1rem;box-shadow:0 25px 50px rgba(0,0,0,0.25);width:100%;max-width:28rem;margin:1rem;overflow:hidden;" onclick="event.stopPropagation()">';
    html += '<div style="padding:1rem 1.5rem;border-bottom:1px solid #f3f4f6;background:linear-gradient(135deg,#1B334A,#385776);">';
    html += '<h3 style="font-size:1rem;font-weight:700;color:white;margin:0;">Nova An\u00e1lise</h3>';
    html += '<p style="font-size:0.7rem;color:rgba(255,255,255,0.6);margin:0.25rem 0 0;">Selecione o modo e tipo de an\u00e1lise</p>';
    html += '</div>';
    html += '<div style="padding:1.5rem;">';

    // Modo
    html += '<div style="margin-bottom:1.25rem;">';
    html += '<label style="display:block;font-size:0.65rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Modo de Opera\u00e7\u00e3o</label>';
    html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;" id="jm-mode-grid">';
    html += '<button type="button" onclick="selectJmMode(this,\u0027consultor\u0027)" class="jm-mode-btn jm-mode-active" data-mode="consultor" style="padding:0.75rem;border-radius:0.75rem;text-align:left;border:2px solid #3b82f6;background:#eff6ff;cursor:pointer;"><div style="font-size:0.875rem;font-weight:700;color:#1d4ed8;">Consultor</div><div style="font-size:0.625rem;margin-top:0.25rem;color:#3b82f6;">An\u00e1lise de casos, pareceres</div></button>';
    html += '<button type="button" onclick="selectJmMode(this,\u0027assessor\u0027)" class="jm-mode-btn" data-mode="assessor" style="padding:0.75rem;border-radius:0.75rem;text-align:left;border:2px solid #e5e7eb;background:#f9fafb;cursor:pointer;"><div style="font-size:0.875rem;font-weight:700;color:#374151;">Assessor</div><div style="font-size:0.625rem;margin-top:0.25rem;color:#9ca3af;">Pe\u00e7as, c\u00e1lculos, execu\u00e7\u00e3o</div></button>';
    html += '</div></div>';

    // Tipo
    html += '<div style="margin-bottom:1.25rem;">';
    html += '<label style="display:block;font-size:0.65rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Tipo de An\u00e1lise</label>';
    html += '<select id="jm-type-select" style="width:100%;border:1px solid #e5e7eb;border-radius:0.75rem;padding:0.625rem 0.75rem;font-size:0.875rem;outline:none;">';
    html += '<option value="analise_estrategica">An\u00e1lise Estrat\u00e9gica</option>';
    html += '<option value="analise_completa">An\u00e1lise Completa</option>';
    html += '<option value="peca">Projeto de Pe\u00e7a</option>';
    html += '</select></div>';

    // Prompt programado
    html += '<div style="margin-bottom:1.25rem;">';
    html += '<label style="display:block;font-size:0.65rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Prompt Programado (opcional)</label>';
    html += '<select id="jm-prompt-select" onchange="applyPromptTemplate()" style="width:100%;border:1px solid #e5e7eb;border-radius:0.75rem;padding:0.625rem 0.75rem;font-size:0.875rem;outline:none;">';
    html += '<option value="">\u2014 Escrever prompt livre \u2014</option>';
    html += '</select></div>';

    // Mensagem inicial
    html += '<div style="margin-bottom:0.5rem;">';
    html += '<label style="display:block;font-size:0.65rem;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:0.5rem;">Mensagem Inicial</label>';
    html += '<textarea id="jm-initial-msg" rows="4" placeholder="Descreva o que deseja analisar..." style="width:100%;border:1px solid #e5e7eb;border-radius:0.75rem;padding:0.625rem 0.75rem;font-size:0.875rem;outline:none;resize:vertical;"></textarea>';
    html += '</div>';

    html += '</div>';

    // Botoes
    html += '<div style="padding:1rem 1.5rem;border-top:1px solid #f3f4f6;display:flex;justify-content:flex-end;gap:0.75rem;background:rgba(249,250,251,0.5);">';
    html += '<button type="button" onclick="document.getElementById(\u0027justus-new-modal\u0027).remove()" style="padding:0.5rem 1rem;font-size:0.875rem;color:#6b7280;background:none;border:none;cursor:pointer;">Cancelar</button>';
    html += '<button type="button" onclick="submitNewAnalysis()" id="jm-submit-btn" style="padding:0.5rem 1.25rem;border-radius:0.75rem;color:white;font-size:0.875rem;font-weight:600;border:none;cursor:pointer;background:linear-gradient(135deg,#1B334A,#385776);">Criar</button>';
    html += '</div></div>';

    overlay.innerHTML = html;
    document.body.appendChild(overlay);
    loadPromptTemplates();
    document.addEventListener('keydown', function esc(e) { if (e.key === 'Escape') { var m = document.getElementById('justus-new-modal'); if (m) m.remove(); document.removeEventListener('keydown', esc); }});
}

var jmSelectedMode = 'consultor';
function selectJmMode(btn, mode) {
    jmSelectedMode = mode;
    var btns = document.querySelectorAll('#jm-mode-grid button');
    btns.forEach(function(b) {
        if (b.dataset.mode === mode) {
            b.style.border = '2px solid ' + (mode === 'consultor' ? '#3b82f6' : '#10b981');
            b.style.background = mode === 'consultor' ? '#eff6ff' : '#ecfdf5';
            b.querySelector('div').style.color = mode === 'consultor' ? '#1d4ed8' : '#047857';
        } else {
            b.style.border = '2px solid #e5e7eb';
            b.style.background = '#f9fafb';
            b.querySelector('div').style.color = '#374151';
        }
    });
}

var jmPromptTemplates = [];

    function loadPromptTemplates() {
        fetch('/justus/prompt-templates')
            .then(r => r.json())
            .then(data => {
                jmPromptTemplates = data;
                var sel = document.getElementById('jm-prompt-select');
                if (!sel) return;
                sel.innerHTML = '<option value="">— Escrever prompt livre —</option>';
                var currentCategory = '';
                data.forEach(function(t) {
                    if (t.category !== currentCategory) {
                        if (currentCategory !== '') sel.innerHTML += '</optgroup>';
                        var catLabel = {execucao:'Execução',analise_pecas:'Análise de Peças',geral:'Geral'}[t.category] || t.category;
                        sel.innerHTML += '<optgroup label="' + catLabel + '">';
                        currentCategory = t.category;
                    }
                    sel.innerHTML += '<option value="' + t.id + '">' + t.label + '</option>';
                });
                if (currentCategory !== '') sel.innerHTML += '</optgroup>';
            })
            .catch(e => console.log('Prompts nao carregados:', e));
    }

    function applyPromptTemplate() {
        var sel = document.getElementById('jm-prompt-select');
        var textarea = document.getElementById('jm-initial-msg');
        var modeButtons = document.querySelectorAll('.jm-mode-btn');
        var typeSelect = document.getElementById('jm-type-select');
        if (!sel || !sel.value) return;
        var tmpl = jmPromptTemplates.find(t => t.id == sel.value);
        if (!tmpl) return;
        textarea.value = tmpl.prompt_text;
        // Setar modo
        if (tmpl.mode) {
            modeButtons.forEach(function(btn) {
                if (btn.dataset.mode === tmpl.mode) {
                    selectJmMode(btn, tmpl.mode);
                }
            });
        }
        // Setar tipo
        if (tmpl.type && typeSelect) {
            typeSelect.value = tmpl.type;
        }
    }

    // Carregar prompts ao abrir modal automaticamente

    async function submitNewAnalysis() {
    var btn = document.getElementById('jm-submit-btn');
    btn.textContent = 'Criando...';
    btn.disabled = true;
    btn.style.opacity = '0.5';
    var type = document.getElementById('jm-type-select').value;
    try {
        var resp = await fetch('/justus/conversations', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
            body: JSON.stringify({ title: 'Nova An\u00e1lise', type: type, mode: jmSelectedMode })
        });
        var data = await resp.json();
        if (data.success && data.conversation_id) {
            window.location.href = '/justus/app?c=' + data.conversation_id;
        } else {
            alert(data.error || 'Erro ao criar');
            btn.textContent = 'Criar';
            btn.disabled = false;
            btn.style.opacity = '1';
        }
    } catch(e) {
        alert('Erro: ' + e.message);
        btn.textContent = 'Criar';
        btn.disabled = false;
        btn.style.opacity = '1';
    }
}


</script>

</body>
</html>
