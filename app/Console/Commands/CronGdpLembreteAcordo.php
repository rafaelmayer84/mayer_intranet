<?php

namespace App\Console\Commands;

use App\Mail\GdpAcordoPendente;
use App\Models\NotificationIntranet;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CronGdpLembreteAcordo extends Command
{
    protected $signature = 'cron:gdp-lembrete-acordo';
    protected $description = 'Envia lembretes diários para advogados com acordo de desempenho pendente de assinatura';

    public function handle(): int
    {
        $ciclo = DB::table('gdp_ciclos')->where('status', 'aberto')->first();
        if (!$ciclo) {
            $this->info('Nenhum ciclo ativo.');
            return 0;
        }

        $usuariosComMeta = DB::table('gdp_metas_individuais')
            ->where('ciclo_id', $ciclo->id)
            ->distinct()
            ->pluck('user_id');

        if ($usuariosComMeta->isEmpty()) {
            $this->info('Nenhuma meta individual encontrada.');
            return 0;
        }

        $jaAssinaram = DB::table('gdp_snapshots')
            ->where('ciclo_id', $ciclo->id)
            ->where('congelado', true)
            ->pluck('user_id');

        $pendentes = $usuariosComMeta->diff($jaAssinaram);

        if ($pendentes->isEmpty()) {
            $this->info('Todos os acordos foram assinados.');
            return 0;
        }

        $advogados = User::whereIn('id', $pendentes)->where('ativo', true)->get();
        $enviados = 0;

        foreach ($advogados as $adv) {
            $link = url("/gdp/acordo/{$adv->id}/visualizar");

            try {
                NotificationIntranet::enviar(
                    $adv->id,
                    '⚠️ Acordo de Desempenho pendente',
                    "Seu Acordo de Desempenho do ciclo {$ciclo->nome} ainda não foi assinado. Acesse e assine agora.",
                    $link,
                    'warning',
                    'alert-triangle'
                );

                if ($adv->email) {
                    Mail::to($adv->email)->queue(new GdpAcordoPendente(
                        advogado: $adv,
                        cicloNome: $ciclo->nome,
                        linkAceite: $link,
                        isLembrete: true
                    ));
                }

                $enviados++;
                $this->info("Lembrete enviado: {$adv->name}");
            } catch (\Throwable $e) {
                Log::error("GDP Lembrete erro para user {$adv->id}: " . $e->getMessage());
                $this->error("Erro: {$adv->name} - {$e->getMessage()}");
            }
        }

        $this->info("Total de lembretes enviados: {$enviados}");
        Log::info("GDP Lembrete Acordo: {$enviados} lembretes enviados para ciclo {$ciclo->nome}");
        return 0;
    }
}
