@extends('layouts.app')

@section('title', 'NEXO — Escala de Atendimento')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Escala de Atendimento</h1>
            <p class="text-sm text-gray-500 mt-1">Defina o responsável pelo atendimento WhatsApp em cada dia.</p>
        </div>
        <a href="{{ route('nexo.gerencial') }}" class="text-sm text-[#385776] hover:underline">&larr; Voltar ao Painel</a>
    </div>

    {{-- Navegação por mês --}}
    <div class="flex items-center gap-4 mb-6">
        @php
            $mesAtual = \Carbon\Carbon::parse($mes . '-01');
            $mesAnterior = $mesAtual->copy()->subMonth()->format('Y-m');
            $mesProximo = $mesAtual->copy()->addMonth()->format('Y-m');
        @endphp
        <a href="{{ route('nexo.gerencial.escala', ['mes' => $mesAnterior]) }}" class="text-gray-500 hover:text-gray-800">&larr;</a>
        <h2 class="text-lg font-semibold text-gray-800">{{ $mesAtual->translatedFormat('F Y') }}</h2>
        <a href="{{ route('nexo.gerencial.escala', ['mes' => $mesProximo]) }}" class="text-gray-500 hover:text-gray-800">&rarr;</a>
    </div>

    {{-- Tabela --}}
    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-3 text-left">Data</th>
                    <th class="px-4 py-3 text-left">Dia</th>
                    <th class="px-4 py-3 text-left">Responsável</th>
                    <th class="px-4 py-3 text-center">Início</th>
                    <th class="px-4 py-3 text-center">Fim</th>
                    <th class="px-4 py-3 text-left">Observação</th>
                    <th class="px-4 py-3 text-center">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y" id="escala-body">
                @php
                    $diasNoMes = \Carbon\CarbonPeriod::create($mesAtual->copy()->startOfMonth(), $mesAtual->copy()->endOfMonth());
                    $escalasMap = $escalas->keyBy(fn($e) => $e->data->format('Y-m-d'));
                    $diasSemana = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
                @endphp
                @foreach($diasNoMes as $dia)
                    @php
                        $dataStr = $dia->format('Y-m-d');
                        $esc = $escalasMap[$dataStr] ?? null;
                        $isFds = $dia->isWeekend();
                    @endphp
                    <tr class="{{ $isFds ? 'bg-gray-50/50' : '' }} escala-row" data-data="{{ $dataStr }}">
                        <td class="px-4 py-2.5 font-medium {{ $isFds ? 'text-gray-400' : '' }}">{{ $dia->format('d/m') }}</td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $diasSemana[$dia->dayOfWeek] }}</td>
                        <td class="px-4 py-2.5">
                            <select class="esc-user border-gray-200 rounded text-sm py-1 px-2 w-full max-w-[200px] {{ $isFds && !$esc ? 'text-gray-300' : '' }}"
                                    data-data="{{ $dataStr }}" onchange="EscalaApp.salvar('{{ $dataStr }}')">
                                <option value="">— Sem escala —</option>
                                @foreach($usuarios as $u)
                                    <option value="{{ $u->id }}" {{ $esc && $esc->user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <input type="time" class="esc-inicio border-gray-200 rounded text-sm py-1 px-2 w-20 text-center"
                                   value="{{ $esc ? $esc->inicio : '09:00' }}" data-data="{{ $dataStr }}" onchange="EscalaApp.salvar('{{ $dataStr }}')">
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <input type="time" class="esc-fim border-gray-200 rounded text-sm py-1 px-2 w-20 text-center"
                                   value="{{ $esc ? $esc->fim : '18:00' }}" data-data="{{ $dataStr }}" onchange="EscalaApp.salvar('{{ $dataStr }}')">
                        </td>
                        <td class="px-4 py-2.5">
                            <input type="text" class="esc-obs border-gray-200 rounded text-sm py-1 px-2 w-full" maxlength="255"
                                   value="{{ $esc->observacao ?? '' }}" placeholder="—" data-data="{{ $dataStr }}"
                                   onblur="EscalaApp.salvar('{{ $dataStr }}')">
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            @if($esc)
                            <button onclick="EscalaApp.excluir({{ $esc->id }}, '{{ $dataStr }}')" class="text-red-400 hover:text-red-600 text-xs">Limpar</button>
                            @else
                            <span class="text-gray-300 text-xs esc-action" data-data="{{ $dataStr }}">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-400 mt-4">Selecione o responsável e o horário será salvo automaticamente. Use "Limpar" para remover a escala do dia.</p>
</div>

<script>
const EscalaApp = {
    async salvar(data) {
        const row = document.querySelector(`.escala-row[data-data="${data}"]`);
        const userId = row.querySelector('.esc-user').value;
        if (!userId) return;

        const body = {
            data: data,
            user_id: userId,
            inicio: row.querySelector('.esc-inicio').value || '09:00',
            fim: row.querySelector('.esc-fim').value || '18:00',
            observacao: row.querySelector('.esc-obs').value || null,
        };

        try {
            const resp = await fetch('{{ route("nexo.gerencial.escala.store") }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify(body),
            });
            const result = await resp.json();
            if (result.success) {
                row.querySelector('.esc-user').classList.add('border-emerald-300');
                setTimeout(() => row.querySelector('.esc-user').classList.remove('border-emerald-300'), 1500);
            }
        } catch (e) {
            console.error('Erro ao salvar escala:', e);
        }
    },

    async excluir(id, data) {
        if (!confirm('Limpar escala deste dia?')) return;
        try {
            const resp = await fetch(`/nexo/gerencial/escala/${id}`, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            });
            const result = await resp.json();
            if (result.success) {
                const row = document.querySelector(`.escala-row[data-data="${data}"]`);
                row.querySelector('.esc-user').value = '';
                row.querySelector('.esc-inicio').value = '09:00';
                row.querySelector('.esc-fim').value = '18:00';
                row.querySelector('.esc-obs').value = '';
            }
        } catch (e) {
            console.error('Erro ao excluir:', e);
        }
    },
};
</script>
@endsection
