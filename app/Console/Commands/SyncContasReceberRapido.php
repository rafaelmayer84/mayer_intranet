<?php

namespace App\Console\Commands;

use App\Services\DataJuriService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncContasReceberRapido extends Command
{
    protected $signature = 'sync:contas-receber-rapido';
    protected $description = 'Sincroniza Contas a Receber do DataJuri (versão otimizada)';

    public function handle(DataJuriService $dj): int
    {
        $params = [
            'campos' => 'id,dataVencimento,valor,status,prazo,pessoa.nome,descricao,dataPagamento,pessoaId,clienteId,processoId,processo.pasta,dataCadastro',
        ];

        $page = 1;
        $pageSize = 100;
        $total = 0;
        $pagos = 0;

        $this->info("Iniciando sync de ContasReceber...");

        while (true) {
            $resultado = $dj->buscarModuloPagina('ContasReceber', $page, $pageSize, $params);
            $rows = $resultado['rows'] ?? [];

            if (empty($rows)) break;

            foreach ($rows as $row) {
                $id = $row['id'] ?? null;
                if (!$id) continue;

                $nome = $row['pessoa.nome'] ?? $row['pessoa'] ?? null;

                // Parse valor brasileiro
                $valorRaw = $row['valor'] ?? '0';
                if (is_string($valorRaw)) {
                    $valorRaw = strip_tags($valorRaw);
                    $valorRaw = str_replace('.', '', $valorRaw);
                    $valorRaw = str_replace(',', '.', $valorRaw);
                }
                $valor = (float) preg_replace('/[^0-9.\-]/', '', $valorRaw);

                // Parse data vencimento
                $dataVenc = $this->parseData($row['dataVencimento'] ?? null);

                // Parse data pagamento
                $dataPag = $this->parseData($row['dataPagamento'] ?? null);

                // Determinar status real baseado no campo 'prazo'
                $prazo = trim($row['prazo'] ?? '');
                $statusOriginal = $row['status'] ?? 'Desconhecido';

                // Se prazo = Concluído, a conta foi PAGA
                $isConcluido = (mb_strtolower($prazo) === 'concluído' || mb_strtolower($prazo) === 'concluido');

                if ($isConcluido) {
                    $statusFinal = 'Concluído';
                    // Se não veio dataPagamento da API, usar dataVencimento como fallback
                    if (!$dataPag) {
                        $dataPag = $dataVenc;
                    }
                    $pagos++;
                } else {
                    $statusFinal = $statusOriginal;
                }

                // Payload raw para auditoria
                $payloadRaw = json_encode($row, JSON_UNESCAPED_UNICODE);
                $payloadHash = hash('sha256', $payloadRaw);

                DB::table('contas_receber')->updateOrInsert(
                    ['datajuri_id' => $id],
                    [
                        'origem'              => 'datajuri',
                        'cliente'             => $nome,
                        'pessoa_datajuri_id'  => $row['pessoaId'] ?? null,
                        'cliente_datajuri_id' => ($row['clienteId'] ?? null) !== '' ? ($row['clienteId'] ?? null) : null,
                        'processo_datajuri_id'=> ($row['processoId'] ?? null) !== '' ? ($row['processoId'] ?? null) : null,
                        'descricao'           => $row['descricao'] ?? null,
                        'valor'               => $valor,
                        'status'              => $statusFinal,
                        'data_vencimento'     => $dataVenc,
                        'data_pagamento'      => $dataPag,
                        'payload_raw'         => $payloadRaw,
                        'payload_hash'        => $payloadHash,
                        'is_stale'            => 0,
                        'updated_at'          => now(),
                    ]
                );
                $total++;
            }

            $this->line("Página $page: " . count($rows) . " registros (total: $total)");

            if (count($rows) < $pageSize) break;
            $page++;
        }

        $this->info("✅ Sincronizados $total registros ($pagos pagos/concluídos)!");
        return self::SUCCESS;
    }

    private function parseData(?string $data): ?string
    {
        if (empty($data)) return null;
        $data = trim(strip_tags($data));
        // DD/MM/YYYY HH:MM ou DD/MM/YYYY
        if (preg_match('/(\d{2})\/(\d{2})\/(\d{4})/', $data, $m)) {
            return "{$m[3]}-{$m[2]}-{$m[1]}";
        }
        return null;
    }
}
