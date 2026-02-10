@extends('layouts.app')

@section('title', 'Detalhes do Lead')

@section('content')
<div class="space-y-6">
    {{-- Header com Navegação --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="{{ route('leads.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                        {{ $lead->nome }}
                    </h1>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Lead #{{ $lead->id }} • {{ $lead->data_entrada->format('d/m/Y H:i') }}
                    </p>
                </div>
            </div>
            <div class="flex gap-3">
                @if($lead->temErro())
                    <button onclick="reprocessLead({{ $lead->id }})" class="inline-flex items-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Reprocessar
                    </button>
                @endif
                @if($lead->espocrm_id)
                    <a href="https://mayeradvogados.adv.br/CRM/#Lead/view/{{ $lead->espocrm_id }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                        </svg>
                        Abrir no CRM
                    </a>
                @endif
            </div>
        </div>

        @if($lead->temErro())
            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-md">
                <div class="flex">
                    <svg class="w-5 h-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800 dark:text-red-200">Erro de Processamento</h3>
                        <p class="mt-1 text-sm text-red-700 dark:text-red-300">{{ $lead->erro_processamento }}</p>
                    </div>
                </div>
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Informações do Lead --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Dados Básicos --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informações Básicas</h3>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nome</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->nome }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Telefone</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->telefone }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Área de Interesse</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->area_interesse ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cidade</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->cidade ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Intenção de Contratar</dt>
                        <dd class="mt-1">{!! $lead->getIntencaoBadge() !!}</dd>
                    </div>
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                        <dd class="mt-1">
                            <select id="status-select" class="rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm" onchange="updateStatus({{ $lead->id }}, this.value)">
                                <option value="novo" {{ $lead->status == 'novo' ? 'selected' : '' }}>Novo</option>
                                <option value="contatado" {{ $lead->status == 'contatado' ? 'selected' : '' }}>Contatado</option>
                                <option value="qualificado" {{ $lead->status == 'qualificado' ? 'selected' : '' }}>Qualificado</option>
                                <option value="convertido" {{ $lead->status == 'convertido' ? 'selected' : '' }}>Convertido</option>
                                <option value="descartado" {{ $lead->status == 'descartado' ? 'selected' : '' }}>Descartado</option>
                            </select>
                        </dd>
                    </div>
                    @if($lead->gclid)
                        <div class="md:col-span-2">
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">GCLID</dt>
                            <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $lead->gclid }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Resumo da Demanda --}}
            @if($lead->resumo_demanda)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Resumo da Demanda</h3>
                    <p class="text-sm text-gray-900 dark:text-gray-300 leading-relaxed">{{ $lead->resumo_demanda }}</p>
                </div>
            @endif

            {{-- Palavras-Chave --}}
            @if($lead->palavras_chave)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Palavras-Chave</h3>
                    <div class="flex flex-wrap gap-2">
                        @foreach(explode(',', $lead->palavras_chave) as $palavra)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                {{ trim($palavra) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Histórico de Mensagens --}}
            @if($lead->messages->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Histórico de Conversa</h3>
                    <div class="space-y-4 max-h-96 overflow-y-auto">
                        @foreach($lead->messages as $message)
                            <div class="flex {{ $message->isInbound() ? 'justify-start' : 'justify-end' }}">
                                <div class="max-w-xs lg:max-w-md">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-xs font-medium {{ $message->isInbound() ? 'text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400' }}">
                                            {{ $message->getSender() }}
                                        </span>
                                        @if($message->sent_at)
                                            <span class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $message->sent_at->format('d/m/Y H:i') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="px-4 py-2 rounded-lg {{ $message->isInbound() ? 'bg-blue-100 dark:bg-blue-900 text-blue-900 dark:text-blue-100' : 'bg-gray-100 dark:bg-gray-700 text-gray-900 dark:text-gray-100' }}">
                                        <p class="text-sm">{{ $message->message_text }}</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar de Metadados --}}
        <div class="space-y-6">
            {{-- Metadados Técnicos --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Metadados</h3>
                <dl class="space-y-3">
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Contact ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $lead->contact_id ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">EspoCRM ID</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $lead->espocrm_id ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Data de Entrada</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->data_entrada->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Criado em</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->created_at->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Atualizado em</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->updated_at->format('d/m/Y H:i:s') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Total de Mensagens</dt>
                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">{{ $lead->messages->count() }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Ações Rápidas --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Ações</h3>
                <div class="space-y-2">
                    @if(!$lead->espocrm_id)
                        <button onclick="sendToCRM({{ $lead->id }})" class="w-full inline-flex items-center justify-center px-4 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            Enviar para CRM
                        </button>
                    @endif
                    <button onclick="reprocessLead({{ $lead->id }})" class="w-full inline-flex items-center justify-center px-4 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Reprocessar com IA
                    </button>
                    <button onclick="deleteLead({{ $lead->id }})" class="w-full inline-flex items-center justify-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                        Deletar Lead
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function updateStatus(leadId, newStatus) {
    if (!confirm('Confirma a alteração de status?')) {
        document.getElementById('status-select').value = '{{ $lead->status }}';
        return;
    }

    fetch(`/leads/${leadId}/status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({ status: newStatus })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status atualizado com sucesso!');
        } else {
            alert('Erro ao atualizar status');
            document.getElementById('status-select').value = '{{ $lead->status }}';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar status');
        document.getElementById('status-select').value = '{{ $lead->status }}';
    });
}

function reprocessLead(leadId) {
    if (!confirm('Confirma o reprocessamento deste lead com a IA?')) {
        return;
    }

    const button = event.target;
    button.disabled = true;
    button.textContent = 'Processando...';

    fetch(`/leads/${leadId}/reprocess`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Lead reprocessado com sucesso!');
            window.location.reload();
        } else {
            alert('Erro ao reprocessar: ' + (data.error || 'Erro desconhecido'));
            button.disabled = false;
            button.textContent = 'Reprocessar';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao reprocessar lead');
        button.disabled = false;
        button.textContent = 'Reprocessar';
    });
}
</script>
@endpush
@endsection
<script>
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
