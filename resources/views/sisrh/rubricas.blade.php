@extends('layouts.app')
@section('title', 'Rubricas do Holerite')
@section('content')
<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Rubricas do Holerite</h1>
        <a href="{{ route('sisrh.folha') }}" class="text-sm underline" style="color: #385776;">← Voltar à Folha</a>
    </div>
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded px-4 py-2 mb-4 text-sm">{{ session('success') }}</div>
    @endif
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden mb-6">
        <table class="w-full text-sm">
            <thead><tr style="background-color: #385776;"><th class="px-3 py-2 text-left text-white">Código</th><th class="px-3 py-2 text-left text-white">Nome</th><th class="px-3 py-2 text-center text-white">Tipo</th><th class="px-3 py-2 text-center text-white">Auto?</th><th class="px-3 py-2 text-center text-white">Ordem</th><th class="px-3 py-2 text-center text-white">Ativo</th><th class="px-3 py-2 text-center text-white">Ação</th></tr></thead>
            <tbody>
                @foreach($rubricas as $r)
                <tr class="border-b border-gray-100">
                    <form action="{{ route('sisrh.rubrica.atualizar', $r->id) }}" method="POST">@csrf @method('PUT')
                        <td class="px-3 py-2 font-mono text-gray-600">{{ $r->codigo }}</td>
                        <td class="px-3 py-2"><input type="text" name="nome" value="{{ $r->nome }}" class="border rounded px-2 py-1 text-sm w-full"></td>
                        <td class="px-3 py-2 text-center">
                            @if($r->automatica)<span class="px-2 py-0.5 rounded text-xs {{ $r->tipo=='provento'?'bg-green-100 text-green-700':'bg-red-100 text-red-700' }}">{{ $r->tipo }}</span><input type="hidden" name="tipo" value="{{ $r->tipo }}">
                            @else<select name="tipo" class="border rounded px-2 py-1 text-xs"><option value="provento" {{ $r->tipo=='provento'?'selected':'' }}>Provento</option><option value="desconto" {{ $r->tipo=='desconto'?'selected':'' }}>Desconto</option></select>@endif
                        </td>
                        <td class="px-3 py-2 text-center text-xs text-gray-500">{{ $r->automatica ? '✅ '.$r->formula : '—' }}</td>
                        <td class="px-3 py-2 text-center"><input type="number" name="ordem" value="{{ $r->ordem }}" class="border rounded px-2 py-1 text-sm w-14 text-center"></td>
                        <td class="px-3 py-2 text-center"><input type="checkbox" name="ativo" value="1" {{ $r->ativo?'checked':'' }}></td>
                        <td class="px-3 py-2 text-center"><button type="submit" class="px-2 py-1 rounded text-white text-xs" style="background-color: #385776;">Salvar</button></td>
                    </form>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 uppercase">Nova Rubrica Manual</h2>
        <form action="{{ route('sisrh.rubrica.salvar') }}" method="POST" class="grid grid-cols-5 gap-3 items-end">@csrf
            <div><label class="text-xs text-gray-500">Código</label><input type="text" name="codigo" maxlength="10" class="border rounded px-2 py-1.5 text-sm w-full" placeholder="Ex: 006" required></div>
            <div><label class="text-xs text-gray-500">Nome</label><input type="text" name="nome" maxlength="100" class="border rounded px-2 py-1.5 text-sm w-full" required></div>
            <div><label class="text-xs text-gray-500">Tipo</label><select name="tipo" class="border rounded px-2 py-1.5 text-sm w-full" required><option value="provento">Provento</option><option value="desconto">Desconto</option></select></div>
            <div><label class="text-xs text-gray-500">Ordem</label><input type="number" name="ordem" value="50" class="border rounded px-2 py-1.5 text-sm w-full"></div>
            <div><button type="submit" class="px-4 py-1.5 rounded text-white text-sm w-full" style="background-color: #385776;">Adicionar</button></div>
        </form>
    </div>
</div>
@endsection
