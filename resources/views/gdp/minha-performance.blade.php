@extends('layouts.app')
@section('title', 'GDP — Minha Performance')
@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                <span class="text-lg">📈</span> GDP — {{ $targetUser->id === $user->id ? 'Minha Performance' : $targetUser->name }}
            </h1>
            <p class="mt-1 text-xs text-gray-500">
                Gestão de Desempenho de Pessoas | Competência: {{ $refDate->translatedFormat('F/Y') }}
                @if($ciclo)
                    | Ciclo: {{ $ciclo->nome }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            {{-- Seletor de usuário (coordenador/admin) --}}
            @if($usuariosDisponiveis->isNotEmpty())
            <form method="GET" action="{{ route('gdp.minha-performance') }}" class="flex items-center gap-2">
                <input type="hidden" name="month" value="{{ $mes }}">
                <input type="hidden" name="year" value="{{ $ano }}">
                <select name="user_id" onchange="this.form.submit()" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500">
                    @foreach($usuariosDisponiveis as $u)
                        <option value="{{ $u->id }}" {{ $targetUser->id === $u->id ? 'selected' : '' }}>
                            {{ $u->name }} ({{ $u->role }})
                        </option>
                    @endforeach
                </select>
            </form>
            @endif

            {{-- Filtro mês/ano --}}
            <form method="GET" action="{{ route('gdp.minha-performance') }}" class="flex items-center gap-2">
                @if($targetUser->id !== $user->id)
                    <input type="hidden" name="user_id" value="{{ $targetUser->id }}">
                @endif
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

            {{-- Botão Apurar (admin only) --}}
            @if($user->isAdmin())
            <button onclick="apurarMes()" id="btn-apurar" class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm transition" style="background-color:#1B334A;">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Apurar Mês
            </button>
            @endif
        </div>
    </div>

    {{-- SEM CICLO ATIVO --}}
    @if(!$ciclo)
    <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-6 text-center">
        <p class="text-sm text-yellow-800">Nenhum ciclo GDP ativo. O administrador precisa criar e abrir um ciclo para iniciar as apurações.</p>
    </div>
    @else

    {{-- SCORE CARD PRINCIPAL --}}
    <div class="grid grid-cols-1 lg:grid-cols-6 gap-4">
        {{-- Score Total --}}
        <div class="lg:col-span-1 rounded-xl border border-gray-200 bg-white p-5 shadow-sm text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Score Total</p>
            <p class="mt-2 text-4xl font-extrabold" style="color:#385776;">
                {{ $snapshot ? number_format($snapshot->score_total, 1, ',', '.') : '—' }}
            </p>
            @if($snapshot && $snapshot->ranking)
                <p class="mt-1 text-sm text-gray-500">
                    🏆 {{ $snapshot->ranking }}º lugar
                </p>
            @endif
            @if(!$snapshot)
                <p class="mt-2 text-xs text-gray-400">Sem dados apurados para este mês</p>
            @endif
        </div>

        {{-- Scores por Eixo --}}
        @php
            $eixoCards = [
                ['label' => 'Jurídico', 'field' => 'score_juridico', 'icon' => '⚖️', 'color' => '#2563eb'],
                ['label' => 'Financeiro', 'field' => 'score_financeiro', 'icon' => '💰', 'color' => '#16a34a'],
                ['label' => 'Desenvolvimento', 'field' => 'score_desenvolvimento', 'icon' => '📚', 'color' => '#9333ea'],
                ['label' => 'Atendimento', 'field' => 'score_atendimento', 'icon' => '💬', 'color' => '#ea580c'],
            ];
        @endphp
        @foreach($eixoCards as $ec)
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $ec['icon'] }} {{ $ec['label'] }}</p>
            <p class="mt-2 text-2xl font-bold" style="color:{{ $ec['color'] }};">
                {{ $snapshot ? number_format($snapshot->{$ec['field']}, 1, ',', '.') : '—' }}
            </p>
        </div>
        @endforeach

        {{-- Card Eval 180° --}}
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm text-center">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">📋 Eval 180°</p>
            <p class="mt-2 text-2xl font-bold {{ $snapshot && $snapshot->score_eval180 !== null ? ($snapshot->score_eval180 >= 3.0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                {{ $snapshot && $snapshot->score_eval180 !== null ? number_format($snapshot->score_eval180, 1, ',', '.') : '—' }}
            </p>
            @if($snapshot && $snapshot->score_eval180 !== null)
                <p class="mt-1 text-[10px] text-gray-400">Nota gestor (1-5)</p>
            @else
                <p class="mt-1 text-[10px] text-gray-400">Sem avaliação</p>
            @endif
            @if($snapshot && $snapshot->score_total_original && $snapshot->score_total_original != $snapshot->score_total)
                <p class="mt-1 text-[10px] text-red-500" title="Guardrail ativo: score penalizado">⚠️ Guardrail ativo</p>
            @endif
        </div>
    </div>

    {{-- DETALHAMENTO POR EIXO --}}
    @foreach($eixos as $eixo)
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100" style="background: linear-gradient(135deg, {{ $eixo->codigo === 'JURIDICO' ? '#eff6ff' : ($eixo->codigo === 'FINANCEIRO' ? '#f0fdf4' : ($eixo->codigo === 'DESENVOLVIMENTO' ? '#faf5ff' : '#fff7ed')) }} 0%, white 100%);">
            <h3 class="text-sm font-semibold text-gray-800">
                {{ $eixo->codigo === 'JURIDICO' ? '⚖️' : ($eixo->codigo === 'FINANCEIRO' ? '💰' : ($eixo->codigo === 'DESENVOLVIMENTO' ? '📚' : '💬')) }}
                {{ $eixo->nome }}
                <span class="text-xs font-normal text-gray-500">(peso {{ number_format($eixo->peso, 0) }}%)</span>
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500">Indicador</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Meta</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Apurado</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Atingimento</th>
                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500">Score</th>
                        <th class="px-4 py-2 text-center text-xs font-medium text-gray-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                @foreach($eixo->indicadores as $ind)
                    @php
                        $res = $resultados->get($ind->codigo);
                        $meta = $metas->get($ind->id);
                        $metaVal = $meta->valor_meta ?? null;
                        $apurado = $res ? $res->valor_efetivo : null;
                        $pctAtingimento = $res ? $res->percentual_atingimento : null;
                        $scorePond = $res ? $res->score_ponderado : null;

                        // Status visual
                        $statusClass = 'bg-gray-100 text-gray-500';
                        $statusLabel = '—';
                        if ($pctAtingimento !== null) {
                            if ($pctAtingimento >= 100) {
                                $statusClass = 'bg-green-100 text-green-700';
                                $statusLabel = '✅ Atingido';
                            } elseif ($pctAtingimento >= 70) {
                                $statusClass = 'bg-yellow-100 text-yellow-700';
                                $statusLabel = '⚠️ Parcial';
                            } else {
                                $statusClass = 'bg-red-100 text-red-700';
                                $statusLabel = '❌ Abaixo';
                            }
                        } elseif ($apurado === null) {
                            $statusLabel = 'Sem dados';
                        } elseif ($metaVal === null) {
                            $statusLabel = 'Sem meta';
                        }
                    @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5">
                            <span class="font-mono text-xs text-gray-400 mr-1">{{ $ind->codigo }}</span>
                            <span class="text-gray-800">{{ $ind->nome }}</span>
                            @if($ind->status_v1 !== 'score')
                                <span class="ml-1 text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500">{{ $ind->status_v1 }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right text-gray-600 font-medium">
                            @if($metaVal !== null)
                                {{ $ind->unidade === 'reais' ? 'R$ ' . number_format($metaVal, 2, ',', '.') : number_format($metaVal, ($ind->unidade === 'percentual' ? 1 : 0), ',', '.') . ($ind->unidade === 'percentual' ? '%' : '') }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right font-semibold text-gray-800">
                            @if($apurado !== null)
                                {{ $ind->unidade === 'reais' ? 'R$ ' . number_format($apurado, 2, ',', '.') : number_format($apurado, ($ind->unidade === 'percentual' ? 1 : ($ind->unidade === 'horas' ? 1 : 0)), ',', '.') . ($ind->unidade === 'percentual' ? '%' : ($ind->unidade === 'horas' ? 'h' : '')) }}
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            @if($pctAtingimento !== null)
                                <div class="flex items-center justify-end gap-2">
                                    <div class="w-16 h-1.5 rounded-full bg-gray-200 overflow-hidden">
                                        <div class="h-full rounded-full {{ $pctAtingimento >= 100 ? 'bg-green-500' : ($pctAtingimento >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}" style="width:{{ min($pctAtingimento, 120) / 1.2 }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium {{ $pctAtingimento >= 100 ? 'text-green-600' : ($pctAtingimento >= 70 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ number_format($pctAtingimento, 1, ',', '.') }}%
                                    </span>
                                </div>
                            @else
                                <span class="text-gray-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 text-right text-xs font-mono text-gray-500">
                            {{ $scorePond !== null ? number_format($scorePond, 2, ',', '.') : '—' }}
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex text-[10px] font-medium px-2 py-0.5 rounded-full {{ $statusClass }}">{{ $statusLabel }}</span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach

    {{-- GRÁFICO HISTÓRICO --}}
    {{-- PENALIZACOES DO MES --}}
    @php
        $pensMes = \App\Models\Gdp\GdpPenalizacao::with('tipo')
            ->where('user_id', $targetUser->id)
            ->where('mes', $mes)->where('ano', $ano)
            ->orderByDesc('created_at')->get();
    @endphp
    @if($pensMes->isNotEmpty())
    <div id="penalizacoes-section" class="rounded-xl border border-red-200 bg-white p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-sm font-semibold text-red-700">⚠️ Conformidade do Mês ({{ $pensMes->count() }})</h3>
            <span class="text-xs font-bold text-red-600">-{{ $pensMes->where('contestacao_status', '!=', 'aceita')->sum('pontos_desconto') }} pts</span>
        </div>
        <div class="space-y-2">
            @foreach($pensMes as $pen)
            <div class="flex items-center justify-between rounded-lg border {{ $pen->contestacao_status === 'aceita' ? 'border-green-200 bg-green-50 opacity-60' : 'border-red-100 bg-red-50' }} px-4 py-2.5">
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xs text-gray-500">{{ $pen->tipo->codigo ?? 'MAN' }}</span>
                        <span class="text-sm text-gray-800">{{ $pen->tipo->nome ?? 'Manual' }}</span>
                        @php $grav = $pen->tipo->gravidade ?? 'leve'; @endphp
                        <span class="inline-flex rounded-full px-1.5 py-0.5 text-[10px] font-medium {{ $grav === 'grave' ? 'bg-red-100 text-red-700' : ($grav === 'moderada' ? 'bg-amber-100 text-amber-700' : 'bg-yellow-100 text-yellow-700') }}">{{ ucfirst($grav) }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">{{ Str::limit($pen->descricao_automatica, 100) }}</p>
                </div>
                <div class="flex items-center gap-3 ml-4">
                    <span class="font-bold text-red-600 text-sm">-{{ $pen->pontos_desconto }}</span>
                    @if($pen->contestacao_status === 'pendente')
                        <span class="text-xs text-amber-600">Contestada</span>
                    @elseif($pen->contestacao_status === 'aceita')
                        <span class="text-xs text-green-600">Aceita</span>
                    @elseif($pen->contestacao_status === 'rejeitada')
                        <span class="text-xs text-red-600">Rejeitada</span>
                        <button onclick="contestar({{ $pen->id }})" class="rounded px-2 py-1 text-xs font-medium text-white" style="background-color:#385776">Contestar</button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

        @if($historico->isNotEmpty())
    <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
        <h3 class="text-sm font-semibold text-gray-800 mb-4">📊 Evolução do Score Total</h3>
        <canvas id="gdpHistoricoChart" height="80"></canvas>
    </div>
    @endif

    @endif {{-- end ciclo --}}
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
@if($user->isAdmin())
function apurarMes() {
    const btn = document.getElementById('btn-apurar');
    btn.disabled = true;
    btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Apurando...';
    fetch('{{ route("gdp.apurar") }}', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        credentials: 'same-origin',
        body: JSON.stringify({mes: {{ $mes }}, ano: {{ $ano }}})
    })
    .then(r => r.json())
    .then(data => {
        if (data.erro) {
            alert('Erro: ' + data.erro);
        } else {
            alert('Apuração concluída!\n\nUsuários: ' + data.usuarios + '\nResultados: ' + data.resultados + '\nSnapshots: ' + data.snapshots + '\nErros: ' + data.erros);
            location.reload();
        }
    })
    .catch(e => alert('Erro: ' + e.message))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg> Apurar Mês';
    });
}
@endif

