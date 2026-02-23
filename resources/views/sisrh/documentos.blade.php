@extends('layouts.app')
@section('title', 'Documentos — ' . $adv->name)
@section('content')
<div class="max-w-6xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Documentos</h1>
            <p class="text-sm text-gray-500">{{ $adv->name }} — {{ $adv->role }}</p>
        </div>
        <a href="{{ route('sisrh.advogados') }}" class="text-sm underline" style="color: #385776;">← Voltar</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded px-4 py-2 mb-4 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Upload --}}
    @if(in_array(Auth::user()->role, ['admin', 'coordenador']))
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3 uppercase">Enviar Documento</h2>
        <form action="{{ route('sisrh.documento.upload', $adv->id) }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="grid grid-cols-4 gap-3 items-end">
                <div>
                    <label class="text-xs text-gray-500">Arquivo PDF *</label>
                    <input type="file" name="arquivo" accept=".pdf" class="border rounded px-2 py-1.5 text-sm w-full" required>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Categoria *</label>
                    <select name="categoria" class="border rounded px-2 py-1.5 text-sm w-full" required>
                        @foreach($categorias as $k => $v)
                        <option value="{{ $k }}">{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Descrição</label>
                    <input type="text" name="descricao" maxlength="500" class="border rounded px-2 py-1.5 text-sm w-full" placeholder="Opcional">
                </div>
                <div>
                    <button type="submit" class="px-4 py-1.5 rounded text-white text-sm w-full" style="background-color: #385776;">📄 Enviar</button>
                </div>
            </div>
            <p class="text-xs text-gray-400 mt-2">Apenas PDF, máximo 10 MB.</p>
        </form>
    </div>
    @endif

    {{-- Filtro --}}
    <form method="GET" class="flex items-center gap-3 mb-4">
        <select name="categoria" class="border rounded px-2 py-1.5 text-sm">
            <option value="">Todas as categorias</option>
            @foreach($categorias as $k => $v)
            <option value="{{ $k }}" {{ request('categoria')==$k?'selected':'' }}>{{ $v }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-3 py-1.5 rounded text-white text-xs" style="background-color: #385776;">Filtrar</button>
        @if(request('categoria'))
        <a href="{{ route('sisrh.documentos', $adv->id) }}" class="text-xs text-gray-500 underline">Limpar</a>
        @endif
        <span class="ml-auto text-xs text-gray-400">{{ $documentos->count() }} documento(s)</span>
    </form>

    {{-- Lista --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr style="background-color: #385776;">
                    <th class="px-3 py-2 text-left text-white">Documento</th>
                    <th class="px-3 py-2 text-center text-white">Categoria</th>
                    <th class="px-3 py-2 text-left text-white">Descrição</th>
                    <th class="px-3 py-2 text-center text-white">Tamanho</th>
                    <th class="px-3 py-2 text-center text-white">Data</th>
                    <th class="px-3 py-2 text-center text-white">Ações</th>
                </tr>
            </thead>
            <tbody>
                @forelse($documentos as $doc)
                <tr class="border-b border-gray-100 hover:bg-gray-50">
                    <td class="px-3 py-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"/></svg>
                            <span class="text-gray-800 truncate max-w-xs" title="{{ $doc->nome_original }}">{{ $doc->nome_original }}</span>
                        </div>
                    </td>
                    <td class="px-3 py-2 text-center">
                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $categorias[$doc->categoria] ?? $doc->categoria }}</span>
                    </td>
                    <td class="px-3 py-2 text-gray-500 text-xs truncate max-w-xs" title="{{ $doc->descricao }}">{{ $doc->descricao ?? '—' }}</td>
                    <td class="px-3 py-2 text-center text-gray-500 text-xs">{{ number_format($doc->tamanho / 1024, 0) }} KB</td>
                    <td class="px-3 py-2 text-center text-gray-500 text-xs">{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-2 text-center">
                        <div class="flex gap-1 justify-center">
                            <a href="{{ route('sisrh.documento.visualizar', $doc->id) }}" target="_blank" class="px-2 py-1 rounded text-xs border" style="color: #385776; border-color: #385776;" title="Visualizar">👁️</a>
                            <a href="{{ route('sisrh.documento.download', $doc->id) }}" class="px-2 py-1 rounded text-xs border text-green-600 border-green-300" title="Download">⬇️</a>
                            @if(in_array(Auth::user()->role, ['admin', 'coordenador']))
                            <form action="{{ route('sisrh.documento.excluir', $doc->id) }}" method="POST" class="inline" onsubmit="return confirm('Excluir documento?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="px-2 py-1 rounded text-xs text-red-600 border border-red-300" title="Excluir">🗑️</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-3 py-8 text-center text-gray-400">
                        <p class="text-lg mb-1">📂</p>
                        <p>Nenhum documento cadastrado.</p>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
