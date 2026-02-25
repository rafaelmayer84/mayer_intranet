@extends('layouts.app')
@section('title', 'CRM - Relatórios')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Relatórios CRM</h1>
            <p class="text-sm text-gray-500 mt-1">Análise de performance, carteira e engajamento</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('crm.dashboard') }}" class="px-4 py-2 border border-[#385776] text-[#385776] rounded-lg text-sm hover:bg-gray-50">← Meu CRM</a>
            <a href="{{ route('crm.pipeline') }}" class="px-4 py-2 border border-[#385776] text-[#385776] rounded-lg text-sm hover:bg-gray-50">Pipeline</a>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SEÇÃO 1: CARTEIRA POR ADVOGADO (C1)                         --}}
    {{-- ============================================================ --}}
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-[#1B334A] mb-4">👤 Carteira por Advogado</h2>
        @if(count($carteira) > 0)
        <div class="bg-white rounded-lg shadow-sm border overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-500 border-b bg-gray-50">
                        <th class="px-4 py-3">Advogado</th>
                        <th class="px-3 py-3 text-center">Ativos</th>
                        <th class="px-3 py-3 text-center">Onboarding</th>
                        <th class="px-3 py-3 text-center">Adormecidos</th>
                        <th class="px-3 py-3 text-center">Sem Contato 30d</th>
                        <th class="px-3 py-3 text-center">Opps Ganhas</th>
                        <th class="px-3 py-3 text-right">Receita Ganho</th>
                        <th class="px-3 py-3 text-right">Pipeline</th>
                        <th class="px-3 py-3 text-right">Títulos Abertos</th>
                        <th class="px-3 py-3 text-right">Valor Vencido</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($carteira as $c)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-[#1B334A]">{{ $c->owner_name }}</td>
                    <td class="px-3 py-3 text-center text-green-600 font-medium">{{ $c->ativos }}</td>
                    <td class="px-3 py-3 text-center text-blue-600">{{ $c->onboarding }}</td>
                    <td class="px-3 py-3 text-center text-yellow-600">{{ $c->adormecidos }}</td>
                    <td class="px-3 py-3 text-center {{ $c->sem_contato_30d > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">{{ $c->sem_contato_30d }}</td>
                    <td class="px-3 py-3 text-center text-green-600">{{ $c->won_count }}</td>
                    <td class="px-3 py-3 text-right text-green-700 font-medium">R$ {{ number_format($c->receita_won, 0, ',', '.') }}</td>
                    <td class="px-3 py-3 text-right text-[#385776]">R$ {{ number_format($c->pipeline_value, 0, ',', '.') }}</td>
                    <td class="px-3 py-3 text-right text-gray-600">{{ $c->titulos_abertos }}</td>
                    <td class="px-3 py-3 text-right {{ $c->valor_vencido > 0 ? 'text-red-600 font-medium' : 'text-gray-400' }}">R$ {{ number_format($c->valor_vencido, 0, ',', '.') }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="bg-white rounded-lg shadow-sm border p-6"><p class="text-gray-400 text-sm">Nenhum advogado com carteira atribuída.</p></div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- SEÇÃO 2: FUNIL + PIPELINE (existentes + enriquecidos) (C2)  --}}
    {{-- ============================================================ --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
        {{-- Valor projetado --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Valor Projetado (Ponderado)</h2>
            <div class="grid grid-cols-3 gap-4 text-center">
                <div><p class="text-xs text-gray-500">30 dias</p><p class="text-xl font-bold text-[#385776]">R$ {{ number_format($projected['30d'] ?? 0, 0, ',', '.') }}</p></div>
                <div><p class="text-xs text-gray-500">60 dias</p><p class="text-xl font-bold text-[#385776]">R$ {{ number_format($projected['60d'] ?? 0, 0, ',', '.') }}</p></div>
                <div><p class="text-xs text-gray-500">90 dias</p><p class="text-xl font-bold text-[#385776]">R$ {{ number_format($projected['90d'] ?? 0, 0, ',', '.') }}</p></div>
            </div>
        </div>

        {{-- Win Rate --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Win Rate por Responsável</h2>
            @if(count($winRate) > 0)
            <table class="w-full text-sm">
                <thead><tr class="text-left text-gray-500 border-b"><th class="py-2">Responsável</th><th class="text-center">Ganhos</th><th class="text-center">Perdidos</th><th class="text-center">Win Rate</th></tr></thead>
                <tbody>@foreach($winRate as $wr)<tr class="border-b"><td class="py-2">{{ $wr->owner_name ?? 'Sem resp.' }}</td><td class="text-center text-green-600">{{ $wr->won }}</td><td class="text-center text-red-600">{{ $wr->lost }}</td><td class="text-center font-medium">{{ number_format($wr->win_rate, 1) }}%</td></tr>@endforeach</tbody>
            </table>
            @else<p class="text-gray-400 text-sm">Dados insuficientes.</p>@endif
        </div>

        {{-- Funil enriquecido --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Funil de Conversão</h2>
            @if(count($funnel) > 0)
            <div class="space-y-3">
                @php $funnelMax = max(1, collect($funnel)->max('current_count')); @endphp
                @foreach($funnel as $f)
                <div>
                    <div class="flex items-center justify-between text-sm mb-1">
                        <span class="text-gray-700 font-medium">{{ $f['stage_name'] }}</span>
                        <span class="text-gray-500">{{ $f['current_count'] }} opps · R$ {{ number_format($f['current_value'] ?? 0, 0, ',', '.') }}</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-4 overflow-hidden">
                        <div class="h-full rounded-full" style="width: {{ round($f['current_count'] / $funnelMax * 100) }}%; background-color: {{ $f['color'] }}"></div>
                    </div>
                </div>
                @endforeach
            </div>
            @else<p class="text-gray-400 text-sm">Dados insuficientes.</p>@endif
        </div>

        {{-- Motivos de perda --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Top Motivos de Perda</h2>
            @if(count($lostReasons) > 0)
            <div class="space-y-2">@foreach($lostReasons as $lr)<div class="flex items-center justify-between text-sm border-b pb-2"><span class="text-gray-700">{{ $lr->reason ?? '(Sem motivo)' }}</span><span class="text-red-600 font-medium">{{ $lr->count ?? 0 }}</span></div>@endforeach</div>
            @else<p class="text-gray-400 text-sm">Nenhuma perda no período.</p>@endif
        </div>

        {{-- Tempo médio --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Tempo Médio por Estágio</h2>
            @if(count($avgTime) > 0)
            <div class="space-y-2">@foreach($avgTime as $at)<div class="flex items-center justify-between text-sm border-b pb-2"><span class="text-gray-600">{{ $at->from_stage ?? '?' }} → {{ $at->to_stage ?? '?' }}</span><span class="font-medium text-[#385776]">{{ $at->avg_days }} dias</span></div>@endforeach</div>
            @else<p class="text-gray-400 text-sm">Dados insuficientes.</p>@endif
        </div>

        {{-- Conversão por estágio --}}
        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-lg font-semibold text-[#1B334A] mb-4">Conversão entre Estágios</h2>
            @if(count($conversion) > 0)
            <div class="space-y-2">
                @foreach($conversion as $c)
                <div class="flex items-center gap-2 text-sm">
                    <span class="w-28 text-gray-600 truncate">{{ $c['stage'] }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-4 overflow-hidden">
                        <div class="h-full bg-[#385776] rounded-full" style="width: {{ min($c['rate'] ?? 0, 100) }}%"></div>
                    </div>
                    <span class="text-xs font-medium w-16 text-right">{{ $c['entered'] }} ({{ $c['rate'] ? number_format($c['rate'], 1) . '%' : '—' }})</span>
                </div>
                @endforeach
            </div>
            @else<p class="text-gray-400 text-sm">Dados insuficientes.</p>@endif
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- SEÇÃO 3: MAPA DE CALOR DE INATIVIDADE (C3)                  --}}
    {{-- ============================================================ --}}
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-[#1B334A] mb-4">🔥 Mapa de Calor — Inatividade (Clientes Ativos)</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-4">
            @foreach($heatmap as $h)
            <div class="rounded-lg border-2 p-4 text-center cursor-pointer hover:shadow-md transition" style="border-color: {{ $h['cor'] }}" onclick="toggleHeatmapDetail('hm-{{ $loop->index }}')">
                <p class="text-3xl font-bold" style="color: {{ $h['cor'] }}">{{ $h['qty'] }}</p>
                <p class="text-xs text-gray-600 mt-1">{{ $h['label'] }}</p>
            </div>
            @endforeach
        </div>

        {{-- Detalhes expansíveis --}}
        @foreach($heatmap as $h)
        @if($h['qty'] > 0)
        <div id="hm-{{ $loop->index }}" class="hidden mb-4">
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-3" style="color: {{ $h['cor'] }}">{{ $h['label'] }} — amostra ({{ min($h['qty'], 10) }} de {{ $h['qty'] }})</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    @foreach($h['accounts'] as $acc)
                    @php $accObj = (object) $acc; @endphp
                    <a href="{{ route('crm.accounts.show', $accObj->id) }}" class="flex items-center justify-between p-2 rounded hover:bg-gray-50 border text-sm">
                        <span class="font-medium text-gray-800 truncate">{{ $accObj->name }}</span>
                        <span class="text-xs text-gray-400 flex-shrink-0 ml-2">
                            {{ $accObj->last_touch_at ? \Carbon\Carbon::parse($accObj->last_touch_at)->diffForHumans(short: true) : 'Nunca' }}
                        </span>
                    </a>
                    @endforeach
                </div>
                @if($h['qty'] > 10)
                <a href="{{ route('crm.carteira', ['lifecycle' => 'ativo', 'sem_contato_dias' => $h['label'] === 'Sem registro' ? 1 : explode('-', $h['label'])[0] ?? 30]) }}" class="block text-center text-xs text-[#385776] hover:underline mt-3">Ver todos →</a>
                @endif
            </div>
        </div>
        @endif
        @endforeach
    </div>
</div>

@push('scripts')
<script>
function toggleHeatmapDetail(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const isHidden = el.classList.contains('hidden');
    // Fechar todos
    document.querySelectorAll('[id^="hm-"]').forEach(e => e.classList.add('hidden'));
    // Toggle
    if (isHidden) el.classList.remove('hidden');
}
</script>
@endpush
@endsection
