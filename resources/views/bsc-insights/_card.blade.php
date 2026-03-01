{{--
    BSC Insight Card v2 — Visual Premium com formatação humanizada
    Vars: $card (BscInsightCardV2), $delay (int ms), $universo (string)
--}}
@php
    $sev = strtoupper($card->severidade ?? 'info');
    $sevClass = match($sev) { 'CRITICO' => 'card-critico', 'ATENCAO' => 'card-atencao', default => 'card-info' };
    $sevPill = match($sev) { 'CRITICO' => 'sev-critico', 'ATENCAO' => 'sev-atencao', default => 'sev-info' };
    $sevLabel = match($sev) { 'CRITICO' => '🔴 Crítico', 'ATENCAO' => '🟡 Atenção', default => '🔵 Info' };
    $universoIcons = [
        'FINANCEIRO' => ['💸','📊','💳','📈','🏦','💰'],
        'CLIENTES_MERCADO' => ['🎯','📋','🤝','👥','📞','🔍'],
        'PROCESSOS_INTERNOS' => ['⚡','📁','⏱️','⚙️','📌','🔧'],
        'TIMES_EVOLUCAO' => ['🏆','⏰','💬','📱','🎓','👤'],
    ];
    $uKey = $universo ?? 'FINANCEIRO';
    $icons = $universoIcons[$uKey] ?? $universoIcons['FINANCEIRO'];
    $icon = $icons[$loop->index % count($icons)];
    $conf = $card->confidence ?? 50;
    $impact = $card->impact_score ?? 0;
    $evidences = $card->evidencias;

    // Detectar tipo pelo nome da métrica
    $detectType = function($metrica) {
        $m = strtolower((string) $metrica);
        if (preg_match('/var_pct|_pct|percent|taxa_|margem|win_rate|conversao|concentracao/', $m)) return 'pct';
        if (preg_match('/receita|despesa|resultado|inadimpl|vencid|pipeline|valor|burn|custo|salario|lucro|pagamento|alugu/', $m)) return 'money';
        if (preg_match('/hora|hours/', $m)) return 'hours';
        return 'count';
    };

    // Formatar número por tipo
    $fmtNum = function($num, $type) {
        $num = (float) $num;
        if ($type === 'money') return 'R$ ' . number_format($num, 2, ',', '.');
        if ($type === 'pct') return number_format($num, 1, ',', '.') . '%';
        if ($type === 'hours') return number_format($num, 1, ',', '.') . 'h';
        if ($num == (int) $num) return number_format($num, 0, ',', '.');
        return number_format($num, 2, ',', '.');
    };

    // Formatar valor com contexto da métrica
    $formatVal = function($v, $metrica = '') use ($detectType, $fmtNum) {
        if (!is_string($v) && !is_numeric($v)) return $v;
        $v = (string) $v;
        if (preg_match('/R\$|%|\bh\b|processo|lead|cliente|conversa|mensag|registro/i', $v)) return $v;
        if (str_contains($v, '{') || $v === '[]') return $v;
        $type = $detectType($metrica);
        if (is_numeric($v)) return $fmtNum($v, $type);
        if (preg_match('/^(\d{4}-\d{2}):\s*([\d.-]+)$/', trim($v), $m)) {
            try { $mesLabel = \Carbon\Carbon::createFromFormat('Y-m', $m[1])->translatedFormat('M/y'); } catch(\Throwable $e) { $mesLabel = $m[1]; }
            return $mesLabel . ': ' . $fmtNum($m[2], $type);
        }
        if (preg_match_all('/([\w_.]+):\s*([\d.,]+)/', $v, $ms, PREG_SET_ORDER) && count($ms) >= 2) {
            $parts = [];
            foreach ($ms as $match) {
                $num = (float) str_replace(',', '.', $match[2]);
                $label = ucfirst(trim(str_replace('_', ' ', preg_replace('/\b(id|pct)\b/i', '', $match[1]))));
                $parts[] = $label . ': ' . ($num == (int)$num ? number_format($num, 0, ',', '.') : number_format($num, 1, ',', '.'));
            }
            return implode(' | ', array_slice($parts, 0, 3));
        }
        return $v;
    };

    // Humanizar nome de métrica técnica
    $humanizeMetric = function($m) {
        if (!is_string($m)) return $m;
        if (!str_contains($m, '.') && !str_contains($m, '_')) return $m;
        $map = [
            'finance.receita_total_mensal' => 'Receita Total',
            'finance.resultado_mensal' => 'Resultado Mensal',
            'finance.despesas_mensal' => 'Despesas',
            'finance.margem_liquida_pct' => 'Margem Líquida',
            'finance.top5_planos_despesa' => 'Maiores Despesas',
            'inadimplencia.total_vencido' => 'Inadimplência Total',
            'inadimplencia.aging_buckets' => 'Aging',
            'inadimplencia.top5_devedores' => 'Maiores Devedores',
            'processos.ativos' => 'Processos Ativos',
            'processos.total' => 'Total de Processos',
            'processos.sem_movimentacao_90d' => 'Processos Parados (90d)',
            'processos.novos_mensal' => 'Novos Processos',
            'processos.encerrados_mensal' => 'Processos Encerrados',
            'processos.encerrados' => 'Processos Encerrados',
            'processos.por_area' => 'Processos por Área',
            'clientes.total_clientes' => 'Total de Clientes',
            'clientes.novos_clientes_mensal' => 'Novos Clientes',
            'clientes.clientes_por_area' => 'Clientes por Área',
            'crm.total_accounts' => 'Contas CRM',
            'crm.oportunidades' => 'Oportunidades',
            'leads.total' => 'Total de Leads',
            'leads.leads_mensal' => 'Leads por Mês',
            'leads.por_status' => 'Leads por Status',
            'horas.horas_mensal' => 'Horas Trabalhadas',
            'horas.por_proprietario' => 'Horas por Profissional',
            'horas.total_registros' => 'Registros de Horas',
            'horas.valor_mensal' => 'Valor Faturável',
            'atendimento.mensagens_mensal' => 'Mensagens',
            'atendimento.conversas_mensal' => 'Conversas',
            'atendimento.total_mensagens' => 'Total Mensagens',
            'gdp.snapshots' => 'GDP Snapshots',
            'gdp.resultados' => 'GDP Resultados',
        ];
        if (isset($map[$m])) return $map[$m];
        $clean = preg_replace('/^metricas_derivadas\./', '', $m);
        if (isset($map[$clean])) return $map[$clean];
        // Remover indices [0], [1] etc e tentar novamente
        $noIdx = preg_replace('/\[\d+\].*$/', '', $m);
        if (isset($map[$noIdx])) return $map[$noIdx];
        $noIdx2 = preg_replace('/^metricas_derivadas\./', '', $noIdx);
        if (isset($map[$noIdx2])) return $map[$noIdx2];
        // Remover prefixo de periodo (ex: finance.receita_total_mensal.2026-01)
        $noPeriod = preg_replace('/\.\d{4}-\d{2}$/', '', $m);
        if (isset($map[$noPeriod])) return $map[$noPeriod];
        $noPeriod2 = preg_replace('/^metricas_derivadas\./', '', $noPeriod);
        if (isset($map[$noPeriod2])) return $map[$noPeriod2];
        $parts = explode('.', $m);
        $last = end($parts);
        return ucfirst(str_replace('_', ' ', $last));
    };

    $mainValue = isset($evidences[0]['valor']) ? $formatVal($evidences[0]['valor'], $evidences[0]['metrica'] ?? '') : null;
    $mainLabel = isset($evidences[0]['metrica']) ? $humanizeMetric($evidences[0]['metrica']) : null;
    $circumference = 2 * 3.14159 * 14;
    $offset = $circumference - ($conf / 100) * $circumference;
    $ringColor = $conf >= 70 ? '#10b981' : ($conf >= 40 ? '#f59e0b' : '#ef4444');
    $detId = 'det-server-' . $card->id;
