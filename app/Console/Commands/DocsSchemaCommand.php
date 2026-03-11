<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocsSchemaCommand extends Command
{
    protected $signature = 'docs:schema {--output=storage : Onde salvar (storage ou stdout)}';
    protected $description = 'Exporta schema completo do banco de dados para markdown';

    public function handle()
    {
        $this->info('=== RESULTADOS! — Schema Export ===');
        $this->info('Banco: ' . config('database.connections.mysql.database'));
        $this->info('Data: ' . now('America/Sao_Paulo')->format('d/m/Y H:i'));
        $this->newLine();

        $tables = $this->getTables();
        $this->info("Tabelas encontradas: " . count($tables));

        $output = [];
        $output[] = "# RESULTADOS! — Schema do Banco de Dados";
        $output[] = "";
        $output[] = "**Banco:** " . config('database.connections.mysql.database');
        $output[] = "**Gerado em:** " . now('America/Sao_Paulo')->format('d/m/Y H:i:s') . " BRT";
        $output[] = "**Tabelas:** " . count($tables);
        $output[] = "";

        $output[] = "## Indice";
        $output[] = "";
        foreach ($tables as $i => $table) {
            $count = $this->getRowCount($table);
            $output[] = ($i + 1) . ". **{$table}** ({$count} registros)";
        }
        $output[] = "";
        $output[] = "---";
        $output[] = "";

        $bar = $this->output->createProgressBar(count($tables));
        $bar->start();

        foreach ($tables as $table) {
            $columns = Schema::getColumnListing($table);
            $count = $this->getRowCount($table);

            $output[] = "## {$table}";
            $output[] = "";
            $output[] = "**Registros:** {$count}";
            $output[] = "";
            $output[] = "| # | Coluna | Tipo | Nullable | Default |";
            $output[] = "|---|--------|------|----------|---------|";

            foreach ($columns as $idx => $col) {
                $type = $this->getColumnType($table, $col);
                $nullable = $this->isNullable($table, $col) ? 'SIM' : 'NAO';
                $default = $this->getDefault($table, $col);

                $output[] = "| " . ($idx + 1) . " | `{$col}` | {$type} | {$nullable} | {$default} |";
            }

            $output[] = "";

            $indexes = $this->getIndexes($table);
            if (!empty($indexes)) {
                $output[] = "**Indices:**";
                foreach ($indexes as $index) {
                    $unique = $index->Non_unique == 0 ? ' (UNIQUE)' : '';
                    $output[] = "- `{$index->Key_name}` -> `{$index->Column_name}`{$unique}";
                }
                $output[] = "";
            }

            $output[] = "---";
            $output[] = "";

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $markdown = implode("\n", $output);

        if ($this->option('output') === 'stdout') {
            $this->line($markdown);
        } else {
            $path = storage_path('app/docs');
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }

            $date = now('America/Sao_Paulo')->format('Y-m-d_His');
            $file = "{$path}/schema_{$date}.md";
            file_put_contents($file, $markdown);

            $latest = "{$path}/schema_latest.md";
            file_put_contents($latest, $markdown);

            $this->info("Schema exportado para: {$file}");
            $this->info("Copia latest em: {$latest}");
        }

        $this->newLine();
        $this->info("=== Resumo ===");
        $this->table(
            ['Metrica', 'Valor'],
            [
                ['Tabelas', count($tables)],
                ['Total colunas', collect($tables)->sum(fn($t) => count(Schema::getColumnListing($t)))],
                ['Maior tabela', collect($tables)->sortByDesc(fn($t) => $this->getRowCount($t))->first()
                    . ' (' . number_format(collect($tables)->max(fn($t) => $this->getRowCount($t))) . ' registros)'],
            ]
        );

        return Command::SUCCESS;
    }

    private function getTables(): array
    {
        $database = config('database.connections.mysql.database');
        $tables = DB::select('SHOW TABLES');
        $key = "Tables_in_{$database}";

        return collect($tables)
            ->pluck($key)
            ->filter(fn($t) => !str_starts_with($t, 'telescope_') && $t !== 'migrations')
            ->sort()
            ->values()
            ->toArray();
    }

    private function getRowCount(string $table): int
    {
        try {
            $result = DB::selectOne(
                "SELECT TABLE_ROWS FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
                [config('database.connections.mysql.database'), $table]
            );
            return $result->TABLE_ROWS ?? 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function getColumnType(string $table, string $column): string
    {
        try {
            $database = config('database.connections.mysql.database');
            $result = DB::selectOne(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$database, $table, $column]
            );
            return $result->COLUMN_TYPE ?? 'unknown';
        } catch (\Throwable $e) {
            return 'unknown';
        }
    }

    private function isNullable(string $table, string $column): bool
    {
        try {
            $database = config('database.connections.mysql.database');
            $result = DB::selectOne(
                "SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$database, $table, $column]
            );
            return ($result->IS_NULLABLE ?? 'YES') === 'YES';
        } catch (\Throwable $e) {
            return true;
        }
    }

    private function getDefault(string $table, string $column): string
    {
        try {
            $database = config('database.connections.mysql.database');
            $result = DB::selectOne(
                "SELECT COLUMN_DEFAULT FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$database, $table, $column]
            );
            $val = $result->COLUMN_DEFAULT;
            return $val === null ? 'NULL' : "`{$val}`";
        } catch (\Throwable $e) {
            return '—';
        }
    }

    private function getIndexes(string $table): array
    {
        try {
            return DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name != 'PRIMARY'");
        } catch (\Throwable $e) {
            return [];
        }
    }
}
