@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">
    {{-- Cabeçalho --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Manuais Normativos</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Publicações e documentos oficiais do escritório</p>
        </div>
        @if($isAdmin)
            <a href="{{ route('admin.manuais.grupos.index') }}"
               class="inline-flex items-center px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                Administrar
            </a>
        @endif
    </div>

    {{-- Grupos em Accordion --}}
    @forelse($grupos as $grupo)
        <div class="mb-3 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
            {{-- Header do grupo (clicável) --}}
            <button type="button"
                    onclick="toggleGrupo({{ $grupo->id }})"
                    class="w-full flex items-center justify-between px-5 py-4 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-750 transition text-left">
                <div class="flex items-center space-x-3">
                    <span id="icon-{{ $grupo->id }}"
                          class="text-blue-600 dark:text-blue-400 font-bold text-xl leading-none select-none">+</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $grupo->nome }}</span>
                    <span class="text-xs text-gray-400 dark:text-gray-500">({{ $grupo->documentosAtivos->count() }})</span>
                </div>
            </button>

            {{-- Corpo do grupo (documentos) --}}
            <div id="grupo-{{ $grupo->id }}" class="hidden border-t border-gray-200 dark:border-gray-700">
                @forelse($grupo->documentosAtivos as $doc)
                    <div class="flex items-center justify-between px-5 py-3 bg-gray-50 dark:bg-gray-850 {{ !$loop->last ? 'border-b border-gray-100 dark:border-gray-700' : '' }}">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-700 dark:text-gray-200 truncate">{{ $doc->titulo }}</p>
                            @if($doc->descricao)
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate">{{ $doc->descricao }}</p>
                            @endif
                            @if($doc->data_publicacao)
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-0.5">{{ $doc->data_publicacao->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        <a href="{{ $doc->url_onedrive }}" target="_blank" rel="noopener noreferrer"
                           class="ml-4 inline-flex items-center px-3 py-1.5 text-xs font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 hover:bg-blue-100 dark:hover:bg-blue-900/50 rounded-md transition">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Abrir
                        </a>
                    </div>
                @empty
                    <div class="px-5 py-4 text-sm text-gray-400 dark:text-gray-500 italic">
                        Nenhum documento cadastrado neste grupo.
                    </div>
                @endforelse
            </div>
        </div>
    @empty
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nenhum manual disponível para você no momento.</p>
        </div>
    @endforelse
</div>

{{-- JS Vanilla: Accordion toggle --}}
<script>
function toggleGrupo(id) {
    var body = document.getElementById('grupo-' + id);
    var icon = document.getElementById('icon-' + id);
    if (body.classList.contains('hidden')) {
        body.classList.remove('hidden');
        icon.textContent = '−';
    } else {
        body.classList.add('hidden');
        icon.textContent = '+';
    }
}
</script>
@endsection
