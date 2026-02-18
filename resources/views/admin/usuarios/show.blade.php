@extends('layouts.app')

@section('title', 'Detalhes do Usuário')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    {{-- Cabeçalho --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-4">
            <a href="{{ route('admin.usuarios.index') }}" 
               class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $usuario->name }}</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $usuario->email }}</p>
            </div>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('admin.usuarios.edit', $usuario) }}" 
               class="inline-flex items-center px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white text-sm font-medium rounded-lg transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
            </a>
        </div>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
            <div class="flex">
                <span class="text-emerald-500 mr-2">✅</span>
                <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ session('success') }}</p>
            </div>
            @if(session('senha_temporaria'))
                <div class="mt-2 p-3 bg-white dark:bg-gray-800 rounded border border-emerald-300 dark:border-emerald-700">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">Senha temporária:</p>
                    <code class="text-lg font-mono text-emerald-600 dark:text-emerald-400">{{ session('senha_temporaria') }}</code>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Anote esta senha, ela não será exibida novamente!</p>
                </div>
            @endif
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex">
                <span class="text-red-500 mr-2">❌</span>
                <p class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Coluna Principal --}}
        <div class="lg:col-span-2 space-y-6">
            {{-- Informações do Usuário --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Informações</h2>
                
                <div class="flex items-start gap-4">
                    <div class="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white text-2xl font-bold">
                        {{ strtoupper(substr($usuario->name, 0, 2)) }}
                    </div>
                    <div class="flex-1 grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Papel</p>
                            @php
                                $roleBadgeClass = match($usuario->role) {
                                    'admin' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
                                    'coordenador' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
                                    'socio' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                };
                            @endphp
                            <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium {{ $roleBadgeClass }}">
                                {{ $usuario->role_nome }}
                            </span>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Status</p>
                            @if($usuario->ativo)
                                <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                    Ativo
                                </span>
                            @else
                                <span class="inline-block mt-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300">
                                    Inativo
                                </span>
                            @endif
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Telefone</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ $usuario->telefone ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Cargo</p>
                            <p class="text-sm text-gray-900 dark:text-white">{{ $usuario->cargo ?? '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Último Acesso</p>
                            <p class="text-sm text-gray-900 dark:text-white">
                                {{ $usuario->ultimo_acesso ? $usuario->ultimo_acesso->format('d/m/Y H:i') : 'Nunca acessou' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 dark:text-gray-400">DataJuri</p>
                            @if($usuario->datajuri_id)
                                <p class="text-sm text-emerald-600 dark:text-emerald-400">🔗 ID: {{ $usuario->datajuri_id }}</p>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Não vinculado</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- Permissões por Módulo (apenas se não for admin) --}}
            @if(!$usuario->isAdmin())
                <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Permissões por Módulo</h2>
                        <form action="{{ route('admin.usuarios.aplicar-permissoes-padrao', $usuario) }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" 
                                    class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                                    onclick="return confirm('Isso irá resetar todas as permissões para o padrão do papel {{ $usuario->role_nome }}. Continuar?')">
                                🔄 Resetar para padrão
                            </button>
                        </form>
                    </div>

                    <form action="{{ route('admin.usuarios.salvar-permissoes', $usuario) }}" method="POST">
                        @csrf
                        
                        @foreach($matrizPermissoes as $grupo => $modulos)
                            <div class="mb-6 last:mb-0">
                                <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3 flex items-center">
                                    <span class="w-2 h-2 rounded-full bg-blue-500 mr-2"></span>
                                    {{ $grupo }}
                                </h3>
                                
                                <div class="space-y-3">
                                    @foreach($modulos as $item)
                                        <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                                            <div class="flex items-center gap-3">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" 
                                                           name="permissoes[{{ $item['modulo']->id }}][ativo]" 
                                                           value="1"
                                                           {{ $item['tem_permissao'] ? 'checked' : '' }}
                                                           class="sr-only peer">
                                                    <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all dark:border-gray-500 peer-checked:bg-brand"></div>
                                                </label>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                                                        {!! \App\Helpers\IconHelper::render($item['modulo']->icone, 'w-5 h-5 text-gray-500 dark:text-gray-400 mr-2 flex-shrink-0') !!}
                                                    <span>{{ $item['modulo']->nome }}</span>
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['modulo']->descricao }}</p>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center gap-4">
                                                {{-- Checkboxes de permissões --}}
                                                <div class="flex items-center gap-2">
                                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                                        <input type="checkbox" 
                                                               name="permissoes[{{ $item['modulo']->id }}][visualizar]" 
                                                               value="1"
                                                               {{ $item['pode_visualizar'] ? 'checked' : '' }}
                                                               class="w-3 h-3 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                                        Ver
                                                    </label>
                                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                                        <input type="checkbox" 
                                                               name="permissoes[{{ $item['modulo']->id }}][editar]" 
                                                               value="1"
                                                               {{ $item['pode_editar'] ? 'checked' : '' }}
                                                               class="w-3 h-3 rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                                                        Editar
                                                    </label>
                                                    <label class="flex items-center gap-1 text-xs text-gray-600 dark:text-gray-400">
                                                        <input type="checkbox" 
                                                               name="permissoes[{{ $item['modulo']->id }}][executar]" 
                                                               value="1"
                                                               {{ $item['pode_executar'] ? 'checked' : '' }}
                                                               class="w-3 h-3 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                                        Executar
                                                    </label>
                                                </div>
                                                
                                                {{-- Escopo --}}
                                                <select name="permissoes[{{ $item['modulo']->id }}][escopo]" 
                                                        class="text-xs px-2 py-1 rounded border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                    <option value="proprio" {{ $item['escopo'] === 'proprio' ? 'selected' : '' }}>Próprio</option>
                                                    <option value="equipe" {{ $item['escopo'] === 'equipe' ? 'selected' : '' }}>Equipe</option>
                                                    <option value="todos" {{ $item['escopo'] === 'todos' ? 'selected' : '' }}>Todos</option>
                                                </select>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        <div class="mt-6 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="submit" 
                                    class="w-full px-4 py-2 bg-[#385776] hover:bg-[#1B334A] text-white font-medium rounded-lg transition-colors">
                                Salvar Permissões
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg p-4">
                    <div class="flex">
                        <span class="text-purple-500 mr-2">👑</span>
                        <div class="text-sm text-purple-700 dark:text-purple-300">
                            <p class="font-medium">Administrador</p>
                            <p>Administradores têm acesso total ao sistema. Não é necessário configurar permissões específicas.</p>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Coluna Lateral --}}
        <div class="space-y-6">
            {{-- Ações Rápidas --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Ações</h2>
                
                <div class="space-y-3">
                    {{-- Resetar Senha --}}
                    <form action="{{ route('admin.usuarios.resetar-senha', $usuario) }}" method="POST">
                        @csrf
                        <button type="submit" 
                                onclick="return confirm('Tem certeza que deseja resetar a senha deste usuário?')"
                                class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/30 dark:hover:bg-amber-900/50 text-amber-700 dark:text-amber-300 rounded-lg transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                            </svg>
                            Resetar Senha
                        </button>
                    </form>

                    {{-- Ativar/Desativar --}}
                    @if($usuario->id !== auth()->id())
                        <form action="{{ route('admin.usuarios.toggle-status', $usuario) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            @if($usuario->ativo)
                                <button type="submit" 
                                        onclick="return confirm('Tem certeza que deseja desativar este usuário?')"
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-100 hover:bg-red-200 dark:bg-red-900/30 dark:hover:bg-red-900/50 text-red-700 dark:text-red-300 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                                    </svg>
                                    Desativar Usuário
                                </button>
                            @else
                                <button type="submit" 
                                        class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-emerald-100 hover:bg-emerald-200 dark:bg-emerald-900/30 dark:hover:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300 rounded-lg transition-colors">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Ativar Usuário
                                </button>
                            @endif
                        </form>
                    @endif
                </div>
            </div>

            {{-- Informações de Criação --}}
            <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Registro</h2>
                
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Criado em</p>
                        <p class="text-gray-900 dark:text-white">{{ $usuario->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <p class="text-gray-500 dark:text-gray-400">Atualizado em</p>
                        <p class="text-gray-900 dark:text-white">{{ $usuario->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
