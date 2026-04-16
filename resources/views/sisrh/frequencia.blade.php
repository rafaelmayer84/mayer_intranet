@extends('layouts.app')
@section('title', 'Folha de Frequência — SISRH')
@section('content')
<div class="w-full px-4 py-6" x-data="frequenciaApp()">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: #1B334A;">Folha de Frequência</h1>
            <p class="text-sm text-gray-500 mt-1">Baseada nos logins do DataJuri</p>
        </div>
        <div class="flex gap-2">
            @if(in_array(Auth::user()->role, ['admin']))
            <button @click="showImport = !showImport" class="px-4 py-2 text-sm rounded text-white" style="background-color: #385776;">
                📥 Importar Dados
            </button>
            @endif
            <a href="{{ route('sisrh.index') }}" class="text-sm underline pt-2" style="color: #385776;">← Voltar</a>
        </div>
    </div>

    {{-- Painel de importação --}}
    @if(in_array(Auth::user()->role, ['admin']))
    <div x-show="showImport" x-cloak class="bg-white rounded-lg shadow p-5 mb-6 border border-blue-200">
        <h2 class="font-semibold text-sm mb-3" style="color: #385776;">Importar Logins do DataJuri</h2>
        <p class="text-xs text-gray-500 mb-3">
            Cole os dados da tela de logins do DataJuri. O sistema reconhece automaticamente as colunas por tabulação.<br>
            Formato esperado: <code class="bg-gray-100 px-1 rounded">Email [TAB] Data [TAB] Hora [TAB] IP [TAB] URL [TAB] Status [TAB] Minutos [TAB] Último Acesso [TAB] Plataforma [TAB] Navegador</code>
        </p>
        <form action="{{ route('sisrh.frequencia.importar') }}" method="POST">
            @csrf
            <textarea name="dados_brutos" rows="10" class="w-full border rounded px-3 py-2 text-xs font-mono mb-3" placeholder="Cole aqui os dados copiados da tela do DataJuri...&#10;&#10;rafaelmayer@mayeradvogados.adv.br&#9;10/03/2026 08:19&#9;177.2.6.57&#9;https://dj21.datajuri.com.br/app/&#9;Êxito&#9;720&#9;10/03/2026 12:26&#9;Windows&#9;Firefox - 148.0" required></textarea>
            <div class="flex items-center gap-3">
                <button type="submit" class="px-5 py-2 rounded text-white text-sm font-medium" style="background-color: #385776;">Processar Importação</button>
                <button type="button" @click="showImport = false" class="px-4 py-2 rounded text-sm border text-gray-600">Cancelar</button>
            </div>
        </form>
    </div>
    @endif

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-700 rounded px-4 py-3 mb-4 text-sm">{{ session('error') }}</div>
    @endif
    @if(session('import_stats'))
    @php $stats = session('import_stats'); @endphp
    <div class="bg-blue-50 border border-blue-200 text-blue-700 rounded px-4 py-3 mb-4 text-sm">
        Importação concluída: <strong>{{ $stats['inseridos'] ?? 0 }}</strong> registros importados,
        <strong>{{ $stats['duplicados'] ?? 0 }}</strong> duplicados ignorados,
        <strong>{{ $stats['erros'] ?? 0 }}</strong> linhas com erro.
    </div>
    @endif

    {{-- Filtros --}}
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-6">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Semana de</label>
            <input type="date" name="data_inicio" value="{{ request('data_inicio', $dataInicio->toDateString()) }}" class="border rounded px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">até</label>
            <input type="date" name="data_fim" value="{{ request('data_fim', $dataFim->toDateString()) }}" class="border rounded px-3 py-1.5 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Colaborador</label>
            <select name="user_id" class="border rounded px-2 py-1.5 text-sm">
                <option value="">Todos</option>
                @foreach($usuarios as $u)
                <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="pt-5">
            <button type="submit" class="px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Filtrar</button>
        </div>
    </form>

    {{-- Resumo por colaborador --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        @foreach($resumo as $r)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-9 h-9 rounded-full flex items-center justify-center text-white text-xs font-bold" style="background-color: #385776;">
                    {{ strtoupper(substr($r['nome'], 0, 1)) }}{{ strtoupper(substr(strstr($r['nome'], ' ') ?: '', 1, 1)) }}
                </div>
                <p class="font-semibold text-sm text-gray-800">{{ $r['nome'] }}</p>
            </div>
            <div class="space-y-1 text-xs">
                <div class="flex justify-between">
                    <span class="text-gray-500">Dias com login</span>
                    <span class="font-medium text-gray-800">{{ $r['dias_com_login'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Primeiro login (média)</span>
                    <span class="font-medium text-gray-800">{{ $r['hora_media_entrada'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Último acesso (média)</span>
                    <span class="font-medium text-gray-800">{{ $r['hora_media_saida'] }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Logins com êxito</span>
                    <span class="font-medium text-green-700">{{ $r['logins_exito'] }}</span>
                </div>
                @if($r['logins_falha'] > 0)
                <div class="flex justify-between">
                    <span class="text-gray-500">Tentativas falhas</span>
                    <span class="font-medium text-red-600">{{ $r['logins_falha'] }}</span>
                </div>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- Tabela detalhada --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-xs">
            <thead>
                <tr style="background-color: #385776;">
                    <th class="px-3 py-2 text-left text-white">Colaborador</th>
                    <th class="px-3 py-2 text-center text-white">Data</th>
                    <th class="px-3 py-2 text-center text-white">Hora</th>
                    <th class="px-3 py-2 text-center text-white">IP</th>
                    <th class="px-3 py-2 text-center text-white">Status</th>
                    <th class="px-3 py-2 text-center text-white">Exp. (min)</th>
                    <th class="px-3 py-2 text-center text-white">Último Acesso</th>
                    <th class="px-3 py-2 text-center text-white">Plataforma</th>
                    <th class="px-3 py-2 text-center text-white">Navegador</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logins as $log)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-3 py-2 font-medium text-gray-800">{{ $log->user_name ?? $log->email_datajuri }}</td>
                    <td class="px-3 py-2 text-center text-gray-600">{{ \Carbon\Carbon::parse($log->data_login)->format('d/m/Y') }}</td>
                    <td class="px-3 py-2 text-center text-gray-600">{{ substr($log->hora_login, 0, 5) }}</td>
                    <td class="px-3 py-2 text-center text-gray-500">{{ $log->ip_origem ?: '—' }}</td>
                    <td class="px-3 py-2 text-center">
                        @if($log->status_login === 'Êxito')
                        <span class="px-2 py-0.5 rounded bg-green-100 text-green-700">{{ $log->status_login }}</span>
                        @elseif(str_contains($log->status_login, 'inválida'))
                        <span class="px-2 py-0.5 rounded bg-red-100 text-red-700">{{ $log->status_login }}</span>
                        @else
                        <span class="px-2 py-0.5 rounded bg-gray-100 text-gray-600">{{ $log->status_login }}</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center text-gray-500">{{ $log->minutos_expirar ?: '—' }}</td>
                    <td class="px-3 py-2 text-center text-gray-500">{{ $log->ultimo_acesso ? \Carbon\Carbon::parse($log->ultimo_acesso)->format('d/m H:i') : '—' }}</td>
                    <td class="px-3 py-2 text-center text-gray-500">{{ $log->plataforma ?: '—' }}</td>
                    <td class="px-3 py-2 text-center text-gray-500">{{ $log->navegador ?: '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-3 py-8 text-center text-gray-400">Nenhum registro de frequência para o período selecionado.</td></tr>
                @endforelse
            </tbody>
        </table>
        @if($logins instanceof \Illuminate\Pagination\LengthAwarePaginator && $logins->hasPages())
        <div class="px-4 py-3 border-t">{{ $logins->appends(request()->query())->links() }}</div>
        @endif
    </div>
</div>

@push('scripts')
<script>
function frequenciaApp() {
    return {
        showImport: false
    };
}
</script>
@endpush
@endsection
