@extends('layouts.public')

@section('title', 'Processo Administrativo — Mayer Advogados')

@section('content')

{{-- Header do processo --}}
<div class="card mb-5">
    <div class="flex items-start gap-3">
        <div class="text-3xl leading-none shrink-0">{{ $processo->tipoIcon() }}</div>
        <div class="flex-1 min-w-0">
            <p class="text-xs text-gray-400 mb-0.5">{{ $processo->protocolo }}</p>
            <h1 class="text-base font-bold text-gray-800 leading-snug">{{ $processo->titulo }}</h1>
            <div class="flex flex-wrap gap-2 mt-2">
                <span class="badge {{ $processo->statusColor() }}">{{ $processo->statusLabel() }}</span>
                <span class="badge border text-xs {{ $processo->tipoColor() }}">{{ $processo->tipoLabel() }}</span>
            </div>
        </div>
    </div>

    @if($processo->prazo_final)
    <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-2 text-xs">
        <svg class="w-4 h-4 {{ $processo->prazo_final->isPast() && !in_array($processo->status, ['concluido','cancelado']) ? 'text-red-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
        <span class="{{ $processo->prazo_final->isPast() && !in_array($processo->status, ['concluido','cancelado']) ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
            Prazo final: {{ $processo->prazo_final->format('d/m/Y') }}
        </span>
    </div>
    @endif
</div>

{{-- Barra de progresso --}}
@php
    $visibleSteps = $processo->steps->whereNotIn('status', ['nao_aplicavel']);
    $totalVisivel = $visibleSteps->count();
    $doneVisivel  = $visibleSteps->where('status', 'concluido')->count();
    $progresso    = $totalVisivel > 0 ? round(($doneVisivel / $totalVisivel) * 100) : 0;
@endphp

@if($totalVisivel > 0)
<div class="card mb-5">
    <div class="flex items-center justify-between mb-2">
        <span class="text-sm font-semibold text-gray-700">Andamento geral</span>
        <span class="text-sm font-bold {{ $progresso === 100 ? 'text-green-600' : 'text-navy-700' }}">{{ $progresso }}%</span>
    </div>
    <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
        <div class="h-3 rounded-full transition-all duration-500
            {{ $progresso === 100 ? 'bg-green-500' : 'bg-navy-700' }}"
             style="width: {{ $progresso }}%"></div>
    </div>
    <p class="text-xs text-gray-400 mt-2">{{ $doneVisivel }} de {{ $totalVisivel }} etapa(s) concluída(s)</p>
</div>
@endif

{{-- Etapas visíveis ao cliente --}}
@if($processo->steps->count() > 0)
<div class="card p-0 overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">Etapas do processo</h2>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($processo->steps as $step)
        <div class="flex items-start gap-3 px-5 py-4">
            {{-- Ícone de status --}}
            <div class="shrink-0 mt-0.5">
                @if($step->status === 'concluido')
                    <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                @elseif($step->status === 'em_andamento')
                    <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center">
                        <div class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></div>
                    </div>
                @elseif($step->status === 'aguardando')
                    <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center">
                        <div class="w-2.5 h-2.5 rounded-full bg-yellow-500"></div>
                    </div>
                @else
                    <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center">
                        <div class="w-2.5 h-2.5 rounded-full bg-gray-300"></div>
                    </div>
                @endif
            </div>

            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-sm font-medium text-gray-800 leading-snug">{{ $step->titulo }}</p>
                    <span class="badge shrink-0 {{ $step->statusColor() }}">{{ $step->statusLabel() }}</span>
                </div>
                @if($step->descricao)
                    <p class="text-xs text-gray-500 mt-0.5 leading-snug">{{ $step->descricao }}</p>
                @endif
                @if($step->deadline_at)
                    <p class="text-xs mt-1
                        {{ $step->isAtrasada() ? 'text-red-500 font-medium' : 'text-gray-400' }}">
                        Prazo: {{ $step->deadline_at->format('d/m/Y') }}
                        @if($step->isAtrasada()) <span class="font-semibold">(atrasado)</span> @endif
                    </p>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Documentos / Checklist --}}
@if($processo->checklist->count() > 0)
<div class="card p-0 overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">Documentos solicitados</h2>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach($processo->checklist as $item)
        <div class="flex items-center gap-3 px-5 py-3.5">
            <div class="shrink-0">
                @if($item->status === 'recebido')
                    <div class="w-6 h-6 rounded-full bg-green-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-green-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                @elseif($item->status === 'dispensado')
                    <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                @else
                    <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5 text-yellow-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                @endif
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm text-gray-800">{{ $item->nome }}</p>
                @if($item->descricao)
                    <p class="text-xs text-gray-400">{{ $item->descricao }}</p>
                @endif
            </div>
            <span class="text-xs font-medium shrink-0
                {{ $item->status === 'recebido' ? 'text-green-600' : ($item->status === 'dispensado' ? 'text-gray-400' : 'text-yellow-600') }}">
                {{ match($item->status) {
                    'recebido'  => 'Recebido',
                    'pendente'  => 'Pendente',
                    'dispensado'=> 'Dispensado',
                    default     => ucfirst($item->status),
                } }}
            </span>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Timeline de eventos --}}
@if($processo->timeline->count() > 0)
<div class="card p-0 overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">Histórico de eventos</h2>
    </div>
    <div class="relative">
        <div class="absolute left-8 top-0 bottom-0 w-0.5 bg-gray-100"></div>
        <div class="divide-y divide-gray-50">
            @foreach($processo->timeline->take(10) as $evento)
            <div class="flex gap-4 px-5 py-4">
                <div class="relative z-10 shrink-0 w-6 h-6 rounded-full bg-navy-700 flex items-center justify-center mt-0.5">
                    <div class="w-2 h-2 rounded-full bg-white"></div>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-xs text-gray-400 mb-0.5">
                        {{ \Carbon\Carbon::parse($evento->happened_at)->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                    </p>
                    <p class="text-sm font-medium text-gray-700">{{ $evento->titulo ?? $evento->tipo ?? '—' }}</p>
                    @if(!empty($evento->corpo))
                        <p class="text-xs text-gray-500 mt-0.5">{{ $evento->corpo }}</p>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>
@endif

{{-- Nota sobre documentos --}}
<div class="rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-xs text-amber-700 flex gap-2 items-start mb-5">
    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span>Para acessar ou enviar documentos do processo, entre em contato com o escritório.</span>
</div>

{{-- CTA --}}
<div class="card bg-blue-50 border-blue-100 text-center">
    <p class="text-sm text-blue-800 font-medium mb-2">Dúvidas sobre seu processo?</p>
    <a href="{{ $whatsappUrl }}" target="_blank"
       class="inline-flex items-center gap-1.5 bg-green-500 text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-green-600 transition-all">
        Falar com o escritório
    </a>
</div>

<p class="text-xs text-gray-400 text-center mt-5">
    Dados atualizados em tempo real · {{ $consultadoEm }}
</p>

@endsection
