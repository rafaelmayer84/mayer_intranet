<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use App\Services\Vigilia\VigiliaAIClassificadorService;

/**
 * Machine C — classifica andamentos de tribunais com Claude Haiku
 * e gera obrigações para eventos significativos (SENTENÇA, ACÓRDÃO, DECISÃO).
 *
 * Roda diariamente após o sync do DataJuri.
 * Processa apenas andamentos 2026+ ainda não classificados (tipo_ai IS NULL).
 */
class VigiliaClassificar extends Command
{
    protected $signature = 'vigilia:classificar {--lote=50 : Tamanho do lote por chamada AI} {--limite= : Máximo de andamentos a processar nesta execução}';
    protected $description = 'Classifica andamentos 2026+ com AI e gera obrigações para eventos significativos';

    private VigiliaAIClassificadorService $classificador;

    // Mapa DataJuri proprietario_id → users.id (carregado uma vez)
    private array $userMap = [];

    public function handle(): int
    {
        $this->info('[VIGÍLIA C] Iniciando classificação de andamentos...');
        $start = microtime(true);

        $this->classificador = app(VigiliaAIClassificadorService::class);
        $this->carregarUserMap();

        $tamLote  = (int) $this->option('lote');
        $limite   = $this->option('limite') ? (int) $this->option('limite') : null;

        $query = DB::table('andamentos_fase')
            ->whereNull('tipo_ai')
            ->where('data_andamento', '>=', '2026-01-01')
            ->whereNotNull('descricao')
            ->where('descricao', '!=', '')
            ->orderBy('data_andamento')
            ->select('id', 'descricao', 'processo_pasta', 'fase_processo_id_datajuri', 'data_andamento');

        if ($limite) {
            $query->limit($limite);
        }

        $total = (clone $query)->count();
        $this->info("[VIGÍLIA C] {$total} andamentos para classificar.");

        if ($total === 0) {
            $this->info('[VIGÍLIA C] Nada a fazer.');
            return self::SUCCESS;
        }

        $processados = 0;
        $obrigacoesCriadas = 0;
        $erros = 0;

        $query->chunk($tamLote, function ($andamentos) use (&$processados, &$obrigacoesCriadas, &$erros, $tamLote) {
            $payload = $andamentos->map(fn($a) => [
                'id'        => $a->id,
                'descricao' => $a->descricao,
            ])->toArray();

            $resultados = $this->classificador->classificarLote($payload);

            if (empty($resultados)) {
                $erros += count($payload);
                // Marca como OUTRO para não ficar em loop infinito
                foreach ($andamentos as $a) {
                    DB::table('andamentos_fase')->where('id', $a->id)->update([
                        'tipo_ai'         => 'OUTRO',
                        'ai_analised_at'  => now(),
                    ]);
                }
                return;
            }

            foreach ($andamentos as $a) {
                $resultado = $resultados[$a->id] ?? null;
                $tipo      = $resultado['tipo_ai'] ?? 'OUTRO';

                DB::table('andamentos_fase')->where('id', $a->id)->update([
                    'tipo_ai'        => $tipo,
                    'ai_analised_at' => now(),
                ]);

                // Gerar obrigação para eventos significativos
                if (in_array($tipo, VigiliaAIClassificadorService::tiposQueGeram0brigacao())) {
                    $criou = $this->gerarObrigacao($a, $tipo, $resultado['motivo'] ?? '');
                    if ($criou) {
                        $obrigacoesCriadas++;
                    }
                }

                $processados++;
            }

            $this->line("  → {$processados} classificados até agora...");
        });

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("[VIGÍLIA C] Concluído em {$elapsed}s — {$processados} classificados, {$obrigacoesCriadas} obrigações criadas, {$erros} erros.");

        return self::SUCCESS;
    }

