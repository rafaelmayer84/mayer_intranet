<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriService;
use Throwable;

class DataJuriSmokeTest extends Command
{
    protected $signature = 'datajuri:smoke {--modulo=Pessoa} {--page=1} {--pageSize=5}';
    protected $description = 'Smoke test DataJuri OAuth + Bearer (modulos + entidades)';

    public function handle(DataJuriService $dj): int
    {
        $this->info('== DataJuri Smoke Test ==');

        try {
            $token = $dj->getToken();
            $this->line('Token: ' . ($token ? ('OK (len=' . strlen($token) . ')') : 'NULL'));
            if (!$token) {
                $this->error('Falha ao obter token (OAuth). Verifique config/services.php e cache.');
                return self::FAILURE;
            }

            $modulos = $dj->getModulos();
            $this->info('GET /v1/modulos: OK (type=' . gettype($modulos) . ')');

            $modulo = (string)$this->option('modulo');
            $page = (int)$this->option('page');
            $pageSize = (int)$this->option('pageSize');

            $ent = $dj->buscarModuloPagina($modulo, $page, $pageSize, []);
            $rows = $ent['rows'] ?? [];
            $listSize = $ent['listSize'] ?? null;

            $this->info("GET /v1/entidades/{$modulo}: OK (rows=" . (is_array($rows) ? count($rows) : 0) . ", listSize={$listSize})");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Erro: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
