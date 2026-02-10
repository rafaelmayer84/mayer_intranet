@extends('layouts.app')

@section('title', 'Lead #' . $lead->id . ' — ' . $lead->nome)

@section('content')
<div class="space-y-6">

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <a href="{{ route('leads.index') }}" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">← Voltar para Central de Leads</a>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white mt-1">{{ $lead->nome }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400">Lead #{{ $lead->id }} • {{ $lead->data_entrada ? $lead->data_entrada->format('d/m/Y H:i') : 'Sem data' }}</p>
        </div>
        <div class="flex gap-2">
            @if(!$lead->espocrm_id)
                <button onclick="sendToCRM({{ $lead->id }})" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 transition">
                    Enviar para CRM
                </button>
            @endif
            <button onclick="reprocessLead({{ $lead->id }})" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 transition">
                Reprocessar com IA
            </button>
            <button onclick="deleteLead({{ $lead->id }})" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                Deletar Lead
            </button>
        </div>
    </div>

    {{-- ALERTA DE ERRO --}}
    @if($lead->erro_processamento)
    <div class="bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl p-4">
        <div class="flex items-start gap-3">
            <span class="text-xl">⚠️</span>
            <div>
                <h4 class="text-sm font-medium text-red-800 dark:text-red-300">Erro de Processamento</h4>
                <p class="text-sm text-red-700 dark:text-red-400 mt-1">{{ $lead->erro_processamento }}</p>
            </div>
        </div>
    </div>
    @endif

    {{-- DADOS BÁSICOS + STATUS --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Dados de Contato --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-4">📞 Contato</h3>
            <div class="space-y-3">
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Nome</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->nome }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Telefone</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->telefone }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Cidade</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->cidade ?: 'Não informada' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">GCLID</span>
                    <p class="text-sm font-mono text-gray-700 dark:text-gray-300">{{ $lead->gclid ?: 'Não capturado' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Contact ID</span>
                    <p class="text-sm font-mono text-gray-500 dark:text-gray-400 break-all">{{ $lead->contact_id ?: '-' }}</p>
                </div>
                @if($lead->espocrm_id)
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">EspoCRM ID</span>
                    <p class="text-sm font-mono text-green-600 dark:text-green-400">✅ {{ $lead->espocrm_id }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Análise Jurídica --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-4">⚖️ Análise Jurídica</h3>
            <div class="space-y-3">
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Área do Direito</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->area_interesse ?: 'Não identificada' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Sub-área</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->sub_area ?: 'Não identificada' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Complexidade</span>
                    @if($lead->complexidade)
                        @php
                            $compColor = match($lead->complexidade) {
                                'alta' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                'média' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                default => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $compColor }}">{{ ucfirst($lead->complexidade) }}</span>
                    @else
                        <p class="text-sm text-gray-500">-</p>
                    @endif
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Urgência</span>
                    @if($lead->urgencia)
                        @php
                            $urgColor = match($lead->urgencia) {
                                'crítica' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                'alta' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                'média' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                default => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $urgColor }}">{{ ucfirst($lead->urgencia) }}</span>
                    @else
                        <p class="text-sm text-gray-500">-</p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Inteligência de Marketing --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-4">📊 Marketing</h3>
            <div class="space-y-3">
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Intenção de Contratar</span>
                    @php
                        $intColor = match($lead->intencao_contratar) {
                            'sim' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                            'talvez' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                            default => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                        };
                        $intIcon = match($lead->intencao_contratar) { 'sim' => '✅', 'talvez' => '⚠️', default => '❌' };
                    @endphp
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $intColor }}">{{ $intIcon }} {{ ucfirst($lead->intencao_contratar ?: 'não') }}</span>
                    @if($lead->intencao_justificativa)
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 italic">{{ $lead->intencao_justificativa }}</p>
                    @endif
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Potencial de Honorários</span>
                    @if($lead->potencial_honorarios)
                        @php
                            $potColor = match($lead->potencial_honorarios) {
                                'alto' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
                                'médio' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                default => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $potColor }}">{{ ucfirst($lead->potencial_honorarios) }}</span>
                    @else
                        <p class="text-sm text-gray-500">-</p>
                    @endif
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Origem</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->origem_label }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Gatilho Emocional</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->gatilho_emocional ? ucfirst($lead->gatilho_emocional) : '-' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Perfil Socioeconômico</span>
                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $lead->perfil_socioeconomico ? 'Classe ' . $lead->perfil_socioeconomico : '-' }}</p>
                </div>
                <div>
                    <span class="text-xs text-gray-500 dark:text-gray-400">Objeções</span>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $lead->objecoes ?: 'Nenhuma detectada' }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- RESUMO DA DEMANDA --}}
    @if($lead->resumo_demanda)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">📝 Resumo da Demanda</h3>
        <p class="text-sm text-gray-800 dark:text-gray-200 leading-relaxed">{{ $lead->resumo_demanda }}</p>
    </div>
    @endif

    {{-- PALAVRAS-CHAVE --}}
    @if($lead->palavras_chave)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">🔑 Palavras-chave Google Ads</h3>
        <div class="flex flex-wrap gap-2">
            @foreach(explode(',', $lead->palavras_chave) as $palavra)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                    {{ trim($palavra) }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- STATUS --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase mb-3">📋 Status do Lead</h3>
        <div class="flex flex-wrap gap-2">
            @foreach(['novo', 'contatado', 'qualificado', 'convertido', 'descartado'] as $status)
                <button
                    onclick="updateStatus({{ $lead->id }}, '{{ $status }}')"
                    class="px-4 py-2 text-sm rounded-lg border transition
                        {{ $lead->status === $status
                            ? 'bg-blue-600 text-white border-blue-600'
                            : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600'
                        }}"
                >
                    {{ ucfirst($status) }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- MENSAGENS --}}
    @if($lead->messages && $lead->messages->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase">💬 Histórico de Mensagens ({{ $lead->messages->count() }})</h3>
        </div>
        <div class="p-4 space-y-3 max-h-96 overflow-y-auto">
            @foreach($lead->messages as $message)
                <div class="flex {{ $message->direction === 'in' ? 'justify-start' : 'justify-end' }}">
                    <div class="max-w-xs sm:max-w-md lg:max-w-lg px-4 py-2 rounded-xl text-sm
                        {{ $message->direction === 'in'
                            ? 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200'
                            : 'bg-blue-600 text-white'
                        }}">
                        <p class="break-words">{{ $message->message_text }}</p>
                        <p class="text-xs mt-1 {{ $message->direction === 'in' ? 'text-gray-400' : 'text-blue-200' }}">
                            {{ $message->sent_at ? \Carbon\Carbon::parse($message->sent_at)->format('d/m H:i') : '' }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
    @include("leads.partials.tracking-info")
@endsection

@push('scripts')
<script>
function updateStatus(leadId, status) {
    fetch(`/leads/${leadId}/status`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ status: status })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { window.location.reload(); }
        else { alert('Erro: ' + (data.message || 'Erro desconhecido')); }
    })
    .catch(() => alert('Erro ao atualizar status'));
}

