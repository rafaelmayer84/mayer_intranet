@extends('layouts.app')

@section('title', 'Conta com pendência de dados')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">

    <div class="bg-white border-2 border-red-300 rounded-xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-red-700 to-red-600 text-white px-6 py-5">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 6a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 6zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h1 class="text-xl font-bold">Conta com pendência de qualidade de dados</h1>
                    <p class="text-red-100 text-sm">Revise o cadastro no DataJuri antes de continuar.</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-5">
            <p class="text-gray-700 mb-4">
                Foram detectadas divergências entre o cadastro no <strong>DataJuri</strong> e os dados efetivos
                desta conta (contratos, processos, contas a receber). Como o DataJuri é a fonte oficial e
                não podemos gravar de volta, <strong>você precisa corrigir lá</strong>.
            </p>
            <p class="text-gray-700 mb-4">
                <strong>Cliente:</strong> {{ $account->name }}
                @if($account->datajuri_pessoa_id)
                    <span class="text-gray-500">(DJ ID: {{ $account->datajuri_pessoa_id }})</span>
                @endif
            </p>

            <div class="space-y-4 mb-6">
                @foreach($gates as $gate)
                    <div class="border border-amber-300 bg-amber-50 rounded-lg p-4">
                        <div class="flex items-start justify-between mb-2">
                            <div>
                                <h3 class="font-semibold text-amber-900">
                                    {{ $gateLabels[$gate->tipo] ?? $gate->tipo }}
                                </h3>
                                <p class="text-xs text-amber-700 mt-1">
                                    Aberto em {{ $gate->opened_at?->format('d/m/Y H:i') }}
                                    @if($gate->dj_valor_snapshot)
                                        · DJ no momento: <code class="bg-white px-1.5 rounded">{{ $gate->dj_valor_snapshot }}</code>
                                    @endif
                                </p>
                            </div>
                        </div>
                        @if(!empty($gate->evidencia_local['dica']))
                            <p class="text-sm text-gray-800 mb-2"><strong>O que verificar:</strong> {{ $gate->evidencia_local['dica'] }}</p>
                        @endif
                        @if(!empty($gate->evidencia_local))
                            <details class="text-sm text-gray-700">
                                <summary class="cursor-pointer text-amber-800 hover:underline">Ver evidências locais</summary>
                                <ul class="mt-2 pl-4 list-disc">
                                    @foreach($gate->evidencia_local as $k => $v)
                                        @if($k !== 'dica')
                                            <li><code>{{ $k }}</code>: {{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</li>
                                        @endif
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <h4 class="font-semibold text-blue-900 mb-2">O que fazer agora</h4>
                <ol class="list-decimal pl-5 text-sm text-blue-900 space-y-1">
                    <li>Clique em <strong>Abrir DataJuri</strong> e ajuste os campos divergentes (especialmente <code>statusPessoa</code>).</li>
                    <li>Salve no DataJuri.</li>
                    <li>Volte aqui e marque <strong>"Confirmo que revisei"</strong> abaixo.</li>
                    <li>A correção será validada na próxima sincronização (algumas horas). Se o DJ não for ajustado em <strong>7 dias</strong>, a pendência vira penalidade no GDP (eixo Atendimento, 3 pts).</li>
                </ol>
            </div>

            <div class="flex flex-wrap gap-3 items-center justify-between">
                <a href="https://mayer.datajuri.com.br/datajuri/pages/publico/cadastro/pessoa/pessoa.faces?id={{ $account->datajuri_pessoa_id }}"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-blue-700 text-white rounded-lg hover:bg-blue-800">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M11 3a1 1 0 100 2h2.586l-6.293 6.293a1 1 0 101.414 1.414L15 6.414V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                        <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                    </svg>
                    Abrir DataJuri
                </a>

                <form method="POST" action="{{ route('crm.accounts.gate-revisar', $account->id) }}" class="flex items-center gap-2">
                    @csrf
                    <label class="inline-flex items-center gap-2 text-sm">
                        <input type="checkbox" name="confirmo" required class="rounded border-gray-300">
                        Confirmo que revisei o DataJuri
                    </label>
                    <button type="submit"
                            class="px-4 py-2 bg-green-700 text-white rounded-lg hover:bg-green-800 disabled:opacity-50">
                        Liberar acesso à conta
                    </button>
                </form>
            </div>

            {{-- Justificar como exceção --}}
            <details class="mt-5 border-t pt-4">
                <summary class="cursor-pointer text-sm text-gray-600 hover:text-gray-900 font-medium">
                    Esta conta é um caso especial que não pode ser corrigido no DataJuri? →
                </summary>
                <form method="POST" action="{{ route('crm.accounts.gate-excecao', $account->id) }}" class="mt-3 space-y-3">
                    @csrf
                    @foreach($gates as $gate)
                        <input type="hidden" name="gate_ids[]" value="{{ $gate->id }}">
                    @endforeach
                    <p class="text-xs text-gray-600">
                        Use somente quando o DataJuri <strong>não tem como</strong> representar a realidade (ex: PF sócia
                        de PJ cliente, cônjuge incluído, herdeiros). A justificativa fica em log auditável e fecha a pendência
                        <strong>sem</strong> gerar penalidade PEN-C01.
                    </p>
                    <textarea name="justificativa" rows="3" minlength="15" maxlength="1000" required
                              placeholder="Ex: PF sócia da PJ Artefatos de Cimento Raimondi (conta #1508) — contrato está no CNPJ da empresa."
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-amber-500"></textarea>
                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-4 py-2 bg-gray-700 text-white rounded-lg hover:bg-gray-800 text-sm">
                            Fechar como exceção justificada
                        </button>
                    </div>
                </form>
            </details>
        </div>
    </div>

    <p class="text-xs text-gray-500 text-center mt-4">
        Você não pode acessar esta conta enquanto o gate está em status <strong>aberto</strong>.
        Gates escalados por mais de 7 dias geram penalidade GDP (PEN-C01).
    </p>
</div>
@endsection
