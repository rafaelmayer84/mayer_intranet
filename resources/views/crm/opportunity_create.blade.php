@extends('layouts.app')

@section('title', 'CRM - Nova Oportunidade')

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('crm.pipeline') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-xl font-bold text-gray-800">Nova Oportunidade</h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
            <ul class="text-sm text-red-700 list-disc list-inside">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('crm.opportunity.store') }}" class="bg-white rounded-xl border border-gray-200 p-6 space-y-5">
        @csrf

        {{-- Dados do contato --}}
        <div class="border-b border-gray-100 pb-4">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Contato</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Nome do contato *</label>
                    <input type="text" name="account_name" value="{{ old('account_name') }}" required
                           id="account_name_input"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="Digite para buscar ou criar novo" autocomplete="off">
                    <div id="account_suggestions" class="hidden absolute z-10 bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-40 overflow-y-auto"></div>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Tipo</label>
                    <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]">
                        <option value="PF" {{ old('type') == 'PF' ? 'selected' : '' }}>Pessoa Física</option>
                        <option value="PJ" {{ old('type') == 'PJ' ? 'selected' : '' }}>Pessoa Jurídica</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Telefone</label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="(47) 99999-9999">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">E-mail</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="email@exemplo.com">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">CPF/CNPJ</label>
                    <input type="text" name="doc" value="{{ old('doc') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="000.000.000-00">
                </div>
            </div>
        </div>

        {{-- Dados da oportunidade --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Oportunidade</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600 mb-1">Título *</label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="Ex: Ação trabalhista - João Silva">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Área do Direito</label>
                    <select name="area" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]">
                        <option value="">Selecione...</option>
                        <option value="Trabalhista" {{ old('area') == 'Trabalhista' ? 'selected' : '' }}>Trabalhista</option>
                        <option value="Cível" {{ old('area') == 'Cível' ? 'selected' : '' }}>Cível</option>
                        <option value="Família" {{ old('area') == 'Família' ? 'selected' : '' }}>Família</option>
                        <option value="Previdenciário" {{ old('area') == 'Previdenciário' ? 'selected' : '' }}>Previdenciário</option>
                        <option value="Tributário" {{ old('area') == 'Tributário' ? 'selected' : '' }}>Tributário</option>
                        <option value="Empresarial" {{ old('area') == 'Empresarial' ? 'selected' : '' }}>Empresarial</option>
                        <option value="Criminal" {{ old('area') == 'Criminal' ? 'selected' : '' }}>Criminal</option>
                        <option value="Imobiliário" {{ old('area') == 'Imobiliário' ? 'selected' : '' }}>Imobiliário</option>
                        <option value="Consumidor" {{ old('area') == 'Consumidor' ? 'selected' : '' }}>Consumidor</option>
                        <option value="Outro" {{ old('area') == 'Outro' ? 'selected' : '' }}>Outro</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Origem</label>
                    <select name="source" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]">
                        <option value="manual" {{ old('source') == 'manual' ? 'selected' : '' }}>Cadastro manual</option>
                        <option value="whatsapp" {{ old('source') == 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                        <option value="indicacao" {{ old('source') == 'indicacao' ? 'selected' : '' }}>Indicação</option>
                        <option value="google" {{ old('source') == 'google' ? 'selected' : '' }}>Google Ads</option>
                        <option value="site" {{ old('source') == 'site' ? 'selected' : '' }}>Site</option>
                        <option value="redes_sociais" {{ old('source') == 'redes_sociais' ? 'selected' : '' }}>Redes Sociais</option>
                        <option value="recorrente" {{ old('source') == 'recorrente' ? 'selected' : '' }}>Cliente recorrente</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Valor estimado (R$)</label>
                    <input type="number" name="value_estimated" step="0.01" value="{{ old('value_estimated') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]"
                           placeholder="0,00">
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Estágio inicial</label>
                    <select name="stage_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]">
                        @foreach($stages as $stg)
                            <option value="{{ $stg->id }}" {{ $loop->first ? 'selected' : '' }}>{{ $stg->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Responsável</label>
                    <select name="owner_user_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]">
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" {{ $user->id == auth()->id() ? 'selected' : '' }}>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Próxima ação</label>
                    <input type="datetime-local" name="next_action_at" value="{{ old('next_action_at') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-[#385776] focus:border-[#385776]">
                </div>
            </div>
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-6 py-2.5 bg-[#385776] text-white rounded-lg hover:bg-[#1B334A] text-sm font-medium">
                Criar Oportunidade
            </button>
            <a href="{{ route('crm.pipeline') }}" class="px-6 py-2.5 border border-gray-300 rounded-lg hover:bg-gray-50 text-sm">
                Cancelar
            </a>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const input = document.getElementById('account_name_input');
    const suggestions = document.getElementById('account_suggestions');
    let debounce = null;

    if (!input || !suggestions) return;

    input.addEventListener('input', function() {
        clearTimeout(debounce);
        const val = this.value.trim();
        if (val.length < 2) { suggestions.classList.add('hidden'); return; }

        debounce = setTimeout(() => {
            fetch('{{ route("crm.accounts.search") }}?q=' + encodeURIComponent(val))
                .then(r => r.json())
                .then(data => {
                    if (data.length === 0) { suggestions.classList.add('hidden'); return; }
                    suggestions.innerHTML = data.map(a =>
                        '<div class="px-3 py-2 text-sm cursor-pointer hover:bg-gray-50" data-name="' + a.name + '">' +
                        '<span class="font-medium">' + a.name + '</span>' +
                        '<span class="text-xs text-gray-400 ml-2">' + a.type + '</span>' +
                        '</div>'
                    ).join('');
                    suggestions.classList.remove('hidden');

                    suggestions.querySelectorAll('[data-name]').forEach(el => {
                        el.addEventListener('click', function() {
                            input.value = this.dataset.name;
                            suggestions.classList.add('hidden');
                        });
                    });
                });
        }, 300);
    });

    document.addEventListener('click', function(e) {
        if (!suggestions.contains(e.target) && e.target !== input) {
            suggestions.classList.add('hidden');
        }
    });
});
</script>
@endsection
