@extends('layouts.public')

@section('title', 'Processo Judicial — Mayer Advogados')

@section('content')

{{-- Header do processo --}}
<div class="card mb-5">
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            <p class="text-xs font-semibold text-navy-500 uppercase tracking-wide mb-1">Processo judicial</p>
            <h1 class="text-base font-bold text-gray-800">{{ $processo_pasta ?? '—' }}</h1>
            @if(!empty($processo_adverso))
                <p class="text-sm text-gray-500 mt-1 truncate">Parte contrária: {{ $processo_adverso }}</p>
            @endif
        </div>
        @if(!empty($processo_status))
        <span class="badge
            {{ $processo_status === 'Ativo' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}
            shrink-0">
            {{ $processo_status }}
        </span>
        @endif
    </div>

    @if(!empty($tipo_acao))
    <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-2 gap-2 text-xs text-gray-500">
        <div><span class="font-medium text-gray-700">Tipo:</span> {{ $tipo_acao }}</div>
        @if(!empty($advogado_responsavel))
        <div><span class="font-medium text-gray-700">Advogado:</span> {{ $advogado_responsavel }}</div>
        @endif
        @if(!empty($area_atuacao))
        <div class="col-span-2"><span class="font-medium text-gray-700">Área:</span> {{ $area_atuacao }}</div>
        @endif
    </div>
    @endif
</div>

{{-- Resumo leigo da IA --}}
@if(!empty($resumo))
<div class="card mb-5 border-l-4 border-navy-700 rounded-l-none">
    <div class="flex items-center gap-2 mb-3">
        <div class="w-7 h-7 rounded-full bg-navy-700 flex items-center justify-center shrink-0">
            <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
            </svg>
        </div>
        <span class="text-sm font-semibold text-gray-700">Resumo do processo</span>
    </div>
    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $resumo }}</p>
</div>
@endif

{{-- Timeline de andamentos --}}
@if(!empty($andamentos) && count($andamentos) > 0)
<div class="card p-0 overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">Andamentos recentes</h2>
    </div>
    <div class="relative">
        {{-- Linha vertical --}}
        <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-100"></div>

        <div class="divide-y divide-gray-50">
            @foreach($andamentos as $i => $and)
            <div class="flex gap-4 px-5 py-4">
                {{-- Bolinha timeline --}}
                <div class="relative z-10 shrink-0 w-6 h-6 rounded-full bg-navy-700 flex items-center justify-center mt-0.5">
                    <div class="w-2 h-2 rounded-full bg-white"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-400 mb-1">{{ $and['data'] ?? '' }}{{ !empty($and['hora']) ? ' às '.$and['hora'] : '' }}</p>
                    <p class="text-sm text-gray-800 leading-snug">{{ $and['descricao'] ?? '' }}</p>
                    @if(!empty($and['observacao']))
                        <p class="text-xs text-gray-500 mt-1 italic">{{ $and['observacao'] }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- CTA dúvidas --}}
<div class="card bg-blue-50 border-blue-100 text-center">
    <p class="text-sm text-blue-800 font-medium mb-2">Tem dúvidas sobre seu processo?</p>
    <a href="{{ $whatsappUrl }}" target="_blank"
       class="inline-flex items-center gap-1.5 bg-green-500 text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-green-600 transition-all">
        Falar com meu advogado
    </a>
</div>

<p class="text-xs text-gray-400 text-center mt-5">Dados consultados em {{ $consultadoEm }}</p>

@endsection
