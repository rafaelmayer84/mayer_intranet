{{--
    _kpi-card.blade.php v3
    Componente universal de KPI Card — RESULTADOS!

    Variáveis aceitas (todas opcionais exceto title e value):
      $id           string   Identificador único do card
      $title        string   Título do KPI
      $value        string   Valor formatado (ex: 'R$ 10.199,08')
      $subtitle     string   Linha de contexto (ex: '78% PF . 22% PJ')
      $meta         mixed    Meta formatada ou null
      $percent      float    Porcent de atingimento (0-100+)
      $trend        float    Variacao porcent vs periodo anterior
      $trendLabel   string   Label do trend (ex: 'vs mes ant.')
      $prevValue    string   Valor anterior formatado
      $icon         string   Emoji ou icone
      $accent       string   green|blue|orange|purple|red|yellow
      $sparkline    array    Array de ate 12 valores numericos
      $status       string   ok|atencao|critico|sem_meta (auto-calculado se omitido)
      $invertTrend  bool     Se true, trend negativo e bom. Default false.
      $href         string   URL para drill-down ao clicar (opcional)

    Retrocompativel: chamadas v2 continuam funcionando.
--}}

@php
    $id = $id ?? 'kpi-' . Str::random(6);
    $title = $title ?? 'KPI';
    $value = $value ?? '—';
    $subtitle = $subtitle ?? null;
    $icon = $icon ?? '📊';
    $accent = $accent ?? 'blue';
    $trend = isset($trend) ? (float) $trend : null;
    $invertTrend = $invertTrend ?? false;
    $trendLabel = $trendLabel ?? 'vs mês ant.';
    $prevValue = $prevValue ?? null;
    $sparkline = $sparkline ?? null;
    $href = $href ?? null;

    $hasMeta = isset($meta) && $meta !== null && $meta !== '' && $meta !== 'R$ 0,00' && $meta !== '0' && $meta !== 0;
    $percent = $hasMeta ? (float) ($percent ?? 0) : 0;

    if (isset($status)) {
        $statusCalc = $status;
    } elseif (!$hasMeta) {
        $statusCalc = 'sem_meta';
    } elseif ($percent >= 90) {
        $statusCalc = 'ok';
    } elseif ($percent >= 70) {
        $statusCalc = 'atencao';
    } else {
        $statusCalc = 'critico';
    }

    $accentColors = [
        'green'  => ['border' => 'border-emerald-500', 'bg' => 'bg-emerald-50 dark:bg-emerald-900/20',  'text' => 'text-emerald-600 dark:text-emerald-400', 'ring' => 'ring-emerald-500/20'],
        'blue'   => ['border' => 'border-blue-500',    'bg' => 'bg-blue-50 dark:bg-blue-900/20',        'text' => 'text-blue-600 dark:text-blue-400',       'ring' => 'ring-blue-500/20'],
        'orange' => ['border' => 'border-orange-500',   'bg' => 'bg-orange-50 dark:bg-orange-900/20',   'text' => 'text-orange-600 dark:text-orange-400',   'ring' => 'ring-orange-500/20'],
        'purple' => ['border' => 'border-purple-500',   'bg' => 'bg-purple-50 dark:bg-purple-900/20',   'text' => 'text-purple-600 dark:text-purple-400',   'ring' => 'ring-purple-500/20'],
        'red'    => ['border' => 'border-red-500',      'bg' => 'bg-red-50 dark:bg-red-900/20',         'text' => 'text-red-600 dark:text-red-400',         'ring' => 'ring-red-500/20'],
        'yellow' => ['border' => 'border-yellow-500',   'bg' => 'bg-yellow-50 dark:bg-yellow-900/20',   'text' => 'text-yellow-600 dark:text-yellow-400',   'ring' => 'ring-yellow-500/20'],
    ];
    $ac = $accentColors[$accent] ?? $accentColors['blue'];

    $statusConfig = match($statusCalc) {
        'ok'       => ['label' => 'No alvo',  'bg' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300', 'dot' => 'bg-emerald-500'],
        'atencao'  => ['label' => 'Atenção',   'bg' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300',     'dot' => 'bg-yellow-500'],
        'critico'  => ['label' => 'Crítico',   'bg' => 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300',                 'dot' => 'bg-red-500'],
        'sem_meta' => ['label' => '',           'bg' => '',                                                                              'dot' => ''],
        default    => ['label' => '',           'bg' => '',                                                                              'dot' => ''],
    };

    $trendHtml = '';
    if ($trend !== null && $trend != 0) {
        $isGood = $invertTrend ? ($trend <= 0) : ($trend >= 0);
        $arrow = $trend >= 0 ? '↑' : '↓';
        $trendColor = $isGood
            ? 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-900/30'
            : 'text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/30';
        $trendText = $arrow . ' ' . number_format(abs($trend), 1, ',', '.') . '%';
        $trendHtml = '<span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold ' . $trendColor . '">' . $trendText . '</span>';
    }
@endphp

<div id="card-{{ $id }}"
     @if($href) onclick="window.location='{{ $href }}'" @endif
     class="group relative rounded-2xl border-t-4 {{ $ac['border'] }} bg-white dark:bg-gray-800 p-4 shadow-sm
            hover:shadow-lg hover:ring-2 {{ $ac['ring'] }} transition-all duration-200
            {{ $href ? 'cursor-pointer' : '' }} overflow-hidden">

    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5 flex-wrap">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">{{ $title }}</p>
                @if($statusConfig['label'])
                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold {{ $statusConfig['bg'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusConfig['dot'] }}"></span>
                        {{ $statusConfig['label'] }}
                    </span>
                @endif
            </div>

            <p class="mt-2 text-2xl font-extrabold text-gray-900 dark:text-white leading-none tracking-tight">{{ $value }}</p>

            @if($trendHtml || $prevValue)
                <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                    {!! $trendHtml !!}
                    @if($prevValue)
                        <span class="text-[10px] text-gray-400 dark:text-gray-500">{{ $trendLabel }}: {{ $prevValue }}</span>
                    @endif
                </div>
            @endif

            @if($hasMeta)
                <div class="mt-2 flex items-center gap-2">
                    <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-700 {{ $percent >= 90 ? 'bg-emerald-500' : ($percent >= 70 ? 'bg-yellow-500' : 'bg-red-500') }}"
                             style="width: {{ min($percent, 100) }}%"></div>
                    </div>
                    <span class="text-[10px] font-bold {{ $percent >= 90 ? 'text-emerald-600 dark:text-emerald-400' : ($percent >= 70 ? 'text-yellow-600 dark:text-yellow-400' : 'text-red-600 dark:text-red-400') }}">
                        {{ number_format($percent, 0) }}%
                    </span>
                </div>
                <p class="mt-0.5 text-[10px] text-gray-400 dark:text-gray-500">Meta: {{ $meta }}</p>
            @endif

            @if($subtitle)
                <p class="mt-1 text-[10px] text-gray-500 dark:text-gray-400 leading-tight">{{ $subtitle }}</p>
            @endif
        </div>

        <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-xl {{ $ac['bg'] }} group-hover:scale-110 transition-transform">
            <span class="text-xl">{{ $icon }}</span>
        </div>
    </div>

    @if(is_array($sparkline) && count($sparkline) >= 2)
        @php
            $spk = array_values(array_slice($sparkline, -12));
            $spkCount = count($spk);
            $spkMin = min($spk);
            $spkMax = max($spk);
            $spkRange = max($spkMax - $spkMin, 1);
            $svgW = 200;
            $svgH = 36;
            $padding = 2;
            $points = [];
            for ($i = 0; $i < $spkCount; $i++) {
                $x = $padding + ($i / max($spkCount - 1, 1)) * ($svgW - 2 * $padding);
                $y = $padding + (1 - ($spk[$i] - $spkMin) / $spkRange) * ($svgH - 2 * $padding);
                $points[] = round($x, 1) . ',' . round($y, 1);
            }
            $polyline = implode(' ', $points);
            $areaPath = 'M' . $points[0];
            for ($i = 1; $i < $spkCount; $i++) { $areaPath .= ' L' . $points[$i]; }
            $lastX = $padding + (($spkCount - 1) / max($spkCount - 1, 1)) * ($svgW - 2 * $padding);
            $firstX = $padding;
            $areaPath .= ' L' . round($lastX, 1) . ',' . $svgH . ' L' . round($firstX, 1) . ',' . $svgH . ' Z';
            $lastY = $padding + (1 - ($spk[$spkCount - 1] - $spkMin) / $spkRange) * ($svgH - 2 * $padding);
            $spkStroke = match($accent) {
                'green' => '#10b981', 'blue' => '#3b82f6', 'orange' => '#f97316',
                'purple' => '#8b5cf6', 'red' => '#ef4444', 'yellow' => '#eab308',
                default => '#6b7280',
            };
        @endphp
        <div class="mt-3 -mx-1">
            <svg width="{{ $svgW }}" height="{{ $svgH }}" viewBox="0 0 {{ $svgW }} {{ $svgH }}" class="w-full" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="grad-{{ $id }}" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="{{ $spkStroke }}" stop-opacity="0.2"/>
                        <stop offset="100%" stop-color="{{ $spkStroke }}" stop-opacity="0.02"/>
                    </linearGradient>
                </defs>
                <path d="{{ $areaPath }}" fill="url(#grad-{{ $id }})" />
                <polyline points="{{ $polyline }}" fill="none" stroke="{{ $spkStroke }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                <circle cx="{{ round($lastX, 1) }}" cy="{{ round($lastY, 1) }}" r="3" fill="{{ $spkStroke }}" />
            </svg>
        </div>
    @endif
</div>
