<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\DataJuriService;
use Illuminate\Support\Facades\Log;

/**
 * Comando para testar a conexão com a API DataJuri
 * 
 * Uso: php artisan datajuri:test
 * 
 * Este comando valida:
 * 1. Autenticação com a API
 * 2. Acesso aos módulos principais
 * 3. Estrutura das respostas
 */
class TestDataJuriConnection extends Command
{
    protected $signature = 'datajuri:test {--verbose : Mostrar detalhes completos}';
    protected $description = 'Testa a conexão com a API DataJuri e valida os módulos';

    private DataJuriService $dataJuri;
    private array $resultados = [];

    public function __construct(DataJuriService $dataJuri)
    {
        parent::__construct();
        $this->dataJuri = $dataJuri;
    }

    public function handle(): int
    {
        $this->info('');
        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║     TESTE DE CONEXÃO - API DATAJURI                    ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        $this->info('');

        // Teste 1: Autenticação
        $this->testarAutenticacao();

        // Teste 2: Módulos
        $this->testarModulos();

        // Teste 3: Estrutura das Respostas
        $this->testarEstruturaRespostas();

        // Resumo
        $this->mostrarResumo();

        return $this->todosTestesPassaram() ? 0 : 1;
    }

    private function testarAutenticacao(): void
    {
        $this->info('🔐 Testando Autenticação...');
        
        try {
            $token = $this->dataJuri->getToken();
            
            if ($token && strlen($token) > 50) {
                $this->resultados['autenticacao'] = true;
                $this->line('   ✅ Autenticação bem sucedida');
                
                if ($this->option('verbose')) {
                    $this->line('   Token: ' . substr($token, 0, 50) . '...');
                }
            } else {
                $this->resultados['autenticacao'] = false;
                $this->error('   ❌ Token inválido ou vazio');
            }
        } catch (\Exception $e) {
            $this->resultados['autenticacao'] = false;
            $this->error('   ❌ Erro: ' . $e->getMessage());
        }
        
        $this->info('');
    }

    private function testarModulos(): void
    {
        $this->info('📦 Testando Acesso aos Módulos...');
        
        $modulos = [
            'Usuario' => 'Advogados',
            'Processo' => 'Processos',
            'Atividade' => 'Atividades',
            'ContasReceber' => 'Contas a Receber',
            'HoraTrabalhada' => 'Horas Trabalhadas',
            'Movimento' => 'Movimentos',
        ];

        foreach ($modulos as $modulo => $descricao) {
            $resultado = $this->testarModulo($modulo, $descricao);
            $this->resultados['modulo_' . $modulo] = $resultado;
        }
        
        $this->info('');
    }

    private function testarModulo(string $modulo, string $descricao): bool
    {
        try {
            $token = $this->dataJuri->getToken();
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.datajuri.com.br/v1/entidades/{$modulo}?pagina=1&porPagina=1");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($response, true);
                $totalRegistros = $data['listSize'] ?? 0;
                $this->line("   ✅ {$descricao} ({$modulo}): HTTP 200 - {$totalRegistros} registros");
                return true;
            } else {
                $this->error("   ❌ {$descricao} ({$modulo}): HTTP {$httpCode}");
                return false;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ {$descricao} ({$modulo}): " . $e->getMessage());
            return false;
        }
    }

    private function testarEstruturaRespostas(): void
    {
        $this->info('🔍 Testando Estrutura das Respostas...');
        
        try {
            $token = $this->dataJuri->getToken();
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://api.datajuri.com.br/v1/entidades/Movimento?pagina=1&porPagina=1");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$token}",
                'Content-Type: application/json',
            ]);
            $response = curl_exec($ch);
            curl_close($ch);

            $data = json_decode($response, true);

            // Verificar chaves esperadas
            $chavesEsperadas = ['rows', 'listSize', 'pageSize', 'page'];
            $chavesPresentes = array_keys($data);
            
            $todasPresentes = true;
            foreach ($chavesEsperadas as $chave) {
                if (in_array($chave, $chavesPresentes)) {
                    $this->line("   ✅ Chave '{$chave}' presente");
                } else {
                    $this->error("   ❌ Chave '{$chave}' ausente");
                    $todasPresentes = false;
                }
            }

            // Verificar campo planoConta.nomeCompleto em Movimento
            if (isset($data['rows'][0]['planoConta.nomeCompleto'])) {
                $this->line("   ✅ Campo 'planoConta.nomeCompleto' disponível para classificação PF/PJ");
            }

            $this->resultados['estrutura'] = $todasPresentes;
            
        } catch (\Exception $e) {
            $this->resultados['estrutura'] = false;
            $this->error('   ❌ Erro ao testar estrutura: ' . $e->getMessage());
        }
        
        $this->info('');
    }

    private function mostrarResumo(): void
    {
        $this->info('╔════════════════════════════════════════════════════════╗');
        $this->info('║                      RESUMO                            ║');
        $this->info('╚════════════════════════════════════════════════════════╝');
        
        $passou = 0;
        $falhou = 0;
        
        foreach ($this->resultados as $teste => $resultado) {
            if ($resultado) {
                $passou++;
            } else {
                $falhou++;
            }
        }
        
        $this->info('');
        $this->line("   Total de testes: " . count($this->resultados));
        $this->line("   ✅ Passou: {$passou}");
        
        if ($falhou > 0) {
            $this->error("   ❌ Falhou: {$falhou}");
        } else {
            $this->line("   ❌ Falhou: 0");
        }
        
        $this->info('');
        
        if ($this->todosTestesPassaram()) {
            $this->info('   🎉 TODOS OS TESTES PASSARAM! API DataJuri funcionando corretamente.');
        } else {
            $this->error('   ⚠️  ALGUNS TESTES FALHARAM! Verificar a documentação.');
        }
        
        $this->info('');
    }

    private function todosTestesPassaram(): bool
    {
        foreach ($this->resultados as $resultado) {
            if (!$resultado) {
                return false;
            }
        }
        return true;
    }
}
