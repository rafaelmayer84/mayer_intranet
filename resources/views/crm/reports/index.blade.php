@extends('layouts.app')
@section('title', 'CRM - Relatórios')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Relatórios CRM</h1>
            <p class="text-sm text-gray-500 mt-1">Análise de performance do pipeline</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.pipeline') }}" class="px-4 py-2 border border-[#385776] text-[#385776] rounded-lg text-sm hover:bg-gray-50">← Pipeline</a>
            <a href="{{ route('crm.carteira') }}" class="px-4 py-2 border border-[#385776] text-[#385776] rounded-lg text-sm hover:bg-gray-50">Carteira</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        {{-- 1. Valor projetado --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Valor Projetado (Ponderado)</h2>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div>
                    <p class="text-xs text-gray-500">30 dias</p>
                    <p class="text-xl font-bold text-[#385776]">R$ {{ number_format($projected['30d'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">60 dias</p>
                    <p class="text-xl font-bold text-[#385776]">R$ {{ number_format($projected['60d'] ?? 0, 0, ',', '.') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">90 dias</p>
                    <p class="text-xl font-bold text-[#385776]">R$ {{ number_format($projected['90d'] ?? 0, 0, ',', '.') }}</p>
                </div>
            </div>
        </div>

        {{-- 2. Win Rate por Responsável --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Win Rate por Responsável</h2>
            @if(count($winRate) > 0)
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 border-b">
                    <th class="py-2">Responsável</th><th class="text-center">Ganhos</th><th class="text-center">Perdidos</th><th class="text-center">Win Rate</th>
                </tr></thead>
                <tbody>
                @foreach($winRate as $wr)
                <tr class="border-b">
                    <td class="py-2">{{ $wr->owner_name ?? 'Sem responsável' }}</td>
                    <td class="text-center text-green-600">{{ $wr->won }}</td>
                    <td class="text-center text-red-600">{{ $wr->lost }}</td>
                    <td class="text-center font-medium">{{ number_format($wr->win_rate, 1) }}%</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            @else
            <p class="text-gray-400 text-sm">Dados insuficientes.</p>
            @endif
        </div>

        {{-- 3. Funil de Conversão --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Funil de Conversão</h2>
            <canvas id="chart-funnel" height="200"></canvas>
        </div>

        {{-- 4. Conversão entre Estágios --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Conversão entre Estágios</h2>
            @if(count($conversion) > 0)
            <div class="space-y-2">
                @foreach($conversion as $c)
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-32 text-gray-600 truncate">{{ $c['from'] ?? $c['from_stage'] ?? '?' }} → {{ $c['to'] ?? $c['to_stage'] ?? '?' }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                        <div class="h-full bg-[#385776] rounded-full" style="width: {{ min($c['rate'] ?? $c['avg_days'] ?? 0, 100) }}%"></div>
                    </div>
                    <span class="text-xs font-medium w-12 text-right">{{ number_format($c['rate'] ?? $c['avg_days'] ?? 0, 1) }}%</span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-gray-400 text-sm">Dados insuficientes.</p>
            @endif
        </div>

        {{-- 5. Tempo Médio por Estágio --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Tempo Médio por Estágio</h2>
            <canvas id="chart-avg-time" height="200"></canvas>
        </div>

        {{-- 6. Motivos de Perda --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Top Motivos de Perda</h2>
            @if(count($lostReasons) > 0)
            <div class="space-y-2">
                @foreach($lostReasons as $lr)
                <div class="flex items-center justify-between text-sm border-b pb-2">
                    <span class="text-gray-700">{{ ($lr->reason ?? $lr->lost_reason ?? '(Sem motivo)') }}</span>
                    <span class="text-red-600 font-medium">{{ ($lr->count ?? $lr->total ?? 0) }}</span>
                </div>
                @endforeach
            </div>
            @else
            <p class="text-gray-400 text-sm">Nenhuma oportunidade perdida no período.</p>
            @endif
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Funil
    const funnelData = @json($funnel);
    if (funnelData.length > 0) {
        new Chart(document.getElementById('chart-funnel'), {
            type: 'bar',
            data: {
                labels: funnelData.map(f => f.stage_name),
                datasets: [{
                    label: 'Oportunidades',
                    data: funnelData.map(f => f.current_count),
                    backgroundColor: funnelData.map(f => f.color || '#385776'),
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true } }
            }
        });
    }

    // Tempo médio
    const avgData = @json($avgTime);
    if (avgData.length > 0) {
        new Chart(document.getElementById('chart-avg-time'), {
            type: 'bar',
            data: {
                labels: avgData.map(a => a.stage_name),
                datasets: [{
                    label: 'Dias médios',
                    data: avgData.map(a => a.avg_days),
                    backgroundColor: '#385776',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, title: { display: true, text: 'Dias' } } }
            }
        });
    }
});
</script>
@endsection
