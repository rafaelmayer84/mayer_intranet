

<div id="datajuri-panel" class="hidden h-full overflow-y-auto">
    
    <div id="dj-loading" class="flex items-center justify-center py-12">
        <svg class="animate-spin h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span class="ml-2 text-sm text-gray-500">Carregando DataJuri...</span>
    </div>

    
    <div id="dj-sem-cliente" class="hidden p-4">
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">
            <svg class="mx-auto h-8 w-8 text-yellow-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-sm font-medium text-yellow-800 mb-3">Sem cliente vinculado</p>
            <button onclick="NexoDataJuri.autoVincular()" class="w-full mb-2 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition">
                Auto-vincular por telefone
            </button>
            <div class="relative mt-3">
                <input type="text" id="dj-busca-cliente" placeholder="Buscar por nome, CPF/CNPJ ou telefone..."
                    class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                    onkeyup="NexoDataJuri.debounceBusca(this.value)">
            </div>
            <div id="dj-busca-resultados" class="hidden mt-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg"></div>
        </div>
    </div>

    
    <div id="dj-conteudo" class="hidden">
        
        <div class="p-3 border-b border-gray-200">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</h4>
                <a id="dj-link-datajuri" href="#" target="_blank" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                    Abrir no DataJuri &rarr;
                </a>
            </div>
            <p id="dj-cliente-nome" class="text-sm font-semibold text-gray-900"></p>
            <div class="flex items-center gap-2 mt-1">
                <span id="dj-cliente-tipo" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700"></span>
                <span id="dj-cliente-doc" class="text-xs text-gray-500"></span>
            </div>
            <p id="dj-cliente-tel" class="text-xs text-gray-500 mt-1"></p>
        </div>

        
        <div id="dj-processo-vinculado" class="hidden p-3 border-b border-gray-200 bg-blue-50">
            <div class="flex items-center justify-between mb-2">
                <h4 class="text-xs font-semibold text-blue-700 uppercase tracking-wider">Processo desta conversa</h4>
                <button onclick="NexoDataJuri.unlinkProcesso()" class="text-xs text-red-500 hover:text-red-700" title="Desvincular">
                    &#10005;
                </button>
            </div>
            <div class="flex items-center gap-2">
                <p id="dj-pv-numero" class="text-sm font-semibold text-blue-900 cursor-pointer hover:underline" onclick="NexoDataJuri.abrirPopupProcessoVinculado()"></p>
                <a id="dj-pv-link-dj" href="#" target="_blank" class="text-blue-600 hover:text-blue-800" title="Abrir no DataJuri">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                </a>
            </div>
            <p id="dj-pv-descricao" class="text-xs text-blue-700 mt-1"></p>
        </div>

        
        <div class="p-3 border-b border-gray-200">
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wider block mb-1">Vincular processo</label>
            <select id="dj-select-processo" onchange="NexoDataJuri.selecionarProcesso(this.value)"
                class="w-full text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Selecione um processo...</option>
            </select>
        </div>

        
        <div class="px-3 pt-2 flex items-center justify-between">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Próximos Prazos</h4>
            <label class="flex items-center gap-1 cursor-pointer" id="dj-toggle-prazos-wrapper" style="display:none;">
                <input type="checkbox" id="dj-toggle-prazos" onchange="NexoDataJuri.togglePrazos()" class="rounded text-blue-600 h-3 w-3">
                <span class="text-xs text-gray-500">Ver todos do cliente</span>
            </label>
        </div>

        
        <div id="dj-prazos-lista" class="p-3 space-y-2"></div>

        
        <div class="p-3 border-t border-gray-200">
            <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Processos Ativos</h4>
            <div id="dj-processos-lista" class="space-y-2"></div>
        </div>
    </div>

    
    <div id="dj-erro" class="hidden p-4">
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">
            <p class="text-sm text-red-700">Erro ao carregar dados do DataJuri.</p>
            <button onclick="NexoDataJuri.carregar()" class="mt-2 px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700">
                Tentar novamente
            </button>
        </div>
    </div>
</div>


