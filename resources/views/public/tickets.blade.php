@extends('layouts.public')

@section('title', 'Solicitações — Mayer Advogados')

@section('content')

<div class="mb-5">
    <h1 class="text-lg font-bold text-gray-800">Suas solicitações</h1>
    <p class="text-sm text-gray-500 mt-0.5">Acompanhe o status das suas solicitações ao escritório.</p>
</div>

@if(($total ?? 0) > 0 && !empty($tickets))

<div class="card p-0 overflow-hidden mb-5">
    <div class="divide-y divide-gray-50">
        @foreach($tickets as $ticket)
        @php
            $statusColor = match($ticket['status'] ?? '') {
                'aberto'       => 'bg-blue-100 text-blue-700',
                'em_andamento' => 'bg-amber-100 text-amber-700',
                'resolvido'    => 'bg-green-100 text-green-700',
                'cancelado'    => 'bg-gray-100 text-gray-400',
                default        => 'bg-gray-100 text-gray-500',
            };
            $statusLabel = match($ticket['status'] ?? '') {
                'aberto'       => 'Aberto',
                'em_andamento' => 'Em andamento',
                'resolvido'    => 'Resolvido',
                'cancelado'    => 'Cancelado',
                default        => ucfirst($ticket['status'] ?? '—'),
            };
        @endphp
        <div class="px-5 py-4">
            <div class="flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-xs font-mono text-gray-400">{{ $ticket['protocolo'] ?? '—' }}</span>
                    </div>
                    <p class="text-sm font-medium text-gray-800 leading-snug">{{ $ticket['assunto'] ?? '—' }}</p>
                    @if(!empty($ticket['data']))
                        <p class="text-xs text-gray-400 mt-1">Aberto em {{ $ticket['data'] }}</p>
                    @endif
                </div>
                <span class="badge {{ $statusColor }} shrink-0">{{ $statusLabel }}</span>
            </div>
        </div>
        @endforeach
    </div>
</div>

@else
<div class="card text-center py-12">
    <div class="text-4xl mb-3">🎫</div>
    <p class="text-gray-500 text-sm">Nenhuma solicitação encontrada.</p>
</div>
@endif

<div class="card bg-blue-50 border-blue-100 text-center">
    <p class="text-sm text-blue-800 font-medium mb-2">Precisa abrir uma nova solicitação?</p>
    <a href="{{ $whatsappUrl }}" target="_blank"
       class="inline-flex items-center gap-1.5 bg-green-500 text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-green-600 transition-all">
        Falar com o escritório
    </a>
</div>

<p class="text-xs text-gray-400 text-center mt-5">Dados consultados em {{ $consultadoEm }}</p>

@endsection