@endphp

<div class="insight-card {{ $sevClass }} animate-card" style="animation-delay:{{ $delay ?? 0 }}ms" onclick="toggleDetail('{{ $detId }}')">
    <div class="card-accent"></div>
    <div class="p-5">
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

                @if($mainValue)
                <div class="metric-value">{!! e($mainValue) !!}</div>
                <div class="text-xs text-gray-400 mb-2">{{ $mainLabel }}</div>
                @endif

                <h3 class="font-bold text-gray-800 text-base leading-snug">{{ $card->titulo }}</h3>
            </div>
        </div>

        <p class="conclusion-text mt-3">{{ $card->descricao }}</p>

        @if(count($evidences) > 1)
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach(array_slice($evidences, 1, 3) as $ev)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white/70 border border-gray-100 text-xs">
                <span class="text-gray-400">{{ $humanizeMetric($ev['metrica'] ?? '') }}</span>
                <span class="font-bold text-gray-700">{!! e($formatVal($ev['valor'] ?? '', $ev['metrica'] ?? '')) !!}</span>
                @if(!empty($ev['variacao']))
                <span class="text-gray-400">({{ $ev['variacao'] }})</span>
                @endif
            </span>
            @endforeach
        </div>
        @endif

        <div class="flex items-center justify-between mt-4">
            @if($card->recomendacao)
            <div class="action-badge" title="Clique para detalhes">
                💡 {{ Str::limit($card->recomendacao, 65) }}
            </div>
            @endif
            <div class="impact-bar w-16 ml-auto" title="Impacto: {{ $impact }}/10">
                <div class="impact-bar-fill" style="width:{{ $impact * 10 }}%;background:{{ $impact >= 7 ? '#ef4444' : ($impact >= 4 ? '#f59e0b' : '#3b82f6') }}"></div>
            </div>
        </div>

        <div id="{{ $detId }}" class="detail-panel">
            <div class="mt-4 pt-4 border-t border-gray-200/60 space-y-3">
                <div class="flex items-start gap-2">
                    <span class="text-sm mt-0.5">✅</span>
                    <div>
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Recomendação</div>
                        <p class="text-sm font-semibold text-gray-700">{{ $card->recomendacao }}</p>
                    </div>
                </div>
                @if($card->acao_sugerida)
                <div class="flex items-start gap-2">
                    <span class="text-sm mt-0.5">📋</span>
                    <div>
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-0.5">Próximo passo</div>
                        <p class="text-sm text-gray-700">{{ $card->acao_sugerida }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
