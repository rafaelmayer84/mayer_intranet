<?php

namespace App\Services\Vigilia;

use Illuminate\Support\Carbon;

class VigiliaExportService
{
    protected VigiliaService $vigilia;

    public function __construct(VigiliaService $vigilia)
    {
        $this->vigilia = $vigilia;
    }

    /**
     * Gera dados para exportação Excel (CSV compatível).
     * A view Blade gera a tabela HTML que é convertida via response headers.
     */
    public function exportCompromissosExcel(array $filtros): array
    {
        $filtros['per_page'] = 99999;
        return $this->vigilia->getCompromissos($filtros);
    }

    /**
     * Gera dados para view de impressão PDF (window.print no browser).
     */
    public function exportRelatorioIndividualPdf(string $responsavel, ?string $inicio, ?string $fim): array
    {
        return $this->vigilia->getRelatorioIndividual($responsavel, $inicio, $fim);
    }

    public function exportRelatorioPrazosPdf(): array
    {
        return $this->vigilia->getRelatorioPrazos();
    }

    public function exportRelatorioConsolidadoPdf(string $inicio, string $fim): array
    {
        return $this->vigilia->getRelatorioConsolidado($inicio, $fim);
    }

    public function exportRelatorioCruzamentoPdf(?string $inicio, ?string $fim): array
    {
        return $this->vigilia->getRelatorioCruzamento($inicio, $fim);
    }
}