<div id="dj-modal-processo" class="hidden fixed inset-0 z-[9999] flex items-center justify-center p-4" style="background:rgba(0,0,0,.5)">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col" onclick="event.stopPropagation()">
        
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 bg-gray-50 rounded-t-xl">
            <div class="flex items-center gap-3 min-w-0">
                <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-lg flex items-center justify-center">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div class="min-w-0">
                    <h3 id="dj-modal-titulo" class="text-sm font-bold text-gray-900 truncate">Detalhes do Processo</h3>
                    <p id="dj-modal-subtitulo" class="text-xs text-gray-500 truncate"></p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a id="dj-modal-link-dj" href="#" target="_blank" class="hidden px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition flex items-center gap-1.5">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    DataJuri
                </a>
                <button onclick="NexoDataJuri.fecharPopup()" class="p-1.5 rounded-lg hover:bg-gray-200 transition">
                    <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        
        <div id="dj-modal-body" class="flex-1 overflow-y-auto px-5 py-4">
            <div class="flex items-center justify-center py-12">
                <svg class="animate-spin h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <span class="ml-2 text-sm text-gray-500">Carregando...</span>
            </div>
        </div>
    </div>
</div>


<script>
window.NexoDataJuri = (function() {
    let _conversationId = null;
    let _data = null;
    let _buscaTimer = null;
    let _filtrandoPorProcesso = false;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const DJ_BASE_URL = 'https://dj21.datajuri.com.br/app/#/lista/Processo/';

    function baseUrl(path) {
        return `/nexo/atendimento/conversas/${_conversationId}/${path}`;
    }

    function show(id) { document.getElementById(id)?.classList.remove('hidden'); }
    function hide(id) { document.getElementById(id)?.classList.add('hidden'); }

    function setConversation(id) {
        _conversationId = id;
        _data = null;
        _filtrandoPorProcesso = false;
    }

    async function carregar() {
        if (!_conversationId) return;

        show('dj-loading');
        hide('dj-sem-cliente');
        hide('dj-conteudo');
        hide('dj-erro');

        try {
            const resp = await fetch(baseUrl('contexto-datajuri'), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

            _data = await resp.json();
            hide('dj-loading');

            if (!_data.has_cliente) {
                show('dj-sem-cliente');
                return;
            }

            renderConteudo();
            show('dj-conteudo');
        } catch (e) {
            console.error('NexoDataJuri erro:', e);
            hide('dj-loading');
            show('dj-erro');
        }
    }

    function renderConteudo() {
        if (!_data || !_data.cliente) return;

        const c = _data.cliente;

        setText('dj-cliente-nome', c.nome || '—');
        setText('dj-cliente-tipo', c.tipo || '—');
        setText('dj-cliente-doc', c.cpf_cnpj || '');
        setText('dj-cliente-tel', c.telefone ? `Tel: ${c.telefone}` : '');

        // Link DataJuri — cliente
        const linkEl = document.getElementById('dj-link-datajuri');
        if (linkEl && c.datajuri_id) {
            linkEl.href = `https://dj21.datajuri.com.br/app/#/lista/Pessoa/${c.datajuri_id}`;
            linkEl.style.display = '';
        } else if (linkEl) {
            linkEl.style.display = 'none';
        }

        // Processo vinculado
        if (_data.processo_vinculado) {
            const pv = _data.processo_vinculado;
            const pvNumEl = document.getElementById('dj-pv-numero');
            pvNumEl.textContent = pv.numero || `Pasta ${pv.pasta}`;
            pvNumEl.setAttribute('data-processo-id', pv.id || '');

            setText('dj-pv-descricao', pv.descricao || '');

            // Link DataJuri do processo vinculado
            const pvLinkDj = document.getElementById('dj-pv-link-dj');
            if (pvLinkDj && pv.datajuri_id) {
                pvLinkDj.href = DJ_BASE_URL + pv.datajuri_id;
                pvLinkDj.style.display = '';
            } else if (pvLinkDj) {
                pvLinkDj.style.display = 'none';
            }

            show('dj-processo-vinculado');
            _filtrandoPorProcesso = true;
            document.getElementById('dj-toggle-prazos-wrapper').style.display = '';
            document.getElementById('dj-toggle-prazos').checked = false;
        } else {
            hide('dj-processo-vinculado');
            _filtrandoPorProcesso = false;
            document.getElementById('dj-toggle-prazos-wrapper').style.display = 'none';
        }

        renderSelectProcessos();
        renderPrazos(_data.prazos);
        renderProcessos(_data.processos);
    }

    function renderSelectProcessos() {
        const sel = document.getElementById('dj-select-processo');
        if (!sel || !_data) return;

        sel.innerHTML = '<option value="">Selecione um processo...</option>';
        (_data.processos || []).forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = `${p.numero || 'Pasta ' + p.pasta} — ${p.descricao || p.status}`;
            if (_data.linked_processo_id && p.id == _data.linked_processo_id) {
                opt.selected = true;
            }
            sel.appendChild(opt);
        });
    }

    function renderPrazos(prazos) {
        const container = document.getElementById('dj-prazos-lista');
        if (!container) return;

        if (!prazos || prazos.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-400 italic">Nenhum prazo futuro encontrado.</p>';
            return;
        }

        container.innerHTML = prazos.map(p => {
            const urgClass = urgenciaClass(p.urgencia);
            const dias = p.dias_restantes;
            const diasLabel = dias === 0 ? 'HOJE' : dias === 1 ? 'D-1' : `D-${dias}`;

            return `<div class="flex items-start gap-2 p-2 rounded-lg ${urgClass.bg}">
                <span class="inline-flex items-center justify-center min-w-[40px] px-1.5 py-0.5 rounded text-xs font-bold ${urgClass.badge}">
                    ${diasLabel}
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-medium text-gray-900 truncate">${escHtml(p.atividade || '—')}</p>
                    <p class="text-xs text-gray-500">${formatDate(p.data)} · ${escHtml(p.processo_numero || '')}</p>
                </div>
            </div>`;
        }).join('');
    }

    function renderProcessos(processos) {
        const container = document.getElementById('dj-processos-lista');
        if (!container) return;

        if (!processos || processos.length === 0) {
            container.innerHTML = '<p class="text-xs text-gray-400 italic">Nenhum processo ativo.</p>';
            return;
        }

        container.innerHTML = processos.map(p => {
            const prazoHtml = p.proximo_prazo
                ? `<span class="text-xs ${urgenciaClass(urgenciaFromDias(p.proximo_prazo.dias_restantes)).text}">Prazo: ${formatDate(p.proximo_prazo.data)} (D-${p.proximo_prazo.dias_restantes})</span>`
                : '<span class="text-xs text-gray-400">Sem prazo</span>';

            const andamentoHtml = p.ultimo_andamento
                ? `<span class="text-xs text-gray-500">Últ: ${formatDate(p.ultimo_andamento.data)} — ${escHtml(p.ultimo_andamento.resumo)}</span>`
                : '';

            const faseHtml = p.fase_atual
                ? `<span class="text-xs text-indigo-600">${escHtml(p.fase_atual.tipo_fase || '')} ${p.fase_atual.instancia ? '(' + escHtml(p.fase_atual.instancia) + ')' : ''}</span>`
                : '';

            const isVinculado = _data.linked_processo_id && p.id == _data.linked_processo_id;
            const djId = p.datajuri_id;

            return `<div class="border ${isVinculado ? 'border-blue-400 bg-blue-50' : 'border-gray-200'} rounded-lg p-2.5">
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5">
                            <p class="text-sm font-medium text-blue-700 truncate cursor-pointer hover:underline" onclick="NexoDataJuri.abrirPopup(${p.id})">${escHtml(p.numero || 'Pasta ' + p.pasta)}</p>
                            ${djId ? `<a href="${DJ_BASE_URL}${djId}" target="_blank" class="flex-shrink-0 text-gray-400 hover:text-blue-600 transition" title="Abrir no DataJuri" onclick="event.stopPropagation()"><svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg></a>` : ''}
                        </div>
                        <p class="text-xs text-gray-600 truncate">${escHtml(p.descricao || '')}</p>
                    </div>
                    <button onclick="NexoDataJuri.selecionarProcesso(${p.id})" title="Definir como processo desta conversa"
                        class="ml-2 flex-shrink-0 p-1 rounded ${isVinculado ? 'text-blue-600' : 'text-gray-400 hover:text-blue-600'} transition">
                        <svg class="h-4 w-4" fill="${isVinculado ? 'currentColor' : 'none'}" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                        </svg>
                    </button>
                </div>
                <div class="mt-1.5 flex flex-col gap-0.5">
                    ${faseHtml}
                    ${prazoHtml}
                    ${andamentoHtml}
                </div>
            </div>`;
        }).join('');
    }

    // ═══════════════════════════════════════════════════════════════
    // POPUP — Detalhes do Processo
    // ═══════════════════════════════════════════════════════════════

    async function abrirPopup(processoId) {
        if (!processoId) return;

        const modal = document.getElementById('dj-modal-processo');
        const body = document.getElementById('dj-modal-body');
        modal.classList.remove('hidden');

        body.innerHTML = '<div class="flex items-center justify-center py-12"><svg class="animate-spin h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span class="ml-2 text-sm text-gray-500">Carregando...</span></div>';

        try {
            const resp = await fetch(`/nexo/atendimento/processo/${processoId}/detalhe`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);

            const p = await resp.json();
            renderPopup(p);
        } catch (e) {
            console.error('Popup processo erro:', e);
            body.innerHTML = '<div class="text-center py-8"><p class="text-red-600 text-sm">Erro ao carregar detalhes.</p><button onclick="NexoDataJuri.fecharPopup()" class="mt-2 text-sm text-blue-600 hover:underline">Fechar</button></div>';
        }
    }

    function abrirPopupProcessoVinculado() {
        const el = document.getElementById('dj-pv-numero');
        const pid = el?.getAttribute('data-processo-id');
        if (pid) abrirPopup(parseInt(pid));
    }

    function fecharPopup() {
        document.getElementById('dj-modal-processo')?.classList.add('hidden');
    }

    function renderPopup(p) {
        // Header
        setText('dj-modal-titulo', p.numero || `Pasta ${p.pasta || '—'}`);
        setText('dj-modal-subtitulo', p.titulo || p.tipo_acao || '');

        // Link DataJuri no header do modal
        const linkDj = document.getElementById('dj-modal-link-dj');
        if (linkDj && p.datajuri_url) {
            linkDj.href = p.datajuri_url;
            linkDj.classList.remove('hidden');
        } else if (linkDj) {
            linkDj.classList.add('hidden');
        }

        // Body
        const body = document.getElementById('dj-modal-body');
        let html = '';

        // ── Identificação ──
        html += section('Identificação', [
            row('Pasta', p.pasta),
            row('Número', p.numero),
            row('Título', p.titulo),
            row('Tipo de Ação', p.tipo_acao),
            row('Tipo Processo', p.tipo_processo),
            row('Natureza', p.natureza),
            row('Área', p.area),
            row('Status', p.status, statusBadge(p.status)),
            row('Assunto', p.assunto),
        ]);

        // ── Partes ──
        html += section('Partes', [
            row('Cliente', p.cliente_nome),
            row('Doc. Cliente', p.cliente_documento),
            row('Posição Cliente', p.posicao_cliente),
            row('Parte Adversa', p.adverso_nome),
            row('Posição Adverso', p.posicao_adverso),
            row('Adv. do Cliente', p.advogado_cliente_nome),
        ]);

        // ── Fase Atual ──
        html += section('Fase Atual', [
            row('Fase', p.fase_atual_numero),
            row('Vara', p.fase_atual_vara),
            row('Instância', p.fase_atual_instancia),
            row('Órgão', p.fase_atual_orgao),
        ]);

        // ── Valores ──
        html += section('Valores', [
            row('Valor da Causa', money(p.valor_causa)),
            row('Valor Provisionado', money(p.valor_provisionado)),
            row('Valor Sentença', money(p.valor_sentenca)),
        ]);

        // ── Datas ──
        html += section('Datas', [
            row('Abertura', formatDate(p.data_abertura)),
            row('Distribuição', formatDate(p.data_distribuicao)),
            row('Conclusão', formatDate(p.data_conclusao)),
            row('Encerramento', formatDate(p.data_encerramento)),
            row('Cadastro DJ', formatDate(p.data_cadastro_dj)),
        ]);

        // ── Responsável / Prognóstico ──
        html += section('Responsável e Prognóstico', [
            row('Advogado Resp.', p.advogado_responsavel),
            row('Proprietário', p.proprietario_nome),
            row('Possibilidade', p.possibilidade, possibilidadeBadge(p.possibilidade)),
            row('Ganho de Causa', p.ganho_causa ? 'Sim' : 'Não'),
            row('Tipo Encerramento', p.tipo_encerramento),
        ]);

        // ── Observação ──
        if (p.observacao) {
            html += section('Observações', [
                `<p class="text-xs text-gray-700 leading-relaxed whitespace-pre-wrap">${escHtml(p.observacao)}</p>`
            ], false);
        }

        // ── Histórico de Fases ──
        if (p.fases && p.fases.length > 0) {
            const fasesRows = p.fases.map(f =>
                `<tr class="${f.fase_atual ? 'bg-blue-50 font-medium' : ''}">
                    <td class="px-2 py-1.5 text-xs">${escHtml(f.tipo_fase || '—')}</td>
                    <td class="px-2 py-1.5 text-xs">${escHtml(f.instancia || '—')}</td>
                    <td class="px-2 py-1.5 text-xs">${escHtml(f.localidade || '—')}</td>
                    <td class="px-2 py-1.5 text-xs text-center">${formatDate(f.data)}</td>
                    <td class="px-2 py-1.5 text-xs text-center">${f.dias_fase_ativa != null ? f.dias_fase_ativa + 'd' : '—'}</td>
                </tr>`
            ).join('');

            html += sectionTable('Histórico de Fases', ['Fase', 'Instância', 'Localidade', 'Data', 'Dias'], fasesRows);
        }

        // ── Últimos Andamentos ──
        if (p.andamentos && p.andamentos.length > 0) {
            const andRows = p.andamentos.map(a =>
                `<tr>
                    <td class="px-2 py-1.5 text-xs">${formatDate(a.data_hora)}</td>
                    <td class="px-2 py-1.5 text-xs">${escHtml(a.status || '—')}</td>
                    <td class="px-2 py-1.5 text-xs">${formatDate(a.data_prazo_fatal)}</td>
                </tr>`
            ).join('');

            html += sectionTable('Últimos Andamentos', ['Data', 'Status', 'Prazo Fatal'], andRows);
        }

        // ── Movimentos Financeiros ──
        if (p.movimentos && p.movimentos.length > 0) {
            const movRows = p.movimentos.map(m =>
                `<tr>
                    <td class="px-2 py-1.5 text-xs">${formatDate(m.data)}</td>
                    <td class="px-2 py-1.5 text-xs text-right ${parseFloat(m.valor) >= 0 ? 'text-green-700' : 'text-red-600'}">${money(m.valor)}</td>
                    <td class="px-2 py-1.5 text-xs truncate max-w-[200px]">${escHtml(m.descricao || '—')}</td>
                </tr>`
            ).join('');

            html += sectionTable('Movimentos Financeiros', ['Data', 'Valor', 'Descrição'], movRows);
        }

        // ── Horas Trabalhadas ──
        if (p.horas && p.horas.length > 0) {
            const horasRows = p.horas.map(h =>
                `<tr>
                    <td class="px-2 py-1.5 text-xs">${formatDate(h.data)}</td>
                    <td class="px-2 py-1.5 text-xs text-center">${escHtml(h.duracao_original || '—')}</td>
                    <td class="px-2 py-1.5 text-xs text-right">${money(h.valor_total_original)}</td>
                    <td class="px-2 py-1.5 text-xs">${escHtml(h.tipo || '—')}</td>
                </tr>`
            ).join('');

            html += sectionTable('Horas Trabalhadas', ['Data', 'Duração', 'Valor', 'Tipo'], horasRows);
        }

        body.innerHTML = html;
    }

    // ── Helpers do popup ──

    function section(title, rows, isKV = true) {
        const content = rows.filter(r => r).join('');
        if (!content) return '';
        return `<div class="mb-4">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 pb-1 border-b border-gray-200">${title}</h4>
            ${isKV ? '<div class="space-y-1">' + content + '</div>' : content}
        </div>`;
    }

    function sectionTable(title, headers, rowsHtml) {
        return `<div class="mb-4">
            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-2 pb-1 border-b border-gray-200">${title}</h4>
            <div class="overflow-x-auto"><table class="w-full text-left">
                <thead><tr class="bg-gray-50">${headers.map(h => `<th class="px-2 py-1 text-xs font-semibold text-gray-600">${h}</th>`).join('')}</tr></thead>
                <tbody class="divide-y divide-gray-100">${rowsHtml}</tbody>
            </table></div>
        </div>`;
    }

    function row(label, value, badge) {
        if (!value && value !== 0) return '';
        const display = badge || `<span class="text-xs text-gray-900">${escHtml(String(value))}</span>`;
        return `<div class="flex justify-between items-center py-0.5"><span class="text-xs text-gray-500 mr-3">${label}</span>${display}</div>`;
    }

    function statusBadge(status) {
        if (!status) return '';
        const colors = {
            'Ativo': 'bg-green-100 text-green-800',
            'Encerrado': 'bg-gray-100 text-gray-700',
            'Arquivado': 'bg-yellow-100 text-yellow-800',
        };
        const cls = colors[status] || 'bg-gray-100 text-gray-700';
        return `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${cls}">${escHtml(status)}</span>`;
    }

    function possibilidadeBadge(possibilidade) {
        if (!possibilidade) return '';
        const colors = {
            'Provável': 'bg-green-100 text-green-800',
            'Possível': 'bg-yellow-100 text-yellow-800',
            'Remota': 'bg-red-100 text-red-800',
        };
        const cls = colors[possibilidade] || 'bg-gray-100 text-gray-700';
        return `<span class="inline-flex px-2 py-0.5 rounded text-xs font-medium ${cls}">${escHtml(possibilidade)}</span>`;
    }

    function money(val) {
        if (val == null || val === '') return '—';
        const num = parseFloat(val);
        if (isNaN(num)) return '—';
        return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    // —— Ações existentes ——————————————————————————————————

    async function autoVincular() {
        if (!_conversationId) return;

        try {
            const resp = await fetch(baseUrl('auto-vincular-cliente'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            const result = await resp.json();

            if (result.success) {
                if (typeof window.refreshContexto360 === 'function') {
                    window.refreshContexto360(_conversationId);
                }
                carregar();
            } else {
                alert(result.message || 'Não foi possível vincular automaticamente.');
            }
        } catch (e) {
            console.error('AutoVincular erro:', e);
            alert('Erro ao tentar vincular. Tente novamente.');
        }
    }

    function debounceBusca(q) {
        clearTimeout(_buscaTimer);
        _buscaTimer = setTimeout(() => buscarClientes(q), 400);
    }

    async function buscarClientes(q) {
        if (!_conversationId || q.length < 2) {
            hide('dj-busca-resultados');
            return;
        }

        try {
            const resp = await fetch(baseUrl('buscar-clientes') + `?q=${encodeURIComponent(q)}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });

            const data = await resp.json();
            const container = document.getElementById('dj-busca-resultados');
            if (!container) return;

            if (!data.clientes || data.clientes.length === 0) {
                container.innerHTML = '<p class="p-3 text-xs text-gray-400">Nenhum resultado.</p>';
                show('dj-busca-resultados');
                return;
            }

            container.innerHTML = data.clientes.map(c =>
                `<button onclick="NexoDataJuri.vincularCliente(${c.id})" class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-0 transition">
                    <p class="text-sm font-medium text-gray-900">${escHtml(c.nome)}</p>
                    <p class="text-xs text-gray-500">${escHtml(c.tipo || '')} ${c.cpf_cnpj ? '· ' + escHtml(c.cpf_cnpj) : ''} ${c.telefone ? '· ' + escHtml(c.telefone) : ''}</p>
                </button>`
            ).join('');
            show('dj-busca-resultados');
        } catch (e) {
            console.error('Busca clientes erro:', e);
        }
    }

    async function vincularCliente(clienteId) {
        if (!_conversationId) return;

        try {
            const resp = await fetch(`/nexo/atendimento/conversas/${_conversationId}/link-cliente`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ cliente_id: clienteId }),
            });

            if (resp.ok) {
                if (typeof window.refreshContexto360 === 'function') {
                    window.refreshContexto360(_conversationId);
                }
                carregar();
            }
        } catch (e) {
            console.error('Vincular cliente erro:', e);
        }
    }

    async function selecionarProcesso(processoId) {
        if (!_conversationId || !processoId) return;

        try {
            const resp = await fetch(baseUrl('link-processo'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ processo_id: parseInt(processoId) }),
            });

            if (resp.ok) {
                carregar();
            }
        } catch (e) {
            console.error('Link processo erro:', e);
        }
    }

    async function unlinkProcesso() {
        if (!_conversationId) return;

        try {
            await fetch(baseUrl('unlink-processo'), {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            carregar();
        } catch (e) {
            console.error('Unlink processo erro:', e);
        }
    }

    async function togglePrazos() {
        if (!_conversationId || !_data) return;

        const verTodos = document.getElementById('dj-toggle-prazos')?.checked;

        if (verTodos || !_data.linked_processo_id) {
            renderPrazos(_data.prazos);
            return;
        }

        try {
            const resp = await fetch(baseUrl('prazos-filtrados') + `?processo_id=${_data.linked_processo_id}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            const result = await resp.json();
            renderPrazos(result.prazos || []);
        } catch (e) {
            console.error('Toggle prazos erro:', e);
        }
    }

    // —— Helpers ——————————————————————————————————

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text;
    }

    function escHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '—';
        try {
            const d = new Date(dateStr);
            if (isNaN(d.getTime())) return dateStr;
            return d.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
        } catch { return dateStr; }
    }

    function urgenciaFromDias(dias) {
        if (dias <= 0) return 'vencido';
        if (dias <= 2) return 'critico';
        if (dias <= 5) return 'urgente';
        if (dias <= 15) return 'atencao';
        return 'normal';
    }

    function urgenciaClass(urgencia) {
        const map = {
            vencido: { bg: 'bg-red-100', badge: 'bg-red-600 text-white', text: 'text-red-600' },
            critico: { bg: 'bg-red-50', badge: 'bg-red-500 text-white', text: 'text-red-500' },
            urgente: { bg: 'bg-orange-50', badge: 'bg-orange-500 text-white', text: 'text-orange-500' },
            atencao: { bg: 'bg-yellow-50', badge: 'bg-yellow-500 text-white', text: 'text-yellow-600' },
            normal:  { bg: 'bg-gray-50', badge: 'bg-gray-400 text-white', text: 'text-gray-500' },
        };
        return map[urgencia] || map.normal;
    }

    // Fechar modal ao clicar fora
    document.getElementById('dj-modal-processo')?.addEventListener('click', function(e) {
        if (e.target === this) fecharPopup();
    });

    // Fechar modal com ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fecharPopup();
    });

    // —— API pública ——————————————————————————————

    return {
        setConversation,
        carregar,
        autoVincular,
        debounceBusca,
        vincularCliente,
        selecionarProcesso,
        unlinkProcesso,
        togglePrazos,
        abrirPopup,
        abrirPopupProcessoVinculado,
        fecharPopup,
    };
})();
</script>
<?php /**PATH /home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/resources/views/nexo/atendimento/partials/_contexto_datajuri.blade.php ENDPATH**/ ?>