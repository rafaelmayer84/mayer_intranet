@extends('layouts.app')
@section('title', 'Advogados SISRH')
@section('content')
<div class="max-w-7xl mx-auto" x-data="advogadosApp()">

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

    {{-- Grid de Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($advogados as $adv)
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 hover:shadow-md transition-shadow overflow-hidden">
            {{-- Header do card --}}
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold" style="background-color: {{ $adv->vinculo_id && $adv->v_ativo ? '#385776' : ($adv->vinculo_id ? '#dc2626' : '#d97706') }};">
                        {{ strtoupper(substr($adv->name, 0, 1)) }}{{ strtoupper(substr(strstr($adv->name, ' ') ?: '', 1, 1)) }}
                    </div>
                    <div>
                        <p class="font-semibold text-gray-800 text-sm leading-tight">{{ $adv->name }}</p>
                        <span class="px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $adv->role }}</span>
                    </div>
                </div>
                @if($adv->vinculo_id && $adv->v_ativo)
                <span class="px-2 py-0.5 rounded text-xs bg-green-100 text-green-700 font-medium">Ativo</span>
                @elseif($adv->vinculo_id && !$adv->v_ativo)
                <span class="px-2 py-0.5 rounded text-xs bg-red-100 text-red-700 font-medium">Inativo</span>
                @else
                <span class="px-2 py-0.5 rounded text-xs bg-yellow-100 text-yellow-700 font-medium">Sem vínculo</span>
                @endif
            </div>

            {{-- Corpo do card --}}
            <div class="px-4 py-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Senioridade</span>
                    @if($adv->v_nivel)
                    <span class="px-2 py-0.5 rounded text-xs bg-blue-100 text-blue-700">{{ str_replace('_', ' ', $adv->v_nivel) }}</span>
                    @else
                    <span class="text-gray-400 text-xs">—</span>
                    @endif
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Início</span>
                    <span class="text-gray-700 text-xs">{{ $adv->data_inicio_exercicio ? date('d/m/Y', strtotime($adv->data_inicio_exercicio)) : '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">CPF</span>
                    <span class="text-gray-700 text-xs">{{ $adv->cpf ?? '—' }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">OAB</span>
                    <span class="text-gray-700 text-xs">{{ $adv->oab ?? '—' }}</span>
                </div>
            </div>

            {{-- Footer do card --}}
            <div class="px-4 py-3 border-t border-gray-100 flex gap-2 justify-end" style="background-color: #fafbfc;">
                <a href="{{ route('sisrh.documentos', $adv->id) }}" class="px-3 py-1.5 rounded text-xs border border-gray-300 text-gray-600 hover:bg-gray-50" title="Documentos">📄 Docs</a>
                @if(!$adv->vinculo_id)
                <button @click="openModal('ativar', {{ json_encode([
                    'user_id' => $adv->id,
                    'name' => $adv->name,
                ]) }})" class="px-3 py-1.5 rounded text-white text-xs" style="background-color: #385776;">Ativar</button>
                @elseif($adv->v_ativo)
                <button @click="openModal('editar', {{ json_encode([
                    'vinculo_id' => $adv->vinculo_id,
                    'name' => $adv->name,
                    'nivel_senioridade' => $adv->v_nivel,
                    'data_inicio_exercicio' => $adv->data_inicio_exercicio,
                    'cpf' => $adv->cpf,
                    'oab' => $adv->oab,
                    'rg' => $adv->rg,
                    'endereco_rua' => $adv->endereco_rua,
                    'endereco_numero' => $adv->endereco_numero,
                    'endereco_complemento' => $adv->endereco_complemento,
                    'endereco_bairro' => $adv->endereco_bairro,
                    'endereco_cep' => $adv->endereco_cep,
                    'endereco_cidade' => $adv->endereco_cidade,
                    'endereco_estado' => $adv->endereco_estado,
                    'nome_pai' => $adv->nome_pai,
                    'nome_mae' => $adv->nome_mae,
                    'equipe_id' => $adv->equipe_id,
                    'observacoes' => $adv->observacoes,
                ]) }})" class="px-3 py-1.5 rounded text-xs border hover:bg-blue-50" style="color: #385776; border-color: #385776;">Editar</button>
                <form action="{{ route('sisrh.advogado.desativar', $adv->vinculo_id) }}" method="POST" class="inline" onsubmit="return confirm('Desativar este advogado no SISRH?')">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded text-xs text-red-600 border border-red-300 hover:bg-red-50">Desativar</button>
                </form>
                @else
                <form action="{{ route('sisrh.advogado.reativar', $adv->vinculo_id) }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 rounded text-xs text-green-600 border border-green-300 hover:bg-green-50">Reativar</button>
                </form>
                <button @click="openModal('editar', {{ json_encode([
                    'vinculo_id' => $adv->vinculo_id,
                    'name' => $adv->name,
                    'nivel_senioridade' => $adv->v_nivel,
                    'data_inicio_exercicio' => $adv->data_inicio_exercicio,
                    'cpf' => $adv->cpf,
                    'oab' => $adv->oab,
                    'rg' => $adv->rg,
                    'endereco_rua' => $adv->endereco_rua,
                    'endereco_numero' => $adv->endereco_numero,
                    'endereco_complemento' => $adv->endereco_complemento,
                    'endereco_bairro' => $adv->endereco_bairro,
                    'endereco_cep' => $adv->endereco_cep,
                    'endereco_cidade' => $adv->endereco_cidade,
                    'endereco_estado' => $adv->endereco_estado,
                    'nome_pai' => $adv->nome_pai,
                    'nome_mae' => $adv->nome_mae,
                    'equipe_id' => $adv->equipe_id,
                    'observacoes' => $adv->observacoes,
                ]) }})" class="px-3 py-1.5 rounded text-xs border hover:bg-blue-50" style="color: #385776; border-color: #385776;">Editar</button>
                @endif
            </div>
        </div>
        @endforeach
    </div>

    {{-- ═══════════════════════════════════════════ --}}
    {{-- MODAL ATIVAR --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div x-show="modalType === 'ativar'" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         @keydown.escape.window="closeModal()">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="closeModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-2xl mx-4 max-h-[90vh] overflow-y-auto z-10"
             @click.stop>
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 rounded-t-xl flex items-center justify-between" style="background: linear-gradient(135deg, #385776 0%, #1B334A 100%);">
                <h2 class="text-lg font-bold text-white">Ativar <span x-text="modalData.name"></span> no SISRH</h2>
                <button @click="closeModal()" class="text-white hover:text-gray-200 text-xl leading-none">&times;</button>
            </div>
            <form action="{{ route('sisrh.advogado.ativar') }}" method="POST" class="p-6">
                @csrf
                <input type="hidden" name="user_id" :value="modalData.user_id">
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Senioridade *</label>
                        <select name="nivel_senioridade" class="border rounded px-3 py-2 text-sm w-full" required>
                            <option value="">Selecione...</option>
                            <option value="Junior">Júnior</option>
                            <option value="Pleno">Pleno</option>
                            <option value="Senior_I">Sênior I</option>
                            <option value="Senior_II">Sênior II</option>
                            <option value="Senior_III">Sênior III</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Data Início Exercício</label>
                        <input type="date" name="data_inicio_exercicio" class="border rounded px-3 py-2 text-sm w-full">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">CPF</label>
                        <input type="text" name="cpf" maxlength="20" class="border rounded px-3 py-2 text-sm w-full" placeholder="000.000.000-00">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">OAB</label>
                        <input type="text" name="oab" maxlength="30" class="border rounded px-3 py-2 text-sm w-full" placeholder="SC 00000">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">RG</label>
                        <input type="text" name="rg" maxlength="30" class="border rounded px-3 py-2 text-sm w-full">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Equipe ID</label>
                        <input type="number" name="equipe_id" class="border rounded px-3 py-2 text-sm w-full">
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-xs text-gray-500 mb-1">Observações</label>
                    <textarea name="observacoes" maxlength="500" rows="2" class="border rounded px-3 py-2 text-sm w-full" placeholder="Opcional"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2 border-t">
                    <button type="button" @click="closeModal()" class="px-4 py-2 rounded text-sm border text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" class="px-6 py-2 rounded text-white text-sm font-medium" style="background-color: #385776;">Confirmar Ativação</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════ --}}
    {{-- MODAL EDITAR --}}
    {{-- ═══════════════════════════════════════════ --}}
    <div x-show="modalType === 'editar'" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         @keydown.escape.window="closeModal()">
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity" @click="closeModal()"></div>
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto z-10"
             @click.stop>
            <div class="sticky top-0 bg-white px-6 py-4 border-b border-gray-200 rounded-t-xl flex items-center justify-between" style="background: linear-gradient(135deg, #385776 0%, #1B334A 100%);">
                <h2 class="text-lg font-bold text-white">Editar <span x-text="modalData.name"></span></h2>
                <button @click="closeModal()" class="text-white hover:text-gray-200 text-xl leading-none">&times;</button>
            </div>
            <form :action="'/sisrh/advogados/' + modalData.vinculo_id" method="POST" class="p-6">
                @csrf
                @method('PUT')

                {{-- Dados profissionais --}}
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Dados Profissionais</p>
                <div class="grid grid-cols-3 gap-4 mb-5">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Senioridade *</label>
                        <select name="nivel_senioridade" class="border rounded px-3 py-2 text-sm w-full" required x-model="modalData.nivel_senioridade">
                            <option value="Junior">Júnior</option>
                            <option value="Pleno">Pleno</option>
                            <option value="Senior_I">Sênior I</option>
                            <option value="Senior_II">Sênior II</option>
                            <option value="Senior_III">Sênior III</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Data Início</label>
                        <input type="date" name="data_inicio_exercicio" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.data_inicio_exercicio">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Equipe ID</label>
                        <input type="number" name="equipe_id" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.equipe_id">
                    </div>
                </div>

                {{-- Documentos --}}
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Documentos</p>
                <div class="grid grid-cols-3 gap-4 mb-5">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">CPF</label>
                        <input type="text" name="cpf" maxlength="20" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.cpf">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">OAB</label>
                        <input type="text" name="oab" maxlength="30" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.oab">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">RG</label>
                        <input type="text" name="rg" maxlength="30" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.rg">
                    </div>
                </div>

                {{-- Endereço --}}
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Endereço</p>
                <div class="grid grid-cols-4 gap-4 mb-2">
                    <div class="col-span-2">
                        <label class="block text-xs text-gray-500 mb-1">Rua</label>
                        <input type="text" name="endereco_rua" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.endereco_rua">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Número</label>
                        <input type="text" name="endereco_numero" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.endereco_numero">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Complemento</label>
                        <input type="text" name="endereco_complemento" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.endereco_complemento">
                    </div>
                </div>
                <div class="grid grid-cols-4 gap-4 mb-5">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Bairro</label>
                        <input type="text" name="endereco_bairro" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.endereco_bairro">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">CEP</label>
                        <input type="text" name="endereco_cep" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.endereco_cep">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Cidade</label>
                        <input type="text" name="endereco_cidade" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.endereco_cidade">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">UF</label>
                        <input type="text" name="endereco_estado" maxlength="2" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.endereco_estado">
                    </div>
                </div>

                {{-- Filiação --}}
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Filiação</p>
                <div class="grid grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nome do Pai</label>
                        <input type="text" name="nome_pai" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.nome_pai">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nome da Mãe</label>
                        <input type="text" name="nome_mae" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.nome_mae">
                    </div>
                </div>

                {{-- Observações --}}
                <div class="mb-4">
                    <label class="block text-xs text-gray-500 mb-1">Observações</label>
                    <textarea name="observacoes" maxlength="500" rows="2" class="border rounded px-3 py-2 text-sm w-full" x-model="modalData.observacoes"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-3 border-t">
                    <button type="button" @click="closeModal()" class="px-4 py-2 rounded text-sm border text-gray-600 hover:bg-gray-50">Cancelar</button>
                    <button type="submit" class="px-6 py-2 rounded text-white text-sm font-medium" style="background-color: #385776;">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function advogadosApp() {
    return {
        modalType: null,
        modalData: {},
        openModal(type, data) {
            this.modalType = type;
            this.modalData = data || {};
            document.body.style.overflow = 'hidden';
        },
        closeModal() {
            this.modalType = null;
            this.modalData = {};
            document.body.style.overflow = '';
        }
    };
}
</script>
@endpush
@endsection
