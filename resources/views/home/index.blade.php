@extends('layouts.app')

@section('title', 'Home')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-5">

    {{-- ============================================================
         HEADER + BUSCA GLOBAL
    ============================================================ --}}
    <div class="mb-6">
        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-5">
            <div>
                <h1 class="text-2xl font-bold tracking-tight" style="color: #1B334A;">{{ $saudacao }}, {{ $primeiroNome }}</h1>
                <p class="text-sm text-gray-400 mt-0.5">{{ now()->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY') }}</p>
            </div>
            {{-- Volumetria inline --}}
            <div class="flex items-center gap-4 text-xs text-gray-400">
                <span><strong class="text-gray-600">{{ number_format($volumetria['clientes']) }}</strong> clientes</span>
                <span class="text-gray-200">|</span>
                <span><strong class="text-gray-600">{{ $volumetria['processos'] }}</strong> processos ativos</span>
                <span class="text-gray-200">|</span>
                <span><strong class="text-gray-600">{{ $volumetria['oportunidades'] }}</strong> oportunidades</span>
            </div>
        </div>

        {{-- Busca Global --}}
        <div x-data="buscaGlobal()" class="relative">
            <div class="relative group">
                <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                    <i class="fa-solid fa-magnifying-glass text-gray-300 group-focus-within:text-gray-500 transition"></i>
                </span>
                <input type="text" x-model="query" x-on:input.debounce.300ms="buscar()"
                    x-on:focus="aberto = query.length >= 2" x-on:click.away="aberto = false" x-on:keydown.escape="aberto = false"
                    placeholder="Buscar cliente, processo, lead ou conta CRM..."
                    class="w-full pl-11 pr-10 py-3.5 rounded-2xl border-2 border-gray-100 bg-white text-sm focus:outline-none focus:border-blue-300 focus:shadow-lg focus:shadow-blue-50 transition-all duration-200"
                    autocomplete="off">
                <span x-show="loading" class="absolute inset-y-0 right-0 flex items-center pr-4">
                    <svg class="animate-spin h-4 w-4 text-blue-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                </span>
            </div>
            <div x-show="aberto && resultados.length > 0" x-transition.opacity.duration.150ms
                class="absolute z-50 w-full mt-1.5 bg-white rounded-2xl shadow-2xl border border-gray-100 max-h-80 overflow-y-auto">
                <template x-for="item in resultados" :key="item.tipo + item.titulo + item.subtitulo">
                    <a :href="item.url" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50/80 transition border-b border-gray-50 last:border-0">
                        <span class="flex-shrink-0 w-9 h-9 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #385776, #1B334A);">
                            <i :class="item.icon" class="text-white text-xs"></i>
                        </span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-sm text-gray-900 truncate" x-text="item.titulo"></span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold tracking-wide uppercase flex-shrink-0" :class="item.badge_cor" x-text="item.badge"></span>
                            </div>
                            <p class="text-xs text-gray-400 truncate mt-0.5" x-text="item.subtitulo"></p>
                        </div>
                        <i class="fa-solid fa-arrow-right text-gray-200 text-[10px]"></i>
                    </a>
                </template>
            </div>
            <div x-show="aberto && resultados.length === 0 && !loading && query.length >= 2" x-transition
                class="absolute z-50 w-full mt-1.5 bg-white rounded-2xl shadow-2xl border border-gray-100 p-8 text-center">
                <div class="w-12 h-12 mx-auto mb-3 rounded-2xl bg-gray-50 flex items-center justify-center">
                    <i class="fa-solid fa-magnifying-glass text-gray-300 text-lg"></i>
                </div>
                <p class="text-sm text-gray-400">Nenhum resultado para "<span class="font-medium text-gray-600" x-text="query"></span>"</p>
            </div>
        </div>
    </div>

    {{-- ============================================================
         LINHA 1 — SCORE GDP (hero) + FINANCEIRO COMPACTO
    ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-5 gap-4 mb-4">

        {{-- GDP Score — Hero Card --}}
        <a href="{{ url('/gdp') }}" class="lg:col-span-3 block relative overflow-hidden rounded-2xl p-6 group transition-all duration-300 hover:shadow-xl"
           style="background: linear-gradient(135deg, #1B334A 0%, #385776 50%, #4A7A9B 100%);">
            <div class="absolute top-0 right-0 w-40 h-40 rounded-full opacity-5 -mr-10 -mt-10" style="background: white;"></div>
            <div class="absolute bottom-0 left-0 w-24 h-24 rounded-full opacity-5 -ml-6 -mb-6" style="background: white;"></div>
            <div class="relative z-10">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-white/15 backdrop-blur flex items-center justify-center">
                            <i class="fa-solid fa-chart-line text-white"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-base">Meu Score GDP</h3>
                            @if($gdpScore)
                            <p class="text-white/50 text-xs">Ref: {{ $gdpScore['mes_ref'] }}</p>
                            @endif
                        </div>
                    </div>
                    <i class="fa-solid fa-arrow-up-right text-white/30 group-hover:text-white/60 transition text-lg"></i>
                </div>

                @if($gdpScore)
                <div class="flex items-end gap-4 mb-5">
                    <span class="text-5xl font-black text-white leading-none">{{ $gdpScore['score_total'] }}</span>
                    <div class="mb-1">
                        <span class="text-white/40 text-sm font-medium">/ 100</span>
                        @if($gdpScore['variacao'] !== null)
                        <div class="flex items-center gap-1 mt-1 {{ $gdpScore['variacao'] >= 0 ? 'text-emerald-300' : 'text-red-300' }}">
                            <i class="fa-solid {{ $gdpScore['variacao'] >= 0 ? 'fa-caret-up' : 'fa-caret-down' }} text-xs"></i>
                            <span class="text-xs font-bold">{{ abs($gdpScore['variacao']) }} pts</span>
                        </div>
                        @endif
                    </div>
                    @if($gdpScore['ranking'])
                    <div class="ml-auto mb-1 text-right">
                        <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/10 backdrop-blur">
                            <i class="fa-solid fa-trophy text-amber-300 text-xs"></i>
                            <span class="text-white font-bold text-sm">{{ $gdpScore['ranking'] }}&#186;</span>
                            <span class="text-white/50 text-xs">de {{ $gdpScore['total_participantes'] }}</span>
                        </div>
                    </div>
                    @endif
                </div>

                {{-- Barras por eixo --}}
                <div class="grid grid-cols-4 gap-3">
                    @php $eixos = [
                        ['label' => 'Juridico', 'key' => 'JUR', 'score' => $gdpScore['score_juridico']],
                        ['label' => 'Financeiro', 'key' => 'FIN', 'score' => $gdpScore['score_financeiro']],
                        ['label' => 'Desenvolvimento', 'key' => 'DEV', 'score' => $gdpScore['score_desenvolvimento']],
                        ['label' => 'Atendimento', 'key' => 'ATE', 'score' => $gdpScore['score_atendimento']],
                    ]; @endphp
                    @foreach($eixos as $eixo)
                    <div>
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-[10px] font-bold text-white/60 uppercase tracking-wider">{{ $eixo['key'] }}</span>
                            <span class="text-[11px] font-bold text-white">{{ $eixo['score'] }}</span>
                        </div>
                        <div class="w-full bg-white/10 rounded-full h-2">
                            <div class="h-2 rounded-full transition-all duration-500
                                {{ $eixo['score'] >= 70 ? 'bg-emerald-400' : ($eixo['score'] >= 40 ? 'bg-amber-400' : 'bg-red-400') }}"
                                style="width: {{ min($eixo['score'], 100) }}%"></div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="text-center py-6">
                    <div class="w-14 h-14 mx-auto mb-3 rounded-2xl bg-white/10 flex items-center justify-center">
                        <i class="fa-solid fa-hourglass-half text-white/40 text-xl"></i>
                    </div>
                    <p class="text-white/40 text-sm">Aguardando primeira apuracao</p>
                </div>
                @endif
            </div>
        </a>

        {{-- Financeiro do mes --}}
        <div class="lg:col-span-2 grid grid-cols-1 gap-4">
            <a href="{{ url('/visao-gerencial') }}" class="block rounded-2xl bg-white border border-gray-100 p-5 hover:shadow-lg transition-all duration-300 group">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 flex items-center justify-center">
                            <i class="fa-solid fa-dollar-sign text-emerald-600 text-sm"></i>
                        </div>
                        <h3 class="font-bold text-sm" style="color: #1B334A;">Financeiro do Mes</h3>
                    </div>
                    <i class="fa-solid fa-arrow-up-right text-gray-200 group-hover:text-gray-400 transition text-xs"></i>
                </div>
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-400">Receita</span>
                        <div class="flex items-center gap-2">
                            <span class="font-bold text-emerald-600 text-sm">R$ {{ number_format($resumoFinanceiro['receita'], 0, ',', '.') }}</span>
                            @if($resumoFinanceiro['var_receita'] !== null)
                            <span class="text-[10px] font-bold px-1.5 py-0.5 rounded {{ $resumoFinanceiro['var_receita'] >= 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-500' }}">
                                {{ $resumoFinanceiro['var_receita'] >= 0 ? '+' : '' }}{{ $resumoFinanceiro['var_receita'] }}%
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs text-gray-400">Despesas</span>
                        <span class="font-bold text-red-500 text-sm">R$ {{ number_format($resumoFinanceiro['despesa'], 0, ',', '.') }}</span>
                    </div>
                    <div class="h-px bg-gray-100"></div>
                    <div class="flex justify-between items-center">
                        <span class="text-xs font-semibold text-gray-600">Resultado</span>
                        <span class="font-black text-base {{ $resumoFinanceiro['resultado'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            R$ {{ number_format($resumoFinanceiro['resultado'], 0, ',', '.') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="flex-1 bg-gray-100 rounded-full h-2">
                            <div class="h-2 rounded-full {{ $resumoFinanceiro['margem'] >= 30 ? 'bg-emerald-500' : ($resumoFinanceiro['margem'] >= 10 ? 'bg-amber-400' : 'bg-red-400') }}"
                                 style="width: {{ min(max($resumoFinanceiro['margem'], 0), 100) }}%"></div>
                        </div>
                        <span class="text-xs font-bold text-gray-500">{{ $resumoFinanceiro['margem'] }}%</span>
                    </div>
                </div>
            </a>

            {{-- Avisos --}}
            <a href="{{ url('/avisos') }}" class="block rounded-2xl bg-white border border-gray-100 p-5 hover:shadow-lg transition-all duration-300 group">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2.5">
                        <div class="w-9 h-9 rounded-xl {{ $avisos['total'] > 0 ? 'bg-red-50' : 'bg-gray-50' }} flex items-center justify-center">
                            <i class="fa-solid fa-bell {{ $avisos['total'] > 0 ? 'text-red-500' : 'text-gray-300' }} text-sm"></i>
                        </div>
                        <h3 class="font-bold text-sm" style="color: #1B334A;">Avisos</h3>
                        @if($avisos['total'] > 0)
                        <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-red-500 text-white text-[10px] font-black">{{ $avisos['total'] }}</span>
                        @endif
                    </div>
                    <i class="fa-solid fa-arrow-up-right text-gray-200 group-hover:text-gray-400 transition text-xs"></i>
                </div>
                @if(count($avisos['avisos']) > 0)
                <div class="space-y-1.5">
                    @foreach(array_slice($avisos['avisos'], 0, 3) as $aviso)
                    <div class="flex items-center gap-2 text-xs">
                        @if($aviso['destaque'])<i class="fa-solid fa-star text-amber-400 text-[9px]"></i>@else<span class="w-1.5 h-1.5 rounded-full bg-gray-200 flex-shrink-0"></span>@endif
                        <span class="text-gray-600 truncate flex-1">{{ $aviso['titulo'] }}</span>
                        <span class="text-gray-300 flex-shrink-0">{{ $aviso['data'] }}</span>
                    </div>
                    @endforeach
                </div>
                @else
                <p class="text-xs text-gray-300 text-center py-2"><i class="fa-solid fa-check-circle text-emerald-300 mr-1"></i> Tudo lido</p>
                @endif
            </a>
        </div>
    </div>

    {{-- ============================================================
         LINHA 2 — ALERTAS CRM + TICKETS + ATALHOS
    ============================================================ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">

        {{-- Alertas CRM --}}
        <div class="rounded-2xl bg-white border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-amber-50 flex items-center justify-center">
                        <i class="fa-solid fa-triangle-exclamation text-amber-500 text-sm"></i>
                    </div>
                    <h3 class="font-bold text-sm" style="color: #1B334A;">Alertas CRM</h3>
                    @if(count($alertasCrm) > 0)
                    <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-amber-100 text-amber-700 text-[10px] font-black">{{ count($alertasCrm) }}</span>
                    @endif
                </div>
                <a href="{{ url('/crm') }}" class="text-[11px] font-bold uppercase tracking-wider hover:underline" style="color: #385776;">CRM &rarr;</a>
            </div>
            @if(count($alertasCrm) > 0)
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach($alertasCrm as $alerta)
                <a href="{{ $alerta['url'] ?? '/crm' }}" class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-amber-50/50 transition">
                    <i class="{{ $alerta['icon'] ?? 'fa-solid fa-circle-exclamation' }} {{ $alerta['cor'] ?? 'text-amber-500' }} text-sm"></i>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $alerta['titulo'] }}</p>
                        <p class="text-[10px] text-gray-400">{{ $alerta['descricao'] }}</p>
                    </div>
                </a>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-emerald-50 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400"></i></div>
                <p class="text-xs text-gray-300">Carteira em dia</p>
            </div>
            @endif
        </div>

        {{-- Tickets NEXO --}}
        <div class="rounded-2xl bg-white border border-gray-100 p-5">
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-9 h-9 rounded-xl bg-indigo-50 flex items-center justify-center">
                        <i class="fa-solid fa-ticket text-indigo-500 text-sm"></i>
                    </div>
                    <h3 class="font-bold text-sm" style="color: #1B334A;">Tickets Abertos</h3>
                    @if(count($ticketsAbertos) > 0)
                    <span class="inline-flex items-center justify-center min-w-[22px] h-[22px] px-1.5 rounded-full bg-indigo-100 text-indigo-700 text-[10px] font-black">{{ count($ticketsAbertos) }}</span>
                    @endif
                </div>
                <a href="{{ url('/nexo/tickets') }}" class="text-[11px] font-bold uppercase tracking-wider hover:underline" style="color: #385776;">Tickets &rarr;</a>
            </div>
            @if(count($ticketsAbertos) > 0)
            <div class="space-y-1.5 max-h-48 overflow-y-auto">
                @foreach($ticketsAbertos as $ticket)
                <div class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-indigo-50/50 transition">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center text-[10px] font-black
                        @if(($ticket['prioridade'] ?? '') === 'alta') bg-red-100 text-red-600
                        @elseif(($ticket['prioridade'] ?? '') === 'media') bg-amber-100 text-amber-600
                        @else bg-gray-100 text-gray-400 @endif">#{{ $ticket['id'] }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $ticket['titulo'] }}</p>
                        <p class="text-[10px] text-gray-400">{{ $ticket['criado_em'] }}</p>
                    </div>
                    @if(($ticket['prioridade'] ?? '') === 'alta')
                    <span class="text-[9px] font-black text-red-500 bg-red-50 px-1.5 py-0.5 rounded-md uppercase tracking-wider">Urgente</span>
                    @endif
                </div>
                @endforeach
            </div>
            @else
            <div class="text-center py-5">
                <div class="w-10 h-10 mx-auto mb-2 rounded-xl bg-emerald-50 flex items-center justify-center"><i class="fa-solid fa-check text-emerald-400"></i></div>
                <p class="text-xs text-gray-300">Nenhum ticket aberto</p>
            </div>
            @endif
        </div>

        {{-- Atalhos Rapidos --}}
        <div class="rounded-2xl bg-white border border-gray-100 p-5">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center" style="background: linear-gradient(135deg, #385776, #1B334A);">
                    <i class="fa-solid fa-bolt text-white text-sm"></i>
                </div>
                <h3 class="font-bold text-sm" style="color: #1B334A;">Acesso Rapido</h3>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <a href="{{ url('/nexo/atendimento') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-green-50 to-emerald-50 hover:from-green-100 hover:to-emerald-100 transition-all text-xs font-semibold text-emerald-700 border border-emerald-100/50">
                    <i class="fa-brands fa-whatsapp text-base"></i> WhatsApp
                </a>
                <a href="{{ url('/crm/pipeline') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-blue-50 to-indigo-50 hover:from-blue-100 hover:to-indigo-100 transition-all text-xs font-semibold text-blue-700 border border-blue-100/50">
                    <i class="fa-solid fa-diagram-project text-base"></i> Pipeline
                </a>
                <a href="{{ url('/crm/leads') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-orange-50 to-amber-50 hover:from-orange-100 hover:to-amber-100 transition-all text-xs font-semibold text-orange-700 border border-orange-100/50">
                    <i class="fa-solid fa-bullhorn text-base"></i> Leads
                </a>
                <a href="{{ url('/visao-gerencial') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-purple-50 to-violet-50 hover:from-purple-100 hover:to-violet-100 transition-all text-xs font-semibold text-purple-700 border border-purple-100/50">
                    <i class="fa-solid fa-chart-pie text-base"></i> Financeiro
                </a>
                @if(in_array(Auth::user()->role ?? '', ['admin', 'socio', 'coordenador']))
                <a href="{{ url('/siric') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-cyan-50 to-sky-50 hover:from-cyan-100 hover:to-sky-100 transition-all text-xs font-semibold text-cyan-700 border border-cyan-100/50">
                    <i class="fa-solid fa-magnifying-glass-dollar text-base"></i> SIRIC
                </a>
                <a href="{{ url('/precificacao') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-violet-50 to-fuchsia-50 hover:from-violet-100 hover:to-fuchsia-100 transition-all text-xs font-semibold text-violet-700 border border-violet-100/50">
                    <i class="fa-solid fa-tags text-base"></i> SIPEX
                </a>
                @else
                <a href="{{ url('/gdp') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-teal-50 to-cyan-50 hover:from-teal-100 hover:to-cyan-100 transition-all text-xs font-semibold text-teal-700 border border-teal-100/50">
                    <i class="fa-solid fa-bullseye text-base"></i> Performance
                </a>
                <a href="{{ url('/manuais') }}" class="flex items-center gap-2.5 px-3 py-3 rounded-xl bg-gradient-to-r from-gray-50 to-slate-50 hover:from-gray-100 hover:to-slate-100 transition-all text-xs font-semibold text-gray-600 border border-gray-100/50">
                    <i class="fa-solid fa-book text-base"></i> Manuais
                </a>
                @endif
            </div>
        </div>
    </div>

</div>

@push('scripts')
<script>
function buscaGlobal() {
    return {
        query: '', resultados: [], aberto: false, loading: false,
        async buscar() {
            if (this.query.length < 2) { this.resultados = []; this.aberto = false; return; }
            this.loading = true;
            try {
                const r = await fetch('/home/buscar?q=' + encodeURIComponent(this.query));
                this.resultados = await r.json();
                this.aberto = true;
            } catch(e) { this.resultados = []; } finally { this.loading = false; }
        }
    };
}
</script>
@endpush
@endsection
