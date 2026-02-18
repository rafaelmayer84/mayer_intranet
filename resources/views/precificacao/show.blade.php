@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('precificacao.index') }}" class="text-indigo-600 hover:text-indigo-800">← Voltar</a>
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Proposta #{{ $proposta->id }}</h1>
        @php
            $statusColors = [
                'gerada' => 'bg-yellow-100 text-yellow-700',
                'enviada' => 'bg-blue-100 text-blue-700',
                'aceita' => 'bg-green-100 text-green-700',
                'recusada' => 'bg-red-100 text-red-700',
            ];
        @endphp
        <span class="px-3 py-1 text-xs rounded-full {{ $statusColors[$proposta->status] ?? 'bg-gray-100 text-gray-700' }}">
            {{ ucfirst($proposta->status) }}
        </span>
    </div>

    {{-- Info do proponente --}}
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3">Proponente</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-400">Nome:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->nome_proponente ?? '-' }}</p>
            </div>
            <div>
                <span class="text-gray-400">Tipo:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->tipo_pessoa ?? '-' }}</p>
            </div>
            <div>
                <span class="text-gray-400">Área:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->area_direito ?? '-' }}</p>
            </div>
            @if($proposta->tipo_acao)
            <div>
                <span class="text-gray-400">Tipo de Ação:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->tipo_acao }}</p>
            </div>
            @endif
            <div>
                <span class="text-gray-400">Data:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->created_at->format('d/m/Y H:i') }}</p>
            </div>
            @if($proposta->valor_causa)
            <div>
                <span class="text-gray-400">Valor Causa:</span>
                <p class="font-medium text-gray-800 dark:text-white">R$ {{ number_format($proposta->valor_causa, 2, ',', '.') }}</p>
            </div>
            @endif
            @if($proposta->siric_score)
            <div>
                <span class="text-gray-400">SIRIC:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->siric_score }} ({{ $proposta->siric_rating }})</p>
            </div>
            @endif
        </div>
        @if($proposta->descricao_demanda)
        <div class="mt-4">
            <span class="text-gray-400 text-sm">Demanda:</span>
            <p class="text-sm text-gray-700 dark:text-gray-300 mt-1">{{ $proposta->descricao_demanda }}</p>
        </div>
        @endif
    </div>

    {{-- 3 Propostas --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        @foreach([
            ['key' => 'rapida', 'label' => 'Fechamento Rápido', 'icon' => '⚡', 'data' => $proposta->proposta_rapida],
            ['key' => 'equilibrada', 'label' => 'Equilibrada', 'icon' => '⚖️', 'data' => $proposta->proposta_equilibrada],
            ['key' => 'premium', 'label' => 'Premium', 'icon' => '👑', 'data' => $proposta->proposta_premium],
        ] as $tipo)
            @php
                $isRecommended = $proposta->recomendacao_ia === $tipo['key'];
                $isChosen = $proposta->proposta_escolhida === $tipo['key'];
                $p = $tipo['data'] ?? [];
            @endphp
            <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-5 relative
                {{ $isRecommended ? 'ring-2 ring-indigo-500' : '' }}
                {{ $isChosen ? 'ring-2 ring-green-500' : '' }}">
                @if($isRecommended)
                    <div class="absolute -top-2 left-4 px-2 py-0.5 bg-brand text-white text-xs rounded-full">Recomendada</div>
                @endif
                @if($isChosen)
                    <div class="absolute -top-2 right-4 px-2 py-0.5 bg-green-600 text-white text-xs rounded-full">Escolhida</div>
                @endif
                <div class="text-center mb-3">
                    <span class="text-xl">{{ $tipo['icon'] }}</span>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mt-1">{{ $tipo['label'] }}</h3>
                    <p class="text-2xl font-bold text-gray-800 dark:text-white mt-1">
                        R$ {{ number_format($p['valor_honorarios'] ?? 0, 0, ',', '.') }}
                    </p>
                    <p class="text-xs text-gray-500">{{ $p['tipo_cobranca'] ?? 'fixo' }} | {{ $p['parcelas_sugeridas'] ?? 1 }}x</p>
                </div>
                <p class="text-xs text-gray-600 dark:text-gray-300 leading-relaxed">{{ $p['justificativa_estrategica'] ?? '' }}</p>
            </div>
        @endforeach
    </div>

    {{-- Justificativa IA --}}
    @if($proposta->justificativa_ia)
    <div class="bg-white dark:bg-gray-800 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Análise Estratégica</h2>
        <p class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed">{{ $proposta->justificativa_ia }}</p>
    </div>
    @endif

    {{-- Decisão do advogado --}}
    @if($proposta->proposta_escolhida)
    <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl border border-green-200 dark:border-green-800 p-6">
        <h2 class="text-sm font-semibold text-green-700 dark:text-green-300 uppercase tracking-wider mb-2">Decisão do Advogado</h2>
        <p class="text-sm text-green-800 dark:text-green-200">
            Proposta escolhida: <strong>{{ ucfirst($proposta->proposta_escolhida) }}</strong>
            @if($proposta->valor_final)
                | Valor final: <strong>R$ {{ number_format($proposta->valor_final, 2, ',', '.') }}</strong>
            @endif
        </p>
        @if($proposta->observacao_advogado)
            <p class="text-sm text-green-700 dark:text-green-300 mt-2">{{ $proposta->observacao_advogado }}</p>
        @endif
    </div>

    {{-- Botão: Gerar Proposta para Cliente --}}
    <div class="mt-4 flex items-center gap-3">
        <button id="btn-gerar-proposta"
            onclick="gerarPropostaCliente({{ $proposta->id }})"
            class="px-5 py-2.5 rounded-xl text-sm font-medium hover:opacity-90 transition flex items-center gap-2" style="background-color:#1B334A;color:#ffffff;">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span id="btn-gerar-texto">Gerar Proposta para Cliente</span>
        </button>

        @if($proposta->texto_proposta_cliente)
            <a href="{{ route('precificacao.proposta.print', $proposta->id) }}" target="_blank"
                class="px-5 py-2.5 bg-gray-600 text-white rounded-xl text-sm font-medium hover:bg-gray-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Ver Proposta Gerada
            </a>
        @endif
    </div>

    <div id="proposta-status" class="mt-2 text-sm hidden"></div>
    @endif
</div>
@push('scripts')
<script>
function gerarPropostaCliente(id) {
    const btn = document.getElementById('btn-gerar-proposta');
    const btnTexto = document.getElementById('btn-gerar-texto');
    const status = document.getElementById('proposta-status');

    btn.disabled = true;
    btnTexto.textContent = 'Gerando proposta...';
    btn.classList.add('opacity-60');
    status.classList.remove('hidden', 'text-red-600', 'text-green-600');
    status.textContent = 'A IA está redigindo a proposta persuasiva. Aguarde ~15-30 segundos...';
    status.classList.add('text-gray-500');

    fetch(`/precificacao/${id}/gerar-proposta-cliente`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            status.textContent = 'Proposta gerada com sucesso! Abrindo...';
            status.classList.remove('text-gray-500');
            status.classList.add('text-green-600');
            window.open(data.redirect, '_blank');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    })
    .catch(err => {
        status.textContent = 'Erro: ' + err.message;
        status.classList.remove('text-gray-500');
        status.classList.add('text-red-600');
        btn.disabled = false;
        btnTexto.textContent = 'Gerar Proposta para Cliente';
        btn.classList.remove('opacity-60');
    });
}
</script>
@endpush
@endsection
