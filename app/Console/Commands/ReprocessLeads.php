<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadProcessingService;
use Illuminate\Console\Command;

class ReprocessLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leads:reprocess
                            {--all : Reprocessar todos os leads}
                            {--errors : Reprocessar apenas leads com erro}
                            {--empty : Reprocessar apenas leads sem resumo}
                            {--limit= : Limitar quantidade de leads}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocessa leads com OpenAI';

    /**
     * Execute the console command.
     */
    public function handle(LeadProcessingService $leadService): int
    {
        $query = Lead::query();

        // Aplicar filtros
        if ($this->option('errors')) {
            $query->whereNotNull('erro_processamento');
            $this->info('Reprocessando leads com erro...');
        } elseif ($this->option('empty')) {
            $query->where(function($q) {
                $q->whereNull('resumo_demanda')
                  ->orWhere('resumo_demanda', '');
            });
            $this->info('Reprocessando leads sem resumo...');
        } elseif ($this->option('all')) {
            $this->info('Reprocessando TODOS os leads...');
        } else {
            $this->error('Especifique uma opção: --all, --errors ou --empty');
            return 1;
        }

        // Aplicar limite
        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $leads = $query->get();
        $total = $leads->count();

        if ($total === 0) {
            $this->warn('Nenhum lead encontrado para reprocessar.');
            return 0;
        }

        $this->info("Encontrados {$total} leads para reprocessar.");
        
        if (!$this->confirm('Deseja continuar?')) {
            $this->warn('Operação cancelada.');
            return 0;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $sucessos = 0;
        $erros = 0;

        foreach ($leads as $lead) {
            try {
                $success = $leadService->reprocessLead($lead);
                
                if ($success) {
                    $sucessos++;
                } else {
                    $erros++;
                }
                
            } catch (\Exception $e) {
                $this->error("\nErro no lead #{$lead->id}: " . $e->getMessage());
                $erros++;
            }

            $bar->advance();
            
            // Rate limiting (evitar sobrecarga da API OpenAI)
            sleep(2);
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Reprocessamento concluído!");
        $this->table(
            ['Métrica', 'Quantidade'],
            [
                ['Total', $total],
                ['Sucessos', $sucessos],
                ['Erros', $erros],
                ['Taxa de Sucesso', $total > 0 ? round(($sucessos / $total) * 100, 1) . '%' : '0%']
            ]
        );

        return 0;
    }
}