function sendToCRM(leadId) {
    if (!confirm('Enviar este lead para o EspoCRM?')) return;
    fetch(`/leads/${leadId}/send-crm`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { alert('Lead enviado ao CRM!'); window.location.reload(); }
        else { alert('Erro: ' + (data.message || 'Erro desconhecido')); }
    })
    .catch(() => alert('Erro ao enviar para CRM'));
}

function reprocessLead(leadId) {
    if (!confirm('Confirma o reprocessamento deste lead com a IA?')) return;
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Processando...';
    fetch(`/leads/${leadId}/reprocess`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { alert('Lead reprocessado com sucesso!'); window.location.reload(); }
        else { alert('Erro ao reprocessar: ' + (data.error || 'Erro desconhecido')); btn.disabled = false; btn.textContent = 'Reprocessar com IA'; }
    })
    .catch(error => { alert('Erro ao reprocessar lead'); btn.disabled = false; btn.textContent = 'Reprocessar com IA'; });
}

function deleteLead(leadId) {
    if (!confirm('ATENÇÃO: Esta ação é irreversível. Deseja deletar este lead permanentemente?')) return;
    fetch(`/leads/${leadId}`, {
        method: 'DELETE',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) { alert('Lead deletado!'); window.location.href = '/leads'; }
        else { alert('Erro: ' + (data.message || 'Erro desconhecido')); }
    })
    .catch(() => alert('Erro ao deletar lead'));
}
</script>
@endpush
