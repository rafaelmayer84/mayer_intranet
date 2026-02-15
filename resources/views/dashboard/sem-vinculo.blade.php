@extends('layouts.app')

@section('title', 'Sem Vínculo - Intranet Mayer')
@section('header', 'Minha Performance')

@section('content')
<div class="flex items-center justify-center min-h-96">
    <div class="max-w-md w-full bg-white rounded-xl shadow-sm p-8 text-center">
        <div class="mb-6">
            <svg class="mx-auto h-16 w-16 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
        </div>
        
        <h2 class="text-2xl font-bold text-gray-800 mb-4">Usuário Sem Vínculo</h2>
        
        <p class="text-gray-600 mb-6">
            Seu usuário ainda não está vinculado a um advogado no sistema DataJuri.
            Entre em contato com o administrador para realizar a vinculação.
        </p>
        
        <div class="bg-gray-100 rounded-lg p-4 mb-6">
            <p class="text-sm text-gray-600">
                <strong>Usuário logado:</strong><br>
                {{ auth()->user()->name }}<br>
                {{ auth()->user()->email }}
            </p>
        </div>
        
        <div class="space-y-3">
            <a href="{{ url('/') }}" class="block w-full bg-brand hover-bg-brand-dark text-white font-medium py-2 px-4 rounded-lg transition">
                Voltar ao Dashboard
            </a>
            
            @if(auth()->user()->role === 'admin')
            <a href="{{ url('/configuracoes') }}" class="block w-full bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition">
                Ir para Configurações
            </a>
            @endif
        </div>
    </div>
</div>
@endsection
