@extends('layouts.app')

@section('title', 'Avaliação 180° — Minhas Avaliações')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Avaliação 180°</h1>
            <p class="text-sm text-gray-500 mt-1">
                @if($ciclo)
                    Ciclo {{ $ciclo->nome }} — {{ \Carbon\Carbon::parse($ciclo->data_inicio)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($ciclo->data_fim)->format('d/m/Y') }}
                @else
                    Nenhum ciclo ativo
                @endif
            </p>
        </div>
        <a href="{{ route('gdp.minha-performance') }}" class="btn-mayer px-4 py-2 rounded-lg text-sm text-white">
            ← Voltar ao GDP
        </a>
    </div>

    @if(!$ciclo)
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
            <p class="text-yellow-700">Nenhum ciclo GDP ativo. Entre em contato com o administrador.</p>
        </div>
    @else
        {{-- Cards por período --}}
        <div class="grid gap-4">
            @forelse($forms as $form)
                <div class="bg-white rounded-xl shadow-sm border p-5 hover:shadow-md transition">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800">
                                Período: {{ \Carbon\Carbon::createFromFormat('Y-m', $form->period)->translatedFormat('F/Y') }}
                            </h3>
                            <div class="flex gap-4 mt-2 text-sm">
                                {{-- Status autoavaliação --}}
                                @php
                                    $self = $form->responses->firstWhere('rater_type', 'self');
                                    $mgr  = $form->responses->firstWhere('rater_type', 'manager');
                                @endphp
                                <span class="inline-flex items-center gap-1">
                                    @if($self && $self->submitted_at)
                                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                        <span class="text-green-700">Autoavaliação enviada</span>
                                    @elseif($self)
                                        <span class="w-2 h-2 rounded-full bg-yellow-500"></span>
                                        <span class="text-yellow-700">Rascunho salvo</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                        <span class="text-gray-500">Pendente</span>
                                    @endif
                                </span>
                                {{-- Status gestor --}}
                                <span class="inline-flex items-center gap-1">
                                    @if($mgr && $mgr->submitted_at)
                                        <span class="w-2 h-2 rounded-full bg-green-500"></span>
                                        <span class="text-green-700">Gestor avaliou</span>
                                    @else
                                        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
                                        <span class="text-gray-500">Gestor pendente</span>
                                    @endif
                                </span>
                                {{-- Lock --}}
                                @if($form->status === 'locked')
                                    <span class="inline-flex items-center gap-1">
                                        <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                        <span class="text-red-600">Travado</span>
                                    </span>
                                @endif
                            </div>
                            @if($self && $self->total_score)
                                <p class="text-sm text-gray-500 mt-1">Sua nota: <strong>{{ number_format($self->total_score, 2, ',', '.') }}</strong></p>
                            @endif
                        </div>
                        <a href="{{ route('gdp.eval180.me.form', [$ciclo->id, $form->period]) }}"
                           class="btn-mayer px-4 py-2 rounded-lg text-sm text-white">
                            {{ $form->isLocked() ? 'Visualizar' : ($self && $self->submitted_at ? 'Visualizar' : 'Preencher') }}
                        </a>
                    </div>
                </div>
            @empty
            @endforelse

            {{-- Períodos disponíveis para iniciar avaliação --}}
            @php
                $inicio = \Carbon\Carbon::parse($ciclo->data_inicio)->startOfMonth();
                $fim = \Carbon\Carbon::parse($ciclo->data_fim)->endOfMonth();
                $hoje = \Carbon\Carbon::now();
                $existingPeriods = $forms->pluck('period')->toArray();
                $availablePeriods = [];
                $current = $inicio->copy();
                while ($current->lte($fim) && $current->lte($hoje)) {
                    $p = $current->format('Y-m');
                    if (!in_array($p, $existingPeriods)) {
                        $availablePeriods[] = $p;
                    }
                    $current->addMonth();
                }
            @endphp

            @if(count($availablePeriods) > 0)
                <div class="mt-6">
                    <h2 class="text-lg font-semibold text-gray-700 mb-3">Iniciar Avaliação</h2>
                    <div class="grid gap-3">
                        @foreach($availablePeriods as $period)
                            <div class="bg-white rounded-xl shadow-sm border p-5 flex items-center justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-800">
                                        {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->translatedFormat('F/Y') }}
                                    </h3>
                                    <p class="text-sm text-gray-500 mt-1">Autoavaliação ainda não iniciada</p>
                                </div>
                                <a href="{{ route('gdp.eval180.me.form', [$ciclo->id, $period]) }}"
                                   class="btn-mayer px-4 py-2 rounded-lg text-sm text-white">
                                    Iniciar
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif($forms->isEmpty())
                <div class="bg-gray-50 rounded-lg p-6 text-center text-gray-500">
                    <p>Nenhum período disponível para avaliação neste momento.</p>
                </div>
            @endif
        </div>
    @endif
</div>
@endsection
