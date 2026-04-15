@extends('layouts.app')
@section('title', 'Novo Processo Administrativo')

@section('content')
<div class="max-w-4xl mx-auto px-6 py-6"
     x-data="adminProcessCreate()">

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
                @php
                $tipoMeta = [
                    'transferencia_imovel'      => ['icon'=>'🏠','ring'=>'ring-purple-400','bg'=>'bg-purple-50','border'=>'border-purple-400','label_color'=>'text-purple-700'],
                    'inventario_extrajudicial'  => ['icon'=>'📋','ring'=>'ring-amber-400','bg'=>'bg-amber-50','border'=>'border-amber-400','label_color'=>'text-amber-700'],
                    'divorcio_extrajudicial'    => ['icon'=>'⚖️','ring'=>'ring-rose-400','bg'=>'bg-rose-50','border'=>'border-rose-400','label_color'=>'text-rose-700'],
                    'abertura_empresa'          => ['icon'=>'🏢','ring'=>'ring-emerald-400','bg'=>'bg-emerald-50','border'=>'border-emerald-400','label_color'=>'text-emerald-700'],
                    'usucapiao_extrajudicial'   => ['icon'=>'🌍','ring'=>'ring-teal-400','bg'=>'bg-teal-50','border'=>'border-teal-400','label_color'=>'text-teal-700'],
                    'retificacao_registro'      => ['icon'=>'📝','ring'=>'ring-blue-400','bg'=>'bg-blue-50','border'=>'border-blue-400','label_color'=>'text-blue-700'],
                    'dissolucao_sociedade'      => ['icon'=>'🤝','ring'=>'ring-orange-400','bg'=>'bg-orange-50','border'=>'border-orange-400','label_color'=>'text-orange-700'],
                    'regularizacao_fundiaria'   => ['icon'=>'🌱','ring'=>'ring-lime-400','bg'=>'bg-lime-50','border'=>'border-lime-400','label_color'=>'text-lime-700'],
                    'testamento'                => ['icon'=>'📜','ring'=>'ring-violet-400','bg'=>'bg-violet-50','border'=>'border-violet-400','label_color'=>'text-violet-700'],
                    'emancipacao'               => ['icon'=>'🎓','ring'=>'ring-cyan-400','bg'=>'bg-cyan-50','border'=>'border-cyan-400','label_color'=>'text-cyan-700'],
                    'reconhecimento_paternidade'=> ['icon'=>'👨‍👧','ring'=>'ring-sky-400','bg'=>'bg-sky-50','border'=>'border-sky-400','label_color'=>'text-sky-700'],
                    'alteracao_contratual'      => ['icon'=>'📃','ring'=>'ring-indigo-400','bg'=>'bg-indigo-50','border'=>'border-indigo-400','label_color'=>'text-indigo-700'],
                ];
                @endphp
                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    @foreach($templates as $tipo => $tpl)
                    @php $meta = $tipoMeta[$tipo] ?? ['icon'=>'📂','ring'=>'ring-gray-400','bg'=>'bg-gray-50','border'=>'border-gray-400','label_color'=>'text-gray-700']; @endphp
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="{{ $tipo }}" x-model="tipo"
                               @change="loadTemplate('{{ $tipo }}')" class="sr-only">
                        <div :class="tipo==='{{ $tipo }}' ? '{{ $meta['border'] }} {{ $meta['bg'] }} ring-2 {{ $meta['ring'] }}' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                             class="border rounded-xl p-3 transition-all">
                            <p class="text-xl mb-1">{{ $meta['icon'] }}</p>
                            <p class="text-sm font-semibold {{ $meta['label_color'] }}">{{ $tpl['nome'] }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">~{{ $tpl['prazo_estimado_dias'] }} dias</p>
                        </div>
                    </label>
                    @endforeach
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="outro" x-model="tipo" class="sr-only">
                        <div :class="tipo==='outro' ? 'border-gray-500 bg-gray-50 ring-2 ring-gray-400' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50'"
                             class="border rounded-xl p-3 transition-all">
                            <p class="text-xl mb-1">📂</p>
                            <p class="text-sm font-semibold text-gray-700">Outro</p>
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
                    <div x-data="accountSearch({{ $accountId ? $accountId : 'null' }}, {{ $accountId && ($preloadAccount = $accounts->firstWhere('id', $accountId)) ? json_encode($preloadAccount->name) : 'null' }})"
                         class="relative">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Cliente *</label>
                        <input type="hidden" name="account_id" :value="selectedId" x-ref="hiddenId">
                        <input type="text"
                               x-model="query"
                               @input.debounce.300ms="search()"
                               @focus="search()"
                               @keydown.escape="close()"
                               @keydown.arrow-down.prevent="moveDown()"
                               @keydown.arrow-up.prevent="moveUp()"
                               @keydown.enter.prevent="selectActive()"
                               @blur="onBlur()"
                               autocomplete="off"
                               required
                               placeholder="Digite o nome ou CPF/CNPJ..."
                               :class="selectedId ? 'border-green-400 bg-green-50' : 'border-gray-300'"
                               class="w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#385776] pr-8">
                        {{-- indicador de selecionado --}}
                        <span x-show="selectedId" class="absolute right-2.5 top-[34px] text-green-500 text-sm">✓</span>
                        {{-- dropdown --}}
                        <div x-show="open && results.length > 0"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-52 overflow-y-auto">
                            <template x-for="(item, i) in results" :key="item.id">
                                <div @mousedown.prevent="select(item)"
                                     :class="i === activeIndex ? 'bg-[#1B334A]/5' : 'hover:bg-gray-50'"
                                     class="px-3 py-2 cursor-pointer flex items-center justify-between gap-2 text-sm">
                                    <span class="font-medium text-gray-800" x-text="item.name"></span>
                                    <span class="text-xs text-gray-400 shrink-0" x-text="item.doc || ''"></span>
                                </div>
                            </template>
                        </div>
                        {{-- nenhum resultado --}}
                        <div x-show="open && results.length === 0 && query.length >= 2"
                             class="absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg px-3 py-2 text-sm text-gray-400">
                            Nenhum cliente encontrado.
                        </div>
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
                    <div>
                        <h2 class="text-base font-semibold text-gray-800">4. Checklist de documentos</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Itens do template ou gerados pela IA. Edite à vontade.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="gerarChecklistIa()"
                                :disabled="iaLoading || !tipo || !titulo"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-sm bg-violet-600 text-white rounded-lg hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                            <span x-show="!iaLoading">✨ Gerar com IA</span>
                            <span x-show="iaLoading" class="flex items-center gap-1">
                                <svg class="animate-spin w-3.5 h-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
                                Gerando...
                            </span>
                        </button>
                        <button type="button" @click="checklistItems.push('')"
                                class="text-sm text-[#385776] hover:underline font-medium">+ Item manual</button>
                    </div>
                </div>

                <div x-show="iaError" class="mb-3 px-3 py-2 bg-red-50 border border-red-200 rounded-lg text-xs text-red-700" x-text="iaError"></div>

                <div x-show="checklistItems.length === 0 && !iaLoading" class="text-sm text-gray-400 text-center py-6">
                    Clique em <strong>✨ Gerar com IA</strong> para o Claude criar o checklist jurídico ideal,
                    ou adicione itens manualmente.
                </div>

                <div class="space-y-2">
                    <template x-for="(item, i) in checklistItems" :key="i">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 w-5 text-center shrink-0" x-text="i+1"></span>
                            <input type="text" :name="'checklist_items['+i+']'" x-model="checklistItems[i]"
                                   placeholder="Ex: Certidão de matrícula do imóvel"
                                   class="flex-1 border rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-[#385776]">
                            <button type="button" @click="checklistItems.splice(i,1)"
                                    class="text-gray-300 hover:text-red-400 shrink-0">✕</button>
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
function accountSearch(preloadId, preloadName) {
    return {
        query:       preloadName || '',
        selectedId:  preloadId  || null,
        results:     [],
        open:        false,
        activeIndex: -1,

        async search() {
            if (this.query.length < 2) { this.open = false; return; }
            const r = await fetch('{{ route('crm.accounts.search') }}?q=' + encodeURIComponent(this.query));
            this.results = await r.json();
            this.activeIndex = -1;
            this.open = true;
        },

        select(item) {
            this.query      = item.name;
            this.selectedId = item.id;
            this.open       = false;
        },

        close() { this.open = false; },

        onBlur() {
            // se o texto não corresponde ao item selecionado, limpa a seleção
            setTimeout(() => {
                this.open = false;
                if (!this.selectedId) return;
                // mantém como está (usuário pode ter clicado no dropdown via mousedown.prevent)
            }, 150);
        },

        moveDown() {
            if (!this.open) return;
            this.activeIndex = Math.min(this.activeIndex + 1, this.results.length - 1);
        },
        moveUp() {
            if (!this.open) return;
            this.activeIndex = Math.max(this.activeIndex - 1, 0);
        },
        selectActive() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                this.select(this.results[this.activeIndex]);
            }
        },
    }
}

function adminProcessCreate() {
    return {
        tipo: '',
        titulo: '',
        steps: [],
        checklistItems: [],
        iaLoading: false,
        iaError: '',

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

        async gerarChecklistIa() {
            if (!this.tipo || !this.titulo) return;
            this.iaLoading = true;
            this.iaError = '';
            try {
                const descricao  = document.querySelector('textarea[name="descricao"]')?.value || '';
                const accountId  = document.querySelector('input[name="account_id"]')?.value || null;
                const res = await fetch('{{ route('crm.admin-processes.checklist-ia') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({
                        tipo:       this.tipo,
                        titulo:     this.titulo,
                        descricao:  descricao,
                        account_id: accountId,
                    }),
                });
                const data = await res.json();
                if (data.success && Array.isArray(data.items)) {
                    this.checklistItems = data.items;
                } else {
                    this.iaError = data.error || 'Erro ao gerar checklist.';
                }
            } catch (e) {
                this.iaError = 'Falha na comunicação com a IA.';
            } finally {
                this.iaLoading = false;
            }
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
