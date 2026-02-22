<?php

namespace Tests\Feature;

use App\Models\Movimento;
use App\Services\FinanceiroCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceiroCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private FinanceiroCalculatorService $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new FinanceiroCalculatorService();
    }

    public function test_dre_formula_receita_menos_deducoes_menos_despesas(): void
    {
        Movimento::factory()->create(['ano' => 2099, 'mes' => 1, 'classificacao' => 'RECEITA_PF', 'valor' => 1000]);
        Movimento::factory()->create(['ano' => 2099, 'mes' => 1, 'classificacao' => 'RECEITA_PJ', 'valor' => 500]);
        Movimento::factory()->create(['ano' => 2099, 'mes' => 1, 'classificacao' => 'RECEITA_FINANCEIRA', 'valor' => 200]);
        Movimento::factory()->create(['ano' => 2099, 'mes' => 1, 'classificacao' => 'DEDUCAO_RECEITA', 'valor' => -100]);
        Movimento::factory()->create(['ano' => 2099, 'mes' => 1, 'classificacao' => 'DESPESA', 'valor' => -300]);

        $dre = $this->calc->dre(2099, 1);

        $this->assertEquals(1000, $dre['receita_pf']);
        $this->assertEquals(500, $dre['receita_pj']);
        $this->assertEquals(200, $dre['receita_fin']);
        $this->assertEquals(1700, $dre['receita_total']);
        $this->assertEquals(100, $dre['deducoes']);
        $this->assertEquals(300, $dre['despesas']);
        $this->assertEquals(1300, $dre['resultado']); // 1700 - 100 - 300
    }

    public function test_ignorar_nao_entra_no_dre(): void
    {
        Movimento::factory()->create(['ano' => 2099, 'mes' => 1, 'classificacao' => 'RECEITA_PF', 'valor' => 500]);
        Movimento::factory()->create(['ano' => 2099, 'mes' => 1, 'classificacao' => 'IGNORAR', 'valor' => 99999]);

        $dre = $this->calc->dre(2099, 1);

        $this->assertEquals(500, $dre['receita_total']);
        $this->assertEquals(500, $dre['resultado']);
    }

    public function test_inadimplencia_nao_inclui_titulos_futuros(): void
    {
        \App\Models\ContaReceber::factory()->create([
            'status' => 'Não lançado',
            'data_vencimento' => now()->subDays(10),
            'valor' => 1000,
        ]);
        \App\Models\ContaReceber::factory()->create([
            'status' => 'Não lançado',
            'data_vencimento' => now()->addDays(30),
            'valor' => 5000,
        ]);

        $inad = $this->calc->inadimplencia();

        $this->assertEquals(1, $inad['qtd']);
        $this->assertEquals(1000, $inad['valor']);
    }
}
