<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Mantém somente seeds úteis para o módulo.
        $this->call(CategoriaAvisoSeeder::class);
    }
}
