@extends('layouts.app')

@section('title', 'Nexo — Atendimento WhatsApp')

@section('content')
<div class="max-w-4xl mx-auto py-8">
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-md p-8 border-t-4 border-blue-500">
        <div class="flex items-center gap-4 mb-6">
            <div class="text-4xl">💬</div>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Nexo — Atendimento WhatsApp</h1>
                <p class="text-gray-500 dark:text-gray-400 mt-1">Fase 1 implantada com sucesso. Infraestrutura pronta.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-emerald-50 dark:bg-emerald-900/20 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" id="kpi-conversas">—</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Conversas</div>
            </div>
            <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400" id="kpi-mensagens">—</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Mensagens</div>
            </div>
            <div class="bg-orange-50 dark:bg-orange-900/20 rounded-lg p-4 text-center">
                <div class="text-2xl font-bold text-orange-600 dark:text-orange-400" id="kpi-unread">—</div>
                <div class="text-sm text-gray-600 dark:text-gray-400">Não Lidas</div>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4">
            <h3 class="font-semibold text-gray-700 dark:text-gray-300 mb-2">Status da Fase 1</h3>
            <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                <li>✅ Tabelas wa_conversations, wa_messages, wa_events criadas</li>
                <li>✅ Models com relacionamentos e scopes</li>
                <li>✅ SendPulseWhatsAppService com OAuth, envio e parsing</li>
                <li>✅ NexoConversationSyncService com webhook e polling</li>
                <li>✅ Endpoints JSON funcionais (inbox, chat, envio, atribuição)</li>
                <li>✅ Webhook integrado ao /webhook/leads existente</li>
                <li>⏳ Interface 3 colunas (Fase 2)</li>
                <li>⏳ Menu Nexo na sidebar (Fase 3)</li>
            </ul>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ url('/nexo/atendimento/conversas') }}" 
               class="inline-flex items-center px-4 py-2 bg-brand text-white rounded-lg hover-bg-brand-dark transition text-sm">
                Ver API JSON (Conversas)
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    fetch('{{ url("/nexo/atendimento/conversas") }}')
        .then(r => r.json())
        .then(data => {
            document.getElementById('kpi-conversas').textContent = data.total || 0;
        })
        .catch(() => {});

    fetch('{{ url("/nexo/gerencial/data") }}')
        .then(r => r.json())
        .then(data => {
            if (data.kpis) {
                document.getElementById('kpi-unread').textContent = data.kpis.nao_lidas || 0;
            }
        })
        .catch(() => {});
});
</script>
@endsection
