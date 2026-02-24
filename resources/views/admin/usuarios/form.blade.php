@extends('layouts.app')

@section('title', isset($usuario) ? 'Editar Usuário' : 'Novo Usuário')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    {{-- Cabeçalho --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.usuarios.index') }}" 
           class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">
                {{ isset($usuario) ? 'Editar Usuário' : 'Novo Usuário' }}
            </h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ isset($usuario) ? 'Atualize as informações do usuário' : 'Preencha os dados para criar um novo usuário' }}
            </p>
        </div>
    </div>

    {{-- Alertas de Erro --}}
    @if($errors->any())
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex">
                <span class="text-red-500 mr-2">❌</span>
                <div>
                    <p class="text-sm font-medium text-red-700 dark:text-red-300">Corrija os erros abaixo:</p>
                    <ul class="mt-2 text-sm text-red-600 dark:text-red-400 list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    {{-- Formulário --}}
    <form action="{{ isset($usuario) ? route('admin.usuarios.update', $usuario) : route('admin.usuarios.store') }}" 
          method="POST"
          class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6 space-y-6">
        @csrf
        @if(isset($usuario))
            @method('PUT')
        @endif

        {{-- Dados Básicos --}}
        <div class="space-y-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white border-b border-gray-200 dark:border-gray-700 pb-2">
                Dados Básicos
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Nome --}}
                <div class="md:col-span-2">
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nome Completo <span class="text-red-500">*</span>
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="{{ old('name', $usuario->name ?? '') }}"
                           required
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                {{-- Email --}}
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        E-mail <span class="text-red-500">*</span>
                    </label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           value="{{ old('email', $usuario->email ?? '') }}"
                           required
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                {{-- Telefone --}}
                <div>
                    <label for="telefone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Telefone
                    </label>
                    <input type="text" 
                           id="telefone" 
                           name="telefone" 
                           value="{{ old('telefone', $usuario->telefone ?? '') }}"
                           placeholder="(00) 00000-0000"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                {{-- Cargo --}}
                <div>
                    <label for="cargo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Cargo
                    </label>
                    <input type="text" 
                           id="cargo" 
                           name="cargo" 
                           value="{{ old('cargo', $usuario->cargo ?? '') }}"
                           placeholder="Ex: Advogado Sênior"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                </div>

                {{-- Alias do Operador (só admin) --}}
                @if(($usuario->role ?? '') === 'admin')
                <div>
                    <label for="operator_alias" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Nome de exibição (WhatsApp)
                    </label>
                    <input type="text"
                           id="operator_alias"
                           name="operator_alias"
                           value="{{ old('operator_alias', $usuario->operator_alias ?? '') }}"
                           placeholder="Deixe vazio para usar o nome real"
                           class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Se preenchido, este nome aparecerá no WhatsApp em vez do nome real.</p>
                </div>
                @endif
                {{-- Papel --}}
                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Papel <span class="text-red-500">*</span>
                    </label>
                    <select id="role" 
                            name="role" 
                            required
                            class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @foreach($roles as $key => $label)
                            <option value="{{ $key }}" {{ old('role', $usuario->role ?? 'socio') === $key ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        O papel define as permissões padrão do usuário
                    </p>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Status
                    </label>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" 
                               name="ativo" 
                               value="1"
                               {{ old('ativo', $usuario->ativo ?? true) ? 'checked' : '' }}
                               class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-500 peer-checked:bg-emerald-600"></div>
                        <span class="ml-3 text-sm text-gray-700 dark:text-gray-300">Usuário ativo</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Informações sobre permissões --}}
        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex">
                <span class="text-blue-500 mr-2">ℹ️</span>
                <div class="text-sm text-blue-700 dark:text-blue-300">
                    <p class="font-medium">Sobre os papéis:</p>
                    <ul class="mt-2 space-y-1">
                        <li><strong>Administrador:</strong> Acesso total ao sistema, pode gerenciar usuários e sincronizações</li>
                        <li><strong>Coordenador:</strong> Visualiza todos os dashboards, vê performance da equipe no GDP</li>
                        <li><strong>Sócio:</strong> Visualiza dashboards, vê apenas sua própria performance no GDP</li>
                    </ul>
                    @if(!isset($usuario))
                        <p class="mt-2 text-blue-600 dark:text-blue-400">
                            ⚠️ Uma senha temporária será gerada automaticamente
                        </p>
                    @endif
                </div>
            </div>
        </div>

        {{-- Vínculo DataJuri (apenas visualização na edição) --}}
        @if(isset($usuario) && $usuario->datajuri_id)
            <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
                <div class="flex items-center">
                    <span class="text-emerald-500 mr-2">🔗</span>
                    <div class="text-sm text-emerald-700 dark:text-emerald-300">
                        <p class="font-medium">Vinculado ao DataJuri</p>
                        <p>ID DataJuri: {{ $usuario->datajuri_id }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Botões --}}
        <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <a href="{{ route('admin.usuarios.index') }}" 
               class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                {{ isset($usuario) ? 'Salvar Alterações' : 'Criar Usuário' }}
            </button>
        </div>
    </form>
</div>
@endsection
