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

        // Gráfico: Receita 6 meses
        $historico = $dados['financeiro']['historico_6meses'] ?? [];
        if (!empty($historico)) {
            $labels   = array_column($historico, 'mes');
            $receitas = array_column($historico, 'receita_total');
            $despesas = array_column($historico, 'despesas');
            $charts['receita_historico'] = $this->fetchChart([
                'type' => 'bar',
                'data' => [
                    'labels'   => $labels,
                    'datasets' => [
                        ['label' => 'Receita', 'data' => $receitas, 'backgroundColor' => '#1e3a5f'],
                        ['label' => 'Despesas', 'data' => array_map(fn($v) => abs($v), $despesas), 'backgroundColor' => '#c0392b'],
                    ],
                ],
                'options' => [
                    'plugins' => ['legend' => ['labels' => ['font' => ['size' => 10]]]],
                    'scales'  => ['y' => ['ticks' => ['font' => ['size' => 9]]]],
                ],
            ]);
        }

        // Gráfico: Performance advogados (scores)
        $snapshots = $dados['gdp']['snapshots'] ?? [];
        if (!empty($snapshots)) {
            $nomes  = array_column($snapshots, 'name');
            $scores = array_column($snapshots, 'score_total');
            $charts['performance_adv'] = $this->fetchChart([
                'type' => 'horizontalBar',
                'data' => [
                    'labels'   => $nomes,
                    'datasets' => [
                        ['label' => 'Score Total', 'data' => $scores, 'backgroundColor' => '#1e3a5f'],
                    ],
                ],
                'options' => [
                    'scales' => ['x' => ['max' => 100, 'ticks' => ['font' => ['size' => 9]]]],
                    'plugins' => ['legend' => ['display' => false]],
                ],
            ]);
        }

        // Gráfico: Atendimentos por responsável
        $porAtendente = $dados['nexo']['por_atendente'] ?? [];
        if (!empty($porAtendente)) {
            $nomes  = array_column($porAtendente, 'name');
            $totais = array_column($porAtendente, 'total_atendimentos');
            $charts['atendimentos_adv'] = $this->fetchChart([
                'type' => 'pie',
                'data' => [
                    'labels'   => array_slice($nomes, 0, 6),
                    'datasets' => [
                        [
                            'data'            => array_slice($totais, 0, 6),
                            'backgroundColor' => ['#1e3a5f', '#2e6da4', '#3498db', '#5dade2', '#85c1e9', '#aed6f1'],
                        ],
                    ],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 9]]]]],
            ]);
        }

        // Gráfico: Processos por tipo de ação (top 6)
        $porTipo = $dados['processos']['por_tipo_acao'] ?? [];
        if (!empty($porTipo)) {
            $tipos  = array_slice(array_column($porTipo, 'tipo_acao'), 0, 6);
            $totais = array_slice(array_column($porTipo, 'total'), 0, 6);
            $charts['processos_tipo'] = $this->fetchChart([
                'type' => 'doughnut',
                'data' => [
                    'labels'   => $tipos,
                    'datasets' => [
                        [
                            'data'            => $totais,
                            'backgroundColor' => ['#1e3a5f', '#2e6da4', '#3498db', '#5dade2', '#85c1e9', '#aed6f1'],
                        ],
                    ],
                ],
                'options' => ['plugins' => ['legend' => ['position' => 'right', 'labels' => ['font' => ['size' => 8]]]]],
            ]);
        }

        return $charts;
    }

    private function fetchChart(array $config): string
    {
        try {
            $json  = urlencode(json_encode($config, JSON_UNESCAPED_UNICODE));
            $url   = "https://quickchart.io/chart?w=540&h=220&bkg=white&c={$json}";
            $resp  = Http::timeout(15)->get($url);
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
        $fin  = $dados['financeiro'] ?? [];
        $nexo = $dados['nexo'] ?? [];
        $gdp  = $dados['gdp'] ?? [];
        $proc = $dados['processos'] ?? [];
        $merc = $dados['mercado'] ?? [];
        $ga   = $dados['ga'] ?? [];
        $geradoEm = now()->format('d/m/Y H:i');

        $res   = $analise['resumo_executivo'] ?? [];
        $aFin  = $analise['financeiro'] ?? [];
        $aAte  = $analise['atendimentos'] ?? [];
        $aAdv  = $analise['performance_advogados'] ?? [];
        $aProc = $analise['carteira_processos'] ?? [];
        $aMerc = $analise['mercado'] ?? [];
        $aMkt  = $analise['marketing'] ?? [];
        $aRec  = $analise['recomendacoes_gerenciais'] ?? [];

        $scoreGeral = $res['score_geral'] ?? 0;
        $scoreCor   = $scoreGeral >= 7 ? '#27ae60' : ($scoreGeral >= 5 ? '#f39c12' : '#c0392b');

        ob_start(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
* { margin: 0; padding: 0; box-sizing: border-box; }
body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #2c3e50; background: #fff; }
.page-break { page-break-before: always; }

/* CAPA */
.cover { background: #0a1628; color: #fff; padding: 60px 50px; min-height: 250px; }
.cover-logo { font-size: 22px; font-weight: bold; color: #c9a84c; letter-spacing: 2px; margin-bottom: 5px; }
.cover-sub  { font-size: 11px; color: #8899aa; margin-bottom: 50px; }
.cover-title { font-size: 28px; font-weight: bold; color: #fff; margin-bottom: 10px; }
.cover-periodo { font-size: 14px; color: #c9a84c; margin-bottom: 30px; }
.cover-score { display: inline-block; background: <?= $scoreCor ?>; color: #fff; padding: 8px 20px; border-radius: 4px; font-size: 13px; font-weight: bold; }
.cover-meta { font-size: 9px; color: #667788; margin-top: 20px; }

/* SEÇÕES */
.section { padding: 25px 40px; border-bottom: 1px solid #ecf0f1; }
.section-header { background: #0a1628; color: #fff; padding: 8px 15px; margin: 0 -40px 15px; }
.section-header h2 { font-size: 13px; letter-spacing: 1px; text-transform: uppercase; color: #c9a84c; }
.section-header .section-sub { font-size: 9px; color: #8899aa; }

/* MÉTRICAS */
.metrics-grid { display: table; width: 100%; margin-bottom: 15px; }
.metric-box { display: table-cell; text-align: center; padding: 10px; background: #f8f9fa; border: 1px solid #e9ecef; }
.metric-val { font-size: 18px; font-weight: bold; color: #0a1628; }
.metric-lbl { font-size: 8px; color: #7f8c8d; text-transform: uppercase; }
.metric-var { font-size: 9px; }
.var-pos { color: #27ae60; }
.var-neg { color: #c0392b; }

/* TEXTO */
.analise-text { font-size: 9.5px; line-height: 1.6; color: #2c3e50; margin-bottom: 12px; }
.ul-clean { padding-left: 15px; margin-bottom: 10px; }
.ul-clean li { font-size: 9.5px; margin-bottom: 4px; line-height: 1.5; }
.ul-clean li::marker { color: #c9a84c; }
.tag-pos { background: #d5f5e3; color: #1a7a3e; padding: 2px 6px; border-radius: 3px; font-size: 8px; }
.tag-neg { background: #fadbd8; color: #922b21; padding: 2px 6px; border-radius: 3px; font-size: 8px; }
.tag-warn { background: #fef9e7; color: #7d6608; padding: 2px 6px; border-radius: 3px; font-size: 8px; }

/* TABELAS */
table.data { width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 9px; }
table.data th { background: #0a1628; color: #c9a84c; padding: 5px 8px; text-align: left; font-size: 8px; }
table.data td { padding: 4px 8px; border-bottom: 1px solid #eee; }
table.data tr:nth-child(even) td { background: #f8f9fa; }

/* RECOMENDAÇÕES */
.rec-card { border-left: 3px solid #c9a84c; padding: 8px 12px; margin-bottom: 10px; background: #fffdf5; }
.rec-num { font-size: 10px; font-weight: bold; color: #c9a84c; }
.rec-decisao { font-size: 10px; font-weight: bold; color: #0a1628; margin: 3px 0; }
.rec-just { font-size: 9px; color: #555; margin-bottom: 4px; }
.rec-prazo { font-size: 8px; color: #888; }
.prazo-imediato { color: #c0392b; font-weight: bold; }

/* CHART */
.chart-img { max-width: 100%; margin: 10px 0; }

/* DOIS COLUNAS */
.two-col { display: table; width: 100%; }
.col-left  { display: table-cell; width: 60%; padding-right: 15px; vertical-align: top; }
.col-right { display: table-cell; width: 40%; vertical-align: top; }

.badge { display: inline-block; padding: 2px 7px; border-radius: 3px; font-size: 8px; font-weight: bold; }
.badge-green  { background: #d5f5e3; color: #1a7a3e; }
.badge-yellow { background: #fef9e7; color: #7d6608; }
.badge-red    { background: #fadbd8; color: #922b21; }
.badge-blue   { background: #d6eaf8; color: #1a5276; }

.noticias-list { font-size: 9px; }
.noticia-item { padding: 6px 0; border-bottom: 1px solid #eee; }
.noticia-titulo { font-weight: bold; color: #0a1628; }
.noticia-meta   { color: #888; font-size: 8px; }
</style>
</head>
<body>

<!-- CAPA -->
<div class="cover">
  <div class="cover-logo">MAYER ADVOGADOS</div>
  <div class="cover-sub">Sociedade de Advogados · Itajaí, SC · OAB/SC 2097</div>
  <div class="cover-title">Relatório Executivo CEO</div>
  <div class="cover-periodo">Período: <?= htmlspecialchars($periodoLabel) ?></div>
  <div>
    <span class="cover-score">Score Geral: <?= $scoreGeral ?>/10</span>
  </div>
  <?php if (!empty($res['titulo'])): ?>
  <div style="margin-top:20px; font-size:13px; color:#8899aa; font-style:italic;">"<?= htmlspecialchars($res['titulo']) ?>"</div>
  <?php endif; ?>
  <div class="cover-meta">Gerado em <?= $geradoEm ?> · Análise via Claude Opus 4.7 · Confidencial — uso interno</div>
</div>

<!-- RESUMO EXECUTIVO -->
<div class="section">
  <div class="section-header">
    <h2>Resumo Executivo</h2>
    <div class="section-sub">Visão consolidada do período</div>
  </div>
  <div class="analise-text"><?= nl2br(htmlspecialchars($res['visao_geral'] ?? '')) ?></div>
  <div class="two-col">
    <div class="col-left">
      <strong style="font-size:9px; color:#27ae60;">✔ DESTAQUES POSITIVOS</strong>
      <ul class="ul-clean" style="margin-top:6px;">
        <?php foreach ($res['destaques_positivos'] ?? [] as $d): ?>
        <li><?= htmlspecialchars($d) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="col-right">
      <strong style="font-size:9px; color:#c0392b;">⚠ ALERTAS CRÍTICOS</strong>
      <ul class="ul-clean" style="margin-top:6px;">
        <?php foreach ($res['alertas_criticos'] ?? [] as $a): ?>
        <li style="color:#c0392b;"><?= htmlspecialchars($a) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<!-- FINANCEIRO -->
<div class="section page-break">
  <div class="section-header">
    <h2>Análise Financeira</h2>
    <div class="section-sub">DRE comparativo · Inadimplência · Histórico 6 meses</div>
  </div>

  <?php
  $dreAt = $fin['dre_atual'] ?? [];
  $dreAntes = $fin['dre_anterior'] ?? [];
  $varPct = $fin['variacao_receita_pct'] ?? 0;
  $inadim = $fin['inadimplencia'] ?? [];
  ?>
  <div class="metrics-grid">
    <div class="metric-box">
      <div class="metric-val">R$ <?= number_format($dreAt['receita_total'] ?? 0, 0, ',', '.') ?></div>
      <div class="metric-lbl">Receita Total</div>
      <div class="metric-var <?= $varPct >= 0 ? 'var-pos' : 'var-neg' ?>">
        <?= $varPct >= 0 ? '▲' : '▼' ?> <?= abs($varPct) ?>% vs mês ant.
      </div>
    </div>
    <div class="metric-box">
      <div class="metric-val">R$ <?= number_format(abs($dreAt['despesas'] ?? 0), 0, ',', '.') ?></div>
      <div class="metric-lbl">Despesas Totais</div>
    </div>
    <div class="metric-box">
      <div class="metric-val" style="color:<?= ($dreAt['resultado'] ?? 0) >= 0 ? '#27ae60' : '#c0392b' ?>">
        R$ <?= number_format($dreAt['resultado'] ?? 0, 0, ',', '.') ?>
      </div>
      <div class="metric-lbl">Resultado</div>
    </div>
    <div class="metric-box">
      <div class="metric-val" style="color:#c0392b;">R$ <?= number_format($inadim['valor'] ?? 0, 0, ',', '.') ?></div>
      <div class="metric-lbl">Inadimplência</div>
      <div class="metric-var"><?= $inadim['qtd'] ?? 0 ?> títulos</div>
    </div>
  </div>

  <?php if (!empty($charts['receita_historico'])): ?>
  <img src="<?= $charts['receita_historico'] ?>" class="chart-img" alt="Histórico receita">
  <?php endif; ?>

  <div class="analise-text"><?= nl2br(htmlspecialchars($aFin['analise'] ?? '')) ?></div>
  <?php if (!empty($aFin['pontos_atencao'])): ?>
  <strong style="font-size:9px;">Pontos de atenção:</strong>
  <ul class="ul-clean">
    <?php foreach ($aFin['pontos_atencao'] as $p): ?><li><?= htmlspecialchars($p) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>

<!-- ATENDIMENTOS NEXO -->
<div class="section page-break">
  <div class="section-header">
    <h2>Atendimentos &amp; Canais</h2>
    <div class="section-sub">WhatsApp NEXO · Qualidade · Público · Perfil</div>
  </div>

  <div class="metrics-grid">
    <div class="metric-box">
      <div class="metric-val"><?= number_format($nexo['novas_conversas'] ?? 0) ?></div>
      <div class="metric-lbl">Novas Conversas</div>
    </div>
    <div class="metric-box">
      <div class="metric-val"><?= number_format($nexo['total_mensagens'] ?? 0) ?></div>
      <div class="metric-lbl">Mensagens Totais</div>
    </div>
    <div class="metric-box">
      <div class="metric-val"><?= number_format($nexo['tempo_medio_resposta_min'] ?? 0) ?> min</div>
      <div class="metric-lbl">Tempo Médio Resposta</div>
    </div>
    <div class="metric-box">
      <div class="metric-val"><?= count($nexo['por_atendente'] ?? []) ?></div>
      <div class="metric-lbl">Atendentes Ativos</div>
    </div>
  </div>

  <div class="two-col">
    <div class="col-left">
      <?php if (!empty($nexo['por_atendente'])): ?>
      <strong style="font-size:9px;">Atendimentos por responsável:</strong>
      <table class="data" style="margin-top:6px;">
        <tr><th>Advogado</th><th>Atendimentos</th></tr>
        <?php foreach (array_slice($nexo['por_atendente'], 0, 8) as $a): ?>
        <tr><td><?= htmlspecialchars($a['name']) ?></td><td><?= $a['total_atendimentos'] ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
      <div class="analise-text" style="margin-top:10px;"><?= nl2br(htmlspecialchars($aAte['analise_qualidade'] ?? '')) ?></div>
    </div>
    <div class="col-right">
      <?php if (!empty($charts['atendimentos_adv'])): ?>
      <img src="<?= $charts['atendimentos_adv'] ?>" class="chart-img" alt="Atendimentos">
      <?php endif; ?>
    </div>
  </div>

  <?php if (!empty($aAte['perfil_publico'])): ?>
  <div style="background:#f0f4ff; padding:10px; border-radius:4px; margin-top:10px;">
    <strong style="font-size:9px; color:#1a5276;">PERFIL DO PÚBLICO:</strong>
    <div class="analise-text" style="margin-top:4px;"><?= htmlspecialchars($aAte['perfil_publico']) ?></div>
  </div>
  <?php endif; ?>

  <?php if (!empty($nexo['qa_scores'])): ?>
  <strong style="font-size:9px; margin-top:10px; display:block;">QA — Satisfação de Clientes:</strong>
  <table class="data">
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

<!-- PERFORMANCE ADVOGADOS -->
<div class="section page-break">
  <div class="section-header">
    <h2>Performance dos Advogados</h2>
    <div class="section-sub">GDP · Scores · Rankings · Indicadores</div>
  </div>

  <div class="two-col">
    <div class="col-left">
      <?php if (!empty($gdp['snapshots'])): ?>
      <table class="data">
        <tr><th>#</th><th>Advogado</th><th>Score</th><th>Jurídico</th><th>Financeiro</th><th>Atend.</th><th>Var.</th></tr>
        <?php foreach ($gdp['snapshots'] as $s): ?>
        <tr>
          <td><?= $s['ranking'] ?? '-' ?></td>
          <td><?= htmlspecialchars($s['name']) ?></td>
          <td><strong><?= number_format($s['score_total'] ?? 0, 1) ?></strong></td>
          <td><?= number_format($s['score_juridico'] ?? 0, 1) ?></td>
          <td><?= number_format($s['score_financeiro'] ?? 0, 1) ?></td>
          <td><?= number_format($s['score_atendimento'] ?? 0, 1) ?></td>
          <td class="<?= ($s['variacao_score'] ?? 0) >= 0 ? 'var-pos' : 'var-neg' ?>">
            <?= ($s['variacao_score'] ?? 0) >= 0 ? '▲' : '▼' ?> <?= abs(round($s['variacao_score'] ?? 0, 1)) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
      <?php endif; ?>
    </div>
    <div class="col-right">
      <?php if (!empty($charts['performance_adv'])): ?>
      <img src="<?= $charts['performance_adv'] ?>" class="chart-img" alt="Performance advogados">
      <?php endif; ?>
    </div>
  </div>

  <div class="analise-text"><?= nl2br(htmlspecialchars($aAdv['analise_geral'] ?? '')) ?></div>
  <?php if (!empty($aAdv['destaque_positivo'])): ?>
  <div style="background:#d5f5e3; padding:8px 12px; border-radius:3px; margin-bottom:8px; font-size:9px;">
    <strong>✔ Destaque positivo:</strong> <?= htmlspecialchars($aAdv['destaque_positivo']) ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($aAdv['ponto_melhoria'])): ?>
  <div style="background:#fef9e7; padding:8px 12px; border-radius:3px; font-size:9px;">
    <strong>⚡ Ponto de melhoria:</strong> <?= htmlspecialchars($aAdv['ponto_melhoria']) ?>
  </div>
  <?php endif; ?>
</div>

<!-- CARTEIRA DE PROCESSOS -->
<div class="section page-break">
  <div class="section-header">
    <h2>Carteira de Processos</h2>
    <div class="section-sub">Portfolio ativo · Prazos · Movimentações</div>
  </div>

  <div class="metrics-grid">
    <div class="metric-box">
      <div class="metric-val"><?= number_format($proc['total_ativos'] ?? 0) ?></div>
      <div class="metric-lbl">Processos Ativos</div>
    </div>
    <div class="metric-box">
      <div class="metric-val">R$ <?= number_format(($proc['valor_carteira'] ?? 0) / 1000000, 1, ',', '.') ?>M</div>
      <div class="metric-lbl">Valor da Carteira</div>
    </div>
    <div class="metric-box">
      <div class="metric-val" style="color:<?= ($proc['prazos_vencidos'] ?? 0) > 0 ? '#c0392b' : '#27ae60' ?>">
        <?= $proc['prazos_vencidos'] ?? 0 ?>
      </div>
      <div class="metric-lbl">Prazos Vencidos</div>
    </div>
    <div class="metric-box">
      <div class="metric-val"><?= number_format($proc['andamentos_periodo'] ?? 0) ?></div>
      <div class="metric-lbl">Andamentos no Período</div>
    </div>
  </div>

  <div class="two-col">
    <div class="col-left">
      <div class="analise-text"><?= nl2br(htmlspecialchars($aProc['analise'] ?? '')) ?></div>
      <?php if (!empty($aProc['riscos'])): ?>
      <strong style="font-size:9px; color:#c0392b;">Riscos identificados:</strong>
      <ul class="ul-clean">
        <?php foreach ($aProc['riscos'] as $r): ?><li><?= htmlspecialchars($r) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (!empty($proc['prazos_proximos'])): ?>
      <strong style="font-size:9px; margin-top:10px; display:block;">Prazos críticos próximos:</strong>
      <table class="data" style="margin-top:4px;">
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
    <div class="col-right">
      <?php if (!empty($charts['processos_tipo'])): ?>
      <img src="<?= $charts['processos_tipo'] ?>" class="chart-img" alt="Processos por tipo">
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MERCADO -->
<div class="section page-break">
  <div class="section-header">
    <h2>Mercado &amp; Tendências</h2>
    <div class="section-sub">Itajaí/SC · Setor jurídico · Oportunidades</div>
  </div>

  <div class="analise-text"><?= nl2br(htmlspecialchars($aMerc['contexto_regional'] ?? '')) ?></div>

  <div class="two-col">
    <div class="col-left">
      <?php if (!empty($aMerc['tendencias'])): ?>
      <strong style="font-size:9px;">Tendências identificadas:</strong>
      <ul class="ul-clean" style="margin-top:5px;">
        <?php foreach ($aMerc['tendencias'] as $t): ?><li><?= htmlspecialchars($t) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if (!empty($aMerc['oportunidades_negocio'])): ?>
      <strong style="font-size:9px; margin-top:10px; display:block; color:#27ae60;">Oportunidades de negócio:</strong>
      <ul class="ul-clean" style="margin-top:5px;">
        <?php foreach ($aMerc['oportunidades_negocio'] as $o): ?><li><?= htmlspecialchars($o) ?></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <div class="col-right">
      <?php if (!empty($merc['noticias'])): ?>
      <strong style="font-size:9px;">Notícias recentes do setor:</strong>
      <div class="noticias-list" style="margin-top:6px;">
        <?php foreach (array_slice($merc['noticias'], 0, 5) as $n): ?>
        <div class="noticia-item">
          <div class="noticia-titulo"><?= htmlspecialchars($n['titulo']) ?></div>
          <div class="noticia-meta"><?= htmlspecialchars($n['fonte']) ?> · <?= $n['data'] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- MARKETING -->
<div class="section">
  <div class="section-header">
    <h2>Marketing &amp; Aquisição</h2>
    <div class="section-sub">Performance dos canais · Perfil de leads</div>
  </div>

  <div class="analise-text"><?= nl2br(htmlspecialchars($aMkt['analise'] ?? '')) ?></div>
  <?php if (!empty($aMkt['perfil_leads'])): ?>
  <div style="background:#f0f4ff; padding:10px; border-radius:4px; margin-bottom:10px;">
    <strong style="font-size:9px; color:#1a5276;">PERFIL DOS LEADS:</strong>
    <div class="analise-text" style="margin-top:4px;"><?= htmlspecialchars($aMkt['perfil_leads']) ?></div>
  </div>
  <?php endif; ?>
  <?php if (!empty($aMkt['canais_efetivos'])): ?>
  <strong style="font-size:9px;">Canais mais efetivos:</strong>
  <ul class="ul-clean">
    <?php foreach ($aMkt['canais_efetivos'] as $c): ?><li><?= htmlspecialchars($c) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>
  <?php if (!empty($aMkt['recomendacoes'])): ?>
  <strong style="font-size:9px;">Recomendações de marketing:</strong>
  <ul class="ul-clean">
    <?php foreach ($aMkt['recomendacoes'] as $r): ?><li><?= htmlspecialchars($r) ?></li><?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <?php if (!empty($ga['configurado']) && empty($ga['erro'])): ?>
  <?php $visao = $ga['visao_geral'] ?? []; $prev = $ga['periodo_anterior'] ?? []; ?>
  <div style="background:#f0f8ff; border:1px solid #bee3f8; border-radius:4px; padding:10px 12px; margin-top:12px;">
    <strong style="font-size:9px; color:#1a5276;">GOOGLE ANALYTICS 4 — Tráfego Web</strong>
    <div class="metrics-grid" style="margin-top:8px;">
      <div class="metric-box">
        <div class="metric-val"><?= number_format($visao['sessions'] ?? 0) ?></div>
        <div class="metric-lbl">Sessões</div>
        <div class="metric-var <?= ($ga['variacao_sessoes'] ?? 0) >= 0 ? 'var-pos' : 'var-neg' ?>">
          <?= ($ga['variacao_sessoes'] ?? 0) >= 0 ? '▲' : '▼' ?> <?= abs($ga['variacao_sessoes'] ?? 0) ?>%
        </div>
      </div>
      <div class="metric-box">
        <div class="metric-val"><?= number_format($visao['active_users'] ?? 0) ?></div>
        <div class="metric-lbl">Usuários Ativos</div>
        <div class="metric-var <?= ($ga['variacao_usuarios'] ?? 0) >= 0 ? 'var-pos' : 'var-neg' ?>">
          <?= ($ga['variacao_usuarios'] ?? 0) >= 0 ? '▲' : '▼' ?> <?= abs($ga['variacao_usuarios'] ?? 0) ?>%
        </div>
      </div>
      <div class="metric-box">
        <div class="metric-val"><?= number_format($visao['new_users'] ?? 0) ?></div>
        <div class="metric-lbl">Novos Usuários</div>
      </div>
      <div class="metric-box">
        <div class="metric-val"><?= $visao['bounce_rate'] ?? 0 ?>%</div>
        <div class="metric-lbl">Taxa de Rejeição</div>
      </div>
    </div>

    <?php if (!empty($ga['por_canal'])): ?>
    <strong style="font-size:9px; display:block; margin-top:10px;">Sessões por canal:</strong>
    <table class="data" style="margin-top:4px;">
      <tr><th>Canal</th><th>Sessões</th><th>Novos</th><th>Conv.</th></tr>
      <?php foreach ($ga['por_canal'] as $c): ?>
      <tr>
        <td><?= htmlspecialchars($c['canal']) ?></td>
        <td><?= number_format($c['sessions']) ?></td>
        <td><?= number_format($c['new_users']) ?></td>
        <td><?= number_format($c['conversions']) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($ga['top_paginas'])): ?>
    <strong style="font-size:9px; display:block; margin-top:10px;">Páginas mais visitadas:</strong>
    <table class="data" style="margin-top:4px;">
      <tr><th>Página</th><th>Pageviews</th></tr>
      <?php foreach ($ga['top_paginas'] as $p): ?>
      <tr><td><?= htmlspecialchars($p['pagina']) ?></td><td><?= number_format($p['pageviews']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php endif; ?>

    <?php if (!empty($ga['por_dispositivo'])): ?>
    <div style="margin-top:8px; font-size:8.5px; color:#555;">
      Dispositivos: <?= implode(' · ', array_map(fn($k, $v) => "{$k}: {$v}", array_keys($ga['por_dispositivo']), $ga['por_dispositivo'])) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php elseif (!empty($ga['erro'])): ?>
  <div style="background:#fff5f5; border:1px solid #fed7d7; padding:8px 12px; border-radius:4px; margin-top:10px; font-size:8.5px; color:#c53030;">
    GA4: erro ao coletar dados — <?= htmlspecialchars($ga['erro']) ?>
  </div>
  <?php endif; ?>
</div>

<!-- RECOMENDAÇÕES GERENCIAIS -->
<div class="section page-break">
  <div class="section-header">
    <h2>Recomendações Gerenciais</h2>
    <div class="section-sub">Decisões prioritárias · Ações imediatas</div>
  </div>

  <?php foreach ($aRec as $rec): ?>
  <div class="rec-card">
    <div class="rec-num">
      PRIORIDADE <?= $rec['prioridade'] ?? '?' ?>
      <span class="badge <?= ($rec['prazo_sugerido'] ?? '') === 'imediato' ? 'badge-red' : 'badge-blue' ?>" style="margin-left:8px;">
        <?= htmlspecialchars(strtoupper($rec['prazo_sugerido'] ?? '')) ?>
      </span>
      <span class="badge badge-yellow" style="margin-left:4px;"><?= htmlspecialchars(strtoupper($rec['area'] ?? '')) ?></span>
    </div>
    <div class="rec-decisao"><?= htmlspecialchars($rec['decisao'] ?? '') ?></div>
    <div class="rec-just"><?= htmlspecialchars($rec['justificativa'] ?? '') ?></div>
    <div class="rec-prazo">Impacto esperado: <?= htmlspecialchars($rec['impacto_esperado'] ?? '') ?></div>
  </div>
  <?php endforeach; ?>

  <div style="margin-top:30px; padding-top:15px; border-top:1px solid #eee; text-align:center; font-size:8px; color:#999;">
    Mayer Sociedade de Advogados · Relatório Executivo CEO · <?= htmlspecialchars($periodoLabel) ?>
    <br>Gerado automaticamente em <?= $geradoEm ?> via Claude Opus 4.7 · Confidencial
  </div>
</div>

</body>
</html>
        <?php
        return ob_get_clean();
    }
}
