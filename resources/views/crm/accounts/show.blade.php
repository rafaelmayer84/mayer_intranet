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
                    @if($commContext['has_wa'])
                        <a href="{{ route('nexo.atendimento') }}?conversation={{ $commContext['whatsapp']->id }}"
                           class="px-3 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 flex items-center gap-1.5">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                            NEXO
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
            <div><span class="text-gray-400 text-xs block">Cidade/UF</span><span class="text-gray-700">{{ $cli ? trim(($cli->endereco_cidade ?? '') . '/' . ($cli->endereco_estado ?? ''), '/') : '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Profissão</span><span class="text-gray-700">{{ $cli->profissao ?? '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Nascimento</span><span class="text-gray-700">{{ $cli && $cli->data_nascimento ? \Carbon\Carbon::parse($cli->data_nascimento)->format('d/m/Y') : '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Responsável</span><span class="text-gray-700 font-medium">{{ $account->owner?->name ?? $cli->proprietario_nome ?? '—' }}</span></div>
            <div><span class="text-gray-400 text-xs block">Último Contato</span><span class="text-gray-700">{{ $account->last_touch_at ? \Carbon\Carbon::parse($account->last_touch_at)->format('d/m/Y') : '—' }}</span></div>
        </div>
    </div>

    {{-- KPI CARDS --}}
    @if($hasDj)
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4"><p class="text-xs text-gray-400 mb-1">Receita Total</p><p class="text-lg font-bold text-[#1B334A]">R$ {{ number_format($djContext['receita_total'], 2, ',', '.') }}</p></div>
        <div class="bg-white rounded-lg shadow-sm border p-4"><p class="text-xs text-gray-400 mb-1">Processos Ativos</p><p class="text-lg font-bold text-[#1B334A]">{{ $processosAtivos }}</p><p class="text-xs text-gray-400">de {{ count($djContext['processos']) }} total</p></div>
        <div class="bg-white rounded-lg shadow-sm border p-4"><p class="text-xs text-gray-400 mb-1">Contratos</p><p class="text-lg font-bold text-[#1B334A]">{{ count($djContext['contratos']) }}</p></div>
        <div class="bg-white rounded-lg shadow-sm border p-4"><p class="text-xs text-gray-400 mb-1">A Receber</p><p class="text-lg font-bold text-[#1B334A]">R$ {{ number_format($contasAbertas->sum('valor'), 2, ',', '.') }}</p><p class="text-xs text-gray-400">{{ $contasAbertas->count() }} título(s)</p></div>
        <div class="bg-white rounded-lg shadow-sm border p-4"><p class="text-xs text-gray-400 mb-1">Vencidos</p><p class="text-lg font-bold {{ $contasVencidas->count() > 0 ? 'text-red-600' : 'text-green-600' }}">R$ {{ number_format($contasVencidas->sum('valor'), 2, ',', '.') }}</p><p class="text-xs {{ $contasVencidas->count() > 0 ? 'text-red-400' : 'text-gray-400' }}">{{ $contasVencidas->count() }} título(s)</p></div>
        <div class="bg-white rounded-lg shadow-sm border p-4"><p class="text-xs text-gray-400 mb-1">Último Movimento</p>@if($djContext['ultimo_movimento'])<p class="text-lg font-bold text-[#1B334A]">{{ \Carbon\Carbon::parse($djContext['ultimo_movimento']->data)->format('d/m/Y') }}</p><p class="text-xs text-gray-400">R$ {{ number_format(abs($djContext['ultimo_movimento']->valor), 2, ',', '.') }}</p>@else<p class="text-lg font-bold text-gray-300">—</p>@endif</div>
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
                {{-- Segmentação IA --}}
                @if(isset($segmentation) && $segmentation)
                <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-purple-700 font-semibold text-sm">🤖 Segmentação</span>
                        <span class="px-2 py-0.5 rounded-full text-xs bg-purple-200 text-purple-800">{{ $segmentation['segment'] }}</span>
                    </div>
                    <p class="text-sm text-purple-700/80">{{ $segmentation['summary'] }}</p>
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
                    <h3 class="text-sm font-medium text-gray-600 mb-2">Tickets NEXO ({{ count($commContext['tickets']) }})</h3>
                    <div class="space-y-2">
                        @foreach($commContext['tickets'] as $tk)
                        <div class="flex items-center justify-between border rounded-lg p-2.5 hover:bg-gray-50 text-sm">
                            <div><span class="font-medium text-gray-700">{{ $tk->assunto ?? 'Sem assunto' }}</span>@if($tk->protocolo ?? null)<span class="text-xs text-gray-400 ml-2">#{{ $tk->protocolo }}</span>@endif</div>
                            <div class="flex items-center gap-2"><span class="px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500">{{ str_replace('_', ' ', ucfirst($tk->status ?? '—')) }}</span><span class="text-xs text-gray-400">{{ $tk->created_at ? \Carbon\Carbon::parse($tk->created_at)->format('d/m/Y') : '' }}</span></div>
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
                        <div class="space-y-3">
                            @foreach($timeline as $item)
                            @php
                                $borderColor = match($item['subtype'] ?? $item['type']) { 'call' => 'border-blue-400', 'meeting' => 'border-purple-400', 'whatsapp' => 'border-green-400', 'task' => 'border-orange-400', 'note' => 'border-gray-300', 'event' => 'border-blue-300', default => 'border-gray-200' };
                                $icon = match($item['subtype'] ?? '') { 'call' => '📞', 'meeting' => '🤝', 'whatsapp' => '💬', 'task' => '✅', 'note' => '📝', default => '•' };
                            @endphp
                            <div class="flex gap-3 text-sm border-l-2 {{ $borderColor }} pl-3 py-1">
                                <span class="flex-shrink-0">{{ $icon }}</span>
                                <div class="flex-1">
                                    <p class="text-gray-800">{{ $item['title'] }}</p>
                                    @if(!empty($item['body']))<p class="text-gray-500 text-xs mt-0.5">{{ \Illuminate\Support\Str::limit($item['body'], 120) }}</p>@endif
                                </div>
                                <div class="text-xs text-gray-400 flex-shrink-0 text-right">
                                    {{ $item['date'] ? (is_string($item['date']) ? \Carbon\Carbon::parse($item['date'])->format('d/m H:i') : $item['date']->format('d/m H:i')) : '' }}
                                    @if($item['user'] ?? null)<br>{{ $item['user'] }}@endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- Painel Direito --}}
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-sm border p-6">
                    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Gestão CRM</h2>
                    <form id="form-crm-update" class="space-y-4">
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
                <div class="bg-gray-50 rounded-lg border p-4 text-xs text-gray-500 space-y-1">
                    <p><strong>Account ID:</strong> {{ $account->id }}</p>
                    @if($account->datajuri_pessoa_id)<p><strong>DataJuri ID:</strong> {{ $account->datajuri_pessoa_id }}</p>@endif
                    <p><strong>Criado:</strong> {{ $account->created_at?->format('d/m/Y') }}</p>
                </div>

                @if(in_array(auth()->user()->role, ['admin', 'coordenador', 'socio']))
                <div class="bg-white rounded-lg shadow-sm border p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-gray-700">Ações Administrativas</h3>
                    {{-- Transferir --}}
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
                    {{-- Arquivar --}}
                    @if($account->lifecycle !== 'arquivado')
                    <div class="border-t pt-3">
                        <input type="text" id="archive-reason" placeholder="Motivo do arquivamento" class="w-full border rounded-lg px-2 py-1.5 text-sm">
                        <button onclick="archiveAccount()" class="w-full mt-1 px-3 py-1.5 bg-gray-500 text-white rounded-lg text-xs hover:bg-gray-600">Arquivar Conta</button>
                    </div>
                    @else
                    <div class="border-t pt-3">
                        <button onclick="unarchiveAccount()" class="w-full px-3 py-1.5 bg-green-600 text-white rounded-lg text-xs hover:bg-green-700">Reativar Conta</button>
                    </div>
                    @endif
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
                            </select>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Natureza</label>
                            <select name="purpose" class="w-full border rounded-lg px-3 py-2 text-sm">
                                <option value="acompanhamento">Acompanhamento processual</option>
                                <option value="comercial">Comercial / Prospecção</option>
                                <option value="cobranca">Cobrança</option>
                                <option value="orientacao">Orientação jurídica</option>
                                <option value="documental">Documental</option>
                                <option value="agendamento">Agendamento</option>
                                <option value="retorno">Retorno de contato</option>
                                <option value="registro_interno">Registro interno</option>
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
                                $actColor = match($act->type) { 'call' => 'border-blue-400 bg-blue-50', 'meeting' => 'border-purple-400 bg-purple-50', 'whatsapp' => 'border-green-400 bg-green-50', 'note' => 'border-gray-300 bg-gray-50', 'email' => 'border-indigo-400 bg-indigo-50', default => 'border-gray-200 bg-gray-50' };
                                $purposeLabel = match($act->purpose ?? '') { 'acompanhamento' => 'Acompanhamento', 'comercial' => 'Comercial', 'cobranca' => 'Cobrança', 'orientacao' => 'Orientação', 'documental' => 'Documental', 'agendamento' => 'Agendamento', 'retorno' => 'Retorno', 'registro_interno' => 'Registro Interno', default => '' };
                            @endphp
                            <div class="border-l-4 {{ $actColor }} rounded-r-lg p-4">
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
                                                    default => 'bg-green-100 text-green-700',
                                                };
                                                $resLabel = match($act->resolution_status ?? '') {
                                                    'procedente' => 'Procedente',
                                                    'improcedente' => 'Improcedente',
                                                    'parcial' => 'Parcial',
                                                    'cancelada' => 'Cancelada',
                                                    default => 'Concluída',
                                                };
                                            @endphp
                                            <span class="px-1.5 py-0.5 rounded text-xs {{ $resCor }}">{{ $resLabel }}</span>
                                            @if($act->resolution_notes)
                                                <p class="text-xs text-gray-500 mt-1 italic">{{ Str::limit($act->resolution_notes, 80) }}</p>
                                            @endif
                                        @else
                                            <button onclick="openCompleteModal({{ $act->id }})" class="px-2 py-1 rounded text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 mt-1">✓ Concluir</button>
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
                            @if($msg->type !== 'text')<span class="text-xs opacity-60">[{{ $msg->type }}]</span>@endif
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

            {{-- Tickets NEXO --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">🎫 Tickets NEXO</h2>
                @if($commContext['has_tickets'])
                <div class="space-y-2">
                    @foreach($commContext['tickets'] as $tk)
                    @php
                        $tkObj = (object) $tk;
                        $tkStatus = $tkObj->status ?? 'aberto';
                        $tkCor = match(strtolower($tkStatus)) { 'resolvido','fechado' => 'bg-green-100 text-green-700', 'em andamento','em_andamento' => 'bg-blue-100 text-blue-700', default => 'bg-yellow-100 text-yellow-700' };
                    @endphp
                    <div class="border rounded-lg p-3 hover:bg-gray-50">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-gray-800 text-sm">{{ $tkObj->assunto ?? 'Ticket #' . ($tkObj->protocolo ?? $tkObj->id ?? '?') }}</span>
                            <span class="px-1.5 py-0.5 rounded text-xs {{ $tkCor }}">{{ ucfirst($tkStatus) }}</span>
                        </div>
                        <div class="flex items-center gap-3 mt-1 text-xs text-gray-400">
                            @if($tkObj->protocolo ?? null)<span>#{{ $tkObj->protocolo }}</span>@endif
                            @if($tkObj->tipo ?? null)<span>{{ $tkObj->tipo }}</span>@endif
                            @if($tkObj->created_at ?? null)<span>{{ \Carbon\Carbon::parse($tkObj->created_at)->format('d/m/Y') }}</span>@endif
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-gray-400 text-sm">Nenhum ticket encontrado.</p>
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
        </div>
    </div>
</div>

{{-- Modal Concluir Atividade --}}
<div id="modal-complete" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-[#1B334A] mb-4">Concluir Atividade</h3>
        <input type="hidden" id="complete-activity-id">
        <div class="space-y-3">
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Resultado</label>
                <select id="complete-status" class="w-full border rounded-lg px-3 py-2 text-sm">
                    <option value="procedente">✅ Procedente — realizado com sucesso</option>
                    <option value="improcedente">❌ Improcedente — não se aplica / indevido</option>
                    <option value="parcial">⚠️ Parcial — resolvido parcialmente</option>
                    <option value="cancelada">🚫 Cancelada — não foi possível realizar</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 mb-1 block">Anotações <span class="text-red-500">*</span></label>
                <textarea id="complete-notes" rows="3" placeholder="Descreva o que foi feito, resultado obtido..." class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            </div>
            <div class="flex gap-2">
                <button onclick="submitComplete()" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">Concluir</button>
                <button onclick="document.getElementById('modal-complete').classList.add('hidden')" class="px-4 py-2 border rounded-lg text-sm">Cancelar</button>
            </div>
        </div>
    </div>
</div>

<div id="tab-solicitacoes" class="tab-content hidden">
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-lg font-semibold text-[#1B334A]">Solicitações Internas</h2>
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
                            <option value="{{ $key }}" data-approval="{{ $cat['approval'] ? '1' : '0' }}">{{ $cat['label'] }}</option>
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
                                    <span class="text-gray-400">•</span>
                                    <span class="text-gray-400">por {{ $sr->requestedBy->name ?? '-' }}</span>
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

            {{-- Formulário de upload --}}
            <form id="form-upload-doc" method="POST" action="{{ route('crm.accounts.upload-document', $account->id) }}" enctype="multipart/form-data" class="hidden mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200 space-y-3">
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

@endsection

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
    const tab = window.location.hash.replace('#', '');
    if (document.getElementById('tab-' + tab)) switchTab(tab);
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

function openCompleteModal(activityId) {
    document.getElementById('complete-activity-id').value = activityId;
    document.getElementById('complete-status').value = 'procedente';
    document.getElementById('complete-notes').value = '';
    document.getElementById('modal-complete').classList.remove('hidden');
}

function submitComplete() {
    const activityId = document.getElementById('complete-activity-id').value;
    const status = document.getElementById('complete-status').value;
    const notes = document.getElementById('complete-notes').value.trim();
    if (!notes) { alert('Anotações são obrigatórias'); return; }
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    fetch('{{ url("crm/accounts") }}/{{ $account->id }}/activities/' + activityId + '/complete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify({ resolution_status: status, resolution_notes: notes })
    }).then(r => r.json()).then(d => {
        if (d.ok) { location.reload(); }
        else { alert(d.error || 'Erro ao concluir'); btn.disabled = false; btn.textContent = 'Concluir'; }
    }).catch(() => { alert('Erro de conexão'); btn.disabled = false; btn.textContent = 'Concluir'; });
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
</script>
@endpush
