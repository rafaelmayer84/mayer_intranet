@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('precificacao.index') }}" class="text-indigo-600 hover:text-indigo-800">← Voltar</a>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Proposta #{{ $proposta->id }}</h1>
        @php
            $statusColors = [
                'gerada' => 'bg-yellow-100 text-yellow-700',
                'enviada' => 'bg-blue-100 text-blue-700',
                'aceita' => 'bg-green-100 text-green-700',
                'recusada' => 'bg-red-100 text-red-700',
            ];
        @endphp
        <span class="px-3 py-1 text-xs rounded-full {{ $statusColors[$proposta->status] ?? 'bg-gray-100 text-gray-700' }}">
            {{ ucfirst($proposta->status) }}
        </span>
    </div>

    {{-- Info do proponente --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Proponente</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-400">Nome:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->nome_proponente ?? '-' }}</p>
            </div>
            <div>
                <span class="text-gray-400">Tipo:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->tipo_pessoa ?? '-' }}</p>
            </div>
            <div>
                <span class="text-gray-400">Área:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->area_direito ?? '-' }}</p>
            </div>
            @if($proposta->tipo_acao)
            <div>
                <span class="text-gray-400">Tipo de Ação:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->tipo_acao }}</p>
            </div>
            @endif
            <div>
                <span class="text-gray-400">Data:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->created_at->format('d/m/Y H:i') }}</p>
            </div>
            @if($proposta->valor_causa)
            <div>
                <span class="text-gray-400">Valor Causa:</span>
                <p class="font-medium text-gray-800 dark:text-white">R$ {{ number_format($proposta->valor_causa, 2, ',', '.') }}</p>
            </div>
            @endif
            @if($proposta->siric_score)
            <div>
                <span class="text-gray-400">SIRIC:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->siric_score }} ({{ $proposta->siric_rating }})</p>
            </div>
            @endif
        </div>
        @if($proposta->descricao_demanda)
        <div class="mt-4">
            <span class="text-gray-400 text-sm">Demanda:</span>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ $proposta->descricao_demanda }}</p>
        </div>
        @endif
    </div>

    {{-- 3 Propostas --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach([
            ['key' => 'rapida', 'label' => 'Fechamento Rápido', 'icon' => '⚡', 'data' => $proposta->proposta_rapida],
            ['key' => 'equilibrada', 'label' => 'Equilibrada', 'icon' => '⚖️', 'data' => $proposta->proposta_equilibrada],
            ['key' => 'premium', 'label' => 'Premium', 'icon' => '👑', 'data' => $proposta->proposta_premium],
        ] as $tipo)
            @php
                $isRecommended = $proposta->recomendacao_ia === $tipo['key'];
                $isChosen = $proposta->proposta_escolhida === $tipo['key'];
                $p = $tipo['data'] ?? [];
            @endphp
            @php
                $parc = $p['parcelas'] ?? [];
                $parcTotal = $parc['total'] ?? ($p['parcelas_sugeridas'] ?? 1);
                $prob = $p['probabilidade_conversao_estimada'] ?? null;
                $er = $p['expected_revenue'] ?? null;
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 relative
                {{ $isRecommended ? 'ring-2 ring-indigo-500' : '' }}
                {{ $isChosen ? 'ring-2 ring-green-500' : '' }}">
                @if($isRecommended)
                    <div class="absolute -top-2 left-4 px-2 py-0.5 bg-brand text-white text-xs rounded-full">Recomendada</div>
                @endif
                @if($isChosen)
                    <div class="absolute -top-2 right-4 px-2 py-0.5 bg-green-600 text-white text-xs rounded-full">Escolhida</div>
                @endif
                <div class="text-center mb-3">
                    <span class="text-xl">{{ $tipo['icon'] }}</span>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mt-1">{{ $tipo['label'] }}</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">
                        R$ {{ number_format($p['valor_honorarios'] ?? 0, 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ $p['tipo_cobranca'] ?? 'fixo' }} | {{ $parcTotal }}x</p>
                </div>

                @if($prob)
                <div class="flex justify-between text-xs mb-3 px-2 py-1.5 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                    <span class="text-gray-500 dark:text-gray-400">Conv. <strong class="text-gray-700 dark:text-gray-200">{{ $prob }}%</strong></span>
                    @if($er)
                    <span class="text-gray-500 dark:text-gray-400">ER <strong class="text-gray-700 dark:text-gray-200">R$ {{ number_format($er, 0, ',', '.') }}</strong></span>
                    @endif
                </div>
                @endif

                @if(!empty($parc['entrada']))
                <div class="text-xs text-gray-500 dark:text-gray-400 mb-3 space-y-0.5">
                    <p>Entrada: <strong class="text-gray-700 dark:text-gray-200">R$ {{ number_format($parc['entrada'], 0, ',', '.') }}</strong> + {{ $parcTotal - 1 }}x <strong class="text-gray-700 dark:text-gray-200">R$ {{ number_format($parc['valor_parcela'] ?? 0, 0, ',', '.') }}</strong></p>
                    @if(!empty($parc['valor_avista']))
                    <p>A vista: <strong class="text-gray-700 dark:text-gray-200">R$ {{ number_format($parc['valor_avista'], 0, ',', '.') }}</strong> <span class="text-green-600">(-{{ $parc['desconto_avista_percentual'] ?? 0 }}%)</span></p>
                    @endif
                </div>
                @endif

                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">{{ $p['justificativa_estrategica'] ?? '' }}</p>
            </div>
        @endforeach
    </div>

    {{-- Análise Yield --}}
    @if($proposta->analise_yield)
    @php $yield = $proposta->analise_yield; @endphp
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider">Análise Yield</h2>
            @if($proposta->modelo_ia_utilizado)
            <span class="text-xs px-2 py-1 rounded-full {{ str_starts_with($proposta->modelo_ia_utilizado, 'claude-') ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' }}">
                {{ $proposta->modelo_ia_utilizado }}
            </span>
            @endif
        </div>
        <div class="flex flex-wrap gap-2">
            @foreach([
                'segmento_cliente' => 'Segmento',
                'elasticidade_estimada' => 'Elasticidade',
                'load_factor_escritorio' => 'Load Factor',
                'estrategia_dominante' => 'Estratégia',
                'faixa_historica_aplicada' => 'Faixa Histórica',
            ] as $key => $label)
                @if(!empty($yield[$key]))
                <span class="px-2 py-1 text-xs rounded-full bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-700">
                    {{ $label }}: <strong>{{ $yield[$key] }}</strong>
                </span>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    {{-- Justificativa IA --}}
    @if($proposta->justificativa_ia)
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Análise Estratégica</h2>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $proposta->justificativa_ia }}</p>
    </div>
    @endif

    {{-- Decisão do advogado --}}
    @if($proposta->proposta_escolhida)
    <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl border border-green-200 dark:border-green-800 p-6">
        <h2 class="text-sm font-semibold text-green-700 dark:text-green-300 uppercase tracking-wider mb-2">Decisão do Advogado</h2>
        <p class="text-sm text-green-800 dark:text-green-200">
            Proposta escolhida: <strong>{{ ucfirst($proposta->proposta_escolhida) }}</strong>
            @if($proposta->valor_final)
                | Valor final: <strong>R$ {{ number_format($proposta->valor_final, 2, ',', '.') }}</strong>
            @endif
        </p>
        @if($proposta->observacao_advogado)
            <p class="text-sm text-green-700 dark:text-green-300 mt-2">{{ $proposta->observacao_advogado }}</p>
        @endif
    </div>

    {{-- SIPEX v2.0: Botao abre modal de configuracao --}}
    <div class="mt-4 flex items-center gap-3" x-data="sipexModal()">
        <button @click="abrirModal()"
            class="px-5 py-2.5 rounded-xl text-sm font-medium hover:opacity-90 transition flex items-center gap-2" style="background-color:#1B334A;color:#ffffff;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span x-text="loading ? 'Carregando...' : 'Gerar Proposta para Cliente'"></span>
        </button>

        @if($proposta->texto_proposta_cliente)
            <a href="{{ route('precificacao.proposta.print', $proposta->id) }}" target="_blank"
                class="px-5 py-2.5 bg-gray-600 text-white rounded-xl text-sm font-medium hover:bg-gray-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Ver Proposta Gerada
            </a>
        @endif

        <div x-show="status" x-text="statusMsg" class="text-sm" :class="statusOk ? 'text-green-600' : 'text-red-600'"></div>

        {{-- MODAL DE CONFIGURACAO --}}
        <div x-show="modalOpen" x-cloak
            style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.5);backdrop-filter:blur(2px);"
            @keydown.escape.window="modalOpen=false">
            <div @click.outside="modalOpen=false"
                style="background:#fff;border-radius:16px;width:95%;max-width:720px;max-height:90vh;overflow-y:auto;box-shadow:0 25px 50px rgba(0,0,0,0.25);padding:0;">

                {{-- Header --}}
                <div style="background:#1B334A;color:#fff;padding:20px 28px;border-radius:16px 16px 0 0;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <h2 style="font-size:16px;font-weight:700;margin:0;">Configurar Proposta de Honorarios</h2>
                        <p style="font-size:12px;opacity:0.8;margin-top:4px;">Valores pre-sugeridos pela IA. Confirme ou ajuste antes de gerar.</p>
                    </div>
                    <button @click="modalOpen=false" style="background:none;border:none;color:#fff;font-size:20px;cursor:pointer;">&times;</button>
                </div>

                <div style="padding:24px 28px;">

                    {{-- SECAO: HONORARIOS --}}
                    <div style="margin-bottom:24px;">
                        <h3 style="font-size:13px;font-weight:700;color:#1B334A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:2px solid #C4A35A;">Honorarios</h3>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Valor (R$)</label>
                                <input type="number" x-model="cfg.valor_honorarios" step="0.01" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                                <p style="font-size:10px;color:#999;margin-top:2px;">Faixa: R$ <span x-text="fmt(cfg.faixa_min)"></span> a R$ <span x-text="fmt(cfg.faixa_max)"></span></p>
                            </div>
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Tipo Cobranca</label>
                                <select x-model="cfg.tipo_cobranca" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                                    <option value="fixo">Fixo</option>
                                    <option value="mensal">Mensal</option>
                                    <option value="misto">Misto</option>
                                    <option value="percentual">Percentual</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Parcelas</label>
                                <input type="number" x-model="cfg.parcelas" min="1" max="24" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                            </div>
                        </div>
                    </div>

                    {{-- SECAO: EXITO --}}
                    <div style="margin-bottom:24px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <h3 style="font-size:13px;font-weight:700;color:#1B334A;text-transform:uppercase;letter-spacing:0.5px;margin:0;">Honorarios de Exito</h3>
                            <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#666;cursor:pointer;">
                                <input type="checkbox" x-model="cfg.incluir_exito" style="accent-color:#1B334A;"> Incluir
                            </label>
                        </div>
                        <div x-show="cfg.incluir_exito" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Tipo</label>
                                <select x-model="cfg.exito_tipo" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                                    <option value="percentual">Percentual do proveito</option>
                                    <option value="fixo">Valor fixo</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Valor / Percentual</label>
                                <input type="text" x-model="cfg.exito_valor" placeholder="Ex: 20% ou R$ 30.000" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                            </div>
                            <div style="grid-column:span 2;">
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Condicao do exito</label>
                                <input type="text" x-model="cfg.exito_condicao" placeholder="Ex: Apos transito em julgado favoravel" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                            </div>
                        </div>
                    </div>

                    {{-- SECAO: ESCOPO --}}
                    <div style="margin-bottom:24px;">
                        <h3 style="font-size:13px;font-weight:700;color:#1B334A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:2px solid #C4A35A;">Escopo e Estrategia</h3>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Escopo</label>
                                <select x-model="cfg.escopo" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                                    <template x-for="op in cfg.escopo_opcoes" :key="op">
                                        <option :value="op" x-text="op"></option>
                                    </template>
                                </select>
                            </div>
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Vigencia (dias)</label>
                                <input type="number" x-model="cfg.vigencia_dias" min="5" max="90" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                            </div>
                        </div>
                        <div style="margin-top:12px;">
                            <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Estrategia (resumo para a IA expandir)</label>
                            <textarea x-model="cfg.estrategia_resumo" rows="2" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;resize:vertical;"></textarea>
                        </div>
                    </div>

                    {{-- SECAO: HORAS --}}
                    <div style="margin-bottom:24px;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                            <h3 style="font-size:13px;font-weight:700;color:#1B334A;text-transform:uppercase;letter-spacing:0.5px;margin:0;">Horas de Trabalho</h3>
                            <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#666;cursor:pointer;">
                                <input type="checkbox" x-model="cfg.incluir_tabela_horas" style="accent-color:#1B334A;"> Incluir tabela detalhada
                            </label>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Horas estimadas (min)</label>
                                <input type="number" x-model="cfg.horas_estimadas_min" min="0" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                            </div>
                            <div>
                                <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Horas estimadas (max)</label>
                                <input type="number" x-model="cfg.horas_estimadas_max" min="0" style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                            </div>
                        </div>
                    </div>

                    {{-- SECAO: DESPESAS --}}
                    <div style="margin-bottom:24px;">
                        <h3 style="font-size:13px;font-weight:700;color:#1B334A;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:12px;padding-bottom:6px;border-bottom:2px solid #C4A35A;">Despesas Reembolsaveis</h3>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            <template x-for="(d, i) in despesasOpcoes" :key="i">
                                <label style="display:flex;align-items:center;gap:4px;font-size:12px;color:#444;cursor:pointer;background:#f8f9fb;padding:6px 12px;border-radius:8px;border:1px solid #e5e7eb;">
                                    <input type="checkbox" :value="d" x-model="cfg.despesas_selecionadas" style="accent-color:#1B334A;">
                                    <span x-text="d"></span>
                                </label>
                            </template>
                        </div>
                    </div>

                    {{-- OBSERVACOES --}}
                    <div style="margin-bottom:24px;">
                        <label style="font-size:11px;color:#666;display:block;margin-bottom:4px;">Observacoes adicionais (opcional)</label>
                        <textarea x-model="cfg.observacoes_advogado" rows="2" placeholder="Instrucoes extras para a IA considerar na redacao..." style="width:100%;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;resize:vertical;"></textarea>
                    </div>

                    {{-- BOTOES --}}
                    <div style="display:flex;justify-content:flex-end;gap:12px;padding-top:16px;border-top:1px solid #eee;">
                        <button @click="modalOpen=false" style="padding:10px 20px;border:1px solid #ddd;border-radius:8px;font-size:13px;cursor:pointer;background:#fff;">Cancelar</button>
                        <button @click="gerarProposta()" :disabled="gerando"
                            style="padding:10px 24px;border:none;border-radius:8px;font-size:13px;cursor:pointer;background:#1B334A;color:#fff;font-weight:600;"
                            :style="gerando ? 'opacity:0.6;cursor:wait;' : ''">
                            <span x-text="gerando ? 'Gerando proposta... (30-60s)' : 'Gerar Proposta Persuasiva'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
