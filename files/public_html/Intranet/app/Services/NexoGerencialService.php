<?php

namespace App\Services;

use App\Models\NexoEscalaDiaria;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class NexoGerencialService
{
    // Constantes de direction (wa_messages)
    private const DIR_IN  = 1;
    private const DIR_OUT = 2;

    private string $tz;
    private int    $slaMinutos;
    private string $horaInicio;
    private string $horaFim;

    public function __construct()
    {
        $this->tz          = config('nexo.timezone', 'America/Sao_Paulo');
        $this->slaMinutos  = (int) config('nexo.sla_primeira_resposta_minutos', 10);
        $this->horaInicio  = config('nexo.horario_inicio', '09:00');
        $this->horaFim     = config('nexo.horario_fim', '18:00');
    }

    // ═══════════════════════════════════════════════════════
    //  PONTO DE ENTRADA — DADOS COMPLETOS DO PAINEL
    // ═══════════════════════════════════════════════════════

    public function getDadosPainel(array $filtros): array
    {
        $range          = $this->resolverPeriodo($filtros);
        $somenteJanela  = ($filtros['somente_janela'] ?? '1') === '1';

        $conversas  = $this->buscarConversas($range, $somenteJanela);
        $tickets    = $this->buscarTickets($range, $somenteJanela);
        $mensagens  = $this->buscarMensagens($range, $somenteJanela);

        return [
            'periodo'    => $range,
            'kpis'       => $this->calcularKpis($conversas, $tickets, $mensagens, $range),
            'graficos'   => $this->calcularGraficos($conversas, $tickets, $mensagens, $range),
            'escala'     => $this->calcularEscala($range, $somenteJanela),
        ];
    }

    // ═══════════════════════════════════════════════════════
    //  DRILL-DOWN — LISTA DE CONVERSAS/TICKETS PARA UM KPI
    // ═══════════════════════════════════════════════════════

    public function drillDown(string $tipo, array $filtros): array
    {
        $range         = $this->resolverPeriodo($filtros);
        $somenteJanela = ($filtros['somente_janela'] ?? '1') === '1';
        $data          = $filtros['data'] ?? null;

        return match ($tipo) {
            'entradas'           => $this->drillEntradas($range, $somenteJanela, $data),
            'resolvidos'         => $this->drillResolvidos($range, $somenteJanela, $data),
            'backlog'            => $this->drillBacklog(),
            'sla_estourado'      => $this->drillSlaEstourado($range, $somenteJanela, $data),
            'sla_cumprido'       => $this->drillSlaCumprido($range, $somenteJanela, $data),
            'sem_resposta'       => $this->drillSemResposta(),
            'tickets_abertos'    => $this->drillTicketsAbertos(),
            'tickets_resolvidos' => $this->drillTicketsResolvidos($range, $data),
            default              => [],
        };
    }

    // ═══════════════════════════════════════════════════════
    //  RESOLUÇÃO DE PERÍODO
    // ═══════════════════════════════════════════════════════

    private function resolverPeriodo(array $filtros): array
    {
        $agora = Carbon::now($this->tz);

        return match ($filtros['periodo'] ?? 'hoje') {
            'hoje'   => [
                'de'  => $agora->copy()->startOfDay()->utc(),
                'ate' => $agora->copy()->endOfDay()->utc(),
                'label' => 'Hoje',
            ],
            '7d'     => [
                'de'  => $agora->copy()->subDays(6)->startOfDay()->utc(),
                'ate' => $agora->copy()->endOfDay()->utc(),
                'label' => 'Últimos 7 dias',
            ],
            '30d'    => [
                'de'  => $agora->copy()->subDays(29)->startOfDay()->utc(),
                'ate' => $agora->copy()->endOfDay()->utc(),
                'label' => 'Últimos 30 dias',
            ],
            'custom' => [
                'de'  => Carbon::parse($filtros['de'] ?? $agora->toDateString(), $this->tz)->startOfDay()->utc(),
                'ate' => Carbon::parse($filtros['ate'] ?? $agora->toDateString(), $this->tz)->endOfDay()->utc(),
                'label' => 'Personalizado',
            ],
            default  => [
                'de'  => $agora->copy()->startOfDay()->utc(),
                'ate' => $agora->copy()->endOfDay()->utc(),
                'label' => 'Hoje',
            ],
        };
    }

    // ═══════════════════════════════════════════════════════
    //  BUSCA DE DADOS (SELECT ONLY — ZERO WRITES)
    // ═══════════════════════════════════════════════════════

    private function buscarConversas(array $range, bool $somenteJanela): Collection
    {
        $query = DB::table('wa_conversations')
            ->whereBetween('created_at', [$range['de'], $range['ate']]);

        if ($somenteJanela) {
            $query->whereRaw("TIME(CONVERT_TZ(created_at, '+00:00', '-03:00')) BETWEEN ? AND ?", [
                $this->horaInicio . ':00',
                $this->horaFim . ':00',
            ]);
        }

        return $query->get();
    }

    private function buscarTickets(array $range, bool $somenteJanela): Collection
    {
        $query = DB::table('nexo_tickets')
            ->whereBetween('created_at', [$range['de'], $range['ate']]);

        if ($somenteJanela) {
            $query->whereRaw("TIME(CONVERT_TZ(created_at, '+00:00', '-03:00')) BETWEEN ? AND ?", [
                $this->horaInicio . ':00',
                $this->horaFim . ':00',
            ]);
        }

        return $query->get();
    }

    private function buscarMensagens(array $range, bool $somenteJanela): Collection
    {
        $query = DB::table('wa_messages')
            ->whereBetween('created_at', [$range['de'], $range['ate']]);

        if ($somenteJanela) {
            $query->whereRaw("TIME(CONVERT_TZ(created_at, '+00:00', '-03:00')) BETWEEN ? AND ?", [
                $this->horaInicio . ':00',
                $this->horaFim . ':00',
            ]);
        }

        return $query->get();
    }

    // ═══════════════════════════════════════════════════════
    //  CÁLCULO DE KPIs
    // ═══════════════════════════════════════════════════════

    private function calcularKpis(Collection $conversas, Collection $tickets, Collection $mensagens, array $range): array
    {
        // --- Conversas ---
        $totalEntradas    = $conversas->count();
        $comResposta      = $conversas->whereNotNull('first_response_at');
        $temposResposta   = $comResposta->map(function ($c) {
            return Carbon::parse($c->first_response_at)->diffInSeconds(Carbon::parse($c->created_at));
        })->filter(fn($s) => $s > 0)->sort()->values();

        $medianaResp    = $this->mediana($temposResposta);
        $p90Resp        = $this->percentil($temposResposta, 90);

        // SLA: primeira resposta humana dentro do limite configurado
        $slaCumpridoCount = $comResposta->filter(function ($c) {
            $segs = Carbon::parse($c->first_response_at)->diffInSeconds(Carbon::parse($c->created_at));
            return $segs <= ($this->slaMinutos * 60);
        })->count();

        $taxaSla = $totalEntradas > 0 ? round(($slaCumpridoCount / $totalEntradas) * 100, 1) : 0;

        // Backlog atual (abertos agora, independente de período)
        $backlog = DB::table('wa_conversations')->where('status', 'open')->count();

        // Sem resposta humana
        $semResposta = $conversas->whereNull('first_response_at')->count();

        // Resolvidos (fechados no período)
        $resolvidos = DB::table('wa_conversations')
            ->where('status', 'closed')
            ->whereBetween('updated_at', [$range['de'], $range['ate']])
            ->count();

        // --- Tickets ---
        $ticketsAbertos    = $tickets->count();
        $ticketsResolvidos = $tickets->where('status', 'resolvido')->count();
        $ticketsPendentes  = DB::table('nexo_tickets')->whereNotIn('status', ['resolvido', 'cancelado'])->count();

        // Tempo de resolução de tickets
        $temposResolucao = $tickets->whereNotNull('resolvido_at')->map(function ($t) {
            return Carbon::parse($t->resolvido_at)->diffInSeconds(Carbon::parse($t->created_at));
        })->filter(fn($s) => $s > 0)->sort()->values();

        $medianaResolucao = $this->mediana($temposResolucao);
        $p90Resolucao     = $this->percentil($temposResolucao, 90);

        // --- Mensagens ---
        $totalMensagens  = $mensagens->count();
        $msgsHumanas     = $mensagens->where('direction', self::DIR_OUT)->where('is_human', 1)->count();
        $msgsBot         = $mensagens->where('direction', self::DIR_OUT)->where('is_human', 0)->count();
        $msgsCliente     = $mensagens->where('direction', self::DIR_IN)->count();

        return [
            'total_entradas'          => $totalEntradas,
            'resolvidos'              => $resolvidos,
            'backlog'                 => $backlog,
            'sem_resposta'            => $semResposta,
            'mediana_primeira_resp'   => $this->formatarTempo($medianaResp),
            'p90_primeira_resp'       => $this->formatarTempo($p90Resp),
            'sla_cumprido'            => $slaCumpridoCount,
            'sla_estourado'           => $totalEntradas - $slaCumpridoCount,
            'taxa_sla'                => $taxaSla,
            'sla_limite_min'          => $this->slaMinutos,
            'tickets_abertos'         => $ticketsAbertos,
            'tickets_resolvidos'      => $ticketsResolvidos,
            'tickets_pendentes'       => $ticketsPendentes,
            'mediana_resolucao'       => $this->formatarTempo($medianaResolucao),
            'p90_resolucao'           => $this->formatarTempo($p90Resolucao),
            'total_mensagens'         => $totalMensagens,
            'msgs_humanas'            => $msgsHumanas,
            'msgs_bot'                => $msgsBot,
            'msgs_cliente'            => $msgsCliente,
        ];
    }

    // ═══════════════════════════════════════════════════════
    //  GRÁFICOS
    // ═══════════════════════════════════════════════════════

    private function calcularGraficos(Collection $conversas, Collection $tickets, Collection $mensagens, array $range): array
    {
        // Volume por hora (heatmap)
        $volumePorHora = [];
        for ($h = 0; $h < 24; $h++) {
            $volumePorHora[$h] = 0;
        }
        foreach ($conversas as $c) {
            $hora = (int) Carbon::parse($c->created_at)->setTimezone($this->tz)->format('H');
            $volumePorHora[$hora]++;
        }

        // Volume por dia
        $volumePorDia = [];
        $de  = Carbon::parse($range['de'])->startOfDay();
        $ate = Carbon::parse($range['ate'])->startOfDay();
        $period = CarbonPeriod::create($de, $ate);
        foreach ($period as $dia) {
            $label = $dia->format('d/m');
            $volumePorDia[$label] = [
                'entradas'   => 0,
                'resolvidos' => 0,
                'msgs_humanas' => 0,
                'msgs_bot'     => 0,
                'msgs_cliente' => 0,
            ];
        }

        foreach ($conversas as $c) {
            $label = Carbon::parse($c->created_at)->setTimezone($this->tz)->format('d/m');
            if (isset($volumePorDia[$label])) {
                $volumePorDia[$label]['entradas']++;
            }
        }

        foreach ($mensagens as $m) {
            $label = Carbon::parse($m->created_at)->setTimezone($this->tz)->format('d/m');
            if (isset($volumePorDia[$label])) {
                if ($m->direction == self::DIR_OUT && $m->is_human) {
                    $volumePorDia[$label]['msgs_humanas']++;
                } elseif ($m->direction == self::DIR_OUT) {
                    $volumePorDia[$label]['msgs_bot']++;
                } else {
                    $volumePorDia[$label]['msgs_cliente']++;
                }
            }
        }

        // SLA por dia
        $slaPorDia = [];
        foreach ($conversas as $c) {
            $label = Carbon::parse($c->created_at)->setTimezone($this->tz)->format('d/m');
            if (!isset($slaPorDia[$label])) {
                $slaPorDia[$label] = ['cumprido' => 0, 'estourado' => 0, 'sem_resp' => 0];
            }
            if (!$c->first_response_at) {
                $slaPorDia[$label]['sem_resp']++;
            } else {
                $segs = Carbon::parse($c->first_response_at)->diffInSeconds(Carbon::parse($c->created_at));
                if ($segs <= ($this->slaMinutos * 60)) {
                    $slaPorDia[$label]['cumprido']++;
                } else {
                    $slaPorDia[$label]['estourado']++;
                }
            }
        }

        return [
            'volume_por_hora' => $volumePorHora,
            'volume_por_dia'  => $volumePorDia,
            'sla_por_dia'     => $slaPorDia,
        ];
    }

    // ═══════════════════════════════════════════════════════
    //  BLOCO ESCALA — CRUZAMENTO DE KPIs POR DIA/RESPONSÁVEL
    // ═══════════════════════════════════════════════════════

    private function calcularEscala(array $range, bool $somenteJanela): array
    {
        $de  = Carbon::parse($range['de'])->startOfDay();
        $ate = Carbon::parse($range['ate'])->startOfDay();

        $escalas = NexoEscalaDiaria::with('usuario:id,name')
            ->whereBetween('data', [$de->toDateString(), $ate->toDateString()])
            ->orderBy('data')
            ->get();

        $resultado = [];

        foreach ($escalas as $esc) {
            $diaInicio = Carbon::parse($esc->data->format('Y-m-d') . ' ' . $esc->inicio, $this->tz)->utc();
            $diaFim    = Carbon::parse($esc->data->format('Y-m-d') . ' ' . $esc->fim, $this->tz)->utc();

            // Conversas iniciadas na janela do dia
            $convsJanela = DB::table('wa_conversations')
                ->whereBetween('created_at', [$diaInicio, $diaFim])
                ->get();

            $entradas = $convsJanela->count();

            // SLA
            $slaCumprido = 0;
            $slaEstourado = 0;
            $tempos = [];
            foreach ($convsJanela as $c) {
                if ($c->first_response_at) {
                    $segs = Carbon::parse($c->first_response_at)->diffInSeconds(Carbon::parse($c->created_at));
                    $tempos[] = $segs;
                    if ($segs <= ($this->slaMinutos * 60)) {
                        $slaCumprido++;
                    } else {
                        $slaEstourado++;
                    }
                } else {
                    $slaEstourado++;
                }
            }
            sort($tempos);

            // Pendências herdadas às 09:00 (abertos antes do início da janela)
            $herdadas = DB::table('wa_conversations')
                ->where('status', 'open')
                ->where('created_at', '<', $diaInicio)
                ->where(function ($q) use ($diaInicio) {
                    $q->whereNull('updated_at')
                      ->orWhere('updated_at', '>=', $diaInicio);
                })
                ->count();

            // Pendências deixadas às 18:00 (abertos ao fim da janela)
            $deixadas = DB::table('wa_conversations')
                ->where('created_at', '<=', $diaFim)
                ->where(function ($q) use ($diaFim) {
                    $q->where('status', 'open')
                      ->orWhere(function ($q2) use ($diaFim) {
                          $q2->where('status', 'closed')
                             ->where('updated_at', '>', $diaFim);
                      });
                })
                ->where('status', 'open')
                ->count();

            // Exceções: mensagens humanas por outro usuário
            $msgsOutrasUser = 0;
            $msgsDoEscalado = 0;
            if ($esc->user_id) {
                $convsIds = $convsJanela->pluck('id')->toArray();
                if (!empty($convsIds)) {
                    $msgsHumanas = DB::table('wa_messages')
                        ->whereIn('conversation_id', $convsIds)
                        ->where('direction', self::DIR_OUT)
                        ->where('is_human', 1)
                        ->whereBetween('created_at', [$diaInicio, $diaFim])
                        ->get();

                    // Precisamos verificar quem enviou — wa_messages não tem user_id diretamente
                    // Usamos assigned_user_id da conversa como proxy do responsável
                    // Exceção = conversa com assigned_user_id diferente do escalado
                    foreach ($convsJanela as $c) {
                        $temMsgHumana = $msgsHumanas->where('conversation_id', $c->id)->count();
                        if ($temMsgHumana > 0 && $c->assigned_user_id && $c->assigned_user_id != $esc->user_id) {
                            $msgsOutrasUser++;
                        } elseif ($temMsgHumana > 0) {
                            $msgsDoEscalado++;
                        }
                    }
                }
            }

            $resultado[] = [
                'data'             => $esc->data->format('d/m/Y'),
                'data_iso'         => $esc->data->format('Y-m-d'),
                'responsavel'      => $esc->usuario?->name ?? 'Não definido',
                'responsavel_id'   => $esc->user_id,
                'horario'          => $esc->inicio . ' - ' . $esc->fim,
                'entradas'         => $entradas,
                'sla_cumprido'     => $slaCumprido,
                'sla_estourado'    => $slaEstourado,
                'mediana_resp'     => $this->formatarTempo($this->mediana(collect($tempos))),
                'p90_resp'         => $this->formatarTempo($this->percentil(collect($tempos), 90)),
                'herdadas_inicio'  => $herdadas,
                'deixadas_fim'     => $deixadas,
                'atendido_escalado'     => $msgsDoEscalado,
                'atendido_outro'        => $msgsOutrasUser,
                'observacao'       => $esc->observacao,
            ];
        }

        return $resultado;
    }

    // ═══════════════════════════════════════════════════════
    //  DRILL-DOWN IMPLEMENTATIONS
    // ═══════════════════════════════════════════════════════

    private function drillEntradas(array $range, bool $somenteJanela, ?string $data): array
    {
        $query = DB::table('wa_conversations')
            ->select('id', 'phone', 'name', 'status', 'assigned_user_id', 'created_at', 'first_response_at', 'last_message_at');

        if ($data) {
            $diaInicio = Carbon::parse($data, $this->tz)->startOfDay()->utc();
            $diaFim    = Carbon::parse($data, $this->tz)->endOfDay()->utc();
            $query->whereBetween('created_at', [$diaInicio, $diaFim]);
        } else {
            $query->whereBetween('created_at', [$range['de'], $range['ate']]);
        }

        return $query->orderByDesc('created_at')->limit(100)->get()->map(function ($c) {
            return $this->formatarConversaDrill($c);
        })->toArray();
    }

    private function drillResolvidos(array $range, bool $somenteJanela, ?string $data): array
    {
        $query = DB::table('wa_conversations')
            ->select('id', 'phone', 'name', 'status', 'assigned_user_id', 'created_at', 'first_response_at', 'updated_at')
            ->where('status', 'closed');

        if ($data) {
            $diaInicio = Carbon::parse($data, $this->tz)->startOfDay()->utc();
            $diaFim    = Carbon::parse($data, $this->tz)->endOfDay()->utc();
            $query->whereBetween('updated_at', [$diaInicio, $diaFim]);
        } else {
            $query->whereBetween('updated_at', [$range['de'], $range['ate']]);
        }

        return $query->orderByDesc('updated_at')->limit(100)->get()->map(function ($c) {
            return $this->formatarConversaDrill($c);
        })->toArray();
    }

    private function drillBacklog(): array
    {
        return DB::table('wa_conversations')
            ->select('id', 'phone', 'name', 'status', 'assigned_user_id', 'created_at', 'first_response_at', 'last_message_at')
            ->where('status', 'open')
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn($c) => $this->formatarConversaDrill($c))
            ->toArray();
    }

    private function drillSlaEstourado(array $range, bool $somenteJanela, ?string $data): array
    {
        return $this->drillSla($range, $data, false);
    }

    private function drillSlaCumprido(array $range, bool $somenteJanela, ?string $data): array
    {
        return $this->drillSla($range, $data, true);
    }

    private function drillSla(array $range, ?string $data, bool $cumprido): array
    {
        $query = DB::table('wa_conversations')
            ->select('id', 'phone', 'name', 'status', 'assigned_user_id', 'created_at', 'first_response_at');

        if ($data) {
            $diaInicio = Carbon::parse($data, $this->tz)->startOfDay()->utc();
            $diaFim    = Carbon::parse($data, $this->tz)->endOfDay()->utc();
            $query->whereBetween('created_at', [$diaInicio, $diaFim]);
        } else {
            $query->whereBetween('created_at', [$range['de'], $range['ate']]);
        }

        $convs = $query->orderByDesc('created_at')->limit(200)->get();

        $limiteSegs = $this->slaMinutos * 60;

        return $convs->filter(function ($c) use ($cumprido, $limiteSegs) {
            if (!$c->first_response_at) {
                return !$cumprido; // sem resposta = estourado
            }
            $segs = Carbon::parse($c->first_response_at)->diffInSeconds(Carbon::parse($c->created_at));
            return $cumprido ? ($segs <= $limiteSegs) : ($segs > $limiteSegs);
        })->take(100)->map(fn($c) => $this->formatarConversaDrill($c))->values()->toArray();
    }

    private function drillSemResposta(): array
    {
        return DB::table('wa_conversations')
            ->select('id', 'phone', 'name', 'status', 'assigned_user_id', 'created_at', 'last_incoming_at')
            ->where('status', 'open')
            ->whereNull('first_response_at')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($c) => $this->formatarConversaDrill($c))
            ->toArray();
    }

    private function drillTicketsAbertos(): array
    {
        return DB::table('nexo_tickets')
            ->whereNotIn('status', ['resolvido', 'cancelado'])
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn($t) => [
                'id'          => $t->id,
                'protocolo'   => $t->protocolo,
                'nome'        => $t->nome_cliente,
                'assunto'     => $t->assunto,
                'status'      => $t->status,
                'prioridade'  => $t->prioridade,
                'criado_em'   => Carbon::parse($t->created_at)->setTimezone($this->tz)->format('d/m/Y H:i'),
                'link'        => '/nexo/tickets/' . $t->id,
            ])
            ->toArray();
    }

    private function drillTicketsResolvidos(array $range, ?string $data): array
    {
        $query = DB::table('nexo_tickets')->where('status', 'resolvido');

        if ($data) {
            $diaInicio = Carbon::parse($data, $this->tz)->startOfDay()->utc();
            $diaFim    = Carbon::parse($data, $this->tz)->endOfDay()->utc();
            $query->whereBetween('resolvido_at', [$diaInicio, $diaFim]);
        } else {
            $query->whereBetween('resolvido_at', [$range['de'], $range['ate']]);
        }

        return $query->orderByDesc('resolvido_at')->limit(100)->get()->map(fn($t) => [
            'id'          => $t->id,
            'protocolo'   => $t->protocolo,
            'nome'        => $t->nome_cliente,
            'assunto'     => $t->assunto,
            'status'      => $t->status,
            'criado_em'   => Carbon::parse($t->created_at)->setTimezone($this->tz)->format('d/m/Y H:i'),
            'resolvido_em' => $t->resolvido_at ? Carbon::parse($t->resolvido_at)->setTimezone($this->tz)->format('d/m/Y H:i') : null,
            'link'        => '/nexo/tickets/' . $t->id,
        ])->toArray();
    }

    // ═══════════════════════════════════════════════════════
    //  ESCALA CRUD (dados para o controller)
    // ═══════════════════════════════════════════════════════

    public function listarEscala(string $mes): Collection
    {
        $inicio = Carbon::parse($mes . '-01');
        $fim    = $inicio->copy()->endOfMonth();

        return NexoEscalaDiaria::with('usuario:id,name')
            ->whereBetween('data', [$inicio, $fim])
            ->orderBy('data')
            ->get();
    }

    public function salvarEscala(array $dados): NexoEscalaDiaria
    {
        return NexoEscalaDiaria::updateOrCreate(
            ['data' => $dados['data']],
            [
                'user_id'    => $dados['user_id'],
                'inicio'     => $dados['inicio'] ?? $this->horaInicio,
                'fim'        => $dados['fim'] ?? $this->horaFim,
                'observacao' => $dados['observacao'] ?? null,
            ]
        );
    }

    public function excluirEscala(int $id): bool
    {
        return NexoEscalaDiaria::where('id', $id)->delete() > 0;
    }

    // ═══════════════════════════════════════════════════════
    //  HELPERS
    // ═══════════════════════════════════════════════════════

    private function formatarConversaDrill(object $c): array
    {
        $tempoResp = null;
        if ($c->first_response_at) {
            $segs = Carbon::parse($c->first_response_at)->diffInSeconds(Carbon::parse($c->created_at));
            $tempoResp = $this->formatarTempo($segs);
        }

        $responsavel = null;
        if ($c->assigned_user_id) {
            $responsavel = DB::table('users')->where('id', $c->assigned_user_id)->value('name');
        }

        return [
            'id'            => $c->id,
            'nome'          => $c->name ?? 'Sem nome',
            'telefone'      => $c->phone,
            'status'        => $c->status,
            'responsavel'   => $responsavel,
            'criado_em'     => Carbon::parse($c->created_at)->setTimezone($this->tz)->format('d/m/Y H:i'),
            'tempo_resposta' => $tempoResp,
            'link'          => '/nexo/atendimento?conv=' . $c->id,
        ];
    }

    private function mediana(Collection $valores): ?int
    {
        if ($valores->isEmpty()) {
            return null;
        }
        $sorted = $valores->sort()->values();
        $count  = $sorted->count();
        $mid    = intdiv($count, 2);

        if ($count % 2 === 0) {
            return (int) round(($sorted[$mid - 1] + $sorted[$mid]) / 2);
        }
        return (int) $sorted[$mid];
    }

    private function percentil(Collection $valores, int $p): ?int
    {
        if ($valores->isEmpty()) {
            return null;
        }
        $sorted = $valores->sort()->values();
        $index  = (int) ceil(($p / 100) * $sorted->count()) - 1;
        $index  = max(0, min($index, $sorted->count() - 1));

        return (int) $sorted[$index];
    }

    private function formatarTempo(?int $segundos): ?string
    {
        if ($segundos === null) {
            return null;
        }
        if ($segundos < 60) {
            return $segundos . 's';
        }
        if ($segundos < 3600) {
            $min = intdiv($segundos, 60);
            $sec = $segundos % 60;
            return $min . 'min ' . $sec . 's';
        }
        $hrs = intdiv($segundos, 3600);
        $min = intdiv($segundos % 3600, 60);
        return $hrs . 'h ' . $min . 'min';
    }
}
