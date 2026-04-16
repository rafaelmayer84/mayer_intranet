@extends('layouts.app')

@section('title', 'SIRIC - Consulta #' . $consulta->id)

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6" x-data="{ tab: 'dados' }">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                🔍 SIRIC — Consulta #{{ $consulta->id }}
            </h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ $consulta->nome ?? '—' }} — {{ $consulta->cpf_cnpj }}
            </p>
        </div>
        <div class="mt-3 sm:mt-0 flex gap-2">
            <a href="{{ route('siric.index') }}"
               class="inline-flex items-center px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white text-sm font-medium rounded-lg shadow transition">
                ← Voltar
            </a>
            <form method="POST" action="{{ route('siric.destroy', $consulta->id) }}"
                  onsubmit="return confirm('Excluir esta consulta?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg shadow transition">
                    🗑 Excluir
                </button>
            </form>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-300 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Status + Rating --}}
    <div class="mb-6 flex flex-wrap gap-2 items-center">
        @php
            $statusColors = [
                'rascunho'   => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                'coletado'   => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                'analisando' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
                'analisado'  => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                'decidido'   => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
                'erro'       => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
            ];
            $statusColor = $statusColors[$consulta->status] ?? $statusColors['rascunho'];
        @endphp
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium {{ $statusColor }}">
            Status: {{ ucfirst($consulta->status) }}
        </span>

        @if($consulta->rating)
            @php
                $ratingColors = ['A' => 'bg-green-500', 'B' => 'bg-lime-500', 'C' => 'bg-yellow-500', 'D' => 'bg-orange-500', 'E' => 'bg-red-500'];
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold text-white {{ $ratingColors[$consulta->rating] ?? 'bg-gray-500' }}">
                Rating: {{ $consulta->rating }} | Score: {{ $consulta->score }}
            </span>
        @endif

        @if($consulta->decisao_humana)
            @php
                $decColors = ['aprovado' => 'bg-green-600', 'negado' => 'bg-red-600', 'condicionado' => 'bg-amber-600'];
            @endphp
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold text-white {{ $decColors[$consulta->decisao_humana] ?? 'bg-gray-500' }}">
                Decisão: {{ ucfirst($consulta->decisao_humana) }}
            </span>
        @endif
    </div>

    {{-- BOTÕES DE AÇÃO --}}
    <div class="mb-6 flex flex-wrap gap-3">

        {{-- Coletar Dados Internos (se rascunho e sem snapshot) --}}
        @if(!$consulta->snapshot_interno && in_array($consulta->status, ['rascunho']))
            <form method="POST" action="{{ route('siric.coletar', $consulta->id) }}">
                @csrf
                <button type="submit"
                    class="btn-mayer shadow">
                    📥 Coletar Dados Internos
                </button>
            </form>
        @endif

        {{-- RODAR ANÁLISE DE CRÉDITO (IA) — AÇÃO PRINCIPAL --}}
        @if($consulta->snapshot_interno && in_array($consulta->status, ['rascunho', 'coletado', 'erro']))
            <form method="POST" action="{{ route('siric.analisar', $consulta->id) }}"
                  onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerHTML='⏳ Analisando... (pode levar até 2 min)';">
                @csrf
                <button type="submit"
                    class="inline-flex items-center px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-bold rounded-lg shadow-lg transition">
                    🤖 Rodar Análise de Crédito (IA)
                </button>
            </form>
        @endif

        {{-- Info: Serasa consultado automaticamente? --}}
        @if(($consulta->actions_ia['gate_decision']['serasa_consultado'] ?? false))
            <span class="inline-flex items-center px-3 py-2 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-sm rounded-lg">
                ✅ Serasa consultado (automático pela IA)
            </span>
        @endif
    </div>

    {{-- TABS --}}
    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
        <nav class="flex gap-1 -mb-px overflow-x-auto">
            @php
                $tabs = ['dados' => '📋 Dados', 'metricas' => '📊 Métricas'];
                if ($consulta->actions_ia) $tabs['analise'] = '🤖 Análise IA';
                $tabs['decisao'] = '⚖️ Decisão';
            @endphp
            @foreach($tabs as $key => $label)
                <button @click="tab = '{{ $key }}'"
                        :class="tab === '{{ $key }}' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                        class="px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap transition">
                    {{ $label }}
                </button>
            @endforeach
        </nav>
    </div>

    {{-- TAB: DADOS --}}
    <div x-show="tab === 'dados'">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">Dados da Solicitação</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">CPF/CNPJ:</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $consulta->cpf_cnpj }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Nome:</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $consulta->nome ?? '—' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Valor Total:</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">R$ {{ number_format($consulta->valor_total ?? 0, 2, ',', '.') }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Parcelas Desejadas:</span>
                    @php $parcDesejadas = max($consulta->parcelas_desejadas ?? 1, 1); @endphp
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $parcDesejadas }}x de R$ {{ number_format(($consulta->valor_total ?? 0) / $parcDesejadas, 2, ',', '.') }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Autorização Externa:</span>
                    <p class="font-medium {{ $consulta->autorizou_consultas_externas ? 'text-green-600' : 'text-red-600' }}">
                        {{ $consulta->autorizou_consultas_externas ? '✅ Sim' : '❌ Não' }}
                    </p>
                </div>
                @if($consulta->renda_declarada)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Renda Declarada:</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">R$ {{ number_format($consulta->renda_declarada, 2, ',', '.') }}</p>
                </div>
                @endif
                @if($consulta->telefone)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Telefone:</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $consulta->telefone }}</p>
                </div>
                @endif
                @if($consulta->email)
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Email:</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $consulta->email }}</p>
                </div>
                @endif
                @if($consulta->observacoes)
                <div class="md:col-span-2 lg:col-span-3">
                    <span class="text-gray-500 dark:text-gray-400">Observações:</span>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $consulta->observacoes }}</p>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- TAB: MÉTRICAS INTERNAS --}}
    <div x-show="tab === 'metricas'" x-cloak>
        @if($consulta->snapshot_interno)
            @php $snap = is_array($consulta->snapshot_interno) ? $consulta->snapshot_interno : json_decode($consulta->snapshot_interno, true); @endphp

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                @include('siric.partials._metric-card', ['label' => 'Clientes Encontrados', 'valor' => $snap['clientes_encontrados'] ?? 0, 'icon' => '👥', 'cor' => 'blue'])
                @include('siric.partials._metric-card', ['label' => 'Total Pago', 'valor' => 'R$ ' . number_format($snap['contas_receber']['total_pago'] ?? 0, 2, ',', '.'), 'icon' => '💵', 'cor' => 'green'])
                @include('siric.partials._metric-card', ['label' => 'Saldo Aberto', 'valor' => 'R$ ' . number_format($snap['contas_receber']['saldo_aberto'] ?? 0, 2, ',', '.'), 'icon' => '📎', 'cor' => ($snap['contas_receber']['saldo_aberto'] ?? 0) > 0 ? 'orange' : 'green'])
                @include('siric.partials._metric-card', ['label' => 'Processos Ativos', 'valor' => $snap['processos']['total_ativos'] ?? 0, 'icon' => '📁', 'cor' => 'blue'])
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                @include('siric.partials._metric-card', ['label' => 'Qtd Atrasos', 'valor' => $snap['contas_receber']['qtd_atrasos'] ?? 0, 'icon' => '⚠️', 'cor' => ($snap['contas_receber']['qtd_atrasos'] ?? 0) > 0 ? 'red' : 'green'])
                @include('siric.partials._metric-card', ['label' => 'Max Dias Atraso', 'valor' => ($snap['contas_receber']['max_dias_atraso'] ?? 0) . ' dias', 'icon' => '📅', 'cor' => ($snap['contas_receber']['max_dias_atraso'] ?? 0) > 30 ? 'red' : 'green'])
                @include('siric.partials._metric-card', ['label' => 'Ticket Médio', 'valor' => 'R$ ' . number_format($snap['metricas']['ticket_medio'] ?? 0, 2, ',', '.'), 'icon' => '📈', 'cor' => 'blue'])
                @include('siric.partials._metric-card', ['label' => 'Recorrência', 'valor' => ($snap['metricas']['recorrencia_meses'] ?? 0) . ' meses', 'icon' => '🔄', 'cor' => 'blue'])
            </div>

            <details class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
                <summary class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-400">
                    📄 Ver snapshot completo (JSON)
                </summary>
                <pre class="mt-3 text-xs bg-gray-50 dark:bg-gray-900 p-3 rounded-lg overflow-x-auto max-h-96">{{ json_encode($snap, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </details>
        @else
            <div class="text-center py-12 text-gray-400">
                <p class="text-lg">📭 Dados internos ainda não coletados</p>
                <p class="text-sm mt-2">Clique em "Coletar Dados Internos" para prosseguir.</p>
            </div>
        @endif
    </div>

    {{-- TAB: ANÁLISE IA --}}
    @if($consulta->actions_ia)
    <div x-show="tab === 'analise'" x-cloak>
        @php
            $ia = is_array($consulta->actions_ia) ? $consulta->actions_ia : json_decode($consulta->actions_ia, true);
            $gate = $ia['gate_decision'] ?? null;
            $rel = $ia['relatorio'] ?? null;
        @endphp

        {{-- Gate Decision --}}
        @if($gate)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3">🚦 Gate Decision (Triagem)</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mb-4">
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Gate Score:</span>
                    <p class="text-2xl font-bold {{ ($gate['gate_score'] ?? 0) >= 30 ? 'text-red-600' : (($gate['gate_score'] ?? 0) >= 15 ? 'text-yellow-600' : 'text-green-600') }}">
                        {{ $gate['gate_score'] ?? '—' }}
                    </p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Serasa Necessário:</span>
                    <p class="font-medium">{{ ($gate['need_serasa'] ?? false) ? '✅ Sim' : '❌ Não' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Serasa Consultado:</span>
                    <p class="font-medium">{{ ($gate['serasa_consultado'] ?? false) ? '✅ Sim' : '❌ Não' }}</p>
                </div>
                <div>
                    <span class="text-gray-500 dark:text-gray-400">Web Intel:</span>
                    <p class="font-medium">{{ ($gate['need_web'] ?? false) ? '✅ Sim' : '❌ Não' }}</p>
                </div>
            </div>

            @if($gate['justificativa'] ?? null)
                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2"><strong>Justificativa:</strong> {{ $gate['justificativa'] }}</p>
            @endif

            @if(!empty($gate['alertas']))
                <div class="mt-2">
                    <span class="text-sm font-medium text-red-600">⚠️ Alertas:</span>
                    <ul class="mt-1 text-sm text-red-600 dark:text-red-400 list-disc list-inside">
                        @foreach($gate['alertas'] as $alerta)
                            <li>{{ $alerta }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($gate['gate_score_breakdown'] ?? null)
                <details class="mt-3">
                    <summary class="cursor-pointer text-sm text-gray-500">Ver breakdown do gate_score</summary>
                    <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                        @foreach($gate['gate_score_breakdown'] as $fator => $pontos)
                            <div class="flex justify-between px-2 py-1 rounded {{ $pontos > 0 ? 'bg-red-50 dark:bg-red-900/20' : ($pontos < 0 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-50 dark:bg-gray-800') }}">
                                <span>{{ str_replace('_', ' ', ucfirst($fator)) }}</span>
                                <span class="font-mono font-bold {{ $pontos > 0 ? 'text-red-600' : ($pontos < 0 ? 'text-green-600' : 'text-gray-400') }}">
                                    {{ $pontos > 0 ? '+' : '' }}{{ $pontos }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
        @endif

        {{-- Relatório Final --}}
        @if($rel)
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 mb-6">
            <h3 class="text-base font-semibold text-gray-800 dark:text-gray-100 mb-3">📄 Relatório Final</h3>

            <div class="flex items-center gap-4 mb-4">
                @php
                    $ratingColors = ['A' => 'bg-green-500', 'B' => 'bg-lime-500', 'C' => 'bg-yellow-500', 'D' => 'bg-orange-500', 'E' => 'bg-red-500'];
                    $ratingLabels = ['A' => 'Excelente', 'B' => 'Bom', 'C' => 'Regular', 'D' => 'Ruim', 'E' => 'Crítico'];
                @endphp
                <div class="w-16 h-16 rounded-full flex items-center justify-center text-white text-2xl font-black {{ $ratingColors[$rel['rating'] ?? ''] ?? 'bg-gray-400' }}">
                    {{ $rel['rating'] ?? '?' }}
                </div>
                <div>
                    <p class="text-lg font-bold text-gray-800 dark:text-gray-100">
                        {{ $ratingLabels[$rel['rating'] ?? ''] ?? 'Indefinido' }} — Score {{ $rel['score_final'] ?? 0 }}/100
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Recomendação: <strong>{{ ucfirst($rel['recomendacao'] ?? '—') }}</strong>
                    </p>
                </div>
            </div>

            @if($rel['resumo_executivo'] ?? null)
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-4 text-sm text-gray-700 dark:text-gray-300">
                    {{ $rel['resumo_executivo'] }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                @if(!empty($rel['fatores_positivos']))
                <div>
                    <h4 class="text-sm font-medium text-green-700 dark:text-green-400 mb-2">✅ Fatores Positivos</h4>
                    <ul class="text-sm space-y-1 text-gray-600 dark:text-gray-400">
                        @foreach($rel['fatores_positivos'] as $f)
                            <li>• {{ $f }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
                @if(!empty($rel['fatores_negativos']))
                <div>
                    <h4 class="text-sm font-medium text-red-700 dark:text-red-400 mb-2">❌ Fatores Negativos</h4>
                    <ul class="text-sm space-y-1 text-gray-600 dark:text-gray-400">
                        @foreach($rel['fatores_negativos'] as $f)
                            <li>• {{ $f }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif
            </div>

            @if(!empty($rel['condicoes_sugeridas']))
                <div class="mb-4">
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">📋 Condições Sugeridas</h4>
                    <ul class="text-sm space-y-1 text-gray-600 dark:text-gray-400">
                        @foreach($rel['condicoes_sugeridas'] as $c)
                            <li>• {{ $c }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(($rel['parcelas_max_sugeridas'] ?? null) || ($rel['comprometimento_max_sugerido'] ?? null))
                <div class="grid grid-cols-2 gap-4 text-sm">
                    @if($rel['parcelas_max_sugeridas'] ?? null)
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                        <span class="text-gray-500 dark:text-gray-400">Parcelas máx sugeridas:</span>
                        <p class="text-xl font-bold text-blue-600">{{ $rel['parcelas_max_sugeridas'] }}x</p>
                    </div>
                    @endif
                    @if($rel['comprometimento_max_sugerido'] ?? null)
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-3">
                        <span class="text-gray-500 dark:text-gray-400">Comprometimento máx:</span>
                        <p class="text-xl font-bold text-blue-600">{{ $rel['comprometimento_max_sugerido'] }}%</p>
                    </div>
                    @endif
                </div>
            @endif

            @if($rel['analise_detalhada'] ?? null)
                <details class="mt-4">
                    <summary class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-400">
                        📖 Ver análise detalhada completa
                    </summary>
                    <div class="mt-3 space-y-3 text-sm text-gray-600 dark:text-gray-400">
                        @foreach($rel['analise_detalhada'] as $area => $texto)
                            <div>
                                <strong class="text-gray-700 dark:text-gray-300">{{ str_replace('_', ' ', ucfirst($area)) }}:</strong>
                                <p class="mt-1">{{ $texto }}</p>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif
        </div>
        @endif

        <details class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4">
            <summary class="cursor-pointer text-sm font-medium text-gray-600 dark:text-gray-400">
                🔧 Ver JSON completo da IA (debug)
            </summary>
            <pre class="mt-3 text-xs bg-gray-50 dark:bg-gray-900 p-3 rounded-lg overflow-x-auto max-h-96">{{ json_encode($ia, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </details>
    </div>
    @endif

    {{-- TAB: DECISÃO HUMANA --}}
    <div x-show="tab === 'decisao'" x-cloak>
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4">⚖️ Decisão Humana</h2>

            @if($consulta->decisao_humana)
                <div class="space-y-3 text-sm">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-500 dark:text-gray-400">Decisão:</span>
                        <span class="font-bold {{ $consulta->decisao_humana === 'aprovado' ? 'text-green-600' : ($consulta->decisao_humana === 'negado' ? 'text-red-600' : 'text-amber-600') }}">
                            {{ ucfirst($consulta->decisao_humana) }}
                        </span>
                    </div>
                    @if($consulta->nota_decisao)
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Justificativa:</span>
                        <p class="mt-1 text-gray-700 dark:text-gray-300">{{ $consulta->nota_decisao }}</p>
                    </div>
                    @endif
                    <div>
                        <span class="text-gray-500 dark:text-gray-400">Decidido por:</span>
                        <span class="text-gray-700 dark:text-gray-300">{{ $consulta->decisaoUser->name ?? 'Usuário #' . $consulta->decisao_user_id }}</span>
                    </div>
                </div>
            @elseif($consulta->status === 'analisado')
                <form method="POST" action="{{ route('siric.decisao', $consulta->id) }}" class="space-y-4">
                    @csrf
                    <div class="flex gap-4">
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="decisao_humana" value="aprovado" class="text-green-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">✅ Aprovado</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="decisao_humana" value="condicionado" class="text-yellow-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">⚠️ Condicionado</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="decisao_humana" value="negado" class="text-red-600">
                            <span class="text-sm text-gray-700 dark:text-gray-300">❌ Negado</span>
                        </label>
                    </div>
                    <textarea name="nota_decisao" rows="3" placeholder="Justificativa da decisão (opcional)..."
                              class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 text-sm"></textarea>
                    <button type="submit"
                        class="px-4 py-2 bg-brand hover-bg-brand-dark text-white text-sm font-medium rounded-lg shadow transition">
                        Registrar Decisão
                    </button>
                </form>
            @else
                <p class="text-gray-400 text-sm">A análise de IA precisa ser concluída antes de registrar uma decisão.</p>
            @endif
        </div>
    </div>

    {{-- Rodapé --}}
    <div class="mt-6 text-xs text-gray-400 dark:text-gray-500">
        Criado em {{ $consulta->created_at->format('d/m/Y H:i') }}
        por {{ $consulta->user->name ?? 'Sistema' }}
        | Última atualização: {{ $consulta->updated_at->format('d/m/Y H:i') }}
    </div>
</div>
@endsection
