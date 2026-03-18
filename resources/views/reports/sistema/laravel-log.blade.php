@extends('layouts.app')

@section('title', 'Log Laravel')

@section('content')
<style>
    .report-table { font-family: "Courier New", Courier, monospace !important; border-collapse: collapse !important; width: 100%; }
    .report-table thead tr th {
        background-color: #1B334A !important; color: #ffffff !important;
        font-size: 0.7rem !important; letter-spacing: 1px; text-transform: uppercase;
        padding: 10px 12px !important; border: 1px solid #0f1f2e !important; white-space: nowrap;
    }
    .report-table thead tr th a,
    .report-table thead tr th a:hover { color: #ffffff !important; text-decoration: none !important; }
    .report-table tbody tr:nth-child(even) td { background-color: #dcfce7 !important; }
    .report-table tbody tr:nth-child(odd) td { background-color: #ffffff !important; }
    .report-table tbody tr:hover td { background-color: #fef3c7 !important; }
    .report-table td { font-size: 0.75rem !important; padding: 6px 10px !important; border: 1px solid #d1d5db !important; }
    .log-message { max-width: 600px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; cursor: pointer; }
    .log-message:hover { white-space: normal; word-break: break-all; }
</style>
<div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 py-6">

    <nav class="flex mb-4 text-sm text-gray-500">
        <a href="{{ route('relatorios.index') }}" class="hover:text-[#385776]">Relatórios</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-400">Saúde do Sistema</span>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 font-medium">Log Laravel</span>
    </nav>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800 font-mono tracking-wide">Log Laravel</h1>
            <p class="text-xs text-gray-400 font-mono mt-1">Saúde do Sistema • Últimas {{ number_format($stats['total']) }} entradas ({{ $stats['size'] }})</p>
        </div>
        <div class="flex items-center gap-2">
            <button onclick="document.getElementById('legenda-modal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-slate-600 hover:bg-slate-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                LEGENDA
            </button>
            @if(isset($exportRoute))
            <a href="{{ $exportRoute }}&type=xlsx" class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">EXCEL</a>
            <a href="{{ $exportRoute }}&type=pdf" class="inline-flex items-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-xs font-bold font-mono rounded-lg transition-colors tracking-wider">PDF</a>
            @endif
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-7 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-gray-800">{{ number_format($stats['total']) }}</div>
            <div class="text-xs text-gray-500 font-mono mt-1">TOTAL</div>
        </div>
        <div class="bg-blue-50 rounded-xl border border-blue-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-blue-700">{{ number_format($stats['info']) }}</div>
            <div class="text-xs text-blue-600 font-mono mt-1">INFO</div>
        </div>
        <div class="bg-yellow-50 rounded-xl border border-yellow-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-yellow-700">{{ number_format($stats['warning']) }}</div>
            <div class="text-xs text-yellow-600 font-mono mt-1">WARNING</div>
        </div>
        <div class="bg-red-50 rounded-xl border border-red-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-red-700">{{ number_format($stats['error']) }}</div>
            <div class="text-xs text-red-600 font-mono mt-1">ERROR</div>
        </div>
        <div class="bg-red-100 rounded-xl border border-red-300 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-red-900">{{ number_format($stats['critical']) }}</div>
            <div class="text-xs text-red-700 font-mono mt-1">CRITICAL</div>
        </div>
        <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-gray-600">{{ number_format($stats['debug']) }}</div>
            <div class="text-xs text-gray-500 font-mono mt-1">DEBUG</div>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-200 p-4 text-center">
            <div class="text-2xl font-bold font-mono text-amber-700">{{ number_format($stats['hoje']) }}</div>
            <div class="text-xs text-amber-600 font-mono mt-1">HOJE</div>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" class="bg-white rounded-xl border border-gray-200 p-4 mb-5">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            @foreach($filters as $filter)
            <div>
                <label class="block text-xs font-mono text-gray-500 mb-1">{{ $filter['label'] }}</label>
                @if($filter['type'] === 'select')
                <select name="{{ $filter['name'] }}" class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-[#385776] focus:ring-[#385776]">
                    <option value="">Todos</option>
                    @foreach($filter['options'] as $val => $label)
                    <option value="{{ $val }}" {{ request($filter['name']) == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @elseif($filter['type'] === 'date')
                <input type="date" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-[#385776] focus:ring-[#385776]">
                @else
                <input type="text" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" placeholder="{{ $filter['placeholder'] ?? '' }}" class="w-full rounded-lg border-gray-300 text-sm font-mono focus:border-[#385776] focus:ring-[#385776]">
                @endif
            </div>
            @endforeach
        </div>
        <div class="flex justify-end gap-2 mt-4">
            <a href="{{ route('relatorios.sistema.laravel-log') }}" class="px-4 py-2 text-sm font-mono text-gray-600 hover:text-gray-800">Limpar</a>
            <button type="submit" class="px-4 py-2 bg-[#385776] text-white text-sm font-mono font-bold rounded-lg hover:bg-[#1B334A] transition-colors">FILTRAR</button>
        </div>
    </form>

    {{-- Pagination Info --}}
    <div class="flex items-center justify-between mb-3">
        <div class="text-xs text-gray-500 font-mono">
            @if($pagination['total'] > 0)
                Registros {{ (($pagination['page'] - 1) * $pagination['per_page']) + 1 }}-{{ min($pagination['page'] * $pagination['per_page'], $pagination['total']) }} de {{ number_format($pagination['total']) }}
            @else
                Nenhum registro encontrado
            @endif
        </div>
        <div class="flex items-center gap-2 text-xs font-mono">
            <span class="text-gray-500">POR PAG:</span>
            @foreach([25, 50, 100] as $pp)
            <a href="{{ request()->fullUrlWithQuery(['per_page' => $pp, 'page' => 1]) }}"
               class="px-2 py-1 rounded {{ (request('per_page', 50) == $pp) ? 'bg-[#385776] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $pp }}</a>
            @endforeach
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-300 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full report-table">
                <thead>
                    <tr>
                        @foreach($columns as $col)
                        <th class="whitespace-nowrap">
                            @if(isset($col['sortable']) && $col['sortable'])
                            <a href="{{ request()->fullUrlWithQuery(['sort' => $col['key'], 'dir' => (request('sort') === $col['key'] && request('dir') === 'asc') ? 'desc' : 'asc']) }}" class="flex items-center gap-1 text-white hover:text-gray-300">
                                {{ $col['label'] }}
                                @if(request('sort') === $col['key'])
                                <svg class="w-3 h-3 {{ request('dir') === 'desc' ? 'rotate-180' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                                @endif
                            </a>
                            @else
                            {{ $col['label'] }}
                            @endif
                        </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $row)
                    <tr>
                        @foreach($columns as $col)
                        <td>
                            @php $val = $row[$col['key']] ?? ''; @endphp
                            @if($col['format'] === 'badge')
                                @php $badgeColor = $col['badge_colors'][$val] ?? 'bg-gray-100 text-gray-600'; @endphp
                                <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold {{ $badgeColor }}">{{ $val }}</span>
                            @elseif($col['key'] === 'message')
                                <div class="log-message" title="{{ $val }}">{{ \Illuminate\Support\Str::limit($val, $col['limit'] ?? 120) }}</div>
                            @else
                                {{ \Illuminate\Support\Str::limit($val, $col['limit'] ?? 999) }}
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @empty
                    <tr>
                        <td colspan="{{ count($columns) }}" class="text-center py-8 text-gray-400 font-mono">Nenhum registro encontrado</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Pagination --}}
    @if($pagination['last_page'] > 1)
    <div class="flex justify-center gap-1 mt-4">
        @if($pagination['page'] > 1)
        <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['page'] - 1]) }}" class="px-3 py-1 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 text-sm font-mono">&laquo;</a>
        @endif
        
        @for($p = max(1, $pagination['page'] - 2); $p <= min($pagination['last_page'], $pagination['page'] + 2); $p++)
        <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}" class="px-3 py-1 rounded text-sm font-mono {{ $p == $pagination['page'] ? 'bg-[#385776] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' }}">{{ $p }}</a>
        @endfor
        
        @if($pagination['page'] < $pagination['last_page'])
        <a href="{{ request()->fullUrlWithQuery(['page' => $pagination['page'] + 1]) }}" class="px-3 py-1 rounded bg-gray-100 text-gray-600 hover:bg-gray-200 text-sm font-mono">&raquo;</a>
        @endif
    </div>
    @endif

</div>

{{-- Modal Legenda --}}
<div id="legenda-modal" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onclick="if(event.target === this) this.classList.add('hidden')">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
        <div class="bg-gradient-to-r from-[#385776] to-[#1B334A] px-6 py-4 flex items-center justify-between">
            <h2 class="text-lg font-bold text-white font-mono">Legenda de Eventos do Log</h2>
            <button onclick="document.getElementById('legenda-modal').classList.add('hidden')" class="text-white/70 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-6 overflow-y-auto max-h-[70vh]">
            
            {{-- Níveis de Log --}}
            <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
                Níveis de Log
            </h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-blue-100 text-blue-700 mb-2">INFO</span>
                    <p class="text-xs text-gray-600">Operação normal. Apenas registro informativo.</p>
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-yellow-100 text-yellow-700 mb-2">WARNING</span>
                    <p class="text-xs text-gray-600">Algo inesperado, mas não crítico. Monitorar.</p>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-3">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-red-100 text-red-700 mb-2">ERROR</span>
                    <p class="text-xs text-gray-600">Erro que precisa atenção. Funcionalidade afetada.</p>
                </div>
                <div class="bg-red-100 border border-red-300 rounded-lg p-3">
                    <span class="inline-flex px-2 py-0.5 rounded text-xs font-bold bg-red-300 text-red-900 mb-2">CRITICAL</span>
                    <p class="text-xs text-gray-600">Erro grave. Pode afetar operação do sistema.</p>
                </div>
            </div>

            {{-- Eventos Comuns --}}
            <h3 class="font-bold text-gray-800 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                Eventos Comuns
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="text-left px-3 py-2 font-mono text-xs text-gray-600">EVENTO</th>
                            <th class="text-left px-3 py-2 font-mono text-xs text-gray-600">MÓDULO</th>
                            <th class="text-left px-3 py-2 font-mono text-xs text-gray-600">SIGNIFICADO</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">Webhook SendPulse recebido</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-purple-100 text-purple-700">SendPulse</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">WhatsApp recebeu mensagem de cliente</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">=== INICIO processLead ===</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-cyan-100 text-cyan-700">DataJuri</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Sistema começou a processar um lead do webhook</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">processLead: BLOQUEADO - telefone pertence a cliente</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-cyan-100 text-cyan-700">DataJuri</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Lead ignorado porque já é cliente cadastrado ✓</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">processLead: contato sem tag de area juridica</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-cyan-100 text-cyan-700">DataJuri</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Contato não completou fluxo do bot (sem área definida)</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">NEXO-AUDIT: parsed webhook</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">NEXO</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Mensagem de WhatsApp parseada com sucesso</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">Bot control: automacao pausada</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">NEXO</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Atendente humano assumiu a conversa</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">Bot control: conversa assumida manualmente</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">NEXO</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Advogado(a) pegou o atendimento</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">NEXO-SYNC: dedup humana local</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">NEXO</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Mensagem duplicada ignorada (proteção ativa)</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">nexo:close-abandoned — execução concluída</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-green-100 text-green-700">NEXO</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">Cron que fecha conversas abandonadas executou</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">[SIATE] Triagem IA chamado</td>
                            <td class="px-3 py-2"><span class="px-2 py-0.5 rounded text-xs font-bold bg-orange-100 text-orange-700">SIATE</span></td>
                            <td class="px-3 py-2 text-xs text-gray-600">IA classificou chamado (SLA, complexidade, responsável)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Alertas Comuns --}}
            <h3 class="font-bold text-gray-800 mt-6 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Alertas (WARNING) — Requerem Atenção
            </h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-yellow-50">
                            <th class="text-left px-3 py-2 font-mono text-xs text-yellow-700">EVENTO</th>
                            <th class="text-left px-3 py-2 font-mono text-xs text-yellow-700">CAUSA PROVÁVEL</th>
                            <th class="text-left px-3 py-2 font-mono text-xs text-yellow-700">AÇÃO</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-yellow-200">
                        <tr class="bg-yellow-50/50">
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">DataJuriService: falha ao inicializar token OAuth</td>
                            <td class="px-3 py-2 text-xs text-gray-600">Servidor DataJuri retornou erro 500</td>
                            <td class="px-3 py-2 text-xs text-gray-600">Verificar status do DataJuri. Se persistir, contatar suporte.</td>
                        </tr>
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-gray-800">SendPulse WA: POST .../tags/set falhou</td>
                            <td class="px-3 py-2 text-xs text-gray-600">Contato não existe mais no SendPulse (deletado)</td>
                            <td class="px-3 py-2 text-xs text-gray-600">Normal se contato foi removido. Monitorar frequência.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Módulos --}}
            <h3 class="font-bold text-gray-800 mt-6 mb-3 flex items-center gap-2">
                <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                Módulos
            </h3>
            <div class="flex flex-wrap gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-700">NEXO — WhatsApp/Atendimento</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-purple-100 text-purple-700">SendPulse — API WhatsApp</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-cyan-100 text-cyan-700">DataJuri — Gestão Jurídica</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-violet-100 text-violet-700">CRM — Clientes/Pipeline</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-rose-100 text-rose-700">GDP — Desempenho</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-indigo-100 text-indigo-700">Justus — Jurisprudência</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700">Evidentia — Embeddings</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-teal-100 text-teal-700">Vigília — Compromissos</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-orange-100 text-orange-700">SIATE — Chamados</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-blue-100 text-blue-700">Sync — Sincronização</span>
                <span class="px-3 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-700">Sistema — Geral</span>
            </div>

        </div>
    </div>
</div>
@endsection
