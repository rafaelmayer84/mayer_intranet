@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">NEXO - Automações WhatsApp</h1>
        <p class="text-sm text-gray-600 mt-1">Monitoramento em tempo real</p>
    </div>

    <!-- Estatísticas -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-600 mb-1">Total Tentativas</div>
            <div class="text-3xl font-bold text-gray-900" id="total-tentativas">
                {{ $estatisticasHoje['total_tentativas'] }}
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-600 mb-1">Sucesso</div>
            <div class="text-3xl font-bold text-green-600" id="total-sucesso">
                {{ $estatisticasHoje['sucesso'] }}
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-600 mb-1">Falhas</div>
            <div class="text-3xl font-bold text-red-600" id="total-falhas">
                {{ $estatisticasHoje['falhas'] }}
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <div class="text-sm text-gray-600 mb-1">Taxa Sucesso</div>
            <div class="text-3xl font-bold text-blue-600" id="taxa-sucesso">
                {{ $estatisticasHoje['taxa_sucesso'] }}%
            </div>
        </div>
    </div>

    <!-- Últimas Automações -->
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Últimas Automações</h2>
        </div>
        <div class="divide-y divide-gray-200" id="ultimas-automacoes">
            @foreach($ultimasAutomacoes as $automacao)
            <div class="px-6 py-4 hover:bg-gray-50">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <div class="flex items-center gap-3 mb-2">
                            <span class="text-sm font-mono text-gray-500">{{ $automacao['horario'] }}</span>
                            <span class="text-sm font-medium text-gray-900">{{ $automacao['nome'] }}</span>
                            
                            @if($automacao['status'] === 'sucesso')
                            <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 rounded-full">
                                ✅ {{ $automacao['acao'] }}
                            </span>
                            @elseif($automacao['status'] === 'erro')
                            <span class="px-2 py-1 text-xs font-medium bg-red-100 text-red-800 rounded-full">
                                ❌ {{ $automacao['acao'] }}
                            </span>
                            @else
                            <span class="px-2 py-1 text-xs font-medium bg-yellow-100 text-yellow-800 rounded-full">
                                ⏸️ {{ $automacao['acao'] }}
                            </span>
                            @endif
                        </div>
                        
                        @if($automacao['processo'])
                        <div class="text-sm text-gray-600">
                            Processo: {{ $automacao['processo'] }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Gráfico -->
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Atividade (Última Hora)</h2>
        <canvas id="grafico-hora" height="80"></canvas>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Gráfico
const ctx = document.getElementById('grafico-hora').getContext('2d');
let grafico = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: @json($graficoHora['labels']),
        datasets: [{
            label: 'Automações',
            data: @json($graficoHora['values']),
            backgroundColor: 'rgba(59, 130, 246, 0.5)',
            borderColor: 'rgb(59, 130, 246)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        }
    }
});

// Auto-refresh a cada 10s
setInterval(() => {
    fetch('{{ route("nexo.monitor.dados") }}')
        .then(r => r.json())
        .then(data => {
            // Atualizar estatísticas
            document.getElementById('total-tentativas').textContent = data.estatisticas.total_tentativas;
            document.getElementById('total-sucesso').textContent = data.estatisticas.sucesso;
            document.getElementById('total-falhas').textContent = data.estatisticas.falhas;
            document.getElementById('taxa-sucesso').textContent = data.estatisticas.taxa_sucesso + '%';

            // Atualizar gráfico
            grafico.data.labels = data.grafico.labels;
            grafico.data.datasets[0].data = data.grafico.values;
            grafico.update();
        });
}, 10000);
</script>
@endpush
@endsection
