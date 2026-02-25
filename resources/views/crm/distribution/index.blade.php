@extends('layouts.app')
@section('title', 'CRM - Distribuição de Carteira')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Distribuição de Carteira</h1>
            <p class="text-sm text-gray-500 mt-1">Configure responsáveis e distribua clientes ativos via IA</p>
        </div>
        <form action="{{ route('crm.distribution.generate') }}" method="POST">
            @csrf
            <button type="submit" class="px-5 py-2.5 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] font-medium"
                    onclick="this.disabled=true; this.innerText='Gerando...'; this.form.submit();">
                🤖 Gerar Distribuição via IA
            </button>
        </form>
    </div>

    @if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{{ session('error') }}</div>@endif

    {{-- Perfis dos Responsáveis --}}
    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">👤 Responsáveis pela Carteira</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach($profiles as $p)
        <div class="bg-white rounded-lg shadow-sm border p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-[#1B334A]">{{ $p->user->name }}</h3>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $p->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $p->active ? 'Ativo' : 'Inativo' }}
                </span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Clientes atuais</span>
                    <span class="font-medium {{ $p->current_count >= $p->max_accounts ? 'text-red-600' : 'text-[#1B334A]' }}">{{ $p->current_count }} / {{ $p->max_accounts }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="h-full rounded-full {{ $p->current_count >= $p->max_accounts ? 'bg-red-500' : 'bg-[#385776]' }}" style="width: {{ min(100, round($p->current_count / max($p->max_accounts, 1) * 100)) }}%"></div>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Peso</span>
                    <span class="font-medium">{{ $p->priority_weight }}/10</span>
                </div>
                <div>
                    <span class="text-gray-500 text-xs block mb-1">Especialidades</span>
                    <div class="flex flex-wrap gap-1">
                        @foreach($p->specialties ?? [] as $spec)
                        <span class="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded">{{ $spec }}</span>
                        @endforeach
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ $p->description }}</p>
            </div>
            {{-- Form de edição rápida --}}
            <form action="{{ route('crm.distribution.update-profile', $p->id) }}" method="POST" class="mt-3 pt-3 border-t">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-gray-500">Max clientes</label>
                        <input type="number" name="max_accounts" value="{{ $p->max_accounts }}" class="w-full border rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Peso (1-10)</label>
                        <input type="number" name="priority_weight" value="{{ $p->priority_weight }}" min="1" max="10" class="w-full border rounded px-2 py-1 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-2 w-full text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-600">Salvar</button>
            </form>
        </div>
        @endforeach
    </div>

    {{-- Histórico de Propostas --}}
    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">📋 Histórico de Distribuições</h2>
    @if($proposals->isNotEmpty())
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-gray-500 text-xs border-b"><th class="px-4 py-3 text-left">Data</th><th class="px-3 py-3 text-center">Status</th><th class="px-3 py-3 text-center">Clientes</th><th class="px-3 py-3">Criado por</th><th class="px-3 py-3">Ação</th></tr></thead>
            <tbody>
                @foreach($proposals as $prop)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $prop->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-3 text-center">
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $prop->status === 'applied' ? 'bg-green-100 text-green-700' : ($prop->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">{{ ucfirst($prop->status) }}</span>
                    </td>
                    <td class="px-3 py-3 text-center">{{ count($prop->assignments ?? []) }}</td>
                    <td class="px-3 py-3 text-gray-600">{{ $prop->creator?->name ?? '—' }}</td>
                    <td class="px-3 py-3">
                        @if($prop->status === 'pending')
                        <a href="{{ route('crm.distribution.review', $prop->id) }}" class="text-xs text-[#385776] hover:underline font-medium">Revisar →</a>
                        @else
                        <a href="{{ route('crm.distribution.review', $prop->id) }}" class="text-xs text-gray-400 hover:underline">Ver</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="bg-white rounded-lg shadow-sm border p-6 text-gray-400 text-sm">Nenhuma distribuição gerada ainda. Clique em "Gerar Distribuição via IA".</div>
    @endif
</div>
@endsection