@php
    $histLabels = isset($historico) && $historico->isNotEmpty()
        ? $historico->map(fn($s) => str_pad($s->mes, 2, '0', STR_PAD_LEFT) . '/' . $s->ano)->values()
        : collect([]);
    $histShow = isset($historico) && $historico->isNotEmpty();
    $histScoreTotal = $histShow ? $historico->pluck('score_total') : collect([]);
    $histScoreJur = $histShow ? $historico->pluck('score_juridico') : collect([]);
    $histScoreFin = $histShow ? $historico->pluck('score_financeiro') : collect([]);
    $histScoreDev = $histShow ? $historico->pluck('score_desenvolvimento') : collect([]);
    $histScoreAte = $histShow ? $historico->pluck('score_atendimento') : collect([]);
@endphp
@if($histShow)
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('gdpHistoricoChart');
    if (!ctx) return;
    const labels = @json($histLabels);
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                { label: 'Score Total', data: @json($histScoreTotal), borderColor: '#385776', backgroundColor: 'rgba(56,87,118,0.1)', fill: true, tension: 0.3, borderWidth: 2.5, pointRadius: 4 },
                { label: 'Jurídico', data: @json($histScoreJur), borderColor: '#2563eb', borderWidth: 1.5, pointRadius: 2, borderDash: [4,4] },
                { label: 'Financeiro', data: @json($histScoreFin), borderColor: '#16a34a', borderWidth: 1.5, pointRadius: 2, borderDash: [4,4] },
                { label: 'Desenvolvimento', data: @json($histScoreDev), borderColor: '#9333ea', borderWidth: 1.5, pointRadius: 2, borderDash: [4,4] },
                { label: 'Atendimento', data: @json($histScoreAte), borderColor: '#ea580c', borderWidth: 1.5, pointRadius: 2, borderDash: [4,4] },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 16 } } },
            scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.04)' } }, x: { grid: { display: false } } }
        }
    });
});
@endif

function contestar(penId) {
    const texto = prompt('Descreva sua justificativa para contestar esta ocorrência:');
    fetch('/gdp/penalizacoes/' + penId + '/contestar', {
        method: 'POST',
        headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
        credentials: 'same-origin',
        body: JSON.stringify({texto: texto})
    })
    .then(r => r.json())
    .then(data => {
        if (data.erro) alert('Erro: ' + data.erro);
        else { alert('Contestação registrada. Aguarde avaliação.'); location.reload(); }
    })
    .catch(e => alert('Erro: ' + e.message));
}
</script>
@endpush
@endsection
