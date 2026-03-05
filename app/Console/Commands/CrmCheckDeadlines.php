<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CrmCheckDeadlines extends Command
{
    protected $signature = 'crm:check-deadlines';
    protected $description = 'Verifica oportunidades CRM com prazo vencido e notifica responsáveis';

    private const CHEFIA_USER_ID = 1; // Rafael

    public function handle(): int
    {
        $hoje = Carbon::today();
        $vencidas = DB::table('crm_opportunities')
            ->join('crm_stages', 'crm_opportunities.stage_id', '=', 'crm_stages.id')
            ->leftJoin('crm_accounts', 'crm_opportunities.account_id', '=', 'crm_accounts.id')
            ->where('crm_opportunities.status', 'open')
            ->where('crm_stages.is_won', false)
            ->where('crm_stages.is_lost', false)
            ->whereNotNull('crm_opportunities.next_action_at')
            ->whereDate('crm_opportunities.next_action_at', '<', $hoje)
            ->whereNotNull('crm_opportunities.owner_user_id')
            ->select(
                'crm_opportunities.id',
                'crm_opportunities.title',
                'crm_opportunities.next_action_at',
                'crm_opportunities.owner_user_id',
                'crm_opportunities.value_estimated',
                'crm_accounts.name as account_name'
            )
            ->get();

        $notificados = 0;

        foreach ($vencidas as $op) {
            $dias = Carbon::parse($op->next_action_at)->diffInDays($hoje);
            $user = DB::table('users')->where('id', $op->owner_user_id)->first();
            if (!$user) continue;

            // Verificar se já notificou hoje
            $jaNotificou = DB::table('notifications_intranet')
                ->where('user_id', $op->owner_user_id)
                ->where('tipo', 'crm_deadline')
                ->where('link', 'LIKE', '%/crm/oportunidades/' . $op->id . '%')
                ->whereDate('created_at', $hoje)
                ->exists();

            if ($jaNotificou) continue;

            $link = url('/crm/oportunidades/' . $op->id);
            $cliente = $op->account_name ?? 'Cliente não identificado';
            $dataVenc = Carbon::parse($op->next_action_at)->format('d/m/Y');

            if ($dias <= 5) {
                // ═══ VERSÃO 1: Amigável (1-5 dias) ═══
                $tituloNotif = 'Lembrete: ação pendente no CRM';
                $msgNotif = "Oi {$user->name}! A oportunidade \"{$op->title}\" ({$cliente}) está aguardando sua ação desde {$dataVenc} ({$dias} dia(s)). Quando puder, dê uma olhada para não perder o timing com o cliente.";

                // Notificação sininho — só para o advogado
                DB::table('notifications_intranet')->insert([
                    'user_id'    => $op->owner_user_id,
                    'tipo'       => 'crm_deadline',
                    'titulo'     => $tituloNotif,
                    'mensagem'   => $msgNotif,
                    'link'       => $link,
                    'icone'      => 'clock',
                    'lida'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Email amigável (a partir de 3 dias)
                if ($dias >= 3 && $user->email) {
                    try {
                        Mail::raw(
                            "Olá {$user->name},\n\n" .
                            "Este é um lembrete amigável: a oportunidade \"{$op->title}\" do cliente {$cliente} está pendente de ação desde {$dataVenc} ({$dias} dias).\n\n" .
                            "Sabemos que a rotina é corrida, mas manter o contato regular com o cliente faz diferença na conversão. Quando possível, registre a próxima ação no CRM.\n\n" .
                            "Acesse: {$link}\n\n" .
                            "Abraço,\nSistema RESULTADOS!",
                            function ($msg) use ($user) {
                                $msg->to($user->email)
                                    ->subject('📋 Lembrete: oportunidade aguardando sua ação');
                            }
                        );
                    } catch (\Exception $e) {
                        Log::warning('CRM Deadlines: falha email amigável', ['user' => $user->id, 'error' => $e->getMessage()]);
                    }
                }

            } else {
                // ═══ VERSÃO 2: Dura + cópia chefia (6+ dias) ═══
                $tituloNotif = '⚠ REITERAÇÃO: oportunidade com ' . $dias . ' dias de atraso';
                $msgNotif = "ATENÇÃO: A oportunidade \"{$op->title}\" ({$cliente}) está com {$dias} dias de atraso. A chefia foi notificada. A omissão no acompanhamento de oportunidades impacta diretamente o indicador de conformidade (PEN-D04) e será considerada na apuração do GDP.";

                // Notificação sininho — para o advogado
                DB::table('notifications_intranet')->insert([
                    'user_id'    => $op->owner_user_id,
                    'tipo'       => 'crm_deadline',
                    'titulo'     => $tituloNotif,
                    'mensagem'   => $msgNotif,
                    'link'       => $link,
                    'icone'      => 'alert-triangle',
                    'lida'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Notificação sininho — para a chefia
                DB::table('notifications_intranet')->insert([
                    'user_id'    => self::CHEFIA_USER_ID,
                    'tipo'       => 'crm_deadline_chefia',
                    'titulo'     => "Reiteração: {$user->name} — oportunidade {$dias}d atrasada",
                    'mensagem'   => "A advogada {$user->name} possui a oportunidade \"{$op->title}\" ({$cliente}) com {$dias} dias de atraso na próxima ação (vencimento: {$dataVenc}). Valor estimado: R$ " . number_format($op->value_estimated ?? 0, 2, ',', '.') . ". Esta é uma reiteração — a primeira notificação foi enviada anteriormente sem resposta.",
                    'link'       => $link,
                    'icone'      => 'alert-triangle',
                    'lida'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Email duro — para o advogado com CC chefia
                if ($user->email) {
                    try {
                        $chefia = DB::table('users')->where('id', self::CHEFIA_USER_ID)->first();
                        $ccEmail = $chefia && $chefia->email ? $chefia->email : null;

                        Mail::raw(
                            "Prezado(a) {$user->name},\n\n" .
                            "REITERAÇÃO — OPORTUNIDADE COM {$dias} DIAS DE ATRASO\n\n" .
                            "A oportunidade \"{$op->title}\" vinculada ao cliente {$cliente} encontra-se com {$dias} dias sem a devida ação de acompanhamento. O prazo para a próxima ação era {$dataVenc}.\n\n" .
                            "Informamos que:\n" .
                            "1. A omissão no acompanhamento de oportunidades comerciais configura ocorrência de conformidade (PEN-D04) no sistema de Gestão de Desempenho (GDP).\n" .
                            "2. O não cumprimento reiterado dos prazos de follow-up impacta diretamente a avaliação de desempenho semestral.\n" .
                            "3. A perda de oportunidades por inação será considerada na apuração do eixo Financeiro do GDP.\n\n" .
                            "Solicitamos a regularização imediata com o registro da ação tomada no CRM.\n\n" .
                            "Acesse: {$link}\n\n" .
                            "Atenciosamente,\nGestão — Mayer Advogados\n(Mensagem automática do Sistema RESULTADOS!)",
                            function ($msg) use ($user, $ccEmail, $dias) {
                                $msg->to($user->email)
                                    ->subject("⚠ REITERAÇÃO: Oportunidade CRM com {$dias} dias de atraso — ação imediata requerida");
                                if ($ccEmail) {
                                    $msg->cc($ccEmail);
                                }
                            }
                        );
                    } catch (\Exception $e) {
                        Log::warning('CRM Deadlines: falha email reiteração', ['user' => $user->id, 'error' => $e->getMessage()]);
                    }
                }
            }

            $notificados++;
        }

        $this->info("Verificadas {$vencidas->count()} oportunidades vencidas, {$notificados} notificações enviadas.");
        Log::info('CRM Deadlines', ['vencidas' => $vencidas->count(), 'notificados' => $notificados]);

        return self::SUCCESS;
    }
}