    private function gerarObrigacao(object $andamento, string $tipoEvento, string $motivo): bool
    {
        $processoPasta = $this->resolverProcessoPasta($andamento);
        if (empty($processoPasta)) {
            return false;
        }
        $andamento->processo_pasta = $processoPasta;

        // Unique constraint impede duplicatas
        $existe = DB::table('vigilia_obrigacoes')
            ->where('andamento_fase_id', $andamento->id)
            ->where('tipo_evento', $tipoEvento)
            ->exists();

        if ($existe) {
            return false;
        }

        $advogadoUserId = $this->resolverAdvogado($andamento->processo_pasta);

        $dataLimite = Carbon::parse($andamento->data_andamento)->addHours(72);

        DB::table('vigilia_obrigacoes')->insert([
            'andamento_fase_id' => $andamento->id,
            'processo_pasta'    => $andamento->processo_pasta,
            'tipo_evento'       => $tipoEvento,
            'descricao_evento'  => mb_substr($andamento->descricao, 0, 500),
            'data_evento'       => $andamento->data_andamento,
            'advogado_user_id'  => $advogadoUserId,
            'status'            => 'pendente',
            'data_limite'       => $dataLimite,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Notificar advogado e sócio (user_id=1)
        $this->notificar($andamento, $tipoEvento, $advogadoUserId);

        return true;
    }

    private function resolverProcessoPasta(object $andamento): string
    {
        if (!empty($andamento->processo_pasta)) {
            return $andamento->processo_pasta;
        }

        if (empty($andamento->fase_processo_id_datajuri)) {
            return '';
        }

        return (string) DB::table('fases_processo')
            ->where('datajuri_id', $andamento->fase_processo_id_datajuri)
            ->value('processo_pasta') ?? '';
    }

    /**
     * Resolve o advogado responsável pelo processo.
     * Cadeia: processo_pasta → fases_processo.proprietario_id → users.datajuri_proprietario_id
     */
    private function resolverAdvogado(string $processoPasta): ?int
    {
        $proprietarioId = DB::table('fases_processo')
            ->where('processo_pasta', $processoPasta)
            ->whereNotNull('proprietario_id')
            ->orderByDesc('fase_atual')
            ->value('proprietario_id');

        if (!$proprietarioId) {
            $proprietarioId = DB::table('processos')
                ->where('pasta', $processoPasta)
                ->value('proprietario_id');
        }

        return $this->userMap[$proprietarioId] ?? null;
    }

    private function notificar(object $andamento, string $tipoEvento, ?int $advogadoUserId): void
    {
        $label = match ($tipoEvento) {
            'SENTENÇA'              => 'Sentença detectada',
            'ACÓRDÃO'               => 'Acórdão detectado',
            'DECISÃO_SIGNIFICATIVA' => 'Decisão significativa detectada',
            default                 => 'Evento significativo detectado',
        };

        $titulo  = "VIGÍLIA: {$label} — processo {$andamento->processo_pasta}";
        $mensagem = mb_substr($andamento->descricao, 0, 200);
        $link    = '/vigilia?tab=obrigacoes&processo=' . $andamento->processo_pasta;

        // Notificar sócio (Rafael = user_id 1)
        DB::table('notifications_intranet')->insert([
            'user_id'    => 1,
            'tipo'       => 'vigilia_obrigacao',
            'titulo'     => $titulo,
            'mensagem'   => $mensagem,
            'link'       => $link,
            'icone'      => '⚖️',
            'lida'       => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Notificar advogado responsável (se diferente do sócio)
        if ($advogadoUserId && $advogadoUserId !== 1) {
            DB::table('notifications_intranet')->insert([
                'user_id'    => $advogadoUserId,
                'tipo'       => 'vigilia_obrigacao',
                'titulo'     => "VIGÍLIA: {$label} no processo {$andamento->processo_pasta} — registre sua estratégia",
                'mensagem'   => $mensagem,
                'link'       => $link,
                'icone'      => '⚖️',
                'lida'       => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function carregarUserMap(): void
    {
        // datajuri_proprietario_id → users.id
        $this->userMap = DB::table('users')
            ->whereNotNull('datajuri_proprietario_id')
            ->pluck('id', 'datajuri_proprietario_id')
            ->toArray();
    }
}
