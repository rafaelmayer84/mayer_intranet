@extends('layouts.app')

@section('title', 'Central de Relatórios')

@section('content')
<div class="w-full px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header com gradiente --}}
    <div class="bg-gradient-to-r from-[#385776] to-[#1B334A] rounded-2xl p-6 mb-8 shadow-lg">
        <div class="flex items-center gap-4">
            <div class="bg-white/10 rounded-xl p-3">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-bold text-white">Central de Relatórios</h1>
                <p class="text-white/60 text-sm mt-1">27 relatórios em 7 domínios — filtre, consulte e exporte em Excel ou PDF</p>
            </div>
        </div>
    </div>

    {{-- Grid de domínios --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($domains as $domain)
        @php
            $colors = [
                'financeiro'     => ['from-emerald-500', 'to-emerald-700', 'bg-emerald-50', 'text-emerald-700', 'border-emerald-200', 'hover:border-emerald-300', 'bg-emerald-100', 'group-hover:text-emerald-600'],
                'processos'      => ['from-blue-500', 'to-blue-700', 'bg-blue-50', 'text-blue-700', 'border-blue-200', 'hover:border-blue-300', 'bg-blue-100', 'group-hover:text-blue-600'],
                'crm'            => ['from-violet-500', 'to-violet-700', 'bg-violet-50', 'text-violet-700', 'border-violet-200', 'hover:border-violet-300', 'bg-violet-100', 'group-hover:text-violet-600'],
                'produtividade'  => ['from-amber-500', 'to-amber-700', 'bg-amber-50', 'text-amber-700', 'border-amber-200', 'hover:border-amber-300', 'bg-amber-100', 'group-hover:text-amber-600'],
                'justus'         => ['from-indigo-500', 'to-indigo-700', 'bg-indigo-50', 'text-indigo-700', 'border-indigo-200', 'hover:border-indigo-300', 'bg-indigo-100', 'group-hover:text-indigo-600'],
                'nexo'           => ['from-green-500', 'to-green-700', 'bg-green-50', 'text-green-700', 'border-green-200', 'hover:border-green-300', 'bg-green-100', 'group-hover:text-green-600'],
                'gdp'            => ['from-rose-500', 'to-rose-700', 'bg-rose-50', 'text-rose-700', 'border-rose-200', 'hover:border-rose-300', 'bg-rose-100', 'group-hover:text-rose-600'],
                'sistema'        => ['from-slate-500', 'to-slate-700', 'bg-slate-50', 'text-slate-700', 'border-slate-200', 'hover:border-slate-300', 'bg-slate-100', 'group-hover:text-slate-600'],
                'sisrh'          => ['from-cyan-500', 'to-cyan-700', 'bg-cyan-50', 'text-cyan-700', 'border-cyan-200', 'hover:border-cyan-300', 'bg-cyan-100', 'group-hover:text-cyan-600'],
                'leads'          => ['from-orange-500', 'to-orange-700', 'bg-orange-50', 'text-orange-700', 'border-orange-200', 'hover:border-orange-300', 'bg-orange-100', 'group-hover:text-orange-600'],
            ];
            $c = $colors[$domain['key']] ?? $colors['sistema'];
        @endphp
        <div class="bg-white rounded-xl shadow-sm border {{ $c[4] }} {{ $c[5] }} hover:shadow-md transition-all overflow-hidden group">
            {{-- Card header com gradiente --}}
            <div class="bg-gradient-to-r {{ $c[0] }} {{ $c[1] }} px-5 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="bg-white/20 rounded-lg p-2 text-white">
                        {!! $domain['icon'] !!}
                    </div>
                    <div>
                        <h2 class="font-bold text-white text-lg">{{ $domain['title'] }}</h2>
                    </div>
                </div>
                <span class="bg-white/20 text-white text-xs font-bold px-2.5 py-1 rounded-full">
                    {{ count($domain['reports']) }}
                </span>
            </div>
            {{-- Lista de relatórios --}}
            <div class="px-4 py-3 space-y-0.5">
                @foreach($domain['reports'] as $report)
                <a href="{{ route($report['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm text-gray-600 hover:{{ $c[2] }} hover:{{ $c[3] }} transition-all group/item">
                    <div class="w-1.5 h-1.5 rounded-full bg-gray-300 group-hover/item:{{ $c[6] }} transition-colors flex-shrink-0"></div>
                    <span class="flex-1">{{ $report['label'] }}</span>
                    <svg class="w-4 h-4 text-gray-300 group-hover/item:{{ $c[3] }} transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
