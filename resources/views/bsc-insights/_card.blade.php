{{--
    BSC Insight Card — Visual Premium
    Vars: $card (BscInsightCard), $delay (int ms)
--}}
@php
    $sev = strtoupper($card->severidade ?? 'INFO');
    $sevClass = match($sev) { 'CRITICO' => 'card-critico', 'ATENCAO' => 'card-atencao', default => 'card-info' };
    $sevPill = match($sev) { 'CRITICO' => 'sev-critico', 'ATENCAO' => 'sev-atencao', default => 'sev-info' };
    $sevLabel = match($sev) { 'CRITICO' => '🔴 Crítico', 'ATENCAO' => '🟡 Atenção', default => '🔵 Info' };
    $universoIcons = [
        'FINANCEIRO' => ['💸','📊','💳','📈','🏦','💰'],
        'CLIENTES_MERCADO' => ['🎯','📋','🤝','👥','📞','🔍'],
        'PROCESSOS_INTERNOS' => ['⚡','📁','⏱️','⚙️','📌','🔧'],
        'TIMES_EVOLUCAO' => ['🏆','⏰','💬','📱','🎓','👤'],
    ];
    $icons = $universoIcons[$card->universo] ?? $universoIcons['FINANCEIRO'];
    $icon = $icons[$loop->index % count($icons)];
    $conf = $card->confidence ?? 50;
    $impact = $card->impact_score ?? 0;
    $evidences = is_array($card->evidences) ? $card->evidences : (is_string($card->evidences) ? json_decode($card->evidences, true) : []);
    $questions = is_array($card->questions) ? $card->questions : (is_string($card->questions) ? json_decode($card->questions, true) : []);
    $mainValue = $evidences[0]['value'] ?? null;
    $mainLabel = $evidences[0]['metric'] ?? null;
    $circumference = 2 * 3.14159 * 14;
    $offset = $circumference - ($conf / 100) * $circumference;
    $ringColor = $conf >= 70 ? '#10b981' : ($conf >= 40 ? '#f59e0b' : '#ef4444');
    $detId = 'det-server-' . $card->id;
@endphp

<div class="insight-card {{ $sevClass }} animate-card" style="animation-delay:{{ $delay ?? 0 }}ms" onclick="toggleDetail('{{ $detId }}')">
    <div class="card-accent"></div>
    <div class="p-5">
        {{-- Top row: icon + content + confidence ring --}}
        <div class="flex items-start gap-4">
            <div class="card-icon">{{ $icon }}</div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="sev-pill {{ $sevPill }}">{{ $sevLabel }}</span>
                    <div class="confidence-ring ml-auto" title="Confiança {{ $conf }}%">
                        <svg width="36" height="36">
                            <circle cx="18" cy="18" r="14" fill="none" stroke="#e2e8f0" stroke-width="3"/>
                            <circle cx="18" cy="18" r="14" fill="none" stroke="{{ $ringColor }}" stroke-width="3"
                                    stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $offset }}" stroke-linecap="round"/>
                        </svg>
                        <span class="ring-text">{{ $conf }}</span>
                    </div>
                </div>

                {{-- Metric hero --}}
                @if($mainValue)
                <div class="metric-value">{{ $mainValue }}</div>
                <div class="text-xs text-gray-400 mb-2">{{ $mainLabel }}</div>
                @endif

                {{-- Title = conclusão curta da IA --}}
                <h3 class="font-bold text-gray-800 text-base leading-snug">{{ $card->title }}</h3>
            </div>
        </div>

        {{-- Insight conclusion --}}
        <p class="conclusion-text mt-3">{{ $card->what_changed }}</p>

        {{-- Evidence chips --}}
        @if(count($evidences) > 1)
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach(array_slice($evidences, 1, 3) as $ev)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/70 border border-gray-100 text-xs">
                <span class="text-gray-400">{{ $ev['metric'] ?? '' }}</span>
                <span class="font-bold text-gray-700">{{ $ev['value'] ?? '' }}</span>
            </span>
            @endforeach
        </div>
        @endif

        {{-- Action badge + impact --}}
        <div class="flex items-center justify-between mt-4">
            @if($card->recommendation)
            <div class="action-badge" title="Clique para detalhes">
                💡 {{ Str::limit($card->recommendation, 65) }}
            </div>
            @endif
            <div class="impact-bar w-16 ml-auto" title="Impacto: {{ $impact }}/10">
                <div class="impact-bar-fill" style="width:{{ $impact * 10 }}%;background:{{ $impact >= 7 ? '#ef4444' : ($impact >= 4 ? '#f59e0b' : '#3b82f6') }}"></div>
            </div>
        </div>

        {{-- Collapsible details --}}
        <div id="{{ $detId }}" class="detail-panel">
            <div class="mt-4 pt-4 border-t border-gray-200/60 space-y-3">
                @if($card->why_it_matters)
                <div class="flex items-start gap-2">
                    <span class="text-sm mt-0.5">🎯</span>
                    <div>
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Por que importa</div>
                        <p class="text-sm text-gray-600">{{ $card->why_it_matters }}</p>
                    </div>
                </div>
                @endif
                @if($card->recommendation)
                <div class="flex items-start gap-2">
                    <span class="text-sm mt-0.5">✅</span>
                    <div>
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Recomendação</div>
                        <p class="text-sm font-semibold text-gray-700">{{ $card->recommendation }}</p>
                    </div>
                </div>
                @endif
                @if($card->next_step)
                <div class="flex items-start gap-2">
                    <span class="text-sm mt-0.5">📋</span>
                    <div>
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Próximo passo</div>
                        <p class="text-sm text-gray-700">{{ $card->next_step }}</p>
                    </div>
                </div>
                @endif
                @if(!empty($questions))
                <div class="flex items-start gap-2">
                    <span class="text-sm mt-0.5">❓</span>
                    <div>
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Investigar</div>
                        @foreach($questions as $q)
                        <p class="text-sm text-gray-500 italic">• {{ $q }}</p>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
