<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CrmPainelService
{
    // ══════════════════════════════════════════════════
    // SEÇÃO 1 — KPIs DE CARTEIRA
    // ══════════════════════════════════════════════════

    public function getKpisCarteira(): array
    {
        $porLifecycle = DB::table('crm_accounts')
            ->select('lifecycle', DB::raw('COUNT(*) as total'))
            ->groupBy('lifecycle')
            ->pluck('total', 'lifecycle')
            ->toArray();

        $porOwner = DB::table('crm_accounts')
            ->leftJoin('users', 'crm_accounts.owner_user_id', '=', 'users.id')
            ->select(
                DB::raw('COALESCE(users.name, "Sem responsável") as owner_name'),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('users.name')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        $semOwner = DB::table('crm_accounts')
            ->whereNull('owner_user_id')
            ->count();

        $healthScoreStats = DB::table('crm_accounts')
            ->whereNotNull('health_score')
            ->selectRaw('
                AVG(health_score) as media,
                SUM(CASE WHEN health_score >= 80 THEN 1 ELSE 0 END) as excelente,
                SUM(CASE WHEN health_score >= 60 AND health_score < 80 THEN 1 ELSE 0 END) as bom,
                SUM(CASE WHEN health_score >= 40 AND health_score < 60 THEN 1 ELSE 0 END) as atencao,
                SUM(CASE WHEN health_score >= 20 AND health_score < 40 THEN 1 ELSE 0 END) as critico,
                SUM(CASE WHEN health_score < 20 THEN 1 ELSE 0 END) as perdido,
                COUNT(*) as total_com_score
            ')
            ->first();

        $totalContas = DB::table('crm_accounts')->count();

        return [
            'total_contas'     => $totalContas,
            'por_lifecycle'    => $porLifecycle,
            'por_owner'        => $porOwner,
            'sem_owner'        => $semOwner,
            'health_score'     => $healthScoreStats,
        ];
    }

    // ══════════════════════════════════════════════════
    // SEÇÃO 2 — PIPELINE + FORECAST
    // ══════════════════════════════════════════════════

    public function getPipelineForecast(): array
    {
        $probabilidades = [
            1 => 0.10, // Lead Novo
            2 => 0.25, // Em Contato
            3 => 0.50, // Proposta
            4 => 0.75, // Negociação
        ];

        $oportunidadesAbertas = DB::table('crm_opportunities')
            ->join('crm_stages', 'crm_opportunities.stage_id', '=', 'crm_stages.id')
            ->leftJoin('crm_accounts', 'crm_opportunities.account_id', '=', 'crm_accounts.id')
            ->leftJoin('users', 'crm_opportunities.owner_user_id', '=', 'users.id')
            ->where('crm_opportunities.status', 'open')
            ->where('crm_stages.is_won', false)
            ->where('crm_stages.is_lost', false)
            ->select(
                'crm_opportunities.id',
                'crm_opportunities.title',
                'crm_opportunities.value_estimated',
                'crm_opportunities.stage_id',
                'crm_opportunities.updated_at',
                'crm_stages.name as stage_name',
                'crm_accounts.name as account_name',
                'users.name as owner_name'
            )
            ->orderBy('crm_stages.order')
            ->get();

        $forecastTotal = 0;
        $porStage = [];

        foreach ($oportunidadesAbertas as $op) {
            $prob = $probabilidades[$op->stage_id] ?? 0.10;
            $valorPonderado = ($op->value_estimated ?? 0) * $prob;
            $forecastTotal += $valorPonderado;

            $op->probabilidade = $prob;
            $op->valor_ponderado = $valorPonderado;

            if (!isset($porStage[$op->stage_name])) {
                $porStage[$op->stage_name] = [
                    'stage'            => $op->stage_name,
                    'probabilidade'    => $prob,
                    'quantidade'       => 0,
                    'valor_estimado'   => 0,
                    'valor_ponderado'  => 0,
                ];
            }
            $porStage[$op->stage_name]['quantidade']++;
            $porStage[$op->stage_name]['valor_estimado'] += ($op->value_estimated ?? 0);
            $porStage[$op->stage_name]['valor_ponderado'] += $valorPonderado;
        }

        // Resumo won/lost para contexto
        $resumoHistorico = DB::table('crm_opportunities')
            ->selectRaw('
                SUM(CASE WHEN status = "won" THEN 1 ELSE 0 END) as total_won,
                SUM(CASE WHEN status = "lost" THEN 1 ELSE 0 END) as total_lost,
                SUM(CASE WHEN status = "won" THEN COALESCE(value_closed, value_estimated, 0) ELSE 0 END) as receita_won,
                SUM(CASE WHEN status = "lost" THEN COALESCE(value_estimated, 0) ELSE 0 END) as valor_lost
            ')
            ->first();

        return [
            'oportunidades'      => $oportunidadesAbertas,
            'por_stage'          => array_values($porStage),
            'forecast_total'     => $forecastTotal,
            'total_abertas'      => $oportunidadesAbertas->count(),
            'historico'          => $resumoHistorico,
        ];
    }

    // ══════════════════════════════════════════════════
    // SEÇÃO 3 — ATIVIDADE DA SEMANA
    // ══════════════════════════════════════════════════

    public function getAtividadeSemana(): array
    {
        $inicioSemana = Carbon::now('America/Sao_Paulo')->startOfWeek(Carbon::MONDAY)->startOfDay();
        $fimSemana = Carbon::now('America/Sao_Paulo')->endOfWeek(Carbon::SUNDAY)->endOfDay();

        $porUsuario = DB::table('crm_activities')
            ->join('users', 'crm_activities.created_by_user_id', '=', 'users.id')
            ->whereBetween('crm_activities.created_at', [$inicioSemana, $fimSemana])
            ->select(
                'users.name as user_name',
                'crm_activities.type',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('users.name', 'crm_activities.type')
            ->orderBy('users.name')
            ->get();

        // Agrupar por usuário
        $agrupado = [];
        $totalSemana = 0;
        foreach ($porUsuario as $row) {
            if (!isset($agrupado[$row->user_name])) {
                $agrupado[$row->user_name] = [
                    'user_name' => $row->user_name,
                    'tipos'     => [],
                    'total'     => 0,
                ];
            }
            $agrupado[$row->user_name]['tipos'][$row->type] = $row->total;
            $agrupado[$row->user_name]['total'] += $row->total;
            $totalSemana += $row->total;
        }

        // Por propósito (purpose)
        $porProposito = DB::table('crm_activities')
            ->whereBetween('created_at', [$inicioSemana, $fimSemana])
            ->whereNotNull('purpose')
            ->select('purpose', DB::raw('COUNT(*) as total'))
            ->groupBy('purpose')
            ->orderByDesc('total')
            ->get()
            ->toArray();

        return [
            'por_usuario'   => array_values($agrupado),
            'por_proposito' => $porProposito,
            'total_semana'  => $totalSemana,
            'periodo'       => $inicioSemana->format('d/m') . ' a ' . $fimSemana->format('d/m'),
        ];
    }

    // ══════════════════════════════════════════════════
    // SEÇÃO 4 — ALERTAS ACIONÁVEIS
    // ══════════════════════════════════════════════════

    public function getAlertas(): array
    {
        $alertas = [];

        // 4a) Health Score Crítico (≤39)
        $contasCriticas = DB::table('crm_accounts')
            ->leftJoin('users', 'crm_accounts.owner_user_id', '=', 'users.id')
            ->where('health_score', '<=', 39)
            ->whereNotNull('health_score')
            ->select(
                'crm_accounts.id',
                'crm_accounts.name',
                'crm_accounts.health_score',
                'crm_accounts.lifecycle',
                'crm_accounts.last_touch_at',
                'users.name as owner_name'
            )
            ->orderBy('health_score')
            ->limit(15)
            ->get();

        foreach ($contasCriticas as $conta) {
            $faixa = $conta->health_score < 20 ? 'Perdido' : 'Crítico';
            $alertas[] = [
                'tipo'       => 'health_score',
                'severidade' => $conta->health_score < 20 ? 'danger' : 'warning',
                'titulo'     => "{$conta->name} — Health Score {$faixa} ({$conta->health_score})",
                'detalhe'    => 'Responsável: ' . ($conta->owner_name ?? 'Sem responsável') .
                               ' | Último contato: ' . ($conta->last_touch_at ? Carbon::parse($conta->last_touch_at)->format('d/m/Y') : 'Nunca'),
                'link'       => url("/crm/accounts/{$conta->id}"),
                'icone'      => 'heart-pulse',
            ];
        }

        // 4b) Oportunidades paradas 15+ dias (sem stage_changed)
        $oportunidadesParadas = DB::table('crm_opportunities')
            ->join('crm_stages', 'crm_opportunities.stage_id', '=', 'crm_stages.id')
            ->leftJoin('crm_accounts', 'crm_opportunities.account_id', '=', 'crm_accounts.id')
            ->leftJoin('users', 'crm_opportunities.owner_user_id', '=', 'users.id')
            ->where('crm_opportunities.status', 'open')
            ->where('crm_stages.is_won', false)
            ->where('crm_stages.is_lost', false)
            ->where('crm_opportunities.updated_at', '<', Carbon::now()->subDays(15))
            ->select(
                'crm_opportunities.id',
                'crm_opportunities.title',
                'crm_opportunities.updated_at',
                'crm_stages.name as stage_name',
                'crm_accounts.name as account_name',
                'users.name as owner_name'
            )
            ->get();

        foreach ($oportunidadesParadas as $op) {
            $dias = Carbon::parse($op->updated_at)->diffInDays(Carbon::now());
            $alertas[] = [
                'tipo'       => 'oportunidade_parada',
                'severidade' => $dias > 30 ? 'danger' : 'warning',
                'titulo'     => ($op->account_name ?? 'Sem conta') . " — Oportunidade parada há {$dias} dias",
                'detalhe'    => "Stage: {$op->stage_name} | Responsável: " . ($op->owner_name ?? 'Sem responsável'),
                'link'       => url("/crm/pipeline"),
                'icone'      => 'clock',
            ];
        }

        // 4c) Inadimplência — contas_receber vencidas (DataJuri, is_stale=false)
        $inadimplentes = DB::table('contas_receber')
            ->join('clientes', 'contas_receber.cliente_datajuri_id', '=', 'clientes.datajuri_id')
            ->where('contas_receber.status', '!=', 'Concluído')
            ->where('contas_receber.status', '!=', 'Excluido')
            ->where(function ($q) {
                $q->where('contas_receber.is_stale', false)
                  ->orWhereNull('contas_receber.is_stale');
            })
            ->whereNotNull('contas_receber.dataVencimento')
            ->whereDate('contas_receber.dataVencimento', '<', Carbon::today())
            ->select(
                'clientes.nome as cliente_nome',
                'clientes.datajuri_id',
                DB::raw('COUNT(*) as titulos_vencidos'),
                DB::raw('SUM(contas_receber.valor) as total_vencido'),
                DB::raw('MIN(contas_receber.dataVencimento) as vencimento_mais_antigo')
            )
            ->groupBy('clientes.nome', 'clientes.datajuri_id')
            ->orderByDesc(DB::raw('SUM(contas_receber.valor)'))
            ->limit(10)
            ->get();

        foreach ($inadimplentes as $inad) {
            $diasAtraso = Carbon::parse($inad->vencimento_mais_antigo)->diffInDays(Carbon::now());
            $alertas[] = [
                'tipo'       => 'inadimplencia',
                'severidade' => $diasAtraso > 90 ? 'danger' : 'warning',
                'titulo'     => "{$inad->cliente_nome} — R$ " . number_format($inad->total_vencido, 2, ',', '.') . " vencido",
                'detalhe'    => "{$inad->titulos_vencidos} título(s) | Atraso desde " . Carbon::parse($inad->vencimento_mais_antigo)->format('d/m/Y') . " ({$diasAtraso} dias)",
                'link'       => url("/crm/accounts") . "?search=" . urlencode($inad->cliente_nome),
                'icone'      => 'banknotes',
            ];
        }

        // 4d) Leads quentes sem ação
        $leadsQuentes = DB::table('leads')
            ->where('classificacao_potencial', 'alto')
            ->where(function ($q) {
                $q->where('status', 'novo')
                  ->orWhere('status', 'qualificado');
            })
            ->select('id', 'nome', 'area_interesse', 'potencial_honorarios', 'data_entrada', 'status')
            ->orderBy('data_entrada')
            ->limit(10)
            ->get();

        foreach ($leadsQuentes as $lead) {
            $diasSemAcao = $lead->data_entrada ? Carbon::parse($lead->data_entrada)->diffInDays(Carbon::now()) : 0;
            $alertas[] = [
                'tipo'       => 'lead_quente',
                'severidade' => $diasSemAcao > 7 ? 'danger' : 'warning',
                'titulo'     => ($lead->nome ?? 'Lead sem nome') . " — Lead quente sem ação",
                'detalhe'    => "Área: " . ($lead->area_interesse ?? '—') .
                               " | Potencial: " . ($lead->potencial_honorarios ?? '—') .
                               " | Entrada: " . ($lead->data_entrada ? Carbon::parse($lead->data_entrada)->format('d/m/Y') : '—'),
                'link'       => url("/leads"),
                'icone'      => 'flame',
            ];
        }

        // Ordenar por severidade (danger primeiro)
        usort($alertas, function ($a, $b) {
            $order = ['danger' => 0, 'warning' => 1, 'info' => 2];
            return ($order[$a['severidade']] ?? 9) <=> ($order[$b['severidade']] ?? 9);
        });

        return [
            'alertas'        => $alertas,
            'total_alertas'  => count($alertas),
            'por_tipo'       => [
                'health_score'         => count(array_filter($alertas, fn($a) => $a['tipo'] === 'health_score')),
                'oportunidade_parada'  => count(array_filter($alertas, fn($a) => $a['tipo'] === 'oportunidade_parada')),
                'inadimplencia'        => count(array_filter($alertas, fn($a) => $a['tipo'] === 'inadimplencia')),
                'lead_quente'          => count(array_filter($alertas, fn($a) => $a['tipo'] === 'lead_quente')),
            ],
        ];
    }
}
