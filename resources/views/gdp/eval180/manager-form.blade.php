@extends('layouts.app')

@section('title', 'Avaliar ' . $avaliado->name . ' — ' . $period)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Avaliar: {{ $avaliado->name }}</h1>
            <p class="text-sm text-gray-500">
                Ciclo {{ $ciclo->nome }} — {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F/Y') }}
                — Cargo: <span class="capitalize">{{ $avaliado->role }}</span>
            </p>
        </div>
        <a href="{{ route('gdp.eval180.cycle', $ciclo->id) }}" class="text-sm text-blue-600 hover:underline">← Voltar</a>
    </div>

    @if($isLocked)
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
            <p class="text-red-700 font-medium">🔒 Avaliação travada — somente leitura.</p>
        </div>
    @endif

    @if(!$selfSubmitted)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
            <p class="text-yellow-700">⚠️ O profissional ainda não enviou a autoavaliação.</p>
        </div>
    @endif

    @if($isSubmitted)
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
            <p class="text-green-700 font-medium">✅ Avaliação do gestor enviada.</p>
        </div>
    @endif

    {{-- Formulário --}}
    <form id="evalForm" class="space-y-6">
        @csrf
        <input type="hidden" name="action" id="formAction" value="draft">

        @php $currentSection = 0; @endphp
        @foreach($questions as $idx => $q)
            @if($q['section'] !== $currentSection)
                @php $currentSection = $q['section']; @endphp
                <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
                    <div class="bg-gradient-to-r from-[#385776] to-[#1B334A] px-5 py-3 flex justify-between items-center">
                        <h2 class="text-white font-semibold">
                            {{ $currentSection }}. {{ $sectionNames[$currentSection] ?? '' }}
                        </h2>
                        <span class="text-white/70 text-xs">Peso: {{ $sectionWeights[$currentSection] ?? 25 }}%</span>
                    </div>
                    <div class="p-5 space-y-3">
                        {{-- Cabeçalho colunas --}}
                        <div class="flex items-center gap-4 pb-2 border-b border-gray-200 text-xs text-gray-500 font-medium">
                            <div class="flex-1">Pergunta</div>
                            <div class="w-[120px] text-center">Auto</div>
                            <div class="w-[200px] text-center">Gestor (1-5)</div>
                        </div>
            @endif

            <div class="flex items-start gap-4 py-2 border-b border-gray-50">
                {{-- Pergunta --}}
                <div class="flex-1">
                    <span class="text-sm text-gray-700">
                        <strong>{{ $q['number'] }}.</strong> {{ $q['text'] }}
                    </span>
                </div>
                {{-- Nota auto --}}
                <div class="w-[120px] text-center">
                    @if($selfSubmitted && isset($selfAnswers[$q['number']]))
                        @php $selfVal = (int)$selfAnswers[$q['number']]; @endphp
                        <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-sm font-bold
                            {{ $selfVal >= 4 ? 'bg-green-100 text-green-700' : ($selfVal <= 2 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-700') }}">
                            {{ $selfVal }}
                        </span>
                    @else
                        <span class="text-gray-400 text-xs">—</span>
                    @endif
                </div>
                {{-- Nota gestor --}}
                <div class="w-[200px] flex justify-center gap-1">
                    @for($n = 1; $n <= 5; $n++)
                        <label class="cursor-pointer">
                            <input type="radio"
                                   name="answers[{{ $q['number'] }}]"
                                   value="{{ $n }}"
                                   class="sr-only peer"
                                   {{ ($managerAnswers[$q['number']] ?? null) == $n ? 'checked' : '' }}
                                   {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>
                            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg border-2 text-xs font-bold transition-all
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
                    {{-- Médias comparativas --}}
                    <div class="px-5 py-3 bg-gray-50 border-t text-sm flex justify-between">
                        <span>
                            Média gestor seção {{ $currentSection }}:
                            <strong id="mgr-avg-{{ $currentSection }}" class="text-[#385776]">—</strong>
                        </span>
                        @if($selfSubmitted && isset($selfScores[$currentSection]))
                            <span class="text-gray-500">
                                Auto: <strong>{{ number_format($selfScores[$currentSection], 2, ',', '.') }}</strong>
                                @php
                                    $diff = ($managerScores[$currentSection] ?? 0) - $selfScores[$currentSection];
                                @endphp
                            </span>
                        @endif
                    </div>
                </div>
            @endif
        @endforeach

        {{-- Comentário obrigatório --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                📌 Comentários e Observações <span class="text-red-500">*</span>
            </label>
            <textarea name="comment_text" rows="4" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                      placeholder="Feedback detalhado para o profissional..."
                      {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>{{ $managerComment }}</textarea>
        </div>

        {{-- Evidência --}}
        <div id="evidenceBlock" class="bg-white rounded-xl shadow-sm border p-5 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                ⚠️ Justificativa obrigatória (há notas ≤{{ $config['evidencia_trigger_min'] ?? 2 }} ou ≥{{ $config['evidencia_trigger_max'] ?? 5 }})
            </label>
            <textarea name="evidence_text" rows="3" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                      {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>{{ $managerEvidence }}</textarea>
        </div>

        {{-- Score total comparativo --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <div class="grid grid-cols-2 gap-6 text-center">
                <div>
                    <p class="text-sm text-gray-500">Nota do Gestor</p>
                    <p id="mgrTotalScore" class="text-3xl font-bold text-[#385776] mt-1">—</p>
                </div>
                @if($selfSubmitted && $selfTotal)
                    <div>
                        <p class="text-sm text-gray-500">Autoavaliação</p>
                        <p class="text-3xl font-bold text-gray-400 mt-1">{{ number_format($selfTotal, 2, ',', '.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Plano de ação --}}
        <div id="actionPlanBlock" class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="font-semibold text-gray-800 mb-3">📋 Plano de Ação</h3>
            <p class="text-xs text-gray-500 mb-3" id="actionRequired" style="display:none;">
                ⚠️ Obrigatório quando nota total &lt; {{ $config['action_required_threshold'] ?? 3.0 }}
            </p>
            <div id="actionItems" class="space-y-3">
                @for($i = 0; $i < 3; $i++)
                    @php $item = $actionItems[$i] ?? null; @endphp
                    <div class="grid grid-cols-12 gap-2 items-start">
                        <div class="col-span-5">
                            <input type="text" name="action_items[{{ $i }}][title]"
                                   value="{{ $item->title ?? '' }}"
                                   placeholder="Ação {{ $i+1 }}"
                                   class="w-full border rounded px-2 py-1.5 text-sm"
                                   {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>
                        </div>
                        <div class="col-span-3">
                            <input type="date" name="action_items[{{ $i }}][due_date]"
                                   value="{{ $item ? $item->due_date->format('Y-m-d') : '' }}"
                                   class="w-full border rounded px-2 py-1.5 text-sm"
                                   {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>
                        </div>
                        <div class="col-span-4">
                            <input type="text" name="action_items[{{ $i }}][notes]"
                                   value="{{ $item->notes ?? '' }}"
                                   placeholder="Observação"
                                   class="w-full border rounded px-2 py-1.5 text-sm"
                                   {{ ($isLocked || $isSubmitted) ? 'disabled' : '' }}>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Botões --}}
        @if(!$isLocked && !$isSubmitted)
            <div class="flex gap-3 justify-end">
                <button type="button" onclick="saveForm('draft')" class="px-6 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                    Salvar rascunho
                </button>
                <button type="button" onclick="saveForm('submit')" class="btn-mayer px-6 py-2 rounded-lg text-sm text-white">
                    Enviar avaliação
                </button>
            </div>
        @endif

        @if(Auth::user()->role === 'admin')
            <div class="flex justify-end mt-2 gap-2">
                @if($form->status === 'pending_feedback')
                    <button type="button" onclick="liberarFeedback()" class="px-4 py-1.5 text-xs text-green-600 border border-green-300 rounded hover:bg-green-50 transition">
                        ✅ Liberar Feedback
                    </button>
                @endif
                @if(in_array($form->status, ['released']))
                    <button type="button" onclick="lockForm()" class="px-4 py-1.5 text-xs text-red-600 border border-red-300 rounded hover:bg-red-50 transition">
                        🔒 Travar avaliação
                    </button>
                @endif
            </div>
        @endif
    </form>
</div>
@endsection

@push('scripts')
<script>
const evMin = {{ $config['evidencia_trigger_min'] ?? 2 }};
const evMax = {{ $config['evidencia_trigger_max'] ?? 5 }};
const actionThreshold = {{ $config['action_required_threshold'] ?? 3.0 }};

document.addEventListener('DOMContentLoaded', function() {
    recalculate();
    document.querySelectorAll('input[type="radio"]').forEach(r => r.addEventListener('change', recalculate));
});

function recalculate() {
    let needsEvidence = false;
    let sectionAvgs = {};

    for (let s = 1; s <= 4; s++) {
        let vals = [];
        for (let q = 1; q <= 5; q++) {
            const checked = document.querySelector(`input[name="answers[${s}.${q}]"]:checked`);
            if (checked) {
                const v = parseInt(checked.value);
                vals.push(v);
                if (v <= evMin || v >= evMax) needsEvidence = true;
            }
        }
        const avg = vals.length > 0 ? vals.reduce((a, b) => a + b, 0) / vals.length : 0;
        sectionAvgs[s] = avg;
        const el = document.getElementById('mgr-avg-' + s);
        if (el) el.textContent = avg > 0 ? avg.toFixed(2) : '—';
    }

    const avgs = Object.values(sectionAvgs).filter(v => v > 0);
    const total = avgs.length > 0 ? avgs.reduce((a, b) => a + b, 0) / avgs.length : 0;
    document.getElementById('mgrTotalScore').textContent = total > 0 ? total.toFixed(2) : '—';

    document.getElementById('evidenceBlock').classList.toggle('hidden', !needsEvidence);

    const ar = document.getElementById('actionRequired');
    if (ar) ar.style.display = total > 0 && total < actionThreshold ? 'block' : 'none';
}

function saveForm(action) {
    document.getElementById('formAction').value = action;

    if (action === 'submit' && !confirm('Confirma o envio da avaliação? Esta ação é definitiva.')) return;

    const formData = new FormData(document.getElementById('evalForm'));
    const data = { answers: {}, action_items: [], action: action };

    for (const [key, value] of formData.entries()) {
        const ansMatch = key.match(/answers\[(.+)\]/);
        if (ansMatch) {
            data.answers[ansMatch[1]] = parseInt(value);
        } else if (key === 'comment_text' || key === 'evidence_text') {
            data[key] = value;
        }
    }

    // Collect action items
    for (let i = 0; i < 3; i++) {
        const title = formData.get(`action_items[${i}][title]`) || '';
        const due = formData.get(`action_items[${i}][due_date]`) || '';
        const notes = formData.get(`action_items[${i}][notes]`) || '';
        if (title.trim()) {
            data.action_items.push({ title, due_date: due, notes });
        }
    }

    fetch('{{ route("gdp.eval180.manager.save", [$ciclo->id, $avaliado->id, $period]) }}', {
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
    .catch(err => alert('Erro: ' + err.message));
}

function lockForm() {
    if (!confirm('Travar esta avaliação? O formulário ficará em modo somente leitura.')) return;

    fetch('{{ route("gdp.eval180.lock", [$ciclo->id, $avaliado->id, $period]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) location.reload();
        else alert(res.message || 'Erro ao travar.');
    })
    .catch(err => alert('Erro: ' + err.message));
}

function liberarFeedback() {
    if (!confirm('Liberar resultado para o profissional ver? Será notificado por email.')) return;

    fetch('{{ route("gdp.eval180.release-feedback", [$ciclo->id, $avaliado->id, $period]) }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        body: '{}'
    })
    .then(r => r.json())
    .then(res => {
        alert(res.message);
        if (res.success) location.reload();
    })
    .catch(err => alert('Erro: ' + err.message));
}
</script>
@endpush
