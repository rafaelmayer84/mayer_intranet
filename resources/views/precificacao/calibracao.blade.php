@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
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

    fetch('{{ route("precificacao.calibracao.salvar") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        body: JSON.stringify({ eixos }),
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
