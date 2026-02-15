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
    @endif
</div>
@endsection
