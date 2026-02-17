@extends('layouts.app')

@section('title', 'Autoavaliação 180° — ' . $period)

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Autoavaliação 180°</h1>
            <p class="text-sm text-gray-500">
                Ciclo {{ $ciclo->nome }} — Período {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F/Y') }}
            </p>
        </div>
        <a href="{{ route('gdp.eval180.me') }}" class="text-sm text-blue-600 hover:underline">← Voltar</a>
    </div>

    @if($isLocked)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <p class="text-red-700 font-medium">🔒 Avaliação travada — somente leitura.</p>
        </div>
    @elseif($isSubmitted)
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
            <p class="text-green-700 font-medium">✅ Autoavaliação enviada em {{ $response->submitted_at->format('d/m/Y H:i') }}.</p>
        </div>
    @endif

    {{-- Formulário --}}
    <form id="evalForm" class="space-y-6">
        @csrf
        <input type="hidden" name="action" id="formAction" value="draft">

        @php $currentSection = 0; @endphp
        @foreach($questions as $q)
            @if($q['section'] !== $currentSection)
                @php $currentSection = $q['section']; @endphp
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    <div class="bg-gradient-to-r from-[#385776] to-[#1B334A] px-5 py-3">
                        <h2 class="text-white font-semibold">
                            {{ $currentSection }}. {{ $sectionNames[$currentSection] ?? '' }}
                        </h2>
                    </div>
                    <div class="p-5 space-y-4">
            @endif

            <div class="flex items-start gap-4 py-2 {{ !$loop->last && $questions[$loop->index + 1]['section'] === $currentSection ? 'border-b border-gray-100' : '' }}">
                <div class="flex-1">
                    <label class="text-sm text-gray-700 font-medium">{{ $q['number'] }}.</label>
                    <span class="text-sm text-gray-700">{{ $q['text'] }}</span>
                </div>
                <div class="flex gap-1 shrink-0">
                    @for($n = 1; $n <= 5; $n++)
                        <label class="cursor-pointer">
                            <input type="radio"
                                   name="answers[{{ $q['number'] }}]"
                                   value="{{ $n }}"
                                   class="sr-only peer"
                                   {{ ($answers[$q['number']] ?? null) == $n ? 'checked' : '' }}
                                   {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border-2 text-sm font-bold transition-all
                                peer-checked:bg-[#385776] peer-checked:text-white peer-checked:border-[#385776]
                                {{ ($isLocked || $isSubmitted) ? 'opacity-60' : 'hover:border-[#385776] hover:bg-blue-50' }}
                                border-gray-300 text-gray-600">
                                {{ $n }}
                            </span>
                        </label>
                    @endfor
                </div>
            </div>

            @if($loop->last || $questions[$loop->index + 1]['section'] !== $currentSection)
                    </div>
                    {{-- Média da seção (calculada em JS) --}}
                    <div class="px-5 py-3 bg-gray-50 border-t text-sm">
                        Média da seção {{ $currentSection }}:
                        <span id="avg-section-{{ $currentSection }}" class="font-bold text-[#385776]">—</span>
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Comentário --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">Comentários e observações (opcional)</label>
            <textarea name="comment_text" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                      {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>{{ $response->comment_text ?? '' }}</textarea>
        </div>

        {{-- Evidência --}}
        <div id="evidenceBlock" class="bg-white rounded-xl shadow-sm border p-5 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                ⚠️ Justificativa obrigatória (há notas ≤2 ou ≥5)
            </label>
            <textarea name="evidence_text" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                      {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>{{ $response->evidence_text ?? '' }}</textarea>
        </div>

        {{-- Score total --}}
        <div class="bg-white rounded-xl shadow-sm border p-5 text-center">
            <p class="text-sm text-gray-500">Pontuação Final</p>
            <p id="totalScore" class="text-3xl font-bold text-[#385776] mt-1">—</p>
        </div>

        {{-- Botões --}}
        @if(!$isLocked && !$isSubmitted)
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="saveForm('draft')" class="px-6 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                    Salvar rascunho
                </button>
                <button type="button" onclick="saveForm('submit')" class="btn-mayer px-6 py-2 rounded-lg text-sm text-white">
                    Enviar autoavaliação
                </button>
            </div>
        @endif
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    recalculate();
    document.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', recalculate));
});

function recalculate() {
    const sectionWeights = @json($sectionNames);
    let sectionAvgs = {};
    let needsEvidence = false;

    for (let s = 1; s <= 4; s++) {
        let vals = [];
        for (let q = 1; q <= 5; q++) {
            const checked = document.querySelector(`input[name="answers[${s}.${q}]"]:checked`);
            if (checked) {
                const v = parseInt(checked.value);
                vals.push(v);
                if (v <= {{ $response ? 2 : 2 }} || v >= 5) needsEvidence = true;
            }
        }
        const avg = vals.length > 0 ? (vals.reduce((a, b) => a + b, 0) / vals.length) : 0;
        sectionAvgs[s] = avg;
        const el = document.getElementById('avg-section-' + s);
        if (el) el.textContent = avg > 0 ? avg.toFixed(2) : '—';
    }

    // Total = média simples das 4 seções (pesos iguais default)
    const avgs = Object.values(sectionAvgs).filter(v => v > 0);
    const total = avgs.length > 0 ? avgs.reduce((a, b) => a + b, 0) / avgs.length : 0;
    document.getElementById('totalScore').textContent = total > 0 ? total.toFixed(2) : '—';

    // Evidência
    const evBlock = document.getElementById('evidenceBlock');
    if (evBlock) evBlock.classList.toggle('hidden', !needsEvidence);
}

function saveForm(action) {
    document.getElementById('formAction').value = action;

    if (action === 'submit' && !confirm('Confirma o envio da autoavaliação? Após enviar, não será possível editar.')) {
        return;
    }

    const formData = new FormData(document.getElementById('evalForm'));
    const data = {};
    data.answers = {};
    data.action = action;

    for (const [key, value] of formData.entries()) {
        const match = key.match(/answers\[(.+)\]/);
        if (match) {
            data.answers[match[1]] = parseInt(value);
        } else if (key === 'comment_text' || key === 'evidence_text') {
            data[key] = value;
        }
    }

    fetch('{{ route("gdp.eval180.me.save", [$ciclo->id, $period]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: JSON.stringify(data),
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message || 'Salvo com sucesso!');
            if (action === 'submit') location.reload();
        } else {
            alert('Erros:\n' + (res.errors || []).join('\n'));
        }
    })
    .catch(err => alert('Erro de conexão: ' + err.message));
}
</script>
@endpush
