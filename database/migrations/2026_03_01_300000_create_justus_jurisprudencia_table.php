<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('justus_jurisprudencia', function (Blueprint $table) {
            $table->id();
            $table->string('stj_id', 20)->unique()->comment('ID unico STJ (ex: 000894270)');
            $table->string('tribunal', 10)->default('STJ')->index();
            $table->string('numero_processo', 30)->nullable()->index();
            $table->string('numero_registro', 30)->nullable()->index();
            $table->string('numero_documento', 30)->nullable();
            $table->string('sigla_classe', 30)->nullable()->comment('REsp, AREsp, HC, etc');
            $table->string('descricao_classe', 100)->nullable();
            $table->string('classe_padronizada', 100)->nullable();
            $table->string('orgao_julgador', 50)->nullable()->index();
            $table->string('relator', 80)->nullable()->index();
            $table->string('data_publicacao', 50)->nullable();
            $table->date('data_decisao')->nullable()->index();
            $table->longText('ementa')->nullable();
            $table->string('tipo_decisao', 30)->nullable()->comment('ACORDAO, DECISAO MONOCRATICA');
            $table->longText('decisao')->nullable();
            $table->text('tese_juridica')->nullable();
            $table->text('termos_auxiliares')->nullable();
            $table->json('referencias_legislativas')->nullable();
            $table->json('acordaos_similares')->nullable();
            $table->string('area_direito', 30)->nullable()->index()->comment('civil, tributario, penal, trabalhista');
            $table->string('fonte_dataset', 100)->nullable()->comment('Nome do dataset CKAN de origem');
            $table->string('fonte_resource', 50)->nullable()->comment('Nome do arquivo JSON de origem (YYYYMMDD)');
            $table->timestamps();
        });

        // FULLTEXT index na ementa + tese_juridica para busca
        DB::statement('ALTER TABLE justus_jurisprudencia ADD FULLTEXT INDEX ft_ementa (ementa)');
        DB::statement('ALTER TABLE justus_jurisprudencia ADD FULLTEXT INDEX ft_ementa_tese (ementa, tese_juridica)');
    }

    public function down(): void
    {
        Schema::dropIfExists('justus_jurisprudencia');
    }
};
