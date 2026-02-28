@extends('layouts.app')
@section('title', 'SIATE')

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-6">
        <div>
            <h1 class="text-2xl font-bold" style="color: #1B334A;">SIATE</h1>
            <p class="text-sm text-gray-400 mt-0.5">Sistema de Atendimentos</p>
        </div>
        <button onclick="document.getElementById('form-novo-chamado').classList.toggle('hidden')"
                class="px-4 py-2.5 text-sm font-medium text-white rounded-xl transition hover:shadow-lg"
                style="background: linear-gradient(135deg, #385776, #1B334A);">
            <i class="fa-solid fa-plus mr-1.5"></i> Novo Chamado
        </button>
    </div>

    {{-- Contadores --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-2xl border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-violet-50 flex items-center justify-center"><i class="fa-solid fa-clipboard-list text-violet-500"></i></div>
                <div><span class="block text-2xl font-black text-gray-800">{{ $contadores['total'] }}</span><span class="text-xs text-gray-400">Total</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center"><i class="fa-solid fa-spinner text-blue-500"></i></div>
                <div><span class="block text-2xl font-black text-blue-600">{{ $contadores['abertas'] }}</span><span class="text-xs text-gray-400">Abertas</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-emerald-50 flex items-center justify-center"><i class="fa-solid fa-check-circle text-emerald-500"></i></div>
                <div><span class="block text-2xl font-black text-emerald-600">{{ $contadores['concluidas'] }}</span><span class="text-xs text-gray-400">Concluidas</span></div>
            </div>
        </div>
        <div class="bg-white rounded-2xl border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl {{ $contadores['sla_risco'] > 0 ? 'bg-red-50' : 'bg-gray-50' }} flex items-center justify-center"><i class="fa-solid fa-clock {{ $contadores['sla_risco'] > 0 ? 'text-red-500' : 'text-gray-300' }}"></i></div>
                <div><span class="block text-2xl font-black {{ $contadores['sla_risco'] > 0 ? 'text-red-600' : 'text-gray-400' }}">{{ $contadores['sla_risco'] }}</span><span class="text-xs text-gray-400">SLA Estourado</span></div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200 text-sm text-emerald-700">{{ session('success') }}</div>
    @endif

    {{-- Formulario Novo Chamado --}}
    <div id="form-novo-chamado" class="hidden mb-6">
        <form method="POST" action="{{ route('chamados.store') }}" enctype="multipart/form-data" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

            {{-- Header do form --}}
            <div class="px-6 py-4 border-b border-gray-100" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
                <h3 class="text-base font-bold text-[#1B334A]">Registrar Chamado</h3>
                <p class="text-xs text-gray-400 mt-0.5">Preencha os dados abaixo. O prazo e responsavel serao definidos automaticamente.</p>
            </div>

            <div class="p-6 space-y-5">
                @csrf

                {{-- LINHA 1: Categoria + Prioridade + Impacto --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Categoria <span class="text-red-400">*</span></label>
                        <select name="category" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition" id="sel-category">
                            <option value="">Selecione a categoria...</option>
                            <optgroup label="Juridico">
                                @foreach(['renuncia_mandato','substabelecimento','emissao_procuracao','solicitacao_documentos','acordo_judicial','encerramento_caso'] as $k)
                                    <option value="{{ $k }}" data-approval="{{ ($categorias[$k]['approval'] ?? false) ? '1' : '0' }}" data-show-value="0">{{ $categorias[$k]['label'] ?? $k }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Administrativo">
                                @foreach(['alteracao_cadastral','transferencia_responsavel','cobranca_honorarios','solicitacao_financeiro','solicitacao_rh'] as $k)
                                    <option value="{{ $k }}" data-approval="{{ ($categorias[$k]['approval'] ?? false) ? '1' : '0' }}" data-show-value="{{ in_array($k, ['cobranca_honorarios','solicitacao_financeiro']) ? '1' : '0' }}">{{ $categorias[$k]['label'] ?? $k }}</option>
                                @endforeach
                            </optgroup>
                            <optgroup label="Operacional">
                                @foreach(['compra_materiais','manutencao','suprimentos','infraestrutura_ti','servicos_terceiros','logistica','solicitacao_ti'] as $k)
                                    @if(isset($categorias[$k]))
                                    <option value="{{ $k }}" data-approval="{{ ($categorias[$k]['approval'] ?? false) ? '1' : '0' }}" data-show-value="{{ in_array($k, ['compra_materiais','servicos_terceiros','manutencao']) ? '1' : '0' }}">{{ $categorias[$k]['label'] ?? $k }}</option>
                                    @endif
                                @endforeach
                            </optgroup>
                            <optgroup label="Outros">
                                <option value="outra" data-approval="0" data-show-value="0">{{ $categorias['outra']['label'] ?? 'Outra' }}</option>
                            </optgroup>
                        </select>
                        <p id="approval-warning" class="text-xs text-orange-600 mt-1 hidden">Requer aprovacao da diretoria</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Prioridade <span class="text-red-400">*</span></label>
                        <select name="priority" required class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                            <option value="baixa">Baixa</option>
                            <option value="normal" selected>Normal</option>
                            <option value="alta">Alta</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Impacto</label>
                        <select name="impact" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                            <option value="">Selecione...</option>
                            <option value="individual">Somente eu</option>
                            <option value="equipe">Minha equipe</option>
                            <option value="escritorio">Todo o escritorio</option>
                            <option value="cliente">Cliente externo</option>
                        </select>
                    </div>
                </div>

                {{-- LINHA 2: Cliente + Atribuir + Prazo desejado --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cliente vinculado <span class="text-gray-300 font-normal">(opcional)</span></label>
                        <select name="account_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                            <option value="">Chamado interno</option>
                            @foreach($accounts as $acc)
                                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Atribuir a <span class="text-gray-300 font-normal">(opcional)</span></label>
                        <select name="assigned_to_user_id" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                            <option value="">Atribuicao automatica</option>
                            @foreach($users as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Prazo desejado <span class="text-gray-300 font-normal">(opcional)</span></label>
                        <input type="date" name="desired_deadline" min="{{ date('Y-m-d', strtotime('+1 day')) }}" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                    </div>
                </div>

                {{-- LINHA 3: Centro de custo + Valor estimado (condicional) --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Centro de custo</label>
                        <select name="cost_center" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                            <option value="">Nao se aplica</option>
                            <option value="escritorio">Escritorio</option>
                            <option value="cliente">Cliente</option>
                            <option value="projeto">Projeto especifico</option>
                        </select>
                    </div>
                    <div id="campo-valor" class="hidden">
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Valor estimado (R$)</label>
                        <input type="number" name="estimated_value" step="0.01" min="0" placeholder="0,00" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">Anexos <span class="text-gray-300 font-normal">(max 10MB cada)</span></label>
                        <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 transition">
                    </div>
                </div>

                {{-- LINHA 4: Assunto --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Assunto <span class="text-red-400">*</span></label>
                    <input type="text" name="subject" required maxlength="255" placeholder="Resumo objetivo do chamado" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition">
                </div>

                {{-- LINHA 5: Descricao --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Descricao detalhada <span class="text-red-400">*</span></label>
                    <textarea name="description" required maxlength="3000" rows="4" placeholder="Descreva o que precisa ser feito, contexto relevante, prazos e qualquer informacao adicional" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition"></textarea>
                    <p class="text-xs text-gray-300 mt-1">Quanto mais detalhes, mais precisa sera a triagem e o prazo.</p>
                </div>
            </div>

            {{-- Footer do form --}}
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between" style="background: #fafbfc;">
                <button type="button" onclick="document.getElementById('form-novo-chamado').classList.add('hidden')" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Cancelar</button>
                <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white rounded-xl transition hover:shadow-lg" style="background: linear-gradient(135deg, #385776, #1B334A);">
                    <i class="fa-solid fa-paper-plane mr-1.5"></i> Registrar Chamado
                </button>
            </div>
        </form>
    </div>

    {{-- Filtros --}}
    <form method="GET" class="flex flex-wrap items-center gap-3 mb-4">
        <select name="status" onchange="this.form.submit()" class="border rounded-xl px-3 py-2 text-xs bg-white">
            <option value="">Todos os status</option>
            @foreach(['aberto','em_andamento','aguardando_aprovacao','aprovado','rejeitado','concluido','cancelado'] as $st)
                <option value="{{ $st }}" {{ request('status') === $st ? 'selected' : '' }}>{{ \App\Models\Crm\CrmServiceRequest::statusLabel($st) }}</option>
            @endforeach
        </select>
        <select name="tipo" onchange="this.form.submit()" class="border rounded-xl px-3 py-2 text-xs bg-white">
            <option value="">Todos os tipos</option>
            <option value="cliente" {{ request('tipo') === 'cliente' ? 'selected' : '' }}>Vinculado a cliente</option>
            <option value="operacional" {{ request('tipo') === 'operacional' ? 'selected' : '' }}>Operacional</option>
        </select>
        <select name="category" onchange="this.form.submit()" class="border rounded-xl px-3 py-2 text-xs bg-white">
            <option value="">Todas as categorias</option>
            @foreach($categorias as $key => $cat)
                <option value="{{ $key }}" {{ request('category') === $key ? 'selected' : '' }}>{{ $cat['label'] }}</option>
            @endforeach
        </select>
        @if(request()->hasAny(['status','tipo','category']))
            <a href="{{ route('chamados.index') }}" class="text-xs text-red-500 hover:underline">Limpar</a>
        @endif
    </form>

    {{-- Lista --}}
    <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
        @if($chamados->count() > 0)
            <div class="divide-y divide-gray-50">
                @foreach($chamados as $sr)
                    <a href="{{ url('/chamados/' . $sr->id) }}" class="flex items-center gap-4 px-5 py-4 hover:bg-gray-50/50 transition group">
                        <span class="w-10 h-10 rounded-xl flex items-center justify-center text-xs font-black flex-shrink-0
                            @if($sr->priority === 'urgente') bg-red-100 text-red-600
                            @elseif($sr->priority === 'alta') bg-orange-100 text-orange-600
                            @elseif($sr->priority === 'normal') bg-violet-100 text-violet-600
                            @else bg-gray-100 text-gray-400 @endif">#{{ $sr->id }}</span>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-0.5">
                                <span class="text-sm font-semibold text-gray-800 truncate">{{ $sr->subject }}</span>
                                @if($sr->isSlaExpired())
                                    <span class="text-[9px] font-black text-red-600 bg-red-50 px-1.5 py-0.5 rounded-md uppercase animate-pulse">SLA</span>
                                @elseif($sr->sla_deadline && $sr->isOpen())
                                    @php $rem = $sr->slaRemainingHours(); @endphp
                                    @if($rem !== null && $rem <= 4)
                                        <span class="text-[9px] font-bold text-orange-600 bg-orange-50 px-1.5 py-0.5 rounded-md">{{ round($rem) }}h</span>
                                    @endif
                                @endif
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-gray-400">
                                @php $catLabel = $categorias[$sr->category]['label'] ?? $sr->category; @endphp
                                <span>{{ $catLabel }}</span>
                                <span class="text-gray-200">|</span>
                                @if($sr->account)
                                    <span class="text-blue-500"><i class="fa-solid fa-building text-[9px] mr-0.5"></i> {{ \Illuminate\Support\Str::limit($sr->account->name, 25) }}</span>
                                @else
                                    <span class="text-violet-500"><i class="fa-solid fa-cog text-[9px] mr-0.5"></i> Interno</span>
                                @endif
                                <span class="text-gray-200">|</span>
                                <span>{{ $sr->requestedBy->name ?? '-' }}</span>
                                <span class="text-gray-200">|</span>
                                <span>{{ $sr->created_at->format('d/m H:i') }}</span>
                                @if($sr->assignedTo)
                                    <span class="text-gray-200">|</span>
                                    <span><i class="fa-solid fa-user-check text-[9px] mr-0.5"></i> {{ $sr->assignedTo->name }}</span>
                                @endif
                            </div>
                        </div>
                        <span class="px-2.5 py-1 rounded-lg text-[10px] font-bold {{ \App\Models\Crm\CrmServiceRequest::statusBadge($sr->status) }} flex-shrink-0">
                            {{ \App\Models\Crm\CrmServiceRequest::statusLabel($sr->status) }}
                        </span>
                        <i class="fa-solid fa-chevron-right text-gray-200 group-hover:text-gray-400 transition text-xs flex-shrink-0"></i>
                    </a>
                @endforeach
            </div>
            @if($chamados->hasPages())
                <div class="px-5 py-3 border-t border-gray-50">{{ $chamados->withQueryString()->links() }}</div>
            @endif
        @else
            <div class="text-center py-16">
                <div class="w-16 h-16 mx-auto mb-4 rounded-2xl bg-violet-50 flex items-center justify-center"><i class="fa-solid fa-clipboard-check text-violet-300 text-2xl"></i></div>
                <p class="text-gray-400 text-sm">Nenhum chamado encontrado</p>
                <p class="text-gray-300 text-xs mt-1">Clique em "Novo Chamado" para registrar</p>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
document.getElementById('sel-category')?.addEventListener('change', function() {
    const opt = this.selectedOptions[0];
    const approval = opt?.dataset?.approval === '1';
    const showValue = opt?.dataset?.showValue === '1';
    document.getElementById('approval-warning').classList.toggle('hidden', !approval);
    document.getElementById('campo-valor').classList.toggle('hidden', !showValue);
});
</script>
@endpush
@endsection
