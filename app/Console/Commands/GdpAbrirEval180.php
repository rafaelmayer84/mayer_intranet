<?php

namespace App\Console\Commands;

use App\Models\Eval180Form;
use App\Models\GdpCiclo;
use App\Models\User;
use App\Notifications\Eval180Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GdpAbrirEval180 extends Command
{
    protected $signature = 'gdp:abrir-eval180
        {--mes= : Mes referencia (default: mes anterior)}
        {--ano= : Ano referencia (default: atual)}';

    protected $description = 'Abre avaliacao 180 do mes para todos os advogados elegiveis e notifica';

    public function handle(): int
    {
        $now = Carbon::now();
        $mes = $this->option('mes') ?? $now->copy()->subMonth()->month;
        $ano = $this->option('ano') ?? ($mes == 12 && $now->month == 1 ? $now->year - 1 : $now->year);
        $period = sprintf('%04d-%02d', $ano, $mes);

        $this->info("Abrindo Eval180 para periodo: {$period}");
        $this->info(str_repeat('-', 40));

        // Ciclo ativo
        $ciclo = GdpCiclo::where('status', 'aberto')->first();
        if (!$ciclo) {
            $this->error('Nenhum ciclo GDP aberto encontrado.');
            return 1;
        }

        // Verificar se periodo esta dentro do ciclo
        $inicio = Carbon::parse($ciclo->data_inicio)->format('Y-m');
        $fim = Carbon::parse($ciclo->data_fim)->format('Y-m');
        if ($period < $inicio || $period > $fim) {
            $this->error("Periodo {$period} fora do ciclo {$ciclo->nome} ({$inicio} a {$fim}).");
            return 1;
        }

        // Usuarios elegiveis (mesma logica GDP)
        $users = User::whereIn('role', ['advogado', 'socio', 'coordenador', 'admin'])
            ->where('ativo', true)
            ->whereNotIn('id', [1, 2, 5, 6])
            ->orderBy('name')
            ->get();

        if ($users->isEmpty()) {
            $this->warn('Nenhum usuario elegivel encontrado.');
            return 1;
        }

        $criados = 0;
        $jaExistiam = 0;
        $notificados = 0;
        $periodoLabel = Carbon::createFromFormat('Y-m', $period)->translatedFormat('F/Y');

        foreach ($users as $user) {
            // Verificar se ja existe
            $exists = Eval180Form::where('cycle_id', $ciclo->id)
                ->where('user_id', $user->id)
                ->where('period', $period)
                ->exists();

            if ($exists) {
                $jaExistiam++;
                $this->line("  {$user->name}: ja existe, pulando.");
                continue;
            }

            // Criar formulario
            Eval180Form::create([
                'cycle_id'   => $ciclo->id,
                'user_id'    => $user->id,
                'period'     => $period,
                'status'     => 'pending_self',
                'created_by' => 1, // sistema (Rafael admin)
            ]);
            $criados++;

            // Notificar
            try {
                $user->notify(new Eval180Notification('autoavaliacao_pendente', [
                    'ciclo_nome'    => $ciclo->nome,
                    'periodo_label' => $periodoLabel,
                    'url'           => url("/gdp/me/eval180/{$ciclo->id}/{$period}"),
                ]));
                $notificados++;
                $this->info("  {$user->name}: criado + notificado.");
            } catch (\Throwable $e) {
                $this->warn("  {$user->name}: criado, mas notificacao falhou: " . $e->getMessage());
            }
        }

        // Audit log
        DB::table('gdp_audit_log')->insert([
            'user_id'     => 1,
            'entidade'    => 'gdp_eval180_forms',
            'entidade_id' => 0,
            'campo'       => 'eval180_batch_open',
            'valor_novo'  => json_encode([
                'period'     => $period,
                'ciclo'      => $ciclo->nome,
                'criados'    => $criados,
                'existentes' => $jaExistiam,
                'notificados' => $notificados,
            ], JSON_UNESCAPED_UNICODE),
            'ip'          => '0.0.0.0',
            'created_at'  => now(),
        ]);

        $this->info(str_repeat('-', 40));
        $this->info("Resultado: {$criados} criados, {$jaExistiam} ja existiam, {$notificados} notificados.");

        return 0;
    }
}
