<?php

namespace App\Services\RelatorioCeo;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PdfGeneratorService
{
    public function gerar(array $dados, array $analise, string $periodoLabel): string
    {
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

        // Receita 6 meses
        $historico = $dados['financeiro']['historico_6meses'] ?? [];
        if (!empty($historico)) {
            $charts['receita_historico'] = $this->fetchChart([
                'type' => 'bar',
                'data' => [
                    'labels'   => array_column($historico, 'mes'),
                    'datasets' => [
                        ['label' => 'Receita', 'data' => array_column($historico, 'receita_total'), 'backgroundColor' => '#1e3a5f'],
                        ['label' => 'Despesas', 'data' => array_map(fn($v) => abs($v), array_column($historico, 'despesas')), 'backgroundColor' => '#c0392b'],
                    ],
                ],
                'options' => ['plugins' => ['legend' => ['labels' => ['font' => ['size' => 10]]]], 'scales' => ['y' => ['ticks' => ['font' => ['size' => 9]]]]],
            ]);
        }

        // Performance advogados
        $snapshots = $dados['gdp']['snapshots'] ?? [];
        if (!empty($snapshots)) {
            $charts['performance_adv'] = $this->fetchChart([
                'type' => 'horizontalBar',
                'data' => [
                    'labels'   => array_column($snapshots, 'name'),
                    'datasets' => [['label' => 'Score', 'data' => array_column($snapshots, 'score_total'), 'backgroundColor' => '#1e3a5f']],
                ],
                'options' => ['scales' => ['x' => ['max' => 100, 'ticks' => ['font' => ['size' => 9]]]], 'plugins' => ['legend' => ['display' => false]]],
            ]);
        }

        // Leads por área
        $leadsArea = $dados['leads']['por_area'] ?? [];
        if (!empty($leadsArea)) {
            $areas  = array_keys($leadsArea);
            $totais = array_values($leadsArea);
            $charts['leads_area'] = $this->fetchChart([
                'type' => 'pie',
                'data' => [
                    'labels'   => array_slice($areas, 0, 6),
                    'datasets' => [['data' => array_slice($totais, 0, 6), 'backgroundColor' => ['#1e3a5f','#2e6da4','#3498db','#5dade2','#85c1e9','#aed6f1']]],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 9]]]]],
            ]);
        }

        // Processos por tipo
        $porTipo = $dados['processos']['por_tipo_acao'] ?? [];
        if (!empty($porTipo)) {
            $charts['processos_tipo'] = $this->fetchChart([
                'type' => 'doughnut',
                'data' => [
                    'labels'   => array_slice(array_column($porTipo, 'tipo_acao'), 0, 6),
                    'datasets' => [['data' => array_slice(array_column($porTipo, 'total'), 0, 6), 'backgroundColor' => ['#1e3a5f','#2e6da4','#3498db','#5dade2','#85c1e9','#aed6f1']]],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 8]]]]],
            ]);
        }

        // Leads por intenção de contratar
        $porIntencao = $dados['leads']['por_intencao_contratar'] ?? [];
        if (!empty($porIntencao)) {
            $charts['leads_intencao'] = $this->fetchChart([
                'type' => 'doughnut',
                'data' => [
                    'labels'   => array_keys($porIntencao),
                    'datasets' => [['data' => array_values($porIntencao), 'backgroundColor' => ['#27ae60','#f39c12','#c0392b','#95a5a6']]],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 9]]]]],
            ]);
        }

        return $charts;
    }

    private function fetchChart(array $config): string
    {
        try {
            $json = urlencode(json_encode($config, JSON_UNESCAPED_UNICODE));
            $url  = "https://quickchart.io/chart?w=520&h=200&bkg=white&c={$json}";
            $resp = Http::timeout(15)->get($url);
            if ($resp->successful()) {
                return 'data:image/png;base64,' . base64_encode($resp->body());
            }
        } catch (\Exception $e) {
            Log::warning('RelatorioCeo PdfGenerator: QuickChart falhou', ['error' => $e->getMessage()]);
        }
        return '';
    }

    private function renderHtml(array $dados, array $analise, string $periodoLabel, array $charts): string
    {
        $fin     = $dados['financeiro'] ?? [];
        $nexo    = $dados['nexo'] ?? [];
        $gdp     = $dados['gdp'] ?? [];
        $proc    = $dados['processos'] ?? [];
        $merc    = $dados['mercado'] ?? [];
        $ga      = $dados['ga'] ?? [];
        $leads   = $dados['leads'] ?? [];
        $wa      = $dados['whatsapp'] ?? [];
        $geradoEm = now()->format('d/m/Y H:i');

        // Campos do novo schema de análise
        $scoreGeral     = $analise['score_geral'] ?? 0;
        $tituloPeriodo  = $analise['titulo_periodo'] ?? '';
        $resumoExec     = $analise['resumo_executivo'] ?? '';
        $vozClientes    = $analise['voz_dos_clientes'] ?? [];
        $intMercado     = $analise['inteligencia_de_mercado'] ?? [];
        $aFin           = $analise['financeiro'] ?? [];
        $aEquipe        = $analise['performance_equipe'] ?? [];
        $aProc          = $analise['carteira_processos'] ?? [];
        $cruzamentos    = $analise['cruzamentos_estrategicos'] ?? [];
        $recomendacoes  = $analise['recomendacoes_priorizadas'] ?? [];
        $monitorar      = $analise['o_que_monitorar_proximo_periodo'] ?? [];

        $scoreCor = $scoreGeral >= 7 ? '#27ae60' : ($scoreGeral >= 5 ? '#f39c12' : '#c0392b');

        ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1c2b3a; background: #fff; line-height: 1.55; }
.page-break { page-break-before: always; }

.cover { background: #0a1628; color: #fff; padding: 55px 48px 45px; }
.cover-firm { font-size: 11px; font-weight: bold; color: #c9a84c; letter-spacing: 3px; text-transform: uppercase; margin-bottom: 3px; }
.cover-oab  { font-size: 8.5px; color: #5a7080; margin-bottom: 48px; letter-spacing: 1px; }
.cover-type { font-size: 10px; color: #8899aa; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px; }
.cover-title { font-size: 24px; font-weight: bold; color: #fff; line-height: 1.3; margin-bottom: 6px; max-width: 420px; }
.cover-periodo { font-size: 11px; color: #c9a84c; margin-bottom: 28px; }
.cover-score { display: inline-block; background: <?= $scoreCor ?>; color: #fff; padding: 7px 18px; border-radius: 3px; font-size: 12px; font-weight: bold; letter-spacing: 1px; }
.cover-confidencial { font-size: 8px; color: #3a4f60; margin-top: 22px; border-top: 1px solid #1e3448; padding-top: 12px; }

.section { padding: 22px 40px; }
.section + .section { border-top: 1px solid #e8edf2; }

.sh { background: #0a1628; padding: 7px 40px; margin: 0 -40px 16px; display: table; width: calc(100% + 80px); }
.sh h2 { font-size: 10px; letter-spacing: 1.5px; text-transform: uppercase; color: #c9a84c; display: table-cell; }
.sh .sh-sub { font-size: 8px; color: #5a7080; display: table-cell; text-align: right; vertical-align: middle; }

.analise { font-size: 9.5px; line-height: 1.65; color: #2c3e50; margin-bottom: 12px; text-align: justify; }
.analise p { margin-bottom: 8px; }

.metrics { display: table; width: 100%; border-collapse: separate; border-spacing: 6px; margin-bottom: 14px; }
.mbox { display: table-cell; text-align: center; padding: 10px 6px; background: #f4f6f9; border: 1px solid #e0e5ec; border-radius: 3px; }
.mval { font-size: 17px; font-weight: bold; color: #0a1628; }
.mlbl { font-size: 7.5px; color: #7f8c8d; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px; }
.mvar { font-size: 8.5px; margin-top: 2px; }
.pos { color: #27ae60; } .neg { color: #c0392b; }

.two { display: table; width: 100%; }
.cl { display: table-cell; width: 58%; padding-right: 14px; vertical-align: top; }
.cr { display: table-cell; width: 42%; vertical-align: top; }

.ul { padding-left: 14px; margin: 6px 0 10px; }
.ul li { font-size: 9px; margin-bottom: 4px; line-height: 1.5; }
.ul li::marker { color: #c9a84c; }

table.dt { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 8.5px; }
table.dt th { background: #0a1628; color: #c9a84c; padding: 5px 8px; text-align: left; font-size: 8px; font-weight: bold; }
table.dt td { padding: 4px 8px; border-bottom: 1px solid #eee; vertical-align: top; }
table.dt tr:nth-child(even) td { background: #f8f9fa; }

.badge { display: inline-block; padding: 2px 7px; border-radius: 2px; font-size: 7.5px; font-weight: bold; letter-spacing: 0.5px; }
.bg-green  { background: #d5f5e3; color: #1a7a3e; }
.bg-yellow { background: #fef9e7; color: #7d6608; }
.bg-red    { background: #fadbd8; color: #922b21; }
.bg-blue   { background: #d6eaf8; color: #1a5276; }
.bg-navy   { background: #0a1628; color: #c9a84c; }

.callout { padding: 10px 14px; border-radius: 3px; margin-bottom: 10px; font-size: 9px; }
.callout-info  { background: #eaf3fb; border-left: 3px solid #2980b9; }
.callout-warn  { background: #fef9e7; border-left: 3px solid #f39c12; }
.callout-alert { background: #fdedec; border-left: 3px solid #c0392b; }
.callout-pos   { background: #eafaf1; border-left: 3px solid #27ae60; }
.callout strong { color: #0a1628; }

.rec-card { border-left: 3px solid #c9a84c; padding: 9px 13px; margin-bottom: 10px; background: #fffdf5; }
.rec-pri { font-size: 8px; font-weight: bold; color: #c9a84c; letter-spacing: 1px; margin-bottom: 3px; }
.rec-decisao { font-size: 10px; font-weight: bold; color: #0a1628; margin-bottom: 4px; }
.rec-why { font-size: 8.5px; color: #4a5568; margin-bottom: 3px; line-height: 1.5; }
.rec-impacto { font-size: 8px; color: #718096; }

.cruzamento { background: #f0f4ff; border: 1px solid #c5d5ef; border-radius: 3px; padding: 10px 13px; margin-bottom: 10px; }
.cruzamento h4 { font-size: 9px; color: #1a5276; font-weight: bold; margin-bottom: 5px; }
.cruzamento .analise { font-size: 9px; margin-bottom: 5px; }
.cruzamento .impl { font-size: 8.5px; color: #2e86c1; font-style: italic; }

.chart-img { max-width: 100%; margin: 8px 0; }

.footer { text-align: center; font-size: 7.5px; color: #aaa; margin-top: 28px; padding-top: 12px; border-top: 1px solid #e8edf2; }

.kv { display: table; width: 100%; margin-bottom: 8px; }
.kv-row { display: table-row; }
.kv-k { display: table-cell; width: 40%; font-size: 8.5px; color: #718096; padding: 2px 8px 2px 0; }
.kv-v { display: table-cell; font-size: 8.5px; color: #1c2b3a; font-weight: bold; }
</style>
</head>
<body>

<!-- ══ CAPA ══ -->
<div class="cover">
  <div class="cover-firm">Mayer Advogados</div>
  <div class="cover-oab">Sociedade de Advogados · OAB/SC 2097 · Itajaí, SC</div>
  <div class="cover-type">Relatório de Inteligência Executiva</div>
  <div class="cover-title"><?= htmlspecialchars($tituloPeriodo ?: 'Análise Executiva do Período') ?></div>
  <div class="cover-periodo"><?= htmlspecialchars($periodoLabel) ?></div>
  <div>
    <span class="cover-score">Saúde Geral: <?= $scoreGeral ?>/10</span>
  </div>
  <div class="cover-confidencial">
    Gerado em <?= $geradoEm ?> · Análise via Claude Opus 4.7 com Extended Thinking · CONFIDENCIAL — uso exclusivo da diretoria
  </div>
</div>

<!-- ══ RESUMO EXECUTIVO ══ -->
<div class="section">
  <div class="sh"><h2>Resumo Executivo</h2><div class="sh-sub">Visão consolidada · <?= htmlspecialchars($periodoLabel) ?></div></div>
  <div class="analise">
    <?php foreach (explode("\n", $resumoExec) as $p): if(trim($p)): ?>
    <p><?= htmlspecialchars(trim($p)) ?></p>
    <?php endif; endforeach; ?>
  </div>
</div>

<!-- ══ VOZ DOS CLIENTES ══ -->
<?php if (!empty($vozClientes['analise'])): ?>
<div class="section page-break">
  <div class="sh"><h2>Voz dos Clientes</h2><div class="sh-sub">Análise semântica de <?= $wa['total_conversas_analisadas'] ?? 0 ?> conversas · <?= array_sum($wa['volume_diario_msgs'] ?? []) ?> mensagens</div></div>

  <div class="analise">
    <?php foreach (explode("\n", $vozClientes['analise']) as $p): if(trim($p)): ?>
    <p><?= htmlspecialchars(trim($p)) ?></p>
    <?php endif; endforeach; ?>
  </div>

  <?php if (!empty($vozClientes['temas_criticos'])): ?>
  <div style="margin-bottom:12px;">
    <strong style="font-size:9px; color:#0a1628;">Temas críticos identificados:</strong>
    <ul class="ul" style="margin-top:5px;">
      <?php foreach ($vozClientes['temas_criticos'] as $t): ?>
      <li><?= htmlspecialchars($t) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <?php if (!empty($vozClientes['alertas'])): ?>
  <?php foreach ($vozClientes['alertas'] as $alerta): ?>
  <div class="callout callout-alert"><strong>⚠ ALERTA:</strong> <?= htmlspecialchars($alerta) ?></div>
  <?php endforeach; ?>
  <?php endif; ?>

  <?php if (!empty($vozClientes['oportunidade_identificada'])): ?>
  <div class="callout callout-pos"><strong>Oportunidade:</strong> <?= htmlspecialchars($vozClientes['oportunidade_identificada']) ?></div>
  <?php endif; ?>

  <?php if (!empty($wa['temas_recorrentes'])): ?>
  <div style="margin-top:10px;">
    <strong style="font-size:8.5px; color:#718096;">Palavras mais frequentes nas mensagens dos clientes:</strong>
    <div style="margin-top:5px; font-size:8px; color:#4a5568; line-height:1.8;">
      <?php $i = 0; foreach (array_slice($wa['temas_recorrentes'], 0, 25, true) as $palavra => $freq): $i++; ?>
      <span style="background:#edf2f7; padding:2px 6px; border-radius:2px; margin:2px; display:inline-block;">
        <?= htmlspecialchars($palavra) ?> <span style="color:#c9a84c;">(<?= $freq ?>)</span>
      </span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($wa['conversas_criticas_urgentes'])): ?>
  <div style="margin-top:8px; font-size:8.5px; color:#c0392b;">
    ⚠ <?= $wa['conversas_criticas_urgentes'] ?> conversa(s) com prioridade crítica/urgente no período.
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══ INTELIGÊNCIA DE MERCADO / LEADS ══ -->
<?php if (!empty($intMercado['perfil_leads_periodo']) && ($leads['total'] ?? 0) > 0): ?>
<div class="section page-break">
  <div class="sh"><h2>Inteligência de Mercado</h2><div class="sh-sub"><?= $leads['total'] ?? 0 ?> leads no período · Captação · Conversão</div></div>

  <div class="metrics">
    <div class="mbox">
      <div class="mval"><?= $leads['total'] ?? 0 ?></div>
      <div class="mlbl">Total de Leads</div>
    </div>
    <div class="mbox">
      <div class="mval" style="color:#27ae60;"><?= $leads['por_intencao_contratar']['sim'] ?? 0 ?></div>
      <div class="mlbl">Intenção Alta</div>
    </div>
    <div class="mbox">
      <div class="mval"><?= $leads['por_intencao_contratar']['talvez'] ?? 0 ?></div>
      <div class="mlbl">Intenção Média</div>
    </div>
    <div class="mbox">
      <div class="mval"><?= $leads['taxa_conversao_estimada'] ?? 0 ?>%</div>
      <div class="mlbl">Taxa Conv. Estimada</div>
    </div>
    <div class="mbox">
      <div class="mval"><?= $leads['convertidos_para_cliente'] ?? 0 ?></div>
      <div class="mlbl">Convertidos</div>
    </div>
  </div>

  <div class="two">
    <div class="cl">
      <div class="analise">
        <?php foreach (explode("\n", $intMercado['perfil_leads_periodo']) as $p): if(trim($p)): ?>
        <p><?= htmlspecialchars(trim($p)) ?></p>
        <?php endif; endforeach; ?>
      </div>

      <?php if (!empty($intMercado['qualidade_captacao'])): ?>
      <div class="callout callout-info" style="margin-bottom:8px;">
        <strong>Qualidade da captação:</strong> <?= htmlspecialchars($intMercado['qualidade_captacao']) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($intMercado['campanhas_eficazes'])): ?>
      <div class="callout callout-info">
        <strong>Campanhas eficazes:</strong> <?= htmlspecialchars($intMercado['campanhas_eficazes']) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($leads['por_area'])): ?>
      <strong style="font-size:8.5px;">Leads por área jurídica:</strong>
      <table class="dt" style="margin-top:5px;">
        <tr><th>Área</th><th>Leads</th></tr>
        <?php foreach (array_slice($leads['por_area'], 0, 8, true) as $area => $tot): ?>
        <tr><td><?= htmlspecialchars($area) ?></td><td><?= $tot ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <div class="cr">
      <?php if (!empty($charts['leads_area'])): ?>
      <strong style="font-size:8.5px;">Distribuição por área:</strong>
      <img src="<?= $charts['leads_area'] ?>" class="chart-img" alt="Leads por área">
      <?php endif; ?>
      <?php if (!empty($charts['leads_intencao'])): ?>
      <strong style="font-size:8.5px; display:block; margin-top:4px;">Intenção de contratar:</strong>
      <img src="<?= $charts['leads_intencao'] ?>" class="chart-img" alt="Intenção de contratar">
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($leads['por_gatilho_emocional'])): ?>
  <div style="margin-top:8px;">
    <strong style="font-size:8.5px;">Gatilhos emocionais predominantes:</strong>
    <div style="margin-top:5px;">
      <?php arsort($leads['por_gatilho_emocional']); foreach (array_slice($leads['por_gatilho_emocional'], 0, 8, true) as $g => $n): ?>
      <span class="badge bg-yellow" style="margin:2px;"><?= htmlspecialchars($g) ?> (<?= $n ?>)</span>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <?php if (!empty($intMercado['alertas'])): ?>
  <div style="margin-top:10px;">
    <?php foreach ($intMercado['alertas'] as $alerta): ?>
    <div class="callout callout-warn"><strong>Atenção:</strong> <?= htmlspecialchars($alerta) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($intMercado['oportunidades'])): ?>
  <strong style="font-size:8.5px; color:#27ae60;">Oportunidades identificadas:</strong>
  <ul class="ul">
    <?php foreach ($intMercado['oportunidades'] as $o): ?><li><?= htmlspecialchars($o) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ══ FINANCEIRO ══ -->
<div class="section page-break">
  <div class="sh"><h2>Análise Financeira</h2><div class="sh-sub">DRE · Inadimplência · Histórico 6 meses</div></div>

  <?php
  $dreAt  = $fin['dre_atual'] ?? [];
  $varPct = $fin['variacao_receita_pct'] ?? 0;
  $inadim = $fin['inadimplencia'] ?? [];
  ?>
  <div class="metrics">
    <div class="mbox">
      <div class="mval">R$ <?= number_format($dreAt['receita_total'] ?? 0, 0, ',', '.') ?></div>
      <div class="mlbl">Receita Total</div>
      <div class="mvar <?= $varPct >= 0 ? 'pos' : 'neg' ?>"><?= $varPct >= 0 ? '▲' : '▼' ?> <?= abs($varPct) ?>% vs mês ant.</div>
    </div>
    <div class="mbox">
      <div class="mval">R$ <?= number_format(abs($dreAt['despesas'] ?? 0), 0, ',', '.') ?></div>
      <div class="mlbl">Despesas</div>
    </div>
    <div class="mbox">
      <div class="mval" style="color:<?= ($dreAt['resultado'] ?? 0) >= 0 ? '#27ae60' : '#c0392b' ?>">
        R$ <?= number_format($dreAt['resultado'] ?? 0, 0, ',', '.') ?>
      </div>
      <div class="mlbl">Resultado</div>
    </div>
    <div class="mbox">
      <div class="mval" style="color:#c0392b;">R$ <?= number_format($inadim['valor'] ?? 0, 0, ',', '.') ?></div>
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
</div>

<!-- ══ PERFORMANCE DA EQUIPE ══ -->
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
  <div class="callout callout-info" style="margin-top:10px;">
    <strong>GDP × NEXO — Cruzamento de performance:</strong> <?= htmlspecialchars($aEquipe['cruzamento_gdp_nexo']) ?>
  </div>
  <?php endif; ?>

  <?php if (!empty($nexo['qa_scores'])): ?>
  <strong style="font-size:8.5px; display:block; margin-top:12px;">QA — Satisfação dos clientes:</strong>
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

<!-- ══ CARTEIRA DE PROCESSOS ══ -->
<div class="section page-break">
  <div class="sh"><h2>Carteira de Processos</h2><div class="sh-sub">Portfolio ativo · Prazos · Andamentos</div></div>

  <div class="metrics">
    <div class="mbox">
      <div class="mval"><?= number_format($proc['total_ativos'] ?? 0) ?></div>
      <div class="mlbl">Processos Ativos</div>
    </div>
    <div class="mbox">
      <div class="mval">R$ <?= number_format(($proc['valor_carteira'] ?? 0) / 1000000, 1, ',', '.') ?>M</div>
      <div class="mlbl">Valor da Carteira</div>
    </div>
    <div class="mbox">
      <div class="mval" style="color:<?= ($proc['prazos_vencidos'] ?? 0) > 0 ? '#c0392b' : '#27ae60' ?>">
        <?= $proc['prazos_vencidos'] ?? 0 ?>
      </div>
      <div class="mlbl">Prazos Vencidos</div>
    </div>
    <div class="mbox">
      <div class="mval"><?= number_format($proc['novos_no_periodo'] ?? 0) ?></div>
      <div class="mlbl">Novos no Período</div>
    </div>
    <div class="mbox">
      <div class="mval"><?= number_format($proc['andamentos_periodo'] ?? 0) ?></div>
      <div class="mlbl">Andamentos</div>
    </div>
  </div>

  <div class="two">
    <div class="cl">
      <div class="analise">
        <?php foreach (explode("\n", $aProc['analise'] ?? '') as $p): if(trim($p)): ?>
        <p><?= htmlspecialchars(trim($p)) ?></p>
        <?php endif; endforeach; ?>
      </div>

      <?php if (!empty($aProc['riscos_prazos'])): ?>
      <div class="callout callout-alert">
        <strong>Prazos — exposição jurídica:</strong> <?= htmlspecialchars($aProc['riscos_prazos']) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($proc['prazos_proximos'])): ?>
      <strong style="font-size:8.5px; display:block; margin-top:10px;">Prazos fatais próximos (30 dias):</strong>
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
      <strong style="font-size:8.5px;">Por tipo de ação:</strong>
      <img src="<?= $charts['processos_tipo'] ?>" class="chart-img" alt="Processos por tipo">
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ══ CRUZAMENTOS ESTRATÉGICOS ══ -->
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

<!-- ══ RECOMENDAÇÕES ══ -->
<div class="section page-break">
  <div class="sh"><h2>Recomendações Priorizadas</h2><div class="sh-sub">Decisões acionáveis · Ordenadas por impacto e urgência</div></div>

  <?php foreach ($recomendacoes as $rec): ?>
  <?php $prazo = $rec['prazo'] ?? ''; ?>
  <div class="rec-card">
    <div class="rec-pri">
      PRIORIDADE <?= $rec['prioridade'] ?? '?' ?>
      <span class="badge <?= $prazo === 'imediato' ? 'bg-red' : ($prazo === '7 dias' ? 'bg-yellow' : 'bg-blue') ?>" style="margin-left:8px;">
        <?= htmlspecialchars(strtoupper($prazo)) ?>
      </span>
      <span class="badge bg-navy" style="margin-left:4px;"><?= htmlspecialchars(strtoupper($rec['area'] ?? '')) ?></span>
    </div>
    <div class="rec-decisao"><?= htmlspecialchars($rec['decisao'] ?? '') ?></div>
    <div class="rec-why"><?= htmlspecialchars($rec['por_que_agora'] ?? '') ?></div>
    <div class="rec-impacto">Impacto esperado: <?= htmlspecialchars($rec['impacto_esperado'] ?? '') ?></div>
  </div>
  <?php endforeach; ?>

  <?php if (!empty($monitorar)): ?>
  <div style="margin-top:20px; background:#f4f6f9; border-radius:3px; padding:12px 15px;">
    <strong style="font-size:9px; color:#0a1628;">O que monitorar no próximo período:</strong>
    <ul class="ul" style="margin-top:6px;">
      <?php foreach ($monitorar as $m): ?><li><?= htmlspecialchars($m) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div class="footer">
    Mayer Sociedade de Advogados · Relatório de Inteligência Executiva · <?= htmlspecialchars($periodoLabel) ?>
    <br>Gerado em <?= $geradoEm ?> via Claude Opus 4.7 · CONFIDENCIAL
  </div>
</div>

</body>
</html>
<?php
        return ob_get_clean();
    }
}
