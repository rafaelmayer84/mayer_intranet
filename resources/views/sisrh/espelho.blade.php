@extends('layouts.app')

@section('title', 'Espelho de Remuneração')

@section('content')
<div class="w-full px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: #1B334A;">Espelho de Remuneração</h1>
            <p class="text-sm text-gray-500">
                {{ $advogado->name ?? 'N/D' }} — {{ str_pad($mes, 2, '0', STR_PAD_LEFT) }}/{{ $ano }}
                @if($advogado->nivel_senioridade)
                    <span class="ml-2 px-2 py-0.5 rounded text-xs bg-gray-200">{{ $advogado->nivel_senioridade }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('sisrh.index') }}" class="text-sm underline" style="color: #385776;">← Voltar</a>
    </div>

    @if(!$apuracao)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-6 text-center">
            <p class="text-amber-700">Nenhuma apuração encontrada para este período.</p>
        </div>
    @else
        {{-- Status --}}
        <div class="mb-4">
            @if($apuracao->bloqueio_motivo)
                <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                    <p class="text-red-700 font-semibold">⚠️ Apuração Bloqueada: {{ $apuracao->bloqueio_motivo }}</p>
                </div>
            @elseif($apuracao->status === 'closed')
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-700 font-semibold">✅ Competência Fechada em {{ $apuracao->closed_at?->format('d/m/Y H:i') }}</p>
                    <p class="text-xs text-green-600 mt-1">Hash: {{ $apuracao->snapshot_hash }}</p>
                </div>
            @else
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                    <p class="text-amber-700">🔄 Apuração em aberto (simulação)</p>
                </div>
            @endif
        </div>

        {{-- Detalhamento --}}
        <div class="bg-white rounded-lg shadow divide-y">
            {{-- Seção 1: Dados base --}}
            <div class="p-4">
                <h2 class="font-semibold mb-3" style="color: #385776;">1. Dados Base</h2>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    <div><span class="text-gray-500">RB (Pró-labore):</span> <strong>R$ {{ number_format($apuracao->rb_valor, 2, ',', '.') }}</strong></div>
                    <div><span class="text-gray-500">Score GDP:</span> <strong>{{ number_format($apuracao->gdp_score, 1) }}%</strong></div>
                    <div><span class="text-gray-500">Faixa Aplicada:</span> <strong>{{ number_format($apuracao->percentual_faixa, 1) }}%</strong></div>
                    <div><span class="text-gray-500">Captação (Receita):</span> <strong>R$ {{ number_format($apuracao->captacao_valor, 2, ',', '.') }}</strong></div>
                </div>
            </div>

            {{-- Seção 2: Cálculo RV --}}
            <div class="p-4">
                <h2 class="font-semibold mb-3" style="color: #385776;">2. Cálculo da RV</h2>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>RV Bruta (Captação × Faixa GDP)</span>
                        <span class="font-medium">R$ {{ number_format($apuracao->rv_bruta, 2, ',', '.') }}</span>
                    </div>
                    @if($apuracao->reducao_conformidade_pct > 0)
                    <div class="flex justify-between text-red-600">
                        <span>(-) Redução Conformidade</span>
                        <span>-{{ number_format($apuracao->reducao_conformidade_pct, 1) }}%</span>
                    </div>
                    @endif
                    @if($apuracao->reducao_acompanhamento_pct > 0)
                    <div class="flex justify-between text-red-600">
                        <span>(-) Redução Acompanhamento Bimestral</span>
                        <span>-{{ number_format($apuracao->reducao_acompanhamento_pct, 1) }}%</span>
                    </div>
                    @endif
                    @if($apuracao->reducao_total_pct > 0)
                    <div class="flex justify-between border-t pt-1">
                        <span>Total Reduções (cap 40%)</span>
                        <span class="text-red-600 font-medium">-{{ number_format($apuracao->reducao_total_pct, 1) }}%</span>
                    </div>
                    @endif
                    <div class="flex justify-between border-t pt-1">
                        <span>RV pós Reduções</span>
                        <span class="font-medium">R$ {{ number_format($apuracao->rv_pos_reducoes, 2, ',', '.') }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Teto RV (50% da RB)</span>
                        <span>R$ {{ number_format($apuracao->teto_rv_valor, 2, ',', '.') }}</span>
                    </div>
                    @if($apuracao->credito_utilizado > 0)
                    <div class="flex justify-between text-blue-600">
                        <span>(+) Crédito utilizado do banco</span>
                        <span>R$ {{ number_format($apuracao->credito_utilizado, 2, ',', '.') }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between border-t-2 pt-2" style="border-color: #385776;">
                        <span class="font-bold text-base" style="color: #1B334A;">RV APLICADA</span>
                        <span class="font-bold text-base" style="color: #385776;">R$ {{ number_format($apuracao->rv_aplicada, 2, ',', '.') }}</span>
                    </div>
                    @if($apuracao->rv_excedente_credito > 0)
                    <div class="flex justify-between text-blue-600 text-xs">
                        <span>Excedente → Banco de Créditos</span>
                        <span>+ R$ {{ number_format($apuracao->rv_excedente_credito, 2, ',', '.') }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Seção 3: Banco de créditos --}}
            <div class="p-4">
                <h2 class="font-semibold mb-2" style="color: #385776;">3. Banco de Créditos</h2>
                <p class="text-sm">Saldo atual: <strong>R$ {{ number_format($saldo, 2, ',', '.') }}</strong></p>
            </div>

            {{-- Seção 4: Ajustes --}}
            @if($ajustes->count() > 0)
            <div class="p-4">
                <h2 class="font-semibold mb-2" style="color: #385776;">4. Ajustes Pós-Fechamento</h2>
                <div class="space-y-1 text-sm">
                    @foreach($ajustes as $aj)
                    <div class="flex justify-between">
                        <span>{{ ucfirst($aj->tipo) }}: {{ $aj->motivo }}</span>
                        <span class="{{ $aj->tipo === 'desconto' ? 'text-red-600' : 'text-green-600' }}">
                            R$ {{ number_format($aj->valor, 2, ',', '.') }}
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Ajuste form (admin only) --}}
        @if(in_array(auth()->user()->role, ['admin', 'socio']) && $apuracao->isClosed())
        <div class="mt-6 bg-white rounded-lg shadow p-4">
            <h3 class="font-semibold mb-3" style="color: #1B334A;">Lançar Ajuste</h3>
            <form action="{{ route('sisrh.ajuste.lancar') }}" method="POST" class="grid grid-cols-1 md:grid-cols-4 gap-3">
                @csrf
                <input type="hidden" name="apuracao_id" value="{{ $apuracao->id }}">
                <select name="tipo" class="border rounded px-3 py-2 text-sm" required>
                    <option value="">Tipo</option>
                    <option value="bonus">Bônus</option>
                    <option value="desconto">Desconto</option>
                    <option value="correcao">Correção</option>
                    <option value="estorno">Estorno</option>
                </select>
                <input type="number" name="valor" step="0.01" placeholder="Valor R$" class="border rounded px-3 py-2 text-sm" required>
                <input type="text" name="motivo" placeholder="Motivo" class="border rounded px-3 py-2 text-sm" required>
                <button type="submit" class="px-4 py-2 rounded text-white text-sm" style="background-color: #385776;">
                    Lançar
                </button>
            </form>
        </div>
        @endif
    @endif
</div>
@endsection
