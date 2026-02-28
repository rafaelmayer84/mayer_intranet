@extends('layouts.app')
@section('title', 'Chamado #' . $sr->id)

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

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
                    <h1 class="text-xl font-bold text-[#1B334A]">#{{ $sr->id }} — {{ $sr->subject }}</h1>
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
                <p class="text-[10px] uppercase tracking-wider text-gray-400 mb-1">Descricao</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $sr->description }}</p>
            </div>

            @if($sr->resolution_notes)
            <div class="mt-4 pt-4 border-t border-gray-50">
                <p class="text-[10px] uppercase tracking-wider text-gray-400 mb-1">Resolucao</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $sr->resolution_notes }}</p>
            </div>
            @endif

            {{-- Anexos --}}
            @if($sr->attachments && count($sr->attachments) > 0)
            <div class="mt-4 pt-4 border-t border-gray-50">
                <p class="text-[10px] uppercase tracking-wider text-gray-400 mb-2">Anexos</p>
                <div class="flex flex-wrap gap-2">
                    @foreach($sr->attachments as $att)
                        <a href="{{ asset('storage/' . $att) }}" target="_blank" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-gray-50 border text-xs text-gray-600 hover:bg-gray-100 transition">
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
                <h3 class="text-sm font-bold text-[#1B334A] mb-3"><i class="fa-solid fa-sliders mr-1.5 text-gray-400"></i> Acoes</h3>
                <form method="POST" action="{{ route('chamados.update', $sr->id) }}" class="space-y-3">
                    @csrf @method('PUT')
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Status</label>
                            <select name="status" class="w-full border rounded-xl px-3 py-2 text-sm">
                                @foreach(['aberto','em_andamento','aguardando_aprovacao','aprovado','rejeitado','concluido','cancelado'] as $st)
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
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Notas de resolucao</label>
                        <textarea name="resolution_notes" rows="2" class="w-full border rounded-xl px-3 py-2 text-sm" placeholder="Descreva o que foi feito">{{ $sr->resolution_notes }}</textarea>
                    </div>
                    <button type="submit" class="px-5 py-2 text-sm font-medium text-white rounded-xl transition" style="background: linear-gradient(135deg, #385776, #1B334A);">Salvar</button>
                </form>
            </div>

            {{-- Comentarios --}}
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
                <h3 class="text-sm font-bold text-[#1B334A] mb-4"><i class="fa-solid fa-comments mr-1.5 text-gray-400"></i> Comentarios ({{ $sr->comments->count() }})</h3>
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
                    </div>
                @endforeach
                <form method="POST" action="{{ route('chamados.comment', $sr->id) }}" class="mt-4 space-y-2">
                    @csrf
                    <textarea name="body" required rows="2" maxlength="3000" placeholder="Adicionar comentario..." class="w-full border rounded-xl px-3 py-2 text-sm"></textarea>
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-1.5 text-xs text-gray-500">
                            <input type="checkbox" name="is_internal" value="1" class="rounded"> Nota interna
                        </label>
                        <button type="submit" class="px-4 py-2 text-sm font-medium text-white rounded-xl transition" style="background: linear-gradient(135deg, #385776, #1B334A);">Comentar</button>
                    </div>
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
@endsection