<script>
function sipexModal() {
    return {
        modalOpen: false,
        loading: false,
        gerando: false,
        status: false,
        statusMsg: '',
        statusOk: false,
        proposalId: {{ $proposta->id }},
        cfg: {
            valor_honorarios: 0,
            tipo_cobranca: 'fixo',
            parcelas: 1,
            incluir_exito: false,
            exito_tipo: 'percentual',
            exito_valor: '',
            exito_condicao: '',
            horas_estimadas_min: 0,
            horas_estimadas_max: 0,
            escopo: '',
            escopo_opcoes: ['1a instancia ate sentenca'],
            estrategia_resumo: '',
            vigencia_dias: 15,
            incluir_tabela_horas: false,
            observacoes_advogado: '',
            faixa_min: 0,
            faixa_max: 0,
            despesas_selecionadas: [],
        },
        despesasOpcoes: [
            'Custas judiciais',
            'Honorarios periciais',
            'Assistente tecnico',
            'Deslocamentos fora da comarca',
            'Correios e reprografia',
            'Emolumentos cartorios',
            'Certidoes e diligencias',
        ],

        fmt(v) {
            return Number(v || 0).toLocaleString('pt-BR', {minimumFractionDigits:2, maximumFractionDigits:2});
        },

        async abrirModal() {
            this.loading = true;
            try {
                const url = '{{ url("/precificacao") }}/' + this.proposalId + '/sugerir-config';
                const resp = await fetch(url, {
                    headers: {'Accept':'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}
                });
                const data = await resp.json();
                if (data.success && data.config) {
                    const c = data.config;
                    this.cfg.valor_honorarios = c.valor_honorarios || this.cfg.valor_honorarios;
                    this.cfg.tipo_cobranca = c.tipo_cobranca || 'fixo';
                    this.cfg.parcelas = c.parcelas || 1;
                    this.cfg.incluir_exito = !!c.incluir_exito;
                    this.cfg.exito_tipo = c.exito_tipo || 'percentual';
                    this.cfg.exito_valor = c.exito_valor || '';
                    this.cfg.exito_condicao = c.exito_condicao || '';
                    this.cfg.horas_estimadas_min = c.horas_estimadas_min || 0;
                    this.cfg.horas_estimadas_max = c.horas_estimadas_max || 0;
                    this.cfg.escopo = c.escopo || '';
                    this.cfg.escopo_opcoes = c.escopo_opcoes || ['1a instancia ate sentenca'];
                    this.cfg.estrategia_resumo = c.estrategia_resumo || '';
                    this.cfg.vigencia_dias = c.vigencia_dias || 15;
                    this.cfg.incluir_tabela_horas = !!c.incluir_tabela_horas;
                    this.cfg.faixa_min = c.faixa_min || 0;
                    this.cfg.faixa_max = c.faixa_max || 0;
                    if (c.despesas_sugeridas && Array.isArray(c.despesas_sugeridas)) {
                        this.cfg.despesas_selecionadas = c.despesas_sugeridas;
                    }
                }
            } catch(e) {
                console.error('Erro ao carregar sugestoes:', e);
            }
            this.loading = false;
            this.modalOpen = true;
        },

        async gerarProposta() {
            this.gerando = true;
            this.status = false;
            try {
                const url = '{{ url("/precificacao") }}/' + this.proposalId + '/gerar-proposta-cliente';
                const resp = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify(this.cfg),
                });
                const data = await resp.json();
                if (data.success) {
                    this.modalOpen = false;
                    this.status = true;
                    this.statusOk = true;
                    this.statusMsg = 'Proposta gerada com sucesso! Abrindo...';
                    window.open(data.redirect, '_blank');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    throw new Error(data.error || 'Erro desconhecido');
                }
            } catch(e) {
                this.status = true;
                this.statusOk = false;
                this.statusMsg = 'Erro: ' + e.message;
            }
            this.gerando = false;
        }
    };
}
</script>
@endsection
