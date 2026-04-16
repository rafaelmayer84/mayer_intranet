@extends('layouts.app')

@section('title', 'EVIDENTIA - Resultados')

@section('content')
<div class="w-full px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <a href="{{ route('evidentia.index') }}" class="text-sm hover:underline" style="color: #385776;">
                ← Nova busca
            </a>
            <h1 class="text-xl font-bold text-gray-800 mt-1">Resultados EVIDENTIA</h1>
        </div>
        <div class="text-right text-sm text-gray-500">
            <span>{{ $search->latency_ms }}ms</span>
            @if($search->cost_usd > 0)
                · <span>${{ number_format((float)$search->cost_usd, 4) }}</span>
            @endif
            @if($search->degraded_mode)
                <br><span class="text-amber-600 font-medium">⚠ Modo degradado (sem busca semântica)</span>
            @endif
        </div>
    </div>

    {{-- Mensagens --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Query original --}}
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6 border-l-4" style="border-left-color: #385776;">
        <p class="text-gray-700"><strong>Consulta:</strong> {{ $search->query }}</p>
        @if(!empty($search->filters_json))
            <div class="flex flex-wrap gap-2 mt-2">
                @foreach($search->filters_json as $key => $val)
                    @if($val)
                        <span class="inline-block bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">
                            {{ $key }}: {{ $val }}
                        </span>
                    @endif
                @endforeach
            </div>
        @endif
        @if(!empty($search->expanded_terms_json['termos']))
            <p class="text-xs text-gray-400 mt-2">
                Termos expandidos: {{ implode(', ', array_merge($search->expanded_terms_json['termos'] ?? [], $search->expanded_terms_json['expansoes'] ?? [])) }}
            </p>
        @endif
    </div>

    {{-- Botão gerar bloco --}}
    @if($resultsWithJuris->isNotEmpty())
        <div class="flex gap-3 mb-6">
            @if($search->citationBlock)
                <a href="#citation-block" class="px-4 py-2 text-sm rounded-lg text-white font-medium" style="background-color: #385776;">
                    Ver Bloco de Citação
                </a>
            @else
                <form action="{{ route('evidentia.gerar-bloco', $search->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm rounded-lg text-white font-medium transition-colors"
                        style="background-color: #385776;"
                        onmouseover="this.style.backgroundColor='#1B334A'"
                        onmouseout="this.style.backgroundColor='#385776'"
                        onclick="this.disabled=true; this.innerText='Gerando...'; this.form.submit();">
                        Gerar Bloco para Petição
                    </button>
                </form>
            @endif
        </div>
    @endif

    {{-- Resultados --}}
    @if($resultsWithJuris->isEmpty())
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-6 text-center">
            <p class="text-lg font-medium">NÃO LOCALIZADO NO ACERVO</p>
            <p class="text-sm mt-2">Nenhum resultado encontrado para a consulta. Tente reformular com termos diferentes ou ampliar os filtros.</p>
        </div>
    @else
        <div class="space-y-6">
            @foreach($resultsWithJuris as $result)
                @if($result->juris)
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden hover:shadow-md transition-shadow">
                    <div class="flex flex-col lg:flex-row">

                        {{-- Coluna principal --}}
                        <div class="flex-1 p-5">
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="inline-flex items-center justify-center w-8 h-8 text-xs font-bold text-white rounded-full" style="background-color: #385776;">
                                        #{{ $result->final_rank }}
                                    </span>
                                    <span class="inline-block text-xs font-bold px-2 py-1 rounded bg-blue-100 text-blue-800">
                                        {{ $result->tribunal }}
                                    </span>
                                    <span class="text-sm font-medium text-gray-700">
                                        {{ $result->juris->sigla_classe ?? '' }}
                                        {{ $result->juris->numero_processo ?? '' }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400 whitespace-nowrap ml-2">
                                    Score: {{ number_format($result->final_score, 3) }}
                                </span>
                            </div>

                            <div class="grid grid-cols-2 md:grid-cols-4 gap-2 mb-3 text-xs text-gray-600">
                                <div><strong>Relator:</strong> {{ $result->juris->relator ?? 'N/I' }}</div>
                                <div><strong>Órgão:</strong> {{ $result->juris->orgao_julgador ?? 'N/I' }}</div>
                                <div><strong>Data:</strong> {{ $result->juris->data_decisao ? \Carbon\Carbon::parse($result->juris->data_decisao)->format('d/m/Y') : 'N/I' }}</div>
                                <div><strong>Área:</strong> {{ $result->juris->area_direito ?? 'N/I' }}</div>
                            </div>

                            <div class="text-sm text-gray-700 leading-relaxed mb-3">
                                {{ Str::limit($result->juris->ementa ?? '', 500) }}
                            </div>

                            <div class="flex flex-wrap items-center gap-3 mt-3 pt-3 border-t border-gray-100">
                                <a href="{{ route('evidentia.juris.show', ['tribunal' => $result->tribunal, 'id' => $result->jurisprudence_id]) }}"
                                   class="text-xs font-medium hover:underline" style="color: #385776;">
                                    Ver documento completo →
                                </a>

                                @php
                                    $urlTribunal = null;
                                    $numProc = $result->juris->numero_processo ?? '';
                                    if ($result->tribunal === 'TJSC' && $numProc) {
                                        $urlTribunal = 'https://busca.tjsc.jus.br/jurisprudencia/buscaForm.do#resultado_ancora';
                                    } elseif ($result->tribunal === 'STJ' && $numProc) {
                                        $urlTribunal = 'https://scon.stj.jus.br/SCON/pesquisar.jsp?b=ACOR&livre=' . urlencode($numProc);
                                    } elseif ($result->tribunal === 'TRF4' && $numProc) {
                                        $urlTribunal = 'https://jurisprudencia.trf4.jus.br/pesquisa/resultado_pesquisa.php?tipo_pesquisa=1&txtPalavraGerada=' . urlencode($numProc);
                                    } elseif ($result->tribunal === 'TRT12' && $numProc) {
                                        $urlTribunal = 'https://www.trt12.jus.br/busca/jurisprudencia?query=' . urlencode($numProc);
                                    }
                                @endphp
                                @if($urlTribunal)
                                    <a href="{{ $urlTribunal }}" target="_blank" rel="noopener"
                                       class="text-xs font-medium text-green-700 hover:underline">
                                        Consultar no {{ $result->tribunal }} ↗
                                    </a>
                                @endif

                                @php
                                    $dataJulg = $result->juris->data_decisao ? \Carbon\Carbon::parse($result->juris->data_decisao)->format('d-n-Y') : '';
                                    $citacao = ($result->juris->ementa ?? '') . ' (' . $result->tribunal . ', ' . ($result->juris->orgao_julgador ?? '') . ', ' . ($result->juris->sigla_classe ?? '') . ' n. ' . ($result->juris->numero_processo ?? '') . ', Rel. ' . ($result->juris->relator ?? '') . ', julgado em ' . $dataJulg . ').';
                                @endphp
                                <button onclick="copyCitation(this, {{ $result->id }})"
                                    class="text-xs font-medium px-2 py-1 rounded border border-gray-300 hover:bg-gray-50 text-gray-600 transition-colors">
                                    📋 Copiar citação
                                </button>
                                <textarea id="citation-{{ $result->id }}" class="hidden">{{ $citacao }}</textarea>

                                <span class="text-xs text-gray-400 ml-auto">
                                    FT: {{ number_format($result->score_text, 2) }}
                                    · Sem: {{ number_format($result->score_semantic, 2) }}
                                    · RR: {{ number_format($result->score_rerank, 2) }}
                                </span>
                            </div>
                        </div>

                        {{-- Coluna lateral: Insight da IA --}}
                        @if($result->rerank_justification || !empty($result->highlights_json))
                        <div class="lg:w-80 lg:border-l border-t lg:border-t-0 border-gray-100 bg-gradient-to-br from-blue-50 to-indigo-50 p-4 flex flex-col gap-3">
                            @if($result->rerank_justification)
                            <div>
                                <div class="flex items-center gap-2 mb-2">
                                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09z"/>
                                    </svg>
                                    <span class="text-sm font-bold text-indigo-700">Análise da IA</span>
                                </div>
                                <p class="text-sm text-indigo-900 leading-relaxed">{{ $result->rerank_justification }}</p>
                            </div>
                            @endif

                            @if(!empty($result->highlights_json))
                                @foreach($result->highlights_json as $highlight)
                                    @if(($highlight['source'] ?? '') === 'chunk')
                                    <div>
                                        <div class="flex items-center gap-2 mb-2">
                                            <svg class="w-4 h-4 text-amber-500" fill="currentColor" viewBox="0 0 24 24">
                                                <path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/>
                                            </svg>
                                            <span class="text-xs font-semibold text-amber-700 uppercase">Trecho relevante</span>
                                        </div>
                                        <p class="text-xs text-gray-700 leading-relaxed bg-white/60 rounded p-2 border border-amber-200/50">
                                            {{ Str::limit($highlight['text'] ?? '', 350) }}
                                        </p>
                                    </div>
                                    @endif
                                @endforeach
                            @endif
                        </div>
                        @endif

                    </div>
                </div>
                @else
                <div class="bg-white rounded-lg shadow-sm border border-red-100 p-5">
                    <p class="text-sm text-red-500">Erro ao carregar dados da jurisprudência (ID: {{ $result->jurisprudence_id }}, {{ $result->tribunal }})</p>
                </div>
                @endif
            @endforeach
        </div>
    @endif

    {{-- Bloco de Citação --}}
    @if($search->citationBlock)
        <div id="citation-block" class="mt-8 bg-white rounded-lg shadow-md p-6 border-l-4" style="border-left-color: #385776;">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-gray-800">Bloco de Citação para Petição</h2>
                <button onclick="copyBlock()" class="text-xs px-3 py-1 rounded border border-gray-300 hover:bg-gray-50 transition-colors">
                    📋 Copiar tudo
                </button>
            </div>
            <div id="citation-content">
                <div class="mb-6">
                    <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">Síntese Objetiva</h3>
                    <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $search->citationBlock->sintese_objetiva }}</div>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-600 uppercase mb-2">Bloco de Precedentes</h3>
                    <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line font-mono">{{ $search->citationBlock->bloco_precedentes }}</div>
                </div>
            </div>
        </div>
    @endif

</div>

@push('scripts')
<script>
function copyCitation(btn, resultId) {
    var textarea = document.getElementById('citation-' + resultId);
    if (!textarea) return;
    navigator.clipboard.writeText(textarea.value).then(function() {
        var original = btn.innerHTML;
        btn.innerHTML = '✅ Copiado!';
        btn.classList.add('bg-green-50', 'border-green-300', 'text-green-700');
        setTimeout(function() {
            btn.innerHTML = original;
            btn.classList.remove('bg-green-50', 'border-green-300', 'text-green-700');
        }, 2000);
    });
}

function copyBlock() {
    var el = document.getElementById('citation-content');
    var text = el.innerText;
    navigator.clipboard.writeText(text).then(function() {
        var btn = event.target;
        var original = btn.innerHTML;
        btn.innerHTML = '✅ Copiado!';
        setTimeout(function() { btn.innerHTML = original; }, 2000);
    });
}
</script>
@endpush
@endsection
