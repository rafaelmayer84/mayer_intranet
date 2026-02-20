<?php

namespace App\Console\Commands;

use App\Models\BscInsightSnapshot;
use App\Services\BscInsights\AiBudgetGuard;
use App\Services\BscInsights\BscInsightSnapshotBuilder;
use App\Services\BscInsights\DemoCardGenerator;
use App\Services\BscInsights\OpenAIInsightService;
use Illuminate\Console\Command;

class GenerateBscInsightsCommand extends Command
{
    protected $signature = 'bsc:generate-insights
                            {--period=monthly : Período (monthly ou weekly)}
                            {--force : Ignora cooldown}';

    protected $description = 'Gera snapshot BSC e insights via OpenAI (ou DEMO)';

    public function handle(): int
    {
        $this->info('BSC Insights — Iniciando geração...');

        $isDemo = config('bsc_insights.demo_mode', false);
        $guard  = new AiBudgetGuard();

        // Budget check (skip em demo)
        if (!$isDemo && !$guard->canRun()) {
            $info = $guard->getBudgetInfo();
            $this->error(sprintf(
                'Limite mensal atingido: $%.2f / $%.2f. Execução bloqueada.',
                $info['spent_usd'],
                $info['limit_usd']
            ));
            $guard->registerBlocked();
            return 1;
        }

        // Cooldown check
        if (!$isDemo && !$this->option('force') && $guard->isInCooldown()) {
            $this->warn('Cooldown ativo. Use --force para ignorar.');
            return 1;
        }

        // 1. Snapshot
        $this->info('Construindo snapshot...');
        $builder = new BscInsightSnapshotBuilder();
        $data    = $builder->build();
        $hash    = BscInsightSnapshot::hashPayload($data);

        $snapshot = BscInsightSnapshot::findByHash($hash, $builder->getInicio(), $builder->getFim());

        if ($snapshot) {
            $this->info('Snapshot idêntico encontrado (ID: ' . $snapshot->id . '). Reutilizando.');
        } else {
            $snapshot = BscInsightSnapshot::create([
                'periodo_inicio'     => $builder->getInicio(),
                'periodo_fim'        => $builder->getFim(),
                'json_payload'       => json_encode($data, JSON_UNESCAPED_UNICODE),
                'payload_hash'       => $hash,
                'created_by_user_id' => null,
                'trigger_type'       => 'scheduled',
            ]);
            $this->info('Snapshot criado (ID: ' . $snapshot->id . ')');
        }

        // Erros no snapshot
        if (!empty($data['_errors'])) {
            $this->warn('Blocos com erro no snapshot:');
            foreach ($data['_errors'] as $err) {
                $this->warn('  - ' . $err['bloco'] . ': ' . $err['mensagem']);
            }
        }

        // 2. Gerar
        $this->info($isDemo ? 'Gerando cards DEMO...' : 'Chamando OpenAI...');

        if ($isDemo) {
            $demoRun = \App\Models\AiRun::create([
                'feature'            => 'bsc_insights',
                'snapshot_id'        => $snapshot->id,
                'model'              => 'demo',
                'input_tokens'       => 0,
                'output_tokens'      => 0,
                'total_tokens'       => 0,
                'estimated_cost_usd' => 0,
                'status'             => 'success',
                'created_by_user_id' => null,
            ]);
            $result = (new DemoCardGenerator())->generate($demoRun, $data);
            $result['run'] = $demoRun;
        } else {
            $result = (new OpenAIInsightService())->generate($snapshot);
        }

        $run = $result['run'];

        if ($run->status === 'success') {
            $this->info(sprintf(
                'Concluído: %d cards gerados | Tokens: %d | Custo: $%.4f | Run ID: %d',
                count($result['cards']),
                $run->total_tokens,
                $run->estimated_cost_usd,
                $run->id
            ));
            return 0;
        }

        $this->error('Falha: ' . ($run->error_message ?? 'erro desconhecido'));
        return 1;
    }
}
