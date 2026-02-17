@extends('layouts.app')
@section('title', 'GDP — Meu Acordo de Desempenho')
@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                <span class="text-lg">📋</span> Acordo de Desempenho — {{ $targetUser->name }}
            </h1>
            <p class="mt-1 text-xs text-gray-500">
                Ciclo: {{ $ciclo->nome }} | {{ $ciclo->data_inicio->format('d/m/Y') }} a {{ $ciclo->data_fim->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('gdp.acordo.print', $targetUser->id) }}" target="_blank"
               class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm" style="background-color:#1B334A;">
                🖨️ Imprimir
            </a>
        </div>
    </div>

    {{-- STATUS --}}
    @if($acordoAceito)
    <div class="rounded-lg border border-green-200 bg-green-50 p-4 flex items-center gap-3">
        <span class="text-2xl">✅</span>
        <p class="text-sm font-semibold text-green-800">Acordo aceito. Metas travadas para o ciclo.</p>
    </div>
    @else
    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 flex items-center gap-3">
        <span class="text-2xl">⚠️</span>
        <div>
            <p class="text-sm font-semibold text-amber-800">Acordo pendente de aceite</p>
            <p class="text-xs text-amber-600">Revise as metas abaixo e clique em "Aceitar Acordo" para formalizar.</p>
        </div>
    </div>
    @endif

    {{-- METAS (readonly) --}}
    @foreach($eixos as $eixo)
    @if($eixo->indicadores->isNotEmpty())
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100" style="background: linear-gradient(135deg, {{ $eixo->codigo === 'JURIDICO' ? '#eff6ff' : ($eixo->codigo === 'FINANCEIRO' ? '#f0fdf4' : ($eixo->codigo === 'DESENVOLVIMENTO' ? '#faf5ff' : '#fff7ed')) }} 0%, white 100%);">
            <h3 class="text-sm font-semibold text-gray-800">
                @if($eixo->codigo === 'JURIDICO') ⚖️
                @elseif($eixo->codigo === 'FINANCEIRO') 💰
                @elseif($eixo->codigo === 'DESENVOLVIMENTO') 📚
                @else 💬
                @endif
                {{ $eixo->nome }}
                <span class="text-xs font-normal text-gray-500">(peso {{ number_format($eixo->peso, 0) }}%)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Indicador</th>
                        <th class="px-2 py-2 text-center text-xs font-medium text-gray-500">Peso</th>
                        @for($m = $mesInicio; $m <= $mesFim; $m++)
                            <th class="px-2 py-2 text-center text-xs font-medium text-gray-500">
                                {{ \Carbon\Carbon::create(null, $m)->translatedFormat('M') }}
                            </th>
                        @endfor
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach($eixo->indicadores as $ind)
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5">
                            <span class="font-mono text-xs text-gray-400 mr-1">{{ $ind->codigo }}</span>
                            <span class="text-gray-800 text-xs">{{ $ind->nome }}</span>
                        </td>
                        <td class="px-2 py-2.5 text-center text-xs text-gray-500">{{ number_format($ind->peso, 0) }}%</td>
                        @for($m = $mesInicio; $m <= $mesFim; $m++)
                            @php
                                $key = $ind->id . '_' . $m;
                                $meta = $metas->get($key);
                                $val = $meta ? (float) $meta->valor_meta : 0;
                            @endphp
                            <td class="px-2 py-2.5 text-center text-xs font-medium text-gray-700">
                                @if($val > 0)
                                    @if($ind->unidade === 'reais')
                                        R$ {{ number_format($val, 2, ',', '.') }}
                                    @elseif($ind->unidade === 'percentual')
                                        {{ number_format($val, 1, ',', '.') }}%
                                    @elseif($ind->unidade === 'horas')
                                        {{ number_format($val, 1, ',', '.') }}h
                                    @elseif($ind->unidade === 'minutos')
                                        {{ number_format($val, 0) }}min
                                    @else
                                        {{ number_format($val, 0) }}
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        @endfor
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endforeach

    {{-- BOTÃO ACEITAR --}}
    @if(!$acordoAceito && $user->id === $targetUser->id)
    <div class="flex justify-center">
        <button onclick="aceitarAcordo()" id="btn-aceitar"
            class="inline-flex items-center gap-2 rounded-lg px-8 py-3 text-sm font-bold text-white shadow-lg transition" style="background-color:#16a34a;">
            ✅ Aceitar Acordo de Desempenho
        </button>
    </div>
    @endif
</div>

@push('scripts')
<script>
function aceitarAcordo() {
    if (!confirm('Ao aceitar, as metas serão travadas para este ciclo.\n\nConfirma o aceite do Acordo de Desempenho?')) return;

    var btn = document.getElementById('btn-aceitar');
    btn.disabled = true;
    btn.innerHTML = '⏳ Processando...';

    fetch('{{ route("gdp.acordo.aceitar", $targetUser->id) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin',
        body: JSON.stringify({})
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.erro) {
            alert('Erro: ' + resp.erro);
            btn.disabled = false;
            btn.innerHTML = '✅ Aceitar Acordo de Desempenho';
        } else {
            alert('Acordo aceito com sucesso!\nHash: ' + resp.hash);
            location.reload();
        }
    })
    .catch(function(e) {
        alert('Erro: ' + e.message);
        btn.disabled = false;
        btn.innerHTML = '✅ Aceitar Acordo de Desempenho';
    });
}
</script>
@endpush
@endsection
