<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\Vigilia\VigiliaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class VigiliaCruzar extends Command
{
    protected $signature = 'vigilia:cruzar';
    protected $description = 'Executa cruzamento VIGÍLIA (atividades × andamentos) e gera notificações';

    public function handle(): int
    {
        $this->info('[VIGÍLIA] Iniciando cruzamento...');
        $start = microtime(true);

        $service = app(VigiliaService::class);
        $stats = $service->executarCruzamento();

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("[VIGÍLIA] Cruzamento concluído em {$elapsed}s");
        $this->info("[VIGÍLIA] Total: {$stats['total']} | Verificados: " . ($stats['verificado'] ?? 0) . " | Suspeitos: " . ($stats['suspeito'] ?? 0) . " | Sem ação: " . ($stats['sem_acao'] ?? 0) . " | N/A: " . ($stats['nao_aplicavel'] ?? 0) . " | Futuro: " . ($stats['futuro'] ?? 0));

        // Gerar notificações para alertas críticos
        $this->gerarNotificacoes($service);

        // Verificar tarefas trigger vencidas
        $this->verificarTriggers($service);

        return self::SUCCESS;
    }

    private function gerarNotificacoes(VigiliaService $service): void
    {
        $alertas = $service->getAlertasAtivos();
        $criticos = array_filter($alertas, fn($a) => $a['severidade'] === 'critico');

        if (empty($criticos)) {
            $this->info('[VIGÍLIA] Nenhum alerta crítico para notificar.');
            return;
        }

        $this->info('[VIGÍLIA] Gerando ' . count($criticos) . ' notificações críticas...');

        // Notificar sócio (user_id = 1 = Rafael)
        $socioId = 1;

        // Agrupar por responsável
        $porResponsavel = [];
        foreach ($criticos as $alerta) {
            $resp = $alerta['responsavel'];
            $porResponsavel[$resp][] = $alerta;
        }

        foreach ($porResponsavel as $resp => $alertasResp) {
            $qtd = count($alertasResp);
            $tipos = array_map(fn($a) => $a['tipo_atividade'], $alertasResp);
            $resumoTipos = implode(', ', array_unique(array_slice($tipos, 0, 3)));

            // Notificação para o sócio
            DB::table('notifications_intranet')->insert([
                'user_id' => $socioId,
                'tipo' => 'vigilia_alerta_critico',
                'titulo' => "VIGÍLIA: {$qtd} alerta(s) crítico(s) — {$resp}",
                'mensagem' => "Existem {$qtd} compromisso(s) com prazo vencido ou sem ação para {$resp}. Tipos: {$resumoTipos}.",
                'link' => '/vigilia?tab=alertas&responsavel=' . urlencode($resp),
                'icone' => '🚨',
                'lida' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Notificação para o responsável (se for um user do sistema)
            $user = DB::table('users')->where('name', 'LIKE', '%' . explode(' ', $resp)[0] . '%')->first();
            if ($user && $user->id !== $socioId) {
                DB::table('notifications_intranet')->insert([
                    'user_id' => $user->id,
                    'tipo' => 'vigilia_alerta_pessoal',
                    'titulo' => "VIGÍLIA: Você tem {$qtd} compromisso(s) com prazo crítico",
                    'mensagem' => "Verifique seus compromissos pendentes. Tipos: {$resumoTipos}.",
                    'link' => '/vigilia?tab=alertas',
                    'icone' => '⏰',
                    'lida' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->info('[VIGÍLIA] Notificações criadas com sucesso.');
    }

    private function verificarTriggers(VigiliaService $service): void
    {
        $resumo = $service->getResumoTriggers();

        if (empty($resumo['detalhes_vencidos'])) {
            $this->info('[VIGÍLIA] Nenhuma tarefa trigger vencida.');
            return;
        }

        $this->info('[VIGÍLIA] ' . count($resumo['detalhes_vencidos']) . ' tarefa(s) trigger vencida(s). Notificando gestor...');

        $socioId = 1;

        foreach ($resumo['detalhes_vencidos'] as $t) {
            // Notificar o gestor (escalação)
            $existe = DB::table('notifications_intranet')
                ->where('tipo', 'vigilia_trigger_vencido')
                ->where('link', 'LIKE', '%datajuri_id=' . $t['datajuri_id'] . '%')
                ->where('created_at', '>=', now()->subHours(24))
                ->exists();

            if (!$existe) {
                DB::table('notifications_intranet')->insert([
                    'user_id' => $socioId,
                    'tipo' => 'vigilia_trigger_vencido',
                    'titulo' => 'VIGÍLIA: ' . $t['assunto'] . ' vencido — ' . $t['responsavel'],
                    'mensagem' => $t['responsavel'] . ' não concluiu "' . $t['assunto'] . '" no processo ' . ($t['processo_pasta'] ?? 'N/A') . ' em ' . $t['prazo_horas'] . 'h.',
                    'link' => '/vigilia?tab=triggers&datajuri_id=' . $t['datajuri_id'],
                    'icone' => '⚠',
                    'lida' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Notificar o advogado responsável
            $user = DB::table('users')
                ->where('name', 'LIKE', '%' . explode(' ', $t['responsavel'])[0] . '%')
                ->first();

            if ($user && $user->id !== $socioId) {
                $existeAdv = DB::table('notifications_intranet')
                    ->where('user_id', $user->id)
                    ->where('tipo', 'vigilia_trigger_pendente')
                    ->where('link', 'LIKE', '%datajuri_id=' . $t['datajuri_id'] . '%')
                    ->where('created_at', '>=', now()->subHours(24))
                    ->exists();

                if (!$existeAdv) {
                    DB::table('notifications_intranet')->insert([
                        'user_id' => $user->id,
                        'tipo' => 'vigilia_trigger_pendente',
                        'titulo' => 'Tarefa pendente: ' . $t['assunto'],
                        'mensagem' => 'Você tem uma tarefa "' . $t['assunto'] . '" pendente no processo ' . ($t['processo_pasta'] ?? 'N/A') . '. Prazo excedido.',
                        'link' => '/vigilia?tab=triggers&datajuri_id=' . $t['datajuri_id'],
                        'icone' => '📋',
                        'lida' => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

}
