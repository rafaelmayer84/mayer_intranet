@extends('layouts.app')

@section('title', 'Alvos — ' . $campaign->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('nexo.qualidade.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $campaign->name }}</h1>
            <p class="text-sm text-gray-500">Alvos sorteados &middot; {{ $targets->total() }} registros</p>
        </div>
    </div>

    {{-- Filtros --}}
    <form class="flex gap-2 mb-4">
        @foreach(['PENDING','SENT','FAILED','SKIPPED'] as $st)
        <a href="{{ route('nexo.qualidade.targets', ['campaign' => $campaign->id, 'status' => $st]) }}"
           class="text-xs px-3 py-1.5 rounded-full border transition {{ request('status') === $st ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
            {{ $st }}
        </a>
        @endforeach
        <a href="{{ route('nexo.qualidade.targets', $campaign->id) }}"
           class="text-xs px-3 py-1.5 rounded-full border transition {{ !request('status') ? 'bg-gray-800 text-white border-gray-800' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
            TODOS
        </a>
    </form>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">ID</th>
                        <th class="px-4 py-3 text-left">Telefone</th>
                        <th class="px-4 py-3 text-left">Origem</th>
                        <th class="px-4 py-3 text-left">Responsável</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Sorteado em</th>
                        <th class="px-4 py-3 text-left">Motivo Skip</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($targets as $t)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-gray-600">{{ $t->id }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $t->masked_phone }}</td>
                        <td class="px-4 py-3">
                            <span class="text-[10px] px-1.5 py-0.5 rounded {{ $t->source_type === 'DATAJURI' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600' }}">{{ $t->source_type }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $t->responsibleUser->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @php
                                $colors = ['PENDING' => 'bg-yellow-50 text-yellow-700', 'SENT' => 'bg-green-50 text-green-700', 'FAILED' => 'bg-red-50 text-red-700', 'SKIPPED' => 'bg-gray-100 text-gray-500'];
                            @endphp
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-medium {{ $colors[$t->send_status] ?? '' }}">{{ $t->send_status }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $t->sampled_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-xs text-gray-400">{{ $t->skip_reason ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">Nenhum alvo sorteado nesta campanha.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $targets->withQueryString()->links() }}</div>
</div>
@endsection
