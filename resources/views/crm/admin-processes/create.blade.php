@extends('layouts.app')
@section('title', 'Novo Processo Administrativo')

@section('content')
<div class="max-w-4xl mx-auto px-6 py-6"
     x-data="adminProcessCreate()"
     x-init="@if($accountId) accountId = {{ $accountId }}; @endif">

    {{-- Breadcrumb --}}
    <div class="flex items-center gap-2 text-sm text-gray-500 mb-4">
        <a href="{{ route('crm.admin-processes.index') }}" class="hover:text-[#385776]">Processos Administrativos</a>
        <span>›</span>
        <span class="text-gray-700 font-medium">Novo Processo</span>
    </div>

    <h1 class="text-2xl font-bold text-[#1B334A] mb-6">Novo Processo Administrativo</h1>

    <form method="POST" action="{{ route('crm.admin-processes.store') }}" @submit.prevent="submitForm">
        @csrf

        <div class="space-y-6">

            {{-- STEP 1: Tipo --}}
            <div class="bg-white rounded-xl border shadow-sm px-6 py-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">1. Tipo de processo</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($templates as $tipo => $tpl)
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="{{ $tipo }}" x-model="tipo"
                               @change="loadTemplate('{{ $tipo }}')" class="sr-only">
                        <div :class="tipo==='{{ $tipo }}' ? 'border-[#1B334A] bg-[#1B334A]/5 ring-2 ring-[#1B334A]' : 'border-gray-200 hover:border-gray-300'"
                             class="border rounded-xl p-3 transition-all">
                            <p class="text-sm font-semibold text-gray-800">{{ $tpl['nome'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">~{{ $tpl['prazo_estimado_dias'] }} dias</p>
                        </div>
                    </label>
                    @endforeach
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="outro" x-model="tipo" class="sr-only">
                        <div :class="tipo==='outro' ? 'border-[#1B334A] bg-[#1B334A]/5 ring-2 ring-[#1B334A]' : 'border-gray-200 hover:border-gray-300'"
                             class="border rounded-xl p-3 transition-all">
                            <p class="text-sm font-semibold text-gray-800">Outro</p>
                            <p class="text-xs text-gray-500 mt-0.5">Personalizado</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- STEP 2: Dados gerais --}}
            <div class="bg-white rounded-xl border shadow-sm px-6 py-5">
                <h2 class="text-base font-semibold text-gray-800 mb-4">2. Dados gerais</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                        <input type="text" name="titulo" x-model="titulo" required
                               placeholder="Ex: Transferência do imóvel — Rua das Flores 420"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                        <select name="account_id" x-model="accountId" required
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                            <option value="">Selecionar cliente...</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}" {{ $accountId == $acc->id ? 'selected' : '' }}>{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Responsável *</label>
                        <select name="owner_user_id" required
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}" {{ auth()->id() == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Órgão destino</label>
                        <input type="text" name="orgao_destino"
                               placeholder="Ex: 3º Cartório de Registro de Imóveis"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prioridade</label>
                        <select name="prioridade"
                                class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                            <option value="normal">Normal</option>
                            <option value="baixa">Baixa</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prazo estimado</label>
                        <input type="date" name="prazo_estimado"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Prazo final (hard)</label>
                        <input type="date" name="prazo_final"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Honorários (R$)</label>
                        <input type="number" step="0.01" name="valor_honorarios"
                               placeholder="0,00"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea name="descricao" rows="2"
                                  placeholder="Detalhes adicionais sobre o processo..."
                                  class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776]"></textarea>
                    </div>
                </div>
            </div>

            {{-- STEP 3: Etapas --}}
            <div class="bg-white rounded-xl border shadow-sm px-6 py-5">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-800">3. Etapas do processo</h2>
                    <button type="button" @click="addStep()"
                            class="text-sm text-[#385776] hover:underline font-medium">+ Adicionar etapa</button>
                </div>

                <div x-show="!tipo" class="text-sm text-gray-400 text-center py-8">
                    Selecione um tipo de processo para carregar as etapas padrão.
                </div>

                <div x-show="tipo" class="space-y-2">
                    <template x-for="(step, i) in steps" :key="i">
                        <div class="flex items-start gap-2 p-3 border rounded-lg bg-gray-50 group">
                            <span class="mt-2 text-xs font-bold text-gray-400 w-5 text-center" x-text="i+1"></span>
                            <div class="flex-1 grid grid-cols-1 md:grid-cols-4 gap-2">
                                <div class="md:col-span-2">
                                    <input type="text" :name="'steps['+i+'][titulo]'" x-model="step.titulo"
                                           placeholder="Título da etapa"
                                           class="w-full border rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#385776]">
                                    <input type="hidden" :name="'steps['+i+'][orgao]'" x-model="step.orgao">
                                </div>
                                <div>
                                    <select :name="'steps['+i+'][tipo]'" x-model="step.tipo"
                                            class="w-full border rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#385776]">
                                        <option value="interno">🏠 Interno</option>
                                        <option value="externo">🏛️ Externo</option>
                                        <option value="cliente">👤 Cliente</option>
                                        <option value="aprovacao">✅ Aprovação</option>
                                    </select>
                                </div>
                                <div class="flex items-center gap-2">
                                    <input type="number" :name="'steps['+i+'][deadline_days]'" x-model="step.deadline_days"
                                           placeholder="Dias" min="1"
                                           class="w-20 border rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#385776]">
                                    <label class="flex items-center gap-1 text-xs text-gray-500 cursor-pointer">
                                        <input type="checkbox" :name="'steps['+i+'][is_client_visible]'" :value="1"
                                               x-model="step.is_client_visible"
                                               class="rounded text-[#385776]">
                                        📱
                                    </label>
                                </div>
                            </div>
                            <button type="button" @click="steps.splice(i,1)"
                                    class="mt-1.5 text-gray-300 hover:text-red-400 transition-colors opacity-0 group-hover:opacity-100">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- STEP 4: Checklist de documentos --}}
            <div class="bg-white rounded-xl border shadow-sm px-6 py-5" x-show="tipo">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-base font-semibold text-gray-800">4. Checklist de documentos</h2>
                    <button type="button" @click="checklistItems.push('')"
                            class="text-sm text-[#385776] hover:underline font-medium">+ Adicionar item</button>
                </div>
                <div class="space-y-2">
                    <template x-for="(item, i) in checklistItems" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="text" :name="'checklist_items['+i+']'" x-model="checklistItems[i]"
                                   placeholder="Ex: Certidão de matrícula do imóvel"
                                   class="flex-1 border rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#385776]">
                            <button type="button" @click="checklistItems.splice(i,1)"
                                    class="text-gray-300 hover:text-red-400">✕</button>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end gap-3">
                <a href="{{ route('crm.admin-processes.index') }}"
                   class="px-5 py-2.5 text-sm text-gray-600 bg-white border rounded-lg hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-5 py-2.5 text-sm text-white bg-[#1B334A] rounded-lg hover:bg-[#385776] font-medium">
                    Criar processo
                </button>
            </div>

        </div>
    </form>
</div>

<script>
function adminProcessCreate() {
    return {
        tipo: '',
        titulo: '',
        accountId: '',
        steps: [],
        checklistItems: [],

        loadTemplate(tipo) {
            if (tipo === 'outro') {
                this.steps = [];
                this.checklistItems = [];
                return;
            }
            fetch(`{{ url('crm/processos-admin/api/template') }}/${tipo}`)
                .then(r => r.json())
                .then(tpl => {
                    this.steps = (tpl.steps || []).map(s => ({
                        titulo: s.titulo || '',
                        tipo: s.tipo || 'interno',
                        orgao: s.orgao || '',
                        deadline_days: s.deadline_days || '',
                        is_client_visible: s.is_client_visible || false,
                    }));
                    this.checklistItems = tpl.checklist || [];
                    if (!this.titulo && tpl.nome) {
                        this.titulo = tpl.nome;
                    }
                });
        },

        addStep() {
            this.steps.push({ titulo: '', tipo: 'interno', orgao: '', deadline_days: '', is_client_visible: false });
        },

        submitForm(e) {
            e.$el.submit();
        }
    }
}
</script>
@endsection
