@extends('layouts.app')
@section('title', 'Chamado ' . $sr->protocolo)

@section('content')
<div class="w-full px-4 py-6">

    {{-- Voltar --}}
    <div class="flex items-center gap-3 mb-5">
        @if($sr->account_id)
            <a href="{{ route('crm.accounts.show', $sr->account_id) }}#solicitacoes" class="text-sm text-[#385776] hover:underline">← Voltar ao cliente</a>
            <span class="text-gray-300">|</span>
        @endif
        <a href="{{ url('/chamados') }}" class="text-sm text-[#385776] hover:underline">← Central SIATE</a>
    </div>

    {{-- Header Card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="px-6 py-5 border-b border-gray-50" style="background: linear-gradient(135deg, #f8fafc, #f1f5f9);">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-xl font-bold text-[#1B334A]">{{ $sr->protocolo }} — {{ $sr->subject }}</h1>
                    @if($sr->account_id && $sr->account)
                        <p class="text-sm text-gray-500 mt-1"><i class="fa-solid fa-building text-xs mr-1"></i> <a href="{{ route('crm.accounts.show', $sr->account_id) }}" class="text-[#385776] hover:underline">{{ $sr->account->name }}</a></p>
                    @else
                        <p class="text-sm text-violet-500 mt-1"><i class="fa-solid fa-cog text-xs mr-1"></i> Chamado interno (operacional)</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ App\Models\Crm\CrmServiceRequest::statusBadge($sr->status) }}">{{ App\Models\Crm\CrmServiceRequest::statusLabel($sr->status) }}</span>
                    <span class="px-3 py-1 rounded-full text-xs font-bold {{ App\Models\Crm\CrmServiceRequest::priorityBadge($sr->priority) }}">{{ ucfirst($sr->priority) }}</span>
                </div>
            </div>
        </div>

        <div class="px-6 py-4">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Categoria</span><span class="font-medium text-gray-700">{{ $categorias[$sr->category]['label'] ?? $sr->category }}</span></div>
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Solicitante</span><span class="font-medium text-gray-700">{{ $sr->requestedBy->name ?? '-' }}</span></div>
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Atribuido a</span><span class="font-medium text-gray-700">{{ $sr->assignedTo->name ?? 'Nao atribuido' }}</span></div>
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Criado em</span><span class="font-medium text-gray-700">{{ $sr->created_at->format('d/m/Y H:i') }}</span></div>
            </div>

            @if($sr->impact || $sr->cost_center || $sr->estimated_value || $sr->desired_deadline)
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm mt-3 pt-3 border-t border-gray-50">
                @if($sr->impact)
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Impacto</span><span class="font-medium text-gray-700">{{ ['individual'=>'Somente eu','equipe'=>'Minha equipe','escritorio'=>'Todo escritorio','cliente'=>'Cliente externo'][$sr->impact] ?? $sr->impact }}</span></div>
                @endif
                @if($sr->cost_center)
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Centro de custo</span><span class="font-medium text-gray-700">{{ ucfirst($sr->cost_center) }}</span></div>
                @endif
                @if($sr->estimated_value)
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Valor estimado</span><span class="font-medium text-gray-700">R$ {{ number_format($sr->estimated_value, 2, ',', '.') }}</span></div>
                @endif
                @if($sr->desired_deadline)
                <div><span class="text-[10px] uppercase tracking-wider text-gray-400 block mb-0.5">Prazo desejado</span><span class="font-medium text-gray-700">{{ $sr->desired_deadline->format('d/m/Y') }}</span></div>
                @endif
            </div>
            @endif

            @if($sr->requires_approval)
            <div class="mt-3 px-3 py-2 rounded-xl bg-purple-50 border border-purple-200 text-xs text-purple-700">
                <i class="fa-solid fa-shield-halved mr-1"></i> Requer aprovacao da diretoria.
                @if($sr->approved_by_user_id)
                    {{ $sr->status === 'aprovado' ? '✅' : '❌' }} {{ App\Models\Crm\CrmServiceRequest::statusLabel($sr->status) }} por {{ $sr->approvedBy->name }} em {{ $sr->approved_at?->format('d/m/Y H:i') }}
                @endif
            </div>
            @endif

            <div class="mt-4 pt-4 border-t border-gray-50">
                <p class="text-[10px] uppercase tracking-wider text-gray-400 mb-1">Descrição</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $sr->description }}</p>
            </div>

            @if($sr->resolution_notes)
            <div class="mt-4 pt-4 border-t border-gray-50">
                <p class="text-[10px] uppercase tracking-wider text-gray-400 mb-1">Resolução</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $sr->resolution_notes }}</p>
            </div>
            @endif

            {{-- Anexos --}}
            @if($sr->attachments && count($sr->attachments) > 0)
            <div class="mt-4 pt-4 border-t border-gray-50">
                <p class="text-[10px] uppercase tracking-wider text-gray-400 mb-2">Anexos</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($sr->attachments as $att)
                        <a href="{{ route('secure-storage', ['path' => $att]) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 border text-xs text-gray-600 hover:bg-gray-100 transition">
                            <i class="fa-solid fa-paperclip text-gray-400"></i> {{ basename($att) }}
                        </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- SLA Bar --}}
        @if($sr->sla_hours)
        @php
            $isExpired = $sr->isSlaExpired();
            $remaining = $sr->slaRemainingHours();
            $pct = $sr->sla_deadline ? max(0, min(100, 100 - (($remaining ?? 0) / max($sr->sla_hours, 1)) * 100)) : 0;
            if(!$sr->isOpen()) $pct = 100;
        @endphp
        <div class="px-6 py-3 border-t border-gray-100 {{ $isExpired ? 'bg-red-50' : ($remaining !== null && $remaining <= 4 ? 'bg-orange-50' : 'bg-gray-50') }}">
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-semibold {{ $isExpired ? 'text-red-700' : 'text-gray-600' }}">
                    <i class="fa-solid fa-clock mr-1"></i> SLA: {{ $sr->sla_hours }}h
                    @if($sr->sla_complexity) | {{ ucfirst($sr->sla_complexity) }} @endif
                </span>
                <span class="text-xs font-bold {{ $isExpired ? 'text-red-600' : ($remaining !== null && $remaining <= 4 ? 'text-orange-600' : 'text-emerald-600') }}">
                    @if(!$sr->isOpen())
                        Finalizado
                    @elseif($isExpired)
                        ESTOURADO ({{ abs(round($remaining)) }}h atras)
                    @elseif($remaining !== null)
                        {{ round($remaining) }}h restantes
                    @endif
                </span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-1.5">
                <div class="h-1.5 rounded-full transition-all {{ $isExpired ? 'bg-red-500' : ($pct > 75 ? 'bg-orange-400' : 'bg-emerald-400') }}" style="width: {{ $pct }}%"></div>
            </div>
            @if($sr->sla_justification)
                <p class="text-[10px] text-gray-400 mt-1">{{ $sr->sla_justification }}</p>
            @endif
            @if($sr->sla_deadline)
                <p class="text-[10px] text-gray-400">Prazo: {{ $sr->sla_deadline->format('d/m/Y H:i') }}</p>
            @endif
        </div>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Coluna Esquerda: Acoes + Comentarios --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Acoes --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-bold text-[#1B334A] mb-3"><i class="fa-solid fa-sliders mr-1.5 text-gray-400"></i> Ações</h3>
                <form id="form-acoes" method="POST" action="{{ route('chamados.update', $sr->id) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Status</label>
                            <select id="sel-status" name="status" class="w-full border rounded-xl px-3 py-2 text-sm">
                                @foreach(['aberto','em_andamento','aguardando_aprovacao','aprovado','rejeitado','concluido','cancelado','devolvido'] as $st)
                                    <option value="{{ $st }}" {{ $sr->status === $st ? 'selected' : '' }}>{{ App\Models\Crm\CrmServiceRequest::statusLabel($st) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Atribuir a</label>
                            <select name="assigned_to_user_id" class="w-full border rounded-xl px-3 py-2 text-sm">
                                <option value="">Nao atribuido</option>
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}" {{ $sr->assigned_to_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Prioridade</label>
                            <select name="priority" class="w-full border rounded-xl px-3 py-2 text-sm">
                                @foreach(['baixa','normal','alta','urgente'] as $p)
                                    <option value="{{ $p }}" {{ $sr->priority === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <button type="button" id="btn-salvar-acao" class="px-5 py-2 text-sm font-medium text-white rounded-xl transition" style="background: linear-gradient(135deg, #385776, #1B334A);">Salvar</button>

                    {{-- Campos de resolução ocultos — preenchidos pelo modal --}}
                    <textarea name="resolution_notes" id="hidden-notes" class="hidden"></textarea>
                    <input type="file" name="action_attachments[]" id="hidden-files" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" class="hidden">
                </form>
            </div>

            {{-- Modal de Resolução --}}
            <div id="modal-resolucao" class="fixed inset-0 z-50 hidden items-center justify-center" style="background:rgba(0,0,0,0.45);">
                <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between" style="background:linear-gradient(135deg,#f8fafc,#f1f5f9)">
                        <div>
                            <h3 class="text-base font-bold text-[#1B334A]" id="modal-titulo">Finalizar chamado</h3>
                            <p class="text-xs text-gray-400 mt-0.5" id="modal-subtitulo">Descreva o que foi feito antes de confirmar.</p>
                        </div>
                        <button onclick="fecharModal()" class="text-gray-400 hover:text-gray-600 transition text-lg leading-none">✕</button>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Notas <span id="modal-obrig" class="text-red-400">*</span></label>
                            <textarea id="modal-notes" rows="5" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:border-[#385776] focus:ring-1 focus:ring-[#385776] transition resize-y" placeholder="Descreva o que foi feito, o motivo ou as observações relevantes..."></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1.5">Anexos <span class="text-gray-300 font-normal">(opcional)</span></label>
                            <input type="file" id="modal-files" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip"
                                class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm bg-white file:mr-3 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100 transition">
                        </div>
                    </div>
                    <div class="px-6 py-4 border-t border-gray-100 flex justify-between items-center bg-gray-50">
                        <button onclick="fecharModal()" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 transition">Cancelar</button>
                        <button onclick="confirmarModal()" id="btn-confirmar" class="px-6 py-2.5 text-sm font-medium text-white rounded-xl transition hover:shadow-lg" style="background:linear-gradient(135deg,#385776,#1B334A);">Confirmar</button>
                    </div>
                </div>
            </div>

            {{-- Comentarios --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-bold text-[#1B334A] mb-4"><i class="fa-solid fa-comments mr-1.5 text-gray-400"></i> Comentários ({{ $sr->comments->count() }})</h3>
                @foreach($sr->comments as $comment)
                    <div class="border-b border-gray-50 py-3 {{ $comment->is_internal ? 'bg-yellow-50/50 -mx-2 px-2 rounded-lg' : '' }}">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold text-gray-700">{{ $comment->user->name ?? '-' }}</span>
                            <span class="text-[10px] text-gray-400">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                            @if($comment->is_internal)
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700">Interno</span>
                            @endif
                        </div>
                        <p class="text-sm text-gray-600 whitespace-pre-line">{{ $comment->body }}</p>
                        @if($comment->attachments && count($comment->attachments) > 0)
                            <div class="flex flex-wrap gap-1.5 mt-1.5">
                                @foreach($comment->attachments as $att)
                                    <a href="{{ route('secure-storage', ['path' => $att]) }}" target="_blank" class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-gray-50 border text-[10px] text-gray-500 hover:bg-gray-100 transition">
                                        <i class="fa-solid fa-paperclip text-gray-400"></i> {{ basename($att) }}
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
                <form method="POST" action="{{ route('chamados.comment', $sr->id) }}" enctype="multipart/form-data" class="mt-4 space-y-2">
                    @csrf
                    <textarea name="body" required rows="4" maxlength="3000" placeholder="Adicionar comentário..." class="w-full border rounded-xl px-3 py-2 text-sm resize-y"></textarea>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-4">
                            <label class="flex items-center gap-1.5 text-xs text-gray-500">
                                <input type="checkbox" name="is_internal" value="1" class="rounded"> Nota interna
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-gray-500 cursor-pointer hover:text-gray-700 transition">
                                <i class="fa-solid fa-paperclip"></i>
                                <span>Anexar arquivo</span>
                                <input type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.zip" class="hidden" onchange="document.getElementById('anexo-names').textContent = Array.from(this.files).map(f => f.name).join(', ')">
                            </label>
                        </div>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white rounded-xl transition" style="background: linear-gradient(135deg, #385776, #1B334A);">Comentar</button>
                    </div>
                    <p id="anexo-names" class="text-[10px] text-gray-400 mt-1"></p>
                </form>
            </div>
        </div>

        {{-- Coluna Direita: Timeline --}}
        <div>
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-bold text-[#1B334A] mb-4"><i class="fa-solid fa-timeline mr-1.5 text-gray-400"></i> Timeline</h3>

                @if($timeline->count() > 0)
                <div class="relative">
                    <div class="absolute left-3 top-0 bottom-0 w-px bg-gray-200"></div>
                    <div class="space-y-4">
                        @foreach($timeline as $item)
                        <div class="relative flex gap-3">
                            {{-- Dot --}}
                            @if($item->kind === 'event' && $item->type === 'service_request_created')
                                <div class="w-6 h-6 rounded-full bg-emerald-100 flex items-center justify-center flex-shrink-0 z-10 ring-2 ring-white"><i class="fa-solid fa-plus text-[8px] text-emerald-600"></i></div>
                            @elseif($item->kind === 'event' && $item->type === 'service_request_updated')
                                <div class="w-6 h-6 rounded-full bg-blue-100 flex items-center justify-center flex-shrink-0 z-10 ring-2 ring-white"><i class="fa-solid fa-pen text-[8px] text-blue-600"></i></div>
                            @elseif($item->type === 'internal_note')
                                <div class="w-6 h-6 rounded-full bg-yellow-100 flex items-center justify-center flex-shrink-0 z-10 ring-2 ring-white"><i class="fa-solid fa-lock text-[8px] text-yellow-600"></i></div>
                            @else
                                <div class="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0 z-10 ring-2 ring-white"><i class="fa-solid fa-comment text-[8px] text-gray-500"></i></div>
                            @endif

                            {{-- Content --}}
                            <div class="flex-1 min-w-0 pb-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-semibold text-gray-700">
                                        @if($item->kind === 'comment')
                                            {{ $item->user_name ?? '-' }}
                                        @else
                                            {{ $userNames[$item->user_id] ?? 'Sistema' }}
                                        @endif
                                    </span>
                                    <span class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($item->created_at)->format('d/m H:i') }}</span>
                                </div>

                                @if($item->kind === 'event' && $item->type === 'service_request_created')
                                    <p class="text-xs text-gray-500 mt-0.5">Chamado criado — <span class="font-medium">{{ $item->payload['category'] ?? '' }}</span> | {{ $item->payload['priority'] ?? '' }}</p>
                                @elseif($item->kind === 'event' && $item->type === 'service_request_updated')
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        @if(isset($item->payload['from_status']) && isset($item->payload['status']))
                                            Status: <span class="line-through text-gray-400">{{ App\Models\Crm\CrmServiceRequest::statusLabel($item->payload['from_status']) }}</span>
                                            → <span class="font-medium">{{ App\Models\Crm\CrmServiceRequest::statusLabel($item->payload['status']) }}</span>
                                        @endif
                                        @if(!empty($item->payload['assigned_to_user_id']))
                                            | Atribuido a <span class="font-medium">{{ $userNames[$item->payload['assigned_to_user_id']] ?? '#'.$item->payload['assigned_to_user_id'] }}</span>
                                        @endif
                                        @if(!empty($item->payload['priority']))
                                            | Prioridade: {{ ucfirst($item->payload['priority']) }}
                                        @endif
                                    </p>
                                @elseif($item->kind === 'comment')
                                    <p class="text-xs text-gray-600 mt-0.5 whitespace-pre-line">{{ \Illuminate\Support\Str::limit($item->payload['body'] ?? '', 150) }}</p>
                                    @if($item->type === 'internal_note')
                                        <span class="text-[9px] text-yellow-600 font-medium">Nota interna</span>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @else
                    <div class="text-center py-8">
                        <i class="fa-solid fa-clock-rotate-left text-gray-200 text-2xl mb-2"></i>
                        <p class="text-xs text-gray-400">Nenhum evento registrado</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
const statusesComModal = ['concluido','rejeitado','cancelado','devolvido'];
const titulosModal = {
    concluido: { titulo: 'Concluir chamado', sub: 'Descreva o que foi feito para resolver o chamado.', obrig: true },
    rejeitado:  { titulo: 'Rejeitar chamado',  sub: 'Informe o motivo da rejeição.',                   obrig: true },
    cancelado:  { titulo: 'Cancelar chamado',  sub: 'Informe o motivo do cancelamento.',               obrig: true },
    devolvido:  { titulo: 'Devolver chamado',  sub: 'Informe o que precisa ser complementado.',        obrig: true },
};

document.getElementById('btn-salvar-acao').addEventListener('click', function () {
    const status = document.getElementById('sel-status').value;
    if (statusesComModal.includes(status)) {
        abrirModal(status);
    } else {
        document.getElementById('form-acoes').submit();
    }
});

const resolucaoAtual = @json($sr->resolution_notes ?? '');

function abrirModal(status) {
    const cfg = titulosModal[status] || { titulo: 'Confirmar ação', sub: '', obrig: false };
    document.getElementById('modal-titulo').textContent = cfg.titulo;
    document.getElementById('modal-subtitulo').textContent = cfg.sub;
    document.getElementById('modal-obrig').style.display = cfg.obrig ? 'inline' : 'none';
    document.getElementById('modal-notes').value = resolucaoAtual;
    document.getElementById('modal-resolucao').classList.remove('hidden');
    document.getElementById('modal-resolucao').classList.add('flex');
    document.getElementById('modal-notes').focus();
}

function fecharModal() {
    document.getElementById('modal-resolucao').classList.add('hidden');
    document.getElementById('modal-resolucao').classList.remove('flex');
}

function confirmarModal() {
    const notes = document.getElementById('modal-notes').value.trim();
    const status = document.getElementById('sel-status').value;
    const obrig = titulosModal[status]?.obrig ?? false;
    if (obrig && !notes) {
        document.getElementById('modal-notes').classList.add('border-red-400');
        document.getElementById('modal-notes').focus();
        return;
    }
    // Transferir notas para o campo oculto
    document.getElementById('hidden-notes').value = notes;

    // Transferir arquivos para o input oculto via DataTransfer
    const modalFiles = document.getElementById('modal-files').files;
    if (modalFiles.length > 0) {
        const dt = new DataTransfer();
        for (let i = 0; i < modalFiles.length; i++) dt.items.add(modalFiles[i]);
        document.getElementById('hidden-files').files = dt.files;
    }

    fecharModal();
    document.getElementById('form-acoes').submit();
}

// Fechar modal clicando fora
document.getElementById('modal-resolucao').addEventListener('click', function(e) {
    if (e.target === this) fecharModal();
});
</script>
@endpush
@endsection