@extends('layouts.app')
@section('title', 'CRM - ' . $account->name)

@section('content')
@php
    $cli = $djContext['cliente'] ?? null;
    $hasDj = $djContext['available'];
    $lcColors = [
        'onboarding' => 'bg-blue-100 text-blue-700',
        'ativo'      => 'bg-green-100 text-green-700',
        'adormecido' => 'bg-yellow-100 text-yellow-700',
        'arquivado'  => 'bg-gray-200 text-gray-600',
        'risco'      => 'bg-red-100 text-red-700',
    ];
    $processosAtivos = collect($djContext['processos'])->filter(fn($p) => in_array($p->status ?? '', ['Ativo','Em andamento','Em Andamento']))->count();
    $contasAbertas = collect($djContext['contas_receber'])->filter(fn($c) => !in_array($c->status ?? '', ['Concluído', 'Concluido', 'Excluido', 'Excluído']))->values();
    $contasVencidas = $contasAbertas->filter(fn($c) => $c->data_vencimento && $c->data_vencimento < date('Y-m-d'));
    $activities = $account->activities()->with('createdBy')->orderByDesc('created_at')->get();
    $lastInsight = \App\Models\CrmAiInsight::where('account_id', $account->id)->where('status', 'active')->where('tipo', 'account_action')->orderByDesc('created_at')->first();
@endphp

