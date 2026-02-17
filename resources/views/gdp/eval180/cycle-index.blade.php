@extends('layouts.app')

@section('title', 'Avaliação 180° — Equipe')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Avaliação 180° — Equipe</h1>
            <p class="text-sm text-gray-500 mt-1">Ciclo {{ $ciclo->nome }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('gdp.eval180.report', $ciclo->id) }}" class="px-4 py-2 border border-gray-300 rounded-lg text-sm hover:bg-gray-50 transition">
                📊 Relatório
            </a>
            <a href="{{ route('gdp.equipe') }}" class="btn-mayer px-4 py-2 rounded-lg text-sm text-white">
                ← Voltar ao GDP
            </a>
        </div>
    </div>

    {{-- Ações do gestor --}}
    <div class="bg-white rounded-xl shadow-sm border p-4 mb-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-700">Criar avaliação avulsa</h3>
            <div class="flex gap-3 items-end">
                <div>
                    <label class="text-xs text-gray-500">Profissional</label>
                    <select id="newEvalUser" class="border rounded px-2 py-1.5 text-sm w-48">
                        <option value="">Selecione...</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-500">Período</label>
                    <input type="month" id="newEvalPeriod" class="border rounded px-2 py-1.5 text-sm" value="{{ now()->format('Y-m') }}">
                </div>
                <button onclick="criarAvaliacao()" class="btn-mayer px-4 py-1.5 rounded-lg text-sm text-white">
                    + Criar Avaliação
                </button>
            </div>
        </div>
    </div>

    {{-- Legenda --}}
    <div class="flex gap-4 mb-4 text-xs text-gray-500">
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Enviado</span>
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-500"></span> Rascunho</span>
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-400"></span> Pendente</span>
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Travado</span>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gradient-to-r from-[#385776] to-[#1B334A] text-white">
                        <th class="px-4 py-3 text-left font-medium">Profissional</th>
                        <th class="px-4 py-3 text-left font-medium">Cargo</th>
                        @foreach($periods as $p)
                            <th class="px-3 py-3 text-center font-medium whitespace-nowrap">
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $p)->translatedFormat('M/y') }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-500 capitalize">{{ $user->role }}</td>
                            @foreach($periods as $p)
                                @php
                                    $key = $user->id . '_' . $p;
                                    $form = $forms->get($key)?->first();
                                    $selfResp = $form ? $form->responses->firstWhere('rater_type', 'self') : null;
                                    $mgrResp = $form ? $form->responses->firstWhere('rater_type', 'manager') : null;
                                @endphp
                                <td class="px-3 py-3 text-center">
                                    <a href="{{ route('gdp.eval180.manager.form', [$ciclo->id, $user->id, $p]) }}"
                                       class="inline-flex flex-col items-center gap-1 hover:opacity-80 transition">
                                        {{-- Semáforo: Auto / Gestor --}}
                                        <div class="flex gap-1">
                                            {{-- Auto --}}
                                            @if($selfResp && $selfResp->submitted_at)
                                                <span class="w-3 h-3 rounded-full bg-green-500" title="Auto: enviado"></span>
                                            @elseif($selfResp)
                                                <span class="w-3 h-3 rounded-full bg-yellow-500" title="Auto: rascunho"></span>
                                            @else
                                                <span class="w-3 h-3 rounded-full bg-gray-300" title="Auto: pendente"></span>
                                            @endif
                                            {{-- Gestor --}}
                                            @if($mgrResp && $mgrResp->submitted_at)
                                                <span class="w-3 h-3 rounded-full bg-green-500" title="Gestor: enviado"></span>
                                            @elseif($mgrResp)
                                                <span class="w-3 h-3 rounded-full bg-yellow-500" title="Gestor: rascunho"></span>
                                            @else
                                                <span class="w-3 h-3 rounded-full bg-gray-300" title="Gestor: pendente"></span>
                                            @endif
                                        </div>
                                        @if($form && $form->status === 'locked')
                                            <span class="text-[10px] text-red-500 font-medium">🔒</span>
                                        @endif
                                        @if($mgrResp && $mgrResp->total_score)
                                            <span class="text-xs font-bold {{ $mgrResp->total_score >= 3.0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ number_format($mgrResp->total_score, 1, ',', '') }}
                                            </span>
                                        @endif
                                    </a>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
function criarAvaliacao() {
    const userId = document.getElementById('newEvalUser').value;
    const period = document.getElementById('newEvalPeriod').value;
    
    if (!userId) { alert('Selecione um profissional.'); return; }
    if (!period) { alert('Selecione o período.'); return; }

    // Redirecionar para o form do gestor — getOrCreateForm cria automaticamente
    window.location.href = `/gdp/cycles/{{ $ciclo->id }}/eval180/${userId}/${period}`;
}
</script>
@endpush
@endsection
