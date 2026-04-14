@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">SIPEX – Sistema Inteligente de Precificação e Expansão</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Formação estratégica de honorários com inteligência artificial</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('precificacao.historico') }}" class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                Histórico
            </a>
            @if(in_array(auth()->user()->role ?? '', ['admin', 'socio']))
            <a href="{{ route('precificacao.calibracao') }}" class="px-4 py-2 text-sm bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-200 rounded-lg hover:bg-indigo-200 dark:hover:bg-indigo-800 transition">
                ⚙ Calibração
            </a>
            @endif
        </div>
    </div>

    {{-- ETAPA 1: Busca do Proponente --}}
    <div id="etapa-busca" class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">1. Identificar Proponente</h2>
        <div class="relative">
            <input type="text" id="busca-proponente" placeholder="Digite nome, CPF ou telefone (mín. 3 caracteres)..."
                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-white dark:bg-gray-700 text-gray-800 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                autocomplete="off">
            <div id="busca-resultados" class="absolute z-50 w-full mt-1 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg hidden max-h-80 overflow-y-auto"></div>
        </div>
        <div id="proponente-selecionado" class="hidden mt-4 p-4 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-200 dark:border-indigo-800">
            <div class="flex justify-between items-start">
                <div>
                    <p class="font-semibold text-indigo-800 dark:text-indigo-200" id="sel-nome"></p>
                    <p class="text-sm text-indigo-600 dark:text-indigo-300" id="sel-info"></p>
                </div>
                <button onclick="limparSelecao()" class="text-sm text-red-500 hover:text-red-700">✕ Limpar</button>
            </div>
        </div>
    </div>

    {{-- ETAPA 2: Dados da Demanda (aparece após selecionar proponente) --}}
    <div id="etapa-demanda" class="hidden bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">2. Dados da Demanda</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Área do Direito</label>
                <select id="area-direito" onchange="filtrarTiposAcao()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                    <option value="">Selecione a área...</option>
                    <option value="Atuação Avulsa/Extrajudicial">Atuação Avulsa/Extrajudicial</option>
                    <option value="Juizados Especiais">Juizados Especiais</option>
                    <option value="Direito Administrativo/Público">Direito Administrativo/Público</option>
                    <option value="Direito Civil e Empresarial">Direito Civil e Empresarial</option>
                    <option value="Direito Falimentar">Direito Falimentar</option>
                    <option value="Direito de Família">Direito de Família</option>
                    <option value="Direito das Sucessões">Direito das Sucessões</option>
                    <option value="Direito Eleitoral">Direito Eleitoral</option>
                    <option value="Direito Militar">Direito Militar</option>
                    <option value="Direito Penal">Direito Penal</option>
                    <option value="Direito do Trabalho">Direito do Trabalho</option>
                    <option value="Direito Previdenciário">Direito Previdenciário</option>
                    <option value="Direito Tributário">Direito Tributário</option>
                    <option value="Direito do Consumidor">Direito do Consumidor</option>
                    <option value="Tribunais e Conselhos">Tribunais e Conselhos</option>
                    <option value="Direito Desportivo">Direito Desportivo</option>
                    <option value="Direito Marítimo/Portuário/Aduaneiro">Direito Marítimo/Portuário/Aduaneiro</option>
                    <option value="Direito de Partido">Direito de Partido</option>
                    <option value="Propriedade Intelectual">Propriedade Intelectual</option>
                    <option value="Direito Ambiental">Direito Ambiental</option>
                    <option value="Direito da Criança e Adolescente">Direito da Criança e Adolescente</option>
                    <option value="Direito Digital">Direito Digital</option>
                    <option value="Assistência Social">Assistência Social</option>
                    <option value="Direito Imobiliário">Direito Imobiliário</option>
                    <option value="Mediação e Conciliação">Mediação e Conciliação</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de Ação</label>
                <select id="tipo-acao" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                    <option value="">Selecione primeiro a área...</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor da Causa (R$)</label>
                <input type="number" id="valor-causa" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="0,00">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Econômico Envolvido (R$)</label>
                <input type="number" id="valor-economico" step="0.01" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="0,00">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Urgência</label>
                <select id="urgencia" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                    <option value="">Selecione...</option>
                    <option value="baixa">Baixa</option>
                    <option value="media">Média</option>
                    <option value="alta">Alta</option>
                    <option value="urgente">Urgente</option>
                </select>
            </div>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descrição da Demanda</label>
            <textarea id="descricao-demanda" rows="3" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="Pré-carregado do lead quando disponível..."></textarea>
        </div>
        <div class="mt-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Contexto Adicional / Observação Estratégica <span class="text-gray-400">(opcional)</span></label>
            <textarea id="contexto-adicional" rows="2" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="Informações adicionais que a IA deve considerar..."></textarea>
        </div>

        {{-- Dados carregados automaticamente (read-only info) --}}
        <div id="dados-carregados" class="hidden mt-4 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-xl">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wider">Dados carregados automaticamente</p>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm" id="dados-auto-grid"></div>
        </div>

        <div class="mt-6 flex justify-end">
            <button onclick="gerarPropostas()" id="btn-gerar" class="btn-mayer rounded-xl font-semibold flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                <span id="btn-gerar-text">Gerar Propostas</span>
                <svg id="btn-gerar-spinner" class="hidden animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </button>
        </div>
    </div>

    {{-- ETAPA 3: Resultado das Propostas --}}
    <div id="etapa-resultado" class="hidden mb-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">3. Propostas Geradas</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4" id="propostas-grid"></div>

        {{-- Justificativa da IA --}}
        <div id="justificativa-box" class="mt-4 bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Análise Estratégica</p>
            <p id="justificativa-texto" class="text-gray-700 dark:text-gray-300 text-sm leading-relaxed"></p>
            <div id="observacoes-estrategicas" class="mt-3 text-sm text-gray-500 dark:text-gray-400"></div>
        </div>
    </div>

    {{-- Últimas propostas --}}
    @if($propostas->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-white mb-4">Últimas Propostas</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                        <th class="pb-2 font-medium">Data</th>
                        <th class="pb-2 font-medium">Proponente</th>
                        <th class="pb-2 font-medium">Área</th>
                        <th class="pb-2 font-medium">Recomendação</th>
                        <th class="pb-2 font-medium">Escolhida</th>
                        <th class="pb-2 font-medium">Status</th>
                        <th class="pb-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($propostas as $p)
                    <tr class="border-b border-gray-100 dark:border-gray-700/50">
                        <td class="py-2 text-gray-600 dark:text-gray-300">{{ $p->created_at->format('d/m/Y H:i') }}</td>
                        <td class="py-2 text-gray-800 dark:text-white font-medium">{{ $p->nome_proponente ?? '-' }}</td>
                        <td class="py-2 text-gray-600 dark:text-gray-300">{{ $p->area_direito ?? '-' }}</td>
                        <td class="py-2">
                            <span class="px-2 py-0.5 text-xs rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300">
                                {{ ucfirst($p->recomendacao_ia ?? '-') }}
                            </span>
                        </td>
                        <td class="py-2 text-gray-600 dark:text-gray-300">{{ $p->proposta_escolhida ? ucfirst($p->proposta_escolhida) : '-' }}</td>
                        <td class="py-2">
                            @php
                                $statusColors = [
                                    'gerada' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
                                    'enviada' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                    'aceita' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                    'recusada' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                ];
                            @endphp
                            <span class="px-2 py-0.5 text-xs rounded-full {{ $statusColors[$p->status] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ ucfirst($p->status) }}
                            </span>
                        </td>
                        <td class="py-2 flex items-center gap-2">
                            <a href="{{ route('precificacao.show', $p->id) }}" class="text-indigo-600 hover:text-indigo-800 text-xs">Ver</a>
                            @if(in_array(auth()->user()->role ?? '', ['admin', 'socio']))
                            <button onclick="excluirProposta({{ $p->id }})" class="text-red-500 hover:text-red-700 text-xs" title="Excluir">✕</button>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

@push('scripts')
<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
let proponenteSelecionado = null;
let proposalId = null;

// ===== AUTO-LOAD VIA URL (chamado pelo NEXO 360) =====
(function(){
    const params = new URLSearchParams(window.location.search);
    const leadId = params.get('lead_id');
    if (!leadId) return;
    fetch('{{ url("/precificacao/lead") }}/' + leadId, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(dados => {
        if (!dados || !dados.proponente) return;
        proponenteSelecionado = {
            tipo: 'lead',
            id: parseInt(leadId),
            nome: dados.proponente.nome || 'Lead #' + leadId,
            info: dados.proponente.telefone || '',
            documento: dados.proponente.documento || ''
        };
        document.getElementById('sel-nome').textContent = proponenteSelecionado.nome;
        document.getElementById('sel-info').textContent = proponenteSelecionado.info + (proponenteSelecionado.documento ? ' | ' + proponenteSelecionado.documento : '');
        document.getElementById('proponente-selecionado').classList.remove('hidden');
        document.getElementById('etapa-demanda').classList.remove('hidden');
        preencherFormulario(dados, 'lead');
    })
    .catch(e => console.error('Auto-load lead falhou:', e));
})();

// ===== BUSCA DE PROPONENTE =====
let debounceTimer;
document.getElementById('busca-proponente').addEventListener('input', function() {
    clearTimeout(debounceTimer);
    const val = this.value.trim();
    if (val.length < 3) {
        document.getElementById('busca-resultados').classList.add('hidden');
        return;
    }
    debounceTimer = setTimeout(() => {
        fetch(`{{ url('/precificacao/buscar') }}?q=${encodeURIComponent(val)}`, {
            headers: { 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('busca-resultados');
            if (data.length === 0) {
                box.innerHTML = '<div class="p-3 text-sm text-gray-500">Nenhum resultado encontrado</div>';
                box.classList.remove('hidden');
                return;
            }
            box.innerHTML = data.map(item => `
                <div class="p-3 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 cursor-pointer border-b border-gray-100 dark:border-gray-700 last:border-0"
                     onclick="selecionarProponente(${JSON.stringify(item).replace(/"/g, '&quot;')})">
                    <p class="font-medium text-gray-800 dark:text-white text-sm">${item.nome}</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">${item.info}</p>
                </div>
            `).join('');
            box.classList.remove('hidden');
        });
    }, 300);
});

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(e) {
    if (!e.target.closest('#busca-proponente') && !e.target.closest('#busca-resultados')) {
        document.getElementById('busca-resultados').classList.add('hidden');
    }
});

function selecionarProponente(item) {
    proponenteSelecionado = item;
    document.getElementById('busca-resultados').classList.add('hidden');
    document.getElementById('sel-nome').textContent = item.nome;
    document.getElementById('sel-info').textContent = item.info + (item.documento ? ' | ' + item.documento : '');
    document.getElementById('proponente-selecionado').classList.remove('hidden');
    document.getElementById('etapa-demanda').classList.remove('hidden');

    // Carregar dados completos
    const url = item.tipo === 'lead'
        ? `{{ url('/precificacao/lead') }}/${item.id}`
        : `{{ url('/precificacao/cliente') }}/${item.id}`;

    fetch(url, { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(dados => {
        preencherFormulario(dados, item.tipo);
    });
}

function preencherFormulario(dados, tipo) {
    // Área de interesse - selecionar no dropdown
    if (dados.demanda?.area_interesse) {
        const selArea = document.getElementById('area-direito');
        const areaVal = dados.demanda.area_interesse;
        // Tentar match exato, senão match parcial
        let matched = false;
        for (let opt of selArea.options) {
            if (opt.value && opt.value.toLowerCase().includes(areaVal.toLowerCase())) {
                opt.selected = true;
                matched = true;
                filtrarTiposAcao();
                break;
            }
        }
    }
    // Descrição
    if (dados.demanda?.resumo_demanda) {
        document.getElementById('descricao-demanda').value = dados.demanda.resumo_demanda;
    }
    // Urgência
    if (dados.demanda?.urgencia) {
        const sel = document.getElementById('urgencia');
        for (let opt of sel.options) {
            if (opt.value.toLowerCase() === dados.demanda.urgencia.toLowerCase()) {
                opt.selected = true;
                break;
            }
        }
    }

    // Mostrar dados carregados automaticamente
    const grid = document.getElementById('dados-auto-grid');
    let html = '';
    const addItem = (label, value) => {
        if (value && value !== '0' && value !== 'null') {
            html += `<div><span class="text-gray-400">${label}:</span> <span class="text-gray-700 dark:text-gray-200 font-medium">${value}</span></div>`;
        }
    };

    addItem('Tipo', dados.proponente?.tipo_pessoa);
    addItem('Cidade', dados.proponente?.cidade);
    addItem('Intenção', dados.demanda?.intencao_contratar);
    addItem('Potencial', dados.demanda?.potencial_honorarios);

    if (dados.historico_cliente) {
        addItem('Processos', dados.historico_cliente.total_processos);
        addItem('Ativos', dados.historico_cliente.processos_ativos);
        addItem('Val. Causa Médio', dados.historico_cliente.valor_causa_medio ? 'R$ ' + Number(dados.historico_cliente.valor_causa_medio).toLocaleString('pt-BR') : null);
    }
    if (dados.financeiro_cliente) {
        addItem('Faturado', dados.financeiro_cliente.total_faturado ? 'R$ ' + Number(dados.financeiro_cliente.total_faturado).toLocaleString('pt-BR') : null);
        addItem('Pontualidade', dados.financeiro_cliente.taxa_pontualidade ? dados.financeiro_cliente.taxa_pontualidade + '%' : null);
    }
    if (dados.siric?.score) {
        addItem('SIRIC Score', dados.siric.score);
        addItem('SIRIC Rating', dados.siric.rating);
    }

    if (html) {
        grid.innerHTML = html;
        document.getElementById('dados-carregados').classList.remove('hidden');
    }
}

function limparSelecao() {
    proponenteSelecionado = null;
    document.getElementById('proponente-selecionado').classList.add('hidden');
    document.getElementById('etapa-demanda').classList.add('hidden');
    document.getElementById('etapa-resultado').classList.add('hidden');
    document.getElementById('dados-carregados').classList.add('hidden');
    document.getElementById('busca-proponente').value = '';
}

// ===== GERAR PROPOSTAS =====
function gerarPropostas() {
    if (!proponenteSelecionado) return;

    // Popup de confirmacao antes de gerar
    const nome = proponenteSelecionado.nome || 'proponente';
    if (!confirm(`Gerar propostas de honorários para "${nome}"?`)) return;

    const btn = document.getElementById('btn-gerar');
    const txt = document.getElementById('btn-gerar-text');
    const spinner = document.getElementById('btn-gerar-spinner');

    btn.disabled = true;
    txt.textContent = 'Analisando...';
    spinner.classList.remove('hidden');

    // Mostrar overlay de status
    mostrarStatusIA();

    const payload = {
        tipo_proponente: proponenteSelecionado.tipo,
        proponente_id: proponenteSelecionado.id,
        area_direito: document.getElementById('area-direito').value,
        tipo_acao: document.getElementById('tipo-acao').value,
        valor_causa: document.getElementById('valor-causa').value || null,
        valor_economico: document.getElementById('valor-economico').value || null,
        descricao_demanda: document.getElementById('descricao-demanda').value,
        contexto_adicional: document.getElementById('contexto-adicional').value,
    };

    // Timeout de 3 minutos para evitar request pendurado
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 180000);

    fetch('{{ route("precificacao.gerar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(payload),
        signal: controller.signal,
    })
    .then(r => {
        clearTimeout(timeoutId);
        if (!r.ok) {
            return r.text().then(body => {
                let msg = 'Erro do servidor (HTTP ' + r.status + ')';
                try {
                    const j = JSON.parse(body);
                    if (j.erro) msg = j.erro;
                    else if (j.message) msg = j.message;
                } catch(e) {}
                throw new Error(msg);
            });
        }
        return r.json();
    })
    .then(data => {
        btn.disabled = false;
        txt.textContent = 'Gerar Propostas';
        spinner.classList.add('hidden');
        fecharStatusIA();

        if (data.erro) {
            alert('Erro: ' + data.erro);
            return;
        }

        proposalId = data.proposal_id;

        try {
            exibirPropostas(data.resultado);
        } catch(renderErr) {
            console.error('SIPEX: Erro ao renderizar propostas', renderErr);
            alert('Propostas geradas com sucesso, mas houve um erro ao exibir. A página será recarregada.');
            window.location.href = '{{ url("/precificacao") }}/' + proposalId;
        }
    })
    .catch(err => {
        clearTimeout(timeoutId);
        btn.disabled = false;
        txt.textContent = 'Gerar Propostas';
        spinner.classList.add('hidden');
        fecharStatusIA();

        if (err.name === 'AbortError') {
            alert('A análise excedeu o tempo limite (3 minutos). Tente novamente ou selecione um modelo mais rápido na Calibração.');
        } else {
            alert('Erro: ' + (err.message || 'Falha na comunicação com o servidor. Tente novamente.'));
        }
        console.error('SIPEX fetch error:', err);
    });
}

function exibirPropostas(resultado) {
    document.getElementById('etapa-resultado').classList.remove('hidden');

    const grid = document.getElementById('propostas-grid');
    const tipos = [
        { key: 'rapida', label: 'Fechamento Rápido', color: 'emerald', icon: '⚡' },
        { key: 'equilibrada', label: 'Equilibrada', color: 'indigo', icon: '⚖️' },
        { key: 'premium', label: 'Premium', color: 'amber', icon: '👑' },
    ];

    // Guardar resultado completo para uso no modal
    window._sipexResultado = resultado;

    grid.innerHTML = tipos.map(t => {
        const p = resultado['proposta_' + t.key];
        const isRecommended = resultado.recomendacao === t.key;
        const borderClass = isRecommended ? `ring-2 ring-${t.color}-500` : '';
        const valor = Number(p.valor_honorarios).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

        // Dados de parcelas (novo formato v3)
        const parc = p.parcelas || {};
        const parcTotal = parc.total || p.parcelas_sugeridas || 1;
        const parcEntrada = parc.entrada ? 'R$ ' + Number(parc.entrada).toLocaleString('pt-BR') : '';
        const parcValor = parc.valor_parcela ? 'R$ ' + Number(parc.valor_parcela).toLocaleString('pt-BR') : '';
        const descAVista = parc.desconto_avista_percentual ? parc.desconto_avista_percentual + '% desc. à vista' : '';
        const valorAVista = parc.valor_avista ? 'R$ ' + Number(parc.valor_avista).toLocaleString('pt-BR') : '';

        // Metricas de yield
        const prob = p.probabilidade_conversao_estimada || '';
        const er = p.expected_revenue ? 'R$ ' + Number(p.expected_revenue).toLocaleString('pt-BR') : '';

        return `
        <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 ${borderClass} relative hover:shadow-md transition">
            ${isRecommended ? '<div class="absolute -top-2 left-1/2 -translate-x-1/2 px-3 py-0.5 bg-brand text-white text-xs rounded-full font-semibold">Recomendada</div>' : ''}
            <div class="text-center mb-3">
                <span class="text-2xl">${t.icon}</span>
                <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 mt-1 uppercase tracking-wider">${t.label}</h3>
                <p class="text-3xl font-bold text-gray-800 dark:text-white mt-2">${valor}</p>
                <p class="text-xs text-gray-500 mt-1">${p.tipo_cobranca || 'fixo'} | ${parcTotal}x</p>
            </div>

            ${prob ? `
            <div class="flex justify-between text-xs mb-3 px-2 py-1.5 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                <span class="text-gray-500 dark:text-gray-400">Conv. <strong class="text-gray-700 dark:text-gray-200">${prob}%</strong></span>
                <span class="text-gray-500 dark:text-gray-400">ER <strong class="text-gray-700 dark:text-gray-200">${er}</strong></span>
            </div>` : ''}

            ${parcEntrada ? `
            <div class="text-xs text-gray-500 dark:text-gray-400 mb-3 space-y-0.5">
                <p>Entrada: <strong class="text-gray-700 dark:text-gray-200">${parcEntrada}</strong> + ${parcTotal - 1}x <strong class="text-gray-700 dark:text-gray-200">${parcValor}</strong></p>
                ${valorAVista ? `<p>À vista: <strong class="text-gray-700 dark:text-gray-200">${valorAVista}</strong> <span class="text-green-600">(${descAVista})</span></p>` : ''}
            </div>` : ''}

            <p class="text-xs text-gray-600 dark:text-gray-300 mb-4 leading-relaxed line-clamp-4">${p.justificativa_estrategica || ''}</p>
            <button onclick="abrirModalEscolha('${t.key}')"
                class="btn-mayer font-semibold">
                Selecionar
            </button>
        </div>
        `;
    }).join('');

    // Justificativa
    document.getElementById('justificativa-texto').textContent = resultado.justificativa_recomendacao || '';

    // Analise Yield
    const yield_data = resultado.analise_yield;
    let yieldHtml = '';
    if (yield_data) {
        const labels = {
            segmento_cliente: 'Segmento',
            elasticidade_estimada: 'Elasticidade',
            load_factor_escritorio: 'Load Factor',
            estrategia_dominante: 'Estratégia',
            faixa_historica_aplicada: 'Faixa Histórica',
        };
        yieldHtml = '<div class="flex flex-wrap gap-2 mt-3">';
        for (const [k, label] of Object.entries(labels)) {
            if (yield_data[k]) {
                yieldHtml += `<span class="px-2 py-1 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700">${label}: <strong>${yield_data[k]}</strong></span>`;
            }
        }
        yieldHtml += '</div>';
    }

    if (resultado.observacoes_estrategicas) {
        document.getElementById('observacoes-estrategicas').innerHTML =
            yieldHtml +
            '<p class="font-semibold text-gray-600 dark:text-gray-400 mt-3">Observações:</p><p class="text-sm text-gray-600 dark:text-gray-300 mt-1">' + resultado.observacoes_estrategicas + '</p>';
    } else {
        document.getElementById('observacoes-estrategicas').innerHTML = yieldHtml;
    }

    // Scroll suave
    document.getElementById('etapa-resultado').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ===== MODAL DE CONFIRMAÇÃO DE ESCOLHA =====
function abrirModalEscolha(tipo) {
    if (!proposalId || !window._sipexResultado) return;
    const p = window._sipexResultado['proposta_' + tipo];
    if (!p) return;

    const nomes = { rapida: 'Fechamento Rápido', equilibrada: 'Equilibrada', premium: 'Premium' };
    const icons = { rapida: '⚡', equilibrada: '⚖️', premium: '👑' };
    const valor = Number(p.valor_honorarios);
    const valorFmt = valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

    const parc = p.parcelas || {};
    const parcTotal = parc.total || p.parcelas_sugeridas || 1;
    const parcEntrada = parc.entrada ? 'R$ ' + Number(parc.entrada).toLocaleString('pt-BR') : '';
    const parcValor = parc.valor_parcela ? 'R$ ' + Number(parc.valor_parcela).toLocaleString('pt-BR') : '';
    const valorAVista = parc.valor_avista ? 'R$ ' + Number(parc.valor_avista).toLocaleString('pt-BR') : '';
    const descAVista = parc.desconto_avista_percentual || 0;

    let parcelasHtml = '';
    if (parcEntrada) {
        parcelasHtml = `
            <div class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                <p>Entrada: <strong>${parcEntrada}</strong></p>
                <p>${parcTotal - 1}x de <strong>${parcValor}</strong></p>
                ${valorAVista ? `<p>À vista: <strong>${valorAVista}</strong> <span class="text-green-600">(-${descAVista}%)</span></p>` : ''}
            </div>`;
    } else {
        parcelasHtml = `<p class="text-sm text-gray-600 dark:text-gray-300">${parcTotal}x parcelas</p>`;
    }

    const modalHtml = `
    <div id="modal-escolha-overlay" style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);backdrop-filter:blur(2px);">
        <div style="background:#fff;border-radius:16px;width:95%;max-width:520px;box-shadow:0 25px 50px rgba(0,0,0,0.25);overflow:hidden;" class="dark:bg-gray-800">
            <div style="padding:24px;">
                <div class="text-center mb-4">
                    <span class="text-3xl">${icons[tipo]}</span>
                    <h3 class="text-lg font-bold text-gray-800 dark:text-white mt-2">Confirmar Proposta ${nomes[tipo]}</h3>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 mb-4">
                    <div class="text-center mb-3">
                        <p class="text-3xl font-bold text-gray-800 dark:text-white">${valorFmt}</p>
                        <p class="text-sm text-gray-500 mt-1">${p.tipo_cobranca || 'fixo'}</p>
                    </div>
                    ${parcelasHtml}
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Final (ajuste se necessário)</label>
                    <input type="number" id="modal-valor-final" value="${valor}" step="1"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-lg font-semibold">
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observação do advogado <span class="text-gray-400">(opcional)</span></label>
                    <textarea id="modal-observacao" rows="2"
                        class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white text-sm"
                        placeholder="Ajuste de escopo, condição especial, etc."></textarea>
                </div>

                <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800 mb-4">
                    <p class="text-sm text-blue-800 dark:text-blue-200 flex items-start gap-2">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Ao confirmar, esta proposta será registrada como escolhida e uma <strong>oportunidade será atualizada no CRM</strong> com o valor aprovado.
                    </p>
                </div>

                <div class="flex gap-3">
                    <button onclick="fecharModalEscolha()" class="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-xl text-sm font-medium hover:bg-gray-200 transition">
                        Cancelar
                    </button>
                    <button onclick="confirmarEscolha('${tipo}')" class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white transition" style="background-color:#1B334A;">
                        Confirmar Proposta
                    </button>
                </div>
            </div>
        </div>
    </div>`;

    document.body.insertAdjacentHTML('beforeend', modalHtml);
}

function fecharModalEscolha() {
    document.getElementById('modal-escolha-overlay')?.remove();
}

function confirmarEscolha(tipo) {
    if (!proposalId) return;
    const valorFinal = document.getElementById('modal-valor-final')?.value || 0;
    const observacao = document.getElementById('modal-observacao')?.value || '';

    fecharModalEscolha();

    fetch(`{{ url('/precificacao') }}/${proposalId}/escolher`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify({
            proposta_escolhida: tipo,
            valor_final: valorFinal,
            observacao: observacao,
        }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = `{{ url('/precificacao') }}/${proposalId}`;
        }
    })
    .catch(() => alert('Erro de conexão. Tente novamente.'));
}

// ===== TABELA OAB/SC - TIPOS DE AÇÃO POR ÁREA =====
const tiposAcaoPorArea = {
    'Atuação Avulsa/Extrajudicial': [
        'Consulta ou parecer verbal',
        'Consulta ou parecer escrito',
        'Elaboração de contrato',
        'Elaboração de distrato',
        'Acompanhamento de audiência',
        'Mediação ou conciliação extrajudicial',
        'Notificação extrajudicial',
        'Análise e revisão de contrato',
        'Assessoria jurídica mensal (PF)',
        'Assessoria jurídica mensal (PJ)',
        'Diligência em cartório ou órgão público',
        'Assembleia societária',
        'Sustentação oral',
        'Interpelação extrajudicial',
    ],
    'Juizados Especiais': [
        'Ação cível até 20 salários mínimos',
        'Ação cível de 20 a 40 salários mínimos',
        'Recurso inominado',
        'Embargos de declaração',
        'Ação no Juizado Especial Criminal',
        'Ação no Juizado Especial Federal',
        'Ação no Juizado da Fazenda Pública',
    ],
    'Direito Administrativo/Público': [
        'Mandado de segurança',
        'Ação popular',
        'Ação civil pública',
        'Procedimento administrativo disciplinar',
        'Licitação - impugnação ou recurso',
        'Defesa em processo administrativo',
        'Habeas data',
        'Ação contra o poder público',
        'Representação em tribunal de contas',
    ],
    'Direito Civil e Empresarial': [
        'Processo cível de conhecimento',
        'Ação de execução',
        'Ação monitória',
        'Embargos à execução',
        'Embargos de terceiro',
        'Cumprimento de sentença',
        'Ação de cobrança',
        'Ação de indenização por danos morais',
        'Ação de indenização por danos materiais',
        'Ação possessória',
        'Ação de usucapião',
        'Ação renovatória',
        'Ação revisional de contrato',
        'Ação de despejo',
        'Ação de consignação em pagamento',
        'Ação de dissolução de sociedade',
        'Ação de responsabilidade civil',
        'Tutela de urgência (cautelar)',
        'Habilitação de crédito',
        'Impugnação de crédito',
        'Recuperação extrajudicial',
        'Ação de regresso',
        'Produção antecipada de provas',
    ],
    'Direito Falimentar': [
        'Recuperação judicial',
        'Falência - requerimento',
        'Falência - defesa',
        'Habilitação de crédito na falência',
        'Impugnação de crédito na falência',
        'Ação revocatória falencial',
        'Pedido de restituição',
    ],
    'Direito de Família': [
        'Ação de divórcio consensual',
        'Ação de divórcio litigioso',
        'Ação de alimentos',
        'Ação revisional de alimentos',
        'Ação de execução de alimentos',
        'Ação de regulamentação de guarda',
        'Ação de regulamentação de visitas',
        'Ação de investigação de paternidade',
        'Ação de negatória de paternidade',
        'Ação de reconhecimento e dissolução de união estável',
        'Inventário e partilha consensual',
        'Inventário e partilha litigiosa',
        'Medida protetiva (Lei Maria da Penha)',
        'Interdição',
        'Curatela',
        'Adoção',
    ],
    'Direito das Sucessões': [
        'Inventário judicial',
        'Inventário extrajudicial',
        'Arrolamento sumário',
        'Arrolamento comum',
        'Testamento - elaboração',
        'Testamento - impugnação',
        'Ação de petição de herança',
        'Sobrepartilha',
        'Alvará judicial',
    ],
    'Direito Eleitoral': [
        'Ação de impugnação de mandato eletivo',
        'Ação de investigação judicial eleitoral',
        'Recurso eleitoral',
        'Representação eleitoral',
        'Registro de candidatura',
        'Defesa em processo eleitoral',
    ],
    'Direito Militar': [
        'Defesa em processo administrativo militar',
        'Defesa em conselho de justificação',
        'Defesa em conselho de disciplina',
        'Ação judicial militar',
    ],
    'Direito Penal': [
        'Inquérito policial - acompanhamento',
        'Ação penal - defesa',
        'Ação penal - assistência de acusação',
        'Habeas corpus',
        'Revisão criminal',
        'Liberdade provisória',
        'Relaxamento de prisão',
        'Execução penal',
        'Tribunal do Júri',
        'Crimes de trânsito',
        'Crimes contra a honra (queixa-crime)',
        'Crimes ambientais',
        'Crimes tributários',
        'Suspensão condicional do processo',
        'Acordo de não persecução penal',
        'Colaboração premiada',
    ],
    'Direito do Trabalho': [
        'Reclamação trabalhista',
        'Ação de consignação em pagamento trabalhista',
        'Mandado de segurança trabalhista',
        'Ação rescisória trabalhista',
        'Execução trabalhista',
        'Embargos à execução trabalhista',
        'Recurso ordinário trabalhista',
        'Recurso de revista',
        'Agravo de instrumento trabalhista',
        'Defesa em inquérito para apuração de falta grave',
        'Ação de cumprimento',
        'Dissídio coletivo',
        'Consultoria trabalhista preventiva',
    ],
    'Direito Previdenciário': [
        'Aposentadoria por idade',
        'Aposentadoria por tempo de contribuição',
        'Aposentadoria especial',
        'Aposentadoria por invalidez',
        'Auxílio-doença',
        'Auxílio-acidente',
        'Pensão por morte',
        'Benefício assistencial (BPC/LOAS)',
        'Revisão de benefício',
        'Recurso ao CRPS',
        'Ação de concessão de benefício',
        'Ação de restabelecimento de benefício',
        'Complementação de aposentadoria',
        'Desaposentação',
    ],
    'Direito Tributário': [
        'Mandado de segurança tributário',
        'Ação anulatória de débito fiscal',
        'Ação declaratória tributária',
        'Ação de repetição de indébito',
        'Embargos à execução fiscal',
        'Exceção de pré-executividade',
        'Defesa em processo administrativo tributário',
        'Planejamento tributário',
        'Consulta tributária',
        'Recuperação de créditos tributários',
        'Ação de consignação em pagamento tributária',
    ],
    'Direito do Consumidor': [
        'Ação indenizatória consumerista',
        'Ação de obrigação de fazer/não fazer',
        'Ação de rescisão contratual',
        'Ação revisional (relação de consumo)',
        'Defesa do fornecedor',
        'Ação coletiva de consumo',
        'Reclamação junto ao Procon',
    ],
    'Tribunais e Conselhos': [
        'Apelação cível',
        'Agravo de instrumento',
        'Embargos de declaração',
        'Recurso especial',
        'Recurso extraordinário',
        'Ação rescisória',
        'Reclamação constitucional',
        'Mandado de segurança em tribunal',
        'Habeas corpus em tribunal',
        'Sustentação oral',
        'Agravo interno',
        'Embargos de divergência',
    ],
    'Direito Desportivo': [
        'Defesa perante Tribunal de Justiça Desportiva',
        'Recurso ao STJD',
        'Consultoria desportiva',
        'Contrato desportivo - elaboração',
    ],
    'Direito Marítimo/Portuário/Aduaneiro': [
        'Ação marítima',
        'Consultoria portuária/aduaneira',
        'Defesa em processo aduaneiro',
        'Contencioso marítimo',
    ],
    'Direito de Partido': [
        'Consultoria partidária',
        'Defesa em processo de fidelidade',
    ],
    'Propriedade Intelectual': [
        'Registro de marca',
        'Registro de patente',
        'Ação de infração de marca',
        'Ação de infração de patente',
        'Consultoria em propriedade intelectual',
        'Contrato de licenciamento',
    ],
    'Direito Ambiental': [
        'Defesa em ação civil pública ambiental',
        'Licenciamento ambiental',
        'Defesa em processo administrativo ambiental',
        'Ação de reparação de dano ambiental',
        'Consultoria ambiental',
    ],
    'Direito da Criança e Adolescente': [
        'Ação de guarda',
        'Ação de adoção',
        'Medida socioeducativa - defesa',
        'Destituição do poder familiar',
        'Acolhimento institucional',
    ],
    'Direito Digital': [
        'Ação de remoção de conteúdo',
        'Ação de indenização por violação digital',
        'Consultoria em LGPD',
        'Adequação à LGPD',
        'Crimes cibernéticos - defesa',
    ],
    'Assistência Social': [
        'Benefício assistencial (BPC/LOAS)',
        'Defesa em processo de inclusão/exclusão',
    ],
    'Direito Imobiliário': [
        'Ação de usucapião',
        'Retificação de registro imobiliário',
        'Ação de adjudicação compulsória',
        'Ação de imissão na posse',
        'Due diligence imobiliária',
        'Elaboração de contrato imobiliário',
        'Ação de despejo',
        'Ação renovatória de locação',
    ],
    'Mediação e Conciliação': [
        'Mediação privada',
        'Conciliação judicial',
        'Câmara de mediação/arbitragem',
        'Arbitragem',
    ],
};

function filtrarTiposAcao() {
    const area = document.getElementById('area-direito').value;
    const selectTipo = document.getElementById('tipo-acao');
    selectTipo.innerHTML = '<option value="">Selecione o tipo de ação...</option>';

    if (area && tiposAcaoPorArea[area]) {
        tiposAcaoPorArea[area].forEach(tipo => {
            const opt = document.createElement('option');
            opt.value = tipo;
            opt.textContent = tipo;
            selectTipo.appendChild(opt);
        });
    } else {
        selectTipo.innerHTML = '<option value="">Selecione primeiro a área...</option>';
    }
}

// ===== STATUS IA — OVERLAY COM ETAPAS =====
let _statusInterval = null;

function mostrarStatusIA() {
    const etapas = [
        { texto: 'Coletando dados do proponente...', tempo: 0 },
        { texto: 'Consultando histórico do CRM...', tempo: 3000 },
        { texto: 'Analisando dados de conversão...', tempo: 6000 },
        { texto: 'Segmentando perfil do cliente...', tempo: 9000 },
        { texto: 'Calculando load factor do escritório...', tempo: 12000 },
        { texto: 'Gerando proposta Rápida...', tempo: 16000 },
        { texto: 'Gerando proposta Equilibrada...', tempo: 22000 },
        { texto: 'Gerando proposta Premium...', tempo: 28000 },
        { texto: 'Calculando Expected Revenue...', tempo: 34000 },
        { texto: 'Finalizando análise de yield...', tempo: 40000 },
        { texto: 'Aguardando resposta da IA...', tempo: 50000 },
    ];

    const overlay = document.createElement('div');
    overlay.id = 'sipex-status-overlay';
    overlay.style.cssText = 'position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);';
    overlay.innerHTML = `
        <div style="background:#fff;border-radius:20px;width:90%;max-width:420px;padding:32px;box-shadow:0 25px 60px rgba(0,0,0,0.3);text-align:center;" class="dark:bg-gray-800">
            <div style="margin-bottom:20px;">
                <svg class="animate-spin mx-auto" style="width:48px;height:48px;color:#4F46E5;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">SIPEX Analisando</h3>
            <p id="sipex-status-texto" class="text-sm text-gray-600 dark:text-gray-300 mb-4" style="transition:opacity 0.2s ease;">${etapas[0].texto}</p>
            <div style="background:#E5E7EB;border-radius:9999px;height:6px;overflow:hidden;margin-bottom:12px;" class="dark:bg-gray-600">
                <div id="sipex-status-barra" style="background:linear-gradient(90deg,#4F46E5,#7C3AED);height:100%;width:5%;border-radius:9999px;transition:width 0.8s ease;"></div>
            </div>
            <p id="sipex-status-tempo" class="text-xs text-gray-400">0s</p>
        </div>
    `;
    document.body.appendChild(overlay);

    let inicio = Date.now();
    let etapaAtual = 0;

    _statusInterval = setInterval(() => {
        const elapsed = Date.now() - inicio;
        const segundos = Math.floor(elapsed / 1000);

        // Atualizar tempo
        const tempoEl = document.getElementById('sipex-status-tempo');
        if (tempoEl) tempoEl.textContent = segundos + 's';

        // Atualizar barra (max 95%)
        const progresso = Math.min(95, (elapsed / 60000) * 100);
        const barraEl = document.getElementById('sipex-status-barra');
        if (barraEl) barraEl.style.width = progresso + '%';

        // Avançar etapa
        for (let i = etapas.length - 1; i >= 0; i--) {
            if (elapsed >= etapas[i].tempo && i > etapaAtual) {
                etapaAtual = i;
                const textoEl = document.getElementById('sipex-status-texto');
                if (textoEl) {
                    textoEl.style.opacity = '0';
                    setTimeout(() => {
                        textoEl.textContent = etapas[i].texto;
                        textoEl.style.opacity = '1';
                    }, 200);
                }
                break;
            }
        }
    }, 500);
}

function fecharStatusIA() {
    if (_statusInterval) {
        clearInterval(_statusInterval);
        _statusInterval = null;
    }
    const overlay = document.getElementById('sipex-status-overlay');
    if (overlay) {
        // Barra a 100% antes de fechar
        const barra = document.getElementById('sipex-status-barra');
        const texto = document.getElementById('sipex-status-texto');
        if (barra) barra.style.width = '100%';
        if (texto) {
            texto.style.opacity = '0';
            setTimeout(() => {
                texto.textContent = 'Propostas prontas!';
                texto.style.opacity = '1';
            }, 200);
        }
        setTimeout(() => overlay.remove(), 600);
    }
}

// ===== EXCLUIR PROPOSTA (ADMIN) =====
function excluirProposta(id) {
    if (!confirm('Tem certeza que deseja excluir esta proposta? Esta ação não pode ser desfeita.')) return;

    fetch(`{{ url('/precificacao') }}/${id}/excluir`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + (data.erro || 'Não foi possível excluir'));
        }
    })
    .catch(() => alert('Erro de conexão'));
}

</script>
@endpush
@endsection
