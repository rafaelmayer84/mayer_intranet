@extends('layouts.app')

@section('title', 'NEXO Qualidade — Monitoramento')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Pesquisa de Qualidade</h1>
            <p class="text-sm text-gray-500 mt-1">Monitoramento automático &middot; WhatsApp &middot; Scan diário 20h</p>
        </div>
        <div class="flex items-center gap-3 mt-3 sm:mt-0">
            @php
                $statusLabel = ['DRAFT' => 'Inativo', 'ACTIVE' => 'Ativo', 'PAUSED' => 'Pausado', 'ARCHIVED' => 'Arquivado'];
                $statusColor = ['DRAFT' => 'bg-gray-100 text-gray-600', 'ACTIVE' => 'bg-green-100 text-green-700', 'PAUSED' => 'bg-yellow-100 text-yellow-700', 'ARCHIVED' => 'bg-red-100 text-red-600'];
            @endphp
            <span class="text-xs px-3 py-1 rounded-full font-medium {{ $statusColor[$campaign->status] ?? 'bg-gray-100 text-gray-600' }}">
                {{ $statusLabel[$campaign->status] ?? $campaign->status }}
            </span>
            @if(auth()->user()->role === 'admin')
            <form method="POST" action="{{ route('nexo.qualidade.toggle-status', $campaign->id) }}" class="inline">
                @csrf @method('PATCH')
                <button class="text-xs px-3 py-1.5 rounded-lg border hover:bg-gray-100 transition font-medium">
                    @if($campaign->status === 'ACTIVE') ⏸ Pausar @elseif($campaign->status === 'PAUSED') ▶ Retomar @else ▶ Ativar @endif
                </button>
            </form>
            @endif
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-200 text-green-700 rounded-lg text-sm">{{ session('success') }}</div>
    @endif

    {{-- KPI Cards --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Respostas (4 sem)</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">{{ $globalStats->total_responses ?? 0 }}</p>
            <p class="text-xs text-gray-400 mt-1">de {{ $globalStats->total_sent ?? 0 }} enviadas</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Taxa de Resposta</p>
            <p class="text-2xl font-bold mt-1" style="color:#385776;">{{ $responseRate }}%</p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Nota Média (1-5)</p>
            <p class="text-2xl font-bold mt-1 {{ ($globalStats->global_avg_score ?? 0) >= 4 ? 'text-green-600' : (($globalStats->global_avg_score ?? 0) >= 3 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $globalStats->global_avg_score ? number_format($globalStats->global_avg_score, 1, ',', '.') : '—' }}
            </p>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">NPS Score</p>
            <p class="text-2xl font-bold mt-1 {{ ($globalStats->global_nps ?? 0) >= 50 ? 'text-green-600' : (($globalStats->global_nps ?? 0) >= 0 ? 'text-yellow-600' : 'text-red-600') }}">
                {{ $globalStats->global_nps !== null ? number_format($globalStats->global_nps, 0) : '—' }}
            </p>
            <p class="text-xs text-gray-400 mt-1">
                <span class="text-green-500">{{ $globalStats->total_promoters ?? 0 }}P</span> /
                <span class="text-gray-400">{{ $globalStats->total_passives ?? 0 }}N</span> /
                <span class="text-red-500">{{ $globalStats->total_detractors ?? 0 }}D</span>
            </p>
        </div>
    </div>

    {{-- Contadores de Status --}}
    <div class="grid grid-cols-4 gap-3 mb-6">
        <a href="{{ route('nexo.qualidade.targets', ['campaign' => $campaign->id, 'status' => 'PENDING']) }}" class="bg-yellow-50 border border-yellow-200 rounded-lg p-3 text-center hover:bg-yellow-100 transition">
            <p class="text-lg font-bold text-yellow-700">{{ $counters->pending }}</p>
            <p class="text-[10px] text-yellow-600 uppercase">Pendentes</p>
        </a>
        <a href="{{ route('nexo.qualidade.targets', ['campaign' => $campaign->id, 'status' => 'SENT']) }}" class="bg-green-50 border border-green-200 rounded-lg p-3 text-center hover:bg-green-100 transition">
            <p class="text-lg font-bold text-green-700">{{ $counters->sent }}</p>
            <p class="text-[10px] text-green-600 uppercase">Enviados</p>
        </a>
        <a href="{{ route('nexo.qualidade.targets', ['campaign' => $campaign->id, 'status' => 'SKIPPED']) }}" class="bg-gray-50 border border-gray-200 rounded-lg p-3 text-center hover:bg-gray-100 transition">
            <p class="text-lg font-bold text-gray-600">{{ $counters->skipped }}</p>
            <p class="text-[10px] text-gray-500 uppercase">Ignorados</p>
        </a>
        <a href="{{ route('nexo.qualidade.targets', ['campaign' => $campaign->id, 'status' => 'FAILED']) }}" class="bg-red-50 border border-red-200 rounded-lg p-3 text-center hover:bg-red-100 transition">
            <p class="text-lg font-bold text-red-600">{{ $counters->failed }}</p>
            <p class="text-[10px] text-red-500 uppercase">Falhas</p>
        </a>
    </div>

    <div class="grid lg:grid-cols-3 gap-6">

        {{-- Coluna Esquerda 2/3 --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Últimos Disparos --}}
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700">Últimos Disparos</h2>
                    <a href="{{ route('nexo.qualidade.targets', $campaign->id) }}" class="text-xs text-blue-600 hover:underline">Ver todos →</a>
                </div>
                <div class="divide-y">
                    @forelse($recentTargets as $t)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-700">{{ $t->responsibleUser->name ?? '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $t->sampled_at ? $t->sampled_at->format('d/m H:i') : '—' }} &middot; {{ $t->source_type }}</p>
                        </div>
                        <div class="ml-3 flex-shrink-0">
                            @php
                                $colors = ['PENDING' => 'bg-yellow-50 text-yellow-700', 'SENT' => 'bg-green-50 text-green-700', 'FAILED' => 'bg-red-50 text-red-700', 'SKIPPED' => 'bg-gray-100 text-gray-500'];
                            @endphp
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $colors[$t->send_status] ?? '' }}">{{ $t->send_status }}</span>
                        </div>
                    </div>
                    @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">
                        Nenhum disparo realizado.<br>
                        @if($campaign->status !== 'ACTIVE')
                        <span class="text-yellow-600">Ative a pesquisa para iniciar os disparos automáticos.</span>
                        @else
                        O scan diário às 20h detectará interações e enviará pesquisas.
                        @endif
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Últimas Respostas --}}
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-5 py-4 border-b flex items-center justify-between">
                    <h2 class="font-semibold text-gray-700">Últimas Respostas</h2>
                    <a href="{{ route('nexo.qualidade.respostas', $campaign->id) }}" class="text-xs text-blue-600 hover:underline">Ver todas →</a>
                </div>
                <div class="divide-y">
                    @forelse($recentRespostas as $r)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm text-gray-700">{{ $r->advogado_nome ?? '—' }}</p>
                            <p class="text-xs text-gray-400">{{ $r->answered_at ? \Carbon\Carbon::parse($r->answered_at)->format('d/m H:i') : '—' }}</p>
                        </div>
                        <div class="flex items-center gap-3 ml-3 flex-shrink-0">
                            @if($r->score_1_5)
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                {{ $r->score_1_5 >= 4 ? 'bg-green-100 text-green-700' : ($r->score_1_5 >= 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ $r->score_1_5 }}
                            </span>
                            @endif
                            @if($r->nps !== null)
                            <span class="text-xs font-medium {{ $r->nps >= 9 ? 'text-green-600' : ($r->nps >= 7 ? 'text-gray-500' : 'text-red-600') }}">NPS {{ $r->nps }}</span>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">Nenhuma resposta recebida ainda.</div>
                    @endforelse
                </div>
            </div>

            {{-- Gráfico Tendência --}}
            @if($weeklyTrend->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border p-5">
                <h3 class="font-semibold text-gray-700 mb-4">Tendência Semanal</h3>
                <canvas id="chartTrend" height="200"></canvas>
            </div>
            @endif
        </div>

        {{-- Coluna Direita 1/3 --}}
        <div class="space-y-6">

            {{-- Ranking --}}
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-5 py-4 border-b">
                    <h2 class="font-semibold text-gray-700">Ranking por Advogado</h2>
                    <p class="text-xs text-gray-400">Últimas 4 semanas</p>
                </div>
                <div class="divide-y">
                    @forelse($ranking as $i => $r)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <span class="text-sm font-bold {{ $i === 0 ? 'text-yellow-500' : 'text-gray-400' }}">{{ $i + 1 }}º</span>
                            <div>
                                <p class="text-sm font-medium text-gray-800">{{ $r->name }}</p>
                                <p class="text-xs text-gray-400">{{ $r->total_responses }} respostas</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold {{ $r->avg_score >= 4 ? 'text-green-600' : ($r->avg_score >= 3 ? 'text-yellow-600' : 'text-red-600') }}">
                                {{ number_format($r->avg_score, 1, ',', '.') }}
                            </p>
                            <p class="text-[10px] text-gray-400">NPS {{ number_format($r->nps_score, 0) }}</p>
                        </div>
                    </div>
                    @empty
                    <div class="px-5 py-6 text-center text-gray-400 text-sm">Sem dados ainda.</div>
                    @endforelse
                </div>
            </div>

            {{-- Configuração --}}
            @if(auth()->user()->role === 'admin')
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-5 py-4 border-b">
                    <h2 class="font-semibold text-gray-700">Configuração</h2>
                </div>
                <form method="POST" action="{{ route('nexo.qualidade.update-config', $campaign->id) }}" class="p-5 space-y-4">
                    @csrf @method('PATCH')
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Disparos por dia</label>
                        <input type="number" name="sample_size" value="{{ $campaign->sample_size }}" min="1" max="100" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <p class="text-[10px] text-gray-400 mt-1">Máximo de pesquisas enviadas por scan diário</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Janela de interação (dias)</label>
                        <input type="number" name="lookback_days" value="{{ $campaign->lookback_days }}" min="1" max="90" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <p class="text-[10px] text-gray-400 mt-1">Considera interações dos últimos X dias</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Cooldown (dias)</label>
                        <input type="number" name="cooldown_days" value="{{ $campaign->cooldown_days }}" min="1" max="365" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-400">
                        <p class="text-[10px] text-gray-400 mt-1">Não envia pesquisa para o mesmo cliente dentro deste período</p>
                    </div>
                    <button type="submit" class="w-full px-4 py-2 text-sm text-white rounded-lg shadow transition" style="background:#385776;">Salvar</button>
                </form>
                <div class="px-5 pb-5">
                    <form method="POST" action="{{ route('nexo.qualidade.destroy', $campaign->id) }}" onsubmit="return confirm('Tem certeza? Isso excluirá TODOS os dados da pesquisa (targets, respostas, agregações).')">
                        @csrf @method('DELETE')
                        <button type="submit" class="w-full px-4 py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition">Excluir campanha e dados</button>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('chartTrend');
    if (!canvas) return;

    const data = @json($weeklyTrend);
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: data.map(d => d.week_start),
            datasets: [
                {
                    label: 'Nota Média',
                    data: data.map(d => d.avg_score),
                    borderColor: '#385776',
                    backgroundColor: 'rgba(56,87,118,0.1)',
                    tension: 0.3,
                    yAxisID: 'y',
                    fill: true,
                },
                {
                    label: 'NPS',
                    data: data.map(d => d.nps),
                    borderColor: '#10b981',
                    borderDash: [5,5],
                    tension: 0.3,
                    yAxisID: 'y1',
                }
            ]
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: { position: 'left', min: 1, max: 5, title: { display: true, text: 'Nota (1-5)' } },
                y1: { position: 'right', min: -100, max: 100, title: { display: true, text: 'NPS' }, grid: { drawOnChartArea: false } }
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });
});
</script>
@endpush
