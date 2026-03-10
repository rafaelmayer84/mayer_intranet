<?php

namespace App\Console\Commands;

use App\Services\Crm\CrmPulsoService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class PulsoConsolidarCommand extends Command
{
    protected $signature = 'pulso:consolidar {--data= : Data específica (Y-m-d), default ontem}';
    protected $description = 'Consolida contatos de clientes e verifica thresholds do Pulso do Cliente';

    public function handle(): int
    {
        $data = $this->option('data') ?? Carbon::yesterday('America/Sao_Paulo')->toDateString();
        $this->info("Pulso: consolidando dia {$data}...");

        $service = app(CrmPulsoService::class);
        $stats = $service->consolidarDia($data);

        $this->info("Concluído: {$stats['processados']} accounts processados, {$stats['alertas']} alertas gerados.");
        return 0;
    }
}
