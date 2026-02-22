@extends('layouts.app')

@section('title', 'GDP — Acompanhamento Bimestral')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-6">
    <h1 class="text-2xl font-bold mb-2" style="color: #1B334A;">Acompanhamento do Plano de Trabalho</h1>
    <p class="text-sm text-gray-500 mb-6">Ciclo: {{ $ciclo->nome ?? 'Nenhum ciclo ativo' }}</p>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 mb-4 text-sm">{{ session('success') }}</div>
    @endif

    @if(!$ciclo)
        <div class="bg-amber-50 border border-amber-200 rounded-lg p-6 text-center text-amber-700">
            Nenhum ciclo GDP ativo no momento.
        </div>
    @else
        {{-- Status dos bimestres --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            @for($b = 1; $b <= 3; $b++)
                @php
                    $acomp = $acompanhamentos->firstWhere('bimestre', $b);
                    $meses = [1 => 'Jan-Fev', 2 => 'Mar-Abr', 3 => 'Mai-Jun'];
                @endphp
                <div class="bg-white rounded-lg shadow p-4 border-l-4 {{ $acomp && in_array($acomp->status, ['submitted','validated']) ? 'border-green-500' : ($b <= $bimestreAtual ? 'border-amber-500' : 'border-gray-200') }}">
                    <p class="text-xs text-gray-500">{{ $b }}º Bimestre ({{ $meses[$b] ?? '' }})</p>
                    @if($acomp)
                        <p class="font-semibold text-sm mt-1">
                            @if($acomp->status === 'validated')
                                <span class="text-green-700">Validado</span>
                            @elseif($acomp->status === 'submitted')
                                <span style="color: #385776;">Submetido</span>
                            @elseif($acomp->status === 'rejected')
                                <span class="text-red-600">Rejeitado</span>
                            @else
                                <span class="text-gray-500">Rascunho</span>
                            @endif
                        </p>
                        <p class="text-xs text-gray-400 mt-1">{{ $acomp->submitted_at ? $acomp->submitted_at->format('d/m/Y H:i') : '' }}</p>
                    @else
                        <p class="text-sm mt-1 {{ $b <= $bimestreAtual ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">
                            {{ $b <= $bimestreAtual ? 'Pendente' : 'Futuro' }}
                        </p>
                    @endif
                </div>
            @endfor
        </div>

        {{-- Formulário de preenchimento --}}
        @if($bimestreAtual >= 1 && $bimestreAtual <= 3)
            @php
                $acompAtual = $acompanhamentos->firstWhere('bimestre', $bimestreAtual);
                $podeEditar = !$acompAtual || !in_array($acompAtual->status, ['validated']);
                $respostasAtuais = $acompAtual ? ($acompAtual->respostas_json ?? []) : [];
            @endphp

            @if($podeEditar)
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="font-semibold mb-4" style="color: #385776;">
                    Preencher {{ $bimestreAtual }}º Bimestre
                </h2>
                <p class="text-xs text-gray-500 mb-4">Responda as 20 questões do acompanhamento conforme seu Plano de Trabalho vigente.</p>

                <form action="{{ route('gdp.acompanhamento.submeter') }}" method="POST">
                    @csrf
                    <input type="hidden" name="ciclo_id" value="{{ $ciclo->id }}">
                    <input type="hidden" name="bimestre" value="{{ $bimestreAtual }}">

                    @php
                        $perguntas = [
                            'q01' => 'Quais foram as principais atividades jurídicas realizadas no bimestre?',
                            'q02' => 'Houve captação de novos clientes ou processos no período?',
                            'q03' => 'Quantos processos novos foram distribuídos sob sua responsabilidade?',
                            'q04' => 'Houve audiências realizadas? Quantas e de que tipo?',
                            'q05' => 'Quantas petições/peças foram protocoladas no período?',
                            'q06' => 'Houve participação em reuniões com clientes? Descreva.',
                            'q07' => 'Como avalia o cumprimento dos prazos processuais no período?',
                            'q08' => 'Houve algum resultado expressivo (sentença favorável, acordo, etc)?',
                            'q09' => 'Qual o status dos processos prioritários sob sua gestão?',
                            'q10' => 'Houve participação em cursos, palestras ou capacitações? Quais?',
                            'q11' => 'Quantas horas de capacitação foram realizadas no período?',
                            'q12' => 'Há certificados de capacitação a serem registrados?',
                            'q13' => 'Como avalia sua performance no atendimento ao cliente (tempo de resposta, qualidade)?',
                            'q14' => 'Houve feedbacks recebidos de clientes no período?',
                            'q15' => 'Quais foram os principais desafios enfrentados?',
                            'q16' => 'Houve contribuição para melhoria de processos internos do escritório?',
                            'q17' => 'Como avalia o atingimento das metas do GDP no período?',
                            'q18' => 'Quais metas precisam de atenção especial no próximo bimestre?',
                            'q19' => 'Há necessidade de suporte ou recursos adicionais?',
                            'q20' => 'Observações gerais ou complementos.',
                        ];
                    @endphp

                    <div class="space-y-4">
                        @foreach($perguntas as $key => $pergunta)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ strtoupper($key) }}. {{ $pergunta }}
                            </label>
                            <textarea name="respostas[{{ $key }}]" rows="2"
                                class="w-full border rounded px-3 py-2 text-sm"
                                placeholder="Sua resposta...">{{ $respostasAtuais[$key] ?? '' }}</textarea>
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end">
                        <button type="submit" class="px-6 py-2 rounded text-white" style="background-color: #385776;">
                            Submeter Acompanhamento
                        </button>
                    </div>
                </form>
            </div>
            @else
            <div class="bg-green-50 border border-green-200 rounded-lg p-4 text-green-700 text-sm">
                Acompanhamento do {{ $bimestreAtual }}º bimestre já validado. Nenhuma edição permitida.
            </div>
            @endif
        @endif
    @endif
</div>
@endsection
