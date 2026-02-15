@extends('layouts.app')

@section('title', 'Configurações - Intranet Mayer')
@section('header', 'Configurações do Sistema')

@section('content')
<div class="space-y-6">
    <!-- Configurações Gerais -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Configurações Gerais</h3>
        
        <form action="{{ route('configuracoes.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Ano de Filtro</label>
                    <select name="ano_filtro" class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        @for($ano = 2020; $ano <= 2030; $ano++)
                            <option value="{{ $ano }}" {{ ($configuracoes['ano_filtro'] ?? 2025) == $ano ? 'selected' : '' }}>{{ $ano }}</option>
                        @endfor
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do Escritório</label>
                    <input type="text" name="nome_escritorio" value="{{ $configuracoes['nome_escritorio'] ?? 'Mayer Advogados' }}" 
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="bg-brand hover-bg-brand-dark text-white font-medium py-2 px-4 rounded-lg transition">
                    Salvar Configurações
                </button>
            </div>
        </form>
    </div>
    
    <!-- Metas BSC -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Metas BSC Padrão</h3>
        
        <form action="{{ route('configuracoes.metas') }}" method="POST" class="space-y-4">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta de Faturamento (R$)</label>
                    <input type="number" name="meta_faturamento" value="{{ $configuracoes['meta_faturamento'] ?? 100000 }}" 
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta de Horas (h)</label>
                    <input type="number" name="meta_horas" value="{{ $configuracoes['meta_horas'] ?? 1200 }}" 
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Meta de Processos</label>
                    <input type="number" name="meta_processos" value="{{ $configuracoes['meta_processos'] ?? 50 }}" 
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition">
                    Salvar Metas
                </button>
            </div>
        </form>
    </div>
    
    <!-- Vinculação de Usuários -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Vinculação de Usuários</h3>
        <p class="text-sm text-gray-500 mb-4">Vincule usuários do sistema aos advogados do DataJuri para exibir dados individuais.</p>
        
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Advogado Vinculado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($usuarios ?? [] as $usuario)
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $usuario->name }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $usuario->email }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $usuario->advogado->nome ?? 'Não vinculado' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <form action="{{ route('configuracoes.vincular') }}" method="POST" class="flex items-center gap-2">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $usuario->id }}">
                                <select name="advogado_id" class="text-sm border border-gray-300 rounded px-2 py-1">
                                    <option value="">Selecione...</option>
                                    @foreach($advogados ?? [] as $advogado)
                                        <option value="{{ $advogado->id }}" {{ $usuario->advogado_id == $advogado->id ? 'selected' : '' }}>
                                            {{ $advogado->nome }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="text-blue-600 hover:text-blue-800">Vincular</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-6 py-4 text-center text-gray-500">Nenhum usuário encontrado</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- API DataJuri -->
    <div class="bg-white rounded-xl shadow-sm p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">Integração DataJuri</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <p class="text-sm text-gray-500">Status da Conexão</p>
                <p class="text-lg font-medium text-green-600">Conectado</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Última Sincronização</p>
                <p class="text-lg font-medium text-gray-800">{{ $ultimaSync ?? 'Nunca' }}</p>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="{{ route('sync.index') }}" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                Ir para Sincronização
            </a>
        </div>
    </div>
</div>

@if(session('success'))
<div class="fixed bottom-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
    {{ session('success') }}
</div>
@endif
@endsection
