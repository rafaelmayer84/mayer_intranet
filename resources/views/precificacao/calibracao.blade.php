@extends('layouts.app')

@section('content')
<div class="w-full px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('precificacao.index') }}" class="text-indigo-600 hover:text-indigo-800 text-sm">← Voltar</a>
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Calibração Estratégica</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Parâmetros que orientam o raciocínio da IA na formação de preços</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-xl border border-amber-200 dark:border-amber-800">
            <p class="text-sm text-amber-800 dark:text-amber-200">
                Estes controles definem a orientação estratégica do escritório. A IA utiliza esses valores como referência para ponderar suas recomendações. Alterações se aplicam a todas as futuras propostas.
            </p>
        </div>

        {{-- Seletor de Modelo de IA --}}
        <div class="mb-8 p-5 bg-indigo-50 dark:bg-indigo-900/20 rounded-xl border border-indigo-200 dark:border-indigo-700">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <h3 class="text-sm font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                        <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        Modelo de IA
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Modelo utilizado para gerar as propostas de precificacao</p>
                </div>
                <span class="text-xs px-2 py-1 rounded-full {{ $modeloAtual && str_starts_with($modeloAtual, 'claude-') ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-300' : 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' }}">
                    {{ $modeloAtual && str_starts_with($modeloAtual, 'claude-') ? 'Anthropic' : 'OpenAI' }}
                </span>
            </div>
            <select id="modelo-ia" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:ring-indigo-500 focus:border-indigo-500">
                @foreach($modelosDisponiveis as $value => $label)
                    <option value="{{ $value }}" {{ $modeloAtual === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <form id="form-calibracao">
            <div class="space-y-8">
                @foreach($eixos as $eixo)
                <div class="border-b border-gray-100 dark:border-gray-700 pb-6 last:border-0 last:pb-0">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-800 dark:text-white">{{ $eixo->label }}</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $eixo->descricao }}</p>
                        </div>
                        <span class="text-lg font-bold text-indigo-600 dark:text-indigo-400 min-w-[3rem] text-right" id="val-{{ $eixo->eixo }}">{{ $eixo->valor }}</span>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="text-xs text-gray-400 min-w-[80px]">{{ $eixo->label_min }}</span>
                        <input type="range" name="eixos[{{ $eixo->eixo }}]" min="0" max="100" value="{{ $eixo->valor }}"
                            class="w-full h-2 bg-gray-200 dark:bg-gray-600 rounded-lg appearance-none cursor-pointer accent-indigo-600"
                            oninput="document.getElementById('val-{{ $eixo->eixo }}').textContent = this.value">
                        <span class="text-xs text-gray-400 min-w-[80px] text-right">{{ $eixo->label_max }}</span>
                    </div>
                </div>
                @endforeach
            </div>

            <div class="mt-8 flex justify-end gap-3">
                <button type="button" onclick="resetarPadroes()" class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-lg hover:bg-gray-200 transition">
                    Resetar Padrões
                </button>
                <button type="submit" class="btn-mayer font-semibold">
                    Salvar Calibração
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('form-calibracao').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const eixos = {};
    for (const [key, value] of formData.entries()) {
        const match = key.match(/eixos\[(.+)\]/);
        if (match) eixos[match[1]] = parseInt(value);
    }

    const modeloIa = document.getElementById('modelo-ia').value;

    fetch('{{ route("precificacao.calibracao.salvar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify({ eixos, modelo_ia: modeloIa }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert('Calibração salva com sucesso!');
        } else {
            alert('Erro ao salvar: ' + (data.message || 'Tente novamente'));
        }
    })
    .catch(() => alert('Erro de conexão'));
});

function resetarPadroes() {
    if (!confirm('Resetar todos os eixos para o valor padrão (50)?')) return;
    document.querySelectorAll('input[type="range"]').forEach(slider => {
        slider.value = 50;
        const eixo = slider.name.match(/eixos\[(.+)\]/)[1];
        document.getElementById('val-' + eixo).textContent = '50';
    });
}
</script>
@endpush
@endsection
