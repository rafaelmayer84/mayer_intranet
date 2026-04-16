@extends('layouts.app')

@section('title', 'Meu Perfil')

@section('content')
<div class="ds">
<div class="ds-page w-full">

    {{-- Header --}}
    <div class="mb-6 ds-a ds-a1">
        <h1 class="text-2xl font-bold" style="color: var(--ds-navy);">Meu Perfil</h1>
        <p class="text-sm" style="color: var(--ds-text-3);">Visualize seus dados e gerencie sua senha</p>
    </div>

    {{-- Mensagem de sucesso --}}
    @if(session('success'))
        <div class="mb-6 ds-a ds-a2">
            <div class="ds-card ds-card--success">
                <div class="ds-card-body">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" style="color: var(--ds-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="font-medium" style="color: var(--ds-success);">{{ session('success') }}</span>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Alerta senha expirando/expirada --}}
    @if($senhaExpirada)
        <div class="mb-6 ds-a ds-a2">
            <div class="ds-card" style="border-left: 3px solid var(--ds-danger);">
                <div class="ds-card-body">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" style="color: var(--ds-danger);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                        </svg>
                        <div>
                            <p class="font-semibold" style="color: var(--ds-danger);">Sua senha expirou!</p>
                            <p class="text-sm" style="color: var(--ds-text-2);">Por seguranca, altere sua senha abaixo para continuar usando o sistema.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @elseif($diasRestantes <= 7)
        <div class="mb-6 ds-a ds-a2">
            <div class="ds-card" style="border-left: 3px solid var(--ds-warning);">
                <div class="ds-card-body">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0" style="color: var(--ds-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <div>
                            <p class="font-semibold" style="color: var(--ds-warning);">Sua senha expira em {{ $diasRestantes }} dia(s)</p>
                            <p class="text-sm" style="color: var(--ds-text-2);">Recomendamos alterar sua senha em breve.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- Card Dados Pessoais --}}
        <div class="ds-card ds-card--accent ds-a ds-a3">
            <div class="ds-card-head">
                <h3 class="flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: var(--ds-primary);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Meus Dados
                </h3>
            </div>

            <div class="ds-card-body">
                <div class="space-y-4">
                    <div>
                        <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">Nome</label>
                        <p class="font-medium" style="color: var(--ds-text);">{{ $usuario->name }}</p>
                    </div>
                    <div>
                        <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">E-mail</label>
                        <p style="color: var(--ds-text);">{{ $usuario->email }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">Telefone</label>
                            <p style="color: var(--ds-text);">{{ $usuario->telefone ?: '—' }}</p>
                        </div>
                        <div>
                            <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">Cargo</label>
                            <p style="color: var(--ds-text);">{{ $usuario->cargo ?: '—' }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">Papel</label>
                            <span class="ds-badge
                                @if($usuario->role === 'admin') ds-badge--info
                                @elseif($usuario->role === 'coordenador') ds-badge--info
                                @else ds-badge--success
                                @endif">
                                {{ ucfirst($usuario->role) }}
                            </span>
                        </div>
                        <div>
                            <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">Status</label>
                            <span class="ds-badge {{ $usuario->ativo ? 'ds-badge--success' : 'ds-badge--danger' }}">
                                {{ $usuario->ativo ? 'Ativo' : 'Inativo' }}
                            </span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4 pt-3" style="border-top: 1px solid var(--ds-border-light);">
                        <div>
                            <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">Ultimo Acesso</label>
                            <p class="text-sm" style="color: var(--ds-text);">{{ $usuario->ultimo_acesso ? ($usuario->ultimo_acesso ? \Carbon\Carbon::parse($usuario->ultimo_acesso)->format('d/m/Y H:i') : 'Nunca acessou') : 'Nunca' }}</p>
                        </div>
                        <div>
                            <label class="ds-label" style="text-transform: uppercase; letter-spacing: 0.05em;">Senha Alterada em</label>
                            <p class="text-sm" style="color: var(--ds-text);">{{ $usuario->password_changed_at ? \Carbon\Carbon::parse($usuario->password_changed_at)->format('d/m/Y H:i') : 'Nunca alterada' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ds-card-foot">
                <p class="text-xs" style="color: var(--ds-text-3);">
                    Para alterar seus dados pessoais, entre em contato com o administrador do sistema.
                </p>
            </div>
        </div>

        {{-- Card Alterar Senha --}}
        <div class="ds-card ds-a ds-a4 {{ $senhaExpirada ? 'ds-card--accent' : '' }}" @if($senhaExpirada) style="border-top-color: var(--ds-danger);" @endif>
            <div class="ds-card-head">
                <h3 class="flex items-center">
                    <svg class="w-5 h-5 mr-2" style="color: var(--ds-warning);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
                    </svg>
                    Alterar Senha
                </h3>
            </div>

            <div class="ds-card-body">
                @if(!$senhaExpirada && $diasRestantes > 7)
                    <div class="mb-4 p-3 rounded-lg" style="background: var(--ds-success-bg);">
                        <p class="text-sm" style="color: var(--ds-success);">
                            <span class="font-medium">&#10003; Senha valida</span> — expira em {{ $diasRestantes }} dias
                        </p>
                        <div class="mt-2 w-full rounded-full h-2" style="background: var(--ds-success-bg); border: 1px solid var(--ds-success-border);">
                            <div class="h-2 rounded-full" style="background: var(--ds-success); width: {{ ($diasRestantes / 30) * 100 }}%"></div>
                        </div>
                    </div>
                @endif

                <form action="{{ route('perfil.alterar-senha') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="senha_atual" class="ds-label">Senha Atual *</label>
                        <input type="password" name="senha_atual" id="senha_atual" required
                               class="ds-input w-full @error('senha_atual') border-red-500 @enderror">
                        @error('senha_atual')
                            <p class="mt-1 text-sm" style="color: var(--ds-danger);">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="nova_senha" class="ds-label">Nova Senha *</label>
                        <input type="password" name="nova_senha" id="nova_senha" required minlength="8"
                               class="ds-input w-full @error('nova_senha') border-red-500 @enderror">
                        @error('nova_senha')
                            <p class="mt-1 text-sm" style="color: var(--ds-danger);">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs" style="color: var(--ds-text-3);">Minimo 8 caracteres, diferente da senha atual</p>
                    </div>

                    <div>
                        <label for="nova_senha_confirmation" class="ds-label">Confirmar Nova Senha *</label>
                        <input type="password" name="nova_senha_confirmation" id="nova_senha_confirmation" required minlength="8"
                               class="ds-input w-full">
                    </div>

                    <button type="submit" class="ds-btn ds-btn--primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Alterar Senha
                    </button>
                </form>
            </div>
        </div>
    </div>

</div>
</div>
@endsection
