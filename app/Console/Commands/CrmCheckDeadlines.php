<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CrmCheckDeadlines extends Command
{
    protected $signature = 'crm:check-deadlines';
    protected $description = 'Verifica oportunidades CRM com prazo vencido e notifica responsáveis';

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
                'crm_accounts.name as account_name'
            )
            ->get();

        $notificados = 0;

        foreach ($vencidas as $op) {
            $dias = Carbon::parse($op->next_action_at)->diffInDays($hoje);

            // Verificar se já notificou hoje para esta oportunidade
            $jaNotificou = DB::table('notifications_intranet')
                ->where('user_id', $op->owner_user_id)
                ->where('tipo', 'crm_deadline')
                ->where('link', 'LIKE', '%/crm/oportunidades/' . $op->id . '%')
                ->whereDate('created_at', $hoje)
                ->exists();

            if ($jaNotificou) continue;

            // Criar notificação intranet
            DB::table('notifications_intranet')->insert([
                'user_id'    => $op->owner_user_id,
                'tipo'       => 'crm_deadline',
                'titulo'     => 'Oportunidade com prazo vencido',
                'mensagem'   => ($op->account_name ?? 'Sem conta') . ' — "' . $op->title . '" está ' . $dias . ' dia(s) atrasada. Próxima ação era ' . Carbon::parse($op->next_action_at)->format('d/m/Y') . '.',
                'link'       => url('/crm/oportunidades/' . $op->id),
                'icone'      => 'clock',
                'lida'       => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $notificados++;

            // Se atraso > 3 dias, enviar email também
            if ($dias > 3) {
                $user = DB::table('users')->where('id', $op->owner_user_id)->first();
                if ($user && $user->email) {
                    try {
                        \Illuminate\Support\Facades\Mail::raw(
                            "Olá {$user->name},\n\nA oportunidade \"{$op->title}\" ({$op->account_name}) está com {$dias} dias de atraso.\n\nA próxima ação era prevista para " . Carbon::parse($op->next_action_at)->format('d/m/Y') . ".\n\nAcesse: " . url('/crm/oportunidades/' . $op->id) . "\n\n— Sistema RESULTADOS!",
                            function ($msg) use ($user) {
                                $msg->to($user->email)->subject('⚠ CRM: Oportunidade com prazo vencido');
                            }
                        );
                    } catch (\Exception $e) {
                        Log::warning('CRM Deadlines: falha email', ['user' => $user->id, 'error' => $e->getMessage()]);
                    }
                }
            }
        }

        $this->info("Verificadas {$vencidas->count()} oportunidades vencidas, {$notificados} notificações enviadas.");
        Log::info('CRM Deadlines', ['vencidas' => $vencidas->count(), 'notificados' => $notificados]);

        return self::SUCCESS;
    }
}
