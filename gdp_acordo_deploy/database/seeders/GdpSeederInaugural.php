<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GdpSeederInaugural extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== GDP Seeder Inaugural ===');

        // Ciclo ativo
        $ciclo = DB::table('gdp_ciclos')->where('status', 'aberto')->first();
        if (!$ciclo) {
            $this->command->error('Nenhum ciclo GDP ativo (status=aberto).');
            return;
        }
        $this->command->info("Ciclo: {$ciclo->nome} (id={$ciclo->id})");

        // Usuários elegíveis
        $users = DB::table('users')
            ->whereNotIn('id', [2, 5, 6])
            ->whereIn('role', ['admin', 'coordenador', 'advogado', 'socio'])
            ->get(['id', 'name']);

        if ($users->isEmpty()) {
            $this->command->error('Nenhum usuario elegivel encontrado.');
            return;
        }
        $this->command->info("Usuarios: " . $users->pluck('name')->implode(', '));

        // Indicadores ativos tipo score
        $indicadores = DB::table('gdp_indicadores')
            ->where('ativo', true)
            ->where('status_v1', 'score')
            ->get(['id', 'codigo']);

        if ($indicadores->isEmpty()) {
            $this->command->error('Nenhum indicador ativo tipo score.');
            return;
        }
        $this->command->info("Indicadores: {$indicadores->count()}");

        // Meses do ciclo
        $mesInicio = (int) date('n', strtotime($ciclo->data_inicio));
        $mesFim    = (int) date('n', strtotime($ciclo->data_fim));
        $ano       = (int) date('Y', strtotime($ciclo->data_inicio));

        $criados = 0;
        $existentes = 0;
        $now = now();

        foreach ($users as $user) {
            foreach ($indicadores as $ind) {
                for ($m = $mesInicio; $m <= $mesFim; $m++) {
                    $exists = DB::table('gdp_metas_individuais')
                        ->where('ciclo_id', $ciclo->id)
                        ->where('indicador_id', $ind->id)
                        ->where('user_id', $user->id)
                        ->where('mes', $m)
                        ->where('ano', $ano)
                        ->exists();

                    if ($exists) {
                        $existentes++;
                        continue;
                    }

                    DB::table('gdp_metas_individuais')->insert([
                        'ciclo_id'      => $ciclo->id,
                        'indicador_id'  => $ind->id,
                        'user_id'       => $user->id,
                        'mes'           => $m,
                        'ano'           => $ano,
                        'valor_meta'    => 0,
                        'justificativa' => null,
                        'definido_por'  => null,
                        'created_at'    => $now,
                        'updated_at'    => $now,
                    ]);
                    $criados++;
                }
            }
        }

        $total = $users->count() * $indicadores->count() * ($mesFim - $mesInicio + 1);
        $this->command->info("Concluido: {$criados} criados, {$existentes} ja existiam. Total esperado: {$total}");
    }
}
