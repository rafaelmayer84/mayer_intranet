<?php

namespace App\Services;
use App\Models\Movimento;

use App\Models\ContaReceber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Caso (B): DataJuri retorna dados, mas banco local está com contas_receber=0.
 * Este service implementa sync robusta e auditável.
 * CORRIGIDO: Mapeia apenas para colunas que existem na tabela
 */
class SyncService
{
    public function __construct(protected DataJuriService $dataJuriService)
    {
    }

    public function sincronizarContasReceber(bool $dryRun = false, int $limit = 0, int $chunk = 200): array
    {
        $created = 0;
        $updated = 0;
        $ignored = 0;
        $errors = [];

        // Buscar todas as páginas de contas a receber
        $items = [];
        $page = 1;
        $pageSize = 100;
        $maxPages = 999;
        
        while ($page <= $maxPages) {
            $result = $this->dataJuriService->getContasReceber($page, $pageSize);
            
            if (!isset($result['rows']) || !is_array($result['rows'])) {
                break;
            }
            
            $items = array_merge($items, $result['rows']);
            
            // Verificar se há mais páginas
            $listSize = $result['listSize'] ?? 0;
            if (count($items) >= $listSize) {
                break;
            }
            
            $page++;
        }

        if (!is_array($items)) {
            return [
                'success' => false,
                'dryRun' => $dryRun,
                'created' => 0,
                'updated' => 0,
                'ignored' => 0,
                'errors' => ['Payload inválido: não é array'],
            ];
        }

        if ($limit > 0) {
            $items = array_slice($items, 0, $limit);
        }

        $dbg = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/sync_debug.log'),
        ]);

        $dbg->info('[SYNC_CONTAS_RECEBER] start', [
            'dryRun' => $dryRun,
            'count' => count($items),
            'chunk' => $chunk,
        ]);

        $buffer = [];
        foreach ($items as $it) {
            $buffer[] = $it;
            if (count($buffer) >= $chunk) {
                $res = $this->processaLote($buffer, $dryRun, $dbg);
                $created += $res['created'];
                $updated += $res['updated'];
                $ignored += $res['ignored'];
                $errors = array_merge($errors, $res['errors']);
                $buffer = [];
            }
        }

        if (!empty($buffer)) {
            $res = $this->processaLote($buffer, $dryRun, $dbg);
            $created += $res['created'];
            $updated += $res['updated'];
            $ignored += $res['ignored'];
            $errors = array_merge($errors, $res['errors']);
        }

        $dbg->info('[SYNC_CONTAS_RECEBER] end', [
            'dryRun' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'ignored' => $ignored,
            'errors_count' => count($errors),
        ]);

        return [
            'success' => count($errors) === 0,
            'dryRun' => $dryRun,
            'created' => $created,
            'updated' => $updated,
            'ignored' => $ignored,
            'errors' => $errors,
        ];
    }

    private function processaLote(array $items, bool $dryRun, $dbg): array
    {
        $created = 0;
        $updated = 0;
        $ignored = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($items as $it) {
                $mapped = $this->mapContaReceber($it);

                if (empty($mapped['datajuri_id'])) {
                    $ignored++;
                    continue;
                }

                if ($dryRun) {
                    $exists = ContaReceber::query()->where('datajuri_id', $mapped['datajuri_id'])->exists();
                    $exists ? $updated++ : $created++;
                    continue;
                }

                try {
                    $row = ContaReceber::query()->firstOrNew(['datajuri_id' => $mapped['datajuri_id']]);
                    $isNew = !$row->exists;
                    $row->fill($mapped);
                    $row->save();

                    $isNew ? $created++ : $updated++;
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                    $dbg->error('[SYNC_CONTAS_RECEBER] erro item', [
                        'datajuri_id' => $mapped['datajuri_id'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = $e->getMessage();
            $dbg->error('[SYNC_CONTAS_RECEBER] erro lote', ['error' => $e->getMessage()]);
        }

        return compact('created', 'updated', 'ignored', 'errors');
    }

    /**
     * Sincronizar Movimentos em batch (por página)
     * Chamado pelo SyncController para sincronizar movimentos de forma paginada
     */
    public function syncMovimentosBatch(int $ano = 0, int $page = 1, int $pageSize = 200): array
    {
        $created = 0;
        $updated = 0;
        $ignored = 0;
        $errors = [];

        // Buscar página de movimentos
        $result = $this->dataJuriService->buscarModuloPagina('Movimento', $page, $pageSize);
        
        if (!isset($result['rows']) || !is_array($result['rows'])) {
            return [
                'success' => false,
                'page' => $page,
                'pageSize' => $pageSize,
                'created' => 0,
                'updated' => 0,
                'ignored' => 0,
                'errors' => ['Payload inválido: não é array'],
            ];
        }

        $items = $result['rows'];
        $listSize = $result['listSize'] ?? 0;

        $dbg = Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/sync_debug.log'),
        ]);

        $dbg->info('[SYNC_MOVIMENTOS_BATCH] start', [
            'page' => $page,
            'pageSize' => $pageSize,
            'count' => count($items),
            'listSize' => $listSize,
        ]);

        // Processar items
        $buffer = [];
        foreach ($items as $it) {
            $buffer[] = $it;
            if (count($buffer) >= 50) {
                $res = $this->processaLoteMovimentos($buffer, $dbg);
                $created += $res['created'];
                $updated += $res['updated'];
                $ignored += $res['ignored'];
                $errors = array_merge($errors, $res['errors']);
                $buffer = [];
            }
        }

        if (!empty($buffer)) {
            $res = $this->processaLoteMovimentos($buffer, $dbg);
            $created += $res['created'];
            $updated += $res['updated'];
            $ignored += $res['ignored'];
            $errors = array_merge($errors, $res['errors']);
        }

        $dbg->info('[SYNC_MOVIMENTOS_BATCH] end', [
            'page' => $page,
            'created' => $created,
            'updated' => $updated,
            'ignored' => $ignored,
            'errors_count' => count($errors),
        ]);

        return [
            'success' => count($errors) === 0,
            'page' => $page,
            'pageSize' => $pageSize,
            'listSize' => $listSize,
            'created' => $created,
            'updated' => $updated,
            'ignored' => $ignored,
            'errors' => $errors,
        ];
    }

    private function processaLoteMovimentos(array $items, $dbg): array
    {
        $created = 0;
        $updated = 0;
        $ignored = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($items as $it) {
                // Placeholder: apenas log por enquanto
                // Implementar mapeamento de Movimento quando necessário
                $ignored++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $errors[] = $e->getMessage();
            $dbg->error('[SYNC_MOVIMENTOS_BATCH] erro lote', ['error' => $e->getMessage()]);
        }

        return compact('created', 'updated', 'ignored', 'errors');
    }

    private function truncateObservacao(string $obs, int $maxLen = 1000): string
    {
        if (strlen($obs) > $maxLen) {
            \Log::warning('Observação truncada de ' . strlen($obs) . ' para ' . $maxLen . ' caracteres');
            return substr($obs, 0, $maxLen);
        }
        return $obs;
    }
    private function mapContaReceber(array $it): array
    {
        $trimOrNull = function ($v) {
            if ($v === null) return null;
            if (is_string($v)) {
                $v = trim($v);
                return $v === '' ? null : $v;
            }
            return $v;
        };

        $parseDate = function ($dateStr) {
            if (!$dateStr) return null;
            // Tenta converter formato DD/MM/YYYY para YYYY-MM-DD
            if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $dateStr, $m)) {
                return $m[3] . '-' . $m[2] . '-' . $m[1];
            }
            return $dateStr; // Retorna como está se não conseguir parsear
        };

        $pessoaNome = null;
        if (isset($it['pessoa']) && is_array($it['pessoa'])) {
            $pessoaNome = $it['pessoa']['nome'] ?? $it['pessoa']['name'] ?? null;
        }
        $pessoaNome = $pessoaNome ?? ($it['pessoa.nome'] ?? $it['pessoaNome'] ?? $it['clienteNome'] ?? $it['descricao'] ?? null);

        $dataVenc = $it['dataVencimento'] ?? $it['data_vencimento'] ?? null;
        $dataPag  = $it['dataPagamento'] ?? $it['data_pagamento'] ?? null;

        // Mapear APENAS para colunas que existem na tabela contas_receber
        // Schema real: id, datajuri_id, cliente, valor, data_vencimento, data_pagamento, status, tipo, created_at, updated_at
        return [
            'datajuri_id' => (string) ($it['id'] ?? $it['datajuri_id'] ?? ''),
            'valor' => (float) ($it['valor'] ?? 0),
            'data_vencimento' => $parseDate($dataVenc),
            'data_pagamento' => $parseDate($dataPag),
            'status' => $trimOrNull($it['status'] ?? null),
            'cliente' => $trimOrNull($pessoaNome) ?? '(Sem cliente)',
            'tipo' => $trimOrNull($it['tipo'] ?? 'normal'),
        ];
    }

    /**
     * Obter resumo de movimentos por período
     * @param int $mes
     * @param int $ano
     * @return array
     */
    public function getResumoMovimentos(int $mes, int $ano): array
    {
        $movimentos = Movimento::where('mes', $mes)
            ->where('ano', $ano)
            ->get();

        $resumo = [
            'receita_pf' => 0,
            'qtd_pf' => 0,
            'receita_pj' => 0,
            'qtd_pj' => 0,
            'receita_financeira' => 0,
            'qtd_financeira' => 0,
            'pendentes_classificacao' => 0,
            'qtd_pendentes_classificacao' => 0,
        ];

        foreach ($movimentos as $mov) {
            if ($mov->classificacao === 'RECEITA_PF') {
                $resumo['receita_pf'] += $mov->valor;
                $resumo['qtd_pf']++;
            } elseif ($mov->classificacao === 'RECEITA_PJ') {
                $resumo['receita_pj'] += $mov->valor;
                $resumo['qtd_pj']++;
            } elseif ($mov->classificacao === 'RECEITA_FINANCEIRA') {
                $resumo['receita_financeira'] += $mov->valor;
                $resumo['qtd_financeira']++;
            } elseif ($mov->classificacao === 'PENDENTE_CLASSIFICACAO') {
                $resumo['pendentes_classificacao'] += $mov->valor;
                $resumo['qtd_pendentes_classificacao']++;
            }
        }

        return $resumo;
    }
}
