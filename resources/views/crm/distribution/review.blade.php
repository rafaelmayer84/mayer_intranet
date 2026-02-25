@extends('layouts.app')
@section('title', 'CRM - Revisar Distribuição')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.distribution') }}" class="hover:text-[#385776]">Distribuição</a>
        <span>›</span>
        <span class="text-gray-700">Proposta #{{ $proposal->id }}</span>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Revisar Distribuição #{{ $proposal->id }}</h1>
            <p class="text-sm text-gray-500 mt-1">Gerada em {{ $proposal->created_at->format('d/m/Y H:i') }} · {{ count($proposal->assignments) }} clientes</p>
        </div>
        <span class="text-sm px-3 py-1 rounded-full {{ $proposal->status === 'applied' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($proposal->status) }}</span>
    </div>

    @if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{{ session('error') }}</div>@endif

    {{-- Resumo por responsável --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        @foreach($proposal->summary ?? [] as $s)
        <div class="bg-white rounded-lg shadow-sm border p-4 text-center">
            <p class="font-semibold text-[#1B334A]">{{ $s['name'] }}</p>
            <p class="text-2xl font-bold text-[#385776] mt-1">{{ $s['qty'] }}</p>
            <p class="text-xs text-gray-400">de {{ $s['max'] }} máx</p>
        </div>
        @endforeach
    </div>

    {{-- Raciocínio da IA --}}
    @if($proposal->ai_reasoning)
    <details class="mb-6 bg-blue-50 border border-blue-200 rounded-lg">
        <summary class="px-4 py-3 text-sm font-medium text-blue-700 cursor-pointer">🤖 Raciocínio da IA</summary>
        <div class="px-4 pb-4 text-sm text-blue-800 whitespace-pre-wrap">{{ is_string($proposal->ai_reasoning) ? $proposal->ai_reasoning : json_encode($proposal->ai_reasoning, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</div>
    </details>
    @endif

    {{-- Tabela de assignments com override --}}
    <form action="{{ route('crm.distribution.apply', $proposal->id) }}" method="POST">
        @csrf
        <div class="bg-white rounded-lg shadow-sm border overflow-x-auto mb-6">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 text-xs text-gray-500 border-b">
                        <th class="px-4 py-3 text-left">Cliente</th>
                        <th class="px-3 py-3 text-center">Score</th>
                        <th class="px-3 py-3 text-left">Sugestão IA</th>
                        <th class="px-3 py-3 text-left">Motivo</th>
                        @if($proposal->status === 'pending')
                        <th class="px-3 py-3 text-left">Corrigir →</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach(collect($proposal->assignments)->sortByDesc('score') as $a)
                    <tr class="border-b hover:bg-gray-50 {{ ($a['overridden'] ?? false) ? 'bg-yellow-50' : '' }}">
                        <td class="px-4 py-2">
                            <a href="{{ route('crm.accounts.show', $a['account_id']) }}" class="text-[#385776] hover:underline font-medium">
                                {{ $accountNames[$a['account_id']] ?? '#'.$a['account_id'] }}
                            </a>
                        </td>
                        <td class="px-3 py-2 text-center">
                            <span class="text-xs px-1.5 py-0.5 rounded {{ $a['score'] >= 80 ? 'bg-green-100 text-green-700' : ($a['score'] >= 50 ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">{{ $a['score'] }}</span>
                        </td>
                        <td class="px-3 py-2 font-medium">{{ $userNames[$a['suggested_owner_id']] ?? '?' }}</td>
                        <td class="px-3 py-2 text-gray-500 text-xs max-w-xs truncate" title="{{ $a['reason'] }}">{{ $a['reason'] }}</td>
                        @if($proposal->status === 'pending')
                        <td class="px-3 py-2">
                            <select name="overrides[{{ $a['account_id'] }}]" class="text-xs border rounded px-2 py-1">
                                <option value="keep">— manter —</option>
                                @foreach($profiles as $p)
                                <option value="{{ $p->user_id }}" {{ ($a['overridden'] ?? false) && ($a['suggested_owner_id'] == $p->user_id) ? 'selected' : '' }}>{{ $p->user->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($proposal->status === 'pending')
        <div class="flex items-center justify-between">
            <p class="text-sm text-gray-500">Revise e corrija pontualmente. Ao aplicar, todos os clientes serão atribuídos.</p>
            <button type="submit" class="px-6 py-2.5 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 font-medium"
                    onclick="return confirm('Aplicar esta distribuição? Todos os 243 clientes ativos serão reatribuídos.')">
                ✅ Aplicar Distribuição
            </button>
        </div>
        @endif
    </form>
</div>
@endsection
