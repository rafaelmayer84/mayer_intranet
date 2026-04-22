@extends('layouts.app')

@section('title', 'Compromisso obrigatório')

@section('content')
<div class="max-w-4xl mx-auto px-4 py-8">

    <div class="bg-white border-2 border-amber-400 rounded-xl shadow-lg overflow-hidden">
        <div class="bg-gradient-to-r from-amber-600 to-amber-500 text-white px-6 py-5">
            <div class="flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M4.083 9h1.946c.089-1.546.383-2.97.837-4.118A6.004 6.004 0 004.083 9zM10 2a8 8 0 100 16 8 8 0 000-16zm0 2c-.076 0-.232.032-.465.262-.238.234-.497.623-.737 1.182-.389.907-.673 2.142-.766 3.556h3.936c-.093-1.414-.377-2.649-.766-3.556-.24-.559-.5-.948-.737-1.182C10.232 4.032 10.076 4 10 4zm3.971 5c-.089-1.546-.383-2.97-.837-4.118A6.004 6.004 0 0115.917 9h-1.946zm-2.003 2H8.032c.093 1.414.377 2.649.766 3.556.24.559.5.948.737 1.182.233.23.389.262.465.262.076 0 .232-.032.465-.262.238-.234.498-.623.737-1.182.389-.907.673-2.142.766-3.556zm1.166 4.118c.454-1.147.748-2.572.837-4.118h1.946a6.004 6.004 0 01-2.783 4.118zm-6.268 0C6.412 13.97 6.118 12.546 6.03 11H4.083a6.004 6.004 0 002.783 4.118z" clip-rule="evenodd"/>
                </svg>
                <div>
                    <h1 class="text-xl font-bold">Compromisso diário obrigatório</h1>
                    <p class="text-amber-100 text-sm">Antes de acessar a conta, assuma o compromisso de hoje.</p>
                </div>
            </div>
        </div>

        <div class="px-6 py-5">
            <p class="text-gray-700 mb-2">
                <strong>Cliente:</strong> {{ $account->name }}
                @if($account->datajuri_pessoa_id)
                    <span class="text-gray-500">(DJ {{ $account->datajuri_pessoa_id }})</span>
                @endif
            </p>
            <p class="text-sm text-gray-600 mb-5">
                Esta conta tem pendência(s) de qualidade de dados ainda não resolvidas no DataJuri.
                Cada dia que você acessa esta conta, precisa reafirmar o compromisso de corrigir.
                Isso é registrado em log auditável (quem, quando, IP).
            </p>

            <div class="space-y-3 mb-6">
                @foreach($gates as $gate)
                    <div class="border rounded-lg p-4 {{ $gate->status === 'escalado' ? 'border-red-300 bg-red-50' : 'border-amber-200 bg-amber-50' }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1">
                                <h3 class="font-semibold {{ $gate->status === 'escalado' ? 'text-red-900' : 'text-amber-900' }}">
                                    {{ $gateLabels[$gate->tipo] ?? $gate->tipo }}
                                </h3>
                                @if(!empty($gate->evidencia_local['dica']))
                                    <p class="text-sm text-gray-800 mt-1">{{ $gate->evidencia_local['dica'] }}</p>
                                @endif
                                <p class="text-xs text-gray-500 mt-2">
                                    Aberto {{ $gate->opened_at?->diffForHumans() }}
                                    @if($gate->first_seen_by_owner_at)
                                        · Revisão iniciada {{ $gate->first_seen_by_owner_at?->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            @if($gate->status === 'escalado')
                                <span class="shrink-0 px-2 py-0.5 text-xs bg-red-600 text-white rounded font-bold">ESCALADO</span>
                            @else
                                <span class="shrink-0 px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded">em revisão</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('crm.accounts.gate-ciente', $account->id) }}" class="space-y-4">
                @csrf
                @foreach($gates as $gate)
                    <input type="hidden" name="gate_ids[]" value="{{ $gate->id }}">
                @endforeach

                <div class="bg-gray-50 border-2 border-gray-300 rounded-lg p-4">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" name="compromisso" value="1" required
                               class="mt-1 h-5 w-5 rounded border-gray-400 text-amber-600 focus:ring-amber-500">
                        <span class="text-sm text-gray-800">
                            Eu, <strong>{{ auth()->user()->name ?? '—' }}</strong>, declaro ciente das pendências acima
                            e <strong>comprometo-me hoje</strong> a ajustar o cadastro desta conta no DataJuri para que
                            os dados ali reflitam a realidade. Sei que se eu não corrigir o DataJuri em até
                            <strong>7 dias</strong> desde o início da revisão, gera penalidade
                            <strong>PEN-C01 (3 pontos, eixo Atendimento)</strong> no GDP Conformidade — e que
                            este compromisso será renovado a cada acesso diário enquanto a pendência não for
                            resolvida.
                        </span>
                    </label>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('crm.carteira') }}" class="text-sm text-gray-600 hover:underline">
                        ← Voltar para Carteira
                    </a>
                    <div class="flex gap-2">
                        @if($account->datajuri_pessoa_id)
                            <a href="https://mayer.datajuri.com.br/datajuri/pages/publico/cadastro/pessoa/pessoa.faces?id={{ $account->datajuri_pessoa_id }}"
                               target="_blank" rel="noopener"
                               class="px-4 py-2 bg-blue-700 text-white rounded-lg hover:bg-blue-800 text-sm">
                                Abrir DataJuri
                            </a>
                        @endif
                        <button type="submit"
                                class="px-5 py-2 bg-amber-600 text-white font-semibold rounded-lg hover:bg-amber-700">
                            Assumir compromisso e acessar a conta
                        </button>
                    </div>
                </div>
            </form>

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
                        Use somente quando o DataJuri <strong>não tem como</strong> representar a realidade
                        (ex: PF sócia de PJ cliente, cônjuge, herdeiros). Justificativa fica em log auditável
                        e fecha sem PEN-C01.
                    </p>
                    <textarea name="justificativa" rows="3" minlength="15" maxlength="1000" required
                              placeholder="Ex: PF sócia da PJ Artefatos de Cimento Raimondi (conta #1508)."
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
        Log de compromisso auditável: user, timestamp, IP e user-agent são registrados em
        <code>crm_account_data_gate_cientes</code>.
    </p>
</div>
@endsection
