<?php

namespace App\Console\Commands;

use App\Models\Movimento;
use Illuminate\Console\Command;

class ValidateClassificacao extends Command
{
    protected $signature = 'resultados:validate-classificacao {--fix : Corrigir inconsistencias automaticamente}';
    protected $description = 'Valida consistencia entre classificacao e tipo_classificacao';

    public function handle(): int
    {
        $regras = [
            'RECEITA_'  => 'receita',
            'DESPESA'   => 'despesa',
            'DEDUCAO_'  => 'deducao',
            'OUTRAS_'   => 'receita',
        ];

        $inconsistentes = 0;
        $corrigidos = 0;

        foreach ($regras as $prefixo => $tipoEsperado) {
            $errados = Movimento::where('classificacao', 'LIKE', $prefixo . '%')
                ->where(function ($q) use ($tipoEsperado) {
                    $q->where('tipo_classificacao', '!=', $tipoEsperado)
                      ->orWhereNull('tipo_classificacao');
                })
                ->get();

            foreach ($errados as $mov) {
                $inconsistentes++;
                $this->warn("ID {$mov->id}: classificacao={$mov->classificacao} tipo={$mov->tipo_classificacao} esperado={$tipoEsperado}");

                if ($this->option('fix')) {
                    $mov->tipo_classificacao = $tipoEsperado;
                    $mov->save();
                    $corrigidos++;
                }
            }
        }

        if ($inconsistentes === 0) {
            $this->info('Nenhuma inconsistencia encontrada.');
        } else {
            $this->error("{$inconsistentes} inconsistencias encontradas.");
            if ($corrigidos > 0) {
                $this->info("{$corrigidos} corrigidas com --fix.");
            } else {
                $this->line('Use --fix para corrigir automaticamente.');
            }
        }

        return $inconsistentes > 0 ? self::FAILURE : self::SUCCESS;
    }
}
