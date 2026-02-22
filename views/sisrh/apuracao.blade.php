@extends('layouts.app')

@section('title', 'SISRH — Apuração de Competência')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold" style="color: #1B334A;">Apuração de Competência</h1>
        <a href="{{ route('sisrh.index') }}" class="text-sm underline" style="color: #385776;">← Voltar</a>
    </div>

    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <p class="text-sm text-gray-600 mb-4">
            Ciclo: <strong>{{ $ciclo->nome ?? 'N/D' }}</strong> |
            Usuários elegíveis: <strong>{{ $users->count() }}</strong>
        </p>

        <div class="flex items-end gap-4">
            <div>
                <label class="block text-xs text-gray-500 mb-1">Ano</label>
                <input type="number" id="apuracao-ano" value="{{ date('Y') }}" min="2024" max="2030" class="border rounded px-3 py-2 text-sm w-24">
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Mês</label>
                <select id="apuracao-mes" class="border rounded px-3 py-2 text-sm">
                    @for($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" {{ $m == date('n') ? 'selected' : '' }}>
                            {{ str_pad($m, 2, '0', STR_PAD_LEFT) }}
                        </option>
                    @endfor
                </select>
            </div>
            <button onclick="sisrhSimular()" id="btn-simular" class="px-4 py-2 rounded text-white text-sm" style="background-color: #385776;">
                Simular
            </button>
            <button onclick="sisrhFechar()" id="btn-fechar" class="px-4 py-2 rounded text-white text-sm" style="background-color: #1B334A;" disabled>
                Fechar Competência
            </button>
        </div>
    </div>

    <div id="resultado-container" class="hidden">
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <table class="min-w-full text-sm">
                <thead style="background-color: #385776;">
                    <tr>
                        <th class="px-3 py-2 text-left text-white">Advogado</th>
                        <th class="px-3 py-2 text-right text-white">RB</th>
                        <th class="px-3 py-2 text-right text-white">Captação</th>
                        <th class="px-3 py-2 text-center text-white">Score GDP</th>
                        <th class="px-3 py-2 text-center text-white">Faixa %</th>
                        <th class="px-3 py-2 text-right text-white">RV Bruta</th>
                        <th class="px-3 py-2 text-center text-white">Redução</th>
                        <th class="px-3 py-2 text-right text-white">RV Aplicada</th>
                        <th class="px-3 py-2 text-right text-white">Excedente</th>
                        <th class="px-3 py-2 text-center text-white">Status</th>
                    </tr>
                </thead>
                <tbody id="resultado-tbody"></tbody>
            </table>
        </div>
    </div>

    <div id="loading" class="hidden text-center py-8">
        <span class="text-gray-500">Calculando apuração...</span>
    </div>
</div>

@push('scripts')
<script>
const usersMap = @json($users->pluck('name', 'id'));

function fmt(v) {
    return parseFloat(v || 0).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

function renderResultados(resultados) {
    const tbody = document.getElementById('resultado-tbody');
    tbody.innerHTML = '';
    resultados.forEach(r => {
        const nome = usersMap[r.user_id] || ('User #' + r.user_id);
        const bloqueio = r.bloqueio_motivo || '';
        const statusHtml = bloqueio
            ? '<span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700">' + bloqueio + '</span>'
            : (r.erro ? '<span class="px-2 py-0.5 rounded text-xs bg-amber-100 text-amber-700">' + r.erro + '</span>'
            : '<span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">OK</span>');

        tbody.innerHTML += '<tr class="border-b border-gray-100 hover:bg-gray-50">'
            + '<td class="px-3 py-2">' + nome + '</td>'
            + '<td class="px-3 py-2 text-right">R$ ' + fmt(r.rb_valor) + '</td>'
            + '<td class="px-3 py-2 text-right">R$ ' + fmt(r.captacao_valor) + '</td>'
            + '<td class="px-3 py-2 text-center">' + fmt(r.gdp_score) + '%</td>'
            + '<td class="px-3 py-2 text-center">' + fmt(r.percentual_faixa) + '%</td>'
            + '<td class="px-3 py-2 text-right">R$ ' + fmt(r.rv_bruta) + '</td>'
            + '<td class="px-3 py-2 text-center text-red-600">' + (r.reducao_total_pct > 0 ? '-' + fmt(r.reducao_total_pct) + '%' : '-') + '</td>'
            + '<td class="px-3 py-2 text-right font-semibold" style="color:#385776;">R$ ' + fmt(r.rv_aplicada) + '</td>'
            + '<td class="px-3 py-2 text-right text-blue-600">' + (r.rv_excedente_credito > 0 ? 'R$ ' + fmt(r.rv_excedente_credito) : '-') + '</td>'
            + '<td class="px-3 py-2 text-center">' + statusHtml + '</td>'
            + '</tr>';
    });
    document.getElementById('resultado-container').classList.remove('hidden');
}

async function sisrhSimular() {
    const ano = document.getElementById('apuracao-ano').value;
    const mes = document.getElementById('apuracao-mes').value;
    document.getElementById('loading').classList.remove('hidden');
    document.getElementById('resultado-container').classList.add('hidden');
    document.getElementById('btn-fechar').disabled = true;

    try {
        const resp = await fetch('{{ route("sisrh.apuracao.simular") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ano: parseInt(ano), mes: parseInt(mes)})
        });
        const data = await resp.json();
        if (data.erro) { alert(data.erro); return; }
        renderResultados(data.resultados);
        document.getElementById('btn-fechar').disabled = false;
    } catch(e) { alert('Erro: ' + e.message); }
    finally { document.getElementById('loading').classList.add('hidden'); }
}

async function sisrhFechar() {
    if (!confirm('ATENÇÃO: Fechar a competência é IRREVERSÍVEL.\nOs valores serão congelados como snapshot auditável.\n\nConfirmar fechamento?')) return;
    const ano = document.getElementById('apuracao-ano').value;
    const mes = document.getElementById('apuracao-mes').value;
    document.getElementById('loading').classList.remove('hidden');

    try {
        const resp = await fetch('{{ route("sisrh.apuracao.fechar") }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
            body: JSON.stringify({ano: parseInt(ano), mes: parseInt(mes)})
        });
        const data = await resp.json();
        if (data.success) {
            alert('Competência fechada com sucesso!');
            renderResultados(data.resultados);
            document.getElementById('btn-fechar').disabled = true;
        } else {
            alert(data.erro || 'Erro ao fechar competência.');
        }
    } catch(e) { alert('Erro: ' + e.message); }
    finally { document.getElementById('loading').classList.add('hidden'); }
}
</script>
@endpush
@endsection
