@extends('layouts.app')
@section('title', 'Lançamentos do Holerite')
@section('content')
<div class="max-w-6xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Lançamentos Manuais</h1>
        <a href="{{ route('sisrh.folha') }}?ano={{ $ano }}&mes={{ $mes }}" class="text-sm underline" style="color: #385776;">← Voltar à Folha</a>
    </div>
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded px-4 py-2 mb-4 text-sm">{{ session('success') }}</div>
    @endif
    <form method="GET" class="flex items-center gap-2 mb-6">
        <label class="text-sm text-gray-600">Competência:</label>
        <select name="mes" class="border rounded px-2 py-1.5 text-sm">@for($m=1;$m<=12;$m++)<option value="{{ $m }}" {{ $mes==$m?'selected':'' }}>{{ str_pad($m,2,'0',STR_PAD_LEFT) }}</option>@endfor</select>
        <input type="number" name="ano" value="{{ $ano }}" min="2024" max="2030" class="border rounded px-2 py-1.5 text-sm w-20">
        <button type="submit" class="px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Filtrar</button>
    </form>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 uppercase">Novo Lançamento</h2>
        <form action="{{ route('sisrh.lancamento.salvar') }}" method="POST">
            @csrf
            <input type="hidden" name="ano" value="{{ $ano }}"><input type="hidden" name="mes" value="{{ $mes }}">
            <div class="grid grid-cols-6 gap-3 items-end">
                <div><label class="text-xs text-gray-500">Advogado</label><select name="user_id" class="border rounded px-2 py-1.5 text-sm w-full" required><option value="">Selecione...</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
                <div><label class="text-xs text-gray-500">Rubrica</label><select name="rubrica_id" class="border rounded px-2 py-1.5 text-sm w-full" required><option value="">Selecione...</option>@foreach($rubricas as $r)<option value="{{ $r->id }}">[{{ $r->tipo=='provento'?'P':'D' }}] {{ $r->codigo }} - {{ $r->nome }}</option>@endforeach</select></div>
                <div><label class="text-xs text-gray-500">Valor (R$)</label><input type="number" name="valor" step="0.01" min="0" class="border rounded px-2 py-1.5 text-sm w-full" required></div>
                <div><label class="text-xs text-gray-500">Referência</label><input type="text" name="referencia" maxlength="20" class="border rounded px-2 py-1.5 text-sm w-full" placeholder="Ex: Jul, 900"></div>
                <div><label class="text-xs text-gray-500">Observação</label><input type="text" name="observacao" maxlength="255" class="border rounded px-2 py-1.5 text-sm w-full" placeholder="Opcional"></div>
                <div><button type="submit" class="px-4 py-1.5 rounded text-white text-sm w-full" style="background-color: #385776;">Lançar</button></div>
            </div>
        </form>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr style="background-color: #385776;"><th class="px-3 py-2 text-left text-white">Advogado</th><th class="px-3 py-2 text-left text-white">Rubrica</th><th class="px-3 py-2 text-center text-white">Tipo</th><th class="px-3 py-2 text-center text-white">Ref</th><th class="px-3 py-2 text-right text-white">Valor</th><th class="px-3 py-2 text-left text-white">Obs</th><th class="px-3 py-2 text-center text-white">Ação</th></tr></thead>
            <tbody>
                @forelse($lancamentos as $l)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-3 py-2">{{ $l->user->name ?? '—' }}</td>
                    <td class="px-3 py-2">{{ $l->rubrica->codigo ?? '' }} - {{ $l->rubrica->nome ?? '' }}</td>
                    <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs font-medium {{ $l->rubrica->tipo=='provento'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' }}">{{ $l->rubrica->tipo=='provento'?'Provento':'Desconto' }}</span></td>
                    <td class="px-3 py-2 text-center text-gray-500">{{ $l->referencia }}</td>
                    <td class="px-3 py-2 text-right font-medium">R$ {{ number_format($l->valor,2,',','.') }}</td>
                    <td class="px-3 py-2 text-gray-500 text-xs">{{ $l->observacao }}</td>
                    <td class="px-3 py-2 text-center"><form action="{{ route('sisrh.lancamento.excluir', $l->id) }}" method="POST" class="inline" onsubmit="return confirm('Excluir?')">@csrf @method('DELETE')<button type="submit" class="text-red-600 text-xs underline">Excluir</button></form></td>
                </tr>
                @empty
                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Nenhum lançamento manual para {{ str_pad($mes,2,'0',STR_PAD_LEFT) }}/{{ $ano }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
