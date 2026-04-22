@extends('layouts.app')
@section('title', 'Processos Administrativos')

@section('content')
<div>
    {{-- ══════════════ HERO EDITORIAL ══════════════ --}}
    <section class="crm-hero">
        <div>
            <div class="crm-hero-eyebrow">
                <a href="{{ route('crm.dashboard') }}" style="color:inherit;text-decoration:none;">CRM</a>
                <span>›</span> Processos Administrativos
            </div>
            <h1>Processos <em>administrativos</em>.</h1>
            <p class="crm-hero-sub">Extrajudiciais, cartorários, abertura de empresa, divórcios — tudo rastreado com timeline automática.</p>
        </div>
        <div class="crm-hero-right">
            <a href="{{ route('crm.admin-processes.create') }}" class="crm-section-head-action" style="background:var(--navy-700);color:white;padding:10px 18px;border-radius:var(--r-sm);">
                + Novo Processo
            </a>
        </div>
    </section>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border-l-4 border-l-[#1B334A] border border-gray-200 shadow-sm px-4 py-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-[#1B334A]/10 flex items-center justify-center text-[#1B334A] shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Ativos</p>
                <p class="text-2xl font-bold text-[#1B334A]">{{ $stats['total'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border-l-4 border-l-indigo-500 border border-gray-200 shadow-sm px-4 py-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center text-indigo-500 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Na minha mesa</p>
                <p class="text-2xl font-bold text-indigo-600">{{ $stats['na_minha_mesa'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border-l-4 border-l-blue-500 border border-gray-200 shadow-sm px-4 py-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center text-blue-500 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Em andamento</p>
                <p class="text-2xl font-bold text-blue-600">{{ $stats['em_andamento'] }}</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border-l-4 border-l-red-500 border border-gray-200 shadow-sm px-4 py-4 flex items-center gap-3">
            <div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center text-red-500 shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <div>
                <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Atrasados</p>
                <p class="text-2xl font-bold text-red-600">{{ $stats['atrasados'] }}</p>
            </div>
        </div>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="bg-white rounded-xl border shadow-sm px-4 py-3 mb-4 flex flex-wrap gap-3 items-end">
        <div>
            <label class="block text-xs text-gray-500 mb-1">Busca</label>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="Protocolo ou título..."
                   class="border rounded-lg px-3 py-1.5 text-sm w-52 focus:outline-none focus:ring-2 focus:ring-[#385776]">
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Status</label>
            <select name="status" class="border rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                <option value="">Todos</option>
                @foreach(['aberto'=>'Aberto','em_andamento'=>'Em andamento','aguardando_cliente'=>'Aguardando cliente','aguardando_terceiro'=>'Aguardando terceiro','suspenso'=>'Suspenso','concluido'=>'Concluído','cancelado'=>'Cancelado'] as $v=>$l)
                    <option value="{{ $v }}" @selected(request('status')===$v)>{{ $l }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-500 mb-1">Responsável</label>
            <select name="owner" class="border rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                <option value="">Todos</option>
                @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" @selected(request('owner')==$u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2 mt-4">
            <input type="checkbox" name="mesa" value="1" id="cb_mesa" @checked(request('mesa')==='1')
                   class="rounded text-[#385776]">
            <label for="cb_mesa" class="text-sm text-gray-700">Minha mesa</label>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-[#1B334A] text-white px-4 py-1.5 rounded-lg text-sm hover:bg-[#385776]">Filtrar</button>
            <a href="{{ route('crm.admin-processes.index') }}" class="bg-gray-100 text-gray-600 px-4 py-1.5 rounded-lg text-sm hover:bg-gray-200">Limpar</a>
        </div>
    </form>

    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Tabela --}}
    <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Protocolo</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Processo / Cliente</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Status</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Na mesa de</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Docs</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide">Último ato</th>
                    <th class="text-left px-4 py-3 text-xs font-medium text-gray-500 uppercase tracking-wide"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($processos as $proc)
                @php
                    $atrasado = $proc->prazo_final && $proc->prazo_final->isPast() && !in_array($proc->status,['concluido','cancelado']);
                    $ultimoAto = $proc->atos->last();
                    $comigo = $proc->com_user_id === auth()->id();
                    $rowBg = $atrasado ? 'bg-red-50/60' : ($comigo ? 'bg-indigo-50/40' : '');
                    $leftBorder = $atrasado ? 'border-l-4 border-l-red-400' : ($comigo ? 'border-l-4 border-l-indigo-400' : '');
                @endphp
                <tr class="hover:bg-gray-50/80 transition-colors {{ $rowBg }} {{ $leftBorder }}">
                    <td class="px-4 py-3">
                        <span class="font-mono text-xs font-semibold text-[#385776]">{{ $proc->protocolo }}</span>
                        @if($atrasado)
                            <span class="ml-1.5 inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-semibold bg-red-100 text-red-600">
                                ⚠ Atrasado
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800 leading-tight">{{ Str::limit($proc->titulo, 50) }}</p>
                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $proc->tipoColor() }}">
                                {{ $proc->tipoIcon() }} {{ $proc->tipoLabel() }}
                            </span>
                            <span class="text-xs text-gray-400">· {{ $proc->account->name ?? '-' }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $proc->statusColor() }}">
                            {{ $proc->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($comigo)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500"></span> Você
                            </span>
                        @else
                            <span class="text-sm text-gray-600">{{ $proc->comUsuario->name ?? $proc->owner->name ?? '-' }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($proc->atos->count() > 0)
                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-gray-100 text-xs font-semibold text-gray-600">
                                {{ $proc->atos->count() }}
                            </span>
                        @else
                            <span class="text-gray-300 text-sm">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($ultimoAto)
                            <p class="text-xs text-gray-600 leading-tight">{{ Str::limit($ultimoAto->titulo, 30) }}</p>
                            <p class="text-[10px] text-gray-400 mt-0.5">{{ $ultimoAto->created_at->format('d/m/Y') }}</p>
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('crm.admin-processes.show', $proc->id) }}"
                           class="inline-flex items-center gap-1 text-xs font-medium px-3 py-1.5 rounded-lg bg-[#1B334A] text-white hover:bg-[#385776] transition-colors">
                            Abrir
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-12 text-center text-gray-400 text-sm">
                        Nenhum processo encontrado.
                        <a href="{{ route('crm.admin-processes.create') }}" class="text-[#385776] hover:underline ml-1">Criar o primeiro</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>

        @if($processos->hasPages())
        <div class="px-4 py-3 border-t bg-gray-50">
            {{ $processos->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
