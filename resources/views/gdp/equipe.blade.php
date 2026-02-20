@extends('layouts.app')
@section('title', 'GDP — Equipe')
@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                <span class="text-lg">👥</span> GDP — Equipe
            </h1>
            <p class="mt-1 text-xs text-gray-500">
                Ranking de Desempenho | Competência: {{ $refDate->translatedFormat('F/Y') }}
                @if($ciclo)
                    | Ciclo: {{ $ciclo->nome }}
                @endif
            </p>
        </div>
        <form method="GET" action="{{ route('gdp.equipe') }}" class="flex items-center gap-2">
            <select name="month" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" {{ $mes === $m ? 'selected' : '' }}>
                        {{ Carbon\Carbon::create(null, $m)->translatedFormat('F') }}
                    </option>
                @endfor
            </select>
            <select name="year" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500">
                @for ($y = 2024; $y <= now()->year; $y++)
                    <option value="{{ $y }}" {{ $ano === $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm transition" style="background-color:#385776;">
                Filtrar
            </button>
        </form>
    </div>

    @if(!$ciclo)
    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-6 text-center">
        <p class="text-sm text-yellow-800">Nenhum ciclo GDP ativo.</p>
    </div>
    @elseif($ranking->isEmpty())
    <div class="rounded-lg border border-gray-200 bg-white p-6 text-center">
        <p class="text-sm text-gray-500">Nenhuma apuração encontrada para {{ $refDate->translatedFormat('F/Y') }}. O admin precisa executar a apuração do mês.</p>
    </div>
    @else

    {{-- MÉDIAS POR EIXO (cards resumo) --}}
    <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">Média Geral</p>
            <p class="mt-1 text-2xl font-extrabold" style="color:#385776;">{{ number_format($ranking->avg('score_total'), 1, ',', '.') }}</p>
        </div>
        @php
            $eixoLabels = [
                ['label' => 'Jurídico', 'key' => 'juridico', 'color' => '#2563eb'],
                ['label' => 'Financeiro', 'key' => 'financeiro', 'color' => '#16a34a'],
                ['label' => 'Desenvolvimento', 'key' => 'desenvolvimento', 'color' => '#9333ea'],
                ['label' => 'Atendimento', 'key' => 'atendimento', 'color' => '#ea580c'],
            ];
        @endphp
        @foreach($eixoLabels as $el)
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm text-center">
            <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wider">{{ $el['label'] }}</p>
            <p class="mt-1 text-xl font-bold" style="color:{{ $el['color'] }};">{{ number_format($mediaEixo[$el['key']] ?? 0, 1, ',', '.') }}</p>
        </div>
        @endforeach
    </div>

    {{-- TABELA RANKING --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 bg-gray-50/50">
            <h3 class="text-sm font-semibold text-gray-800">🏆 Ranking de Desempenho</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 w-12">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Advogado</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">⚖️ Jurídico</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">💰 Financeiro</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">📚 Desenv.</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">💬 Atend.</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">📋 180°</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Score Total</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach($ranking as $snap)
                    @php
                        $medal = match($snap->ranking) { 1 => '🥇', 2 => '🥈', 3 => '🥉', default => '' };
                        $rowBg = $snap->ranking <= 3 ? 'bg-amber-50/30' : '';
                    @endphp
                    <tr class="hover:bg-gray-50/50 {{ $rowBg }}">
                        <td class="px-4 py-2.5 text-center font-semibold text-gray-600">
                            {{ $medal ?: $snap->ranking . 'º' }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span class="font-medium text-gray-800">{{ $snap->user->name ?? '—' }}</span>
                            <span class="ml-1 text-[10px] text-gray-400">{{ $snap->user->role ?? '' }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-center font-medium" style="color:#2563eb;">{{ number_format($snap->score_juridico, 1, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-center font-medium" style="color:#16a34a;">{{ number_format($snap->score_financeiro, 1, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-center font-medium" style="color:#9333ea;">{{ number_format($snap->score_desenvolvimento, 1, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-center font-medium" style="color:#ea580c;">{{ number_format($snap->score_atendimento, 1, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-center font-medium {{ $snap->score_eval180 !== null ? ($snap->score_eval180 >= 3.0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                            {{ $snap->score_eval180 !== null ? number_format($snap->score_eval180, 1, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-sm font-bold text-white" style="background-color:#385776;">
                                {{ number_format($snap->score_total, 1, ',', '.') }}
                            </span>
                            @if($snap->score_total_original && $snap->score_total_original != $snap->score_total)
                                <span class="block text-[10px] text-red-500 mt-0.5" title="Score original antes do guardrail 180°">
                                    ({{ number_format($snap->score_total_original, 1, ',', '.') }})
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <a href="{{ route('gdp.minha-performance', ['user_id' => $snap->user_id, 'month' => $mes, 'year' => $ano]) }}"
                               class="text-xs font-medium hover:underline" style="color:#385776;">
                                Ver detalhe →
                            </a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- GRÁFICO RADAR COMPARATIVO --}}
    @if(!empty($mediaEixo))
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">📊 Comparativo por Eixo (Radar)</h3>
        <div class="flex justify-center">
            <div style="max-width:450px;width:100%;">
                <canvas id="gdpRadarChart"></canvas>
            </div>
        </div>
    </div>
    @endif

    @endif {{-- end ciclo + ranking --}}
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
@if(!empty($mediaEixo) && $ranking->isNotEmpty())
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('gdpRadarChart');
    if (!ctx) return;

    const labels = ['Jurídico', 'Financeiro', 'Desenvolvimento', 'Atendimento'];
    const mediaData = [
        {{ $mediaEixo['juridico'] ?? 0 }},
        {{ $mediaEixo['financeiro'] ?? 0 }},
        {{ $mediaEixo['desenvolvimento'] ?? 0 }},
        {{ $mediaEixo['atendimento'] ?? 0 }}
    ];

    const datasets = [
        {
            label: 'Média Equipe',
            data: mediaData,
            backgroundColor: 'rgba(56,87,118,0.15)',
            borderColor: '#385776',
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: '#385776'
        }
    ];

    // Top 3 individuais
    const colors = ['#2563eb', '#16a34a', '#ea580c'];
    @foreach($ranking->take(3) as $idx => $snap)
    datasets.push({
        label: '{{ addslashes($snap->user->name ?? "User") }}',
        data: [{{ $snap->score_juridico }}, {{ $snap->score_financeiro }}, {{ $snap->score_desenvolvimento }}, {{ $snap->score_atendimento }}],
        backgroundColor: 'transparent',
        borderColor: colors[{{ $idx }}],
        borderWidth: 1.5,
        borderDash: [4, 4],
        pointRadius: 3,
        pointBackgroundColor: colors[{{ $idx }}]
    });
    @endforeach

    new Chart(ctx, {
        type: 'radar',
        data: { labels: labels, datasets: datasets },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } } },
            scales: { r: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.06)' }, ticks: { display: false } } }
        }
    });
});
@endif
</script>
@endpush
@endsection
