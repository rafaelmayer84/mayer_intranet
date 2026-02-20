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
@endphp

<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.carteira') }}" class="hover:text-[#385776]">Carteira</a>
        <span>›</span>
        <span class="text-gray-700 font-medium">{{ $account->name }}</span>
    </div>

    {{-- ================================================================== --}}
    {{-- HEADER CARD — Dados pessoais + badges + ações rápidas             --}}
    {{-- ================================================================== --}}
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
                        @if($cli && $cli->status_pessoa)
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
                    </div>
                </div>
                <div class="flex gap-2">
                    @if($cli && $cli->celular)
                        @php $waPhone = preg_replace('/\D/', '', $cli->celular); if(!str_starts_with($waPhone, '55')) $waPhone = '55'.$waPhone; @endphp
                        <a href="https://wa.me/{{ $waPhone }}" target="_blank"
                           class="px-3 py-2 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600 flex items-center gap-1.5"
                           title="Abrir WhatsApp">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.11.547 4.093 1.504 5.815L0 24l6.335-1.652A11.943 11.943 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-1.86 0-3.63-.5-5.166-1.41l-.37-.22-3.834 1.005 1.022-3.734-.24-.382A9.71 9.71 0 012.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75z"/></svg>
                            WhatsApp
                        </a>
                    @endif
                    <button onclick="document.getElementById('modal-new-opp').classList.remove('hidden')"
                            class="px-3 py-2 bg-white/20 text-white rounded-lg text-sm hover:bg-white/30 backdrop-blur">
                        + Oportunidade
                    </button>
                </div>
            </div>
        </div>

        {{-- Dados pessoais grid --}}
        <div class="px-6 py-4 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-x-6 gap-y-3 text-sm">
            <div>
                <span class="text-gray-400 text-xs block">CPF/CNPJ</span>
                <span class="text-gray-700 font-medium">{{ $cli->cpf_cnpj ?? $account->doc_digits ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 text-xs block">Email</span>
                <span class="text-gray-700">{{ $cli->email ?? $account->email ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 text-xs block">Celular</span>
                <span class="text-gray-700">{{ $cli->celular ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 text-xs block">Cidade/UF</span>
                <span class="text-gray-700">{{ $cli ? trim(($cli->endereco_cidade ?? '') . '/' . ($cli->endereco_estado ?? ''), '/') : '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 text-xs block">Profissão</span>
                <span class="text-gray-700">{{ $cli->profissao ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 text-xs block">Nascimento</span>
                <span class="text-gray-700">{{ $cli && $cli->data_nascimento ? \Carbon\Carbon::parse($cli->data_nascimento)->format('d/m/Y') : '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 text-xs block">Responsável</span>
                <span class="text-gray-700 font-medium">{{ $account->owner?->name ?? $cli->proprietario_nome ?? '—' }}</span>
            </div>
            <div>
                <span class="text-gray-400 text-xs block">Último Contato</span>
                <span class="text-gray-700">{{ $account->last_touch_at ? \Carbon\Carbon::parse($account->last_touch_at)->format('d/m/Y') : '—' }}</span>
            </div>
            @if($cli && $cli->estado_civil)
            <div>
                <span class="text-gray-400 text-xs block">Estado Civil</span>
                <span class="text-gray-700">{{ $cli->estado_civil }}</span>
            </div>
            @endif
            @if($cli && $cli->rg)
            <div>
                <span class="text-gray-400 text-xs block">RG</span>
                <span class="text-gray-700">{{ $cli->rg }}</span>
            </div>
            @endif
        </div>
    </div>

    {{-- ================================================================== --}}
    {{-- KPI CARDS — Resumo financeiro e operacional                       --}}
    {{-- ================================================================== --}}
    @if($hasDj)
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Receita Total</p>
            <p class="text-lg font-bold text-[#1B334A]">R$ {{ number_format($djContext['receita_total'], 2, ',', '.') }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Processos Ativos</p>
            <p class="text-lg font-bold text-[#1B334A]">{{ $processosAtivos }}</p>
            <p class="text-xs text-gray-400">de {{ count($djContext['processos']) }} total</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Contratos</p>
            <p class="text-lg font-bold text-[#1B334A]">{{ count($djContext['contratos']) }}</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">A Receber (Aberto)</p>
            <p class="text-lg font-bold text-[#1B334A]">R$ {{ number_format($contasAbertas->sum('valor'), 2, ',', '.') }}</p>
            <p class="text-xs text-gray-400">{{ $contasAbertas->count() }} título(s)</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Vencidos</p>
            <p class="text-lg font-bold {{ $contasVencidas->count() > 0 ? 'text-red-600' : 'text-green-600' }}">
                R$ {{ number_format($contasVencidas->sum('valor'), 2, ',', '.') }}
            </p>
            <p class="text-xs {{ $contasVencidas->count() > 0 ? 'text-red-400' : 'text-gray-400' }}">{{ $contasVencidas->count() }} título(s)</p>
        </div>
        <div class="bg-white rounded-lg shadow-sm border p-4">
            <p class="text-xs text-gray-400 mb-1">Último Movimento</p>
            @if($djContext['ultimo_movimento'])
                <p class="text-lg font-bold text-[#1B334A]">{{ \Carbon\Carbon::parse($djContext['ultimo_movimento']->data)->format('d/m/Y') }}</p>
                <p class="text-xs text-gray-400">R$ {{ number_format(abs($djContext['ultimo_movimento']->valor), 2, ',', '.') }}</p>
            @else
                <p class="text-lg font-bold text-gray-300">—</p>
            @endif
        </div>
    </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- ============================================================== --}}
        {{-- COLUNA PRINCIPAL (2/3)                                         --}}
        {{-- ============================================================== --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- PROCESSOS --}}
            @if(!empty($djContext['processos']))
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">Processos ({{ count($djContext['processos']) }})</h2>
                    <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-700">{{ $processosAtivos }} ativo(s)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-400 border-b">
                                <th class="pb-2 pr-3">Pasta</th>
                                <th class="pb-2 pr-3">Número</th>
                                <th class="pb-2 pr-3">Tipo Ação</th>
                                <th class="pb-2 pr-3">Adverso</th>
                                <th class="pb-2 pr-3">Status</th>
                                <th class="pb-2 pr-3">Abertura</th>
                                <th class="pb-2">Advogado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($djContext['processos'] as $proc)
                            @php
                                $isAtivo = in_array($proc->status ?? '', ['Ativo','Em andamento','Em Andamento']);
                            @endphp
                            <tr class="border-b border-gray-50 {{ $isAtivo ? '' : 'opacity-60' }}">
                                <td class="py-2 pr-3 font-medium text-[#385776]">{{ $proc->pasta ?? '—' }}</td>
                                <td class="py-2 pr-3 text-gray-600 text-xs">{{ \Illuminate\Support\Str::limit($proc->numero ?? '—', 25) }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ \Illuminate\Support\Str::limit($proc->tipo_acao ?? '—', 30) }}</td>
                                <td class="py-2 pr-3 text-gray-600">{{ \Illuminate\Support\Str::limit($proc->adverso_nome ?? '—', 25) }}</td>
                                <td class="py-2 pr-3">
                                    <span class="px-1.5 py-0.5 rounded text-xs {{ $isAtivo ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                        {{ $proc->status ?? '—' }}
                                    </span>
                                </td>
                                <td class="py-2 pr-3 text-gray-500 text-xs">{{ $proc->data_abertura ? \Carbon\Carbon::parse($proc->data_abertura)->format('d/m/Y') : '—' }}</td>
                                <td class="py-2 text-gray-500 text-xs">{{ $proc->proprietario_nome ?? '—' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- CONTRATOS --}}
            @if(!empty($djContext['contratos']))
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Contratos ({{ count($djContext['contratos']) }})</h2>
                <div class="space-y-2">
                    @foreach($djContext['contratos'] as $ct)
                    <div class="flex items-center justify-between border rounded-lg p-3 hover:bg-gray-50">
                        <div>
                            <span class="font-medium text-gray-800">Contrato #{{ $ct->numero ?? $ct->id }}</span>
                            @if($ct->proprietario_nome ?? null)
                                <span class="text-xs text-gray-400 ml-2">{{ $ct->proprietario_nome }}</span>
                            @endif
                        </div>
                        <div class="text-right">
                            <span class="font-medium text-[#1B334A]">R$ {{ number_format($ct->valor ?? 0, 2, ',', '.') }}</span>
                            @if($ct->data_assinatura ?? null)
                                <span class="text-xs text-gray-400 block">{{ \Carbon\Carbon::parse($ct->data_assinatura)->format('d/m/Y') }}</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- CONTAS A RECEBER --}}
            @if(!empty($djContext['contas_receber']))
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold text-[#1B334A]">Contas a Receber ({{ count($djContext['contas_receber']) }})</h2>
                    @if($contasVencidas->count() > 0)
                        <span class="text-xs px-2 py-1 rounded-full bg-red-100 text-red-700">{{ $contasVencidas->count() }} vencida(s)</span>
                    @endif
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-400 border-b">
                                <th class="pb-2 pr-3">Descrição</th>
                                <th class="pb-2 pr-3">Valor</th>
                                <th class="pb-2 pr-3">Vencimento</th>
                                <th class="pb-2 pr-3">Pagamento</th>
                                <th class="pb-2">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($djContext['contas_receber'] as $cr)
                            @php
                                $isVencida = !in_array($cr->status ?? '', ['Concluído', 'Concluido', 'Excluido', 'Excluído']) && $cr->data_vencimento && $cr->data_vencimento < date('Y-m-d');
                                $isPaga = in_array($cr->status ?? '', ['Concluído', 'Concluido']);
                            @endphp
                            <tr class="border-b border-gray-50 {{ $isVencida ? 'bg-red-50/50' : '' }}">
                                <td class="py-2 pr-3 text-gray-600">{{ \Illuminate\Support\Str::limit($cr->descricao ?? '—', 40) }}</td>
                                <td class="py-2 pr-3 font-medium {{ $isVencida ? 'text-red-600' : 'text-gray-700' }}">R$ {{ number_format($cr->valor ?? 0, 2, ',', '.') }}</td>
                                <td class="py-2 pr-3 text-xs {{ $isVencida ? 'text-red-500 font-medium' : 'text-gray-500' }}">
                                    {{ $cr->data_vencimento ? \Carbon\Carbon::parse($cr->data_vencimento)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="py-2 pr-3 text-xs text-gray-500">
                                    {{ $cr->data_pagamento ? \Carbon\Carbon::parse($cr->data_pagamento)->format('d/m/Y') : '—' }}
                                </td>
                                <td class="py-2">
                                    @if($isPaga)
                                        <span class="px-1.5 py-0.5 rounded text-xs bg-green-100 text-green-700">Pago</span>
                                    @elseif($isVencida)
                                        <span class="px-1.5 py-0.5 rounded text-xs bg-red-100 text-red-700">Vencido</span>
                                    @elseif(($cr->status ?? '') === 'Excluido')
                                        <span class="px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Excluído</span>
                                    @else
                                        <span class="px-1.5 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700">Aberto</span>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            {{-- COMUNICAÇÃO: WhatsApp + Tickets --}}
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
                                <span class="font-medium text-gray-700">
                                    {{ $commContext['whatsapp']->last_message_at ? \Carbon\Carbon::parse($commContext['whatsapp']->last_message_at)->format('d/m/Y H:i') : '—' }}
                                </span>
                            </div>
                            <a href="{{ route('nexo.atendimento') }}?conversation={{ $commContext['whatsapp']->id }}"
                               class="px-2.5 py-1 bg-green-600 text-white rounded text-xs hover:bg-green-700">
                                Abrir NEXO
                            </a>
                        </div>
                    </div>
                    <div class="flex gap-4 mt-2 text-xs text-green-700">
                        <span>Status: <strong>{{ $commContext['whatsapp']->status === 'open' ? 'Aberta' : 'Fechada' }}</strong></span>
                        <span>Bot: <strong>{{ $commContext['whatsapp']->bot_ativo ? 'Ativo' : 'Inativo' }}</strong></span>
                        @if($commContext['whatsapp']->priority ?? null)
                            <span>Prioridade: <strong>{{ ucfirst($commContext['whatsapp']->priority) }}</strong></span>
                        @endif
                    </div>
                </div>
                @endif

                @if($commContext['has_tickets'])
                <h3 class="text-sm font-medium text-gray-600 mb-2">Tickets NEXO ({{ count($commContext['tickets']) }})</h3>
                <div class="space-y-2">
                    @foreach($commContext['tickets'] as $tk)
                    @php
                        $tkStatusColors = [
                            'aberto'       => 'bg-blue-100 text-blue-700',
                            'em_andamento' => 'bg-yellow-100 text-yellow-700',
                            'concluido'    => 'bg-green-100 text-green-700',
                            'cancelado'    => 'bg-gray-100 text-gray-500',
                        ];
                    @endphp
                    <div class="flex items-center justify-between border rounded-lg p-2.5 hover:bg-gray-50 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">{{ $tk->assunto ?? 'Sem assunto' }}</span>
                            @if($tk->protocolo ?? null)
                                <span class="text-xs text-gray-400 ml-2">#{{ $tk->protocolo }}</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-1.5 py-0.5 rounded text-xs {{ $tkStatusColors[$tk->status] ?? 'bg-gray-100 text-gray-500' }}">
                                {{ str_replace('_', ' ', ucfirst($tk->status ?? '—')) }}
                            </span>
                            <span class="text-xs text-gray-400">{{ $tk->created_at ? \Carbon\Carbon::parse($tk->created_at)->format('d/m/Y') : '' }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
            @endif

            {{-- OPORTUNIDADES --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Oportunidades CRM</h2>
                @if($account->opportunities->isEmpty())
                    <p class="text-gray-400 text-sm">Nenhuma oportunidade registrada.</p>
                @else
                    <div class="space-y-3">
                        @foreach($account->opportunities as $opp)
                        <a href="{{ route('crm.opportunities.show', $opp->id) }}"
                           class="block border rounded-lg p-3 hover:bg-gray-50 transition">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="font-medium text-gray-800">{{ $opp->title }}</span>
                                    <div class="flex gap-2 mt-1">
                                        <span class="text-xs px-1.5 py-0.5 rounded" style="background-color: {{ $opp->stage?->color ?? '#eee' }}20; color: {{ $opp->stage?->color ?? '#666' }}">
                                            {{ $opp->stage?->name ?? '?' }}
                                        </span>
                                        <span class="text-xs px-1.5 py-0.5 rounded {{ $opp->status === 'won' ? 'bg-green-100 text-green-700' : ($opp->status === 'lost' ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600') }}">
                                            {{ ucfirst($opp->status) }}
                                        </span>
                                    </div>
                                </div>
                                @if($opp->value_estimated)
                                    <span class="text-sm font-medium text-gray-700">R$ {{ number_format($opp->value_estimated, 2, ',', '.') }}</span>
                                @endif
                            </div>
                        </a>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- TIMELINE --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Timeline</h2>
                @if($timeline->isEmpty())
                    <p class="text-gray-400 text-sm">Nenhum evento registrado.</p>
                @else
                    <div class="space-y-3">
                        @foreach($timeline as $item)
                        @php
                            $borderColor = match($item['subtype'] ?? $item['type']) {
                                'call'      => 'border-blue-400',
                                'meeting'   => 'border-purple-400',
                                'whatsapp'  => 'border-green-400',
                                'task'      => 'border-orange-400',
                                'note'      => 'border-gray-300',
                                'event'     => 'border-blue-300',
                                default     => 'border-gray-200',
                            };
                            $icon = match($item['subtype'] ?? '') {
                                'call'      => '📞',
                                'meeting'   => '🤝',
                                'whatsapp'  => '💬',
                                'task'      => '✅',
                                'note'      => '📝',
                                default     => '•',
                            };
                        @endphp
                        <div class="flex gap-3 text-sm border-l-2 {{ $borderColor }} pl-3 py-1">
                            <span class="flex-shrink-0">{{ $icon }}</span>
                            <div class="flex-1">
                                <p class="text-gray-800">{{ $item['title'] }}</p>
                                @if(!empty($item['body']))
                                    <p class="text-gray-500 text-xs mt-0.5">{{ \Illuminate\Support\Str::limit($item['body'], 120) }}</p>
                                @endif
                            </div>
                            <div class="text-xs text-gray-400 flex-shrink-0 text-right">
                                {{ $item['date'] ? (is_string($item['date']) ? \Carbon\Carbon::parse($item['date'])->format('d/m H:i') : $item['date']->format('d/m H:i')) : '' }}
                                @if($item['user'] ?? null)
                                    <br>{{ $item['user'] }}
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- ============================================================== --}}
        {{-- PAINEL DIREITO (1/3) — Gestão CRM                            --}}
        {{-- ============================================================== --}}
        <div class="space-y-6">
            {{-- Gestão CRM --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Gestão CRM</h2>
                <form id="form-crm-update" class="space-y-4">
                    @csrf
                    <div>
                        <label class="text-xs text-gray-500">Responsável</label>
                        <select name="owner_user_id" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                            <option value="">Sem responsável</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}" {{ $account->owner_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Ciclo de Vida</label>
                        <select name="lifecycle" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                            @foreach(['onboarding','ativo','adormecido','arquivado','risco'] as $lc)
                                <option value="{{ $lc }}" {{ $account->lifecycle === $lc ? 'selected' : '' }}>{{ ucfirst($lc) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Saúde (0-100)</label>
                        <input type="number" name="health_score" min="0" max="100" value="{{ $account->health_score }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Próxima ação</label>
                        <input type="date" name="next_touch_at" value="{{ $account->next_touch_at?->format('Y-m-d') }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Notas</label>
                        <textarea name="notes" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm mt-1">{{ $account->notes }}</textarea>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Tags (separar por vírgula)</label>
                        <input type="text" name="tags" value="{{ implode(', ', $account->getTagsArray()) }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm mt-1">
                    </div>
                    <button type="button" onclick="saveAccountCrm()" class="w-full px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A]">
                        Salvar Alterações
                    </button>
                    <p id="save-feedback" class="text-xs text-green-600 hidden text-center">Salvo com sucesso!</p>
                </form>
            </div>

            {{-- Registrar Atividade --}}
            <div class="bg-white rounded-lg shadow-sm border p-6">
                <h2 class="text-sm font-semibold text-[#1B334A] mb-3">Registrar Atividade</h2>
                <form id="form-activity" class="space-y-3">
                    @csrf
                    <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="note">📝 Nota</option>
                        <option value="call">📞 Ligação</option>
                        <option value="meeting">🤝 Reunião</option>
                        <option value="task">✅ Tarefa</option>
                        <option value="whatsapp">💬 WhatsApp</option>
                    </select>
                    <input type="text" name="title" placeholder="Título" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <textarea name="body" placeholder="Detalhes (opcional)" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
                    <button type="button" onclick="saveActivity()" class="w-full px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700">
                        Registrar
                    </button>
                </form>
            </div>

            {{-- Info rápido --}}
            <div class="bg-gray-50 rounded-lg border p-4 text-xs text-gray-500 space-y-1">
                <p><strong>Account ID:</strong> {{ $account->id }}</p>
                @if($account->datajuri_pessoa_id)
                    <p><strong>DataJuri ID:</strong> {{ $account->datajuri_pessoa_id }}</p>
                @endif
                <p><strong>Criado:</strong> {{ $account->created_at?->format('d/m/Y') }}</p>
                @if($account->next_touch_at)
                    <p><strong>Próxima ação:</strong> {{ \Carbon\Carbon::parse($account->next_touch_at)->format('d/m/Y') }}</p>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal Nova Oportunidade --}}
<div id="modal-new-opp" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold text-[#1B334A] mb-4">Nova Oportunidade</h3>
        <form method="POST" action="{{ route('crm.accounts.create-opp', $account->id) }}" class="space-y-3">
            @csrf
            <input type="text" name="title" placeholder="Título" class="w-full border rounded-lg px-3 py-2 text-sm">
            <select name="type" class="w-full border rounded-lg px-3 py-2 text-sm">
                <option value="aquisicao">Aquisição</option>
                <option value="carteira">Carteira</option>
            </select>
            <input type="text" name="area" placeholder="Área do Direito (opcional)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <input type="text" name="source" placeholder="Fonte (WhatsApp, Indicação...)" class="w-full border rounded-lg px-3 py-2 text-sm">
            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-[#385776] text-white rounded-lg text-sm">Criar</button>
                <button type="button" onclick="document.getElementById('modal-new-opp').classList.add('hidden')"
                        class="px-4 py-2 border rounded-lg text-sm">Cancelar</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function saveAccountCrm() {
    const form = document.getElementById('form-crm-update');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token') body[k] = v; });
    if (body.tags) body.tags = JSON.stringify(body.tags.split(',').map(t => t.trim()).filter(Boolean));

    fetch('{{ route("crm.accounts.update", $account->id) }}', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => {
        if (d.ok) {
            const fb = document.getElementById('save-feedback');
            fb.classList.remove('hidden');
            setTimeout(() => fb.classList.add('hidden'), 2000);
        }
    });
}

function saveActivity() {
    const form = document.getElementById('form-activity');
    const data = new FormData(form);
    const body = {};
    data.forEach((v, k) => { if (k !== '_token') body[k] = v; });

    fetch('{{ route("crm.accounts.store-activity", $account->id) }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: JSON.stringify(body)
    }).then(r => r.json()).then(d => {
        if (d.ok) location.reload();
    });
}
</script>
@endpush
