<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siric_consultas', function (Blueprint $table) {
            // Dados pessoais expandidos (após email)
            $table->string('estado_civil', 30)->nullable()->after('email');
            $table->string('profissao', 150)->nullable()->after('estado_civil');
            $table->date('data_nascimento')->nullable()->after('profissao');
            $table->string('rg', 30)->nullable()->after('data_nascimento');
            $table->string('nacionalidade', 80)->default('Brasileira')->after('rg');

            // Endereço
            $table->string('endereco_rua', 200)->nullable()->after('nacionalidade');
            $table->string('endereco_numero', 20)->nullable()->after('endereco_rua');
            $table->string('endereco_complemento', 100)->nullable()->after('endereco_numero');
            $table->string('endereco_bairro', 100)->nullable()->after('endereco_complemento');
            $table->string('endereco_cidade', 100)->nullable()->after('endereco_bairro');
            $table->string('endereco_uf', 2)->nullable()->after('endereco_cidade');
            $table->string('endereco_cep', 10)->nullable()->after('endereco_uf');

            // Renda e patrimônio expandidos (após renda_declarada)
            $table->string('fonte_renda', 100)->nullable()->after('renda_declarada');
            $table->string('empresa_empregador', 200)->nullable()->after('fonte_renda');
            $table->string('tempo_emprego', 50)->nullable()->after('empresa_empregador');
            $table->decimal('outras_rendas', 15, 2)->nullable()->after('tempo_emprego');
            $table->text('descricao_outras_rendas')->nullable()->after('outras_rendas');
            $table->decimal('patrimonio_estimado', 15, 2)->nullable()->after('descricao_outras_rendas');
            $table->text('descricao_patrimonio')->nullable()->after('patrimonio_estimado');
            $table->decimal('despesas_mensais', 15, 2)->nullable()->after('descricao_patrimonio');
            $table->boolean('possui_imovel')->default(false)->after('despesas_mensais');
            $table->boolean('possui_veiculo')->default(false)->after('possui_imovel');
            $table->decimal('valor_imovel', 15, 2)->nullable()->after('possui_veiculo');
            $table->decimal('valor_veiculo', 15, 2)->nullable()->after('valor_imovel');

            // Referências
            $table->string('referencia1_nome', 150)->nullable()->after('valor_veiculo');
            $table->string('referencia1_telefone', 30)->nullable()->after('referencia1_nome');
            $table->string('referencia1_relacao', 80)->nullable()->after('referencia1_telefone');
            $table->string('referencia2_nome', 150)->nullable()->after('referencia1_relacao');
            $table->string('referencia2_telefone', 30)->nullable()->after('referencia2_nome');
            $table->string('referencia2_relacao', 80)->nullable()->after('referencia2_telefone');

            // Finalidade
            $table->string('finalidade', 150)->nullable()->after('parcelas_desejadas');
            $table->date('data_primeiro_vencimento')->nullable()->after('finalidade');
        });
    }

    public function down(): void
    {
        Schema::table('siric_consultas', function (Blueprint $table) {
            $table->dropColumn([
                'estado_civil', 'profissao', 'data_nascimento', 'rg', 'nacionalidade',
                'endereco_rua', 'endereco_numero', 'endereco_complemento',
                'endereco_bairro', 'endereco_cidade', 'endereco_uf', 'endereco_cep',
                'fonte_renda', 'empresa_empregador', 'tempo_emprego',
                'outras_rendas', 'descricao_outras_rendas',
                'patrimonio_estimado', 'descricao_patrimonio',
                'despesas_mensais', 'possui_imovel', 'possui_veiculo',
                'valor_imovel', 'valor_veiculo',
                'referencia1_nome', 'referencia1_telefone', 'referencia1_relacao',
                'referencia2_nome', 'referencia2_telefone', 'referencia2_relacao',
                'finalidade', 'data_primeiro_vencimento',
            ]);
        });
    }
};
