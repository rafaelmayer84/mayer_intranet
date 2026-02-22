@extends('layouts.app')

@section('title', 'SISRH — Regras de Remuneração')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold" style="color: #1B334A;">Regras de Remuneração</h1>
        <a href="{{ route('sisrh.index') }}" class="text-sm underline" style="color: #385776;">← Voltar</a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
    @endif

        {{-- Senioridade dos Advogados --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <h2 class="font-semibold mb-3" style="color: #385776;">Nível de Senioridade dos Advogados</h2>
        <table class="w-full text-sm mb-4">
            <thead><tr class="border-b"><th class="text-left py-1">Advogado</th><th class="text-left py-1">Role</th><th class="text-left py-1">Nível Atual</th><th class="text-left py-1">Alterar</th></tr></thead>
            <tbody>
                @foreach($users as $u)
                <tr class="border-b border-gray-100">
                    <td class="py-2">{{ $u->name }}</td>
                    <td class="py-2 text-xs text-gray-500">{{ $u->role ?? '-' }}</td>
                    <td class="py-2">
                        @if($u->nivel_senioridade)
                            <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">{{ str_replace('_', ' ', $u->nivel_senioridade) }}</span>
                        @else
                            <span class="text-xs text-red-500">Não definido</span>
                        @endif
                    </td>
                    <td class="py-2">
                        <form action="{{ route('sisrh.senioridade.salvar') }}" method="POST" class="flex gap-1">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $u->id }}">
                            <select name="nivel_senioridade" class="border rounded px-2 py-1 text-xs">
                                <option value="">Selecione</option>
                                <option value="Junior" {{ $u->nivel_senioridade == 'Junior' ? 'selected' : '' }}>Junior</option>
                                <option value="Pleno" {{ $u->nivel_senioridade == 'Pleno' ? 'selected' : '' }}>Pleno</option>
                                <option value="Senior_I" {{ $u->nivel_senioridade == 'Senior_I' ? 'selected' : '' }}>Sênior I</option>
                                <option value="Senior_II" {{ $u->nivel_senioridade == 'Senior_II' ? 'selected' : '' }}>Sênior II</option>
                                <option value="Senior_III" {{ $u->nivel_senioridade == 'Senior_III' ? 'selected' : '' }}>Sênior III</option>
                            </select>
                            <button type="submit" class="px-2 py-1 rounded text-white text-xs" style="background-color: #385776;">Salvar</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- RB por Nível --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3" style="color: #385776;">RB por Nível de Senioridade</h2>
            <p class="text-xs text-gray-500 mb-3">Ciclo: {{ $ciclo->nome ?? 'N/D' }}</p>

            @if($niveis->count())
            <table class="w-full text-sm mb-4">
                <thead><tr class="border-b"><th class="text-left py-1">Nível</th><th class="text-right py-1">Valor RB</th></tr></thead>
                <tbody>
                    @foreach($niveis as $n)
                    <tr class="border-b border-gray-100"><td class="py-2">{{ $n->nivel }}</td><td class="text-right">R$ {{ number_format($n->valor_rb, 2, ',', '.') }}</td></tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            <form action="{{ route('sisrh.rb-nivel.salvar') }}" method="POST" class="grid grid-cols-3 gap-2">
                @csrf
                <input type="hidden" name="ciclo_id" value="{{ $ciclo->id ?? '' }}">
                <select name="nivel" class="border rounded px-2 py-1.5 text-sm" required>
                    <option value="">Nível</option>
                    @foreach(\App\Models\SisrhRbNivel::NIVEIS as $nv)
                        <option value="{{ $nv }}">{{ str_replace('_', ' ', $nv) }}</option>
                    @endforeach
                </select>
                <input type="number" name="valor_rb" step="0.01" placeholder="Valor R$" class="border rounded px-2 py-1.5 text-sm" required>
                <button type="submit" class="px-3 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Salvar</button>
            </form>
        </div>

        {{-- Overrides --}}
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="font-semibold mb-3" style="color: #385776;">Override de RB por Advogado</h2>

            @if($overrides->count())
            <table class="w-full text-sm mb-4">
                <thead><tr class="border-b"><th class="text-left py-1">Advogado</th><th class="text-right py-1">Valor RB</th><th class="text-left py-1">Motivo</th></tr></thead>
                <tbody>
                    @foreach($overrides as $o)
                    <tr class="border-b border-gray-100">
                        <td class="py-2">{{ $o->user->name ?? 'N/D' }}</td>
                        <td class="text-right">R$ {{ number_format($o->valor_rb, 2, ',', '.') }}</td>
                        <td class="text-xs text-gray-500">{{ Str::limit($o->motivo, 40) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif

            <form action="{{ route('sisrh.rb-override.salvar') }}" method="POST" class="space-y-2">
                @csrf
                <input type="hidden" name="ciclo_id" value="{{ $ciclo->id ?? '' }}">
                <div class="grid grid-cols-2 gap-2">
                    <select name="user_id" class="border rounded px-2 py-1.5 text-sm" required>
                        <option value="">Advogado</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    <input type="number" name="valor_rb" step="0.01" placeholder="Valor R$" class="border rounded px-2 py-1.5 text-sm" required>
                </div>
                <input type="text" name="motivo" placeholder="Motivo do override" class="w-full border rounded px-2 py-1.5 text-sm" required>
                <button type="submit" class="px-3 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Salvar Override</button>
            </form>
        </div>
    </div>

    {{-- Faixas GDP --}}
    <div class="bg-white rounded-lg shadow p-4 mt-6">
        <h2 class="font-semibold mb-3" style="color: #385776;">Faixas de Remuneração por Score GDP</h2>
        <p class="text-xs text-gray-500 mb-3">Definem o percentual aplicado sobre a captação. Ex: score 70-90% → 8% sobre receita captada.</p>

        @if($faixas->count())
        <table class="w-full text-sm mb-4">
            <thead style="background-color: #385776;">
                <tr>
                    <th class="px-3 py-2 text-left text-white">Score Mín</th>
                    <th class="px-3 py-2 text-left text-white">Score Máx</th>
                    <th class="px-3 py-2 text-left text-white">% Remuneração</th>
                    <th class="px-3 py-2 text-left text-white">Label</th>
                    <th class="px-3 py-2 text-center text-white">Ação</th>
                </tr>
            </thead>
            <tbody>
                @foreach($faixas as $f)
                <tr class="border-b border-gray-100">
                    <form action="{{ route('sisrh.faixa.atualizar', $f->id) }}" method="POST">
                        @csrf @method('PUT')
                        <td class="px-3 py-2"><input type="number" step="0.01" name="score_min" value="{{ $f->score_min }}" class="border rounded px-2 py-1 text-sm w-20"></td>
                        <td class="px-3 py-2"><input type="number" step="0.01" name="score_max" value="{{ $f->score_max }}" class="border rounded px-2 py-1 text-sm w-20"></td>
                        <td class="px-3 py-2"><input type="number" step="0.01" name="percentual_remuneracao" value="{{ $f->percentual_remuneracao }}" class="border rounded px-2 py-1 text-sm w-20 font-semibold"></td>
                        <td class="px-3 py-2"><input type="text" name="label" value="{{ $f->label }}" class="border rounded px-2 py-1 text-sm w-36"></td>
                        <td class="px-3 py-2 text-center">
                            <div class="flex gap-2 justify-center">
                                <button type="submit" class="px-2 py-1 rounded text-white text-xs" style="background-color: #385776;">Salvar</button>
                    </form>
                                <form action="{{ route('sisrh.faixa.excluir', $f->id) }}" method="POST" class="inline" onsubmit="return confirm('Remover faixa?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-red-600 text-xs underline">Excluir</button>
                                </form>
                            </div>
                        </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <form action="{{ route('sisrh.faixa.salvar') }}" method="POST" class="grid grid-cols-5 gap-2">
            @csrf
            <input type="hidden" name="ciclo_id" value="{{ $ciclo->id ?? '' }}">
            <input type="number" name="score_min" step="0.1" placeholder="Score Mín %" class="border rounded px-2 py-1.5 text-sm" required>
            <input type="number" name="score_max" step="0.1" placeholder="Score Máx %" class="border rounded px-2 py-1.5 text-sm" required>
            <input type="number" name="percentual_remuneracao" step="0.1" placeholder="% Remuneração" class="border rounded px-2 py-1.5 text-sm" required>
            <input type="text" name="label" placeholder="Label (ex: Bom)" class="border rounded px-2 py-1.5 text-sm">
            <button type="submit" class="px-3 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Adicionar</button>
        </form>
    </div>
</div>
@endsection
