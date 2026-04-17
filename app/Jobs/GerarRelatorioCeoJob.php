<?php
// ESTÁVEL desde 17/04/2026

namespace App\Jobs;

use App\Models\NotificationIntranet;
use App\Models\RelatorioCeo;
use App\Models\User;
use App\Services\RelatorioCeo\ClaudeAnalysisService;
use App\Services\RelatorioCeo\DataCollector;
use App\Services\RelatorioCeo\PdfGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GerarRelatorioCeoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout  = 900; // 15 min
    public int $tries    = 1;

    public function __construct(private int $relatorioId) {}

    public function handle(
        DataCollector       $collector,
        ClaudeAnalysisService $claude,
        PdfGeneratorService  $pdf,
    ): void {
        $relatorio = RelatorioCeo::findOrFail($this->relatorioId);
        $relatorio->update(['status' => 'running']);

        try {
            $inicio = $relatorio->periodo_inicio->startOfDay();
            $fim    = $relatorio->periodo_fim->endOfDay();

            // 1. Coleta dados
            Log::info("RelatorioCeo #{$this->relatorioId}: coletando dados...");
            $dados = $collector->coletar($inicio, $fim);
            $relatorio->update(['dados_json' => json_encode($dados, JSON_UNESCAPED_UNICODE)]);

            // 2. Análise Claude Opus 4.7
            Log::info("RelatorioCeo #{$this->relatorioId}: chamando Claude Opus 4.7...");
            $analise = $claude->analisar($dados);
            $relatorio->update(['analise_json' => json_encode($analise, JSON_UNESCAPED_UNICODE)]);

            // 3. Gera PDF
            Log::info("RelatorioCeo #{$this->relatorioId}: gerando PDF...");
            $periodoLabel = "{$inicio->format('d/m/Y')} a {$fim->format('d/m/Y')}";
            $pdfBytes = $pdf->gerar($dados, $analise, $periodoLabel);

            // 4. Salva PDF
            $filename = "relatorios-ceo/relatorio-{$this->relatorioId}.pdf";
            Storage::disk('local')->put($filename, $pdfBytes);

            $relatorio->update([
                'status'   => 'success',
                'pdf_path' => $filename,
                'metadata' => [
                    'tamanho_pdf_kb' => round(strlen($pdfBytes) / 1024, 1),
                    'modulos_ok'     => array_keys(array_filter($dados, fn($v) => !isset($v['erro']))),
                    'gerado_em'      => now()->toISOString(),
                ],
            ]);

            // 5. Notifica admins
            $this->notificarAdmins($relatorio, $periodoLabel, $analise);

            Log::info("RelatorioCeo #{$this->relatorioId}: concluído com sucesso.");

        } catch (\Exception $e) {
            Log::error("RelatorioCeo #{$this->relatorioId}: FALHOU", [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 1000),
            ]);

            $relatorio->update([
                'status' => 'failed',
                'erro'   => $e->getMessage(),
            ]);

            $this->notificarErro($relatorio, $e->getMessage());

            throw $e;
        }
    }

    private function notificarAdmins(RelatorioCeo $relatorio, string $periodo, array $analise): void
    {
        $score = $analise['score_geral'] ?? null;
        $titulo = $analise['titulo_periodo'] ?? 'Novo relatório disponível';
        $scoreLabel = $score ? " · Score {$score}/10" : '';

        $admins = User::where('role', 'admin')->where('ativo', true)->get();
        foreach ($admins as $admin) {
            NotificationIntranet::enviar(
                userId:  $admin->id,
                titulo:  "Relatório CEO · {$periodo}{$scoreLabel}",
                mensagem: $titulo,
                link:    route('admin.relatorios-ceo.index'),
                tipo:    'info',
                icone:   'chart-bar',
            );
        }
    }

    private function notificarErro(RelatorioCeo $relatorio, string $erro): void
    {
        $admins = User::where('role', 'admin')->where('ativo', true)->get();
        foreach ($admins as $admin) {
            NotificationIntranet::enviar(
                userId:  $admin->id,
                titulo:  'Relatório CEO: falha na geração',
                mensagem: substr($erro, 0, 200),
                link:    route('admin.relatorios-ceo.index'),
                tipo:    'error',
                icone:   'exclamation-triangle',
            );
        }
    }
}
