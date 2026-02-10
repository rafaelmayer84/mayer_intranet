<?php

namespace App\Console\Commands;

use App\Services\DataJuriService;
use Illuminate\Console\Command;

class DataJuriTest extends Command
{
    protected $signature = 'datajuri:test';
    protected $description = 'Testa autenticação e conexão com API DataJuri';

    public function handle(DataJuriService $service)
    {
        $this->info('🔍 Testando conexão com DataJuri API...');
        $this->newLine();

        // 1. Verificar credenciais no .env
        $this->info('1️⃣  Verificando credenciais:');
        $credentials = [
            'DATAJURI_CLIENT_ID' => config('services.datajuri.client_id'),
            'DATAJURI_SECRET_ID' => config('services.datajuri.secret_id'),
            'DATAJURI_USERNAME' => config('services.datajuri.username'),
            'DATAJURI_PASSWORD' => config('services.datajuri.password'),
        ];

        foreach ($credentials as $key => $value) {
            if (empty($value)) {
                $this->error("   ❌ {$key} não configurado");
            } else {
                $maskedValue = $key === 'DATAJURI_PASSWORD' 
                    ? str_repeat('*', strlen($value))
                    : substr($value, 0, 10) . '...';
                $this->info("   ✅ {$key}: {$maskedValue}");
            }
        }

        if (in_array(true, array_map('empty', $credentials), true)) {
            $this->newLine();
            $this->error('❌ Configure todas as credenciais no arquivo .env antes de continuar');
            return 1;
        }

        $this->newLine();

        // 2. Testar autenticação
        $this->info('2️⃣  Testando autenticação OAuth2...');
        try {
            $token = $service->authenticate();
            $this->info("   ✅ Token obtido: " . substr($token, 0, 30) . '...');
        } catch (\Exception $e) {
            $this->error("   ❌ Falha na autenticação: {$e->getMessage()}");
            return 1;
        }

        $this->newLine();

        // 3. Buscar módulos disponíveis
        $this->info('3️⃣  Buscando módulos disponíveis...');
        try {
            $modulos = $service->getModulos();
            if (empty($modulos)) {
                $this->warn('   ⚠️  Nenhum módulo retornado pela API');
            } else {
                $this->info("   ✅ Módulos encontrados: " . count($modulos));
                $this->table(['Módulo'], array_map(fn($m) => [$m], array_slice($modulos, 0, 10)));
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Erro ao buscar módulos: {$e->getMessage()}");
        }

        $this->newLine();

        // 4. Testar busca de Pessoa
        $this->info('4️⃣  Testando busca de Pessoas (primeira página)...');
        try {
            $resultado = $service->buscarModuloPagina('Pessoa', 1, 5);
            $this->info("   ✅ Total de pessoas: {$resultado['listSize']}");
            $this->info("   ✅ Registros na página: " . count($resultado['rows']));
            
            if (!empty($resultado['rows'])) {
                $primeiraPessoa = $resultado['rows'][0];
                $this->info("   📋 Primeira pessoa: " . ($primeiraPessoa['nome'] ?? 'Sem nome'));
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Erro ao buscar pessoas: {$e->getMessage()}");
        }

        $this->newLine();
        $this->info('✅ Teste concluído com sucesso!');
        $this->newLine();

        return 0;
    }
}
