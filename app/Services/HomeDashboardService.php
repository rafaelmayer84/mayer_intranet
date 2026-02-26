<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HomeDashboardService
{
    public function buscarGlobal(string $query, int $limit = 15): array
    {
        $q = '%' . $query . '%';
        $results = [];

        try {
            $clientes = DB::table('clientes')
                ->where(function ($qb) use ($q) {
                    $qb->where('nome', 'LIKE', $q)
                        ->orWhere('cpf_cnpj', 'LIKE', $q)
                        ->orWhere('email', 'LIKE', $q)
                        ->orWhere('telefone', 'LIKE', $q);
                })
                ->select('id', 'datajuri_id', 'nome', 'cpf_cnpj', 'email', 'telefone')
                ->orderBy('nome')->limit($limit)->get();
            foreach ($clientes as $c) {
                $results[] = [
                    'tipo' => 'cliente', 'badge' => 'Cliente',
                    'badge_cor' => 'bg-blue-100 text-blue-800',
                    'icon' => 'fa-solid fa-user',
                    'titulo' => $c->nome,
                    'subtitulo' => $c->cpf_cnpj ?: ($c->email ?: ''),
                    'url' => (function() use ($c) {
                        if ($c->datajuri_id) {
                            $crmAcc = \Illuminate\Support\Facades\DB::table('crm_accounts')
                                ->where('datajuri_pessoa_id', $c->datajuri_id)
                                ->select('id')->first();
                            if ($crmAcc) return '/crm/accounts/' . $crmAcc->id;
                        }
                        return '/crm/carteira';
                    })(),
                ];
            }
        } catch (\Throwable $e) { Log::warning('Home busca clientes: ' . $e->getMessage()); }

        try {
            $processos = DB::table('processos')
                ->where(function ($qb) use ($q) {
                    $qb->where('numero_processo', 'LIKE', $q)
                        ->orWhere('titulo', 'LIKE', $q)
                        ->orWhere('parte_contraria', 'LIKE', $q);
                })
                ->select('id', 'numero_processo', 'titulo', 'status', 'parte_contraria')
                ->orderByDesc('id')->limit($limit)->get();
            foreach ($processos as $p) {
                $results[] = [
                    'tipo' => 'processo', 'badge' => 'Processo',
                    'badge_cor' => 'bg-purple-100 text-purple-800',
                    'icon' => 'fa-solid fa-gavel',
                    'titulo' => $p->numero_processo,
                    'subtitulo' => mb_substr($p->titulo ?: ($p->parte_contraria ?: ''), 0, 60),
                    'url' => (function() use ($p) {
                        if (isset($p->cliente_id) && $p->cliente_id) {
                            $cli = \Illuminate\Support\Facades\DB::table('clientes')
                                ->where('id', $p->cliente_id)->select('datajuri_id')->first();
                            if ($cli && $cli->datajuri_id) {
                                $crmAcc = \Illuminate\Support\Facades\DB::table('crm_accounts')
                                    ->where('datajuri_pessoa_id', $cli->datajuri_id)
                                    ->select('id')->first();
                                if ($crmAcc) return '/crm/accounts/' . $crmAcc->id;
                            }
                        }
                        return '/processos';
                    })(),
                ];
            }
        } catch (\Throwable $e) { Log::warning('Home busca processos: ' . $e->getMessage()); }

        try {
            $accounts = DB::table('crm_accounts')
                ->where('name', 'LIKE', $q)
                ->select('id', 'name', 'lifecycle')
                ->orderBy('name')->limit($limit)->get();
            foreach ($accounts as $a) {
                $label = ($a->lifecycle === 'client') ? 'Cliente' : 'Prospect';
                $cor = ($a->lifecycle === 'client') ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800';
                $results[] = [
                    'tipo' => 'crm', 'badge' => $label, 'badge_cor' => $cor,
                    'icon' => 'fa-solid fa-building',
                    'titulo' => $a->name, 'subtitulo' => ucfirst($a->lifecycle ?? ''),
                    'url' => '/crm/accounts/' . $a->id,
                ];
            }
        } catch (\Throwable $e) { Log::warning('Home busca CRM: ' . $e->getMessage()); }

        try {
            $leads = DB::table('leads')
                ->where(function ($qb) use ($q) {
                    $qb->where('nome', 'LIKE', $q)->orWhere('telefone', 'LIKE', $q);
                })
                ->select('id', 'nome', 'telefone', 'status')
                ->orderByDesc('id')->limit($limit)->get();
            foreach ($leads as $l) {
                $results[] = [
                    'tipo' => 'lead', 'badge' => 'Lead',
                    'badge_cor' => 'bg-orange-100 text-orange-800',
                    'icon' => 'fa-solid fa-bullhorn',
                    'titulo' => $l->nome ?: $l->telefone,
                    'subtitulo' => $l->telefone ?? '',
                    'url' => '/crm/leads',
                ];
            }
        } catch (\Throwable $e) { Log::warning('Home busca leads: ' . $e->getMessage()); }

        return $results;
    }

    public function getGdpScore(int $userId): ?array
    {
        try {
            $ciclo = DB::table('gdp_ciclos')->where('status', 'aberto')->first();
            if (!$ciclo) return null;

            $snapshot = DB::table('gdp_snapshots')
                ->where('user_id', $userId)
                ->where('ciclo_id', $ciclo->id)
                ->orderByDesc('ano')->orderByDesc('mes')
                ->first();
            if (!$snapshot) return null;

            $anterior = DB::table('gdp_snapshots')
                ->where('user_id', $userId)
                ->where('ciclo_id', $ciclo->id)
                ->where(function ($q) use ($snapshot) {
                    $q->where('ano', '<', $snapshot->ano)
                      ->orWhere(function ($q2) use ($snapshot) {
                          $q2->where('ano', $snapshot->ano)
                             ->where('mes', '<', $snapshot->mes);
                      });
                })
                ->orderByDesc('ano')->orderByDesc('mes')->first();

            $totalPart = DB::table('gdp_snapshots')
                ->where('ciclo_id', $ciclo->id)
                ->where('mes', $snapshot->mes)->where('ano', $snapshot->ano)
                ->count();

            $variacao = ($anterior && isset($anterior->score_total) && $anterior->score_total > 0)
                ? round($snapshot->score_total - $anterior->score_total, 1) : null;

            return [
                'score_total'             => round($snapshot->score_total ?? 0, 1),
                'ranking'                 => $snapshot->ranking ?? null,
                'total_participantes'     => $totalPart,
                'mes_ref'                 => str_pad($snapshot->mes, 2, '0', STR_PAD_LEFT) . '/' . $snapshot->ano,
                'variacao'                => $variacao,
                'score_juridico'          => round($snapshot->score_juridico ?? 0, 1),
                'score_financeiro'        => round($snapshot->score_financeiro ?? 0, 1),
                'score_desenvolvimento'   => round($snapshot->score_desenvolvimento ?? 0, 1),
                'score_atendimento'       => round($snapshot->score_atendimento ?? 0, 1),
            ];
        } catch (\Throwable $e) {
            Log::warning('Home GDP: ' . $e->getMessage());
            return null;
        }
    }

    public function getAlertasCrm(int $userId): array
    {
        $alertas = [];
        try {
            if (class_exists(\App\Services\CrmProactiveService::class)) {
                $service = app(\App\Services\CrmProactiveService::class);
                if (method_exists($service, 'getAlerts')) {
                    $result = $service->getAlerts($userId);
                    if (is_array($result) && count($result) > 0) return array_slice($result, 0, 8);
                }
            }
        } catch (\Throwable $e) {}

        try {
            $semContato = DB::table('crm_accounts')
                ->where('lifecycle', 'client')
                ->where(function ($q) {
                    $q->where('last_touch_at', '<', Carbon::now()->subDays(30))
                      ->orWhereNull('last_touch_at');
                })
                ->select('id', 'name', 'last_touch_at')
                ->orderBy('last_touch_at')->limit(5)->get();

            foreach ($semContato as $c) {
                $dias = $c->last_touch_at ? Carbon::parse($c->last_touch_at)->diffInDays(now()) : '30+';
                $alertas[] = [
                    'tipo' => 'sem_contato', 'icon' => 'fa-solid fa-user-clock',
                    'cor' => 'text-amber-600', 'titulo' => $c->name,
                    'descricao' => "Sem contato ha {$dias} dias",
                    'url' => '/crm/accounts/' . $c->id,
                ];
            }
        } catch (\Throwable $e) { Log::warning('Home alertas CRM: ' . $e->getMessage()); }
        return $alertas;
    }

    public function getTicketsAbertos(int $userId): array
    {
        try {
            $tickets = DB::table('nexo_tickets')
                ->where(function ($q) use ($userId) {
                    $q->where('responsavel_id', $userId);
                })
                ->whereIn('status', ['aberto', 'pendente', 'open', 'pending', 'em_andamento'])
                ->select('id', 'assunto', 'status', 'prioridade', 'created_at')
                ->orderByDesc('created_at')->limit(8)->get();
            return $tickets->map(fn($t) => [
                'id' => $t->id, 'titulo' => $t->assunto ?: ('Ticket #' . $t->id),
                'status' => $t->status, 'prioridade' => $t->prioridade ?? 'normal',
                'criado_em' => Carbon::parse($t->created_at)->format('d/m H:i'),
            ])->toArray();
        } catch (\Throwable $e) {
            Log::warning('Home tickets: ' . $e->getMessage());
            return [];
        }
    }

    public function getResumoFinanceiro(): array
    {
        try {
            $calc = app(\App\Services\FinanceiroCalculatorService::class);
            $mesAtual = Carbon::now()->month;
            $anoAtual = Carbon::now()->year;
            $dre = $calc->dre($anoAtual, $mesAtual);

            $mesAnt = $mesAtual - 1 ?: 12;
            $anoAnt = $mesAtual == 1 ? $anoAtual - 1 : $anoAtual;
            $dreAnt = $calc->dre($anoAnt, $mesAnt);

            $receita = $dre['receita_total'];
            $despesa = $dre['despesas'];
            $resultado = $dre['resultado'];
            $margem = $receita > 0 ? round(($resultado / $receita) * 100, 1) : 0;
            $varReceita = $dreAnt['receita_total'] > 0 ? round((($receita - $dreAnt['receita_total']) / $dreAnt['receita_total']) * 100, 1) : null;

            return [
                'receita'     => $receita,
                'despesa'     => $despesa,
                'resultado'   => $resultado,
                'margem'      => $margem,
                'var_receita' => $varReceita,
            ];
        } catch (\Throwable $e) {
            Log::warning('Home financeiro: ' . $e->getMessage());
            return ['receita' => 0, 'despesa' => 0, 'resultado' => 0, 'margem' => 0, 'var_receita' => null];
        }
    }

    public function getAvisosNaoLidos(int $userId): array
    {
        try {
            $naoLidos = DB::table('avisos')
                ->leftJoin('avisos_lidos', function ($join) use ($userId) {
                    $join->on('avisos.id', '=', 'avisos_lidos.aviso_id')
                         ->where('avisos_lidos.user_id', '=', $userId);
                })
                ->whereNull('avisos_lidos.id')
                ->select('avisos.id', 'avisos.titulo', 'avisos.prioridade', 'avisos.created_at', 'avisos.destaque')
                ->orderByDesc('avisos.destaque')->orderByDesc('avisos.created_at')
                ->limit(5)->get();

            $total = DB::table('avisos')
                ->leftJoin('avisos_lidos', function ($join) use ($userId) {
                    $join->on('avisos.id', '=', 'avisos_lidos.aviso_id')
                         ->where('avisos_lidos.user_id', '=', $userId);
                })
                ->whereNull('avisos_lidos.id')->count();

            return [
                'total' => $total,
                'avisos' => $naoLidos->map(fn($a) => [
                    'id' => $a->id, 'titulo' => $a->titulo,
                    'prioridade' => $a->prioridade ?? 'normal',
                    'destaque' => $a->destaque ?? false,
                    'data' => Carbon::parse($a->created_at)->format('d/m'),
                ])->toArray(),
            ];
        } catch (\Throwable $e) {
            Log::warning('Home avisos: ' . $e->getMessage());
            return ['total' => 0, 'avisos' => []];
        }
    }

    public function getVolumetria(): array
    {
        try {
            return [
                'clientes'      => DB::table('clientes')->count(),
                'processos'     => DB::table('processos')->where('status', 'Em andamento')->count(),
                'oportunidades' => DB::table('crm_opportunities')->count(),
                'leads'         => DB::table('leads')->count(),
            ];
        } catch (\Throwable $e) {
            return ['clientes' => 0, 'processos' => 0, 'oportunidades' => 0, 'leads' => 0];
        }
    }
}
