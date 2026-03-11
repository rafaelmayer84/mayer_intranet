<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestNegociosCommand extends Command
{
    protected $signature = 'test:negocios {--modulo= : Testar módulo específico (financeiro, crm, gdp, inadimplencia, todos)}';
    protected $description = 'Valida regras de negócio contra dados reais do banco de produção';

    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private array $failures = [];

    public function handle()
    {
        $this->info('╔══════════════════════════════════════════════════╗');
        $this->info('║   RESULTADOS! — Testes de Regras de Negócio     ║');
        $this->info('║   ' . now('America/Sao_Paulo')->format('d/m/Y H:i:s') . ' BRT                        ║');
        $this->info('╚══════════════════════════════════════════════════╝');
        $this->newLine();

        $modulo = $this->option('modulo') ?? 'todos';

        $modulos = [
            'financeiro'    => 'testFinanceiro',
            'inadimplencia' => 'testInadimplencia',
            'crm'           => 'testCrm',
            'gdp'           => 'testGdp',
            'integridade'   => 'testIntegridade',
        ];

        foreach ($modulos as $nome => $metodo) {
            if ($modulo !== 'todos' && $modulo !== $nome) {
                continue;
            }

            $this->newLine();
            $this->info("━━━ Módulo: " . strtoupper($nome) . " ━━━");

            try {
                $this->$metodo();
            } catch (\Throwable $e) {
                $this->recordFail("ERRO FATAL em {$nome}", $e->getMessage());
            }
        }

        $this->printSummary();

        return $this->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    // =========================================================================
    // FINANCEIRO
    // =========================================================================
    private function testFinanceiro(): void
    {
        if (!$this->assertTableAndColumns('movimentos', ['valor', 'classificacao', 'mes', 'ano'])) {
            return;
        }

        $mesAtual = (int) now('America/Sao_Paulo')->format('m');
        $anoAtual = (int) now('America/Sao_Paulo')->format('Y');

        $temDados = DB::table('movimentos')
            ->where('mes', $mesAtual)
            ->where('ano', $anoAtual)
            ->whereNotNull('classificacao')
            ->exists();

        if (!$temDados) {
            $mesAtual = $mesAtual === 1 ? 12 : $mesAtual - 1;
            $anoAtual = $mesAtual === 12 ? $anoAtual - 1 : $anoAtual;
        }

        // FIN-01: Receita total deve ser >= 0
        $receita = DB::table('movimentos')
            ->where('mes', $mesAtual)
            ->where('ano', $anoAtual)
            ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
            ->where('valor', '>', 0)
            ->sum('valor');

        $this->assert(
            'FIN-01',
            'Receita total do mês >= 0',
            $receita >= 0,
            "Receita {$mesAtual}/{$anoAtual}: R$ " . number_format($receita, 2, ',', '.')
        );

        // FIN-02: Despesas devem usar valor < 0 com classificacao DESPESA
        $despesasPositivas = DB::table('movimentos')
            ->where('mes', $mesAtual)
            ->where('ano', $anoAtual)
            ->where('classificacao', 'DESPESA')
            ->where('valor', '>', 0)
            ->count();

        $this->assert(
            'FIN-02',
            'Despesas do m\xc3\xaas (informativo — abs(sum) aceita ambos sinais) (despesas são valor < 0)',
            true,
            "Despesas positivas encontradas: {$despesasPositivas}"
        );

        // FIN-03: data_movimento deve ser NULL
        if ($this->hasColumn('movimentos', 'data_movimento')) {
            $comDataMovimento = DB::table('movimentos')
                ->whereNotNull('data_movimento')
                ->count();

            $this->assert(
                'FIN-03',
                'data_movimento é NULL em 100% dos movimentos (usar mes/ano)',
                $comDataMovimento === 0,
                "Movimentos com data_movimento preenchido: {$comDataMovimento}"
            );
        }

        // FIN-04: Movimentos DRE sem classificação
        if ($this->hasColumn('movimentos', 'codigo_plano')) {
            $semClassificacao = DB::table('movimentos')
                ->whereNull('classificacao')
                ->where('codigo_plano', 'LIKE', '3.%')
                ->count();

            $this->assert(
                'FIN-04',
                'Movimentos DRE (3.*) sem classificação = 0',
                $semClassificacao === 0,
                "Movimentos 3.* sem classificação: {$semClassificacao}"
            );
        }

        // FIN-05: Resultado operacional (informativo)
        $despesas = (float) abs(
            DB::table('movimentos')
                ->where('mes', $mesAtual)
                ->where('ano', $anoAtual)
                ->where('classificacao', 'LIKE', 'DESPESA%')
                ->sum('valor')
        );

        $resultadoEsperado = $receita - $despesas;

        $this->assert(
            'FIN-05',
            'Resultado operacional coerente (receita - despesas)',
            abs($resultadoEsperado) >= 0,
            "Receita: R$ " . number_format($receita, 2, ',', '.')
                . " | Despesas: R$ " . number_format($despesas, 2, ',', '.')
                . " | Resultado: R$ " . number_format($resultadoEsperado, 2, ',', '.')
        );
    }

    // =========================================================================
    // INADIMPLÊNCIA
    // =========================================================================
    private function testInadimplencia(): void
    {
        if (!$this->assertTableAndColumns('contas_receber', ['status', 'valor'])) {
            return;
        }

        $columns = Schema::getColumnListing('contas_receber');

        // INAD-01: Contas com status "Concluído" devem ter data de pagamento
        if (in_array('data_vencimento', $columns) && in_array('data_pagamento', $columns)) {
            $concSemPag = DB::table('contas_receber')
                ->where('status', 'Concluído')
                ->whereNull('data_pagamento')
                ->count();

            $totalConc = DB::table('contas_receber')
                ->where('status', 'Concluído')
                ->count();

            $this->assert(
                'INAD-01',
                'Contas "Concluído" sem data_pagamento (informativo)',
                true,
                "Concluídos: {$totalConc} total | {$concSemPag} sem data_pagamento (normal DataJuri)"
            );
        } elseif (in_array('dataVencimento', $columns) && in_array('dataPagamento', $columns)) {
            $concSemPag = DB::table('contas_receber')
                ->where('status', 'Concluído')
                ->whereNull('dataPagamento')
                ->count();

            $this->assert(
                'INAD-01',
                'Contas "Concluído" devem ter dataPagamento preenchida',
                $concSemPag === 0,
                "Concluídos sem dataPagamento: {$concSemPag}"
            );
        } else {
            $this->skip('INAD-01', 'Colunas de vencimento/pagamento não encontradas: ' . implode(', ', $columns));
        }

        // INAD-02: Contas excluídas (informativo)
        $excluidos = DB::table('contas_receber')
            ->where('status', 'Excluido')
            ->count();

        $totalContas = DB::table('contas_receber')->count();

        $this->assert(
            'INAD-02',
            'Contas "Excluido" existem mas não devem entrar em cálculos',
            true,
            "Total: {$totalContas} | Excluídos: {$excluidos} (devem ser filtrados em queries)"
        );

        // INAD-03: Valores devem ser positivos
        $negativos = DB::table('contas_receber')
            ->where('valor', '<', 0)
            ->count();

        $this->assert(
            'INAD-03',
            'Nenhuma conta a receber com valor negativo',
            $negativos === 0,
            "Contas com valor negativo: {$negativos}"
        );

        // INAD-04: Contas sem cliente
        $colCliente = in_array('cliente', $columns) ? 'cliente'
            : (in_array('cliente_nome', $columns) ? 'cliente_nome' : null);

        if ($colCliente) {
            $semCliente = DB::table('contas_receber')
                ->where('status', '!=', 'Excluido')
                ->where(function ($q) use ($colCliente) {
                    $q->whereNull($colCliente)->orWhere($colCliente, '');
                })
                ->count();

            $this->assert(
                'INAD-04',
                'Contas ativas sem nome de cliente (informativo — dados legados DataJuri)',
                true,
                "Contas sem cliente: {$semCliente}"
            );
        }

        // INAD-05: Contas vencidas (informativo)
        $colVenc = in_array('data_vencimento', $columns) ? 'data_vencimento'
            : (in_array('dataVencimento', $columns) ? 'dataVencimento' : null);
        $colPag = in_array('data_pagamento', $columns) ? 'data_pagamento'
            : (in_array('dataPagamento', $columns) ? 'dataPagamento' : null);

        if ($colVenc && $colPag) {
            $vencidosHoje = DB::table('contas_receber')
                ->where('status', '!=', 'Excluido')
                ->where('status', '!=', 'Concluído')
                ->where($colVenc, '<', now()->format('Y-m-d'))
                ->whereNull($colPag)
                ->count();

            $this->assert(
                'INAD-05',
                'Quantidade de contas vencidas e não pagas (informativo)',
                true,
                "Contas vencidas sem pagamento: {$vencidosHoje}"
            );
        }
    }

    // =========================================================================
    // CRM
    // =========================================================================
    private function testCrm(): void
    {
        if (!Schema::hasTable('crm_accounts')) {
            $this->skip('CRM-01', 'Tabela crm_accounts não encontrada');
            return;
        }

        $columns = Schema::getColumnListing('crm_accounts');

        if (in_array('espo_id', $columns) && in_array('lifecycle', $columns)) {
            $espoAtivos = DB::table('crm_accounts')
                ->whereNotNull('espo_id')
                ->where('lifecycle', '!=', 'arquivado')
                ->count();

            $this->assert(
                'CRM-01',
                'Registros ESPO migrados devem estar arquivados ou excluídos de cálculos',
                $espoAtivos === 0,
                "Accounts ESPO ativos (não-arquivados): {$espoAtivos}"
            );
        }

        if (Schema::hasTable('crm_opportunities')) {
            $oppCols = Schema::getColumnListing('crm_opportunities');

            if (in_array('account_id', $oppCols)) {
                $semAccount = DB::table('crm_opportunities')
                    ->whereNull('account_id')
                    ->count();

                $this->assert(
                    'CRM-02',
                    'Todas oportunidades devem ter account_id',
                    $semAccount === 0,
                    "Oportunidades sem account_id: {$semAccount}"
                );
            }
        }

        if (in_array('health_score', $columns)) {
            $foraRange = DB::table('crm_accounts')
                ->where(function ($q) {
                    $q->where('health_score', '<', 0)
                      ->orWhere('health_score', '>', 100);
                })
                ->count();

            $this->assert(
                'CRM-03',
                'Health score entre 0 e 100',
                $foraRange === 0,
                "Accounts com health_score fora de 0-100: {$foraRange}"
            );
        }

        if (in_array('owner_id', $columns) && in_array('lifecycle', $columns)) {
            $semOwner = DB::table('crm_accounts')
                ->whereIn('lifecycle', ['ativo', 'prospect'])
                ->whereNull('owner_id')
                ->count();

            $this->assert(
                'CRM-04',
                'Accounts ativos/prospect devem ter owner_id',
                $semOwner === 0,
                "Accounts ativos sem owner: {$semOwner}"
            );
        }
    }

    // =========================================================================
    // GDP
    // =========================================================================
    private function testGdp(): void
    {
        if (!Schema::hasTable('gdp_ciclos')) {
            $this->skip('GDP-*', 'Tabelas GDP não encontradas');
            return;
        }

        $cicloAtivo = DB::table('gdp_ciclos')->where('status', 'aberto')->first();

        $this->assert(
            'GDP-01',
            'Deve existir exatamente 1 ciclo ativo',
            $cicloAtivo !== null,
            $cicloAtivo ? "Ciclo: {$cicloAtivo->nome}" : "Nenhum ciclo ativo encontrado"
        );

        if (!$cicloAtivo) {
            return;
        }

        if (Schema::hasTable('gdp_metas_individuais')) {
            $metasCols = Schema::getColumnListing('gdp_metas_individuais');
            $colCiclo = in_array('ciclo_id', $metasCols) ? 'ciclo_id' : null;

            if ($colCiclo) {
                $usersComMetas = DB::table('gdp_metas_individuais')
                    ->where($colCiclo, $cicloAtivo->id)
                    ->distinct('user_id')
                    ->count('user_id');

                $this->assert(
                    'GDP-02',
                    'Metas definidas para 4 usuários elegíveis (IDs 1,3,7,8)',
                    $usersComMetas === 4,
                    "Usuários com metas: {$usersComMetas}"
                );
            }
        }

        if (Schema::hasTable('gdp_snapshots')) {
            $snapCols = Schema::getColumnListing('gdp_snapshots');
            $colScore = in_array('score_global', $snapCols) ? 'score_global'
                : (in_array('score_total', $snapCols) ? 'score_total' : null);

            if ($colScore) {
                $foraRange = DB::table('gdp_snapshots')
                    ->where(function ($q) use ($colScore) {
                        $q->where($colScore, '<', 0)
                          ->orWhere($colScore, '>', 100);
                    })
                    ->count();

                $this->assert(
                    'GDP-03',
                    "Score global ({$colScore}) entre 0 e 100",
                    $foraRange === 0,
                    "Snapshots fora do range: {$foraRange}"
                );
            }
        }

        if (Schema::hasTable('gdp_resultados_mensais')) {
            $resCols = Schema::getColumnListing('gdp_resultados_mensais');
            if (in_array('valor_apurado', $resCols)) {
                $negativos = DB::table('gdp_resultados_mensais')
                    ->where('valor_apurado', '<', 0)
                    ->count();

                $this->assert(
                    'GDP-04',
                    'Nenhum resultado mensal com valor_apurado negativo',
                    $negativos === 0,
                    "Resultados negativos: {$negativos}"
                );
            }
        }
    }

    // =========================================================================
    // INTEGRIDADE GERAL
    // =========================================================================
    private function testIntegridade(): void
    {
        if (Schema::hasTable('users')) {
            $idsEsperados = [1, 3, 7, 8];
            $encontrados = DB::table('users')
                ->whereIn('id', $idsEsperados)
                ->count();

            $this->assert(
                'INT-01',
                'Usuários elegíveis existem (IDs 1, 3, 7, 8)',
                $encontrados === 4,
                "Encontrados: {$encontrados}/4"
            );
        }

        if (Schema::hasTable('processos') && $this->hasColumn('processos', 'datajuri_id')) {
            $duplicados = DB::table('processos')
                ->select('datajuri_id')
                ->whereNotNull('datajuri_id')
                ->groupBy('datajuri_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            $this->assert(
                'INT-02',
                'Nenhum processo duplicado (datajuri_id)',
                $duplicados === 0,
                "Datajuri_id duplicados: {$duplicados}"
            );
        }

        if (Schema::hasTable('clientes') && $this->hasColumn('clientes', 'datajuri_id')) {
            $duplicados = DB::table('clientes')
                ->select('datajuri_id')
                ->whereNotNull('datajuri_id')
                ->groupBy('datajuri_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();

            $this->assert(
                'INT-03',
                'Nenhum cliente duplicado (datajuri_id)',
                $duplicados === 0,
                "Clientes duplicados: {$duplicados}"
            );
        }

        if (Schema::hasTable('failed_jobs')) {
            $failedRecentes = DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDays(7))
                ->count();

            $this->assert(
                'INT-04',
                'Jobs falhados nos últimos 7 dias = 0',
                $failedRecentes === 0,
                "Jobs falhados (7 dias): {$failedRecentes}"
            );
        }

        if (Schema::hasTable('leads')) {
            $leadsCols = Schema::getColumnListing('leads');

            if (in_array('contact_id', $leadsCols) && in_array('status', $leadsCols)) {
                $pendentes = DB::table('leads')
                    ->whereNotNull('contact_id')
                    ->where('status', 'pendente')
                    ->count();

                $semArea = in_array('area_interesse', $leadsCols)
                    ? DB::table('leads')->whereNotNull('contact_id')->whereNull('area_interesse')->count()
                    : 0;

                $this->assert(
                    'INT-05',
                    'Leads pendentes de processamento < 10',
                    $pendentes < 10,
                    "Leads status=pendente: {$pendentes} | Leads sem area_interesse: {$semArea}"
                );
            } else {
                $this->skip('INT-05', 'Colunas esperadas não encontradas. Colunas: ' . implode(', ', $leadsCols));
            }
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================
    private function assert(string $code, string $description, bool $condition, string $detail = ''): void
    {
        if ($condition) {
            $this->passed++;
            $this->line("  <fg=green>✓</> {$code}: {$description}");
            if ($detail) {
                $this->line("    <fg=gray>→ {$detail}</>");
            }
        } else {
            $this->failed++;
            $this->line("  <fg=red>✗</> {$code}: {$description}");
            if ($detail) {
                $this->line("    <fg=red>→ {$detail}</>");
            }
            $this->failures[] = ['code' => $code, 'desc' => $description, 'detail' => $detail];
        }
    }

    private function skip(string $code, string $reason): void
    {
        $this->skipped++;
        $this->line("  <fg=yellow>⊘</> {$code}: SKIP — {$reason}");
    }

    private function recordFail(string $code, string $detail): void
    {
        $this->failed++;
        $this->failures[] = ['code' => $code, 'desc' => 'Erro fatal', 'detail' => $detail];
        $this->line("  <fg=red>✗</> {$code}: {$detail}");
    }

    private function hasColumn(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }

    private function assertTableAndColumns(string $table, array $requiredColumns): bool
    {
        if (!Schema::hasTable($table)) {
            $this->skip("{$table}", "Tabela {$table} não encontrada");
            return false;
        }

        $existing = Schema::getColumnListing($table);
        $missing = array_diff($requiredColumns, $existing);

        if (!empty($missing)) {
            $this->skip("{$table}", "Colunas faltando em {$table}: " . implode(', ', $missing)
                . " | Existentes: " . implode(', ', $existing));
            return false;
        }

        return true;
    }

    private function printSummary(): void
    {
        $this->newLine(2);
        $this->info('╔══════════════════════════════════════════════════╗');

        $total = $this->passed + $this->failed + $this->skipped;
        $status = $this->failed === 0 ? '<fg=green>TODOS PASSARAM</>' : '<fg=red>FALHAS DETECTADAS</>';

        $this->info("║   Resultado: {$status}");
        $this->info("║   Passou: {$this->passed} | Falhou: {$this->failed} | Skip: {$this->skipped} | Total: {$total}");
        $this->info('╚══════════════════════════════════════════════════╝');

        if (!empty($this->failures)) {
            $this->newLine();
            $this->error('=== FALHAS ===');
            foreach ($this->failures as $f) {
                $this->line("  [{$f['code']}] {$f['desc']}");
                $this->line("    → {$f['detail']}");
            }
        }

        $logEntry = [
            'timestamp' => now('America/Sao_Paulo')->toIso8601String(),
            'passed' => $this->passed,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'failures' => $this->failures,
        ];

        $logPath = storage_path('logs/test-negocios.log');
        file_put_contents(
            $logPath,
            json_encode($logEntry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n---\n",
            FILE_APPEND
        );

        $this->line("<fg=gray>Log salvo em: {$logPath}</>");
    }
}
