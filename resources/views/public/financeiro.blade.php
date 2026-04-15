@extends('layouts.public')

@section('title', 'Financeiro — Mayer Advogados')

@section('content')

{{-- Saudação --}}
<div class="mb-5">
    <h1 class="text-lg font-bold text-gray-800">Olá, {{ $nome_cliente ?? 'cliente' }}!</h1>
    <p class="text-sm text-gray-500 mt-0.5">Aqui estão seus títulos em aberto.</p>
</div>

{{-- Cards resumo --}}
<div class="grid grid-cols-2 gap-3 mb-5">
    <div class="card text-center">
        <p class="text-2xl font-bold text-navy-700">{{ $total ?? 0 }}</p>
        <p class="text-xs text-gray-500 mt-1">Título(s) em aberto</p>
    </div>
    <div class="card text-center">
        <p class="text-lg font-bold text-navy-700">{{ $valor_total ?? 'R$ 0,00' }}</p>
        <p class="text-xs text-gray-500 mt-1">Valor total</p>
    </div>
</div>

@if(($total ?? 0) > 0)

{{-- Tabela de títulos --}}
<div class="card p-0 overflow-hidden mb-5">
    <div class="px-5 py-3 border-b border-gray-100">
        <h2 class="text-sm font-semibold text-gray-700">Detalhamento</h2>
    </div>
    <div class="divide-y divide-gray-50">
        @foreach(range(1, min($total, 5)) as $n)
            @php
                $desc  = ${'titulo'.$n.'_desc'}  ?? null;
                $valor = ${'titulo'.$n.'_valor'}  ?? null;
                $venc  = ${'titulo'.$n.'_vencimento'} ?? null;
                $sit   = ${'titulo'.$n.'_situacao'}   ?? null;
                if (!$desc) continue;
                $vencido = $sit === 'Vencido';
            @endphp
            <div class="px-5 py-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ $desc }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">Vencimento: {{ $venc }}</p>
                    </div>
                    <div class="text-right shrink-0">
                        <p class="text-sm font-semibold text-gray-800">{{ $valor }}</p>
                        @if($vencido)
                            <span class="badge bg-red-100 text-red-600 mt-1">Vencido</span>
                        @else
                            <span class="badge bg-green-100 text-green-700 mt-1">Em aberto</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

{{-- CTA --}}
<div class="card bg-blue-50 border-blue-100 text-center">
    <p class="text-sm text-blue-800 font-medium mb-2">Precisa de boleto ou tem dúvidas?</p>
    <a href="{{ $whatsappUrl }}" target="_blank"
       class="inline-flex items-center gap-1.5 bg-green-500 text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-green-600 transition-all">
        Falar com financeiro
    </a>
</div>

@else
<div class="card text-center py-10">
    <p class="text-gray-400 text-sm">Nenhum título em aberto encontrado.</p>
</div>
@endif

{{-- Rodapé de timestamp --}}
<p class="text-xs text-gray-400 text-center mt-5">
    Dados consultados em {{ $consultadoEm }}
</p>

@endsection
