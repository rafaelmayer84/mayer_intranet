@extends('layouts.app')
@section('title', 'CRM — Nova Conta')

@section('content')
<div class="max-w-2xl mx-auto px-6 py-6">
    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-5">
        <a href="{{ route('crm.carteira') }}" class="hover:text-[#385776]">Carteira</a>
        <span>›</span>
        <span class="text-gray-700 font-medium">Nova Conta</span>
    </div>

    <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
        <div class="bg-gradient-to-r from-[#1B334A] to-[#385776] px-6 py-4">
            <h1 class="text-xl font-bold text-white">Criar Conta Manualmente</h1>
            <p class="text-white/60 text-sm mt-0.5">Cadastro direto sem vinculação ao DataJuri</p>
        </div>

        <form method="POST" action="{{ route('crm.accounts.store') }}" class="px-6 py-6 space-y-5">
            @csrf

            @if($errors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            @endif

            {{-- Tipo e Nome --}}
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Tipo <span class="text-red-500">*</span></label>
                    <select name="kind" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]" required>
                        <option value="client" {{ old('kind') === 'client' ? 'selected' : '' }}>Cliente</option>
                        <option value="prospect" {{ old('kind') === 'prospect' ? 'selected' : '' }}>Prospect</option>
                    </select>
                </div>
                <div class="col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Nome completo <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required maxlength="255"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="Nome do cliente ou empresa">
                </div>
            </div>

            {{-- Contato --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">E-mail</label>
                    <input type="email" name="email" value="{{ old('email') }}" maxlength="255"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="email@exemplo.com">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Telefone</label>
                    <input type="text" name="phone_e164" value="{{ old('phone_e164') }}" maxlength="30"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="5549999999999">
                </div>
            </div>

            {{-- Doc e DataJuri --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">CPF / CNPJ</label>
                    <input type="text" name="doc_digits" value="{{ old('doc_digits') }}" maxlength="20"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="Somente dígitos">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">ID DataJuri (pessoa_id)</label>
                    <input type="number" name="datajuri_pessoa_id" value="{{ old('datajuri_pessoa_id') }}" min="1"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="Opcional — vincula ao DataJuri">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Profissão</label>
                    <input type="text" name="profissao" value="{{ old('profissao') }}" maxlength="255"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="Ex: Engenheiro">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Nascimento</label>
                    <input type="date" name="data_nascimento" value="{{ old('data_nascimento') }}"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Cidade</label>
                    <input type="text" name="endereco_cidade" value="{{ old('endereco_cidade') }}" maxlength="100"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">UF</label>
                    <input type="text" name="endereco_estado" value="{{ old('endereco_estado') }}" maxlength="2"
                           class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="SC">
                </div>
            </div>

            {{-- Responsável e Lifecycle --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Responsável</label>
                    <select name="owner_user_id" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]">
                        <option value="">— Sem responsável —</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ old('owner_user_id') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 mb-1">Lifecycle</label>
                    <select name="lifecycle" class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]">
                        <option value="onboarding" {{ (old('lifecycle','onboarding') === 'onboarding') ? 'selected' : '' }}>Onboarding</option>
                        <option value="ativo"      {{ old('lifecycle') === 'ativo'      ? 'selected' : '' }}>Ativo</option>
                        <option value="adormecido" {{ old('lifecycle') === 'adormecido' ? 'selected' : '' }}>Adormecido</option>
                        <option value="risco"      {{ old('lifecycle') === 'risco'      ? 'selected' : '' }}>Risco</option>
                    </select>
                </div>
            </div>

            {{-- Notas --}}
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Notas internas</label>
                <textarea name="notes" rows="3" maxlength="5000"
                          class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-1 focus:ring-[#385776] focus:border-[#385776]"
                          placeholder="Observações sobre o cliente...">{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('crm.carteira') }}" class="px-4 py-2 text-sm text-gray-500 border rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-6 py-2 text-sm text-white font-semibold rounded-lg"
                        style="background:linear-gradient(135deg,#1B334A,#385776)">
                    Criar Conta
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
