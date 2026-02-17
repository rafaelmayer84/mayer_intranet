@extends('layouts.app')
@section('title', 'GDP — Acordo de Desempenho')
@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="flex items-center gap-2 text-xl font-bold text-gray-900">
                <span class="text-lg">📋</span> Acordo de Desempenho
            </h1>
            <p class="mt-1 text-xs text-gray-500">
                Ciclo: {{ $ciclo->nome }} | {{ $ciclo->data_inicio->format('d/m/Y') }} a {{ $ciclo->data_fim->format('d/m/Y') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('gdp.acordo') }}" class="flex items-center gap-2">
                <select name="user_id" onchange="this.form.submit()" class="rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-sm shadow-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">— Selecione o profissional —</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" {{ ($targetUser && $targetUser->id === $u->id) ? 'selected' : '' }}>
                            {{ $u->name }} {{ $u->cargo ? '('.$u->cargo.')' : '('.$u->role.')' }}
                        </option>
                    @endforeach
                </select>
            </form>

            @if($targetUser)
                <a href="{{ route('gdp.acordo.visualizar', $targetUser->id) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm transition" style="background-color:#385776;">
                    👁️ Visualizar como Advogado
                </a>
                <a href="{{ route('gdp.acordo.print', $targetUser->id) }}" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium text-white shadow-sm transition" style="background-color:#1B334A;">
                    🖨️ Imprimir Acordo
                </a>
            @endif
        </div>
    </div>

    @if(!$targetUser)
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-6 text-center">
        <p class="text-sm text-blue-800">Selecione um profissional acima para configurar o acordo de desempenho.</p>
    </div>
    @else

    {{-- STATUS DO ACORDO --}}
    @if($acordoAceito)
    <div class="rounded-lg border border-green-200 bg-green-50 p-4 flex items-center gap-3">
        <span class="text-2xl">✅</span>
        <div>
            <p class="text-sm font-semibold text-green-800">Acordo aceito por {{ $targetUser->name }}</p>
            <p class="text-xs text-green-600">As metas estão travadas. Para alterar, é necessário descongelar o ciclo.</p>
        </div>
    </div>
    @endif

    {{-- GRID DE METAS --}}
    <form id="form-acordo">
        <input type="hidden" name="user_id" value="{{ $targetUser->id }}">

        @foreach($eixos as $eixo)
        @if($eixo->indicadores->isNotEmpty())
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden mb-4">
            <div class="px-5 py-3 border-b border-gray-100" style="background: linear-gradient(135deg, {{ $eixo->codigo === 'JURIDICO' ? '#eff6ff' : ($eixo->codigo === 'FINANCEIRO' ? '#f0fdf4' : ($eixo->codigo === 'DESENVOLVIMENTO' ? '#faf5ff' : '#fff7ed')) }} 0%, white 100%);">
                <h3 class="text-sm font-semibold text-gray-800">
                    @if($eixo->codigo === 'JURIDICO') ⚖️
                    @elseif($eixo->codigo === 'FINANCEIRO') 💰
                    @elseif($eixo->codigo === 'DESENVOLVIMENTO') 📚
                    @else 💬
                    @endif
                    {{ $eixo->nome }}
                    <span class="text-xs font-normal text-gray-500">(peso {{ number_format($eixo->peso, 0) }}%)</span>
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 w-64">Indicador</th>
                            <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 w-16">Peso</th>
                            <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 w-16">Un.</th>
                            @for($m = $mesInicio; $m <= $mesFim; $m++)
                                <th class="px-2 py-2 text-center text-xs font-medium text-gray-500 w-24">
                                    {{ \Carbon\Carbon::create(null, $m)->translatedFormat('M') }}
                                </th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach($eixo->indicadores as $ind)
                        <tr class="hover:bg-gray-50/50">
                            <td class="px-4 py-2.5">
                                <span class="font-mono text-xs text-gray-400 mr-1">{{ $ind->codigo }}</span>
                                <span class="text-gray-800 text-xs">{{ $ind->nome }}</span>
                            </td>
                            <td class="px-2 py-2.5 text-center text-xs text-gray-500">{{ number_format($ind->peso, 0) }}%</td>
                            <td class="px-2 py-2.5 text-center text-xs text-gray-400">{{ $ind->unidade }}</td>
                            @for($m = $mesInicio; $m <= $mesFim; $m++)
                                @php
                                    $key = $ind->id . '_' . $m;
                                    $existing = $metas->get($key);
                                    $val = $existing ? $existing->valor_meta : '';
                                    $showVal = '';
                                    if ($val !== '' && $val !== null && (float)$val > 0) {
                                        if ($ind->unidade === 'reais') {
                                            $showVal = number_format((float)$val, 2, ',', '.');
                                        } elseif ($ind->unidade === 'percentual') {
                                            $showVal = number_format((float)$val, 1, ',', '.');
                                        } else {
                                            $showVal = number_format((float)$val, 0, ',', '.');
                                        }
                                    }
                                @endphp
                                <td class="px-1 py-1.5 text-center">
                                    <input type="text"
                                        name="metas[{{ $ind->id }}][{{ $m }}]"
                                        value="{{ $showVal }}"
                                        placeholder="—"
                                        {{ $acordoAceito ? 'disabled' : '' }}
                                        class="w-full rounded border border-gray-200 px-2 py-1 text-xs text-center focus:ring-2 focus:ring-blue-400 focus:border-blue-400 {{ $acordoAceito ? 'bg-gray-100 cursor-not-allowed' : 'bg-white' }}"
                                    >
                                </td>
                            @endfor
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
        @endforeach

        {{-- BOTÕES --}}
        @if(!$acordoAceito)
        <div class="flex justify-end gap-3">
            <button type="button" onclick="salvarAcordo()" id="btn-salvar"
                class="inline-flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition" style="background-color:#385776;">
                💾 Salvar Acordo
            </button>
        </div>
        @endif
    </form>

    @endif
</div>

@push('scripts')
<script>
function salvarAcordo() {
    var btn = document.getElementById('btn-salvar');
    var form = document.getElementById('form-acordo');
    var formData = new FormData(form);

    var data = { user_id: formData.get('user_id'), metas: {} };
    for (var pair of formData.entries()) {
        var match = pair[0].match(/metas\[(\d+)\]\[(\d+)\]/);
        if (match && pair[1].trim() !== '') {
            var indId = match[1];
            var mes = match[2];
            if (!data.metas[indId]) data.metas[indId] = {};
            data.metas[indId][mes] = pair[1].trim();
        }
    }

    btn.disabled = true;
    btn.innerHTML = '⏳ Salvando...';

    fetch('{{ route("gdp.acordo.salvar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        credentials: 'same-origin',
        body: JSON.stringify(data)
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (resp.erro) {
            alert('Erro: ' + resp.erro);
        } else {
            alert('Acordo salvo com sucesso! Metas salvas: ' + resp.metas_salvas);
        }
    })
    .catch(function(e) { alert('Erro: ' + e.message); })
    .finally(function() {
        btn.disabled = false;
        btn.innerHTML = '💾 Salvar Acordo';
    });
}
</script>
@endpush
@endsection
