<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Services\Vigilia\VigiliaAIAuditorService;

/**
 * Machine B — audita atividades 'suspeito' com Claude Sonnet e emite veredicto.
 * Roda diariamente após vigilia:cruzar e vigilia:classificar.
 */
class VigiliaAuditar extends Command
{
    protected $signature = 'vigilia:auditar {--lote=10 : Casos por chamada AI} {--limite= : Máximo de cruzamentos a auditar}';
    protected $description = 'Audita cruzamentos suspeitos com Claude Sonnet e registra veredicto';

    private VigiliaAIAuditorService $auditor;

    // Padrões de andamento do escritório (replicado de VigiliaService)
    private const ANDAMENTO_ESCRITORIO = [
        'Petição', 'Recurso', 'Contestação', 'Impugnação', 'Manifestação',
        'Apelação', 'Agravo', 'Embargos', 'Réplica', 'Contrarrazões',
        'Memorial', 'Protocolo', 'Juntada de documento', 'Substabelecimento',
    ];

    public function handle(): int
    {
        $this->info('[VIGÍLIA B] Iniciando auditoria AI de cruzamentos suspeitos...');
        $start = microtime(true);

        $this->auditor = app(VigiliaAIAuditorService::class);

        $tamLote = (int) $this->option('lote');
        $limite  = $this->option('limite') ? (int) $this->option('limite') : null;

        $query = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->whereIn('vc.status_cruzamento', ['suspeito'])
            ->where(function ($q) {
                $q->whereNull('vc.ai_verdict')->orWhere('vc.ai_verdict', '');
            })
            ->select(
                'vc.id as cruzamento_id',
                'ad.id as atividade_id',
                'ad.assunto',
                'ad.tipo_atividade',
                'ad.data_hora',
                'ad.data_conclusao',
                'ad.processo_pasta',
                'ad.responsavel_nome',
            )
            ->orderBy('vc.updated_at');

        if ($limite) {
            $query->limit($limite);
        }

        $total = (clone $query)->count();
        $this->info("[VIGÍLIA B] {$total} cruzamentos suspeitos para auditar.");

        if ($total === 0) {
            $this->info('[VIGÍLIA B] Nada a fazer.');
            return self::SUCCESS;
        }

        $processados = 0;
        $verificados = 0;
        $suspeitos   = 0;
        $erros       = 0;

        $query->chunk($tamLote, function ($rows) use (&$processados, &$verificados, &$suspeitos, &$erros) {
            $casos = $rows->map(function ($row) {
                $andamentos = $this->buscarAndamentosContexto($row->processo_pasta, $row->data_conclusao ?? $row->data_hora);
                return [
                    'cruzamento_id' => $row->cruzamento_id,
                    'atividade'     => $row,
                    'andamentos'    => $andamentos,
                ];
            })->values()->toArray();

            $resultados = $this->auditor->auditarLote($casos);

            if (empty($resultados)) {
                $erros += count($casos);
                return;
            }

            foreach ($rows as $row) {
                $res = $resultados[$row->cruzamento_id] ?? null;
                if (!$res) {
                    $erros++;
                    continue;
                }

                DB::table('vigilia_cruzamentos')->where('id', $row->cruzamento_id)->update([
                    'ai_verdict'       => $res['verdict'],
                    'ai_justificativa' => $res['justificativa'],
                    'ai_auditado_at'   => now(),
                    'updated_at'       => now(),
                ]);

                if ($res['verdict'] === 'VERIFICADO') {
                    $verificados++;
                } else {
                    $suspeitos++;
                }
                $processados++;
            }

            $this->line("  → {$processados} auditados até agora...");
        });

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("[VIGÍLIA B] Concluído em {$elapsed}s — {$processados} auditados, {$verificados} verificados, {$suspeitos} suspeitos/inconclusivos, {$erros} erros.");

        return self::SUCCESS;
    }

    private function buscarAndamentosContexto(string $processoPasta, ?string $dataRef): array
    {
        if (!$processoPasta || !$dataRef) {
            return [];
        }

        $dataFim   = date('Y-m-d', strtotime($dataRef));
        $dataInicio = date('Y-m-d', strtotime($dataRef . ' -60 days'));

        $query = DB::table('andamentos_fase')
            ->where('processo_pasta', $processoPasta)
            ->whereBetween('data_andamento', [$dataInicio, $dataFim])
            ->whereNotNull('descricao')
            ->where('descricao', '!=', '');

        // Prioriza andamentos do escritório
        $padroes = self::ANDAMENTO_ESCRITORIO;
        $query->where(function ($q) use ($padroes) {
            foreach ($padroes as $p) {
                $q->orWhere('descricao', 'LIKE', '%' . $p . '%');
            }
        });

        return $query->orderByDesc('data_andamento')
            ->limit(5)
            ->select('data_andamento', 'descricao')
            ->get()
            ->toArray();
    }
}
