<?php

namespace App\Services\Gdp;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GdpDataAdapter
{
    public function calcular(string $codigoIndicador, int $userId, int $mes, int $ano): ?float
    {
        $djPropId = $this->getDjPropId($userId);
        $precisaDj = !in_array($codigoIndicador, ['A1', 'A2', 'A3', 'A4', 'A5']);
        if ($precisaDj && !$djPropId) {
            return null;
        }

        $inicioMes = Carbon::createFromDate($ano, $mes, 1)->startOfDay();
        $fimMes    = $inicioMes->copy()->endOfMonth()->endOfDay();

        try {
            return match ($codigoIndicador) {
                'J1' => $this->j1ProcessosAtivos($djPropId),
                'J2' => $this->j2NovosProcessos($djPropId, $inicioMes, $fimMes),
                'J3' => $this->j3ProcessosEncerradosExito($djPropId, $inicioMes, $fimMes),
                'J4' => $this->j4PontualidadePrazos($djPropId, $inicioMes, $fimMes),
                'F1' => $this->f1ReceitaPontuavel($djPropId, $mes, $ano),
                'F2' => $this->f2ContratosQtd($djPropId, $inicioMes, $fimMes),
                'F3' => $this->f3ContratosValor($djPropId, $inicioMes, $fimMes),
                'D1' => $this->d1HorasTrabalhadas($djPropId, $inicioMes, $fimMes),
                'D2' => $this->d2AderenciaHoras($djPropId, $inicioMes, $fimMes),
                'A1' => $this->a1TempoRespostaWA($userId, $inicioMes, $fimMes),
                'A2' => $this->a2TaxaRespostaWA($userId, $inicioMes, $fimMes),
                'A3' => $this->a3ConversasSemResposta($userId, $inicioMes, $fimMes),
                'A4' => $this->a4TicketsResolvidos($userId, $inicioMes, $fimMes),
                'A5' => $this->a5TempoResolucaoTickets($userId, $inicioMes, $fimMes),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error("[GDP-Adapter] Erro {$codigoIndicador} user={$userId}: " . $e->getMessage());
            return null;
        }
    }

    private function j1ProcessosAtivos(int $djPropId): float
    {
        return (float) DB::table('processos')
            ->where('proprietario_id', $djPropId)
            ->where('status', 'Ativo')
            ->count();
    }

    private function j2NovosProcessos(int $djPropId, Carbon $inicio, Carbon $fim): float
    {
        return (float) DB::table('processos')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data_abertura', [$inicio->toDateString(), $fim->toDateString()])
            ->count();
    }

    private function j3ProcessosEncerradosExito(int $djPropId, Carbon $inicio, Carbon $fim): float
    {
        return (float) DB::table('processos')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data_encerramento', [$inicio->toDateString(), $fim->toDateString()])
            ->where('ganho_causa', 1)
            ->count();
    }

    private function j4PontualidadePrazos(int $djPropId, Carbon $inicio, Carbon $fim): ?float
    {
        try {
            $baseQuery = DB::table('atividades_datajuri')
                ->where('proprietario_id', $djPropId)
                ->whereNotNull('data_prazo_fatal')
                ->whereBetween('data_prazo_fatal', [$inicio->toDateString(), $fim->toDateString()]);

            $total = (clone $baseQuery)->count();
            if ($total === 0) {
                return null;
            }

            $noPrazo = (clone $baseQuery)
                ->whereNotNull('data_conclusao')
                ->whereColumn('data_conclusao', '<=', 'data_prazo_fatal')
                ->count();

            return round(($noPrazo / $total) * 100, 2);
        } catch (\Throwable $e) {
            Log::warning("[GDP-Adapter] J4 colunas ausentes: " . $e->getMessage());
            return null;
        }
    }

    private function f1ReceitaPontuavel(int $djPropId, int $mes, int $ano): float
    {
        $validado = DB::table('gdp_validacao_financeira as v')
            ->join('users as u', function ($join) use ($djPropId) {
                $join->on('u.id', '=', 'v.user_id_resolvido')
                     ->where('u.datajuri_proprietario_id', '=', $djPropId);
            })
            ->where('v.mes', $mes)
            ->where('v.ano', $ano)
            ->where('v.status_pontuacao', 'pontuavel')
            ->sum('v.valor');

        if ((float) $validado > 0) {
            return (float) $validado;
        }

        $inicioMes = Carbon::createFromDate($ano, $mes, 1)->toDateString();
        $fimMes    = Carbon::createFromDate($ano, $mes, 1)->endOfMonth()->toDateString();

        return (float) DB::table('movimentos')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data', [$inicioMes, $fimMes])
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->sum('valor');
    }

    private function f2ContratosQtd(int $djPropId, Carbon $inicio, Carbon $fim): float
    {
        return (float) DB::table('contratos')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data_assinatura', [$inicio->toDateString(), $fim->toDateString()])
            ->count();
    }

    private function f3ContratosValor(int $djPropId, Carbon $inicio, Carbon $fim): float
    {
        return (float) DB::table('contratos')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data_assinatura', [$inicio->toDateString(), $fim->toDateString()])
            ->sum('valor');
    }

    private function d1HorasTrabalhadas(int $djPropId, Carbon $inicio, Carbon $fim): float
    {
        $registros = DB::table('horas_trabalhadas_datajuri')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->pluck('total_hora_trabalhada');

        $totalMinutos = 0;
        foreach ($registros as $hhmm) {
            if (empty($hhmm)) {
                continue;
            }
            $parts = explode(':', (string) $hhmm);
            if (count($parts) >= 2) {
                $totalMinutos += ((int) $parts[0] * 60) + (int) $parts[1];
            }
        }

        return round($totalMinutos / 60, 2);
    }

    private function d2AderenciaHoras(int $djPropId, Carbon $inicio, Carbon $fim): float
    {
        $diasUteis = $this->contarDiasUteis($inicio, $fim);
        if ($diasUteis === 0) {
            return 0;
        }

        $diasComRegistro = DB::table('horas_trabalhadas_datajuri')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data', [$inicio->toDateString(), $fim->toDateString()])
            ->distinct('data')
            ->count('data');

        return round(($diasComRegistro / $diasUteis) * 100, 2);
    }

    private function a1TempoRespostaWA(int $userId, Carbon $inicio, Carbon $fim): ?float
    {
        try {
            $conversas = DB::table('wa_conversations')
                ->where('assigned_to', $userId)
                ->whereBetween('created_at', [$inicio, $fim])
                ->pluck('id');
        } catch (\Throwable $e) {
            Log::warning("[GDP-Adapter] A1 coluna assigned_to ausente: " . $e->getMessage());
            return null;
        }

        if ($conversas->isEmpty()) {
            return null;
        }

        $totalMinutos = 0;
        $count = 0;

        foreach ($conversas as $convId) {
            $firstIn = DB::table('wa_messages')
                ->where('conversation_id', $convId)
                ->where('direction', 'incoming')
                ->orderBy('created_at')
                ->value('created_at');

            $firstOut = DB::table('wa_messages')
                ->where('conversation_id', $convId)
                ->where('direction', 'outgoing')
                ->orderBy('created_at')
                ->value('created_at');

            if ($firstIn && $firstOut) {
                $diff = Carbon::parse($firstIn)->diffInMinutes(Carbon::parse($firstOut));
                if ($diff >= 0 && $diff < 1440) {
                    $totalMinutos += $diff;
                    $count++;
                }
            }
        }

        return $count > 0 ? round($totalMinutos / $count, 2) : null;
    }

    private function a2TaxaRespostaWA(int $userId, Carbon $inicio, Carbon $fim): ?float
    {
        try {
            $total = DB::table('wa_conversations')
                ->where('assigned_to', $userId)
                ->whereBetween('created_at', [$inicio, $fim])
                ->count();
        } catch (\Throwable $e) {
            return null;
        }

        if ($total === 0) {
            return null;
        }

        $comResposta = DB::table('wa_conversations')
            ->where('assigned_to', $userId)
            ->whereBetween('created_at', [$inicio, $fim])
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('wa_messages')
                    ->whereColumn('wa_messages.conversation_id', 'wa_conversations.id')
                    ->where('wa_messages.direction', 'outgoing');
            })
            ->count();

