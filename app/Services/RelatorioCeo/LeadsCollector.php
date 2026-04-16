<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class LeadsCollector
{
    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $inicioStr = $inicio->toDateTimeString();
        $fimStr    = $fim->toDateTimeString();

        $leads = DB::table('leads')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->whereNull('erro_processamento')
            ->orderByDesc('created_at')
            ->get([
                'area_interesse', 'sub_area', 'complexidade', 'urgencia',
                'gatilho_emocional', 'perfil_socioeconomico', 'potencial_honorarios',
                'origem_canal', 'cidade', 'resumo_demanda', 'palavras_chave',
                'intencao_contratar', 'intencao_justificativa',
                'utm_source', 'utm_medium', 'utm_campaign', 'landing_page',
                'status', 'created_at',
            ])
            ->toArray();

        $total = count($leads);
        if ($total === 0) {
            return ['total' => 0, 'leads_detalhados' => [], 'resumos' => []];
        }

        // Agregações
        $porArea = [];
        $porCanal = [];
        $porPotencial = [];
        $porIntencao = [];
        $porGatilho = [];
        $porComplexidade = [];
        $porUrgencia = [];
        $porPerfil = [];
        $leadsDemanda = [];

        foreach ($leads as $l) {
            $l = (array) $l;

            $area = $l['area_interesse'] ?: 'Não identificado';
            $porArea[$area] = ($porArea[$area] ?? 0) + 1;

            $canal = $l['origem_canal'] ?: 'direto';
            $porCanal[$canal] = ($porCanal[$canal] ?? 0) + 1;

            $pot = $l['potencial_honorarios'] ?: 'não avaliado';
            $porPotencial[$pot] = ($porPotencial[$pot] ?? 0) + 1;

            $int = $l['intencao_contratar'] ?: 'não avaliado';
            $porIntencao[$int] = ($porIntencao[$int] ?? 0) + 1;

            $gat = $l['gatilho_emocional'] ?: 'não identificado';
            $porGatilho[$gat] = ($porGatilho[$gat] ?? 0) + 1;

            $comp = $l['complexidade'] ?: 'não avaliado';
            $porComplexidade[$comp] = ($porComplexidade[$comp] ?? 0) + 1;

            $urg = $l['urgencia'] ?: 'não avaliado';
            $porUrgencia[$urg] = ($porUrgencia[$urg] ?? 0) + 1;

            $perf = $l['perfil_socioeconomico'] ?: 'não avaliado';
            $porPerfil[$perf] = ($porPerfil[$perf] ?? 0) + 1;

            // Leads com resumo de demanda para análise qualitativa
            if (!empty($l['resumo_demanda'])) {
                $leadsDemanda[] = [
                    'area'         => $area,
                    'sub_area'     => $l['sub_area'],
                    'resumo'       => substr($l['resumo_demanda'], 0, 300),
                    'gatilho'      => $l['gatilho_emocional'],
                    'potencial'    => $l['potencial_honorarios'],
                    'intencao'     => $l['intencao_contratar'],
                    'complexidade' => $l['complexidade'],
                    'urgencia'     => $l['urgencia'],
                    'canal'        => $l['origem_canal'],
                    'perfil'       => $l['perfil_socioeconomico'],
                    'data'         => Carbon::parse($l['created_at'])->format('d/m'),
                ];
            }
        }

        // Qualidade dos leads: intenção "sim" = alto valor
        $altaIntencao = ($porIntencao['sim'] ?? 0);
        $mediaIntencao = ($porIntencao['talvez'] ?? 0);
        $taxaConversaoEstimada = $total > 0
            ? round((($altaIntencao + $mediaIntencao * 0.3) / $total) * 100, 1)
            : 0;

        // UTM / campanhas
        $campanhas = DB::table('leads')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->whereNotNull('utm_campaign')
            ->where('utm_campaign', '!=', '')
            ->select('utm_campaign', DB::raw('count(*) as total'))
            ->groupBy('utm_campaign')
            ->orderByDesc('total')
            ->take(10)
            ->pluck('total', 'utm_campaign')
            ->toArray();

        // Conversão para cliente: leads que viraram conta CRM
        $convertidos = DB::table('leads')
            ->whereBetween('created_at', [$inicioStr, $fimStr])
            ->whereNotNull('cliente_id')
            ->count();

        arsort($porArea);
        arsort($porCanal);
        arsort($porGatilho);

        return [
            'total'                    => $total,
            'convertidos_para_cliente' => $convertidos,
            'taxa_conversao_estimada'  => $taxaConversaoEstimada,
            'por_area'                 => $porArea,
            'por_canal'                => $porCanal,
            'por_potencial_honorarios' => $porPotencial,
            'por_intencao_contratar'   => $porIntencao,
            'por_gatilho_emocional'    => $porGatilho,
            'por_complexidade'         => $porComplexidade,
            'por_urgencia'             => $porUrgencia,
            'por_perfil_socioeconomico'=> $porPerfil,
            'campanhas_utm'            => $campanhas,
            'leads_com_demanda'        => $leadsDemanda,
        ];
    }
}
