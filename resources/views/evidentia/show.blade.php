@extends('layouts.app')

@section('title', 'EVIDENTIA - ' . ($juris->sigla_classe ?? '') . ' ' . ($juris->numero_processo ?? ''))

@section('content')
<div class="w-full px-4 py-6">

    <a href="javascript:history.back()" class="text-sm hover:underline" style="color: #385776;">
        ← Voltar
    </a>

    <div class="bg-white rounded-lg shadow-md p-6 mt-4">

        {{-- Header --}}
        <div class="border-b border-gray-200 pb-4 mb-6">
            <div class="flex items-center gap-3 mb-2">
                <span class="inline-block text-sm font-bold text-white px-3 py-1 rounded" style="background-color: #385776;">
                    {{ $tribunal }}
                </span>
                <span class="text-lg font-bold text-gray-800">
                    {{ $juris->sigla_classe ?? '' }} {{ $juris->numero_processo ?? 'Sem número' }}
                </span>
            </div>
            @if($juris->descricao_classe ?? false)
                <p class="text-sm text-gray-500">{{ $juris->descricao_classe }}</p>
            @endif
        </div>

        {{-- Metadados --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Relator</p>
                <p class="text-sm text-gray-800">{{ $juris->relator ?? 'Não informado' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Órgão Julgador</p>
                <p class="text-sm text-gray-800">{{ $juris->orgao_julgador ?? 'Não informado' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Data do Julgamento</p>
                <p class="text-sm text-gray-800">
                    {{ $juris->data_decisao ? \Carbon\Carbon::parse($juris->data_decisao)->format('d/m/Y') : 'Não informada' }}
                </p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Data de Publicação</p>
                <p class="text-sm text-gray-800">{{ $juris->data_publicacao ?? 'Não informada' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Área do Direito</p>
                <p class="text-sm text-gray-800">{{ $juris->area_direito ?? 'Não classificada' }}</p>
            </div>
            <div>
                <p class="text-xs font-medium text-gray-500 uppercase">Tipo da Decisão</p>
                <p class="text-sm text-gray-800">{{ $juris->tipo_decisao ?? 'Acórdão' }}</p>
            </div>
        </div>

        {{-- Ementa --}}
        <div class="mb-6">
            <h2 class="text-sm font-bold text-gray-700 uppercase border-b pb-2 mb-3">Ementa</h2>
            <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $juris->ementa ?? 'Não disponível' }}</div>
        </div>

        {{-- Tese Jurídica --}}
        @if($juris->tese_juridica ?? false)
            <div class="mb-6">
                <h2 class="text-sm font-bold text-gray-700 uppercase border-b pb-2 mb-3">Tese Jurídica</h2>
                <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $juris->tese_juridica }}</div>
            </div>
        @endif

        {{-- Decisão / Inteiro Teor --}}
        @if($juris->decisao ?? false)
            <div class="mb-6">
                <details>
                    <summary class="text-sm font-bold text-gray-700 uppercase border-b pb-2 mb-3 cursor-pointer">
                        Decisão / Inteiro Teor (clique para expandir)
                    </summary>
                    <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line mt-3 max-h-96 overflow-y-auto">{{ $juris->decisao }}</div>
                </details>
            </div>
        @endif

        {{-- Referências Legislativas --}}
        @if($juris->referencias_legislativas ?? false)
            <div class="mb-6">
                <h2 class="text-sm font-bold text-gray-700 uppercase border-b pb-2 mb-3">Referências Legislativas</h2>
                <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $juris->referencias_legislativas }}</div>
            </div>
        @endif

        {{-- Acórdãos Similares --}}
        @if($juris->acordaos_similares ?? false)
            <div class="mb-6">
                <h2 class="text-sm font-bold text-gray-700 uppercase border-b pb-2 mb-3">Acórdãos Similares Citados</h2>
                <div class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $juris->acordaos_similares }}</div>
            </div>
        @endif

        {{-- Termos Auxiliares --}}
        @if($juris->termos_auxiliares ?? false)
            <div class="mb-6">
                <h2 class="text-sm font-bold text-gray-700 uppercase border-b pb-2 mb-3">Termos Auxiliares</h2>
                <div class="text-sm text-gray-600">{{ $juris->termos_auxiliares }}</div>
            </div>
        @endif

        {{-- Fonte --}}
        <div class="border-t border-gray-200 pt-4 mt-6 text-xs text-gray-400">
            <p>Fonte: {{ $juris->fonte_dataset ?? 'Acervo Local' }} · ID: {{ $juris->id }} · External ID: {{ $juris->external_id ?? 'N/A' }}</p>
        </div>

    </div>
</div>
@endsection
