@extends('layouts.app')
@section('title', 'Editar — ' . $processo->protocolo)

@section('content')
<div class="w-full px-6 py-6">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.admin-processes.index') }}" class="hover:text-[#385776]">Processos Administrativos</a>
        <span>›</span>
        <a href="{{ route('crm.admin-processes.show', $processo->id) }}" class="hover:text-[#385776]">{{ $processo->protocolo }}</a>
        <span>›</span>
        <span class="text-gray-700 font-medium">Editar</span>
    </div>

    <h1 class="text-2xl font-bold text-[#1B334A] mb-6">Editar Processo — {{ $processo->protocolo }}</h1>

    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
            <ul class="list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('crm.admin-processes.update', $processo->id) }}">
        @csrf
        @method('PUT')

        <div class="space-y-6">

            {{-- Dados gerais --}}
            <div class="bg-white rounded-xl border shadow-sm px-6 py-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Dados gerais</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                        <input type="text" name="titulo" value="{{ old('titulo', $processo->titulo) }}" required
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Responsável *</label>
                        <select name="owner_user_id" required
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}" @selected(old('owner_user_id', $processo->owner_user_id) == $u->id)>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prioridade *</label>
                        <select name="prioridade" required
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                            @foreach(['baixa'=>'Baixa','normal'=>'Normal','alta'=>'Alta','urgente'=>'Urgente'] as $v=>$l)
                                <option value="{{ $v }}" @selected(old('prioridade', $processo->prioridade) === $v)>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Órgão destino</label>
                        <input type="text" name="orgao_destino" value="{{ old('orgao_destino', $processo->orgao_destino) }}"
                               placeholder="Ex: 3º Cartório de Registro de Imóveis"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Número externo</label>
                        <input type="text" name="numero_externo" value="{{ old('numero_externo', $processo->numero_externo) }}"
                               placeholder="Protocolo no cartório/órgão"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea name="descricao" rows="3"
                                  class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">{{ old('descricao', $processo->descricao) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Prazos e valores --}}
            <div class="bg-white rounded-xl border shadow-sm px-6 py-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">Prazos e valores</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prazo estimado</label>
                        <input type="date" name="prazo_estimado" value="{{ old('prazo_estimado', $processo->prazo_estimado?->format('Y-m-d')) }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prazo final (hard)</label>
                        <input type="date" name="prazo_final" value="{{ old('prazo_final', $processo->prazo_final?->format('Y-m-d')) }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Honorários (R$)</label>
                        <input type="number" step="0.01" name="valor_honorarios"
                               value="{{ old('valor_honorarios', $processo->valor_honorarios) }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Despesas (R$)</label>
                        <input type="number" step="0.01" name="valor_despesas"
                               value="{{ old('valor_despesas', $processo->valor_despesas) }}"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                </div>
            </div>

            {{-- Info somente leitura --}}
            <div class="bg-gray-50 rounded-xl border px-6 py-4">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400">Protocolo</p>
                        <p class="font-mono font-semibold text-gray-700">{{ $processo->protocolo }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Tipo</p>
                        <p class="text-gray-700">{{ $processo->tipoLabel() }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Cliente</p>
                        <p class="text-gray-700">{{ $processo->account->name ?? '-' }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-400">Criado em</p>
                        <p class="text-gray-700">{{ $processo->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('crm.admin-processes.show', $processo->id) }}"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-white border rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-5 py-2.5 text-sm text-white bg-[#1B334A] rounded-lg hover:bg-[#385776] font-medium">
                    Salvar alterações
                </button>
            </div>

        </div>
    </form>
</div>
@endsection
