@extends('layouts.app')

@section('title', 'Detalhes da Sincronização - Intranet Mayer')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">📋 Detalhes da Sincronização</h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">Informações detalhadas sobre a sincronização #{{ $log->id }}</p>
        </div>
        <a href="{{ route('integration.index') }}" class="bg-brand hover-bg-brand-dark text-white px-6 py-3 rounded-lg font-medium transition">
            ← Voltar
        </a>
    </div>

    <!-- Informações Principais -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Card 1: Informações Básicas -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">ℹ️ Informações Básicas</h2>
            <div class="space-y-3">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">ID de Sincronização</p>
                    <p class="text-lg font-mono text-gray-900 dark:text-white">{{ $log->sync_id }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Tipo</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        @switch($log->tipo)
                            @case('sync_clientes')
                                👥 Sincronizar Clientes
                                @break
                            @case('sync_leads')
                                🎯 Sincronizar Leads
                                @break
                            @case('sync_oportunidades')
                                💼 Sincronizar Oportunidades
                                @break
                            @case('sync_full')
                                🔄 Sincronização Completa
                                @break
                            @default
                                {{ $log->tipo }}
                        @endswitch
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Fonte</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        @if($log->fonte === 'datajuri')
                            📊 DataJuri
                        @elseif($log->fonte === 'espocrm')
                            🎯 ESPO CRM
                        @else
                            {{ $log->fonte }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <!-- Card 2: Status -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">✅ Status</h2>
            <div class="space-y-3">
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Status Atual</p>
                    <p class="text-lg font-bold">
                        @if($log->status === 'concluido')
                            <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-medium">✅ Concluído</span>
                        @elseif($log->status === 'erro')
                            <span class="bg-red-100 text-red-800 px-3 py-1 rounded-full text-sm font-medium">❌ Erro</span>
                        @elseif($log->status === 'em_progresso')
                            <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">⏳ Em Progresso</span>
                        @else
                            <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-sm font-medium">{{ $log->status }}</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Duração</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        @if($log->duracao_segundos)
                            {{ abs($log->duracao_segundos) }}s
                        @else
                            -
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Estatísticas de Processamento -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">📊 Estatísticas de Processamento</h2>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div class="text-center">
                <p class="text-2xl font-bold text-blue-600">{{ $log->registros_processados ?? 0 }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Processados</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-green-600">{{ $log->registros_criados ?? 0 }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Criados</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-yellow-600">{{ $log->registros_atualizados ?? 0 }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Atualizados</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-purple-600">{{ $log->registros_ignorados ?? 0 }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Ignorados</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-red-600">{{ $log->registros_erro ?? 0 }}</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">Erros</p>
            </div>
        </div>
    </div>

    <!-- Timeline -->
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">🕐 Timeline</h2>
        <div class="space-y-4">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-brand rounded-full"></div>
                <div class="ml-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Iniciado</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $log->inicio ? $log->inicio->format('d/m/Y H:i:s') : '-' }}
                    </p>
                </div>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-600 rounded-full"></div>
                <div class="ml-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Finalizado</p>
                    <p class="text-lg font-bold text-gray-900 dark:text-white">
                        {{ $log->fim ? $log->fim->format('d/m/Y H:i:s') : '-' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensagem de Erro (se houver) -->
    @if($log->mensagem_erro)
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
            <p class="font-bold">❌ Erro</p>
            <p>{{ $log->mensagem_erro }}</p>
        </div>
    @endif

    <!-- Detalhes JSON (se houver) -->
    @if($log->detalhes)
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">📝 Detalhes Adicionais</h2>
            <pre class="bg-gray-100 dark:bg-gray-900 p-4 rounded-lg overflow-auto text-sm">{{ json_encode(json_decode($log->detalhes), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
    @endif
</div>
@endsection
