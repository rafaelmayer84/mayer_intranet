@extends('layouts.app')

@section('title', 'Meu Perfil')

@section('content')
<div class="max-w-4xl mx-auto">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Meu Perfil</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">Visualize seus dados e gerencie sua senha</p>
    </div>

    {{-- Mensagem de sucesso --}}
    @if(session('success'))
        <div class="mb-6 p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <span class="text-green-700 dark:text-green-300 font-medium">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    {{-- Alerta senha expirando/expirada --}}
    @if($senhaExpirada)
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-red-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                <div>
                    <p class="text-red-700 dark:text-red-300 font-semibold">Sua senha expirou!</p>
                    <p class="text-red-600 dark:text-red-400 text-sm">Por segurança, altere sua senha abaixo para continuar usando o sistema.</p>
                </div>
            </div>
        </div>
    @elseif($diasRestantes <= 7)
        <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-xl">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-amber-500 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-amber-700 dark:text-amber-300 font-semibold">Sua senha expira em {{ $diasRestantes }} dia(s)</p>
                    <p class="text-amber-600 dark:text-amber-400 text-sm">Recomendamos alterar sua senha em breve.</p>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Card Dados Pessoais --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Meus Dados
            </h2>

            <div class="space-y-4">
                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Nome</label>
                    <p class="text-gray-900 dark:text-gray-100 font-medium">{{ $usuario->name }}</p>
                </div>
                <div>
                    <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">E-mail</label>
                    <p class="text-gray-900 dark:text-gray-100">{{ $usuario->email }}</p>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Telefone</label>
                        <p class="text-gray-900 dark:text-gray-100">{{ $usuario->telefone ?: '—' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Cargo</label>
                        <p class="text-gray-900 dark:text-gray-100">{{ $usuario->cargo ?: '—' }}</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Papel</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            @if($usuario->role === 'admin') bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300
                            @elseif($usuario->role === 'coordenador') bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300
                            @else bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300
                            @endif">
                            {{ ucfirst($usuario->role) }}
                        </span>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $usuario->ativo ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300' : 'bg-red-100 text-red-800' }}">
                            {{ $usuario->ativo ? 'Ativo' : 'Inativo' }}
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4 pt-2 border-t border-gray-100 dark:border-gray-700">
                    <div>
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Último Acesso</label>
                        <p class="text-gray-900 dark:text-gray-100 text-sm">{{ $usuario->ultimo_acesso ? $usuario->ultimo_acesso->format('d/m/Y H:i') : 'Nunca' }}</p>
                    </div>
                    <div>
                        <label class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">Senha Alterada em</label>
                        <p class="text-gray-900 dark:text-gray-100 text-sm">{{ $usuario->password_changed_at ? $usuario->password_changed_at->format('d/m/Y H:i') : 'Nunca' }}</p>
                    </div>
                </div>
            </div>

            <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700">
                <p class="text-xs text-gray-400 dark:text-gray-500">
                    Para alterar seus dados pessoais, entre em contato com o administrador do sistema.
                </p>
            </div>
        </div>

        {{-- Card Alterar Senha --}}
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 {{ $senhaExpirada ? 'ring-2 ring-red-400' : '' }}">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                <svg class="w-5 h-5 mr-2 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                </svg>
                Alterar Senha
            </h2>

            @if(!$senhaExpirada && $diasRestantes > 7)
                <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 rounded-lg">
                    <p class="text-sm text-green-700 dark:text-green-300">
                        <span class="font-medium">✓ Senha válida</span> — expira em {{ $diasRestantes }} dias
                    </p>
                    <div class="mt-2 w-full bg-green-200 dark:bg-green-800 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full" style="width: {{ ($diasRestantes / 30) * 100 }}%"></div>
                    </div>
                </div>
            @endif

            <form action="{{ route('perfil.alterar-senha') }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label for="senha_atual" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Senha Atual *</label>
                    <input type="password" name="senha_atual" id="senha_atual" required
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('senha_atual') border-red-500 @enderror">
                    @error('senha_atual')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="nova_senha" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nova Senha *</label>
                    <input type="password" name="nova_senha" id="nova_senha" required minlength="8"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('nova_senha') border-red-500 @enderror">
                    @error('nova_senha')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-1 text-xs text-gray-400">Mínimo 8 caracteres, diferente da senha atual</p>
                </div>

                <div>
                    <label for="nova_senha_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Confirmar Nova Senha *</label>
                    <input type="password" name="nova_senha_confirmation" id="nova_senha_confirmation" required minlength="8"
                           class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <button type="submit"
                        class="btn-mayer shadow">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Alterar Senha
                </button>
            </form>
        </div>
    </div>
</div>
@endsection
