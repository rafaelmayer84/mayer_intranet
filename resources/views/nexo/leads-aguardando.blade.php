@extends('layouts.app')

@php
    $totalUrgente = $grupos['urgente']->count();
    $totalAtencao = $grupos['atencao']->count();
    $totalRecente = $grupos['recente']->count();
    $total = $totalUrgente + $totalAtencao + $totalRecente;

    function slaLabel(int $min): string {
        if ($min < 60) return $min . 'min';
        $h = intdiv($min, 60); $m = $min % 60;
        return $h . 'h' . ($m > 0 ? $m . 'min' : '');
    }
@endphp

@section('content')
<meta http-equiv="refresh" content="60">

<div class="max-w-5xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-[#1B334A]">Leads Aguardando Atendimento</h1>
            <p class="text-sm text-gray-500 mt-0.5">Contatos qualificados pelo Lexus sem resposta humana ainda</p>
        </div>
        <div class="flex items-center gap-3">
            @if($totalUrgente > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-[#dc2626] text-white">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ $totalUrgente }} urgente{{ $totalUrgente > 1 ? 's' : '' }}
                </span>
            @endif
            @if($totalAtencao > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-[#f59e0b] text-white">
                    {{ $totalAtencao }} atenção
                </span>
            @endif
            @if($totalRecente > 0)
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-semibold bg-[#16a34a] text-white">
                    {{ $totalRecente }} recente{{ $totalRecente > 1 ? 's' : '' }}
                </span>
            @endif
            <button onclick="window.location.reload()" class="ml-2 p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition" title="Atualizar">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>
    </div>

    {{-- Empty state --}}
    @if($total === 0)
        <div class="text-center py-20">
            <div class="text-5xl mb-4">✅</div>
            <p class="text-lg font-semibold text-gray-700">Nenhum lead aguardando atendimento — equipe em dia!</p>
            <p class="text-sm text-gray-400 mt-2">A página atualiza automaticamente a cada 60 segundos.</p>
        </div>
    @else

    {{-- Seção Urgente --}}
    @if($grupos['urgente']->count())
    <div class="mb-8">
        <h2 class="text-sm font-bold text-[#dc2626] uppercase tracking-wider mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
            Urgente — mais de 3h sem resposta
        </h2>
        <div class="space-y-3">
            @foreach($grupos['urgente'] as $conv)
                @include('nexo._lead-card', ['conv' => $conv, 'cor' => '#dc2626', 'slaLabel' => slaLabel($conv->sla_minutos)])
            @endforeach
        </div>
    </div>
    @endif

    {{-- Seção Atenção --}}
    @if($grupos['atencao']->count())
    <div class="mb-8">
        <h2 class="text-sm font-bold text-[#f59e0b] uppercase tracking-wider mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            Atenção — entre 1h e 3h
        </h2>
        <div class="space-y-3">
            @foreach($grupos['atencao'] as $conv)
                @include('nexo._lead-card', ['conv' => $conv, 'cor' => '#f59e0b', 'slaLabel' => slaLabel($conv->sla_minutos)])
            @endforeach
        </div>
    </div>
    @endif

    {{-- Seção Recente --}}
    @if($grupos['recente']->count())
    <div class="mb-8">
        <h2 class="text-sm font-bold text-[#16a34a] uppercase tracking-wider mb-3 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Recente — menos de 1h
        </h2>
        <div class="space-y-3">
            @foreach($grupos['recente'] as $conv)
                @include('nexo._lead-card', ['conv' => $conv, 'cor' => '#16a34a', 'slaLabel' => slaLabel($conv->sla_minutos)])
            @endforeach
        </div>
    </div>
    @endif

    @endif {{-- /empty state --}}

    <p class="text-center text-xs text-gray-400 mt-6">Atualização automática a cada 60s · Última atualização: {{ now()->format('H:i:s') }}</p>
</div>

<script>
function marcarAtendido(convId, btn) {
    if (!confirm('Confirmar que este lead já foi atendido?')) return;
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    fetch(`/nexo/leads-aguardando/${convId}/atendido`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const card = document.getElementById(`lead-card-${convId}`);
            if (card) {
                card.style.transition = 'opacity .3s, height .3s';
                card.style.opacity = '0';
                setTimeout(() => { card.remove(); atualizarContadores(); }, 350);
            }
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Marcar atendido'; });
}

function atualizarContadores() {
    ['urgente','atencao','recente'].forEach(secao => {
        const sec = document.getElementById(`secao-${secao}`);
        if (!sec) return;
        const cards = sec.querySelectorAll('[id^="lead-card-"]');
        if (cards.length === 0) sec.remove();
    });
}
</script>
@endsection
