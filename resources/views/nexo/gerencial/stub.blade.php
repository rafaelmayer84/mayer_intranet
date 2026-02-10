@extends('layouts.app')

@section('title', 'Nexo — Visão Gerencial')

@section('content')
<div class="max-w-4xl mx-auto py-8">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-8 border-t-4 border-purple-500">
        <div class="flex items-center gap-4 mb-6">
            <div class="text-4xl">📊</div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Nexo — Visão Gerencial</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Fase 1 implantada. Endpoint de dados já funcional.</p>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 mb-6">
            <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">KPIs (dados reais do banco)</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="kpi-grid">
                <div class="bg-white dark:bg-gray-800 rounded p-3 text-center shadow-sm">
                    <div class="text-lg font-bold text-emerald-600" id="kpi-abertas">—</div>
                    <div class="text-xs text-gray-500">Conversas Abertas</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded p-3 text-center shadow-sm">
                    <div class="text-lg font-bold text-red-600" id="kpi-nao-lidas">—</div>
                    <div class="text-xs text-gray-500">Não Lidas</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded p-3 text-center shadow-sm">
                    <div class="text-lg font-bold text-blue-600" id="kpi-sla">—</div>
                    <div class="text-xs text-gray-500">SLA Média 7d (min)</div>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded p-3 text-center shadow-sm">
                    <div class="text-lg font-bold text-purple-600" id="kpi-conversao">—</div>
                    <div class="text-xs text-gray-500">Conversão (%)</div>
                </div>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
            <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Status</h3>
            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                <li>✅ NexoGerencialService com queries de KPIs e gráficos</li>
                <li>✅ Endpoint JSON /nexo/gerencial/data funcional</li>
                <li>⏳ Dashboard com 6 gráficos Chart.js (Fase 3)</li>
                <li>⏳ Filtros interativos (Fase 3)</li>
            </ul>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ url('/nexo/gerencial/data') }}" 
               class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition text-sm">
                Ver API JSON (Dados Gerenciais)
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('{{ url("/nexo/gerencial/data") }}')
        .then(r => r.json())
        .then(data => {
            if (data.kpis) {
                document.getElementById('kpi-abertas').textContent = data.kpis.conversas_abertas ?? 0;
                document.getElementById('kpi-nao-lidas').textContent = data.kpis.nao_lidas ?? 0;
                document.getElementById('kpi-sla').textContent = data.kpis.sla_media_7d ?? '—';
                document.getElementById('kpi-conversao').textContent = data.kpis.taxa_conversao ? data.kpis.taxa_conversao + '%' : '—';
            }
        })
        .catch(() => {});
});
</script>
@endsection
