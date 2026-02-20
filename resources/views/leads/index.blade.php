@extends('layouts.app')

@section('title', 'Central de Leads — Marketing Jurídico')

@section('content')
<style>
.chart-card { position: relative; }
.chart-card .chart-wrap { position: relative; height: 260px; }
</style>
<div class="space-y-4">

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Central de Leads</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Inteligência de Marketing Jurídico</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('leads.index') }}" class="inline-flex items-center px-3 py-2 text-sm font-medium text-gray-700 bg-white dark:bg-gray-800 dark:text-gray-300 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700">
                🔄 Atualizar
            </a>
        </div>
    </div>

    {{-- FILTROS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
        <form method="GET" action="{{ route('leads.index') }}" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            <select name="periodo" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" onchange="this.form.submit()">
                <option value="todos" {{ $filtroPeriodo == 'todos' ? 'selected' : '' }}>Todo período</option>
                <option value="hoje" {{ $filtroPeriodo == 'hoje' ? 'selected' : '' }}>Hoje</option>
                <option value="semana" {{ $filtroPeriodo == 'semana' ? 'selected' : '' }}>Última semana</option>
                <option value="mes" {{ $filtroPeriodo == 'mes' ? 'selected' : '' }}>Último mês</option>
                <option value="trimestre" {{ $filtroPeriodo == 'trimestre' ? 'selected' : '' }}>Último trimestre</option>
            </select>

            <select name="area" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" onchange="this.form.submit()">
                <option value="todos">Todas as áreas</option>
                @foreach($areas as $area)
                    <option value="{{ $area }}" {{ $filtroArea == $area ? 'selected' : '' }}>{{ $area }}</option>
                @endforeach
            </select>

            <select name="cidade" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" onchange="this.form.submit()">
                <option value="todos">Todas as cidades</option>
                @foreach($cidades as $cidade)
                    <option value="{{ $cidade }}" {{ $filtroCidade == $cidade ? 'selected' : '' }}>{{ $cidade }}</option>
                @endforeach
            </select>

            <select name="intencao" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" onchange="this.form.submit()">
                <option value="todos">Toda intenção</option>
                <option value="sim" {{ $filtroIntencao == 'sim' ? 'selected' : '' }}>✅ Sim</option>
                <option value="talvez" {{ $filtroIntencao == 'talvez' ? 'selected' : '' }}>⚠️ Talvez</option>
                <option value="não" {{ $filtroIntencao == 'não' ? 'selected' : '' }}>❌ Não</option>
            </select>

            <select name="origem" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" onchange="this.form.submit()">
                <option value="todos">Todas as origens</option>
                @foreach($origens as $origem)
                    <option value="{{ $origem }}" {{ $filtroOrigem == $origem ? 'selected' : '' }}>{{ $origem }}</option>
                @endforeach
            </select>

            <select name="potencial" class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm" onchange="this.form.submit()">
                <option value="todos">Todo potencial</option>
                <option value="alto" {{ $filtroPotencial == 'alto' ? 'selected' : '' }}>🟢 Alto</option>
                <option value="médio" {{ $filtroPotencial == 'médio' ? 'selected' : '' }}>🟡 Médio</option>
                <option value="baixo" {{ $filtroPotencial == 'baixo' ? 'selected' : '' }}>⚪ Baixo</option>
            </select>
        </form>
    </div>

    {{-- KPIs PRINCIPAIS --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @include('dashboard.partials._kpi-card', [
            'id' => 'leads-total',
            'title' => 'Total de Leads',
            'value' => $totalLeads,
            'icon' => '📊',
            'accent' => 'blue',
            'meta' => '-',
            'percent' => 0
        ])
        @include('dashboard.partials._kpi-card', [
            'id' => 'leads-interesse',
            'title' => 'Taxa Interesse',
            'value' => $taxaInteresse . '%',
            'icon' => '🎯',
            'accent' => 'green',
            'meta' => '70%',
            'percent' => $taxaInteresse
        ])
        @include('dashboard.partials._kpi-card', [
            'id' => 'leads-conversao',
            'title' => 'Taxa Conversão',
            'value' => $taxaConversao . '%',
            'icon' => '💰',
            'accent' => 'purple',
            'meta' => '30%',
            'percent' => $taxaConversao
        ])
        @include('dashboard.partials._kpi-card', [
            'id' => 'leads-erro',
            'title' => 'Com Erro',
            'value' => $leadsComErro,
            'icon' => '⚠️',
            'accent' => 'orange',
            'meta' => '0',
            'percent' => 0
        ])
    </div>

    {{-- GRÁFICOS LINHA 1: Timeline + Funil de Intenção --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Timeline 30 dias --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">📈 Leads por Dia (30 dias)</h3>
            <div class="chart-wrap"><canvas id="chartTimeline"></canvas></div>
        </div>

        {{-- Funil de Intenção --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">🎯 Funil de Intenção de Contratar</h3>
            <div class="chart-wrap"><canvas id="chartIntencao"></canvas></div>
        </div>
    </div>

    {{-- GRÁFICOS LINHA 2: Áreas + Origem --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Por Área Jurídica --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">⚖️ Leads por Área Jurídica</h3>
            <div class="chart-wrap"><canvas id="chartArea"></canvas></div>
        </div>

        {{-- Por Origem/Canal --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">📡 Origem dos Leads</h3>
            <div class="chart-wrap"><canvas id="chartOrigem"></canvas></div>
        </div>
    </div>

    {{-- GRÁFICOS LINHA 3: Potencial + Urgência + Perfil --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        {{-- Potencial de Honorários --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">💰 Potencial Honorários</h3>
            <div class="chart-wrap"><canvas id="chartPotencial"></canvas></div>
        </div>

        {{-- Urgência --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">🚨 Nível de Urgência</h3>
            <div class="chart-wrap"><canvas id="chartUrgencia"></canvas></div>
        </div>

        {{-- Perfil Socioeconômico --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">👤 Perfil Socioeconômico</h3>
            <div class="chart-wrap"><canvas id="chartPerfil"></canvas></div>
        </div>
    </div>

    {{-- GRÁFICOS LINHA 4: Sub-áreas + Gatilho Emocional --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

        {{-- Sub-áreas --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">📋 Top Sub-áreas Jurídicas</h3>
            <div class="chart-wrap"><canvas id="chartSubArea"></canvas></div>
        </div>

        {{-- Gatilho Emocional --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">💡 Gatilhos Emocionais</h3>
            <div class="chart-wrap"><canvas id="chartGatilho"></canvas></div>
        </div>
    </div>

    {{-- NUVEM DE PALAVRAS-CHAVE --}}
    @if(count($topPalavras) > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">🔑 Palavras-chave para Google Ads (Top 25)</h3>
        <div class="flex flex-wrap gap-2">
            @php $maxCount = max($topPalavras); @endphp
            @foreach($topPalavras as $palavra => $count)
                @php
                    $ratio = $maxCount > 0 ? $count / $maxCount : 0;
                    if ($ratio > 0.7) { $sizeClass = 'text-xl font-bold'; $bgClass = 'bg-brand text-white'; }
                    elseif ($ratio > 0.4) { $sizeClass = 'text-base font-semibold'; $bgClass = 'bg-blue-500 text-white'; }
                    elseif ($ratio > 0.2) { $sizeClass = 'text-sm font-medium'; $bgClass = 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'; }
                    else { $sizeClass = 'text-xs'; $bgClass = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'; }
                @endphp
                <span class="inline-flex items-center px-3 py-1 rounded-full {{ $bgClass }} {{ $sizeClass }}" title="{{ $count }}x">
                    {{ $palavra }} <span class="ml-1 opacity-70">({{ $count }})</span>
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- POR CIDADE --}}
    @if($dadosCidade->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-4" >
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-2">📍 Leads por Cidade</h3>
        <div class="chart-wrap"><canvas id="chartCidade"></canvas></div>
    </div>
    @endif

    {{-- TABELA DE LEADS RECENTES --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">📋 Todos os Leads</h3>
        </div>
        <div class="overflow-x-auto">
            {{-- Barra de Exportação Google Ads --}}
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $totalLeads }} leads encontrados</span>
                <div class="flex gap-2">
                    <a href="{{ route('leads.export-google-ads', array_merge(request()->query(), ['formato' => 'csv'])) }}"
                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        CSV Google Ads
                    </a>
                    <a href="{{ route('leads.export-google-ads', array_merge(request()->query(), ['formato' => 'xls'])) }}"
                       class="btn-mayer">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        XLS Google Ads
                    </a>
                </div>
            </div>
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900">
                    <tr>
                        @php
                            $cols = [
                                'id' => '#',
                                'nome' => 'Nome',
                                'area_interesse' => 'Área',
                                'cidade' => 'Cidade',
                                'intencao_contratar' => 'Intenção',
                                'potencial_honorarios' => 'Potencial',
                                'urgencia' => 'Urgência',
                                'origem_canal' => 'Origem',
                                'status' => 'Status',
                                'data_entrada' => 'Data',
                            ];
                        @endphp
                        @foreach($cols as $col => $label)
                            @php
                                $isSorted = ($sortField === $col);
                                $nextOrder = ($isSorted && $sortOrder === 'asc') ? 'desc' : 'asc';
                                $arrow = $isSorted ? ($sortOrder === 'asc' ? '▲' : '▼') : '';
                                $qp = array_merge(request()->query(), ['sort' => $col, 'order' => $nextOrder, 'page' => 1]);
                            @endphp
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase">
                                <a href="{{ route('leads.index', $qp) }}" class="inline-flex items-center gap-1 {{ $isSorted ? 'text-[#385776] font-bold' : 'text-gray-500 dark:text-gray-400' }} hover:text-[#385776] transition">
                                    {{ $label }}
                                    @if($arrow)<span class="text-[10px]">{{ $arrow }}</span>@endif
                                </a>
                            </th>
                        @endforeach
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @forelse($leads as $lead)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $lead->id }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('leads.show', $lead) }}" class="text-sm font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                {{ $lead->nome }}
                            </a>
                            @if($lead->erro_processamento)
                                <span class="ml-1 text-xs text-red-500">⚠️</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $lead->area_interesse ?? '-' }}</td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $lead->cidade ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($lead->intencao_contratar === 'sim')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">✅ Sim</span>
                            @elseif($lead->intencao_contratar === 'talvez')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">⚠️ Talvez</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">❌ Não</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($lead->potencial_honorarios === 'alto')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">🟢 Alto</span>
                            @elseif($lead->potencial_honorarios === 'médio')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">🟡 Médio</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">⚪ {{ $lead->potencial_honorarios ?: '-' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if($lead->urgencia === 'crítica')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">🔴 Crítica</span>
                            @elseif($lead->urgencia === 'alta')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">🟠 Alta</span>
                            @elseif($lead->urgencia === 'média')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">🟡 Média</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">{{ $lead->urgencia ?: '-' }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $lead->origem_label ?? '-' }}</td>
                        <td class="px-4 py-3">
                            @if($lead->status === 'novo')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">Novo</span>
                            @elseif($lead->status === 'contatado')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Contatado</span>
                            @elseif($lead->status === 'convertido')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Convertido</span>
                            @elseif($lead->status === 'descartado')
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Descartado</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">{{ ucfirst($lead->status ?? '-') }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $lead->data_entrada ? $lead->data_entrada->format('d/m H:i') : '-' }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('leads.show', $lead) }}" class="text-blue-600 dark:text-blue-400 hover:underline text-sm">Ver</a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">Nenhum lead encontrado com os filtros aplicados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            {{-- Paginação --}}
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                {{ $leads->links() }}
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const isDark = document.documentElement.classList.contains('dark');
    const textColor = isDark ? '#e5e7eb' : '#374151';
    const gridColor = isDark ? 'rgba(255,255,255,0.1)' : 'rgba(0,0,0,0.1)';

    Chart.defaults.color = textColor;
    Chart.defaults.plugins.legend.labels.color = textColor;

    const palette = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4','#ec4899','#f97316','#14b8a6','#6366f1'];

    // ====== TIMELINE ======
    const timelineCtx = document.getElementById('chartTimeline');
    if (timelineCtx) {
        new Chart(timelineCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode($dadosTimeline->pluck('data')->map(fn($d) => \Carbon\Carbon::parse($d)->format('d/m'))) !!},
                datasets: [{
                    label: 'Leads',
                    data: {!! json_encode($dadosTimeline->pluck('total')) !!},
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 6
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // ====== FUNIL DE INTENÇÃO ======
    const intencaoCtx = document.getElementById('chartIntencao');
    if (intencaoCtx) {
        const intencaoData = {!! json_encode($dadosIntencao) !!};
        const intencaoLabels = intencaoData.map(d => {
            if (d.intencao_contratar === 'sim') return '✅ Sim';
            if (d.intencao_contratar === 'talvez') return '⚠️ Talvez';
            return '❌ Não';
        });
        const intencaoColors = intencaoData.map(d => {
            if (d.intencao_contratar === 'sim') return '#10b981';
            if (d.intencao_contratar === 'talvez') return '#f59e0b';
            return '#ef4444';
        });
        new Chart(intencaoCtx, {
            type: 'doughnut',
            data: {
                labels: intencaoLabels,
                datasets: [{
                    data: intencaoData.map(d => d.total),
                    backgroundColor: intencaoColors,
                    borderWidth: 2,
                    borderColor: isDark ? '#1f2937' : '#ffffff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    }

    // ====== ÁREAS JURÍDICAS ======
    const areaCtx = document.getElementById('chartArea');
    if (areaCtx) {
        const areaData = {!! json_encode($dadosArea) !!};
        new Chart(areaCtx, {
            type: 'bar',
            data: {
                labels: areaData.map(d => d.area_interesse),
                datasets: [{
                    label: 'Leads',
                    data: areaData.map(d => d.total),
                    backgroundColor: palette,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // ====== ORIGEM ======
    const origemCtx = document.getElementById('chartOrigem');
    if (origemCtx) {
        const origemData = {!! json_encode($dadosOrigem) !!};
        const origemLabels = origemData.map(d => {
            const map = {
                'google_ads': '🔍 Google Ads',
                'indicacao': '🤝 Indicação',
                'redes_sociais': '📱 Redes Sociais',
                'organico': '🌐 Orgânico',
                'outro': '📌 Outro',
                'nao_identificado': '❓ Não identificado'
            };
            return map[d.origem_canal] || d.origem_canal;
        });
        new Chart(origemCtx, {
            type: 'doughnut',
            data: {
                labels: origemLabels,
                datasets: [{
                    data: origemData.map(d => d.total),
                    backgroundColor: ['#3b82f6','#10b981','#ec4899','#06b6d4','#f59e0b','#9ca3af'],
                    borderWidth: 2,
                    borderColor: isDark ? '#1f2937' : '#ffffff'
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // ====== POTENCIAL ======
    const potencialCtx = document.getElementById('chartPotencial');
    if (potencialCtx) {
        const potData = {!! json_encode($dadosPotencial) !!};
        const potColors = potData.map(d => {
            if (d.potencial_honorarios === 'alto') return '#10b981';
            if (d.potencial_honorarios === 'médio') return '#f59e0b';
            return '#9ca3af';
        });
        new Chart(potencialCtx, {
            type: 'doughnut',
            data: {
                labels: potData.map(d => d.potencial_honorarios ? d.potencial_honorarios.charAt(0).toUpperCase() + d.potencial_honorarios.slice(1) : 'N/A'),
                datasets: [{
                    data: potData.map(d => d.total),
                    backgroundColor: potColors,
                    borderWidth: 2,
                    borderColor: isDark ? '#1f2937' : '#ffffff'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    // ====== URGÊNCIA ======
    const urgenciaCtx = document.getElementById('chartUrgencia');
    if (urgenciaCtx) {
        const urgData = {!! json_encode($dadosUrgencia) !!};
        const urgColors = urgData.map(d => {
            if (d.urgencia === 'crítica') return '#ef4444';
            if (d.urgencia === 'alta') return '#f97316';
            if (d.urgencia === 'média') return '#f59e0b';
            return '#10b981';
        });
        new Chart(urgenciaCtx, {
            type: 'doughnut',
            data: {
                labels: urgData.map(d => d.urgencia ? d.urgencia.charAt(0).toUpperCase() + d.urgencia.slice(1) : 'N/A'),
                datasets: [{
                    data: urgData.map(d => d.total),
                    backgroundColor: urgColors,
                    borderWidth: 2,
                    borderColor: isDark ? '#1f2937' : '#ffffff'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    // ====== PERFIL SOCIOECONÔMICO ======
    const perfilCtx = document.getElementById('chartPerfil');
    if (perfilCtx) {
        const perfilData = {!! json_encode($dadosPerfil) !!};
        new Chart(perfilCtx, {
            type: 'doughnut',
            data: {
                labels: perfilData.map(d => 'Classe ' + (d.perfil_socioeconomico || 'N/A')),
                datasets: [{
                    data: perfilData.map(d => d.total),
                    backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444'],
                    borderWidth: 2,
                    borderColor: isDark ? '#1f2937' : '#ffffff'
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });
    }

    // ====== SUB-ÁREAS ======
    const subAreaCtx = document.getElementById('chartSubArea');
    if (subAreaCtx) {
        const subData = {!! json_encode($dadosSubArea) !!};
        new Chart(subAreaCtx, {
            type: 'bar',
            data: {
                labels: subData.map(d => d.sub_area),
                datasets: [{
                    label: 'Leads',
                    data: subData.map(d => d.total),
                    backgroundColor: palette,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                    y: { grid: { display: false } }
                }
            }
        });
    }

    // ====== GATILHO EMOCIONAL ======
    const gatilhoCtx = document.getElementById('chartGatilho');
    if (gatilhoCtx) {
        const gatilhoData = {!! json_encode($dadosGatilho) !!};
        new Chart(gatilhoCtx, {
            type: 'bar',
            data: {
                labels: gatilhoData.map(d => d.gatilho_emocional),
                datasets: [{
                    label: 'Leads',
                    data: gatilhoData.map(d => d.total),
                    backgroundColor: palette.slice(0, gatilhoData.length),
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    // ====== CIDADE ======
    const cidadeCtx = document.getElementById('chartCidade');
    if (cidadeCtx) {
        const cidadeData = {!! json_encode($dadosCidade) !!};
        new Chart(cidadeCtx, {
            type: 'bar',
            data: {
                labels: cidadeData.map(d => d.cidade),
                datasets: [{
                    label: 'Leads',
                    data: cidadeData.map(d => d.total),
                    backgroundColor: '#3b82f6',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { precision: 0 }, grid: { color: gridColor } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>
@endpush
