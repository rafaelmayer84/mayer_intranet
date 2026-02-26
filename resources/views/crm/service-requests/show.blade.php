@extends('layouts.app')
@section('title', 'Solicitação #' . $sr->id)

@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">
    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('crm.accounts.show', $sr->account_id) }}#solicitacoes" class="text-sm text-[#385776] hover:underline">← Voltar ao cliente</a>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h1 class="text-xl font-bold text-[#1B334A]">#{{ $sr->id }} — {{ $sr->subject }}</h1>
                <p class="text-sm text-gray-500 mt-1">Cliente: <a href="{{ route('crm.accounts.show', $sr->account_id) }}" class="text-[#385776] hover:underline">{{ $sr->account->name }}</a></p>
            </div>
            <div class="flex items-center gap-2">
                <span class="px-3 py-1 rounded-full text-xs font-medium {{ App\Models\Crm\CrmServiceRequest::statusBadge($sr->status) }}">{{ App\Models\Crm\CrmServiceRequest::statusLabel($sr->status) }}</span>
                <span class="px-3 py-1 rounded-full text-xs font-medium {{ App\Models\Crm\CrmServiceRequest::priorityBadge($sr->priority) }}">{{ ucfirst($sr->priority) }}</span>
            </div>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4 text-sm">
            <div><span class="text-xs text-gray-400 block">Categoria</span><span class="font-medium">{{ $categorias[$sr->category]['label'] ?? $sr->category }}</span></div>
            <div><span class="text-xs text-gray-400 block">Solicitante</span><span class="font-medium">{{ $sr->requestedBy->name ?? '-' }}</span></div>
            <div><span class="text-xs text-gray-400 block">Atribuído a</span><span class="font-medium">{{ $sr->assignedTo->name ?? 'Não atribuído' }}</span></div>
            <div><span class="text-xs text-gray-400 block">Criado em</span><span class="font-medium">{{ $sr->created_at->format('d/m/Y H:i') }}</span></div>
        </div>

        @if($sr->requires_approval)
            <div class="mb-4 px-3 py-2 rounded-lg bg-purple-50 border border-purple-200 text-xs text-purple-700">
                ⚠️ Esta solicitação requer aprovação da diretoria.
                @if($sr->approved_by_user_id)
                    {{ $sr->status === 'aprovado' ? '✅' : '❌' }} {{ App\Models\Crm\CrmServiceRequest::statusLabel($sr->status) }} por {{ $sr->approvedBy->name }} em {{ $sr->approved_at?->format('d/m/Y H:i') }}
                @endif
            </div>
        @endif

        <div class="border-t pt-4">
            <p class="text-xs text-gray-400 mb-1">Descrição</p>
            <p class="text-sm text-gray-700 whitespace-pre-line">{{ $sr->description }}</p>
        </div>

        @if($sr->resolution_notes)
            <div class="border-t pt-4 mt-4">
                <p class="text-xs text-gray-400 mb-1">Resolução</p>
                <p class="text-sm text-gray-700 whitespace-pre-line">{{ $sr->resolution_notes }}</p>
            </div>
        @endif
    </div>

    {{-- Ações --}}
    <div class="bg-white rounded-xl shadow-sm border p-6 mb-6">
        <h3 class="text-sm font-semibold text-[#1B334A] mb-3">Ações</h3>
        <form method="POST" action="{{ route('crm.service-requests.update', $sr->id) }}" class="space-y-3">
            @csrf @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Status</label>
                    <select name="status" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(['aberto','em_andamento','aguardando_aprovacao','aprovado','rejeitado','concluido','cancelado'] as $st)
                            <option value="{{ $st }}" {{ $sr->status === $st ? 'selected' : '' }}>{{ App\Models\Crm\CrmServiceRequest::statusLabel($st) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Atribuir a</label>
                    <select name="assigned_to_user_id" class="w-full border rounded-lg px-3 py-2 text-sm">
                        <option value="">Não atribuído</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" {{ $sr->assigned_to_user_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Prioridade</label>
                    <select name="priority" class="w-full border rounded-lg px-3 py-2 text-sm">
                        @foreach(['baixa','normal','alta','urgente'] as $p)
                            <option value="{{ $p }}" {{ $sr->priority === $p ? 'selected' : '' }}>{{ ucfirst($p) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-xs text-gray-500 mb-1">Notas de resolução (ao concluir)</label>
                <textarea name="resolution_notes" rows="2" class="w-full border rounded-lg px-3 py-2 text-sm" placeholder="Descreva o que foi feito para resolver">{{ $sr->resolution_notes }}</textarea>
            </div>
            <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">Salvar Alterações</button>
        </form>
    </div>

    {{-- Comentários --}}
    <div class="bg-white rounded-xl shadow-sm border p-6">
        <h3 class="text-sm font-semibold text-[#1B334A] mb-4">Comentários ({{ $sr->comments->count() }})</h3>

        @foreach($sr->comments as $comment)
            <div class="border-b border-gray-50 py-3 {{ $comment->is_internal ? 'bg-yellow-50/50 -mx-2 px-2 rounded' : '' }}">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xs font-medium text-gray-700">{{ $comment->user->name ?? '-' }}</span>
                    <span class="text-xs text-gray-400">{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                    @if($comment->is_internal)
                        <span class="text-xs px-1.5 py-0.5 rounded bg-yellow-100 text-yellow-700">Interno</span>
                    @endif
                </div>
                <p class="text-sm text-gray-600 whitespace-pre-line">{{ $comment->body }}</p>
            </div>
        @endforeach

        <form method="POST" action="{{ route('crm.service-requests.comment', $sr->id) }}" class="mt-4 space-y-2">
            @csrf
            <textarea name="body" required rows="2" maxlength="3000" placeholder="Adicionar comentário..." class="w-full border rounded-lg px-3 py-2 text-sm"></textarea>
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-1.5 text-xs text-gray-500">
                    <input type="checkbox" name="is_internal" value="1" class="rounded">
                    Nota interna (não visível para o cliente)
                </label>
                <button type="submit" class="px-4 py-2 bg-[#385776] text-white rounded-lg text-sm hover:bg-[#1B334A] transition">Comentar</button>
            </div>
        </form>
    </div>
</div>
@endsection
