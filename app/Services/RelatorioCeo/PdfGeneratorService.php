<?php

namespace App\Services\RelatorioCeo;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdfGeneratorService
{
    private string $logoB64 = '';

    public function gerar(array $dados, array $analise, string $periodoLabel): string
    {
        $logoPath = public_path('logo-mayer.png');
        if (file_exists($logoPath)) {
            $this->logoB64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        $charts = $this->gerarCharts($dados);
        $html   = $this->renderHtml($dados, $analise, $periodoLabel, $charts);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', storage_path('app'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    private function gerarCharts(array $dados): array
    {
        $charts = [];

        $historico = $dados['financeiro']['historico_6meses'] ?? [];
        if (!empty($historico)) {
            $charts['receita_historico'] = $this->fetchChart([
                'type' => 'bar',
                'data' => [
                    'labels'   => array_column($historico, 'mes'),
                    'datasets' => [
                        ['label' => 'Receita', 'data' => array_column($historico, 'receita_total'), 'backgroundColor' => '#0a1628'],
                        ['label' => 'Despesas', 'data' => array_map(fn($v) => abs($v), array_column($historico, 'despesas')), 'backgroundColor' => '#c9a84c'],
                    ],
                ],
                'options' => [
                    'plugins' => ['legend' => ['labels' => ['font' => ['size' => 10]]]],
                    'scales'  => ['y' => ['ticks' => ['font' => ['size' => 9]]]],
                ],
            ], 560, 210);
        }

        $snapshots = $dados['gdp']['snapshots'] ?? [];
        if (!empty($snapshots)) {
            $charts['performance_adv'] = $this->fetchChart([
                'type' => 'horizontalBar',
                'data' => [
                    'labels'   => array_column($snapshots, 'name'),
                    'datasets' => [['label' => 'Score GDP', 'data' => array_column($snapshots, 'score_total'), 'backgroundColor' => '#385776']],
                ],
                'options' => [
                    'scales'  => ['x' => ['max' => 100, 'ticks' => ['font' => ['size' => 9]]]],
                    'plugins' => ['legend' => ['display' => false]],
                ],
            ], 480, 200);
        }

        $leadsArea = $dados['leads']['por_area'] ?? [];
        if (!empty($leadsArea)) {
            $areas  = array_keys($leadsArea);
            $totais = array_values($leadsArea);
            $charts['leads_area'] = $this->fetchChart([
                'type' => 'pie',
                'data' => [
                    'labels'   => array_slice($areas, 0, 6),
                    'datasets' => [['data' => array_slice($totais, 0, 6), 'backgroundColor' => ['#0a1628','#385776','#5d8ab4','#c9a84c','#e8c97a','#8896a6']]],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 9]]]]],
            ], 400, 180);
        }

        $porTipo = $dados['processos']['por_tipo_acao'] ?? [];
        if (!empty($porTipo)) {
            $charts['processos_tipo'] = $this->fetchChart([
                'type' => 'doughnut',
                'data' => [
                    'labels'   => array_slice(array_column($porTipo, 'tipo_acao'), 0, 6),
                    'datasets' => [['data' => array_slice(array_column($porTipo, 'total'), 0, 6), 'backgroundColor' => ['#0a1628','#385776','#5d8ab4','#c9a84c','#e8c97a','#8896a6']]],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 8]]]]],
            ], 340, 160);
        }

        $porIntencao = $dados['leads']['por_intencao_contratar'] ?? [];
        if (!empty($porIntencao)) {
            $charts['leads_intencao'] = $this->fetchChart([
                'type' => 'doughnut',
                'data' => [
                    'labels'   => array_keys($porIntencao),
                    'datasets' => [['data' => array_values($porIntencao), 'backgroundColor' => ['#27ae60','#f39c12','#c0392b','#95a5a6']]],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 9]]]]],
            ], 340, 150);
        }

        return $charts;
    }

    private function fetchChart(array $config, int $w = 520, int $h = 200): string
    {
        try {
            $json = urlencode(json_encode($config, JSON_UNESCAPED_UNICODE));
            $url  = "https://quickchart.io/chart?w={$w}&h={$h}&bkg=white&c={$json}";
            $resp = Http::timeout(15)->get($url);
            if ($resp->successful()) {
                return 'data:image/png;base64,' . base64_encode($resp->body());
            }
        } catch (\Exception $e) {
            Log::warning('RelatorioCeo PdfGenerator: QuickChart falhou', ['error' => $e->getMessage()]);
        }
        return '';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HTML RENDER
    // ─────────────────────────────────────────────────────────────────────────

    private function renderHtml(array $dados, array $analise, string $periodoLabel, array $charts): string
    {
        $fin        = $dados['financeiro'] ?? [];
        $nexo       = $dados['nexo'] ?? [];
        $gdp        = $dados['gdp'] ?? [];
        $proc       = $dados['processos'] ?? [];
        $leads      = $dados['leads'] ?? [];
        $wa         = $dados['whatsapp'] ?? [];
        $geradoEm   = now()->format('d/m/Y H:i');

        $scoreGeral    = $analise['score_geral'] ?? 0;
        $tituloPeriodo = $analise['titulo_periodo'] ?? '';
        $resumoExec    = $analise['resumo_executivo'] ?? '';
        $vozClientes   = $analise['voz_dos_clientes'] ?? [];
        $intMercado    = $analise['inteligencia_de_mercado'] ?? [];
        $aFin          = $analise['financeiro'] ?? [];
        $aEquipe       = $analise['performance_equipe'] ?? [];
        $aProc         = $analise['carteira_processos'] ?? [];
        $cruzamentos   = $analise['cruzamentos_estrategicos'] ?? [];
        $recomendacoes = $analise['recomendacoes_priorizadas'] ?? [];
        $monitorar     = $analise['o_que_monitorar_proximo_periodo'] ?? [];

        $dreAt     = $fin['dre_atual'] ?? [];
        $inadim    = $fin['inadimplencia'] ?? [];
        $varPct    = $fin['variacao_receita_pct'] ?? 0;
        $receita   = $dreAt['receita_total'] ?? 0;
        $despesas  = abs($dreAt['despesas'] ?? 0);
        $resultado = $dreAt['resultado'] ?? 0;
        $scoreCor  = $scoreGeral >= 7 ? '#27ae60' : ($scoreGeral >= 5 ? '#d97706' : '#c0392b');
        $logo      = $this->logoB64;

        ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
/* ── Reset ── */
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family: "DejaVu Sans", sans-serif; font-size:9px; color:#1B334A; background:#fff; line-height:1.55; }

/* ── Page header (repeated on every page via a sticky-style div) ── */
.page-header {
  background:#0a1628;
  padding:10px 24px;
  display:table;
  width:100%;
}
.page-header-left  { display:table-cell; vertical-align:middle; }
.page-header-right { display:table-cell; vertical-align:middle; text-align:right; }
.page-header-firm  { font-size:7px; color:#c9a84c; letter-spacing:2px; text-transform:uppercase; font-weight:bold; }
.page-header-section { font-size:9px; color:#fff; font-weight:bold; letter-spacing:.5px; margin-top:1px; }
.page-header-period { font-size:7.5px; color:#8896A6; margin-top:1px; }

/* ── Dashboard layout (Page 1) ── */
.dashboard-wrap { display:table; width:100%; border-collapse:collapse; }

/* Sidebar */
.sidebar {
  display:table-cell;
  width:29%;
  background:#EFF3F8;
  vertical-align:top;
  padding:18px 14px;
  border-right:2px solid #D8DEE6;
}
.sidebar-logo { text-align:center; margin-bottom:14px; }
.sidebar-logo img { max-width:80px; max-height:56px; }
.sidebar-prepared { background:#fff; border-radius:4px; padding:10px 10px; margin-bottom:14px; border:1px solid #D8DEE6; }
.sidebar-prepared-name { font-size:9px; font-weight:bold; color:#0a1628; }
.sidebar-prepared-role { font-size:7.5px; color:#8896A6; margin-top:1px; }
.sidebar-prepared-label { font-size:6.5px; color:#c9a84c; text-transform:uppercase; letter-spacing:1px; font-weight:bold; margin-bottom:5px; }

.sidebar-divider { border:none; border-top:1px solid #D8DEE6; margin:12px 0; }

.sidebar-metric { margin-bottom:10px; }
.sidebar-metric-label { font-size:7px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; display:block; }
.sidebar-metric-value { font-size:16px; font-weight:bold; color:#0a1628; display:block; margin-top:1px; line-height:1.1; }
.sidebar-metric-sub { font-size:7px; color:#8896A6; margin-top:1px; display:block; }
.sidebar-metric-badge { display:inline-block; padding:1px 6px; border-radius:10px; font-size:7px; font-weight:bold; }

.sidebar-chart-label { font-size:7px; color:#385776; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; margin-bottom:4px; display:block; }

/* Score badge */
.score-badge {
  display:inline-block;
  background:<?= $scoreCor ?>;
  color:#fff;
  padding:4px 10px;
  border-radius:3px;
  font-size:12px;
  font-weight:bold;
  letter-spacing:.5px;
}

/* Main content area */
.main-content {
  display:table-cell;
  width:71%;
  vertical-align:top;
  padding:16px 20px;
}

/* ── KPI Cards (3 side by side) ── */
.kpi-row { display:table; width:100%; border-collapse:separate; border-spacing:8px; margin-bottom:14px; }
.kpi-card {
  display:table-cell;
  background:#fff;
  border:1px solid #D8DEE6;
  border-radius:4px;
  padding:10px 12px;
  vertical-align:top;
}
.kpi-card-label  { font-size:7px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; }
.kpi-card-value  { font-size:17px; font-weight:bold; color:#0a1628; margin-top:3px; line-height:1.1; }
.kpi-card-var    { font-size:8px; margin-top:3px; }
.kpi-card-var.up   { color:#16a34a; }
.kpi-card-var.down { color:#dc2626; }
.kpi-card-var.flat { color:#8896A6; }

/* ── Section header strip ── */
.sec-header {
  background:#0a1628;
  padding:5px 12px;
  margin-bottom:10px;
  display:table;
  width:100%;
}
.sec-header-title { display:table-cell; font-size:8px; letter-spacing:1.5px; text-transform:uppercase; color:#c9a84c; font-weight:bold; }
.sec-header-sub   { display:table-cell; text-align:right; font-size:7px; color:#5a7080; vertical-align:middle; }

/* ── Full-page sections ── */
.section { padding:18px 24px; }
.section + .section { border-top:1px solid #E8ECF1; }
.page-break { page-break-before:always; }

/* ── Wide section header (for detail pages) ── */
.sh { background:#0a1628; padding:7px 24px; margin:0 -24px 14px; display:table; width:calc(100% + 48px); }
.sh h2 { font-size:9px; letter-spacing:1.5px; text-transform:uppercase; color:#c9a84c; display:table-cell; font-weight:bold; }
.sh .sh-sub { font-size:7.5px; color:#5a7080; display:table-cell; text-align:right; vertical-align:middle; }

/* ── Analise text ── */
.analise { font-size:9px; line-height:1.65; color:#2c3e50; margin-bottom:10px; text-align:justify; }
.analise p { margin-bottom:7px; }

/* ── Metrics row ── */
.metrics { display:table; width:100%; border-collapse:separate; border-spacing:5px; margin-bottom:12px; }
.mbox { display:table-cell; text-align:center; padding:8px 5px; background:#F2F5F8; border:1px solid #D8DEE6; border-radius:3px; }
.mval { font-size:14px; font-weight:bold; color:#0a1628; }
.mlbl { font-size:7px; color:#8896A6; text-transform:uppercase; letter-spacing:.5px; margin-top:2px; }
.mvar { font-size:8px; margin-top:2px; }
.pos { color:#16a34a; } .neg { color:#dc2626; }

/* ── Two-column layout ── */
.two { display:table; width:100%; }
.cl  { display:table-cell; width:58%; padding-right:12px; vertical-align:top; }
.cr  { display:table-cell; width:42%; vertical-align:top; }

/* ── Lists ── */
.ul { padding-left:13px; margin:5px 0 8px; }
.ul li { font-size:8.5px; margin-bottom:3px; line-height:1.5; }

/* ── Tables ── */
table.dt { width:100%; border-collapse:collapse; margin-bottom:10px; font-size:8px; }
table.dt th { background:#0a1628; color:#c9a84c; padding:5px 8px; text-align:left; font-size:7.5px; font-weight:bold; }
table.dt td { padding:4px 8px; border-bottom:1px solid #E8ECF1; vertical-align:top; }
table.dt tr:nth-child(even) td { background:#F8F9FA; }

/* ── Badges ── */
.badge { display:inline-block; padding:2px 7px; border-radius:2px; font-size:7px; font-weight:bold; letter-spacing:.5px; }
.bg-green  { background:#D1FAE5; color:#065F46; }
.bg-yellow { background:#FEF3C7; color:#92400E; }
.bg-red    { background:#FEE2E2; color:#991B1B; }
.bg-blue   { background:#DBEAFE; color:#1E40AF; }
.bg-navy   { background:#0a1628; color:#c9a84c; }

/* ── Callouts ── */
.callout { padding:8px 12px; border-radius:3px; margin-bottom:8px; font-size:8.5px; }
.callout-info  { background:#EFF6FF; border-left:3px solid #3B82F6; }
.callout-warn  { background:#FFFBEB; border-left:3px solid #F59E0B; }
.callout-alert { background:#FEF2F2; border-left:3px solid #EF4444; }
.callout-pos   { background:#ECFDF5; border-left:3px solid #10B981; }
.callout strong { color:#0a1628; }

/* ── Recommendation cards ── */
.rec-card { border-left:3px solid #c9a84c; padding:8px 12px; margin-bottom:9px; background:#FFFDF5; }
.rec-pri     { font-size:7.5px; font-weight:bold; color:#c9a84c; letter-spacing:1px; margin-bottom:2px; }
.rec-decisao { font-size:9.5px; font-weight:bold; color:#0a1628; margin-bottom:3px; }
.rec-why     { font-size:8px; color:#4A5568; margin-bottom:2px; line-height:1.5; }
.rec-impacto { font-size:7.5px; color:#8896A6; }

/* ── Strategic cross cards ── */
.cruzamento { background:#F0F4FF; border:1px solid #C5D5EF; border-radius:3px; padding:9px 12px; margin-bottom:9px; }
.cruzamento h4 { font-size:8.5px; color:#1E40AF; font-weight:bold; margin-bottom:4px; }
.cruzamento .analise { font-size:8.5px; margin-bottom:4px; }
.cruzamento .impl { font-size:8px; color:#2563EB; font-style:italic; }

/* ── Chart images ── */
.chart-img { max-width:100%; margin:6px 0; }

/* ── Footer ── */
.footer { text-align:center; font-size:7px; color:#aaa; margin-top:24px; padding-top:10px; border-top:1px solid #E8ECF1; }

/* ── Key-value pairs ── */
.kv { display:table; width:100%; margin-bottom:6px; }
.kv-k { display:table-cell; width:40%; font-size:8px; color:#8896A6; padding:2px 6px 2px 0; }
.kv-v { display:table-cell; font-size:8px; color:#1B334A; font-weight:bold; }
</style>
</head>
<body>

<!-- ══════════════════════════════════════════════════════
     PAGE 1 — DASHBOARD
     ══════════════════════════════════════════════════════ -->

<!-- HEADER: full-width navy bar -->
<table style="width:100%; background:#0a1628; padding:0; border-collapse:collapse;">
  <tr>
    <td style="padding:18px 24px; vertical-align:middle;">
      <div style="font-size:9px; color:#c9a84c; letter-spacing:2px; text-transform:uppercase; font-weight:bold; margin-bottom:2px;">Mayer Sociedade de Advogados · OAB/SC 2097</div>
      <div style="font-size:20px; font-weight:bold; color:#fff; letter-spacing:.3px; line-height:1.2;">Relatório de Inteligência Executiva</div>
      <div style="font-size:10px; color:#8896A6; margin-top:3px;"><?= htmlspecialchars($periodoLabel) ?> · Análise via Claude Opus 4.7</div>
    </td>
    <td style="padding:14px 24px 14px 0; text-align:right; vertical-align:middle; width:120px;">
      <?php if ($logo): ?>
      <img src="<?= $logo ?>" style="max-width:80px; max-height:60px;" alt="Mayer">
      <?php else: ?>
      <div style="font-size:11px; font-weight:bold; color:#c9a84c; letter-spacing:1px;">MAYER</div>
      <?php endif; ?>
    </td>
  </tr>
</table>

<!-- BODY: sidebar + main content -->
<table style="width:100%; border-collapse:collapse;">
<tr>

<!-- ── SIDEBAR ── -->
<td style="width:29%; background:#EFF3F8; vertical-align:top; padding:16px 14px; border-right:2px solid #D8DEE6;">

  <!-- Prepared by -->
  <div style="background:#fff; border-radius:4px; padding:10px; margin-bottom:14px; border:1px solid #D8DEE6;">
    <div style="font-size:6.5px; color:#c9a84c; text-transform:uppercase; letter-spacing:1px; font-weight:bold; margin-bottom:5px;">Prepared by</div>
    <div style="display:table; width:100%;">
      <div style="display:table-cell; vertical-align:middle; padding-right:8px;">
        <div style="width:32px; height:32px; background:#0a1628; border-radius:50%; text-align:center; line-height:32px;">
          <span style="color:#c9a84c; font-size:12px; font-weight:bold;">R</span>
        </div>
      </div>
      <div style="display:table-cell; vertical-align:middle;">
        <div style="font-size:9px; font-weight:bold; color:#0a1628;">Rafael Mayer</div>
        <div style="font-size:7.5px; color:#8896A6; margin-top:1px;">Sócio-Fundador &amp; CEO</div>
        <div style="font-size:7px; color:#8896A6; margin-top:1px;">Gerado: <?= now()->format('d/m/Y') ?></div>
      </div>
    </div>
  </div>

  <!-- Score geral -->
  <div style="text-align:center; margin-bottom:14px;">
    <div style="font-size:6.5px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; margin-bottom:5px;">Saúde Geral do Escritório</div>
    <div style="font-size:30px; font-weight:bold; color:<?= $scoreCor ?>; line-height:1;"><?= $scoreGeral ?><span style="font-size:14px; color:#8896A6;">/10</span></div>
    <div style="font-size:7.5px; color:#8896A6; margin-top:2px; font-style:italic; max-width:120px; margin-left:auto; margin-right:auto; line-height:1.4;"><?= htmlspecialchars(mb_substr($tituloPeriodo, 0, 60)) ?></div>
  </div>

  <hr style="border:none; border-top:1px solid #D8DEE6; margin:0 0 12px;">

  <!-- Key metrics stacked -->
  <div style="margin-bottom:10px;">
    <div style="font-size:6.5px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; display:block;">Receita Total</div>
    <div style="font-size:15px; font-weight:bold; color:#0a1628; display:block; margin-top:1px;">R$ <?= number_format($receita, 0, ',', '.') ?></div>
    <div style="font-size:7px; color:<?= $varPct >= 0 ? '#16a34a' : '#dc2626' ?>; margin-top:1px;"><?= $varPct >= 0 ? '▲' : '▼' ?> <?= abs($varPct) ?>% vs período anterior</div>
  </div>

  <div style="margin-bottom:10px;">
    <div style="font-size:6.5px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; display:block;">Resultado</div>
    <div style="font-size:15px; font-weight:bold; color:<?= $resultado >= 0 ? '#16a34a' : '#dc2626' ?>; display:block; margin-top:1px;">R$ <?= number_format($resultado, 0, ',', '.') ?></div>
  </div>

  <div style="margin-bottom:10px;">
    <div style="font-size:6.5px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; display:block;">Inadimplência</div>
    <div style="font-size:15px; font-weight:bold; color:#dc2626; display:block; margin-top:1px;">R$ <?= number_format($inadim['valor'] ?? 0, 0, ',', '.') ?></div>
    <div style="font-size:7px; color:#8896A6; margin-top:1px;"><?= $inadim['qtd'] ?? 0 ?> títulos</div>
  </div>

  <div style="margin-bottom:10px;">
    <div style="font-size:6.5px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; display:block;">Novos Leads</div>
    <div style="font-size:15px; font-weight:bold; color:#0a1628; display:block; margin-top:1px;"><?= $leads['total'] ?? 0 ?></div>
    <div style="font-size:7px; color:#16a34a; margin-top:1px;"><?= $leads['convertidos_para_cliente'] ?? 0 ?> convertidos</div>
  </div>

  <div style="margin-bottom:12px;">
    <div style="font-size:6.5px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; display:block;">Processos Ativos</div>
    <div style="font-size:15px; font-weight:bold; color:#0a1628; display:block; margin-top:1px;"><?= number_format($proc['total_ativos'] ?? 0) ?></div>
    <div style="font-size:7px; color:<?= ($proc['prazos_vencidos'] ?? 0) > 0 ? '#dc2626' : '#8896A6' ?>; margin-top:1px;"><?= $proc['prazos_vencidos'] ?? 0 ?> prazo(s) vencido(s)</div>
  </div>

  <div style="margin-bottom:10px;">
    <div style="font-size:6.5px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; display:block;">Conversas WhatsApp</div>
    <div style="font-size:15px; font-weight:bold; color:#0a1628; display:block; margin-top:1px;"><?= $wa['total_conversas_analisadas'] ?? 0 ?></div>
  </div>

  <hr style="border:none; border-top:1px solid #D8DEE6; margin:4px 0 10px;">

  <!-- Donut chart: processos por tipo -->
  <?php if (!empty($charts['processos_tipo'])): ?>
  <div style="font-size:6.5px; color:#385776; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; margin-bottom:4px; display:block;">Carteira por tipo de ação</div>
  <img src="<?= $charts['processos_tipo'] ?>" style="max-width:100%; margin:0;" alt="Processos tipo">
  <?php elseif (!empty($charts['leads_intencao'])): ?>
  <div style="font-size:6.5px; color:#385776; text-transform:uppercase; letter-spacing:.8px; font-weight:bold; margin-bottom:4px; display:block;">Intenção de contratar</div>
  <img src="<?= $charts['leads_intencao'] ?>" style="max-width:100%; margin:0;" alt="Leads intenção">
  <?php endif; ?>

  <div style="margin-top:12px; font-size:6.5px; color:#8896A6; text-align:center; border-top:1px solid #D8DEE6; padding-top:8px;">
    CONFIDENCIAL · Uso exclusivo da diretoria
  </div>
</td>

<!-- ── MAIN CONTENT ── -->
<td style="width:71%; vertical-align:top; padding:16px 20px;">

  <!-- KPI Cards: 3 side by side -->
  <table style="width:100%; border-collapse:separate; border-spacing:6px; margin-bottom:14px;">
    <tr>
      <td style="background:#fff; border:1px solid #D8DEE6; border-radius:4px; padding:10px 12px; border-top:3px solid #0a1628; vertical-align:top;">
        <div style="font-size:7px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold;">Receita Total</div>
        <div style="font-size:16px; font-weight:bold; color:#0a1628; margin-top:3px; line-height:1.1;">R$ <?= number_format($receita / 1000, 1, ',', '.') ?>k</div>
        <div style="font-size:8px; margin-top:3px; color:<?= $varPct >= 0 ? '#16a34a' : '#dc2626' ?>;"><?= $varPct >= 0 ? '↑' : '↓' ?> <?= abs($varPct) ?>% vs ant.</div>
      </td>
      <td style="background:#fff; border:1px solid #D8DEE6; border-radius:4px; padding:10px 12px; border-top:3px solid #c9a84c; vertical-align:top;">
        <div style="font-size:7px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold;">Resultado</div>
        <div style="font-size:16px; font-weight:bold; color:<?= $resultado >= 0 ? '#16a34a' : '#dc2626' ?>; margin-top:3px; line-height:1.1;">R$ <?= number_format($resultado / 1000, 1, ',', '.') ?>k</div>
        <div style="font-size:8px; margin-top:3px; color:#8896A6;">Margem: <?= $receita > 0 ? round(($resultado / $receita) * 100) : 0 ?>%</div>
      </td>
      <td style="background:#fff; border:1px solid #D8DEE6; border-radius:4px; padding:10px 12px; border-top:3px solid #385776; vertical-align:top;">
        <div style="font-size:7px; color:#8896A6; text-transform:uppercase; letter-spacing:.8px; font-weight:bold;">Score GDP Médio</div>
        <?php $gdpMedia = !empty($gdp['snapshots']) ? round(array_sum(array_column($gdp['snapshots'], 'score_total')) / count($gdp['snapshots']), 1) : 0; ?>
        <div style="font-size:16px; font-weight:bold; color:#0a1628; margin-top:3px; line-height:1.1;"><?= $gdpMedia ?><span style="font-size:10px; color:#8896A6;">/100</span></div>
        <div style="font-size:8px; margin-top:3px; color:#8896A6;"><?= count($gdp['snapshots'] ?? []) ?> advogados avaliados</div>
      </td>
    </tr>
  </table>

  <!-- Bar chart: receita histórico -->
  <?php if (!empty($charts['receita_historico'])): ?>
  <div style="margin-bottom:10px;">
    <div style="background:#0a1628; padding:4px 10px; margin-bottom:6px;">
      <span style="font-size:7.5px; letter-spacing:1.5px; text-transform:uppercase; color:#c9a84c; font-weight:bold;">Performance Financeira · Últimos 6 Meses</span>
    </div>
    <img src="<?= $charts['receita_historico'] ?>" style="max-width:100%;" alt="Histórico receita">
  </div>
  <?php endif; ?>

  <!-- Table: Top 5 recomendações -->
  <?php if (!empty($recomendacoes)): ?>
  <div style="margin-top:10px;">
    <div style="background:#0a1628; padding:4px 10px; margin-bottom:6px; display:table; width:100%;">
      <span style="font-size:7.5px; letter-spacing:1.5px; text-transform:uppercase; color:#c9a84c; font-weight:bold; display:table-cell;">Ações Prioritárias</span>
      <span style="font-size:7px; color:#5a7080; display:table-cell; text-align:right; vertical-align:middle;">Decisões acionáveis</span>
    </div>
    <table style="width:100%; border-collapse:collapse; font-size:8px;">
      <thead>
        <tr style="background:#0a1628;">
          <th style="color:#c9a84c; padding:5px 8px; text-align:left; font-size:7px; font-weight:bold; width:30px;">#</th>
          <th style="color:#c9a84c; padding:5px 8px; text-align:left; font-size:7px; font-weight:bold;">Decisão</th>
          <th style="color:#c9a84c; padding:5px 8px; text-align:left; font-size:7px; font-weight:bold; width:55px;">Área</th>
          <th style="color:#c9a84c; padding:5px 8px; text-align:left; font-size:7px; font-weight:bold; width:60px;">Prazo</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach (array_slice($recomendacoes, 0, 6) as $i => $rec):
          $prazo = $rec['prazo'] ?? '';
          $prazoBg = $prazo === 'imediato' ? '#FEE2E2' : ($prazo === '7 dias' ? '#FEF3C7' : '#DBEAFE');
          $prazoCl = $prazo === 'imediato' ? '#991B1B' : ($prazo === '7 dias' ? '#92400E' : '#1E40AF');
        ?>
        <tr style="background:<?= $i % 2 === 0 ? '#fff' : '#F8F9FA' ?>;">
          <td style="padding:4px 8px; border-bottom:1px solid #E8ECF1; color:#c9a84c; font-weight:bold;"><?= $rec['prioridade'] ?? ($i+1) ?></td>
          <td style="padding:4px 8px; border-bottom:1px solid #E8ECF1; color:#1B334A;"><?= htmlspecialchars(mb_substr($rec['decisao'] ?? '', 0, 70)) ?></td>
          <td style="padding:4px 8px; border-bottom:1px solid #E8ECF1;">
            <span style="display:inline-block; padding:1px 5px; border-radius:2px; font-size:6.5px; font-weight:bold; background:#0a1628; color:#c9a84c;"><?= htmlspecialchars(strtoupper($rec['area'] ?? '')) ?></span>
          </td>
          <td style="padding:4px 8px; border-bottom:1px solid #E8ECF1;">
            <span style="display:inline-block; padding:1px 5px; border-radius:2px; font-size:6.5px; font-weight:bold; background:<?= $prazoBg ?>; color:<?= $prazoCl ?>;"><?= htmlspecialchars($prazo) ?></span>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</td>
</tr>
</table>

<!-- ══════════════════════════════════════════════════════
     PAGE 2 — RESUMO EXECUTIVO
     ══════════════════════════════════════════════════════ -->
<div class="section page-break">
  <div class="sh"><h2>Resumo Executivo</h2><div class="sh-sub">Visão consolidada · <?= htmlspecialchars($periodoLabel) ?></div></div>
  <div class="analise">
    <?php foreach (explode("\n", $resumoExec) as $p): if(trim($p)): ?>
    <p><?= htmlspecialchars(trim($p)) ?></p>
    <?php endif; endforeach; ?>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     VOZ DOS CLIENTES
     ══════════════════════════════════════════════════════ -->
<?php if (!empty($vozClientes['analise'])): ?>
<div class="section">
  <div class="sh"><h2>Voz dos Clientes</h2><div class="sh-sub">Análise semântica de <?= $wa['total_conversas_analisadas'] ?? 0 ?> conversas</div></div>

  <div class="analise">
    <?php foreach (explode("\n", $vozClientes['analise']) as $p): if(trim($p)): ?>
    <p><?= htmlspecialchars(trim($p)) ?></p>
    <?php endif; endforeach; ?>
  </div>

  <?php if (!empty($vozClientes['temas_criticos'])): ?>
  <strong style="font-size:8.5px; color:#0a1628;">Temas críticos:</strong>
  <ul class="ul" style="margin-top:4px;">
    <?php foreach ($vozClientes['temas_criticos'] as $t): ?>
    <li><?= htmlspecialchars($t) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if (!empty($vozClientes['alertas'])): ?>
  <?php foreach ($vozClientes['alertas'] as $alerta): ?>
  <div class="callout callout-alert"><strong>Alerta:</strong> <?= htmlspecialchars($alerta) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($vozClientes['oportunidade_identificada'])): ?>
  <div class="callout callout-pos"><strong>Oportunidade:</strong> <?= htmlspecialchars($vozClientes['oportunidade_identificada']) ?></div>
  <?php endif; ?>

  <?php if (!empty($wa['temas_recorrentes'])): ?>
  <div style="margin-top:8px;">
    <strong style="font-size:8px; color:#8896A6;">Palavras mais frequentes:</strong>
    <div style="margin-top:4px; font-size:7.5px; color:#4A5568; line-height:2;">
      <?php foreach (array_slice($wa['temas_recorrentes'], 0, 20, true) as $palavra => $freq): ?>
      <span style="background:#EDF2F7; padding:2px 6px; border-radius:2px; margin:2px; display:inline-block;">
        <?= htmlspecialchars($palavra) ?> <span style="color:#c9a84c;">(<?= $freq ?>)</span>
      </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     INTELIGÊNCIA DE MERCADO / LEADS
     ══════════════════════════════════════════════════════ -->
<?php if (!empty($intMercado['perfil_leads_periodo']) && ($leads['total'] ?? 0) > 0): ?>
<div class="section page-break">
  <div class="sh"><h2>Inteligência de Mercado</h2><div class="sh-sub"><?= $leads['total'] ?? 0 ?> leads · Captação · Conversão</div></div>

  <div class="metrics">
    <div class="mbox"><div class="mval"><?= $leads['total'] ?? 0 ?></div><div class="mlbl">Total Leads</div></div>
    <div class="mbox"><div class="mval" style="color:#16a34a;"><?= $leads['por_intencao_contratar']['sim'] ?? 0 ?></div><div class="mlbl">Intenção Alta</div></div>
    <div class="mbox"><div class="mval"><?= $leads['por_intencao_contratar']['talvez'] ?? 0 ?></div><div class="mlbl">Intenção Média</div></div>
    <div class="mbox"><div class="mval"><?= $leads['taxa_conversao_estimada'] ?? 0 ?>%</div><div class="mlbl">Taxa Conv.</div></div>
    <div class="mbox"><div class="mval"><?= $leads['convertidos_para_cliente'] ?? 0 ?></div><div class="mlbl">Convertidos</div></div>
  </div>

  <div class="two">
    <div class="cl">
      <div class="analise">
        <?php foreach (explode("\n", $intMercado['perfil_leads_periodo']) as $p): if(trim($p)): ?>
        <p><?= htmlspecialchars(trim($p)) ?></p>
        <?php endif; endforeach; ?>
      </div>
      <?php if (!empty($intMercado['qualidade_captacao'])): ?>
      <div class="callout callout-info"><strong>Qualidade da captação:</strong> <?= htmlspecialchars($intMercado['qualidade_captacao']) ?></div>
      <?php endif; ?>
      <?php if (!empty($intMercado['campanhas_eficazes'])): ?>
      <div class="callout callout-info"><strong>Campanhas eficazes:</strong> <?= htmlspecialchars($intMercado['campanhas_eficazes']) ?></div>
      <?php endif; ?>
      <?php if (!empty($leads['por_area'])): ?>
      <strong style="font-size:8px;">Leads por área jurídica:</strong>
      <table class="dt" style="margin-top:4px;">
        <tr><th>Área</th><th>Leads</th></tr>
        <?php foreach (array_slice($leads['por_area'], 0, 8, true) as $area => $tot): ?>
        <tr><td><?= htmlspecialchars($area) ?></td><td><?= $tot ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <div class="cr">
      <?php if (!empty($charts['leads_area'])): ?>
      <strong style="font-size:8px;">Distribuição por área:</strong>
      <img src="<?= $charts['leads_area'] ?>" class="chart-img" alt="Leads área">
      <?php endif; ?>
      <?php if (!empty($charts['leads_intencao'])): ?>
      <strong style="font-size:8px; display:block; margin-top:6px;">Intenção de contratar:</strong>
      <img src="<?= $charts['leads_intencao'] ?>" class="chart-img" alt="Leads intenção">
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($leads['por_gatilho_emocional'])): ?>
  <div style="margin-top:8px;">
    <strong style="font-size:8px;">Gatilhos emocionais:</strong>
    <div style="margin-top:4px;">
      <?php arsort($leads['por_gatilho_emocional']); foreach (array_slice($leads['por_gatilho_emocional'], 0, 8, true) as $g => $n): ?>
      <span class="badge bg-yellow" style="margin:2px;"><?= htmlspecialchars($g) ?> (<?= $n ?>)</span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($intMercado['alertas'])): ?>
  <?php foreach ($intMercado['alertas'] as $al): ?>
  <div class="callout callout-warn"><strong>Atenção:</strong> <?= htmlspecialchars($al) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     FINANCEIRO
     ══════════════════════════════════════════════════════ -->
<div class="section page-break">
  <div class="sh"><h2>Análise Financeira</h2><div class="sh-sub">DRE · Inadimplência · Histórico 6 meses</div></div>

  <div class="metrics">
    <div class="mbox">
      <div class="mval">R$ <?= number_format($receita, 0, ',', '.') ?></div>
      <div class="mlbl">Receita Total</div>
      <div class="mvar <?= $varPct >= 0 ? 'pos' : 'neg' ?>"><?= $varPct >= 0 ? '▲' : '▼' ?> <?= abs($varPct) ?>%</div>
    </div>
    <div class="mbox">
      <div class="mval">R$ <?= number_format($despesas, 0, ',', '.') ?></div>
      <div class="mlbl">Despesas</div>
    </div>
    <div class="mbox">
      <div class="mval" style="color:<?= $resultado >= 0 ? '#16a34a' : '#dc2626' ?>">R$ <?= number_format($resultado, 0, ',', '.') ?></div>
      <div class="mlbl">Resultado</div>
    </div>
    <div class="mbox">
      <div class="mval" style="color:#dc2626;">R$ <?= number_format($inadim['valor'] ?? 0, 0, ',', '.') ?></div>
      <div class="mlbl">Inadimplência</div>
      <div class="mvar"><?= $inadim['qtd'] ?? 0 ?> títulos</div>
    </div>
  </div>

  <?php if (!empty($charts['receita_historico'])): ?>
  <img src="<?= $charts['receita_historico'] ?>" class="chart-img" alt="Histórico receita">
  <?php endif; ?>

  <div class="analise">
    <?php foreach (explode("\n", $aFin['analise'] ?? '') as $p): if(trim($p)): ?>
    <p><?= htmlspecialchars(trim($p)) ?></p>
    <?php endif; endforeach; ?>
  </div>

  <?php if (!empty($aFin['riscos_identificados'])): ?>
  <?php foreach ($aFin['riscos_identificados'] as $r): ?>
  <div class="callout callout-alert"><?= htmlspecialchars($r) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($aFin['recomendacoes'])): ?>
  <ul class="ul">
    <?php foreach ($aFin['recomendacoes'] as $r): ?>
    <li><?= htmlspecialchars($r) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════
     PERFORMANCE DA EQUIPE
     ══════════════════════════════════════════════════════ -->
<div class="section page-break">
  <div class="sh"><h2>Performance da Equipe</h2><div class="sh-sub">GDP · Scores · Rankings · QA de Atendimento</div></div>

  <div class="two">
    <div class="cl">
      <?php if (!empty($gdp['snapshots'])): ?>
      <table class="dt">
        <tr><th>#</th><th>Advogado</th><th>Score</th><th>Jurídico</th><th>Financeiro</th><th>Atend.</th><th>Var.</th></tr>
        <?php foreach ($gdp['snapshots'] as $s): ?>
        <tr>
          <td><?= $s['ranking'] ?? '-' ?></td>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><strong><?= number_format($s['score_total'] ?? 0, 1) ?></strong></td>
          <td><?= number_format($s['score_juridico'] ?? 0, 1) ?></td>
          <td><?= number_format($s['score_financeiro'] ?? 0, 1) ?></td>
          <td><?= number_format($s['score_atendimento'] ?? 0, 1) ?></td>
          <td class="<?= ($s['variacao_score'] ?? 0) >= 0 ? 'pos' : 'neg' ?>">
            <?= ($s['variacao_score'] ?? 0) >= 0 ? '▲' : '▼' ?> <?= abs(round($s['variacao_score'] ?? 0, 1)) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <div class="cr">
      <?php if (!empty($charts['performance_adv'])): ?>
      <img src="<?= $charts['performance_adv'] ?>" class="chart-img" alt="Performance advogados">
      <?php endif; ?>
    </div>
  </div>

  <div class="analise">
    <?php foreach (explode("\n", $aEquipe['analise_geral'] ?? '') as $p): if(trim($p)): ?>
    <p><?= htmlspecialchars(trim($p)) ?></p>
    <?php endif; endforeach; ?>
  </div>

  <?php if (!empty($aEquipe['destaques'])): ?>
  <?php foreach ($aEquipe['destaques'] as $d): ?>
  <div class="callout <?= ($d['tipo'] ?? '') === 'destaque_positivo' ? 'callout-pos' : 'callout-warn' ?>">
    <strong><?= htmlspecialchars($d['nome'] ?? '') ?>:</strong> <?= htmlspecialchars($d['analise'] ?? '') ?>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($aEquipe['cruzamento_gdp_nexo'])): ?>
  <div class="callout callout-info" style="margin-top:8px;">
    <strong>GDP × NEXO:</strong> <?= htmlspecialchars($aEquipe['cruzamento_gdp_nexo']) ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($nexo['qa_scores'])): ?>
  <strong style="font-size:8px; display:block; margin-top:10px;">QA — Satisfação dos clientes:</strong>
  <table class="dt" style="margin-top:4px;">
    <tr><th>Advogado</th><th>Score Médio</th><th>NPS</th><th>Respostas</th></tr>
    <?php foreach ($nexo['qa_scores'] as $qa): ?>
    <tr>
      <td><?= htmlspecialchars($qa['name']) ?></td>
      <td><?= number_format($qa['score_medio'] ?? 0, 2) ?>/5</td>
      <td><?= number_format($qa['nps_medio'] ?? 0, 1) ?></td>
      <td><?= $qa['respostas'] ?? 0 ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════
     CARTEIRA DE PROCESSOS
     ══════════════════════════════════════════════════════ -->
<div class="section page-break">
  <div class="sh"><h2>Carteira de Processos</h2><div class="sh-sub">Portfolio ativo · Prazos · Andamentos</div></div>

  <div class="metrics">
    <div class="mbox"><div class="mval"><?= number_format($proc['total_ativos'] ?? 0) ?></div><div class="mlbl">Processos Ativos</div></div>
    <div class="mbox"><div class="mval">R$ <?= number_format(($proc['valor_carteira'] ?? 0) / 1000000, 1, ',', '.') ?>M</div><div class="mlbl">Valor Carteira</div></div>
    <div class="mbox">
      <div class="mval" style="color:<?= ($proc['prazos_vencidos'] ?? 0) > 0 ? '#dc2626' : '#16a34a' ?>"><?= $proc['prazos_vencidos'] ?? 0 ?></div>
      <div class="mlbl">Prazos Vencidos</div>
    </div>
    <div class="mbox"><div class="mval"><?= number_format($proc['novos_no_periodo'] ?? 0) ?></div><div class="mlbl">Novos no Período</div></div>
    <div class="mbox"><div class="mval"><?= number_format($proc['andamentos_periodo'] ?? 0) ?></div><div class="mlbl">Andamentos</div></div>
  </div>

  <div class="two">
    <div class="cl">
      <div class="analise">
        <?php foreach (explode("\n", $aProc['analise'] ?? '') as $p): if(trim($p)): ?>
        <p><?= htmlspecialchars(trim($p)) ?></p>
        <?php endif; endforeach; ?>
      </div>
      <?php if (!empty($aProc['riscos_prazos'])): ?>
      <div class="callout callout-alert"><strong>Exposição jurídica:</strong> <?= htmlspecialchars($aProc['riscos_prazos']) ?></div>
      <?php endif; ?>
      <?php if (!empty($proc['prazos_proximos'])): ?>
      <strong style="font-size:8px; display:block; margin-top:8px;">Prazos fatais próximos (30 dias):</strong>
      <table class="dt" style="margin-top:4px;">
        <tr><th>Processo</th><th>Prazo Fatal</th><th>Responsável</th></tr>
        <?php foreach (array_slice($proc['prazos_proximos'], 0, 8) as $p): ?>
        <tr>
          <td><?= htmlspecialchars($p['processo_pasta']) ?></td>
          <td><?= date('d/m/Y', strtotime($p['data_prazo_fatal'])) ?></td>
          <td><?= htmlspecialchars($p['responsavel'] ?? '-') ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <div class="cr">
      <?php if (!empty($charts['processos_tipo'])): ?>
      <strong style="font-size:8px;">Por tipo de ação:</strong>
      <img src="<?= $charts['processos_tipo'] ?>" class="chart-img" alt="Processos tipo">
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════
     CRUZAMENTOS ESTRATÉGICOS
     ══════════════════════════════════════════════════════ -->
<?php if (!empty($cruzamentos)): ?>
<div class="section page-break">
  <div class="sh"><h2>Cruzamentos Estratégicos</h2><div class="sh-sub">Insights gerados por correlação de múltiplas fontes</div></div>
  <?php foreach ($cruzamentos as $c): ?>
  <div class="cruzamento">
    <h4><?= htmlspecialchars($c['titulo'] ?? '') ?></h4>
    <div class="analise"><?= htmlspecialchars($c['analise'] ?? '') ?></div>
    <?php if (!empty($c['implicacao'])): ?>
    <div class="impl">→ <?= htmlspecialchars($c['implicacao']) ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════
     RECOMENDAÇÕES PRIORIZADAS
     ══════════════════════════════════════════════════════ -->
<div class="section page-break">
  <div class="sh"><h2>Recomendações Priorizadas</h2><div class="sh-sub">Decisões acionáveis · Ordenadas por impacto e urgência</div></div>

  <?php foreach ($recomendacoes as $rec):
    $prazo = $rec['prazo'] ?? '';
    $pBg   = $prazo === 'imediato' ? 'bg-red' : ($prazo === '7 dias' ? 'bg-yellow' : 'bg-blue');
  ?>
  <div class="rec-card">
    <div class="rec-pri">
      PRIORIDADE <?= $rec['prioridade'] ?? '?' ?>
      <span class="badge <?= $pBg ?>" style="margin-left:7px;"><?= htmlspecialchars(strtoupper($prazo)) ?></span>
      <span class="badge bg-navy" style="margin-left:4px;"><?= htmlspecialchars(strtoupper($rec['area'] ?? '')) ?></span>
    </div>
    <div class="rec-decisao"><?= htmlspecialchars($rec['decisao'] ?? '') ?></div>
    <div class="rec-why"><?= htmlspecialchars($rec['por_que_agora'] ?? '') ?></div>
    <div class="rec-impacto">Impacto esperado: <?= htmlspecialchars($rec['impacto_esperado'] ?? '') ?></div>
  </div>
  <?php endforeach; ?>

  <?php if (!empty($monitorar)): ?>
  <div style="margin-top:16px; background:#F2F5F8; border-radius:3px; padding:10px 14px; border-left:3px solid #385776;">
    <strong style="font-size:8.5px; color:#0a1628;">O que monitorar no próximo período:</strong>
    <ul class="ul" style="margin-top:5px;">
      <?php foreach ($monitorar as $m): ?><li><?= htmlspecialchars($m) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <!-- FOOTER -->
  <div style="text-align:center; font-size:7px; color:#aaa; margin-top:24px; padding-top:10px; border-top:1px solid #E8ECF1;">
    Mayer Sociedade de Advogados · OAB/SC 2097 · Relatório de Inteligência Executiva · <?= htmlspecialchars($periodoLabel) ?><br>
    Gerado em <?= $geradoEm ?> · Claude Opus 4.7 com Extended Thinking · CONFIDENCIAL — uso exclusivo da diretoria
  </div>
</div>

</body>
</html>
<?php
        return ob_get_clean();
    }
}