<div class="max-w-full mx-auto px-6 py-6">
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.carteira') }}" class="hover:text-[#385776]">Carteira</a>
        <span>›</span>
        <span class="text-gray-700 font-medium">{{ $account->name }}</span>
    </div>

    {{-- HEADER CARD --}}
    <div class="bg-white rounded-xl shadow-sm border mb-6 overflow-hidden">
        <div class="bg-gradient-to-r from-[#1B334A] to-[#385776] px-6 py-4">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-bold" style="color:#ffffff !important">{{ $account->name }}</h1>
                    @if($cli && $cli->nome_fantasia)
                        <p class="text-blue-200 text-sm mt-0.5">{{ $cli->nome_fantasia }}</p>
                    @endif
                    <div class="flex flex-wrap items-center gap-2 mt-2">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $account->kind === 'client' ? 'bg-green-400/20 text-green-200' : 'bg-blue-400/20 text-blue-200' }}">
                            {{ $account->kind === 'client' ? 'Cliente' : 'Prospect' }}
                        </span>
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/20 text-white">
                            {{ ucfirst($account->lifecycle ?? 'onboarding') }}
                        </span>
                        @if($cli && $cli->tipo)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/80">
                                {{ $cli->tipo === 'PF' ? 'Pessoa Física' : 'Pessoa Jurídica' }}
                            </span>
                        @endif
                        @if($cli && $cli->status_pessoa && !(isset($segmentation) && $segmentation))
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-white/10 text-white/80">
                                {{ $cli->status_pessoa }}
                            </span>
                        @endif
                        @if($account->health_score !== null)
                            @php $hs = $account->health_score; @endphp
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $hs >= 70 ? 'bg-green-400/20 text-green-200' : ($hs >= 40 ? 'bg-yellow-400/20 text-yellow-200' : 'bg-red-400/20 text-red-200') }}">
                                Saúde: {{ $hs }}
                            </span>
                        @endif
                        @if(isset($segmentation) && $segmentation)
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-400/20 text-purple-200" title="{{ $segmentation['summary'] ?? '' }}">
                                {{ $segmentation['segment'] }}
                            </span>
                        @endif
                    </div>
                </div>
                <div class="flex gap-2">
                    @if(auth()->user()->isAdmin())
                        <button onclick="openEditModal()"
                                class="px-3 py-2 bg-white/20 text-white rounded-lg text-sm hover:bg-white/30 backdrop-blur flex items-center gap-1.5" title="Editar dados da conta">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            Editar
                        </button>
                    @endif
                    @if($commContext['has_wa'])
                        <a href="{{ route('nexo.atendimento') }}?conversation={{ $commContext['whatsapp']->id }}"
                           class="px-3 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                            NEXO
                        </a>
                    @elseif($djContext['cliente'] && ($djContext['cliente']->celular ?? null))
                        @php $celNorm = preg_replace('/\D/', '', $djContext['cliente']->celular); if(strlen($celNorm)>=10 && !str_starts_with($celNorm,'55')) $celNorm='55'.$celNorm; @endphp
                        <a href="{{ route('nexo.atendimento') }}?phone={{ $celNorm }}&name={{ urlencode($djContext['cliente']->nome ?? '') }}"
                           class="px-3 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                            Iniciar WhatsApp
                        </a>
                    @endif
                    <button onclick="document.getElementById('modal-new-opp').classList.remove('hidden')"
                            class="px-3 py-2 bg-white/20 text-white rounded-lg text-sm hover:bg-white/30 backdrop-blur">
                        + Oportunidade
                    </button>
                </div>
            </div>
        </div>
        {{-- Dados pessoais --}}
        <div class="px-6 py-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-x-6 gap-y-3 text-sm">
            <div><span class="text-gray-400 text-xs block">CPF/CNPJ</span><span class="text-gray-700 font-medium">{{ $cli->cpf_cnpj ?? $account->doc_digits ?? '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Email</span><span class="text-gray-700">{{ $cli->email ?? $account->email ?? '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Celular</span><span class="text-gray-700">{{ $cli->celular ?? '—' }}</span></div>
            @php
                $cidadeUF = $account->endereco_cidade
                    ? trim($account->endereco_cidade . ($account->endereco_estado ? '/' . $account->endereco_estado : ''))
                    : ($cli ? trim(($cli->endereco_cidade ?? '') . ($cli->endereco_estado ? '/' . $cli->endereco_estado : ''), '/') : null);
            @endphp
            <div><span class="text-gray-400 text-xs block">Cidade/UF</span><span class="text-gray-700">{{ $cidadeUF ?: '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Profissão{{ $account->profissao ? ' ✎' : '' }}</span><span class="text-gray-700">{{ $account->profissao ?? $cli->profissao ?? '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Nascimento</span><span class="text-gray-700">{{ $account->data_nascimento ? $account->data_nascimento->format('d/m/Y') : ($cli && $cli->data_nascimento ? \Carbon\Carbon::parse($cli->data_nascimento)->format('d/m/Y') : '—') }}</span></div>
            <div><span class="text-gray-400 text-xs block">Responsável</span><span class="text-gray-700 font-medium">{{ $account->owner?->name ?? $cli->proprietario_nome ?? '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Último Contato</span><span class="text-gray-700">{{ $account->last_touch_at ? \Carbon\Carbon::parse($account->last_touch_at)->format('d/m/Y') : '—' }}</span></div>
        </div>
    </div>

    {{-- BANNER IA — Ação pendente --}}
    @if($lastInsight)
    <div class="mb-6 bg-gradient-to-r from-amber-50 via-orange-50 to-yellow-50 border border-amber-300 rounded-xl p-5 shadow-sm" id="ai-action-banner">
        <div class="flex items-center justify-between flex-wrap gap-4">
            <div class="flex items-start gap-3 flex-1 min-w-0">
                <span class="flex-shrink-0 w-10 h-10 bg-amber-400 text-white rounded-full flex items-center justify-center text-lg font-bold shadow-sm">✦</span>
                <div class="min-w-0">
                    <p class="text-sm font-bold text-amber-900">{{ $lastInsight->titulo }}</p>
                    @if($lastInsight->action_suggested)
                        <p class="text-sm text-amber-700 mt-1">→ {{ $lastInsight->action_suggested }}</p>
                    @endif
                    <p class="text-[10px] text-amber-500 mt-1">Gerado em {{ $lastInsight->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</p>
                </div>
            </div>
            <button onclick="document.getElementById('ai-insight-box').scrollIntoView({behavior:'smooth', block:'center'})"
                    class="px-5 py-2.5 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-semibold shadow transition-colors whitespace-nowrap flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/></svg>
                Executar ação recomendada
            </button>
        </div>
    </div>
    @endif

    {{-- BANNER NEXO — Notificações WhatsApp pendentes --}}
    @if($nexoPendentes->isNotEmpty())
    <div class="mb-6" id="nexo-pendentes-banner">
        <div class="bg-gradient-to-r from-green-50 via-emerald-50 to-teal-50 border border-green-300 rounded-xl shadow-sm overflow-hidden">
            {{-- Header do banner --}}
            <div class="flex items-center justify-between px-5 py-3 border-b border-green-200 bg-green-100/50">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    </svg>
                    <span class="text-sm font-semibold text-green-800">
                        {{ $nexoPendentes->count() === 1 ? '1 notificação WhatsApp aguardando decisão' : $nexoPendentes->count() . ' notificações WhatsApp aguardando decisão' }}
                    </span>
                </div>
                <a href="{{ url('/nexo/notificacoes') }}" class="text-xs text-green-700 underline hover:text-green-900">
                    Ver todas no painel Nexo
                </a>
            </div>

            {{-- Cards de cada notificação pendente --}}
            <div class="divide-y divide-green-100">
            @foreach($nexoPendentes as $np)
            @php $npVars = json_decode($np->template_vars, true); @endphp
            <div class="px-5 py-4" id="crm-nexo-card-{{ $np->id }}">
                <div class="flex items-start gap-3">
                    {{-- Ícone e meta --}}
                    <div class="flex-shrink-0 mt-0.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                            {{ $np->tipo === 'audiencia' ? 'bg-purple-100 text-purple-700' : ($np->tipo === 'andamento' ? 'bg-blue-100 text-blue-700' : 'bg-orange-100 text-orange-700') }}">
                            {{ $np->tipo === 'audiencia' ? 'Audiência' : ($np->tipo === 'andamento' ? 'Andamento' : 'OS') }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        {{-- Preview do andamento --}}
                        <div class="text-sm text-gray-700 leading-relaxed mb-1">
                            {{ $npVars[2]['text'] ?? ($np->error_message ?? 'Movimentação processual') }}
                        </div>
                        <div class="text-xs text-gray-400 mb-3">
                            Processo: <span class="font-mono">{{ $np->processo_pasta ?? '—' }}</span>
                            · Detectado em {{ \Carbon\Carbon::parse($np->created_at)->format('d/m H:i') }}
                        </div>

                        {{-- Área de ação: textarea editável + botões --}}
                        <div id="crm-nexo-action-{{ $np->id }}">
                            <textarea id="crm-nexo-msg-{{ $np->id }}" rows="2" maxlength="300"
                                class="w-full text-sm border border-green-300 rounded-lg px-3 py-2 focus:border-green-500 focus:ring-1 focus:ring-green-500 bg-white mb-2"
                                placeholder="Edite a mensagem que será enviada ao cliente...">{{ $npVars[2]['text'] ?? '' }}</textarea>
                            <div class="flex items-center gap-2">
                                <button onclick="crmNexoEnviar({{ $np->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-green-600 hover:bg-green-700 text-white text-xs font-medium rounded-lg transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                                    Enviar WhatsApp ao cliente
                                </button>
                                <button onclick="crmNexoDescartar({{ $np->id }})"
                                    class="px-3 py-1.5 border border-gray-300 text-gray-600 hover:bg-gray-50 text-xs font-medium rounded-lg transition-colors">
                                    Não notificar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
            </div>
        </div>
    </div>
    @endif

    {{-- KPI CARDS --}}
    @if($hasDj)
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4 {{ $djContext['receita_total'] == 0 ? 'opacity-50' : '' }}">
            <p class="text-xs text-gray-400 mb-1">{{ $djContext['receita_total'] == 0 ? '⚠ ' : '' }}Receita Total</p>
            <p class="text-lg font-bold text-[#1B334A]">R$ {{ number_format($djContext['receita_total'], 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 {{ $processosAtivos == 0 ? 'opacity-50' : '' }}">
            <p class="text-xs text-gray-400 mb-1">{{ $processosAtivos == 0 ? '⚠ ' : '' }}Processos Ativos</p>
            <p class="text-lg font-bold text-[#1B334A]">{{ $processosAtivos }}</p>
            <p class="text-xs text-gray-400">de {{ count($djContext['processos']) }} total</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 {{ count($djContext['contratos']) == 0 ? 'opacity-50' : '' }}">
            <p class="text-xs text-gray-400 mb-1">{{ count($djContext['contratos']) == 0 ? '⚠ ' : '' }}Contratos</p>
            <p class="text-lg font-bold text-[#1B334A]">{{ count($djContext['contratos']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 {{ $contasAbertas->sum('valor') == 0 ? 'opacity-50' : '' }}">
            <p class="text-xs text-gray-400 mb-1">{{ $contasAbertas->sum('valor') == 0 ? '⚠ ' : '' }}A Receber</p>
            <p class="text-lg font-bold text-[#1B334A]">R$ {{ number_format($contasAbertas->sum('valor'), 2, ',', '.') }}</p>
            <p class="text-xs text-gray-400">{{ $contasAbertas->count() }} título(s)</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Vencidos</p>
            <p class="text-lg font-bold {{ $contasVencidas->count() > 0 ? 'text-red-600' : 'text-green-600' }}">R$ {{ number_format($contasVencidas->sum('valor'), 2, ',', '.') }}</p>
            <p class="text-xs {{ $contasVencidas->count() > 0 ? 'text-red-400' : 'text-gray-400' }}">{{ $contasVencidas->count() }} título(s)</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4 {{ !$djContext['ultimo_movimento'] ? 'opacity-50' : '' }}">
            <p class="text-xs text-gray-400 mb-1">{{ !$djContext['ultimo_movimento'] ? '⚠ ' : '' }}Último Movimento</p>
            @if($djContext['ultimo_movimento'])
                <p class="text-lg font-bold text-[#1B334A]">{{ \Carbon\Carbon::parse($djContext['ultimo_movimento']->data)->format('d/m/Y') }}</p>
                <p class="text-xs text-gray-400">R$ {{ number_format(abs($djContext['ultimo_movimento']->valor), 2, ',', '.') }}</p>
            @else
                <p class="text-lg font-bold text-gray-300">—</p>
            @endif
        </div>
    </div>
    @endif

    {{-- ================================================================== --}}
    {{-- ABAS                                                              --}}
    {{-- ================================================================== --}}
    <div class="mb-6">
        <div class="border-b border-gray-200">
            <nav class="flex gap-0 -mb-px" id="tab-nav">
                <button onclick="switchTab('resumo')" data-tab="resumo" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-[#385776] text-[#385776]">
                    📋 Resumo
                </button>
                <button onclick="switchTab('atividades')" data-tab="atividades" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    📋 Registro de Interações <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $activities->count() }}</span>
                </button>
                <button onclick="switchTab('processos')" data-tab="processos" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    ⚖️ Processos <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ count($djContext['processos']) }}</span>
                </button>
                <button onclick="switchTab('proc-adm')" data-tab="proc-adm" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    🏛️ Proc. Adm.
                    @if($adminProcesses->count() > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs {{ $adminProcesses->whereNotIn('status',['concluido','cancelado'])->count() > 0 ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-600' }}">{{ $adminProcesses->count() }}</span>
                    @endif
                </button>
                <button onclick="switchTab('comunicacao')" data-tab="comunicacao" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    💬 Comunicação
                </button>
                <button onclick="switchTab('financeiro')" data-tab="financeiro" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    💰 Financeiro
                    @if($contasVencidas->count() > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs bg-red-100 text-red-600">{{ $contasVencidas->count() }}</span>
                    @endif
                </button>
                <button onclick="switchTab('documentos')" data-tab="documentos" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    📎 Documentos <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $documents->count() }}</span>
                </button>
                <button onclick="switchTab('solicitacoes')" data-tab="solicitacoes" class="tab-btn px-5 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                    📝 Solicitações
                    @if($serviceRequests->where('status','!=','concluido')->where('status','!=','cancelado')->count() > 0)
                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-xs bg-orange-100 text-orange-600">{{ $serviceRequests->where('status','!=','concluido')->where('status','!=','cancelado')->count() }}</span>
                    @endif
                </button>
            </nav>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- TAB: RESUMO                                                       --}}
    {{-- ================================================================== --}}
    <div id="tab-resumo" class="tab-content">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                {{-- Alerta de Tickets/Solicitações abertas --}}
                @php
                    $ticketsAbertos = collect($commContext['tickets'])->filter(fn($t) => !in_array($t->status ?? ($t['status'] ?? ''), ['concluido', 'cancelado']));
                    $ticketsWa = $ticketsAbertos->filter(fn($t) => ($t->origem ?? ($t['origem'] ?? '')) === 'autoatendimento');
                @endphp
                @if($ticketsAbertos->count() > 0)
                <div class="bg-orange-50 border border-orange-300 rounded-lg p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex-shrink-0 w-10 h-10 bg-orange-400 text-white rounded-full flex items-center justify-center text-lg shadow-sm">🎫</span>
                        <div class="flex-1">
                            <p class="text-sm font-bold text-orange-900">
                                {{ $ticketsAbertos->count() }} {{ $ticketsAbertos->count() === 1 ? 'solicitação aberta' : 'solicitações abertas' }}
                                @if($ticketsWa->count() > 0)
                                    <span class="text-xs font-normal text-orange-700">({{ $ticketsWa->count() }} via WhatsApp)</span>
                                @endif
                            </p>
                            <div class="flex flex-wrap gap-2 mt-2">
                                @foreach($ticketsAbertos->take(3) as $tkt)
                                @php $tktObj = is_array($tkt) ? (object) $tkt : $tkt; @endphp
                                <a href="{{ route('crm.service-requests.show', $tktObj->id) }}" class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-white border border-orange-200 rounded-lg text-xs hover:bg-orange-100 transition">
                                    @if(($tktObj->origem ?? '') === 'autoatendimento')
                                        <svg class="w-3.5 h-3.5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                                    @endif
                                    <span class="font-medium text-orange-800">#{{ $tktObj->protocolo ?? $tktObj->id }}</span>
                                    <span class="text-orange-600 truncate max-w-[180px]">{{ $tktObj->subject ?? 'Sem assunto' }}</span>
                                    <span class="px-1.5 py-0.5 rounded text-[10px] {{ \App\Models\Crm\CrmServiceRequest::statusBadge($tktObj->status ?? 'aberto') }}">{{ \App\Models\Crm\CrmServiceRequest::statusLabel($tktObj->status ?? 'aberto') }}</span>
                                </a>
                                @endforeach
                                @if($ticketsAbertos->count() > 3)
                                    <button onclick="switchTab('solicitacoes')" class="text-xs text-orange-700 font-medium hover:underline">+{{ $ticketsAbertos->count() - 3 }} mais</button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Diagnóstico + Ação (Segmentação + IA unificados) --}}
                @if(isset($segmentation) && $segmentation)
                <div class="bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-200 rounded-lg overflow-hidden">
                    <div class="px-4 py-3 bg-purple-100/60 border-b border-purple-200 flex items-center gap-2">
                        <span class="text-purple-700 font-semibold text-sm">🤖 Diagnóstico + Ação</span>
                        <span class="px-2 py-0.5 rounded-full text-xs bg-purple-200 text-purple-800">{{ $segmentation['segment'] }}</span>
                    </div>
                    <div class="px-4 py-3 space-y-3">
                        <p class="text-sm text-purple-800">{{ $segmentation['summary'] }}</p>
                        @if($lastInsight)
                            <div class="border-t border-purple-200 pt-3">
                                <p class="text-sm font-semibold text-[#1B334A]">{{ $lastInsight->titulo }}</p>
                                <p class="text-sm text-gray-700 mt-1 leading-relaxed">{{ $lastInsight->insight_text }}</p>
                                @if($lastInsight->action_suggested)
                                    <p class="text-xs text-[#385776] font-medium mt-2">→ {{ $lastInsight->action_suggested }}</p>
                                @endif
                            </div>
                        @else
                            <div class="border-t border-purple-200 pt-3 text-center">
                                <button onclick="gerarSugestaoIA()" class="px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-[#385776] hover:bg-[#1B334A]">
                                    🤖 Gerar recomendação
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Comunicação --}}
                @if($commContext['has_wa'] || $commContext['has_tickets'])
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Comunicação</h2>
                    @if($commContext['has_wa'])
                    <div class="mb-4 p-3 rounded-lg bg-green-50 border border-green-200">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <svg class="w-5 h-5 text-green-600" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                                <div>
                                    <span class="font-medium text-green-800">WhatsApp</span>
                                    <span class="text-xs text-green-600 ml-2">{{ $commContext['whatsapp']->phone }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="text-right text-xs">
                                    <span class="text-gray-500">Última msg:</span>
                                    <span class="font-medium text-gray-700">{{ $commContext['whatsapp']->last_message_at ? \Carbon\Carbon::parse($commContext['whatsapp']->last_message_at)->format('d/m/Y H:i') : '—' }}</span>
                                </div>
                                <a href="{{ route('nexo.atendimento') }}?conversation={{ $commContext['whatsapp']->id }}" class="px-2.5 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">Abrir NEXO</a>
                            </div>
                        </div>
                        <div class="flex gap-4 mt-2 text-xs text-green-700">
                            <span>Status: <strong>{{ $commContext['whatsapp']->status === 'open' ? 'Aberta' : 'Fechada' }}</strong></span>
                            <span>Bot: <strong>{{ $commContext['whatsapp']->bot_ativo ? 'Ativo' : 'Inativo' }}</strong></span>
                        </div>
                    </div>
                    @endif
                    @if($commContext['has_tickets'])
                    <h3 class="text-sm font-medium text-gray-600 mb-2">Solicitações CRM ({{ count($commContext['tickets']) }})</h3>
                    <div class="space-y-2">
                        @foreach($commContext['tickets'] as $tk)
                        <div class="flex items-center justify-between border rounded-lg p-2.5 hover:bg-gray-50 text-sm">
                            <div><span class="font-medium text-gray-700">{{ $tk->subject ?? 'Sem assunto' }}</span>@if($tk->protocolo ?? null)<span class="text-xs text-gray-400 ml-2">#{{ $tk->protocolo }}</span>@endif</div>
                            <div class="flex items-center gap-2"><span class="px-1.5 py-0.5 rounded text-xs {{ \App\Models\Crm\CrmServiceRequest::statusBadge($tk->status ?? 'aberto') }}">{{ \App\Models\Crm\CrmServiceRequest::statusLabel($tk->status ?? 'aberto') }}</span><span class="text-xs text-gray-400">{{ $tk->created_at ? \Carbon\Carbon::parse($tk->created_at)->format('d/m/Y') : '' }}</span></div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
                @endif

                {{-- Oportunidades --}}
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Oportunidades CRM</h2>
                    @if($account->opportunities->isEmpty())
                        <p class="text-gray-400 text-sm">Nenhuma oportunidade registrada.</p>
                    @else
                        <div class="space-y-3">
                            @foreach($account->opportunities as $opp)
                            <a href="{{ route('crm.opportunities.show', $opp->id) }}" class="block border rounded-lg p-3 hover:bg-gray-50 transition">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <span class="font-medium text-gray-800">{{ $opp->title }}</span>
                                        <div class="flex gap-2 mt-1">
                                            <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: {{ $opp->stage?->color ?? '#eee' }}20; color: {{ $opp->stage?->color ?? '#666' }}">{{ $opp->stage?->name ?? '?' }}</span>
                                            <span class="text-xs px-1.5 py-0.5 rounded {{ $opp->status === 'won' ? 'bg-green-100 text-green-700' : ($opp->status === 'lost' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">{{ ucfirst($opp->status) }}</span>
                                            @if($opp->status === 'won' && (!$account->last_touch_at || \Carbon\Carbon::parse($account->last_touch_at)->lt(now()->subDays(30))))
                                                <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 font-medium">⚠ Sem follow-up registrado</span>
                                            @endif
                                        </div>
                                    </div>
                                    @if($opp->value_estimated)<span class="text-sm font-medium text-gray-700">R$ {{ number_format($opp->value_estimated, 2, ',', '.') }}</span>@endif
                                </div>
                            </a>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Timeline --}}
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Timeline</h2>
                    @if($timeline->isEmpty())
                        <p class="text-gray-400 text-sm">Nenhum evento registrado.</p>
                    @else
                        <div class="divide-y divide-gray-100">
                            @foreach($timeline as $item)
                            @php
                                $subtype = $item['subtype'] ?? '';
                                $isActivity = ($item['type'] === 'activity');

                                if ($isActivity) {
                                    // Atividades (interações humanas)
                                    [$tlIcon, $tlBorder, $tlBadge, $tlLabel] = match($subtype) {
                                        'call'     => ['📞', 'border-blue-500',   'bg-blue-100 text-blue-700',     'Ligação'],
                                        'meeting'  => ['🤝', 'border-purple-500', 'bg-purple-100 text-purple-700', 'Reunião'],
                                        'whatsapp' => ['💬', 'border-green-500',  'bg-green-100 text-green-700',   'WhatsApp'],
                                        'email'    => ['✉️', 'border-indigo-500', 'bg-indigo-100 text-indigo-700', 'E-mail'],
                                        'visit'    => ['🏢', 'border-teal-500',   'bg-teal-100 text-teal-700',     'Visita'],
                                        'note'     => ['📝', 'border-gray-400',   'bg-gray-200 text-gray-600',     'Nota'],
                                        'task'     => ['✅', 'border-orange-500', 'bg-orange-100 text-orange-700', 'Tarefa'],
                                        default    => ['📋', 'border-gray-400',   'bg-gray-200 text-gray-600',     'Atividade'],
                                    };
                                } else {
                                    // Eventos de sistema — cada subtipo com visual próprio
                                    [$tlIcon, $tlBorder, $tlBadge, $tlLabel] = match($subtype) {
                                        'health_score_changed'       => ['💊', 'border-rose-400',    'bg-rose-100 text-rose-700',       'Saúde'],
                                        'account_updated'            => ['✏️', 'border-sky-400',     'bg-sky-100 text-sky-700',          'Atualização'],
                                        'account_archived'           => ['📦', 'border-gray-500',    'bg-gray-200 text-gray-700',        'Arquivamento'],
                                        'account_unarchived'         => ['📤', 'border-emerald-500', 'bg-emerald-100 text-emerald-700',  'Reativação'],
                                        'lead_status_changed'        => ['🏷️', 'border-cyan-400',    'bg-cyan-100 text-cyan-700',        'Status Lead'],
                                        'lead_qualified'             => ['⭐', 'border-amber-500',   'bg-amber-100 text-amber-700',      'Lead Qualificado'],
                                        'opportunity_created'        => ['💼', 'border-violet-500',  'bg-violet-100 text-violet-700',    'Oportunidade'],
                                        'opportunity_imported'       => ['📥', 'border-violet-400',  'bg-violet-100 text-violet-700',    'Importação'],
                                        'opportunity_lost'           => ['❌', 'border-red-400',     'bg-red-100 text-red-700',          'Oportunidade Perdida'],
                                        'stage_changed'              => ['🔀', 'border-blue-400',    'bg-blue-100 text-blue-700',        'Estágio'],
                                        'owner_transferred'          => ['🔄', 'border-indigo-400',  'bg-indigo-100 text-indigo-700',    'Transferência'],
                                        'segment_changed'            => ['🎯', 'border-purple-400',  'bg-purple-100 text-purple-700',    'Segmentação'],
                                        'document_uploaded'          => ['📎', 'border-sky-400',     'bg-sky-100 text-sky-700',          'Documento'],
                                        'document_deleted'           => ['🗑️', 'border-gray-400',    'bg-gray-200 text-gray-600',        'Doc. Removido'],
                                        'service_request_created'    => ['🎫', 'border-orange-500',  'bg-orange-100 text-orange-700',    'Ticket Criado'],
                                        'service_request_updated'    => ['🎫', 'border-orange-400',  'bg-orange-100 text-orange-700',    'Ticket Atualizado'],
                                        'nexo_opened_chat'           => ['💬', 'border-green-500',   'bg-green-100 text-green-700',      'Chat NEXO'],
                                        'prospect_converted'         => ['🎉', 'border-emerald-500', 'bg-emerald-100 text-emerald-700',  'Conversão'],
                                        'account_created_from_lead'  => ['🆕', 'border-blue-500',    'bg-blue-100 text-blue-700',        'Nova Conta'],
                                        default                      => ['🔹', 'border-slate-400',   'bg-slate-100 text-slate-600',      'Evento'],
                                    };
                                }

                                $isHighlight = in_array($subtype, ['service_request_created', 'opportunity_lost', 'account_archived']);
                            @endphp
                            <div class="flex gap-3 text-sm border-l-4 {{ $tlBorder }} pl-3 py-3 {{ $isHighlight ? 'bg-amber-50/50' : '' }}">
                                <span class="flex-shrink-0 w-8 h-8 rounded-full {{ $tlBadge }} flex items-center justify-center text-sm shadow-sm">{{ $tlIcon }}</span>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide {{ $tlBadge }}">{{ $tlLabel }}</span>
                                        @if($isHighlight)
                                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                        @endif
                                    </div>
                                    <p class="text-gray-900 mt-1 {{ $isActivity ? 'font-medium' : '' }}">{{ $item['title'] }}</p>
                                    @if(!empty($item['body']))<p class="text-gray-600 text-xs mt-0.5">{{ \Illuminate\Support\Str::limit($item['body'], 120) }}</p>@endif
                                </div>
                                <div class="text-xs text-gray-500 flex-shrink-0 text-right">
                                    {{ $item['date'] ? (is_string($item['date']) ? \Carbon\Carbon::parse($item['date'])->format('d/m H:i') : $item['date']->format('d/m H:i')) : '' }}
                                    @if($item['user'] ?? null)<br><span class="text-gray-400">{{ $item['user'] }}</span>@endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Painel Direito --}}
            <div class="space-y-6">
                {{-- IA — Sugestão de Ação --}}
                <div class="bg-white rounded-lg shadow-sm border p-5" id="ai-insight-box">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-[#385776]">✦ Sugestão IA</span>
                        </div>
                        <button onclick="gerarSugestaoIA()" id="btn-ai-account" class="px-3 py-1.5 text-xs font-semibold rounded-lg text-white bg-[#385776] hover:bg-[#1B334A] transition-colors">
                            🤖 Analisar
                        </button>
                    </div>
                    <div id="ai-insight-content">
                        @if($lastInsight)
                            <div class="text-sm font-semibold text-[#1B334A] mb-2">{{ $lastInsight->titulo }}</div>
                            <div class="text-sm text-gray-700 leading-relaxed">{{ $lastInsight->insight_text }}</div>
                            @if($lastInsight->action_suggested)
                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <div class="text-xs text-[#385776] font-medium">→ {{ $lastInsight->action_suggested }}</div>
                                </div>
                            @endif
                            <div class="mt-2 text-[10px] text-gray-400">Gerado em {{ $lastInsight->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</div>
                        @else
                            <div class="text-xs text-gray-400 text-center py-2">Clique em "Analisar" para recomendação IA</div>
                        @endif
                    </div>
                </div>

                <script>
                function gerarSugestaoIA() {
                    const btn = document.getElementById('btn-ai-account');
                    const box = document.getElementById('ai-insight-content');
                    btn.disabled = true;
                    btn.textContent = '⏳ Analisando...';
                    box.innerHTML = '<div class="text-xs text-[#385776] text-center py-4">Analisando perfil com gpt-5-mini...</div>';

                    fetch('{{ url("/crm/painel/account-action/" . $account->id) }}', {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success && data.insight) {
                            let h = '<div class="text-sm font-semibold text-[#1B334A] mb-2">' + data.insight.titulo + '</div>';
                            h += '<div class="text-sm text-gray-700 leading-relaxed">' + data.insight.insight_text + '</div>';
                            if (data.insight.action_suggested) {
                                h += '<div class="mt-3 pt-3 border-t border-gray-100"><div class="text-xs text-[#385776] font-medium">→ ' + data.insight.action_suggested + '</div></div>';
                            }
                            h += '<div class="mt-2 text-[10px] text-gray-400">Gerado agora</div>';
                            box.innerHTML = h;
                        } else {
                            box.innerHTML = '<div class="text-xs text-red-500">Erro: ' + (data.error || 'falha') + '</div>';
                        }
                        btn.disabled = false;
                        btn.textContent = '🤖 Analisar';
                    })
                    .catch(err => {
                        box.innerHTML = '<div class="text-xs text-red-500">Erro de conexão</div>';
                        btn.disabled = false;
                        btn.textContent = '🤖 Analisar';
                    });
                }
                </script>
                <div class="bg-white rounded-lg shadow-sm border" x-data="{ crmOpen: false }">
                    <div class="p-4 flex items-center justify-between cursor-pointer" x-on:click="crmOpen = !crmOpen">
                        <div>
                            <h2 class="text-sm font-semibold text-[#1B334A]">Gestão CRM</h2>
                            <p class="text-xs text-gray-500 mt-1" x-show="!crmOpen">
                                Responsável: <span class="font-medium text-gray-700">{{ $account->owner?->name ?? '—' }}</span>
                                &nbsp;|&nbsp; Ciclo: <span class="font-medium text-gray-700">{{ ucfirst($account->lifecycle ?? 'onboarding') }}</span>
                                &nbsp;|&nbsp; Saúde: <span class="font-medium text-gray-700">{{ $account->health_score ?? '—' }}</span>
                            </p>
                        </div>
                        <button class="text-xs text-[#385776] font-medium hover:underline" x-text="crmOpen ? 'Fechar' : 'Editar gestão'"></button>
                    </div>
                    <div x-show="crmOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0" class="px-6 pb-6 border-t border-gray-100">
                        <form id="form-crm-update" class="space-y-4 pt-4">
                            @csrf
                            <div><label class="text-xs text-gray-500">Responsável</label><select name="owner_user_id" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"><option value="">Sem responsável</option>@foreach($users as $u)<option value="{{ $u->id }}" {{ $account->owner_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>@endforeach</select></div>
                            <div><label class="text-xs text-gray-500">Ciclo de Vida</label><select name="lifecycle" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">@foreach(['onboarding','ativo','adormecido','arquivado','risco'] as $lc)<option value="{{ $lc }}" {{ $account->lifecycle === $lc ? 'selected' : '' }}>{{ ucfirst($lc) }}</option>@endforeach</select></div>
                            <div><label class="text-xs text-gray-500">Saúde</label><div class="flex items-center gap-2 mt-1">@php $hs = $account->health_score; @endphp<div class="flex-1 bg-gray-100 rounded-full h-6 overflow-hidden"><div class="h-full rounded-full flex items-center justify-center text-xs font-medium text-white" style="width: {{ max(15, $hs ?? 0) }}%; background-color: {{ ($hs ?? 0) >= 70 ? '#22C55E' : (($hs ?? 0) >= 40 ? '#F59E0B' : '#EF4444') }}">{{ $hs ?? '—' }}</div></div><span class="text-xs text-gray-400">auto</span></div></div>
                            <div><label class="text-xs text-gray-500">Próxima ação</label><input type="date" name="next_touch_at" value="{{ $account->next_touch_at?->format('Y-m-d') }}" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
                            <div><label class="text-xs text-gray-500">Notas</label><textarea name="notes" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">{{ $account->notes }}</textarea></div>
                            <div><label class="text-xs text-gray-500">Tags (separar por vírgula)</label><input type="text" name="tags" value="{{ implode(', ', $account->getTagsArray()) }}" class="w-full border rounded-lg px-3 py-2 text-sm mt-1"></div>
                            <button type="button" onclick="saveAccountCrm()" class="w-full px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">Salvar Alterações</button>
                            <p id="save-feedback" class="text-xs text-green-600 hidden text-center">Salvo com sucesso!</p>
                        </form>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg border p-4 text-xs text-gray-500 space-y-1">
                    <p><strong>Account ID:</strong> {{ $account->id }}</p>
                    @if($account->datajuri_pessoa_id)<p><strong>DataJuri ID:</strong> {{ $account->datajuri_pessoa_id }}</p>@endif
                    <p><strong>Criado:</strong> {{ $account->created_at?->format('d/m/Y') }}</p>
                </div>

                @if(in_array(auth()->user()->role, ['admin', 'coordenador', 'socio']))
                {{-- Ações Administrativas — Transferir (ação segura) --}}
                <div class="bg-white rounded-lg shadow-sm border p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Ações Administrativas</h3>
                    <div>
                        <label class="text-xs text-gray-500">Transferir para</label>
                        <div class="flex gap-2 mt-1">
                            <select id="transfer-owner" class="flex-1 border rounded-lg px-2 py-1.5 text-sm">
                                <option value="">Selecionar...</option>
                                @foreach($users as $u)
                                    @if($u->id !== $account->owner_user_id)
                                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endif
                                @endforeach
                            </select>
                            <button onclick="transferOwner()" class="px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs hover:bg-blue-700">Transferir</button>
                        </div>
                        <input type="text" id="transfer-reason" placeholder="Motivo (opcional)" class="w-full border rounded-lg px-2 py-1.5 text-sm mt-1">
                    </div>
                </div>

                {{-- Zona de Perigo (colapsável) --}}
                <div class="border border-red-200 rounded-lg" x-data="{ dangerOpen: false }">
                    <button x-on:click="dangerOpen = !dangerOpen"
                            class="w-full px-4 py-3 flex items-center justify-between text-sm font-medium text-red-600 hover:bg-red-50 rounded-lg transition-colors">
                        <span>⚠ Zona de Perigo</span>
                        <svg class="w-4 h-4 transition-transform duration-200" :class="{ 'rotate-180': dangerOpen }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="dangerOpen" x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-150" class="px-4 pb-4 space-y-4">
                        {{-- Arquivar --}}
                        @if($account->lifecycle !== 'arquivado')
                        <div class="border-t border-red-100 pt-3">
                            <p class="text-xs text-gray-500 mb-2">Arquivar esta conta (reversível)</p>
                            <input type="text" id="archive-reason" placeholder="Motivo do arquivamento" class="w-full border rounded-lg px-2 py-1.5 text-sm">
                            <button onclick="archiveAccount()" class="w-full mt-2 px-3 py-1.5 bg-gray-500 text-white rounded-lg text-xs hover:bg-gray-600">Arquivar Conta</button>
                        </div>
                        @else
                        <div class="border-t border-red-100 pt-3">
                            <button onclick="unarchiveAccount()" class="w-full px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">Reativar Conta</button>
                        </div>
                        @endif

                        {{-- Excluir (movido do botão flutuante) --}}
                        @if(auth()->user()->isAdmin())
                        <div class="border-t border-red-100 pt-3">
                            <p class="text-xs text-red-500 mb-2">Exclusão permanente — esta ação não pode ser desfeita</p>
                            <button onclick="document.getElementById('delete-modal').classList.remove('hidden')"
                                class="w-full px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-medium flex items-center justify-center gap-2 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                Excluir Conta Permanentemente
                            </button>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- TAB: ATIVIDADES                                                   --}}
    {{-- ================================================================== --}}
    <div id="tab-atividades" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Form de registro (destaque) --}}
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border p-6 sticky top-4">
                    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">📋 Registrar Interação</h2>
                    <form id="form-activity" class="space-y-3">
                        @csrf
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Canal</label>
                            <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="call">📞 Ligação</option>
                                <option value="meeting">🤝 Reunião</option>
                                <option value="whatsapp">💬 WhatsApp</option>
                                <option value="email">✉️ E-mail</option>
                                <option value="note">📝 Registro Interno</option>
                                <option value="visit">🏢 Visita Presencial</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Natureza</label>
                            <select name="purpose" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="acompanhamento">📋 Acompanhamento processual</option>
                                <option value="comercial">Comercial / Prospecção</option>
                                <option value="cobranca">Cobrança</option>
                                <option value="orientacao">Orientação jurídica</option>
                                <option value="documental">Documental</option>
                                <option value="agendamento">Agendamento</option>
                                <option value="retorno">Retorno de contato</option>
                                <option value="registro_interno">Registro interno</option>
                                <option value="relacionamento">Relacionamento</option>
                                <option value="assinatura">✍️ Assinatura de contrato</option>
                                <option value="estrategica">📊 Reunião estratégica</option>
                            </select>
                        </div>
                        <input type="text" name="title" placeholder="Resumo da interação" required class="w-full border rounded-lg px-3 py-2 text-sm">
                        <textarea name="body" placeholder="Descrição detalhada" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                        <textarea name="decisions" placeholder="Decisões / Recomendações (opcional)" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                        <textarea name="pending_items" placeholder="Pendências (opcional)" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                        <input type="datetime-local" name="due_at" class="w-full border rounded-lg px-3 py-2 text-sm text-gray-500" title="Agendar follow-up (opcional)">
                        <button type="button" onclick="saveActivity()" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 font-medium">
                            Registrar Interação
                        </button>
                    </form>
                </div>
            </div>

            {{-- Lista de atividades --}}
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Histórico de Interações ({{ $activities->count() }})</h2>
                    @if($activities->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-400 text-sm">Nenhuma interação registrada.</p>
                            <p class="text-gray-300 text-xs mt-1">Registre a primeira interação ao lado →</p>
                        </div>
                    @else
                        <div class="space-y-3">
                            @foreach($activities as $act)
                            @php
                                $actIcon = match($act->type) { 'call' => '📞', 'meeting' => '🤝', 'whatsapp' => '💬', 'note' => '📝', 'email' => '✉️', default => '•' };
                                $actColor = match($act->type) { 'call' => 'border-blue-400 bg-blue-50', 'meeting' => 'border-purple-400 bg-purple-50', 'whatsapp' => 'border-green-400 bg-green-50', 'note' => 'border-gray-300 bg-gray-50', 'email' => 'border-indigo-400 bg-indigo-50', 'visit' => 'border-teal-400 bg-teal-50', default => 'border-gray-200 bg-gray-50' };
                                $purposeLabel = match($act->purpose ?? '') { 'acompanhamento' => 'Acompanhamento', 'comercial' => 'Comercial', 'cobranca' => 'Cobrança', 'orientacao' => 'Orientação', 'documental' => 'Documental', 'agendamento' => 'Agendamento', 'retorno' => 'Retorno', 'registro_interno' => 'Registro Interno', 'relacionamento' => 'Relacionamento', 'assinatura' => 'Assinatura', 'estrategica' => 'Estratégica', default => '' };
                            @endphp
                            <div id="activity-{{ $act->id }}" class="border-l-4 {{ $actColor }} rounded-r-lg p-4 transition-all duration-500">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-start gap-2">
                                        <span class="text-lg">{{ $actIcon }}</span>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <p class="font-medium text-gray-800">{{ $act->title }}</p>
                                                @if($purposeLabel)
                                                    <span class="px-1.5 py-0.5 rounded text-xs bg-gray-200 text-gray-600">{{ $purposeLabel }}</span>
                                                @endif
                                            </div>
                                            @if($act->body)<p class="text-gray-600 text-sm mt-1">{{ $act->body }}</p>@endif
                                            @if($act->decisions)
                                                <div class="mt-2 p-2 bg-blue-50 rounded text-sm">
                                                    <span class="text-xs font-medium text-blue-700">Decisões:</span>
                                                    <p class="text-blue-800 text-xs mt-0.5">{{ $act->decisions }}</p>
                                                </div>
                                            @endif
                                            @if($act->pending_items)
                                                <div class="mt-1 p-2 bg-amber-50 rounded text-sm">
                                                    <span class="text-xs font-medium text-amber-700">Pendências:</span>
                                                    <p class="text-amber-800 text-xs mt-0.5">{{ $act->pending_items }}</p>
                                                </div>
                                            @endif
                                            @if($act->type === 'visit')
                                                <div class="mt-2 p-3 bg-teal-50 border border-teal-200 rounded-lg text-xs space-y-1">
                                                    <div class="flex items-center gap-4 flex-wrap">
                                                        @if($act->visit_arrival_time)<span>â° <strong>Chegada:</strong> {{ \Carbon\Carbon::parse($act->visit_arrival_time)->format('H:i') }}</span>@endif
                                                        @if($act->visit_departure_time)<span>ð <strong>Saída:</strong> {{ \Carbon\Carbon::parse($act->visit_departure_time)->format('H:i') }}</span>@endif
                                                        @if($act->visit_transport)<span>ð <strong>Transporte:</strong> {{ ['carro_proprio'=>'Carro próprio','aplicativo'=>'Aplicativo','taxi'=>'Táxi','transporte_publico'=>'Transp. público','a_pe'=>'A pé','moto'=>'Moto','outro'=>'Outro'][$act->visit_transport] ?? $act->visit_transport }}</span>@endif
                                                    </div>
                                                    <div class="flex items-center gap-4 flex-wrap">
                                                        @if($act->visit_objective)<span>ð¯ <strong>Objetivo:</strong> {{ ['acompanhamento'=>'Acompanhamento','relacionamento'=>'Relacionamento','prospeccao'=>'Prospecção','cobranca'=>'Cobrança','entrega_docs'=>'Entrega docs','assinatura'=>'Assinatura','reuniao_estrategica'=>'Reunião estratégica','outro'=>'Outro'][$act->visit_objective] ?? $act->visit_objective }}</span>@endif
                                                        @if($act->visit_location)<span>ð <strong>Local:</strong> {{ $act->visit_location }}</span>@endif
                                                    </div>
                                                    @if($act->visit_attendees)<div>ð¥ <strong>Participantes:</strong> {{ $act->visit_attendees }}</div>@endif
                                                    @if($act->visit_receptivity)
                                                        <div>ð¬ <strong>Receptividade:</strong>
                                                            <span class="px-1.5 py-0.5 rounded {{ $act->visit_receptivity === 'positiva' ? 'bg-green-100 text-green-800' : ($act->visit_receptivity === 'negativa' ? 'bg-red-100 text-red-800' : 'bg-yellow-100 text-yellow-800') }}">{{ ucfirst($act->visit_receptivity) }}</span>
                                                        </div>
                                                    @endif
                                                    @if($act->visit_next_contact)<div>ð <strong>Próximo contato:</strong> {{ $act->visit_next_contact->format('d/m/Y') }}</div>@endif
                                                    <div class="mt-1 pt-1 border-t border-teal-200">
                                                        <a href="{{ url('crm/accounts/' . $act->account_id . '/activities/' . $act->id . '/pdf') }}" target="_blank" class="text-teal-700 hover:text-teal-900 font-medium">ð Gerar PDF</a>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="text-right text-xs text-gray-400 flex-shrink-0 ml-4">
                                        <p>{{ $act->created_at->format('d/m/Y H:i') }}</p>
                                        @if($act->createdBy)<p class="font-medium">{{ $act->createdBy->name }}</p>@endif
                                        @if($act->done_at)
                                            @php
                                                $resCor = match($act->resolution_status ?? '') {
                                                    'procedente' => 'bg-green-100 text-green-700',
                                                    'improcedente' => 'bg-red-100 text-red-700',
                                                    'parcial' => 'bg-yellow-100 text-yellow-700',
                                                    'cancelada' => 'bg-gray-200 text-gray-600',
                                                    'ciente' => 'bg-blue-100 text-blue-700',
                                                    default => 'bg-green-100 text-green-700',
                                                };
                                                $resLabel = match($act->resolution_status ?? '') {
                                                    'procedente' => 'Procedente',
                                                    'improcedente' => 'Improcedente',
                                                    'parcial' => 'Parcial',
                                                    'cancelada' => 'Cancelada',
                                                    'ciente' => 'Ciente',
                                                    default => 'Concluída',
                                                };
                                            @endphp
                                            <span class="px-1.5 py-0.5 rounded text-xs {{ $resCor }}">{{ $resLabel }}</span>
                                            @if($act->resolution_notes)
                                                <p class="text-xs text-gray-500 mt-1 italic">{{ Str::limit($act->resolution_notes, 80) }}</p>
                                            @endif
                                        @else
                                            @php
                                                $authId = auth()->id();
                                                $authUser = auth()->user();
                                                $actIsCreator = $act->created_by_user_id == $authId;
                                                $actIsOwner = $account->owner_user_id == $authId;
                                                $actIsSuper = $authUser->isAdmin() || $authUser->isCoordenador();
                                                $actCanBtn = $actIsCreator || $actIsOwner || $actIsSuper;
                                                $actBtnMode = ($actIsOwner && !$actIsCreator && !$actIsSuper) ? 'ciente' : 'concluir';
                                                $actBtnLabel = $actBtnMode === 'ciente' ? '✓ Ciente' : '✓ Concluir';
                                            @endphp
                                            @if($actCanBtn)
                                                <button onclick="openCompleteModal({{ $act->id }}, '{{ $actBtnMode }}')" class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 mt-1">{{ $actBtnLabel }}</button>
                                            @endif
                                        @endif
                                        @if($act->due_at && !$act->done_at)
                                            <p class="mt-1 {{ $act->due_at->isPast() ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                                                📅 {{ $act->due_at->format('d/m/Y H:i') }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- TAB: PROCESSOS                                                    --}}
    {{-- ================================================================== --}}
    <div id="tab-processos" class="tab-content hidden">
        <div class="space-y-6">
            @if(!empty($djContext['processos']))
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">Processos ({{ count($djContext['processos']) }})</h2>
                    <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700">{{ $processosAtivos }} ativo(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2 pr-3">Pasta</th><th class="pb-2 pr-3">Número</th><th class="pb-2 pr-3">Tipo Ação</th><th class="pb-2 pr-3">Adverso</th><th class="pb-2 pr-3">Status</th><th class="pb-2 pr-3">Abertura</th><th class="pb-2">Advogado</th></tr></thead>
                        <tbody>
                            @foreach($djContext['processos'] as $proc)
                            @php $isAtivo = in_array($proc->status ?? '', ['Ativo','Em andamento','Em Andamento']); @endphp
                            <tr class="border-b border-gray-50 {{ $isAtivo ? '' : 'opacity-60' }}">
                                <td class="py-2 pr-3 font-medium text-[#385776]">{{ $proc->pasta ?? '—' }}</td>
                                <td class="py-2 pr-3 text-gray-600 text-xs">{{ \Illuminate\Support\Str::limit($proc->numero ?? '—', 25) }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ \Illuminate\Support\Str::limit($proc->tipo_acao ?? '—', 30) }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ \Illuminate\Support\Str::limit($proc->adverso_nome ?? '—', 25) }}</td>
                                <td class="py-2 pr-3"><span class="px-1.5 py-0.5 rounded text-xs {{ $isAtivo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $proc->status ?? '—' }}</span></td>
                                <td class="py-2 pr-3 text-gray-500 text-xs">{{ $proc->data_abertura ? \Carbon\Carbon::parse($proc->data_abertura)->format('d/m/Y') : '—' }}</td>
                                <td class="py-2 text-gray-500 text-xs">{{ $proc->proprietario_nome ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border p-6"><p class="text-gray-400 text-sm">Nenhum processo encontrado.</p></div>
            @endif

            @if(!empty($djContext['contratos']))
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Contratos ({{ count($djContext['contratos']) }})</h2>
                <div class="space-y-2">
                    @foreach($djContext['contratos'] as $ct)
                    <div class="flex items-center justify-between border rounded-lg p-3 hover:bg-gray-50">
                        <div><span class="font-medium text-gray-800">Contrato #{{ $ct->numero ?? $ct->id }}</span>@if($ct->proprietario_nome ?? null)<span class="text-xs text-gray-400 ml-2">{{ $ct->proprietario_nome }}</span>@endif</div>
                        <div class="text-right"><span class="font-medium text-[#1B334A]">R$ {{ number_format($ct->valor ?? 0, 2, ',', '.') }}</span>@if($ct->data_assinatura ?? null)<span class="text-xs text-gray-400 block">{{ \Carbon\Carbon::parse($ct->data_assinatura)->format('d/m/Y') }}</span>@endif</div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- TAB: PROCESSOS ADMINISTRATIVOS                                   --}}
    {{-- ================================================================== --}}
    <div id="tab-proc-adm" class="tab-content hidden">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-[#1B334A]">Processos Administrativos</h2>
                <a href="{{ route('crm.admin-processes.create') }}?account_id={{ $account->id }}"
                   class="px-3 py-1.5 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] flex items-center gap-1.5">
                    + Novo processo
                </a>
            </div>

            @if($adminProcesses->isEmpty())
                <div class="bg-white rounded-lg shadow-sm border p-8 text-center">
                    <p class="text-gray-400 text-sm">Nenhum processo administrativo cadastrado para este cliente.</p>
                    <a href="{{ route('crm.admin-processes.create') }}?account_id={{ $account->id }}"
                       class="mt-3 inline-block text-sm text-[#385776] hover:underline">Criar o primeiro</a>
                </div>
            @else
                {{-- Cards ativos --}}
                @php
                    $adm_ativos    = $adminProcesses->whereNotIn('status', ['concluido','cancelado']);
                    $adm_encerrados = $adminProcesses->whereIn('status', ['concluido','cancelado']);
                @endphp

                @foreach($adm_ativos as $ap)
                @php
                    $statusColors = [
                        'rascunho'            => 'bg-gray-100 text-gray-600',
                        'aberto'              => 'bg-blue-100 text-blue-700',
                        'em_andamento'        => 'bg-indigo-100 text-indigo-700',
                        'aguardando_cliente'  => 'bg-yellow-100 text-yellow-700',
                        'aguardando_terceiro' => 'bg-orange-100 text-orange-700',
                        'suspenso'            => 'bg-red-100 text-red-600',
                    ];
                    $sc = $statusColors[$ap->status] ?? 'bg-gray-100 text-gray-600';
                @endphp
                <div class="bg-white rounded-lg shadow-sm border hover:shadow-md transition-shadow">
                    <a href="{{ route('crm.admin-processes.show', $ap->id) }}" class="block p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="text-base">{{ $ap->tipoIcon() }}</span>
                                    <span class="text-xs font-mono text-gray-400">{{ $ap->protocolo }}</span>
                                    <span class="px-2 py-0.5 rounded-full text-xs border {{ $ap->tipoColor() }}">{{ $ap->tipoLabel() }}</span>
                                </div>
                                <p class="font-medium text-gray-800 truncate">{{ $ap->titulo }}</p>
                                @if($ap->orgao_destino)
                                    <p class="text-xs text-gray-400 mt-0.5">{{ $ap->orgao_destino }}</p>
                                @endif
                            </div>
                            <div class="flex flex-col items-end gap-1.5 shrink-0">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $sc }}">{{ $ap->statusLabel() }}</span>
                                @if($ap->prazo_final)
                                    @php $atrasado = $ap->prazo_final->isPast(); @endphp
                                    <span class="text-xs {{ $atrasado ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                                        {{ $atrasado ? '⚠ ' : '' }}Prazo: {{ $ap->prazo_final->format('d/m/Y') }}
                                    </span>
                                @endif
                                @if($ap->owner)
                                    <span class="text-xs text-gray-400">{{ $ap->owner->name }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                </div>
                @endforeach

                @if($adm_encerrados->isNotEmpty())
                <details class="group">
                    <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 py-2 flex items-center gap-1">
                        <svg class="w-4 h-4 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        {{ $adm_encerrados->count() }} processo(s) encerrado(s)
                    </summary>
                    <div class="mt-2 space-y-2">
                        @foreach($adm_encerrados as $ap)
                        <div class="bg-gray-50 rounded-lg border border-gray-200 opacity-70 hover:opacity-100 transition-opacity">
                            <a href="{{ route('crm.admin-processes.show', $ap->id) }}" class="block px-5 py-3">
                                <div class="flex items-center justify-between gap-4">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span>{{ $ap->tipoIcon() }}</span>
                                        <span class="text-xs font-mono text-gray-400">{{ $ap->protocolo }}</span>
                                        <span class="font-medium text-sm text-gray-700 truncate">{{ $ap->titulo }}</span>
                                    </div>
                                    <span class="text-xs px-2 py-0.5 rounded-full shrink-0 {{ $ap->status === 'concluido' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' }}">{{ $ap->statusLabel() }}</span>
                                </div>
                            </a>
                        </div>
                        @endforeach
                    </div>
                </details>
                @endif
            @endif
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- TAB: COMUNICACAO                                                  --}}
    {{-- ================================================================== --}}
    <div id="tab-comunicacao" class="tab-content hidden">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- WhatsApp Messages --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">💬 WhatsApp</h2>
                    @if($commContext['has_wa'])
                    <span class="text-xs px-2 py-1 rounded-full {{ $commContext['whatsapp']->status === 'open' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ ucfirst($commContext['whatsapp']->status ?? 'closed') }}</span>
                    @endif
                </div>
                @if($commContext['has_wa'] && !empty($commContext['wa_messages']))
                <div class="space-y-2 max-h-96 overflow-y-auto pr-2" id="wa-messages-box">
                    @foreach($commContext['wa_messages'] as $msg)
                    @php $isIncoming = ($msg->direction ?? '') === 'incoming'; @endphp
                    <div class="flex {{ $isIncoming ? 'justify-start' : 'justify-end' }}">
                        <div class="max-w-[80%] rounded-lg px-3 py-2 text-sm {{ $isIncoming ? 'bg-gray-100 text-gray-800' : 'bg-[#385776] text-white' }}">
                            @if($msg->body)<p>{{ \Illuminate\Support\Str::limit($msg->body, 300) }}</p>@endif
                            @if($msg->message_type !== 'text')<span class="text-xs opacity-60">[{{ $msg->message_type }}]</span>@endif
                            <p class="text-xs {{ $isIncoming ? 'text-gray-400' : 'text-blue-200' }} mt-1">{{ \Carbon\Carbon::parse($msg->created_at)->format('d/m H:i') }}</p>
                        </div>
                    </div>
                    @endforeach
                </div>
                <script>document.addEventListener('DOMContentLoaded',()=>{const b=document.getElementById('wa-messages-box');if(b)b.scrollTop=b.scrollHeight;});</script>
                @elseif($commContext['has_wa'])
                <p class="text-gray-400 text-sm">Conversa encontrada mas sem mensagens recentes.</p>
                <p class="text-xs text-gray-300 mt-1">Contato: {{ $commContext['whatsapp']->phone ?? '' }} · {{ $commContext['whatsapp']->name ?? '' }}</p>
                @else
                <p class="text-gray-400 text-sm">Nenhuma conversa WhatsApp vinculada.</p>
                @endif
            </div>

            {{-- Solicitações CRM --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">🎫 Solicitações</h2>
                @if($commContext['has_tickets'])
                <div class="space-y-2">
                    @foreach($commContext['tickets'] as $tk)
                    @php
                        $tkObj = (object) $tk;
                        $tkStatus = $tkObj->status ?? 'aberto';
                        $tkCor = \App\Models\Crm\CrmServiceRequest::statusBadge($tkStatus);
                        $tkLabel = \App\Models\Crm\CrmServiceRequest::statusLabel($tkStatus);
                        $cats = \App\Models\Crm\CrmServiceRequest::categorias();
                        $catLabel = $cats[$tkObj->category ?? '']['label'] ?? ($tkObj->category ?? '');
                    @endphp
                    <div class="border rounded-lg p-3 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-800 text-sm">{{ $tkObj->subject ?? 'Solicitação #' . ($tkObj->protocolo ?? $tkObj->id ?? '?') }}</span>
                            <span class="px-1.5 py-0.5 rounded text-xs {{ $tkCor }}">{{ $tkLabel }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                            @if($tkObj->protocolo ?? null)<span>#{{ $tkObj->protocolo }}</span>@endif
                            @if($catLabel)<span>{{ $catLabel }}</span>@endif
                            @if($tkObj->created_at ?? null)<span>{{ \Carbon\Carbon::parse($tkObj->created_at)->format('d/m/Y') }}</span>@endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-gray-400 text-sm">Nenhuma solicitação encontrada.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- TAB: FINANCEIRO                                                   --}}
    {{-- ================================================================== --}}
    <div id="tab-financeiro" class="tab-content hidden">
        <div class="space-y-6">
            @if(!empty($djContext['contas_receber']))
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">Contas a Receber ({{ count($djContext['contas_receber']) }})</h2>
                    @if($contasVencidas->count() > 0)<span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-700">{{ $contasVencidas->count() }} vencida(s)</span>@endif
                </div>

                {{-- Aging Summary --}}
                @if(isset($finSummary))
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                    <div class="bg-green-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-green-600 uppercase">Recebido</p>
                        <p class="text-lg font-bold text-green-700">R$ {{ number_format($finSummary['total_recebido'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-yellow-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-yellow-600 uppercase">Em Aberto</p>
                        <p class="text-lg font-bold text-yellow-700">R$ {{ number_format($finSummary['total_aberto'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-red-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-red-600 uppercase">Vencido</p>
                        <p class="text-lg font-bold text-red-700">R$ {{ number_format($finSummary['total_vencido'], 0, ',', '.') }}</p>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-3 text-center">
                        <p class="text-xs text-gray-500 uppercase">Títulos Abertos</p>
                        <p class="text-lg font-bold text-gray-700">{{ $finSummary['qty_abertas'] }}</p>
                    </div>
                </div>

                {{-- Aging Bars --}}
                @php $agingMax = max(1, collect($finSummary['aging'])->max('valor')); @endphp
                <div class="space-y-2 mb-4">
                    @foreach($finSummary['aging'] as $ag)
                    @if($ag['valor'] > 0)
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-24 text-gray-600 text-xs">{{ $ag['label'] }}</span>
                        <div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">
                            <div class="h-full rounded-full flex items-center justify-end pr-2" style="width: {{ round($ag['valor'] / $agingMax * 100) }}%; background-color: {{ $ag['cor'] }}">
                                <span class="text-white text-xs font-medium">R$ {{ number_format($ag['valor'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <span class="text-xs text-gray-400 w-12 text-right">{{ $ag['qty'] }}x</span>
                    </div>
                    @endif
                    @endforeach
                </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead><tr class="text-left text-xs text-gray-400 border-b"><th class="pb-2 pr-3">Descrição</th><th class="pb-2 pr-3">Valor</th><th class="pb-2 pr-3">Vencimento</th><th class="pb-2 pr-3">Pagamento</th><th class="pb-2">Status</th></tr></thead>
                        <tbody>
                            @foreach($djContext['contas_receber'] as $cr)
                            @php
                                $isVencida = !in_array($cr->status ?? '', ['Concluído', 'Concluido', 'Excluido', 'Excluído']) && $cr->data_vencimento && $cr->data_vencimento < date('Y-m-d');
                                $isPaga = in_array($cr->status ?? '', ['Concluído', 'Concluido']);
                            @endphp
                            <tr class="border-b border-gray-50 {{ $isVencida ? 'bg-red-50/50' : '' }}">
                                <td class="py-2 pr-3 text-gray-600">{{ \Illuminate\Support\Str::limit($cr->descricao ?? '—', 40) }}</td>
                                <td class="py-2 pr-3 font-medium {{ $isVencida ? 'text-red-600' : 'text-gray-700' }}">R$ {{ number_format($cr->valor ?? 0, 2, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-xs {{ $isVencida ? 'text-red-500 font-medium' : 'text-gray-500' }}">{{ $cr->data_vencimento ? \Carbon\Carbon::parse($cr->data_vencimento)->format('d/m/Y') : '—' }}</td>
                                <td class="py-2 pr-3 text-xs text-gray-500">{{ $cr->data_pagamento ? \Carbon\Carbon::parse($cr->data_pagamento)->format('d/m/Y') : '—' }}</td>
                                <td class="py-2">
                                    @if($isPaga)<span class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Pago</span>
                                    @elseif($isVencida)<span class="px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700">Vencido</span>
                                    @else<span class="px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">Aberto</span>@endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @else
                <div class="bg-white rounded-lg shadow-sm border p-6"><p class="text-gray-400 text-sm">Nenhuma conta a receber encontrada.</p></div>
            @endif

            {{-- ============================================================ --}}
            {{-- PAINEL DE INADIMPLÊNCIA                                        --}}
            {{-- ============================================================ --}}
            @if($contasVencidas->count() > 0 || $inadTarefaAberta || $inadDecisaoAtiva || $inadHistoricoDecisoes->count() > 0)
            <div class="bg-white rounded-lg shadow-sm border border-red-200 p-6" id="painel-inadimplencia">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-8 h-8 rounded-full bg-red-100 flex items-center justify-center">
                        <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    </div>
                    <h2 class="text-lg font-semibold text-red-800">Gestão de Inadimplência</h2>
                    @if($inadDecisaoAtiva)
                        @php $badgeColor = match($inadDecisaoAtiva->decisao) { 'aguardar' => 'bg-yellow-100 text-yellow-800', 'renegociar' => 'bg-blue-100 text-blue-800', 'sinistrar' => 'bg-gray-100 text-gray-600', default => 'bg-gray-100 text-gray-600' }; @endphp
                        <span class="text-xs px-2 py-1 rounded-full font-medium {{ $badgeColor }}">
                            {{ match($inadDecisaoAtiva->decisao) { 'aguardar' => '⏸ Aguardando', 'renegociar' => '🔄 Renegociando', 'sinistrar' => '🔒 Sinistrado', default => $inadDecisaoAtiva->decisao } }}
                        </span>
                    @endif
                </div>

                {{-- Decisão ativa --}}
                @if($inadDecisaoAtiva)
                <div class="mb-5 p-4 rounded-lg {{ match($inadDecisaoAtiva->decisao) { 'aguardar' => 'bg-yellow-50 border border-yellow-200', 'renegociar' => 'bg-blue-50 border border-blue-200', 'sinistrar' => 'bg-gray-50 border border-gray-200', default => 'bg-gray-50' } }}">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1">
                            <p class="text-sm font-medium text-gray-700 mb-1">
                                Decisão registrada em {{ $inadDecisaoAtiva->created_at->format('d/m/Y') }} por {{ $inadDecisaoAtiva->createdBy?->name ?? '—' }}
                                @if($inadDecisaoAtiva->prazo_revisao)
                                — revisão em <strong>{{ $inadDecisaoAtiva->prazo_revisao->format('d/m/Y') }}</strong>
                                @endif
                            </p>
                            <p class="text-sm text-gray-600">{{ $inadDecisaoAtiva->justificativa }}</p>
                            @if($inadDecisaoAtiva->sinistro_notas)
                                <p class="text-xs text-gray-500 mt-1"><strong>Notas do contrato:</strong> {{ $inadDecisaoAtiva->sinistro_notas }}</p>
                            @endif
                        </div>
                        @if(auth()->user()->isAdmin() && $contasVencidas->count() > 0)
                        <button onclick="document.getElementById('painel-decisao').scrollIntoView({behavior:'smooth'})" class="text-xs text-gray-500 underline hover:text-gray-700 whitespace-nowrap flex-shrink-0 mt-0.5">Alterar decisão</button>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Tarefa aberta de cobrança --}}
                @if($inadTarefaAberta && (!$inadDecisaoAtiva || $inadDecisaoAtiva->decisao !== 'sinistrar'))
                <div class="mb-5 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="flex items-center gap-2 mb-2">
                        <svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span class="text-sm font-semibold text-orange-800">Tarefa de Cobrança Aberta</span>
                        @if($inadTarefaAberta->due_at && $inadTarefaAberta->due_at->isPast())
                            <span class="text-xs px-1.5 py-0.5 rounded bg-red-100 text-red-700">Atrasada</span>
                        @endif
                    </div>
                    <p class="text-sm text-orange-700 mb-3">{{ $inadTarefaAberta->title }}</p>
                    <p class="text-xs text-orange-600 mb-3">{{ $inadTarefaAberta->body }}</p>
                    @if($inadTarefaAberta->due_at)
                        <p class="text-xs text-gray-500 mb-3">Prazo: {{ $inadTarefaAberta->due_at->format('d/m/Y H:i') }}</p>
                    @endif

                    {{-- Evidências já enviadas --}}
                    @if($inadEvidencias->count() > 0)
                    <div class="mb-3">
                        <p class="text-xs font-medium text-gray-600 mb-1">Evidências enviadas:</p>
                        @foreach($inadEvidencias as $ev)
                        <div class="flex items-center gap-2 text-xs text-gray-600 py-1 border-b border-orange-100">
                            <svg class="w-3 h-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            <a href="{{ $ev->url }}" target="_blank" class="text-blue-600 hover:underline truncate max-w-[200px]">{{ $ev->original_name }}</a>
                            <span class="text-gray-400">{{ $ev->uploadedBy?->name }}</span>
                            <span class="text-gray-400">{{ $ev->created_at->format('d/m H:i') }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Form upload evidência --}}
                    <form id="form-evidencia-cobranca" class="mt-3 space-y-3" enctype="multipart/form-data">
                        @csrf
                        <p class="text-xs font-semibold text-orange-800">Registrar evidência de cobrança:</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Tipo de contato *</label>
                                <select name="tipo_contato" required class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                                    <option value="">Selecione...</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">E-mail</option>
                                    <option value="ligacao">Ligação</option>
                                    <option value="visita">Visita</option>
                                    <option value="outro">Outro</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Data da cobrança *</label>
                                <input type="date" name="data_cobranca" required max="{{ date('Y-m-d') }}" min="{{ date('Y-m-d', strtotime('-30 days')) }}" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Descrição detalhada do contato * <span class="text-gray-400">(mín. 100 caracteres)</span></label>
                            <textarea name="descricao" required minlength="100" rows="3" placeholder="Descreva o que foi feito: com quem falou, o que foi discutido, qual foi a resposta do cliente..." class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
                            <p class="text-xs text-gray-400 mt-0.5" id="desc-counter">0 / 100 caracteres mínimos</p>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Arquivo de evidência * <span class="text-gray-400">(JPG, PNG, WebP ou PDF — mín. 15 KB)</span></label>
                            <input type="file" name="file" required accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 bg-white">
                        </div>
                        <div id="evidencia-error" class="hidden text-sm text-red-600 bg-red-50 p-2 rounded"></div>
                        <button type="submit" id="btn-enviar-evidencia" class="px-4 py-2 bg-orange-600 text-white text-sm rounded hover:bg-orange-700 font-medium transition-colors">
                            Enviar evidência e fechar tarefa
                        </button>
                    </form>
                </div>
                @endif

                {{-- Painel decisório (admin) --}}
                @if(auth()->user()->isAdmin() && $contasVencidas->count() > 0)
                <div class="mb-5 p-4 bg-red-50 border border-red-300 rounded-lg" id="painel-decisao">
                    <p class="text-sm font-semibold text-red-800 mb-3">Deliberar sobre inadimplência</p>
                    <div class="flex flex-wrap gap-2 mb-4">
                        <button onclick="abrirDecisao('aguardar')" class="px-3 py-1.5 text-sm bg-yellow-500 text-white rounded hover:bg-yellow-600 font-medium transition-colors">⏸ Aguardar (30 dias)</button>
                        <button onclick="abrirDecisao('renegociar')" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 font-medium transition-colors">🔄 Renegociar</button>
                        <button onclick="abrirDecisao('sinistrar')" class="px-3 py-1.5 text-sm bg-gray-700 text-white rounded hover:bg-gray-800 font-medium transition-colors">🔒 Sinistrar contrato</button>
                    </div>
                    <form id="form-decisao-inadimplencia" class="hidden space-y-3">
                        @csrf
                        <input type="hidden" name="decisao" id="decisao-valor">
                        <div id="decisao-label" class="text-sm font-medium text-gray-700"></div>
                        <div id="sinistro-notas-wrap" class="hidden">
                            <label class="block text-xs text-gray-600 mb-1">Identificação do contrato sinistrado</label>
                            <input type="text" name="sinistro_notas" maxlength="1000" placeholder="N° do contrato, data, valor..." class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Justificativa * <span class="text-gray-400">(mín. 20 caracteres)</span></label>
                            <textarea name="justificativa" required minlength="20" rows="3" placeholder="Descreva o motivo da decisão..." class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
                        </div>
                        <div id="decisao-error" class="hidden text-sm text-red-600 bg-red-50 p-2 rounded"></div>
                        <div class="flex gap-2">
                            <button type="submit" class="px-4 py-2 bg-[#1B334A] text-white text-sm rounded hover:bg-[#2a4a64] font-medium transition-colors">Confirmar decisão</button>
                            <button type="button" onclick="fecharDecisao()" class="px-4 py-2 bg-gray-200 text-gray-700 text-sm rounded hover:bg-gray-300 font-medium transition-colors">Cancelar</button>
                        </div>
                    </form>
                </div>
                @endif

                {{-- Histórico de decisões --}}
                @if($inadHistoricoDecisoes->count() > 0)
                <div>
                    <p class="text-xs font-medium text-gray-500 uppercase mb-2">Histórico de decisões</p>
                    <div class="space-y-2">
                        @foreach($inadHistoricoDecisoes as $hd)
                        <div class="flex items-start gap-3 text-xs text-gray-500 py-1.5 border-b border-gray-100">
                            <span class="shrink-0">{{ $hd->created_at->format('d/m/Y') }}</span>
                            <span class="font-medium {{ match($hd->decisao) { 'aguardar' => 'text-yellow-700', 'renegociar' => 'text-blue-700', 'sinistrar' => 'text-gray-700', default => 'text-gray-600' } }}">
                                {{ match($hd->decisao) { 'aguardar' => 'Aguardar', 'renegociar' => 'Renegociar', 'sinistrar' => 'Sinistrar', default => $hd->decisao } }}
                            </span>
                            <span class="text-gray-400">{{ $hd->createdBy?->name }}</span>
                            <span class="shrink-0 px-1.5 py-0.5 rounded {{ match($hd->status) { 'ativa' => 'bg-green-100 text-green-700', 'expirada' => 'bg-yellow-100 text-yellow-700', 'encerrada' => 'bg-gray-100 text-gray-500', default => '' } }}">
                                {{ match($hd->status) { 'ativa' => 'Ativa', 'expirada' => 'Expirada', 'encerrada' => 'Encerrada', default => $hd->status } }}
                            </span>
                            <span class="flex-1 truncate">{{ \Illuminate\Support\Str::limit($hd->justificativa, 60) }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
            @endif

        </div>
    </div>
</div>

{{-- Modal Concluir / Ciente --}}
<div id="modal-complete" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md mx-4">
        <h3 class="text-lg font-semibold text-[#1B334A] mb-4" id="modal-complete-title">Concluir Atividade</h3>
        <input type="hidden" id="complete-activity-id">
        <input type="hidden" id="complete-mode" value="concluir">
        <div class="space-y-3">
            <div id="complete-status-wrap">
                <label class="text-xs text-gray-500 mb-1 block">Resultado</label>
                <select id="complete-status" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="procedente">✅ Procedente — realizado com sucesso</option>
                    <option value="improcedente">❌ Improcedente — não se aplica / indevido</option>
                    <option value="parcial">⚠️ Parcial — resolvido parcialmente</option>
                    <option value="cancelada">🚫 Cancelada — não foi possível realizar</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block" id="complete-notes-label">Anotações <span class="text-red-500">*</span></label>
                <textarea id="complete-notes" rows="3" placeholder="Descreva o que foi feito, resultado obtido..." class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="flex gap-2">
                <button onclick="submitComplete()" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700" id="modal-complete-btn">Concluir</button>
                <button onclick="document.getElementById('modal-complete').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<div id="tab-solicitacoes" class="tab-content hidden">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-[#1B334A]">Solicitações</h2>
            <button onclick="document.getElementById('form-new-sr').classList.toggle('hidden')" class="px-3 py-1.5 bg-[#385776] text-white rounded-lg text-xs hover:bg-[#1B334A] transition">+ Nova Solicitação</button>
        </div>

        {{-- Formulário nova solicitação --}}
        <form id="form-new-sr" method="POST" action="{{ route('crm.service-requests.store', $account->id) }}" class="hidden mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Categoria</label>
                    <select name="category" required class="w-full border rounded-lg px-3 py-2 text-sm bg-white" onchange="this.form.querySelector('[data-approval-info]').textContent = this.selectedOptions[0]?.dataset?.approval === '1' ? '⚠️ Requer aprovação da diretoria' : ''">
                        <option value="">Selecione...</option>
                        @foreach($srCategorias as $key => $cat)
                            @if(!str_starts_with($key, 'cliente_'))
                            <option value="{{ $key }}" data-approval="{{ $cat['approval'] ? '1' : '0' }}">{{ $cat['label'] }}</option>
                            @endif
                        @endforeach
                    </select>
                    <p data-approval-info class="text-xs text-orange-600 mt-1"></p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Prioridade</label>
                    <select name="priority" required class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="normal" selected>Normal</option>
                        <option value="baixa">Baixa</option>
                        <option value="alta">Alta</option>
                        <option value="urgente">Urgente</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Atribuir a</label>
                    <select name="assigned_to_user_id" class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                        <option value="">Não atribuir agora</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Assunto</label>
                <input type="text" name="subject" required maxlength="255" placeholder="Resumo da solicitação" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Descrição detalhada</label>
                <textarea name="description" required maxlength="3000" rows="3" placeholder="Descreva o que precisa ser feito, contexto e prazos" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">Criar Solicitação</button>
                <button type="button" onclick="document.getElementById('form-new-sr').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-gray-600">Cancelar</button>
            </div>
        </form>

        {{-- Lista de solicitações --}}
        @if($serviceRequests->count() > 0)
            <div class="space-y-3">
                @foreach($serviceRequests as $sr)
                    <div class="border rounded-lg p-4 hover:shadow-sm transition {{ $sr->isOpen() ? 'border-l-4 border-l-[#385776]' : 'border-gray-200' }}">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <a href="{{ route('crm.service-requests.show', $sr->id) }}" class="text-sm font-medium text-[#1B334A] hover:underline">
                                        #{{ $sr->id }} — {{ $sr->subject }}
                                    </a>
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="px-2 py-0.5 rounded-full {{ App\Models\Crm\CrmServiceRequest::statusBadge($sr->status) }}">{{ App\Models\Crm\CrmServiceRequest::statusLabel($sr->status) }}</span>
                                    <span class="px-2 py-0.5 rounded-full {{ App\Models\Crm\CrmServiceRequest::priorityBadge($sr->priority) }}">{{ ucfirst($sr->priority) }}</span>
                                    <span class="text-gray-400">{{ $srCategorias[$sr->category]['label'] ?? $sr->category }}</span>
                                    @if($sr->origem === 'autoatendimento')
                                        <span class="px-1.5 py-0.5 rounded bg-teal-50 text-teal-600">cliente</span>
                                    @endif
                                    <span class="text-gray-400">•</span>
                                    <span class="text-gray-400">por {{ $sr->requestedBy->name ?? 'cliente' }}</span>
                                    @if($sr->assignedTo)
                                        <span class="text-gray-400">→ {{ $sr->assignedTo->name }}</span>
                                    @endif
                                    <span class="text-gray-400">• {{ $sr->created_at->diffForHumans() }}</span>
                                </div>
                                @if($sr->requires_approval && $sr->status === 'aguardando_aprovacao')
                                    <p class="text-xs text-purple-600 mt-1">⚠️ Aguardando aprovação da diretoria</p>
                                @endif
                            </div>
                            <a href="{{ route('crm.service-requests.show', $sr->id) }}" class="text-xs text-[#385776] hover:underline ml-3">Detalhes →</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-10 text-gray-400">
                <p class="text-3xl mb-2">📝</p>
                <p class="text-sm">Nenhuma solicitação registrada.</p>
                <p class="text-xs mt-1">Clique em "+ Nova Solicitação" para abrir um chamado interno.</p>
            </div>
        @endif
    </div>
    </div>

<div id="tab-documentos" class="tab-content hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-lg font-semibold text-[#1B334A]">Documentos ({{ $documents->count() }})</h2>
                <button onclick="document.getElementById('form-upload-doc').classList.toggle('hidden')" class="px-3 py-1.5 bg-[#385776] text-white rounded-lg text-xs hover:bg-[#1B334A] transition">+ Enviar Documento</button>
            </div>

            {{-- Erros de validação do upload --}}
            @if($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                    <p class="text-sm font-medium text-red-700">Erro no upload:</p>
                    @foreach($errors->all() as $error)
                        <p class="text-xs text-red-600 mt-1">{{ $error }}</p>
                    @endforeach
                </div>
                <script>document.addEventListener('DOMContentLoaded',function(){var t=document.querySelector('[data-tab="documentos"]');if(t)t.click();var f=document.getElementById('form-upload-doc');if(f)f.classList.remove('hidden');});</script>
            @endif

            {{-- Formulário de upload --}}
            <form id="form-upload-doc" method="POST" action="{{ route('crm.accounts.upload-document', $account->id) }}" enctype="multipart/form-data" class="{{ $errors->any() ? '' : 'hidden' }} mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Arquivo (PDF, JPG, PNG, DOC)</label>
                        <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Categoria</label>
                        <select name="category" required class="w-full border rounded-lg px-3 py-2 text-sm bg-white">
                            @foreach($docCategorias as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Observações (opcional)</label>
                    <input type="text" name="notes" maxlength="500" placeholder="Descrição breve do documento" class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">Enviar</button>
                    <button type="button" onclick="document.getElementById('form-upload-doc').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-gray-600">Cancelar</button>
                </div>
            </form>

            {{-- Lista de documentos --}}
            @if($documents->count() > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Documento</th>
                                <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Categoria</th>
                                <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Enviado por</th>
                                <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Data</th>
                                <th class="text-left py-2 text-xs font-medium text-gray-500 uppercase">Tamanho</th>
                                <th class="text-right py-2 text-xs font-medium text-gray-500 uppercase">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $doc)
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50">
                                    <td class="py-2.5">
                                        <div class="flex items-center gap-2">
                                            <span class="text-lg">{{ str_contains($doc->mime_type, 'pdf') ? '📄' : (str_contains($doc->mime_type, 'image') ? '🖼️' : '📋') }}</span>
                                            <div>
                                                <a href="{{ $doc->url }}" target="_blank" class="text-[#385776] hover:underline font-medium text-xs">{{ $doc->original_name }}</a>
                                                @if($doc->notes)
                                                    <p class="text-xs text-gray-400 mt-0.5">{{ $doc->notes }}</p>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-2.5"><span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">{{ $docCategorias[$doc->category] ?? $doc->category }}</span></td>
                                    <td class="py-2.5 text-xs text-gray-600">{{ $doc->uploadedBy->name ?? '-' }}</td>
                                    <td class="py-2.5 text-xs text-gray-500">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                                    <td class="py-2.5 text-xs text-gray-500">{{ number_format($doc->size_bytes / 1024, 0, ',', '.') }} KB</td>
                                    <td class="py-2.5 text-right">
                                        <a href="{{ $doc->url }}" target="_blank" class="text-xs text-[#385776] hover:underline mr-2">Abrir</a>
                                        <form method="POST" action="{{ route('crm.accounts.delete-document', [$account->id, $doc->id]) }}" class="inline" onsubmit="return confirm('Remover este documento?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="text-xs text-red-500 hover:underline">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="text-center py-10 text-gray-400">
                    <p class="text-3xl mb-2">📎</p>
                    <p class="text-sm">Nenhum documento enviado.</p>
                    <p class="text-xs mt-1">Clique em "+ Enviar Documento" para anexar arquivos.</p>
                </div>
            @endif
        </div>
    </div>
{{-- Modal Nova Oportunidade --}}
<div id="modal-new-opp" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-[#1B334A] mb-4">Nova Oportunidade</h3>
        <form method="POST" action="{{ route('crm.accounts.create-opp', $account->id) }}" class="space-y-3">
            @csrf
            <input type="text" name="title" placeholder="Título" class="w-full border rounded-lg px-3 py-2 text-sm">
            <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm"><option value="aquisicao">Aquisição</option><option value="carteira">Carteira</option></select>
            <input type="text" name="area" placeholder="Área do Direito (opcional)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <input type="text" name="source" placeholder="Fonte (WhatsApp, Indicação...)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#385776] text-white rounded-lg text-sm">Criar</button>
                <button type="button" onclick="document.getElementById('modal-new-opp').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm">Cancelar</button>
            </div>
        </form>
    </div>
</div>


@if(auth()->user()->isAdmin())
{{-- Modal de confirmação de exclusão --}}
<div id="delete-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50">
    <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md mx-4">
        <h2 class="text-lg font-bold text-red-600 mb-2">Excluir conta permanentemente</h2>
        <p class="text-sm text-gray-600 mb-1">Tem certeza que deseja excluir <strong>{{ $account->name }}</strong>?</p>
        <p class="text-xs text-gray-400 mb-4">Isso removerá a conta, identidades, atividades e oportunidades vinculadas. Esta ação não pode ser desfeita.</p>
        <div class="flex gap-3 justify-end">
            <button onclick="document.getElementById('delete-modal').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
            <form action="{{ route('crm.accounts.destroy', $account->id) }}" method="POST">
                @csrf @method('DELETE')
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg text-sm hover:bg-red-700 font-medium">Sim, excluir</button>
            </form>
        </div>
    </div>
</div>
@endif

@endsection



{{-- ================================================================== --}}
{{-- ABA PULSO DO CLIENTE                                               --}}
{{-- ================================================================== --}}
<div id="tab-pulso" class="tab-content hidden">
    <div class="bg-white rounded-lg shadow-sm border p-6">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold text-[#1B334A]">Pulso do Cliente</h2>
            <div id="pulso-badge"></div>
        </div>
        <div id="pulso-loading" class="text-center py-8 text-gray-400">Carregando dados do Pulso...</div>
        <div id="pulso-content" class="hidden">
            <div class="grid grid-cols-3 gap-4 mb-6">
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Média 7 dias</p>
                    <p id="pulso-media" class="text-2xl font-bold text-[#1B334A]">—</p>
                    <p class="text-xs text-gray-400">contatos/dia</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Classificação</p>
                    <p id="pulso-class" class="text-lg font-bold">—</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 text-center">
                    <p class="text-xs text-gray-400 mb-1">Alertas Ativos</p>
                    <p id="pulso-alertas-count" class="text-2xl font-bold text-[#1B334A]">—</p>
                </div>
            </div>
            {{-- Gráfico (barras simples via divs) --}}
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-600 mb-2">Contatos por dia (últimos 30 dias)</h3>
                <div id="pulso-chart" class="flex items-end gap-1 h-32 border-b border-gray-200"></div>
            </div>
            {{-- Alertas --}}
            <div id="pulso-alertas-list"></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tabPulso = document.querySelector('[data-tab="pulso"]');
    if (!tabPulso) return;

    let pulsoLoaded = false;
    tabPulso.addEventListener('click', function() {
        if (pulsoLoaded) return;
        pulsoLoaded = true;

        fetch("{{ route('crm.accounts.pulso', $account->id) }}")
            .then(r => r.json())
            .then(data => {
                document.getElementById('pulso-loading').classList.add('hidden');
                document.getElementById('pulso-content').classList.remove('hidden');

                // Média
                document.getElementById('pulso-media').textContent = data.media_7d;

                // Classificação
                const classEl = document.getElementById('pulso-class');
                const cls = data.classificacao;
                const clsColors = {normal: 'text-green-600', atencao: 'text-yellow-600', excessivo: 'text-red-600'};
                classEl.textContent = cls.charAt(0).toUpperCase() + cls.slice(1);
                classEl.className = 'text-lg font-bold ' + (clsColors[cls] || '');

                // Badge no header
                const badge = document.getElementById('pulso-badge');
                const badgeColors = {normal: 'bg-green-100 text-green-700', atencao: 'bg-yellow-100 text-yellow-700', excessivo: 'bg-red-100 text-red-700'};
                badge.innerHTML = '<span class="px-2.5 py-1 rounded-full text-xs font-medium ' + (badgeColors[cls] || '') + '">' + cls.charAt(0).toUpperCase() + cls.slice(1) + '</span>';

                // Alertas count
                document.getElementById('pulso-alertas-count').textContent = data.alertas.length;

                // Chart
                const chart = document.getElementById('pulso-chart');
                const threshold = parseInt(data.thresholds.max_contatos_dia || 5);
                const maxVal = Math.max(...data.diarios.map(d => d.total_contatos), threshold, 1);

                chart.innerHTML = '';
                data.diarios.forEach(d => {
                    const pct = (d.total_contatos / maxVal * 100);
                    const color = d.total_contatos > threshold ? 'bg-red-400' : (d.has_movimentacao ? 'bg-green-400' : 'bg-[#385776]');
                    const bar = document.createElement('div');
                    bar.className = color + ' rounded-t flex-1 min-w-[6px] relative group cursor-default';
                    bar.style.height = Math.max(pct, 2) + '%';
                    bar.title = d.data + ': ' + d.total_contatos + ' contatos' + (d.has_movimentacao ? ' (com mov.)' : '');
                    chart.appendChild(bar);
                });

                // Threshold line
                const linePct = (threshold / maxVal * 100);
                chart.style.position = 'relative';
                const line = document.createElement('div');
                line.className = 'absolute w-full border-t-2 border-dashed border-red-300 pointer-events-none';
                line.style.bottom = linePct + '%';
                line.title = 'Limite: ' + threshold + ' contatos/dia';
                chart.appendChild(line);

                // Alertas list
                const alertasDiv = document.getElementById('pulso-alertas-list');
                if (data.alertas.length > 0) {
                    let html = '<h3 class="text-sm font-medium text-gray-600 mb-2">Alertas ativos</h3><div class="space-y-2">';
                    data.alertas.forEach(a => {
                        const tColors = {diario_excedido: 'bg-red-50 border-red-200', semanal_excedido: 'bg-orange-50 border-orange-200', reiteracao: 'bg-yellow-50 border-yellow-200', fora_horario: 'bg-purple-50 border-purple-200'};
                        html += '<div class="p-3 rounded-lg border text-xs ' + (tColors[a.tipo] || 'bg-gray-50') + '">';
                        html += '<span class="font-medium">' + a.tipo.replace(/_/g, ' ') + '</span> — ' + a.descricao;
                        html += '<span class="text-gray-400 ml-2">' + new Date(a.created_at).toLocaleDateString('pt-BR') + '</span>';
                        html += '</div>';
                    });
                    html += '</div>';
                    alertasDiv.innerHTML = html;
                }
            })
            .catch(err => {
                document.getElementById('pulso-loading').textContent = 'Erro ao carregar dados do Pulso.';
                console.error('Pulso error:', err);
            });
    });
});

// =====================================================================
// NEXO — ações inline na ficha do cliente
// =====================================================================
function crmNexoEnviar(notifId) {
    const msg = document.getElementById('crm-nexo-msg-' + notifId).value.trim();
    if (!msg) { alert('Escreva ou confirme a mensagem antes de enviar.'); return; }
    if (!confirm('Enviar esta mensagem via WhatsApp ao cliente?')) return;

    const btn = event.target.closest('button');
    btn.disabled = true;
    btn.textContent = 'Enviando...';

    fetch(`/nexo/notificacoes/${notifId}/aprovar`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ descricao_custom: msg }),
    })
    .then(r => r.json())
    .then(data => {
        const card = document.getElementById('crm-nexo-card-' + notifId);
        if (data.success) {
            card.innerHTML = '<div class="px-5 py-3 flex items-center gap-2 text-green-700 text-sm"><svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>WhatsApp enviado — registrado na linha do tempo do cliente.</div>';
            setTimeout(() => {
                card.style.transition = 'opacity 0.4s';
                card.style.opacity = '0';
                setTimeout(() => {
                    card.remove();
                    if (document.querySelectorAll('[id^="crm-nexo-card-"]').length === 0) {
                        const banner = document.getElementById('nexo-pendentes-banner');
                        if (banner) banner.remove();
                    }
                }, 400);
            }, 2500);
        } else {
            btn.disabled = false;
            btn.textContent = 'Enviar WhatsApp ao cliente';
            alert('Erro ao enviar: ' + (data.error || data.message || 'Falha desconhecida'));
        }
    })
    .catch(e => {
        btn.disabled = false;
        btn.textContent = 'Enviar WhatsApp ao cliente';
        alert('Erro de rede: ' + e.message);
    });
}

function crmNexoDescartar(notifId) {
    if (!confirm('Confirma que não irá notificar o cliente sobre este andamento?\n\nA decisão será registrada na linha do tempo do CRM.')) return;

    fetch(`/nexo/notificacoes/${notifId}/descartar`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            'Content-Type': 'application/json'
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            const card = document.getElementById('crm-nexo-card-' + notifId);
            card.style.transition = 'opacity 0.3s';
            card.style.opacity = '0';
            setTimeout(() => {
                card.remove();
                if (document.querySelectorAll('[id^="crm-nexo-card-"]').length === 0) {
                    const banner = document.getElementById('nexo-pendentes-banner');
                    if (banner) banner.remove();
                }
            }, 300);
        }
    })
    .catch(e => alert('Erro de rede: ' + e.message));
}
</script>
@endpush

@push('scripts')
<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('border-[#385776]', 'text-[#385776]');
        btn.classList.add('border-transparent', 'text-gray-500');
    });
    document.getElementById('tab-' + tab).classList.remove('hidden');
    const activeBtn = document.querySelector('[data-tab="' + tab + '"]');
    activeBtn.classList.add('border-[#385776]', 'text-[#385776]');
    activeBtn.classList.remove('border-transparent', 'text-gray-500');
    history.replaceState(null, '', '#' + tab);
}

// Ativar aba da URL hash
if (window.location.hash) {
    const hash = window.location.hash.replace('#', '');
    if (hash.startsWith('activity-')) {
        switchTab('atividades');
        setTimeout(() => {
            const el = document.getElementById(hash);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                el.style.outline = '2px solid #385776';
                el.style.outlineOffset = '3px';
                setTimeout(() => { el.style.outline = ''; el.style.outlineOffset = ''; }, 3000);
            }
        }, 100);
    } else if (document.getElementById('tab-' + hash)) {
        switchTab(hash);
    }
}

function saveAccountCrm() {
    const form = document.getElementById('form-crm-update');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token') body[k] = v; });
    if (body.tags) body.tags = JSON.stringify(body.tags.split(',').map(t => t.trim()).filter(Boolean));
    fetch('{{ route("crm.accounts.update", $account->id) }}', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => {
        if (d.ok) { const fb = document.getElementById('save-feedback'); fb.classList.remove('hidden'); setTimeout(() => fb.classList.add('hidden'), 2000); }
    });
}

function saveActivity() {
    const form = document.getElementById('form-activity');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token') body[k] = v; });
    const btn = document.querySelector('#form-activity button[type="button"]');
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    fetch('{{ route("crm.accounts.store-activity", $account->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            btn.textContent = '✓ Registrado!';
            btn.classList.remove('bg-green-600'); btn.classList.add('bg-green-500');
            setTimeout(() => { window.location.href = window.location.pathname + '#atividades'; location.reload(); }, 600);
        } else {
            btn.textContent = 'Erro — tente novamente'; btn.classList.remove('bg-green-600'); btn.classList.add('bg-red-500'); btn.disabled = false;
            setTimeout(() => { btn.textContent = 'Registrar'; btn.classList.remove('bg-red-500'); btn.classList.add('bg-green-600'); }, 2000);
        }
    }).catch(e => {
        btn.textContent = 'Erro — tente novamente'; btn.classList.remove('bg-green-600'); btn.classList.add('bg-red-500'); btn.disabled = false;
        setTimeout(() => { btn.textContent = 'Registrar'; btn.classList.remove('bg-red-500'); btn.classList.add('bg-green-600'); }, 2000);
    });
}

function openCompleteModal(activityId, mode = 'concluir') {
    document.getElementById('complete-activity-id').value = activityId;
    document.getElementById('complete-mode').value = mode;
    document.getElementById('complete-status').value = 'procedente';
    document.getElementById('complete-notes').value = '';
    const isCiente = mode === 'ciente';
    document.getElementById('modal-complete-title').textContent = isCiente ? 'Registrar Ciência' : 'Concluir Atividade';
    document.getElementById('modal-complete-btn').textContent = isCiente ? 'Registrar' : 'Concluir';
    document.getElementById('complete-status-wrap').style.display = isCiente ? 'none' : '';
    document.getElementById('complete-notes-label').innerHTML = isCiente
        ? 'Observação <span class="text-gray-400">(opcional)</span>'
        : 'Anotações <span class="text-red-500">*</span>';
    document.getElementById('complete-notes').placeholder = isCiente
        ? 'Adicione uma observação (opcional)...'
        : 'Descreva o que foi feito, resultado obtido...';
    document.getElementById('modal-complete').classList.remove('hidden');
}

function submitComplete() {
    const activityId = document.getElementById('complete-activity-id').value;
    const mode = document.getElementById('complete-mode').value;
    const isCiente = mode === 'ciente';
    const status = isCiente ? 'ciente' : document.getElementById('complete-status').value;
    const notes = document.getElementById('complete-notes').value.trim();
    if (!isCiente && !notes) { alert('Anotações são obrigatórias'); return; }
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    fetch('{{ url("crm/accounts") }}/{{ $account->id }}/activities/' + activityId + '/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ resolution_status: status, resolution_notes: notes || null })
    }).then(r => r.json()).then(d => {
        if (d.ok) { location.reload(); }
        else { alert(d.error || 'Erro ao concluir'); btn.disabled = false; btn.textContent = isCiente ? 'Registrar' : 'Concluir'; }
    }).catch(() => { alert('Erro de conexão'); btn.disabled = false; btn.textContent = isCiente ? 'Registrar' : 'Concluir'; });
}

function transferOwner() {
    const newOwner = document.getElementById('transfer-owner').value;
    const reason = document.getElementById('transfer-reason').value;
    if (!newOwner) { alert('Selecione o novo responsável'); return; }
    if (!confirm('Confirma a transferência de responsável?')) return;
    fetch('{{ route("crm.accounts.transfer", $account->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ new_owner_id: newOwner, reason: reason })
    }).then(r => r.json()).then(d => {
        if (d.ok) { alert('Transferido para ' + d.new_owner); location.reload(); }
        else { alert(d.error || 'Erro na transferência'); }
    }).catch(() => alert('Erro de conexão'));
}

function archiveAccount() {
    const reason = document.getElementById('archive-reason').value;
    if (!reason) { alert('Informe o motivo do arquivamento'); return; }
    if (!confirm('Confirma o arquivamento desta conta?')) return;
    fetch('{{ route("crm.accounts.archive", $account->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ reason: reason })
    }).then(r => r.json()).then(d => {
        if (d.ok) { alert('Conta arquivada'); location.reload(); }
        else { alert(d.error || 'Erro'); }
    }).catch(() => alert('Erro de conexão'));
}

function unarchiveAccount() {
    if (!confirm('Reativar esta conta?')) return;
    fetch('{{ route("crm.accounts.unarchive", $account->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
    }).then(r => r.json()).then(d => {
        if (d.ok) { alert('Conta reativada'); location.reload(); }
        else { alert(d.error || 'Erro'); }
    }).catch(() => alert('Erro de conexão'));
}

document.querySelector('select[name="type"]').addEventListener('change', function() {
    if (this.value === 'visit') { openVisitModal(); this.value = 'call'; }
});
function openVisitModal() { document.getElementById('modal-visit').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
function closeVisitModal() { document.getElementById('modal-visit').classList.add('hidden'); document.body.style.overflow = ''; }
function selectReceptivity(el) {
    document.querySelectorAll('#visit-receptivity-pills button').forEach(function(b) {
        b.className = 'px-3 py-1.5 rounded-full text-xs font-semibold border-2 border-gray-200 bg-white text-gray-500';
    });
    var val = el.getAttribute('data-val');
    document.getElementById('visit_receptivity').value = val;
    var styles = { positiva: 'border-green-500 bg-green-50 text-green-800', neutra: 'border-yellow-500 bg-yellow-50 text-yellow-800', negativa: 'border-red-500 bg-red-50 text-red-800' };
    el.className = 'px-3 py-1.5 rounded-full text-xs font-semibold border-2 ' + styles[val];
}
function sanitizeText(str) {
    if (!str) return str;
    return str.replace(/[\u200B-\u200D\uFEFF\u00A0]/g, ' ').replace(/[\u201C\u201D]/g, '"').replace(/[\u201E]/g, '"').replace(/[\u2018\u2019]/g, "'").replace(/[\u2013\u2014]/g, '-').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
}
function saveVisit(generatePdf) {
    var arrival = document.getElementById('visit_arrival').value;
    var departure = document.getElementById('visit_departure').value;
    var transport = document.getElementById('visit_transport').value;
    var objective = document.getElementById('visit_objective').value;
    var body = sanitizeText(document.getElementById('visit_body').value.trim());
    var visitDate = document.getElementById('visit_date').value;
    if (!arrival || !departure || !transport || !objective || !body) { alert('Preencha os campos obrigatorios: Hora de Chegada, Hora de Saida, Meio de Deslocamento, Objetivo e Relato.'); return; }
    var btn = generatePdf ? document.getElementById('btn-visit-pdf') : document.getElementById('btn-visit-save');
    btn.disabled = true; var origText = btn.innerHTML; btn.innerHTML = 'Salvando...';
    var purposeMap = { prospeccao: 'comercial', cobranca: 'cobranca', relacionamento: 'relacionamento', assinatura: 'assinatura', reuniao_estrategica: 'estrategica' };
    var payload = {
        type: 'visit', purpose: purposeMap[objective] || 'acompanhamento',
        title: 'Visita Presencial - ' + visitDate, body: body,
        decisions: sanitizeText(document.getElementById('visit_decisions').value.trim()) || null,
        pending_items: sanitizeText(document.getElementById('visit_pending').value.trim()) || null,
        due_at: document.getElementById('visit_next_contact').value || null,
        visit_arrival_time: arrival, visit_departure_time: departure,
        visit_transport: transport,
        visit_location: document.getElementById('visit_location').value.trim() || null,
        visit_attendees: document.getElementById('visit_attendees').value.trim() || null,
        visit_objective: objective,
        visit_receptivity: document.getElementById('visit_receptivity').value || null,
        visit_next_contact: document.getElementById('visit_next_contact').value || null,
    };
    fetch('{{ route("crm.accounts.store-activity", $account->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(payload)
    }).then(function(r) {
        if (!r.ok) { return r.json().then(function(err) { throw err; }); }
        return r.json();
    }).then(function(d) {
        if (d.ok) {
            btn.innerHTML = 'Registrado!';
            if (generatePdf) { window.open('{{ url("crm/accounts") }}/{{ $account->id }}/activities/' + d.id + '/pdf', '_blank'); }
            setTimeout(function() { window.location.href = window.location.pathname + '#atividades'; location.reload(); }, 800);
        } else { btn.innerHTML = origText; btn.disabled = false; alert(d.message || 'Erro ao salvar'); }
    }).catch(function(err) {
        btn.innerHTML = origText; btn.disabled = false;
        if (err && err.errors) {
            var msgs = Object.values(err.errors).flat().join('\n');
            alert('Erro de validacao:\n' + msgs);
        } else if (err && err.message) {
            alert('Erro: ' + err.message);
        } else {
            alert('Erro de conexao');
        }
    });
}
document.addEventListener('keydown', function(e) { var mv = document.getElementById('modal-visit'); if (mv && e.key === 'Escape' && !mv.classList.contains('hidden')) closeVisitModal(); });
document.addEventListener('DOMContentLoaded', function() { var mv = document.getElementById('modal-visit'); if (mv) mv.addEventListener('click', function(e) { if (e.target === this) closeVisitModal(); }); });

@if(auth()->user()->isAdmin())
function openEditModal() { document.getElementById('modal-edit-account').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
function closeEditModal() { document.getElementById('modal-edit-account').classList.add('hidden'); document.body.style.overflow = ''; }
function saveEditAccount() {
    var form = document.getElementById('form-edit-account');
    var data = {};
    new FormData(form).forEach(function(v, k) { data[k] = v; });
    var btn = document.getElementById('btn-edit-save');
    btn.disabled = true; btn.textContent = 'Salvando...';
    fetch('{{ route("crm.accounts.update", $account->id) }}', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(data)
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.ok) { location.reload(); }
        else {
            btn.disabled = false; btn.textContent = 'Salvar';
            var msg = d.message || (d.errors ? Object.values(d.errors).flat().join('\n') : 'Erro ao salvar');
            alert(msg);
        }
    }).catch(function() { btn.disabled = false; btn.textContent = 'Salvar'; alert('Erro de conexão'); });
}
document.addEventListener('keydown', function(e) { var m = document.getElementById('modal-edit-account'); if (m && e.key === 'Escape' && !m.classList.contains('hidden')) closeEditModal(); });
document.addEventListener('DOMContentLoaded', function() { var m = document.getElementById('modal-edit-account'); if (m) m.addEventListener('click', function(e) { if (e.target === this) closeEditModal(); }); });
@endif
</script>

<!-- ======= MODAL VISITA PRESENCIAL ======= -->
<div id="modal-visit" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(27,51,74,0.55);backdrop-filter:blur(4px)">
    <div class="bg-white rounded-2xl w-full max-w-2xl max-h-[90vh] flex flex-col shadow-2xl" style="animation:slideUp .3s ease">
        <div class="px-6 py-4 rounded-t-2xl flex items-center justify-between" style="background:linear-gradient(135deg,#1B334A,#385776)">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" style="background:rgba(255,255,255,.15)">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                </div>
                <div>
                    <h2 class="text-white font-bold text-lg">Relat&oacute;rio de Visita Presencial</h2>
                    <p class="text-white/60 text-xs">{{ $account->name }}</p>
                </div>
            </div>
            <button onclick="closeVisitModal()" class="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 overflow-y-auto flex-1 space-y-5">
            <div>
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#385776] border-b-2 border-gray-100 pb-1 mb-3">⏰ Identificação da Visita</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Data da Visita <span class="text-red-500">*</span></label><input type="date" id="visit_date" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50" value="{{ date('Y-m-d') }}"></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Respons&aacute;vel</label><select id="visit_responsible" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50">@foreach(\App\Models\User::whereNotNull('datajuri_proprietario_id')->orderBy('name')->get() as $u)<option value="{{ $u->id }}" {{ $u->id == auth()->id() ? 'selected' : '' }}>{{ $u->name }}</option>@endforeach</select></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Hora de Chegada <span class="text-red-500">*</span></label><input type="time" id="visit_arrival" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Hora de Sa&iacute;da <span class="text-red-500">*</span></label><input type="time" id="visit_departure" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Meio de Deslocamento <span class="text-red-500">*</span></label><select id="visit_transport" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"><option value="">Selecione...</option><option value="carro_proprio">🚗 Carro próprio</option><option value="aplicativo">📱 Aplicativo (Uber/99)</option><option value="taxi">🚕 Táxi</option><option value="transporte_publico">🚌 Transporte público</option><option value="a_pe">🚶 A pé</option><option value="moto">🏍 Moto</option><option value="outro">Outro</option></select></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Local da Visita</label><input type="text" id="visit_location" placeholder="Endereco ou sede do cliente" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></div>
                </div>
            </div>
            <div>
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#385776] border-b-2 border-gray-100 pb-1 mb-3">Participantes</div>
                <div><label class="text-xs font-semibold text-gray-500 block mb-1">Pessoas presentes (lado do cliente)</label><input type="text" id="visit_attendees" placeholder="Nome e cargo" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></div>
            </div>
            <div>
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#385776] border-b-2 border-gray-100 pb-1 mb-3">✍️ Conteúdo da Visita</div>
                <div class="space-y-3">
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Objetivo <span class="text-red-500">*</span></label><select id="visit_objective" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"><option value="">Selecione...</option><option value="acompanhamento">📋 Acompanhamento processual</option><option value="relacionamento">🤝 Relacionamento</option><option value="prospeccao">💼 Prospecção comercial</option><option value="cobranca">💰 Cobrança</option><option value="entrega_docs">📦 Entrega de documentos</option><option value="assinatura">✍️ Assinatura de contrato</option><option value="reuniao_estrategica">📊 Reunião estratégica</option><option value="outro">Outro</option></select></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Relato da Visita <span class="text-red-500">*</span></label><textarea id="visit_body" rows="4" placeholder="Descreva detalhadamente..." class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></textarea></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Decisoes / Encaminhamentos</label><textarea id="visit_decisions" rows="2" placeholder="Decisoes e proximos passos..." class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></textarea></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Pendencias Geradas</label><textarea id="visit_pending" rows="2" placeholder="Pendencias abertas..." class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></textarea></div>
                </div>
            </div>
            <div>
                <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-[#385776] border-b-2 border-gray-100 pb-1 mb-3">📅 Follow-up & Percepção</div>
                <div class="grid grid-cols-2 gap-3">
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Proximo Contato</label><input type="date" id="visit_next_contact" class="w-full border rounded-lg px-3 py-2 text-sm bg-gray-50"></div>
                    <div><label class="text-xs font-semibold text-gray-500 block mb-1">Receptividade</label><div class="flex gap-2 mt-1" id="visit-receptivity-pills"><button type="button" data-val="positiva" onclick="selectReceptivity(this)" class="px-3 py-1.5 rounded-full text-xs font-semibold border-2 border-gray-200 bg-white text-gray-500">😊 Positiva</button><button type="button" data-val="neutra" onclick="selectReceptivity(this)" class="px-3 py-1.5 rounded-full text-xs font-semibold border-2 border-gray-200 bg-white text-gray-500">😐 Neutra</button><button type="button" data-val="negativa" onclick="selectReceptivity(this)" class="px-3 py-1.5 rounded-full text-xs font-semibold border-2 border-gray-200 bg-white text-gray-500">😞 Negativa</button></div><input type="hidden" id="visit_receptivity" value=""></div>
                </div>
            </div>
        </div>
        <div class="px-6 py-3 border-t bg-gray-50 rounded-b-2xl flex justify-between items-center">
            <button onclick="closeVisitModal()" class="px-4 py-2 text-sm text-gray-500 border rounded-lg hover:bg-gray-100 font-medium">Cancelar</button>
            <div class="flex gap-2">
                <button onclick="saveVisit(true)" id="btn-visit-pdf" class="px-4 py-2 text-sm text-[#385776] border border-[#385776] rounded-lg hover:bg-blue-50 font-medium">Salvar + PDF</button>
                <button onclick="saveVisit(false)" id="btn-visit-save" class="px-4 py-2 text-sm text-white rounded-lg font-medium" style="background:linear-gradient(135deg,#1B334A,#385776)">Registrar Visita</button>
            </div>
        </div>
    </div>
</div>
<style>@keyframes slideUp{from{opacity:0;transform:translateY(20px)scale(.97)}to{opacity:1;transform:translateY(0)scale(1)}}</style>

@if(auth()->user()->isAdmin())
<!-- ======= MODAL EDITAR CONTA (ADMIN) ======= -->
<div id="modal-edit-account" class="hidden fixed inset-0 z-50 flex items-center justify-center" style="background:rgba(27,51,74,0.55);backdrop-filter:blur(4px)">
    <div class="bg-white rounded-2xl w-full max-w-xl max-h-[90vh] flex flex-col shadow-2xl" style="animation:slideUp .3s ease">
        <div class="px-6 py-4 rounded-t-2xl flex items-center justify-between" style="background:linear-gradient(135deg,#1B334A,#385776)">
            <div>
                <h2 class="text-white font-bold text-lg">Editar Conta</h2>
                <p class="text-white/60 text-xs">{{ $account->name }} · Admin</p>
            </div>
            <button onclick="closeEditModal()" class="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="px-6 py-5 overflow-y-auto flex-1">
            <form id="form-edit-account" class="space-y-4" onsubmit="return false">
                {{-- Identidade --}}
                <div class="text-xs font-bold uppercase tracking-wider text-[#385776] border-b-2 border-gray-100 pb-1 mb-3">Dados de identidade</div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tipo</label>
                        <select name="kind" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="client"   {{ $account->kind === 'client'   ? 'selected' : '' }}>Cliente</option>
                            <option value="prospect" {{ $account->kind === 'prospect' ? 'selected' : '' }}>Prospect</option>
                        </select>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ $account->name }}" maxlength="255" required
                               class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">E-mail</label>
                        <input type="email" name="email" value="{{ $account->email }}" maxlength="255"
                               class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Telefone (E.164)</label>
                        <input type="text" name="phone_e164" value="{{ $account->phone_e164 }}" maxlength="30"
                               class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="5549999999999">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">CPF / CNPJ</label>
                        <input type="text" name="doc_digits" value="{{ $account->doc_digits }}" maxlength="20"
                               class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Somente dígitos">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">ID DataJuri</label>
                        <input type="number" name="datajuri_pessoa_id" value="{{ $account->datajuri_pessoa_id }}" min="1"
                               class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="pessoa_id no DataJuri">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Profissão</label>
                        <input type="text" name="profissao" value="{{ $account->profissao ?? $cli->profissao ?? '' }}" maxlength="255"
                               class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="{{ $cli->profissao ?? 'Ex: Engenheiro' }}">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Nascimento</label>
                        <input type="date" name="data_nascimento"
                               value="{{ $account->data_nascimento ? $account->data_nascimento->format('Y-m-d') : ($cli->data_nascimento ?? '') }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Cidade</label>
                        <input type="text" name="endereco_cidade" value="{{ $account->endereco_cidade ?? $cli->endereco_cidade ?? '' }}" maxlength="100"
                               class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="{{ $cli->endereco_cidade ?? '' }}">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">UF</label>
                        <input type="text" name="endereco_estado" value="{{ $account->endereco_estado ?? $cli->endereco_estado ?? '' }}" maxlength="2"
                               class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="SC">
                    </div>
                </div>

                {{-- Gestão --}}
                <div class="text-xs font-bold uppercase tracking-wider text-[#385776] border-b-2 border-gray-100 pb-1 mt-5 mb-3">Gestão</div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Responsável</label>
                        <select name="owner_user_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="">— Sem responsável —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $account->owner_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Lifecycle</label>
                        <select name="lifecycle" class="w-full border rounded-lg px-3 py-2 text-sm">
                            <option value="onboarding" {{ $account->lifecycle === 'onboarding' ? 'selected' : '' }}>Onboarding</option>
                            <option value="ativo"      {{ $account->lifecycle === 'ativo'      ? 'selected' : '' }}>Ativo</option>
                            <option value="adormecido" {{ $account->lifecycle === 'adormecido' ? 'selected' : '' }}>Adormecido</option>
                            <option value="arquivado"  {{ $account->lifecycle === 'arquivado'  ? 'selected' : '' }}>Arquivado</option>
                            <option value="risco"      {{ $account->lifecycle === 'risco'      ? 'selected' : '' }}>Risco</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Saúde (0–100)</label>
                        <input type="number" name="health_score" value="{{ $account->health_score }}" min="0" max="100"
                               class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Próximo contato</label>
                        <input type="date" name="next_touch_at"
                               value="{{ $account->next_touch_at ? $account->next_touch_at->format('Y-m-d') : '' }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Tags (JSON ou texto livre)</label>
                    <input type="text" name="tags" value="{{ $account->tags }}" maxlength="1000"
                           class="w-full border rounded-lg px-3 py-2 text-sm" placeholder='["vip","cobrança"]'>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Notas internas</label>
                    <textarea name="notes" rows="3" maxlength="5000"
                              class="w-full border rounded-lg px-3 py-2 text-sm">{{ $account->notes }}</textarea>
                </div>
            </form>
        </div>
        <div class="px-6 py-3 border-t bg-gray-50 rounded-b-2xl flex justify-between items-center">
            <button onclick="closeEditModal()" class="px-4 py-2 text-sm text-gray-500 border rounded-lg hover:bg-gray-100 font-medium">Cancelar</button>
            <button onclick="saveEditAccount()" id="btn-edit-save"
                    class="px-5 py-2 text-sm text-white font-semibold rounded-lg"
                    style="background:linear-gradient(135deg,#1B334A,#385776)">Salvar</button>
        </div>
    </div>
</div>
@endif

{{-- ============================================================ --}}
{{-- JS — Painel Inadimplência                                     --}}
{{-- ============================================================ --}}
<script>
(function () {
    // --- Evidência de cobrança ---
    const formEv = document.getElementById('form-evidencia-cobranca');
    if (formEv) {
        const textarea = formEv.querySelector('textarea[name="descricao"]');
        const counter  = document.getElementById('desc-counter');
        const errBox   = document.getElementById('evidencia-error');
        const btnEnv   = document.getElementById('btn-enviar-evidencia');

        textarea?.addEventListener('input', () => {
            const len = textarea.value.length;
            counter.textContent = len + ' / 100 caracteres mínimos';
            counter.className = 'text-xs mt-0.5 ' + (len >= 100 ? 'text-green-600' : 'text-gray-400');
        });

        formEv.addEventListener('submit', async (e) => {
            e.preventDefault();
            errBox.classList.add('hidden');
            btnEnv.disabled = true;
            btnEnv.textContent = 'Enviando...';

            const fd = new FormData(formEv);
            const url = '/crm/accounts/{{ $account->id }}/inadimplencia/evidencia/{{ $inadTarefaAberta?->id ?? 0 }}';

            try {
                const r = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await r.json();
                if (r.ok && data.ok) {
                    window.location.reload();
                } else {
                    const msgs = data.errors ? Object.values(data.errors).flat().join(' ') : (data.message || 'Erro ao enviar.');
                    errBox.textContent = msgs;
                    errBox.classList.remove('hidden');
                    btnEnv.disabled = false;
                    btnEnv.textContent = 'Enviar evidência e fechar tarefa';
                }
            } catch {
                errBox.textContent = 'Falha de comunicação. Tente novamente.';
                errBox.classList.remove('hidden');
                btnEnv.disabled = false;
                btnEnv.textContent = 'Enviar evidência e fechar tarefa';
            }
        });
    }

    // --- Decisão de inadimplência (admin) ---
    const formDec = document.getElementById('form-decisao-inadimplencia');
    const errDec  = document.getElementById('decisao-error');

    window.abrirDecisao = function (decisao) {
        if (!formDec) return;
        formDec.classList.remove('hidden');
        document.getElementById('decisao-valor').value = decisao;
        const labels = {
            aguardar:   '⏸ Aguardar 30 dias — o sistema silenciará as notificações até a revisão.',
            renegociar: '🔄 Renegociar — uma oportunidade "Negociação" será criada no pipeline.',
            sinistrar:  '🔒 Sinistrar — contrato encerrado. Notificações de inadimplência suprimidas permanentemente.'
        };
        document.getElementById('decisao-label').textContent = labels[decisao] || '';
        const sinistroWrap = document.getElementById('sinistro-notas-wrap');
        sinistroWrap.classList.toggle('hidden', decisao !== 'sinistrar');
    };

    window.fecharDecisao = function () {
        formDec?.classList.add('hidden');
        if (errDec) errDec.classList.add('hidden');
    };

    formDec?.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (errDec) errDec.classList.add('hidden');
        const btn = formDec.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        const fd = new FormData(formDec);
        const url = '/crm/accounts/{{ $account->id }}/inadimplencia/decisao';

        try {
            const r = await fetch(url, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await r.json();
            if (r.ok && data.ok) {
                window.location.reload();
            } else {
                const msgs = data.errors ? Object.values(data.errors).flat().join(' ') : (data.error || data.message || 'Erro ao salvar.');
                if (errDec) { errDec.textContent = msgs; errDec.classList.remove('hidden'); }
                btn.disabled = false;
                btn.textContent = 'Confirmar decisão';
            }
        } catch {
            if (errDec) { errDec.textContent = 'Falha de comunicação.'; errDec.classList.remove('hidden'); }
            btn.disabled = false;
            btn.textContent = 'Confirmar decisão';
        }
    });
})();
</script>
@endpush
