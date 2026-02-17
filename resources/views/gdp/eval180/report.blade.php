@extends('layouts.app')

@section('title', 'Relatório Avaliação 180°')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Relatório Consolidado — Avaliação 180°</h1>
            <p class="text-sm text-gray-500 mt-1">Ciclo {{ $ciclo->nome }}</p>
        </div>
        <a href="{{ route('gdp.eval180.cycle', $ciclo->id) }}" class="btn-mayer px-4 py-2 rounded-lg text-sm text-white">
            ← Voltar
        </a>
    </div>

    @if(empty($report))
        <div class="bg-gray-50 rounded-lg p-6 text-center text-gray-500">
            Nenhuma avaliação encontrada para este ciclo.
        </div>
    @else
        {{-- Cards resumo --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            @php
                $total = count($report);
                $selfDone = collect($report)->where('self_submitted', true)->count();
                $mgrDone = collect($report)->where('manager_submitted', true)->count();
                $avgScore = collect($report)->whereNotNull('manager_total')->avg('manager_total');
            @endphp
            <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                <p class="text-2xl font-bold text-[#385776]">{{ $total }}</p>
                <p class="text-xs text-gray-500">Avaliações</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                <p class="text-2xl font-bold text-green-600">{{ $selfDone }}</p>
                <p class="text-xs text-gray-500">Auto enviadas</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                <p class="text-2xl font-bold text-blue-600">{{ $mgrDone }}</p>
                <p class="text-xs text-gray-500">Gestor enviadas</p>
            </div>
            <div class="bg-white rounded-xl shadow-sm border p-4 text-center">
                <p class="text-2xl font-bold {{ $avgScore >= 3.0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ $avgScore ? number_format($avgScore, 2, ',', '.') : '—' }}
                </p>
                <p class="text-xs text-gray-500">Média geral gestor</p>
            </div>
        </div>

        {{-- Tabela detalhada --}}
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-4 py-3 text-left font-medium text-gray-600">Profissional</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-600">Período</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-600">Status</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-600">Auto</th>
                            <th class="px-3 py-3 text-center font-medium text-gray-600">Gestor</th>
                            @foreach($sectionNames as $sNum => $sName)
                                <th class="px-2 py-3 text-center font-medium text-gray-600 text-xs" title="{{ $sName }}">
                                    S{{ $sNum }}
                                </th>
                            @endforeach
                            <th class="px-3 py-3 text-center font-medium text-gray-600">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($report as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-medium text-gray-800">{{ $row['user_name'] }}</td>
                                <td class="px-3 py-3 text-center text-gray-600">
                                    {{ \Carbon\Carbon::createFromFormat('Y-m', $row['period'])->translatedFormat('M/y') }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    @if($row['status'] === 'locked')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">🔒 Travado</span>
                                    @elseif($row['manager_submitted'])
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">✅ Completo</span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">⏳ Pendente</span>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-center font-medium {{ $row['self_total'] ? ($row['self_total'] >= 3.0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                                    {{ $row['self_total'] ? number_format($row['self_total'], 1, ',', '') : '—' }}
                                </td>
                                <td class="px-3 py-3 text-center font-bold {{ $row['manager_total'] ? ($row['manager_total'] >= 3.0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                                    {{ $row['manager_total'] ? number_format($row['manager_total'], 1, ',', '') : '—' }}
                                </td>
                                @foreach($sectionNames as $sNum => $sName)
                                    @php $sv = $row['manager_sections'][$sNum] ?? null; @endphp
                                    <td class="px-2 py-3 text-center text-xs {{ $sv ? ($sv >= 3.0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                                        {{ $sv ? number_format($sv, 1, ',', '') : '—' }}
                                    </td>
                                @endforeach
                                <td class="px-3 py-3 text-center">
                                    @if($row['action_items'] > 0)
                                        <span class="text-xs text-amber-600" title="{{ $row['action_done'] }}/{{ $row['action_items'] }} ações concluídas">
                                            📋 {{ $row['action_done'] }}/{{ $row['action_items'] }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Gráfico radar (Chart.js) --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-semibold text-gray-800 mb-4">Comparativo por Seção — Médias do Gestor</h3>
            <div class="flex justify-center">
                <canvas id="radarChart" width="400" height="400"></canvas>
            </div>
        </div>
    @endif
</div>
@endsection

@push('scripts')
@if(!empty($report))
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const report = @json($report);
    const sectionLabels = @json(array_values($sectionNames));

    // Agrupar por user
    const users = {};
    report.forEach(r => {
        if (!r.manager_sections || Object.keys(r.manager_sections).length === 0) return;
        if (!users[r.user_name]) users[r.user_name] = { scores: {}, count: {} };
        Object.entries(r.manager_sections).forEach(([s, v]) => {
            if (!users[r.user_name].scores[s]) { users[r.user_name].scores[s] = 0; users[r.user_name].count[s] = 0; }
            users[r.user_name].scores[s] += v;
            users[r.user_name].count[s]++;
        });
    });

    const colors = ['#385776', '#E74C3C', '#2ECC71', '#F39C12'];
    const datasets = [];
    let ci = 0;
    Object.entries(users).forEach(([name, data]) => {
        const avgBySection = sectionLabels.map((_, i) => {
            const s = String(i + 1);
            return data.count[s] > 0 ? (data.scores[s] / data.count[s]).toFixed(2) : 0;
        });
        datasets.push({
            label: name,
            data: avgBySection,
            borderColor: colors[ci % colors.length],
            backgroundColor: colors[ci % colors.length] + '20',
            pointRadius: 4,
        });
        ci++;
    });

    if (datasets.length > 0) {
        new Chart(document.getElementById('radarChart'), {
            type: 'radar',
            data: { labels: sectionLabels.map((l, i) => 'S' + (i+1) + ': ' + l.substring(0, 20) + '...'), datasets },
            options: {
                scales: { r: { min: 0, max: 5, ticks: { stepSize: 1 } } },
                plugins: { legend: { position: 'bottom' } },
            }
        });
    }
});
</script>
@endif
@endpush
