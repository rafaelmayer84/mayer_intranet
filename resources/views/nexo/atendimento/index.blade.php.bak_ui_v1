@extends('layouts.app')
@section('content')
<div id="nexo-app" class="flex h-[calc(100vh-64px)] bg-gray-100 overflow-hidden">

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- COLUNA 1 — INBOX --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div id="inbox-panel" class="w-full lg:w-[340px] flex-shrink-0 bg-white border-r border-gray-200 flex flex-col">
        <div class="px-4 py-3 bg-[#1e3a5f] text-white">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a8 8 0 01-4.243-1.214l-.252-.149-2.868.852.852-2.868-.149-.252A8 8 0 1112 20z"/></svg>
                    <h2 class="text-base font-semibold">NEXO</h2>
                </div>
                <span id="inbox-total" class="text-xs opacity-80">0 conversas</span>
            </div>
            <div class="flex gap-1.5 mb-2">
                <button onclick="NexoApp.setFilter('status','')" class="filter-btn active text-xs px-2.5 py-1 rounded-full" data-filter="all">Todas</button>
                <button onclick="NexoApp.setFilter('status','open')" class="filter-btn text-xs px-2.5 py-1 rounded-full" data-filter="open">Abertas</button>
                <button onclick="NexoApp.setFilter('unread','1')" class="filter-btn text-xs px-2.5 py-1 rounded-full" data-filter="unread">Não lidas</button>
            </div>
            <div class="relative">
                <svg class="absolute left-2.5 top-2 w-4 h-4 text-white/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input type="text" id="inbox-search" placeholder="Buscar conversa..."
                    class="w-full pl-8 pr-3 py-1.5 text-sm bg-white/10 border border-white/20 rounded-lg text-white placeholder-white/50 focus:outline-none focus:ring-1 focus:ring-white/40"
                    oninput="NexoApp.filterLocal(this.value)">
            </div>
        </div>
        <div id="inbox-items" class="flex-1 overflow-y-auto"></div>
        <div id="inbox-loading" class="flex items-center justify-center py-8">
            <div class="w-6 h-6 border-2 border-[#1e3a5f] border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div id="inbox-empty" class="hidden flex-col items-center justify-center py-12 text-gray-400">
            <svg class="w-12 h-12 mb-2 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
            <p class="text-sm">Nenhuma conversa encontrada</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- COLUNA 2 — CHAT --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div id="chat-panel" class="hidden lg:flex flex-1 flex-col bg-gray-100">
        <div id="chat-empty" class="flex-1 flex flex-col items-center justify-center text-gray-400">
            <svg class="w-16 h-16 mb-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            <p class="text-sm font-medium">Selecione uma conversa</p>
            <p class="text-xs mt-1">Clique em um contato na lista ao lado</p>
        </div>

        {{-- Header do Chat --}}
        <div id="chat-header" class="hidden items-center justify-between px-4 py-2.5 bg-[#f0f2f5] border-b border-gray-200">
            <div class="flex items-center gap-3">
                <button onclick="NexoApp.voltarInbox()" class="lg:hidden p-1 rounded hover:bg-gray-200">
                    <svg class="w-5 h-5 text-[#54656f]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div id="chat-avatar" class="w-10 h-10 rounded-full bg-[#dfe5e7] text-[#54656f] flex items-center justify-center text-sm font-semibold"></div>
                <div>
                    <p id="chat-contact-name" class="text-sm font-semibold text-[#111b21]"></p>
                    <p id="chat-contact-phone" class="text-xs text-[#667781]"></p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                {{-- Badge de Prioridade (clicável) --}}
                <span id="chat-priority-badge" onclick="NexoApp.cyclePriority()" class="text-[10px] px-2 py-0.5 rounded-full font-medium cursor-pointer priority-normal" title="Clique para alterar prioridade">⚪ Normal</span>
                <span id="chat-status-badge" class="text-xs px-2 py-0.5 rounded-full"></span>
                <select id="chat-assign-select" onchange="NexoApp.assignResponsavel(this.value)"
                    class="text-xs border border-gray-300 rounded px-2 py-1 bg-white focus:outline-none focus:ring-1 focus:ring-[#1e3a5f]">
                    <option value="">Sem responsável</option>
                    @foreach($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                <button id="chat-toggle-status" onclick="NexoApp.toggleStatus()"
                    class="text-xs px-3 py-1 rounded border border-gray-300 hover:bg-gray-100 transition-colors text-[#54656f]">
                    Fechar
                </button>
                <button onclick="NexoApp.toggleContexto()" class="xl:hidden p-1.5 rounded hover:bg-gray-200" title="Painel">
                    <svg class="w-5 h-5 text-[#54656f]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </button>
            </div>
        </div>

        {{-- Mensagens --}}
        <div id="chat-messages" class="hidden flex-1 overflow-y-auto px-4 py-3 space-y-1" style="background-color:#efeae2; background-image:url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAoAAAAKCAYAAACNMs+9AAAAFklEQVQYV2P8////fwY8gHHUCvphBQBf9AoLBkWKLAAAAA=='); background-repeat:repeat;"></div>

        {{-- Input --}}
        <div id="chat-input-bar" class="hidden px-4 py-3 bg-[#f0f2f5] border-t border-gray-200">
            <div class="flex items-end gap-2">
                <textarea id="chat-input" rows="1" placeholder="Digite uma mensagem..."
                    class="flex-1 resize-none px-4 py-2.5 text-sm bg-white border border-gray-200 rounded-2xl focus:outline-none focus:ring-1 focus:ring-[#1e3a5f] focus:border-[#1e3a5f] max-h-32"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();NexoApp.sendMessage()}"
                    oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,128)+'px'"></textarea>
                <button onclick="NexoApp.sendMessage()"
                    class="flex-shrink-0 w-10 h-10 rounded-full bg-[#1e3a5f] hover:bg-[#162d4a] text-white flex items-center justify-center transition-colors shadow-sm">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </div>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- COLUNA 3 — PAINEL DIREITO (4 ABAS) --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div id="contexto-panel" class="hidden xl:flex w-[320px] flex-shrink-0 flex-col bg-white border-l border-gray-200 overflow-hidden">
        <div class="flex-shrink-0">
            <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-800">Painel</h3>
                <button onclick="NexoApp.toggleContexto()" class="xl:hidden p-1 rounded hover:bg-gray-100">
                    <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            {{-- 4 Abas --}}
            <div class="flex border-b border-gray-200 bg-white">
                <button id="tab-contexto" onclick="switchTab('contexto')" class="flex-1 px-2 py-2.5 text-xs font-medium text-[#1e3a5f] border-b-2 border-[#1e3a5f] transition">Contexto</button>
                <button id="tab-datajuri" onclick="switchTab('datajuri')" class="flex-1 px-2 py-2.5 text-xs font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition">DataJuri</button>
                <button id="tab-notes" onclick="switchTab('notes')" class="flex-1 px-2 py-2.5 text-xs font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition">Notas</button>
                <button id="tab-flows" onclick="switchTab('flows')" class="flex-1 px-2 py-2.5 text-xs font-medium text-gray-500 border-b-2 border-transparent hover:text-gray-700 transition">Flows</button>
            </div>
        </div>

        {{-- Painel Contexto 360 --}}
        <div id="contexto360-panel" class="flex-1 overflow-y-auto">
            <div id="contexto-empty" class="flex-1 flex items-center justify-center text-gray-400 py-12">
                <p class="text-sm">Selecione uma conversa</p>
            </div>
            <div id="contexto-loading" class="hidden flex items-center justify-center py-12">
                <div class="w-5 h-5 border-2 border-[#1e3a5f] border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div id="contexto-data" class="hidden p-4 space-y-3"></div>
            <div id="contexto-link-actions" class="hidden p-4 border-t border-gray-200">
                <p class="text-xs text-gray-500 mb-2 font-medium">Vincular manualmente:</p>
                <div class="space-y-2">
                    <div class="flex gap-1">
                        <input type="number" id="link-lead-id" placeholder="ID do Lead" class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#1e3a5f]">
                        <button onclick="NexoApp.linkLead(document.getElementById('link-lead-id').value)" class="text-xs px-2 py-1.5 bg-blue-50 text-blue-700 rounded hover:bg-blue-100 border border-blue-200">Lead</button>
                    </div>
                    <div class="flex gap-1">
                        <input type="number" id="link-cliente-id" placeholder="ID do Cliente" class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#1e3a5f]">
                        <button onclick="NexoApp.linkCliente(document.getElementById('link-cliente-id').value)" class="text-xs px-2 py-1.5 bg-purple-50 text-purple-700 rounded hover:bg-purple-100 border border-purple-200">Cliente</button>
                    </div>
                </div>
                {{-- Botões de desvinculação --}}
                <div id="unlink-actions" class="hidden mt-3 pt-3 border-t border-gray-100">
                    <p class="text-xs text-gray-400 mb-2 font-medium">Remover vinculação:</p>
                    <div class="flex gap-2">
                        <button id="btn-unlink-lead" onclick="NexoApp.unlinkLead()" class="hidden text-[11px] px-2 py-1 bg-red-50 text-red-600 rounded hover:bg-red-100 border border-red-200 transition-colors">✕ Desvincular Lead</button>
                        <button id="btn-unlink-cliente" onclick="NexoApp.unlinkCliente()" class="hidden text-[11px] px-2 py-1 bg-red-50 text-red-600 rounded hover:bg-red-100 border border-red-200 transition-colors">✕ Desvincular Cliente</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Painel DataJuri --}}
        @include('nexo.atendimento.partials._contexto_datajuri')

        {{-- Painel Notas --}}
        <div id="notes-panel" class="hidden flex-col flex-1 overflow-hidden">
            <div class="p-3 border-b border-gray-200">
                <textarea id="note-input" rows="2" placeholder="Escrever nota interna..."
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-[#1e3a5f] resize-none"></textarea>
                <button onclick="NexoApp.saveNote()"
                    class="w-full mt-2 py-2 text-sm font-medium text-white bg-[#1e3a5f] rounded-lg hover:bg-[#162d4a] transition-colors">
                    Salvar Nota
                </button>
            </div>
            <div id="notes-list" class="flex-1 overflow-y-auto p-3 space-y-2">
                <p class="text-xs text-gray-400 text-center py-4">Selecione uma conversa</p>
            </div>
        </div>

        {{-- Painel Flows --}}
        <div id="flows-panel" class="hidden flex-col flex-1 overflow-hidden">
            <div id="flows-loading" class="hidden flex items-center justify-center py-8">
                <div class="w-5 h-5 border-2 border-[#1e3a5f] border-t-transparent rounded-full animate-spin"></div>
            </div>
            <div id="flows-list" class="hidden flex-1 overflow-y-auto p-3 space-y-2"></div>
            <div id="flows-empty" class="hidden flex-1 flex items-center justify-center text-gray-400">
                <p class="text-xs">Nenhum flow disponível</p>
            </div>
        </div>
    </div>
</div>

{{-- ═══ ESTILOS ═══ --}}
<style>
    .filter-btn { border: 1px solid rgba(255,255,255,.3); color: rgba(255,255,255,.8); background: transparent; }
    .filter-btn:hover { background: rgba(255,255,255,.1); }
    .filter-btn.active { background: white; color: #1e3a5f; border-color: white; font-weight: 600; }
    .inbox-item { transition: background-color .15s; }
    .inbox-item:hover { background-color: #f3f4f6; }
    .inbox-item.active { background-color: #e8f0fe; }
    .msg-bubble-in { background: white; border-radius: 0 8px 8px 8px; box-shadow: 0 1px 1px rgba(0,0,0,.08); }
    .msg-bubble-out { background: #d9fdd3; border-radius: 8px 0 8px 8px; box-shadow: 0 1px 1px rgba(0,0,0,.08); }
    .msg-media-img { max-width: 280px; max-height: 300px; border-radius: 6px; cursor: pointer; display: block; margin-bottom: 4px; }
    .msg-media-audio { width: 100%; max-width: 260px; height: 36px; margin-bottom: 4px; }
    .msg-media-doc { display: flex; align-items: center; gap: 8px; padding: 8px; background: rgba(0,0,0,.04); border-radius: 6px; margin-bottom: 4px; text-decoration: none; }
    .msg-media-doc:hover { background: rgba(0,0,0,.08); }
    .ctx-section { border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; }
    .ctx-section-header { background: #f9fafb; padding: 8px 12px; cursor: pointer; display: flex; align-items: center; justify-content: space-between; }
    .ctx-section-header:hover { background: #f3f4f6; }
    .ctx-section-body { padding: 10px 12px; }
    .ctx-row { display: flex; justify-content: space-between; padding: 3px 0; font-size: 12px; }
    .ctx-label { color: #6b7280; }
    .ctx-value { color: #1f2937; font-weight: 500; text-align: right; max-width: 60%; }
    .priority-normal { background: #f3f4f6; color: #6b7280; }
    .priority-alta { background: #fef3c7; color: #92400e; }
    .priority-urgente { background: #fed7aa; color: #9a3412; }
    .priority-critica { background: #fecaca; color: #991b1b; }
    #chat-messages::-webkit-scrollbar { width: 6px; }
    #chat-messages::-webkit-scrollbar-thumb { background: rgba(0,0,0,.15); border-radius: 3px; }
    #inbox-items::-webkit-scrollbar, #contexto360-panel::-webkit-scrollbar, #datajuri-panel::-webkit-scrollbar, #notes-list::-webkit-scrollbar, #flows-list::-webkit-scrollbar { width: 4px; }
    #inbox-items::-webkit-scrollbar-thumb, #contexto360-panel::-webkit-scrollbar-thumb, #datajuri-panel::-webkit-scrollbar-thumb, #notes-list::-webkit-scrollbar-thumb, #flows-list::-webkit-scrollbar-thumb { background: rgba(0,0,0,.1); border-radius: 2px; }
</style>

{{-- ═══ JAVASCRIPT ═══ --}}
@include('nexo.atendimento.partials._nexo_scripts')
@endsection
