@extends('layouts.app')

@section('title', 'NEXO Qualidade — Pesquisa de Atendimento')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Pesquisa de Qualidade</h1>
            <p class="text-sm text-gray-500 mt-1">Amostragem semanal &middot; WhatsApp &middot; Satisfação do cliente</p>
        </div>
        @if(in_array(auth()->user()->role, ['admin','socio']))
        <button onclick="document.getElementById('modal-nova-campanha').classList.remove('hidden')"
            class="mt-3 sm:mt-0 inline-flex items-center px-4 py-2 text-sm font-medium text-white rounded-lg shadow"
            style="background:#385776;">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Nova Campanha
        </button>
        @endif
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

    <div class="grid lg:grid-cols-3 gap-6">

        {{-- Coluna Esquerda: Campanhas --}}
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border">
                <div class="px-5 py-4 border-b">
                    <h2 class="font-semibold text-gray-700">Campanhas</h2>
                </div>
                <div class="divide-y">
                    @forelse($campaigns as $c)
                    <div class="px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 truncate">{{ $c->name }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                Amostra: {{ $c->sample_size }} &middot;
                                Lookback: {{ $c->lookback_days }}d &middot;
                                Cooldown: {{ $c->cooldown_days }}d &middot;
                                Alvos: {{ $c->targets_count }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 ml-3 flex-shrink-0">
                            @php
                                $statusColors = ['DRAFT' => 'bg-gray-100 text-gray-600', 'ACTIVE' => 'bg-green-100 text-green-700', 'PAUSED' => 'bg-yellow-100 text-yellow-700', 'ARCHIVED' => 'bg-red-100 text-red-600'];
                            @endphp
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $statusColors[$c->status] ?? 'bg-gray-100 text-gray-600' }}">{{ $c->status }}</span>
                            @if(in_array(auth()->user()->role, ['admin','socio']))
                            <form method="POST" action="{{ route('nexo.qualidade.toggle-status', $c->id) }}" class="inline">
                                @csrf @method('PATCH')
                                <button class="text-xs px-2 py-1 rounded border hover:bg-gray-100 transition" title="Alternar status">
                                    @if($c->status === 'ACTIVE') ⏸ @elseif($c->status === 'PAUSED') ▶ @else ▶ @endif
                                </button>
                            </form>
                            @endif
                            <a href="{{ route('nexo.qualidade.targets', $c->id) }}" class="text-xs px-2 py-1 rounded border hover:bg-gray-100 transition" title="Ver alvos">📋</a>
                            <a href="{{ route('nexo.qualidade.respostas', $c->id) }}" class="text-xs px-2 py-1 rounded border hover:bg-gray-100 transition" title="Ver respostas">📊</a>
                        </div>
                    </div>
                    @empty
                    <div class="px-5 py-8 text-center text-gray-400 text-sm">
                        Nenhuma campanha criada ainda.<br>Clique em "Nova Campanha" para começar.
                    </div>
                    @endforelse
                </div>
            </div>

            {{-- Gráfico Tendência Semanal --}}
            @if($weeklyTrend->isNotEmpty())
            <div class="bg-white rounded-xl shadow-sm border mt-6 p-5">
                <h3 class="font-semibold text-gray-700 mb-4">Tendência Semanal</h3>
                <canvas id="chartTrend" height="200"></canvas>
            </div>
            @endif
        </div>

        {{-- Coluna Direita: Ranking --}}
        <div>
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
        </div>
    </div>
</div>

{{-- Modal Nova Campanha --}}
<div id="modal-nova-campanha" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Nova Campanha de Pesquisa</h3>
        <form method="POST" action="{{ route('nexo.qualidade.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                    <input type="text" name="name" required class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-200 focus:border-blue-400" placeholder="Ex: Pesquisa Mensal Fev/2026">
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Amostra</label>
                        <input type="number" name="sample_size" value="10" min="1" max="500" class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Lookback (dias)</label>
                        <input type="number" name="lookback_days" value="21" min="1" max="365" class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Cooldown (dias)</label>
                        <input type="number" name="cooldown_days" value="60" min="1" max="365" class="w-full border rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button type="button" onclick="document.getElementById('modal-nova-campanha').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm text-white rounded-lg shadow" style="background:#385776;">Criar Campanha</button>
            </div>
        </form>
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
