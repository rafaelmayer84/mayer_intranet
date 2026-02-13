@extends('layouts.app')

@section('title', 'CRM - Relatórios')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">CRM - Relatórios</h1>
            <p class="text-sm text-gray-500 mt-1">Análise de conversão e performance do pipeline</p>
        </div>
        <a href="{{ route('crm.pipeline') }}"
           class="mt-3 md:mt-0 px-4 py-2 text-sm border border-gray-300 rounded-lg hover:bg-gray-50">
            ← Pipeline
        </a>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-6 bg-white rounded-lg border border-gray-200 p-3">
        <select name="owner" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
            <option value="">Todos responsáveis</option>
            @foreach($users as $user)
                <option value="{{ $user->id }}" {{ ($filters['owner'] ?? '') == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
            @endforeach
        </select>
        <input type="date" name="period_start" value="{{ $filters['period_start'] ?? '' }}" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
        <input type="date" name="period_end" value="{{ $filters['period_end'] ?? '' }}" class="text-sm border border-gray-300 rounded-lg px-3 py-2">
        <button type="submit" class="px-4 py-2 text-sm bg-[#385776] text-white rounded-lg hover:bg-[#1B334A]">Filtrar</button>
    </form>

    {{-- KPIs resumo --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Total oportunidades</p>
            <p class="text-xl font-bold text-gray-800 mt-1">{{ $kpis['total_all'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Ganhas</p>
            <p class="text-xl font-bold text-emerald-600 mt-1">{{ $kpis['total_won'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Perdidas</p>
            <p class="text-xl font-bold text-red-600 mt-1">{{ $kpis['total_lost'] }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase">Win Rate</p>
            <p class="text-xl font-bold text-[#385776] mt-1">{{ $kpis['win_rate'] }}%</p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- 1. Funil --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Funil de Conversão</h3>
            <canvas id="chart-funnel" height="200"></canvas>
        </div>

        {{-- 2. Tempo médio por estágio --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Tempo Médio por Estágio (dias)</h3>
            <canvas id="chart-avg-time" height="200"></canvas>
        </div>

        {{-- 3. Win rate por responsável --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Win Rate por Responsável</h3>
            @if(count($reports['win_rate_by_owner']) > 0)
                <div class="space-y-3">
                    @foreach($reports['win_rate_by_owner'] as $owner)
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-gray-700">{{ $owner->name }}</span>
                                <span class="font-medium">{{ $owner->win_rate }}% <span class="text-gray-400 text-xs">({{ $owner->won }}/{{ $owner->total }})</span></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2">
                                <div class="bg-[#385776] h-2 rounded-full" style="width: {{ min($owner->win_rate, 100) }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Sem dados suficientes</p>
            @endif
        </div>

        {{-- 4. Motivos de perda --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Motivos de Perda</h3>
            @if(count($reports['lost_reasons']) > 0)
                <canvas id="chart-lost-reasons" height="200"></canvas>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Nenhuma oportunidade perdida com motivo registrado</p>
            @endif
        </div>

        {{-- 5. Conversão entre etapas --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Conversão entre Etapas</h3>
            @if(count($reports['conversion_by_stage']) > 0)
                <div class="space-y-3">
                    @foreach($reports['conversion_by_stage'] as $conv)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="text-gray-600 w-24 truncate">{{ $conv['from'] }}</span>
                            <span class="text-gray-400">→</span>
                            <span class="text-gray-600 w-24 truncate">{{ $conv['to'] }}</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-2">
                                <div class="bg-blue-500 h-2 rounded-full" style="width: {{ min($conv['rate'], 100) }}%"></div>
                            </div>
                            <span class="font-medium text-gray-700 w-12 text-right">{{ $conv['rate'] }}%</span>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Sem dados suficientes</p>
            @endif
        </div>

        {{-- 6. Receita projetada --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">Receita Projetada</h3>
            @php $proj = $reports['projected_revenue']; @endphp
            @if(count($proj['stages']) > 0)
                <div class="space-y-3 mb-4">
                    @foreach($proj['stages'] as $s)
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center gap-2">
                                <span class="w-3 h-3 rounded-full" style="background-color: {{ $s['color'] }}"></span>
                                <span class="text-gray-700">{{ $s['stage'] }} ({{ $s['count'] }})</span>
                            </div>
                            <div class="text-right">
                                <span class="text-gray-400 text-xs">{{ $s['probability'] }}% ×</span>
                                <span class="font-medium">R$ {{ number_format($s['projected'], 0, ',', '.') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-gray-200 pt-3 flex justify-between">
                    <span class="text-sm font-semibold text-gray-700">Total projetado</span>
                    <span class="text-lg font-bold text-[#385776]">R$ {{ number_format($proj['total_projected'], 0, ',', '.') }}</span>
                </div>
            @else
                <p class="text-sm text-gray-400 text-center py-8">Sem oportunidades abertas</p>
            @endif
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const defaultOpts = { responsive: true, plugins: { legend: { display: false } } };

    // Funil
    const funnelData = @json($reports['funnel']);
    if (funnelData.length > 0) {
        new Chart(document.getElementById('chart-funnel'), {
            type: 'bar',
            data: {
                labels: funnelData.map(d => d.stage),
                datasets: [{
                    data: funnelData.map(d => d.current),
                    backgroundColor: funnelData.map(d => d.color + '80'),
                    borderColor: funnelData.map(d => d.color),
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: { ...defaultOpts, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } }
        });
    }

    // Tempo médio
    const avgData = @json($reports['avg_time_by_stage']);
    if (avgData.length > 0) {
        new Chart(document.getElementById('chart-avg-time'), {
            type: 'bar',
            data: {
                labels: avgData.map(d => d.stage),
                datasets: [{
                    data: avgData.map(d => d.avg_days),
                    backgroundColor: avgData.map(d => d.color + '80'),
                    borderColor: avgData.map(d => d.color),
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: { ...defaultOpts, indexAxis: 'y', scales: { x: { beginAtZero: true } } }
        });
    }

    // Motivos de perda
    const lostData = @json($reports['lost_reasons']);
    if (lostData.length > 0) {
        const colors = ['#EF4444', '#F97316', '#EAB308', '#6366F1', '#EC4899', '#14B8A6'];
        new Chart(document.getElementById('chart-lost-reasons'), {
            type: 'doughnut',
            data: {
                labels: lostData.map(d => d.lost_reason),
                datasets: [{
                    data: lostData.map(d => d.count),
                    backgroundColor: lostData.map((_, i) => colors[i % colors.length] + 'CC'),
                    borderWidth: 0
                }]
            },
            options: { responsive: true, plugins: { legend: { position: 'right', labels: { boxWidth: 12, font: { size: 11 } } } } }
        });
    }
});
</script>
@endsection
