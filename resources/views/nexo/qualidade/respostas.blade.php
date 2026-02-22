@extends('layouts.app')

@section('title', 'Respostas — ' . $campaign->name)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-6">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('nexo.qualidade.index') }}" class="text-gray-400 hover:text-gray-600 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ $campaign->name }}</h1>
            <p class="text-sm text-gray-500">Respostas recebidas &middot; {{ $respostas->total() }} registros</p>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left">Data</th>
                        <th class="px-4 py-3 text-left">Advogado</th>
                        <th class="px-4 py-3 text-center">Nota (1-5)</th>
                        <th class="px-4 py-3 text-center">NPS (0-10)</th>
                        <th class="px-4 py-3 text-left">Comentário</th>
                        @if($canViewIdentity)
                        <th class="px-4 py-3 text-left">Telefone</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($respostas as $r)
                    <tr class="hover:bg-gray-50 transition">
                        <td class="px-4 py-3 text-xs text-gray-500">{{ \Carbon\Carbon::parse($r->answered_at)->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $r->advogado_nome ?? '—' }}</td>
                        <td class="px-4 py-3 text-center">
                            @if($r->score_1_5)
                            <span class="inline-flex items-center justify-center w-7 h-7 rounded-full text-xs font-bold
                                {{ $r->score_1_5 >= 4 ? 'bg-green-100 text-green-700' : ($r->score_1_5 >= 3 ? 'bg-yellow-100 text-yellow-700' : 'bg-red-100 text-red-700') }}">
                                {{ $r->score_1_5 }}
                            </span>
                            @else — @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($r->nps !== null)
                            <span class="text-xs font-medium {{ $r->nps >= 9 ? 'text-green-600' : ($r->nps >= 7 ? 'text-gray-500' : 'text-red-600') }}">{{ $r->nps }}</span>
                            @else — @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600 max-w-xs truncate" title="{{ $r->free_text }}">
                            @if($r->free_text)
                                @php
                                    try { $txt = \Illuminate\Support\Facades\Crypt::decryptString($r->free_text); } catch(\Exception $e) { $txt = '[criptografado]'; }
                                @endphp
                                {{ \Illuminate\Support\Str::limit($txt, 80) }}
                            @else — @endif
                        </td>
                        @if($canViewIdentity)
                        <td class="px-4 py-3 font-mono text-xs text-gray-400">{{ $r->phone_e164 }}</td>
                        @endif
                    </tr>
                    @empty
                    <tr><td colspan="{{ $canViewIdentity ? 6 : 5 }}" class="px-4 py-8 text-center text-gray-400">Nenhuma resposta recebida.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $respostas->withQueryString()->links() }}</div>
</div>
@endsection
