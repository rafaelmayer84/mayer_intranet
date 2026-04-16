@extends('layouts.app')

@section('title', $reportTitle ?? 'Relatório')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6">

    {{-- Breadcrumb --}}
    <nav class="flex mb-6 text-sm text-gray-500">
        <a href="{{ route('relatorios.index') }}" class="hover:text-[#385776]">Relatórios</a>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-400">{{ $domainLabel ?? '' }}</span>
        <svg class="w-4 h-4 mx-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        <span class="text-gray-700 font-medium">{{ $reportTitle ?? '' }}</span>
    </nav>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <svg class="w-16 h-16 mx-auto mb-4 text-[#385776] opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        <h2 class="text-xl font-bold text-gray-700 mb-2">{{ $reportTitle }}</h2>
        <p class="text-gray-400">{{ $message ?? 'Este relatório está em fase de implementação.' }}</p>
        <a href="{{ route('relatorios.index') }}" class="inline-flex items-center gap-2 mt-6 px-5 py-2.5 bg-[#385776] hover:bg-[#1B334A] text-white text-sm font-medium rounded-lg transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
            Voltar à Central
        </a>
    </div>
</div>
@endsection
