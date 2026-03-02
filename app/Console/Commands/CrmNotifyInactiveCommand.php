<?php

namespace App\Console\Commands;

use App\Models\Crm\CrmAccount;
use App\Models\User;
use App\Models\NotificationIntranet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CrmNotifyInactiveCommand extends Command
{
    protected $signature = 'crm:notify-inactive';
    protected $description = 'Gera avisos individuais para advogados com contas sem interação há mais de 15 dias';

    public function handle(): int
    {
        $this->info('Verificando contas inativas...');

        // Buscar advogados que possuem contas ativas
        $lawyers = User::whereIn('role', ['advogado', 'socio', 'coordenador'])
            ->whereHas('crmAccounts', function ($q) {
                $q->where('lifecycle', 'ativo');
            })
            ->get();

        $notified = 0;

        foreach ($lawyers as $lawyer) {
            // Contas ativas sem contato há 15+ dias
            $stale = CrmAccount::where('owner_user_id', $lawyer->id)
                ->where('lifecycle', 'ativo')
                ->where(function ($q) {
                    $q->whereNull('last_touch_at')
                      ->orWhere('last_touch_at', '<', now()->subDays(15));
                })
                ->orderBy('last_touch_at')
                ->get(['id', 'name', 'last_touch_at', 'health_score']);

            if ($stale->isEmpty()) {
                continue;
            }

            // Contas com next_touch_at vencido
            $overdue = CrmAccount::where('owner_user_id', $lawyer->id)
                ->where('lifecycle', 'ativo')
                ->whereNotNull('next_touch_at')
                ->where('next_touch_at', '<', now())
                ->count();

            // Contas com health < 40
            $critical = CrmAccount::where('owner_user_id', $lawyer->id)
                ->where('lifecycle', 'ativo')
                ->where('health_score', '<', 40)
                ->count();

            // Montar lista das 10 mais urgentes
            $listItems = $stale->take(10)->map(function ($acc) {
                $days = $acc->last_touch_at
                    ? (int) Carbon::parse($acc->last_touch_at)->diffInDays(now())
                    : 999;
                $daysLabel = $days >= 999 ? 'nunca contatado' : $days . ' dias sem contato';
                $hs = $acc->health_score !== null ? " (saúde: {$acc->health_score})" : '';
                return "- **{$acc->name}**: {$daysLabel}{$hs}";
            })->implode("\n");

            $extra = $stale->count() > 10 ? "\n... e mais " . ($stale->count() - 10) . " conta(s)." : '';

            $body = "Você tem **{$stale->count()}** conta(s) ativa(s) sem interação há mais de 15 dias.";
            if ($overdue > 0) {
                $body .= "\n**{$overdue}** conta(s) com follow-up vencido.";
            }
            if ($critical > 0) {
                $body .= "\n**{$critical}** conta(s) com saúde crítica (abaixo de 40).";
            }
            $body .= "\n\n**Contas que precisam de atenção:**\n{$listItems}{$extra}";
            $body .= "\n\nAcesse o CRM → Carteira para registrar suas interações.";

            // Verificar se já existe notificação do mesmo tipo hoje para este advogado
            $existing = NotificationIntranet::where('user_id', $lawyer->id)
                ->where('titulo', 'LIKE', '%CRM: Aten%')
                ->where('created_at', '>=', now()->startOfDay())
                ->exists();

            if ($existing) {
                continue;
            }

            // Resumo conciso para o sininho
            $resumo = $stale->count() . ' conta(s) sem interação há 15+ dias.';
            if ($overdue > 0) $resumo .= ' ' . $overdue . ' follow-up(s) vencido(s).';
            if ($critical > 0) $resumo .= ' ' . $critical . ' em saúde crítica.';

            NotificationIntranet::enviar(
                $lawyer->id,
                'CRM: Atenção necessária',
                $resumo,
                '/crm/carteira',
                $critical > 0 ? 'danger' : 'warning',
                'alert-triangle'
            );

            $notified++;
            $this->info("  → {$lawyer->name}: {$stale->count()} contas inativas");
        }

        $this->info("Concluído: {$notified} aviso(s) gerado(s).");
        return self::SUCCESS;
    }
}
