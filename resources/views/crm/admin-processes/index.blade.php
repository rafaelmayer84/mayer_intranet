@extends('layouts.app')
@section('title', 'Processos Administrativos')

@section('content')
<div class="max-w-full mx-auto px-6 py-6">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.dashboard') }}" class="hover:text-[#385776]">CRM</a>
        <span>›</span>
        <span class="text-gray-700 font-medium">Processos Administrativos</span>
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-[#1B334A]">Processos Administrativos</h1>
            <p class="text-sm text-gray-500 mt-0.5">Gestão de processos extrajudiciais e cartorários</p>
        </div>
        <a href="{{ route('crm.admin-processes.create') }}"
           class="inline-flex items-center gap-2 bg-[#1B334A] text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-[#385776] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Novo Processo
        </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Ativos</p>
            <p class="text-2xl font-bold text-[#1B334A] mt-1">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Na minha mesa</p>
            <p class="text-2xl font-bold text-indigo-600 mt-1">{{ $stats['na_minha_mesa'] }}</p>
        </div>
        <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Em andamento</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">{{ $stats['em_andamento'] }}</p>
        </div>
        <div class="bg-white rounded-xl border shadow-sm px-4 py-4">
            <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Atrasados</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $stats['atrasados'] }}</p>
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
                @endphp
                <tr class="hover:bg-gray-50 transition-colors {{ $comigo ? 'border-l-4 border-l-[#385776]' : '' }}">
                    <td class="px-4 py-3">
                        <span class="font-mono text-xs font-semibold text-[#385776]">{{ $proc->protocolo }}</span>
                        @if($atrasado)
                            <span class="ml-1 inline-block w-2 h-2 rounded-full bg-red-500" title="Atrasado"></span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-gray-800 leading-tight">{{ Str::limit($proc->titulo, 50) }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $proc->tipoLabel() }} · {{ $proc->account->name ?? '-' }}
                        </p>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $proc->statusColor() }}">
                            {{ $proc->statusLabel() }}
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm {{ $comigo ? 'text-[#385776] font-semibold' : 'text-gray-600' }}">
                            {{ $proc->comUsuario->name ?? $proc->owner->name ?? '-' }}
                        </span>
                        @if($comigo)
                            <span class="text-[10px] text-[#385776] block">Você</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-gray-500">{{ $proc->atos->count() }}</span>
                    </td>
                    <td class="px-4 py-3">
                        @if($ultimoAto)
                            <p class="text-xs text-gray-600 leading-tight">{{ Str::limit($ultimoAto->titulo, 30) }}</p>
                            <p class="text-[10px] text-gray-400">{{ $ultimoAto->created_at->format('d/m/Y') }}</p>
                        @else
                            <span class="text-gray-300">-</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('crm.admin-processes.show', $proc->id) }}"
                           class="text-[#385776] hover:underline text-sm font-medium">
                            Abrir
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
