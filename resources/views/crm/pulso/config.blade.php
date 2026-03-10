@extends('layouts.app')
@section('title', 'Pulso - Configurações')

@section('content')
<div class="max-w-3xl mx-auto px-6 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-[#1B334A]">Thresholds do Pulso</h1>
        <a href="{{ route('crm.pulso') }}" class="px-4 py-2 bg-white border rounded-lg text-sm hover:bg-gray-50">← Voltar ao Pulso</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg mb-6">{{ session('success') }}</div>
    @endif

    <div class="bg-white rounded-lg shadow-sm border p-6">
        <form method="POST" action="{{ route('crm.pulso.config.save') }}">
            @csrf
            <div class="space-y-6">
                @foreach($configs as $cfg)
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $cfg->chave }}</label>
                    <div class="flex gap-4 items-start">
                        <input type="number" name="config[{{ $cfg->id }}]" value="{{ $cfg->valor }}" class="w-32 border rounded-lg px-3 py-2 text-sm" min="1">
                        <p class="text-xs text-gray-500 flex-1 pt-2">{{ $cfg->descricao }}</p>
                    </div>
                    @if($cfg->updated_at)
                        <p class="text-xs text-gray-400 mt-1">Atualizado em: {{ \Carbon\Carbon::parse($cfg->updated_at)->format('d/m/Y H:i') }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            <div class="mt-6 pt-4 border-t">
                <button type="submit" class="px-6 py-2.5 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#2a4460]">Salvar Thresholds</button>
            </div>
        </form>
    </div>
</div>
@endsection
