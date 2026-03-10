@extends('layouts.app')
@section('title', 'Pulso - Upload Ligações')

@section('content')
<div class="max-w-4xl mx-auto px-6 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[#1B334A]">Upload de Relatório de Ligações</h1>
        <a href="{{ route('crm.pulso') }}" class="px-4 py-2 bg-white border rounded-lg text-sm hover:bg-gray-50">← Voltar ao Pulso</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border p-6 mb-6">
        <h2 class="text-lg font-semibold text-[#1B334A] mb-2">Enviar arquivo</h2>
        <p class="text-sm text-gray-500 mb-4">Formato aceito: CSV do VoIP do Brasil (separador ponto-e-vírgula, sem cabeçalho). Apenas ligações com status "Atendido" serão contabilizadas. Números fixos comerciais são filtrados automaticamente.</p>

        <form method="POST" action="{{ route('crm.pulso.upload.process') }}" enctype="multipart/form-data" class="flex items-end gap-4">
            @csrf
            <div class="flex-1">
                <label class="block text-sm font-medium text-gray-700 mb-1">Arquivo CSV</label>
                <input type="file" name="arquivo" accept=".csv,.txt,.xlsx" required class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="px-6 py-2.5 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#2a4460]">Processar Upload</button>
        </form>
    </div>

    {{-- Histórico de uploads --}}
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50"><h3 class="text-sm font-semibold text-gray-600">Últimos uploads</h3></div>
        <table class="w-full text-sm">
            <thead class="text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">Data upload</th>
                    <th class="px-4 py-2 text-left">Arquivo</th>
                    <th class="px-4 py-2 text-center">Processados</th>
                    <th class="px-4 py-2 text-center">Ignorados</th>
                    <th class="px-4 py-2 text-left">Período</th>
                    <th class="px-4 py-2 text-left">Por</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($uploads as $up)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-2 text-xs">{{ $up->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-4 py-2 text-xs">{{ $up->filename }}</td>
                    <td class="px-4 py-2 text-center text-green-600 font-medium">{{ $up->registros_processados }}</td>
                    <td class="px-4 py-2 text-center text-gray-400">{{ $up->registros_ignorados }}</td>
                    <td class="px-4 py-2 text-xs">{{ $up->periodo_inicio?->format('d/m') }} — {{ $up->periodo_fim?->format('d/m') }}</td>
                    <td class="px-4 py-2 text-xs">{{ $up->user->name ?? '—' }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-4 text-center text-gray-400">Nenhum upload realizado.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
