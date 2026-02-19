@extends('layouts.app')
<style>
/* Template WhatsApp Modal (17/02/2026) */
.template-btn-reabrir {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; background: #f59e0b; color: #fff;
    border: none; border-radius: 6px; font-size: 13px;
    font-weight: 600; cursor: pointer; transition: background 0.2s;
}
.template-btn-reabrir:hover { background: #d97706; }
.template-btn-reabrir.hidden { display: none; }
.template-modal-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.5); z-index: 9999;
    justify-content: center; align-items: center;
}
.template-modal-overlay.active { display: flex; }
.template-modal {
    background: #fff; border-radius: 12px; width: 560px;
    max-width: 95vw; max-height: 80vh; display: flex;
    flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}
.template-modal-header {
    padding: 16px 20px; border-bottom: 1px solid #e5e7eb;
    display: flex; justify-content: space-between; align-items: center;
}
.template-modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #1B334A; }
.template-modal-close { background: none; border: none; font-size: 22px; cursor: pointer; color: #6b7280; }
.template-modal-body { padding: 16px 20px; overflow-y: auto; flex: 1; }
.template-modal-loading { text-align: center; padding: 40px; color: #6b7280; }
.template-list-empty { text-align: center; padding: 30px; color: #9ca3af; }
.template-card {
    border: 2px solid #e5e7eb; border-radius: 8px; padding: 14px;
    margin-bottom: 10px; cursor: pointer; transition: all 0.2s;
}
.template-card:hover { border-color: #385776; background: #f0f5fa; }
.template-card.selected { border-color: #385776; background: #e8f0f8; }
.template-card-name { font-weight: 700; font-size: 14px; color: #1B334A; margin-bottom: 4px; }
.template-card-meta { font-size: 12px; color: #6b7280; margin-bottom: 8px; }
.template-card-meta span {
    display: inline-block; padding: 2px 8px; border-radius: 4px;
    font-weight: 600; font-size: 11px; margin-right: 6px;
}
.template-card-meta .cat-utility { background: #dbeafe; color: #1e40af; }
.template-card-meta .cat-marketing { background: #fef3c7; color: #92400e; }
.template-card-meta .cat-auth { background: #ede9fe; color: #5b21b6; }
.template-card-body {
    font-size: 13px; color: #374151; background: #f9fafb;
    padding: 10px 12px; border-radius: 6px; white-space: pre-line; line-height: 1.5;
}
.template-modal-footer {
    padding: 14px 20px; border-top: 1px solid #e5e7eb;
    display: flex; justify-content: flex-end; gap: 10px;
}
.template-btn-cancel { padding: 8px 18px; background: #e5e7eb; color: #374151; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
.template-btn-send { padding: 8px 18px; background: #385776; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; }
.template-btn-send:hover { background: #1B334A; }
.template-btn-send:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
@section('content')
<link rel="stylesheet" href="{{ asset('css/nexo-atendimento.css') }}">
<div id="nexo-app" class="flex h-[calc(100vh-64px)] bg-[#f0f2f5] overflow-hidden w-full">

    {{-- ═══════════════════════════════════════════════════════ --}}
    {{-- COLUNA 1 — INBOX (360px) --}}
    {{-- ═══════════════════════════════════════════════════════ --}}
    <div id="inbox-panel" class="w-full lg:w-[290px] flex-shrink-0 bg-white border-r border-gray-200/80 flex flex-col">
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
                <button onclick="NexoApp.setFilter('minhas','1')" class="filter-btn text-[11px] px-3 py-1.5 rounded-full" data-filter="minhas">Minhas</button>
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
            <!-- Botão Reabrir Conversa via Template (17/02/2026) -->
            <button id="btnReabrirConversa" class="template-btn-reabrir hidden" onclick="abrirTemplateModal(document.getElementById('chat-contact-phone').textContent.trim())" title="Enviar template para reabrir conversa (janela 24h expirada)">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                Reabrir Conversa
            </button>
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

        {{-- Reply Bar (citação) --}}
        <div id="reply-bar" class="hidden px-4 pt-3 pb-0 bg-white border-t border-gray-200/80">
            <div class="flex items-start gap-2 bg-[#f0f2f5] rounded-xl px-3 py-2 border-l-4 border-[#1e3a5f]">
                <div class="flex-1 min-w-0">
                    <p id="reply-bar-author" class="text-[11px] font-semibold text-[#1e3a5f] truncate"></p>
                    <p id="reply-bar-text" class="text-[12px] text-[#667781] truncate max-h-[36px] overflow-hidden"></p>
                </div>
                <button onclick="NexoApp.cancelReply()" class="flex-shrink-0 text-[#8696a0] hover:text-[#3b4a54] p-0.5 transition-colors" title="Cancelar resposta">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        {{-- Input --}}
        <div id="bot-ativo-banner" class="hidden px-4 py-2.5 bg-amber-50 border-t border-amber-200">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-amber-600 text-sm">🤖</span>
                    <span class="text-[12px] text-amber-800 font-medium">Cliente em atendimento automático (bot)</span>
                </div>
                @if(in_array(Auth::user()->role, ['admin', 'coordenador', 'socio']))
                <button onclick="NexoApp.assumirConversa()" class="text-[11px] px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded-lg font-medium transition-colors shadow-sm">
                    Assumir Conversa
                </button>
                @endif
            </div>
        </div>
        <div id="chat-input-bar" class="hidden px-4 py-3 bg-white border-t-0">
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
    <div id="contexto-panel" class="hidden xl:flex w-[310px] min-w-[280px] flex-shrink flex-col bg-white border-l border-gray-200/80 overflow-hidden">
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

<!-- Modal Templates WhatsApp (17/02/2026) -->
<div id="templateModal" class="template-modal-overlay" onclick="if(event.target===this)fecharTemplateModal()">
    <div class="template-modal">
        <div class="template-modal-header">
            <h3>Enviar Template WhatsApp</h3>
            <button class="template-modal-close" onclick="fecharTemplateModal()">&times;</button>
        </div>
        <div class="template-modal-body" id="templateModalBody">
            <div class="template-modal-loading" id="templateLoading">
                Carregando templates aprovados...
            </div>
            <div id="templateList"></div>
        </div>
        <div class="template-modal-footer">
            <button class="template-btn-cancel" onclick="fecharTemplateModal()">Cancelar</button>
            <button class="template-btn-send" id="templateBtnSend" disabled onclick="enviarTemplateSelecionado()">
                Enviar Template
            </button>
        </div>
    </div>
</div>


<script>
let templatesSP = [];
let tplSelecionado = null;
let tplTelefone = '';

function abrirTemplateModal(telefone) {
    tplTelefone = telefone;
    tplSelecionado = null;
    document.getElementById('templateBtnSend').disabled = true;
    document.getElementById('templateList').innerHTML = '';
    document.getElementById('templateLoading').style.display = 'block';
    document.getElementById('templateModal').classList.add('active');

    fetch('/nexo/templates', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        document.getElementById('templateLoading').style.display = 'none';
        if (data.success && data.templates.length > 0) {
            templatesSP = data.templates;
            renderTemplates(data.templates);
        } else {
            document.getElementById('templateList').innerHTML =
                '<div class="template-list-empty">' +
                '<p><strong>Nenhum template aprovado encontrado.</strong></p>' +
                '<p style="font-size:12px;margin-top:8px">Crie templates na aba Templates do SendPulse e aguarde aprovacao da Meta.</p></div>';
        }
    })
    .catch(function(err) {
        document.getElementById('templateLoading').style.display = 'none';
        document.getElementById('templateList').innerHTML =
            '<div class="template-list-empty" style="color:#dc2626"><p>Erro: ' + err.message + '</p></div>';
    });
}

function renderTemplates(templates) {
    var html = '';
    for (var i = 0; i < templates.length; i++) {
        var tpl = templates[i];
        var catClass = (tpl.category||'').toLowerCase().indexOf('util') > -1 ? 'cat-utility'
            : (tpl.category||'').toLowerCase().indexOf('market') > -1 ? 'cat-marketing' : 'cat-auth';
        var body = (tpl.body || 'Sem conteudo').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var hdr = tpl.header ? '<div style="font-weight:600;margin-bottom:4px;font-size:12px;color:#385776">' + tpl.header.replace(/</g,'&lt;') + '</div>' : '';
        var ftr = tpl.footer ? '<div style="font-style:italic;margin-top:4px;font-size:11px;color:#9ca3af">' + tpl.footer.replace(/</g,'&lt;') + '</div>' : '';

        html += '<div class="template-card" data-idx="' + i + '" onclick="selecionarTpl(' + i + ')">'
            + '<div class="template-card-name">' + (tpl.name||'Sem nome').replace(/</g,'&lt;') + '</div>'
            + '<div class="template-card-meta">'
            + '<span class="' + catClass + '">' + (tpl.category||'N/A') + '</span>'
            + '<span style="background:#f3f4f6;color:#6b7280">' + (tpl.language||'pt_BR') + '</span>'
            + (tpl.has_vars ? '<span style="background:#fef3c7;color:#92400e">Variaveis</span>' : '')
            + '</div>'
            + '<div class="template-card-body">' + hdr + body + ftr + '</div></div>';
    }
    document.getElementById('templateList').innerHTML = html;
}

function selecionarTpl(idx) {
    tplSelecionado = templatesSP[idx];
    document.querySelectorAll('.template-card').forEach(function(el){ el.classList.remove('selected'); });
    document.querySelector('.template-card[data-idx="' + idx + '"]').classList.add('selected');
    document.getElementById('templateBtnSend').disabled = false;
}

function fecharTemplateModal() {
    document.getElementById('templateModal').classList.remove('active');
    tplSelecionado = null;
}

function enviarTemplateSelecionado() {
    if (!tplSelecionado || !tplTelefone) return;
    var btn = document.getElementById('templateBtnSend');
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    fetch('/nexo/templates/enviar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        },
        body: JSON.stringify({
            telefone: tplTelefone,
            template_name: tplSelecionado.name,
            template_data: tplSelecionado.template,
            language: tplSelecionado.language || 'pt_BR'
        })
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Template enviado com sucesso!');
            fecharTemplateModal();
        } else {
            alert('Erro: ' + (data.message || 'Falha ao enviar template'));
        }
    })
    .catch(function(err) {
        alert('Erro de conexao: ' + err.message);
    })
    .finally(function() {
        btn.disabled = false;
        btn.textContent = 'Enviar Template';
    });
}

// Hook: mostrar botão "Reabrir Conversa" quando janela 24h expirou
function atualizarBotaoReabrir(lastClientMsgTimestamp) {
    var btn = document.getElementById('btnReabrirConversa');
    if (!btn) return;
    if (!lastClientMsgTimestamp) {
        btn.classList.remove('hidden');
        return;
    }
    var diff = Date.now() - new Date(lastClientMsgTimestamp).getTime();
    if (diff > 24 * 60 * 60 * 1000) {
        btn.classList.remove('hidden');
    } else {
        btn.classList.add('hidden');
    }
}

</script>

@endsection
