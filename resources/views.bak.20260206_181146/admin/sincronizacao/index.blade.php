@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sincronização Unificada</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Gerencie a sincronização com DataJuri e ESPO CRM
        </p>
    </div>

    <!-- Abas -->
    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
            <button
                onclick="switchTab('dashboard')"
                id="tab-dashboard"
                class="tab-button active border-blue-500 text-blue-600 dark:border-blue-400 dark:text-blue-400 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                📊 Dashboard
            </button>
            <button
                onclick="switchTab('configuracoes')"
                id="tab-configuracoes"
                class="tab-button border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:border-gray-600 dark:hover:text-gray-300 whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium">
                ⚙️ Configurações
            </button>
        </nav>
    </div>

    <!-- Conteúdo das Abas -->
    <div id="content-dashboard" class="tab-content">
        @include('admin.sincronizacao.partials._tab-dashboard')
    </div>

    <div id="content-configuracoes" class="tab-content hidden">
        @include('admin.sincronizacao.partials._tab-configuracoes')
    </div>
</div>

<!-- Modal Gerenciar Regras -->
@include('admin.sincronizacao.partials._modal-gerenciar-regras')

<script>
function switchTab(tabName) {
    // Esconde todos os conteúdos
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active de todos os botões
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-600', 'dark:border-blue-400', 'dark:text-blue-400');
        button.classList.add('border-transparent', 'text-gray-500', 'dark:text-gray-400');
    });

    // Mostra conteúdo ativo
    document.getElementById('content-' + tabName).classList.remove('hidden');

    // Ativa botão correspondente
    const activeButton = document.getElementById('tab-' + tabName);
    activeButton.classList.remove('border-transparent', 'text-gray-500', 'dark:text-gray-400');
    activeButton.classList.add('active', 'border-blue-500', 'text-blue-600', 'dark:border-blue-400', 'dark:text-blue-400');
}
</script>
@endsection