        return round(($comResposta / $total) * 100, 2);
    }

    private function a3ConversasSemResposta(int $userId, Carbon $inicio, Carbon $fim): float
    {
        try {
            return (float) DB::table('wa_conversations')
                ->where('assigned_to', $userId)
                ->whereBetween('created_at', [$inicio, $fim])
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('wa_messages')
                        ->whereColumn('wa_messages.conversation_id', 'wa_conversations.id')
                        ->where('wa_messages.direction', 'outgoing');
                })
                ->where('created_at', '<=', Carbon::now()->subHours(24))
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function a4TicketsResolvidos(int $userId, Carbon $inicio, Carbon $fim): float
    {
        try {
            return (float) DB::table('nexo_tickets')
                ->where('responsavel_id', $userId)
                ->where('status', 'resolvido')
                ->whereBetween('updated_at', [$inicio, $fim])
                ->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function a5TempoResolucaoTickets(int $userId, Carbon $inicio, Carbon $fim): ?float
    {
        try {
            $tickets = DB::table('nexo_tickets')
                ->where('responsavel_id', $userId)
                ->where('status', 'resolvido')
                ->whereBetween('updated_at', [$inicio, $fim])
                ->whereNotNull('created_at')
                ->select('created_at', 'updated_at')
                ->get();
        } catch (\Throwable $e) {
            return null;
        }

        if ($tickets->isEmpty()) {
            return null;
        }

        $totalHoras = 0;
        foreach ($tickets as $t) {
            $totalHoras += Carbon::parse($t->created_at)->diffInMinutes(Carbon::parse($t->updated_at)) / 60;
        }

        return round($totalHoras / $tickets->count(), 2);
    }

    private function getDjPropId(int $userId): ?int
    {
        $val = DB::table('users')->where('id', $userId)->value('datajuri_proprietario_id');
        return $val ? (int) $val : null;
    }

    private function contarDiasUteis(Carbon $inicio, Carbon $fim): int
    {
        $count = 0;
        $current = $inicio->copy();
        while ($current->lte($fim)) {
            if ($current->isWeekday()) {
                $count++;
            }
            $current->addDay();
        }
        return $count;
    }
}
