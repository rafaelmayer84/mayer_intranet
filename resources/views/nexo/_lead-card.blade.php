@php
    $lead    = $conv->lead;
    $sessao  = $conv->lexusSessao;
    $nome    = $lead?->nome ?? $conv->name ?? 'Sem nome';
    $area    = $lead?->area_interesse ?? '—';
    $cidade  = $lead?->cidade ?? '—';
    $briefing = $sessao?->briefing_operador ?? $lead?->resumo_demanda ?? null;
@endphp

<div id="lead-card-{{ $conv->id }}"
     class="bg-white rounded-xl shadow-sm border border-gray-100 border-l-4 p-4 flex flex-col gap-3"
     style="border-left-color: {{ $cor }}">

    <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="font-semibold text-[#1B334A] text-sm">{{ $nome }}</span>
                @if($cidade !== '—')
                    <span class="text-xs text-gray-400">· {{ $cidade }}</span>
                @endif
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white"
                      style="background-color: {{ $cor }}">
                    {{ $slaLabel }}
                </span>
            </div>
            <div class="flex items-center gap-2 mt-1 flex-wrap">
                @if($area !== '—')
                    <span class="text-xs px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-medium">{{ $area }}</span>
                @endif
                @if($lead?->urgencia)
                    <span class="text-xs text-gray-400">Urgência: <strong>{{ $lead->urgencia }}</strong></span>
                @endif
                @if($lead?->intencao_contratar)
                    <span class="text-xs text-gray-400">Intenção: <strong>{{ $lead->intencao_contratar }}</strong></span>
                @endif
            </div>
        </div>
        <div class="text-right shrink-0">
            <p class="text-xs text-gray-400">{{ $conv->last_incoming_at?->format('d/m H:i') }}</p>
            <p class="text-xs text-gray-300">conv #{{ $conv->id }}</p>
        </div>
    </div>

    @if($briefing)
    <div class="bg-gray-50 rounded-lg px-3 py-2 border border-gray-100">
        <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-1">Briefing para o advogado</p>
        <p class="text-xs text-gray-700 leading-relaxed">{{ $briefing }}</p>
    </div>
    @endif

    <div class="flex items-center gap-2 pt-1">
        <a href="{{ route('nexo.atendimento') }}?conv={{ $conv->id }}"
           target="_blank"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold text-white transition"
           style="background-color: #385776">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            Abrir conversa
        </a>
        <button onclick="marcarAtendido({{ $conv->id }}, this)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-gray-600 bg-gray-100 hover:bg-gray-200 transition disabled:opacity-50">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            Marcar como atendido
        </button>
    </div>
</div>
