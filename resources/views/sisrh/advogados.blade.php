@extends('layouts.app')
@section('title', 'Advogados SISRH')
@section('content')
<div class="max-w-7xl mx-auto">

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold text-gray-800">Cadastro de Advogados</h1>
        <a href="{{ route('sisrh.index') }}" class="text-sm underline" style="color: #385776;">← Voltar</a>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 rounded px-4 py-2 mb-4 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Filtros --}}
    <form method="GET" class="flex items-center gap-3 mb-6">
        <input type="text" name="nome" value="{{ request('nome') }}" placeholder="Buscar por nome..." class="border rounded px-3 py-1.5 text-sm w-56">
        <select name="role" class="border rounded px-2 py-1.5 text-sm">
            <option value="">Todas as roles</option>
            <option value="admin" {{ request('role')=='admin'?'selected':'' }}>Admin</option>
            <option value="coordenador" {{ request('role')=='coordenador'?'selected':'' }}>Coordenador</option>
            <option value="socio" {{ request('role')=='socio'?'selected':'' }}>Sócio</option>
            <option value="advogado" {{ request('role')=='advogado'?'selected':'' }}>Advogado</option>
        </select>
        <select name="status" class="border rounded px-2 py-1.5 text-sm">
            <option value="">Todos os status</option>
            <option value="ativo" {{ request('status')=='ativo'?'selected':'' }}>Ativo no SISRH</option>
            <option value="inativo" {{ request('status')=='inativo'?'selected':'' }}>Inativo</option>
            <option value="sem_vinculo" {{ request('status')=='sem_vinculo'?'selected':'' }}>Sem vínculo</option>
        </select>
        <button type="submit" class="px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Filtrar</button>
        @if(request()->hasAny(['nome','role','status']))
        <a href="{{ route('sisrh.advogados') }}" class="text-xs text-gray-500 underline">Limpar</a>
        @endif
    </form>

    {{-- Tabela --}}
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr style="background-color: #385776;">
                    <th class="px-3 py-2 text-left text-white">Nome</th>
                    <th class="px-3 py-2 text-center text-white">Role</th>
                    <th class="px-3 py-2 text-center text-white">CPF</th>
                    <th class="px-3 py-2 text-center text-white">OAB</th>
                    <th class="px-3 py-2 text-center text-white">Senioridade</th>
                    <th class="px-3 py-2 text-center text-white">Início</th>
                    <th class="px-3 py-2 text-center text-white">Status SISRH</th>
                    <th class="px-3 py-2 text-center text-white">Ações</th>
                </tr>
            </thead>
            <tbody>
                @foreach($advogados as $adv)
                <tr class="border-b border-gray-100 hover:bg-gray-50" x-data="{ showEdit: false, showAtivar: false }">
                    <td class="px-3 py-2 font-medium text-gray-800">{{ $adv->name }}</td>
                    <td class="px-3 py-2 text-center"><span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $adv->role }}</span></td>
                    <td class="px-3 py-2 text-center text-gray-600 text-xs">{{ $adv->cpf ?? '—' }}</td>
                    <td class="px-3 py-2 text-center text-gray-600 text-xs">{{ $adv->oab ?? '—' }}</td>
                    <td class="px-3 py-2 text-center">
                        @if($adv->v_nivel)
                        <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">{{ str_replace('_', ' ', $adv->v_nivel) }}</span>
                        @else
                        <span class="text-gray-400 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center text-gray-600 text-xs">{{ $adv->data_inicio_exercicio ? date('d/m/Y', strtotime($adv->data_inicio_exercicio)) : '—' }}</td>
                    <td class="px-3 py-2 text-center">
                        @if($adv->vinculo_id && $adv->v_ativo)
                        <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700 font-medium">Ativo</span>
                        @elseif($adv->vinculo_id && !$adv->v_ativo)
                        <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700 font-medium">Inativo</span>
                        @else
                        <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700 font-medium">Sem vínculo</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-center">
                        <div class="flex gap-1 justify-center">
                            @if(!$adv->vinculo_id)
                            <a href="{{ route('sisrh.documentos', $adv->id) }}" class="px-2 py-1 rounded text-xs border border-gray-300 text-gray-600" title="Documentos">📄</a>
                            <button @click="showAtivar = !showAtivar" class="px-2 py-1 rounded text-white text-xs" style="background-color: #385776;">Ativar</button>
                            @elseif($adv->v_ativo)
                            <a href="{{ route('sisrh.documentos', $adv->id) }}" class="px-2 py-1 rounded text-xs border border-gray-300 text-gray-600" title="Documentos">📄</a>
                            <button @click="showEdit = !showEdit" class="px-2 py-1 rounded text-xs border" style="color: #385776; border-color: #385776;">Editar</button>
                            <form action="{{ route('sisrh.advogado.desativar', $adv->vinculo_id) }}" method="POST" class="inline" onsubmit="return confirm('Desativar este advogado no SISRH?')">
                                @csrf
                                <button type="submit" class="px-2 py-1 rounded text-xs text-red-600 border border-red-300">Desativar</button>
                            </form>
                            @else
                            <a href="{{ route('sisrh.documentos', $adv->id) }}" class="px-2 py-1 rounded text-xs border border-gray-300 text-gray-600" title="Documentos">📄</a>
                            <form action="{{ route('sisrh.advogado.reativar', $adv->vinculo_id) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-2 py-1 rounded text-xs text-green-600 border border-green-300">Reativar</button>
                            </form>
                            <button @click="showEdit = !showEdit" class="px-2 py-1 rounded text-xs border" style="color: #385776; border-color: #385776;">Editar</button>
                            @endif
                        </div>
                    </td>
                </tr>

                {{-- Modal Ativar --}}
                @if(!$adv->vinculo_id)
                <tr x-show="showAtivar" x-cloak>
                    <td colspan="8" class="px-4 py-4 bg-blue-50 border-b border-blue-200">
                        <form action="{{ route('sisrh.advogado.ativar') }}" method="POST">
                            @csrf
                            <input type="hidden" name="user_id" value="{{ $adv->id }}">
                            <p class="text-sm font-semibold text-gray-700 mb-3">Ativar {{ $adv->name }} no SISRH</p>
                            <div class="grid grid-cols-4 gap-3 mb-3">
                                <div>
                                    <label class="text-xs text-gray-500">Senioridade *</label>
                                    <select name="nivel_senioridade" class="border rounded px-2 py-1.5 text-sm w-full" required>
                                        <option value="">Selecione...</option>
                                        <option value="Junior">Júnior</option>
                                        <option value="Pleno">Pleno</option>
                                        <option value="Senior_I">Sênior I</option>
                                        <option value="Senior_II">Sênior II</option>
                                        <option value="Senior_III">Sênior III</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Data Início Exercício</label>
                                    <input type="date" name="data_inicio_exercicio" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">CPF</label>
                                    <input type="text" name="cpf" maxlength="20" class="border rounded px-2 py-1.5 text-sm w-full" placeholder="000.000.000-00">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">OAB</label>
                                    <input type="text" name="oab" maxlength="30" class="border rounded px-2 py-1.5 text-sm w-full" placeholder="SC 00000">
                                </div>
                            </div>
                            <div class="grid grid-cols-4 gap-3 mb-3">
                                <div>
                                    <label class="text-xs text-gray-500">RG</label>
                                    <input type="text" name="rg" maxlength="30" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs text-gray-500">Observações</label>
                                    <input type="text" name="observacoes" maxlength="500" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Equipe ID</label>
                                    <input type="number" name="equipe_id" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Confirmar Ativação</button>
                                <button type="button" @click="showAtivar = false" class="px-4 py-1.5 rounded text-sm border text-gray-600">Cancelar</button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endif

                {{-- Modal Editar --}}
                @if($adv->vinculo_id)
                <tr x-show="showEdit" x-cloak>
                    <td colspan="8" class="px-4 py-4 bg-gray-50 border-b border-gray-200">
                        <form action="{{ route('sisrh.advogado.editar', $adv->vinculo_id) }}" method="POST">
                            @csrf @method('PUT')
                            <p class="text-sm font-semibold text-gray-700 mb-3">Editar {{ $adv->name }}</p>
                            <div class="grid grid-cols-5 gap-3 mb-3">
                                <div>
                                    <label class="text-xs text-gray-500">Senioridade *</label>
                                    <select name="nivel_senioridade" class="border rounded px-2 py-1.5 text-sm w-full" required>
                                        @foreach(['Junior'=>'Júnior','Pleno'=>'Pleno','Senior_I'=>'Sênior I','Senior_II'=>'Sênior II','Senior_III'=>'Sênior III'] as $k=>$v)
                                        <option value="{{ $k }}" {{ $adv->v_nivel==$k?'selected':'' }}>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Data Início</label>
                                    <input type="date" name="data_inicio_exercicio" value="{{ $adv->data_inicio_exercicio }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">CPF</label>
                                    <input type="text" name="cpf" value="{{ $adv->cpf }}" maxlength="20" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">OAB</label>
                                    <input type="text" name="oab" value="{{ $adv->oab }}" maxlength="30" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">RG</label>
                                    <input type="text" name="rg" value="{{ $adv->rg }}" maxlength="30" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-5 gap-3 mb-3">
                                <div class="col-span-2">
                                    <label class="text-xs text-gray-500">Rua</label>
                                    <input type="text" name="endereco_rua" value="{{ $adv->endereco_rua }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Número</label>
                                    <input type="text" name="endereco_numero" value="{{ $adv->endereco_numero }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Complemento</label>
                                    <input type="text" name="endereco_complemento" value="{{ $adv->endereco_complemento }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Bairro</label>
                                    <input type="text" name="endereco_bairro" value="{{ $adv->endereco_bairro }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-5 gap-3 mb-3">
                                <div>
                                    <label class="text-xs text-gray-500">CEP</label>
                                    <input type="text" name="endereco_cep" value="{{ $adv->endereco_cep }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Cidade</label>
                                    <input type="text" name="endereco_cidade" value="{{ $adv->endereco_cidade }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">UF</label>
                                    <input type="text" name="endereco_estado" value="{{ $adv->endereco_estado }}" maxlength="2" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Nome do Pai</label>
                                    <input type="text" name="nome_pai" value="{{ $adv->nome_pai }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div>
                                    <label class="text-xs text-gray-500">Nome da Mãe</label>
                                    <input type="text" name="nome_mae" value="{{ $adv->nome_mae }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-3 mb-3">
                                <div>
                                    <label class="text-xs text-gray-500">Equipe ID</label>
                                    <input type="number" name="equipe_id" value="{{ $adv->equipe_id }}" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                                <div class="col-span-2">
                                    <label class="text-xs text-gray-500">Observações</label>
                                    <input type="text" name="observacoes" value="{{ $adv->observacoes }}" maxlength="500" class="border rounded px-2 py-1.5 text-sm w-full">
                                </div>
                            </div>
                            <div class="flex gap-2">
                                <button type="submit" class="px-4 py-1.5 rounded text-white text-sm" style="background-color: #385776;">Salvar</button>
                                <button type="button" @click="showEdit = false" class="px-4 py-1.5 rounded text-sm border text-gray-600">Cancelar</button>
                            </div>
                        </form>
                    </td>
                </tr>
                @endif

                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
