<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DataCollector
{
    public function __construct(
        private FinanceiroCollector      $financeiro,
        private NexoCollector            $nexo,
        private GdpCollector             $gdp,
        private ProcessosCollector       $processos,
        private MercadoCollector         $mercado,
        private GaCollector              $ga,
        private LeadsCollector           $leads,
        private WhatsAppContentCollector $whatsapp,
    ) {}

    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $dados = [
            'periodo' => [
                'inicio'    => $inicio->toDateString(),
                'fim'       => $fim->toDateString(),
                'label'     => "{$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}",
                'gerado_em' => now()->format('d/m/Y H:i'),
            ],
        ];

        foreach ([
            'financeiro' => fn() => $this->financeiro->coletar($inicio, $fim),
            'nexo'       => fn() => $this->nexo->coletar($inicio, $fim),
            'whatsapp'   => fn() => $this->whatsapp->coletar($inicio, $fim),
            'leads'      => fn() => $this->leads->coletar($inicio, $fim),
            'gdp'        => fn() => $this->gdp->coletar($inicio, $fim),
            'processos'  => fn() => $this->processos->coletar($inicio, $fim),
            'mercado'    => fn() => $this->mercado->coletar($inicio, $fim),
            'ga'         => fn() => $this->ga->coletar($inicio, $fim),
        ] as $modulo => $coletor) {
            try {
                $dados[$modulo] = $coletor();
            } catch (\Exception $e) {
                Log::error("RelatorioCeo DataCollector: falha em [{$modulo}]", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $dados[$modulo] = ['erro' => $e->getMessage()];
            }
        }

        return $dados;
    }
}
