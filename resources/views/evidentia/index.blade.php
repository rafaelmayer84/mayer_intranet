@extends('layouts.app')

@section('title', 'EVIDENTIA - Busca Inteligente de Jurisprudência')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">

    {{-- Header --}}
    <div class="text-center mb-8">
        <div class="flex justify-center mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" class="w-20 h-20">
                <polygon points="100,8 185,52 185,148 100,192 15,148 15,52" fill="#385776" stroke="#1B334A" stroke-width="4"/>
                <polygon points="100,22 172,60 172,140 100,178 28,140 28,60" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2"/>
                <g transform="translate(62,48)"><rect x="0" y="0" width="16" height="104" rx="3" fill="#FFFFFF"/><rect x="0" y="0" width="72" height="16" rx="3" fill="#FFFFFF"/><rect x="0" y="44" width="60" height="16" rx="3" fill="#FFFFFF"/><rect x="0" y="88" width="72" height="16" rx="3" fill="#FFFFFF"/></g>
                <path d="M157,38 L159.5,47.5 L169,50 L159.5,52.5 L157,62 L154.5,52.5 L145,50 L154.5,47.5 Z" fill="#F5C842" opacity="0.95"/>
                <circle cx="167" cy="32" r="2" fill="#F5C842" opacity="0.7"/>
                <circle cx="172" cy="48" r="1.5" fill="#F5C842" opacity="0.5"/>
                <g transform="translate(135,115)" opacity="0.3"><circle cx="12" cy="12" r="10" fill="none" stroke="#FFFFFF" stroke-width="3"/><line x1="19" y1="19" x2="28" y2="28" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round"/></g>
            </svg>
        </div>
        <h1 class="text-3xl font-bold" style="color: #385776;">EVIDENTIA</h1>
        <p class="text-gray-500 mt-2">Busca Inteligente de Jurisprudência com IA</p>
    </div>

    {{-- Mensagens --}}
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-6">
            {{ session('error') }}
        </div>
    @endif

    {{-- Formulário de busca --}}
    <form action="{{ route('evidentia.search') }}" method="POST" class="bg-white rounded-lg shadow-md p-6 mb-8">
        @csrf

        {{-- Campo principal --}}
        <div class="mb-4">
            <label for="query" class="block text-sm font-medium text-gray-700 mb-1">Pesquisa</label>
            <textarea name="query" id="query" rows="3"
                class="w-full border border-gray-300 rounded-lg px-4 py-3 focus:ring-2 focus:border-transparent text-base"
                style="focus:ring-color: #385776;"
                placeholder="Ex: indenização por dano moral em contrato bancário com cláusula abusiva"
                required minlength="5">{{ old('query') }}</textarea>
            @error('query')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Filtros expansíveis --}}
        <details class="mb-4">
            <summary class="cursor-pointer text-sm font-medium" style="color: #385776;">
                Filtros avançados
            </summary>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Tribunal</label>
                    <select name="tribunal" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="">Todos</option>
                        @foreach($tribunais as $t)
                            <option value="{{ $t }}" {{ old('tribunal') == $t ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Classe</label>
                    <input type="text" name="classe" value="{{ old('classe') }}"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        placeholder="Ex: APL, REsp, HC">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Área do Direito</label>
                    <select name="area_direito" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="">Todas</option>
                        <option value="cível" {{ old('area_direito') == 'cível' ? 'selected' : '' }}>Cível</option>
                        <option value="criminal" {{ old('area_direito') == 'criminal' ? 'selected' : '' }}>Criminal</option>
                        <option value="trabalhista" {{ old('area_direito') == 'trabalhista' ? 'selected' : '' }}>Trabalhista</option>
                        <option value="público" {{ old('area_direito') == 'público' ? 'selected' : '' }}>Público</option>
                        <option value="comercial" {{ old('area_direito') == 'comercial' ? 'selected' : '' }}>Comercial</option>
                        <option value="família" {{ old('area_direito') == 'família' ? 'selected' : '' }}>Família</option>
                        <option value="consumidor" {{ old('area_direito') == 'consumidor' ? 'selected' : '' }}>Consumidor</option>
                        <option value="previdenciário" {{ old('area_direito') == 'previdenciário' ? 'selected' : '' }}>Previdenciário</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Órgão Julgador</label>
                    <input type="text" name="orgao_julgador" value="{{ old('orgao_julgador') }}"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        placeholder="Ex: 1ª Câmara de Direito Civil">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Relator</label>
                    <input type="text" name="relator" value="{{ old('relator') }}"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                        placeholder="Ex: Des. João Silva">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Resultados</label>
                    <select name="topk" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="5" {{ old('topk', 10) == 5 ? 'selected' : '' }}>5</option>
                        <option value="10" {{ old('topk', 10) == 10 ? 'selected' : '' }}>10</option>
                        <option value="20" {{ old('topk', 10) == 20 ? 'selected' : '' }}>20</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Período - De</label>
                    <input type="date" name="periodo_inicio" value="{{ old('periodo_inicio') }}"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Período - Até</label>
                    <input type="date" name="periodo_fim" value="{{ old('periodo_fim') }}"
                        class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                </div>
            </div>
        </details>

        <button type="submit"
            class="w-full text-white font-medium py-3 px-6 rounded-lg transition-colors text-base"
            style="background-color: #385776;"
            onmouseover="this.style.backgroundColor='#1B334A'"
            onmouseout="this.style.backgroundColor='#385776'">
            Buscar Jurisprudência
        </button>
    </form>

    {{-- Buscas recentes --}}
    @if($recentSearches->isNotEmpty())
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">Buscas Recentes</h2>
            <div class="space-y-3">
                @foreach($recentSearches as $s)
                    <a href="{{ route('evidentia.resultados', $s->id) }}"
                       class="block p-3 rounded-lg border border-gray-100 hover:bg-gray-50 transition-colors">
                        <div class="flex justify-between items-start">
                            <p class="text-sm text-gray-800 flex-1">{{ Str::limit($s->query, 120) }}</p>
                            <div class="text-xs text-gray-400 ml-4 whitespace-nowrap">
                                {{ $s->created_at->diffForHumans() }}
                                @if($s->degraded_mode)
                                    <span class="text-amber-500 ml-1" title="Modo degradado">⚠</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex gap-3 mt-1 text-xs text-gray-500">
                            <span>{{ $s->results()->count() }} resultados</span>
                            <span>{{ $s->latency_ms }}ms</span>
                            @if($s->cost_usd > 0)
                                <span>${{ number_format((float)$s->cost_usd, 4) }}</span>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

</div>
@endsection
