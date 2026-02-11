@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="{{ asset('css/nexo-atendimento.css') }}">
<div id="nexo-app" class="flex h-[calc(100vh-64px)] bg-[#f0f2f5] overflow-hidden">

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- COLUNA 1 — INBOX (360px) --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div id="inbox-panel" class="w-full lg:w-[360px] flex-shrink-0 bg-white border-r border-gray-200/80 flex flex-col">
        {{-- Header Inbox --}}
        <div class="flex-shrink-0 px-4 py-3.5 bg-white border-b border-gray-200">
            <div class="flex items-center justify-between mb-2.5">
                <div class="flex items-center gap-2.5">
                    <svg class="w-5 h-5 text-[#1e3a5f]" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a8 8 0 01-4.243-1.214l-.252-.149-2.868.852.852-2.868-.149-.252A8 8 0 1112 20z"/></svg>
                    <h2 class="text-[15px] font-semibold text-[#1e3a5f] tracking-wide">NEXO</h2>
                </div>
                <span id="inbox-total" class="text-[11px] text-gray-500 font-medium">0 conversas</span>
            </div>
            {{-- Filtros --}}
            <div class="flex gap-1.5 mb-2.5">
                <button onclick="NexoApp.setFilter('status','')" class="filter-btn active text-[11px] px-3 py-1.5 rounded-full" data-filter="all">Todas</button>
                <button onclick="NexoApp.setFilter('status','open')" class="filter-btn text-[11px] px-3 py-1.5 rounded-full" data-filter="open">Abertas</button>
                <button onclick="NexoApp.setFilter('unread','1')" class="filter-btn text-[11px] px-3 py-1.5 rounded-full" data-filter="unread">Não lidas</button>
            </div>
            {{-- Busca --}}
            <div class="relative">
                <svg class="absolute left-3 top-2.5 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="inbox-search" placeholder="Buscar conversa..."
                    class="w-full pl-9 pr-3 py-2 text-[13px] bg-gray-100 border border-gray-200 rounded-xl text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/20 focus:bg-white transition"
                    oninput="NexoApp.filterLocal(this.value)">
            </div>
        </div>
        {{-- Lista de conversas --}}
        <div id="inbox-items" class="flex-1 overflow-y-auto"></div>
        <div id="inbox-loading" class="flex items-center justify-center py-10">
            <div class="w-6 h-6 border-2 border-[#1e3a5f] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div id="inbox-empty" class="hidden flex-col items-center justify-center py-14 text-gray-400">
            <svg class="w-12 h-12 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            <p class="text-sm">Nenhuma conversa encontrada</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- COLUNA 2 — CHAT (flex grow) --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div id="chat-panel" class="hidden lg:flex flex-1 flex-col bg-[#f0f2f5]">
        {{-- Estado vazio --}}
        <div id="chat-empty" class="flex-1 flex flex-col items-center justify-center text-gray-400">
            <div class="w-20 h-20 mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                <svg class="w-10 h-10 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </div>
            <p class="text-sm font-medium text-gray-500">Selecione uma conversa</p>
            <p class="text-xs mt-1.5 text-gray-400">Clique em um contato na lista ao lado</p>
        </div>

        {{-- Header do Chat --}}
        <div id="chat-header" class="hidden items-center justify-between px-4 py-2.5 bg-white border-b border-gray-200/80 shadow-[0_1px_3px_rgba(0,0,0,.04)]">
            <div class="flex items-center gap-3">
                <button onclick="NexoApp.voltarInbox()" class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div id="chat-avatar" class="w-10 h-10 rounded-full bg-gradient-to-br from-[#dfe5e7] to-[#c8d1d6] text-[#54656f] flex items-center justify-center text-sm font-bold shadow-sm"></div>
                <div>
                    <p id="chat-contact-name" class="text-[14px] font-semibold text-[#111b21] leading-tight"></p>
                    <p id="chat-contact-phone" class="text-[12px] text-[#667781] mt-0.5"></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span id="chat-priority-badge" onclick="NexoApp.cyclePriority()" class="text-[10px] px-2.5 py-1 rounded-full font-medium cursor-pointer priority-normal transition-colors" title="Clique para alterar prioridade">⚪ Normal</span>
                <span id="chat-status-badge" class="text-[11px] px-2.5 py-1 rounded-full font-medium"></span>
                <select id="chat-assign-select" onchange="NexoApp.assignResponsavel(this.value)"
                    class="text-[12px] border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/20 transition">
                    <option value="">Sem responsável</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <button id="chat-toggle-status" onclick="NexoApp.toggleStatus()"
                    class="text-[12px] px-3.5 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors text-gray-600 font-medium">
                    Fechar
                </button>
                <button onclick="NexoApp.toggleContexto()" class="xl:hidden p-1.5 rounded-lg hover:bg-gray-100 transition" title="Painel">
                    <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
            </div>
        </div>

        {{-- Mensagens --}}
        <div id="chat-messages" class="hidden flex-1 overflow-y-auto px-5 py-4 space-y-1 nexo-chat-bg"></div>

        {{-- Input --}}
        <div id="chat-input-bar" class="hidden px-4 py-3 bg-white border-t border-gray-200/80">
            <div class="flex items-end gap-3">
                <textarea id="chat-input" rows="1" placeholder="Digite uma mensagem..."
                    class="flex-1 resize-none px-4 py-3 text-[14px] bg-[#f8f9fb] border border-gray-200 rounded-2xl focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/15 focus:border-[#1e3a5f]/30 focus:bg-white max-h-32 leading-relaxed transition-all"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();NexoApp.sendMessage()}"
                    oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,128)+'px'"></textarea>
                <button onclick="NexoApp.sendMessage()"
                    class="flex-shrink-0 w-11 h-11 rounded-full bg-[#1e3a5f] hover:bg-[#162d4a] text-white flex items-center justify-center transition-all shadow-md hover:shadow-lg active:scale-95">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- COLUNA 3 — PAINEL DIREITO (380px, 4 ABAS) --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div id="contexto-panel" class="hidden xl:flex w-[380px] flex-shrink-0 flex-col bg-white border-l border-gray-200/80 overflow-hidden">
        <div class="flex-shrink-0">
            {{-- Header painel --}}
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-[13px] font-semibold text-gray-700 tracking-wide uppercase">Painel</h3>
                <button onclick="NexoApp.toggleContexto()" class="xl:hidden p-1.5 rounded-lg hover:bg-gray-100 transition">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            {{-- 4 Abas --}}
            <div class="flex bg-white px-2">
                <button id="tab-contexto" onclick="switchTab('contexto')" class="nexo-tab active flex-1 px-2 py-3 text-[12px] font-medium text-[#1e3a5f] border-b-2 border-[#1e3a5f] transition">Contexto</button>
                <button id="tab-datajuri" onclick="switchTab('datajuri')" class="nexo-tab flex-1 px-2 py-3 text-[12px] font-medium text-gray-400 border-b-2 border-transparent hover:text-gray-600 transition">DataJuri</button>
                <button id="tab-notes" onclick="switchTab('notes')" class="nexo-tab flex-1 px-2 py-3 text-[12px] font-medium text-gray-400 border-b-2 border-transparent hover:text-gray-600 transition">Notas</button>
                <button id="tab-flows" onclick="switchTab('flows')" class="nexo-tab flex-1 px-2 py-3 text-[12px] font-medium text-gray-400 border-b-2 border-transparent hover:text-gray-600 transition">Flows</button>
            </div>
        </div>

        {{-- Painel Contexto 360 --}}
        <div id="contexto360-panel" class="flex-1 overflow-y-auto">
            <div id="contexto-empty" class="flex-1 flex items-center justify-center text-gray-400 py-14">
                <p class="text-[13px]">Selecione uma conversa</p>
            </div>
            <div id="contexto-loading" class="hidden flex items-center justify-center py-14">
                <div class="w-5 h-5 border-2 border-[#1e3a5f] border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div id="contexto-data" class="hidden p-4 space-y-3"></div>
            <div id="contexto-link-actions" class="hidden p-4 border-t border-gray-100">
                <p class="text-[11px] text-gray-500 mb-2.5 font-semibold uppercase tracking-wide">Vincular manualmente</p>
                <div class="space-y-2.5">
                    <div class="flex gap-1.5">
                        <input type="number" id="link-lead-id" placeholder="ID do Lead" class="flex-1 text-[12px] border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/15 transition">
                        <button onclick="NexoApp.linkLead(document.getElementById('link-lead-id').value)" class="text-[12px] px-3 py-2 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 border border-blue-200/60 font-medium transition">Lead</button>
                    </div>
                    <div class="flex gap-1.5">
                        <input type="number" id="link-cliente-id" placeholder="ID do Cliente" class="flex-1 text-[12px] border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/15 transition">
                        <button onclick="NexoApp.linkCliente(document.getElementById('link-cliente-id').value)" class="text-[12px] px-3 py-2 bg-purple-50 text-purple-700 rounded-lg hover:bg-purple-100 border border-purple-200/60 font-medium transition">Cliente</button>
                    </div>
                </div>
                {{-- Botões de desvinculação --}}
                <div id="unlink-actions" class="hidden mt-3 pt-3 border-t border-gray-100">
                    <p class="text-[11px] text-gray-400 mb-2 font-medium">Remover vinculação:</p>
                    <div class="flex gap-2">
                        <button id="btn-unlink-lead" onclick="NexoApp.unlinkLead()" class="hidden text-[11px] px-2.5 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 border border-red-200/60 transition-colors font-medium">✕ Desvincular Lead</button>
                        <button id="btn-unlink-cliente" onclick="NexoApp.unlinkCliente()" class="hidden text-[11px] px-2.5 py-1.5 bg-red-50 text-red-600 rounded-lg hover:bg-red-100 border border-red-200/60 transition-colors font-medium">✕ Desvincular Cliente</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Painel DataJuri --}}
        @include('nexo.atendimento.partials._contexto_datajuri')

        {{-- Painel Notas --}}
        <div id="notes-panel" class="hidden flex-col flex-1 overflow-hidden">
            <div class="p-4 border-b border-gray-100">
                <textarea id="note-input" rows="2" placeholder="Escrever nota interna..."
                    class="w-full text-[13px] border border-gray-200 rounded-xl px-4 py-2.5 focus:outline-none focus:ring-2 focus:ring-[#1e3a5f]/15 resize-none leading-relaxed transition"></textarea>
                <button onclick="NexoApp.saveNote()"
                    class="w-full mt-2.5 py-2.5 text-[13px] font-semibold text-white bg-[#1e3a5f] rounded-xl hover:bg-[#162d4a] transition-colors shadow-sm">
                    Salvar Nota
                </button>
            </div>
            <div id="notes-list" class="flex-1 overflow-y-auto p-4 space-y-2">
                <p class="text-[12px] text-gray-400 text-center py-6">Selecione uma conversa</p>
            </div>
        </div>

        {{-- Painel Flows --}}
        <div id="flows-panel" class="hidden flex-col flex-1 overflow-hidden">
            <div id="flows-loading" class="hidden flex items-center justify-center py-10">
                <div class="w-5 h-5 border-2 border-[#1e3a5f] border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div id="flows-list" class="hidden flex-1 overflow-y-auto p-4 space-y-2.5"></div>
            <div id="flows-empty" class="hidden flex-1 flex items-center justify-center text-gray-400">
                <p class="text-[12px]">Nenhum flow disponível</p>
            </div>
        </div>
    </div>
</div>

{{-- ═══ JAVASCRIPT ═══ --}}
@include('nexo.atendimento.partials._nexo_scripts')
@endsection
