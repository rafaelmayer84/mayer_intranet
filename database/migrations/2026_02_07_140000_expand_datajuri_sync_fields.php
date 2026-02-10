<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // =====================================================================
        // 1. CLIENTES — Campos confirmados via GET /v1/campos/Pessoa
        //    e registro real: enderecoprua, celular, statusPessoa, cliente, etc.
        // =====================================================================
        Schema::table('clientes', function (Blueprint $table) {
            // Celular separado (API retorna celular ≠ telefone)
            if (!Schema::hasColumn('clientes', 'celular')) {
                $table->string('celular', 30)->nullable()->after('telefone');
            }

            // Telefone normalizado E.164 (para match rápido no resolver)
            if (!Schema::hasColumn('clientes', 'telefone_normalizado')) {
                $table->string('telefone_normalizado', 20)->nullable()->after('celular');
            }

            // Outro email (API: outroEmail)
            if (!Schema::hasColumn('clientes', 'outro_email')) {
                $table->string('outro_email', 255)->nullable()->after('email');
            }

            // Endereço decomposto (API: enderecoprua, enderecopnumero, etc.)
            if (!Schema::hasColumn('clientes', 'endereco_rua')) {
                $table->string('endereco_rua', 255)->nullable()->after('endereco');
            }
            if (!Schema::hasColumn('clientes', 'endereco_numero')) {
                $table->string('endereco_numero', 20)->nullable()->after('endereco_rua');
            }
            if (!Schema::hasColumn('clientes', 'endereco_complemento')) {
                $table->string('endereco_complemento', 255)->nullable()->after('endereco_numero');
            }
            if (!Schema::hasColumn('clientes', 'endereco_bairro')) {
                $table->string('endereco_bairro', 100)->nullable()->after('endereco_complemento');
            }
            if (!Schema::hasColumn('clientes', 'endereco_cep')) {
                $table->string('endereco_cep', 15)->nullable()->after('endereco_bairro');
            }
            if (!Schema::hasColumn('clientes', 'endereco_cidade')) {
                $table->string('endereco_cidade', 100)->nullable()->after('endereco_cep');
            }
            if (!Schema::hasColumn('clientes', 'endereco_estado')) {
                $table->string('endereco_estado', 5)->nullable()->after('endereco_cidade');
            }
            if (!Schema::hasColumn('clientes', 'endereco_pais')) {
                $table->string('endereco_pais', 50)->nullable()->after('endereco_estado');
            }

            // Proprietário/Responsável (API: proprietario.nome, proprietarioId)
            if (!Schema::hasColumn('clientes', 'proprietario_nome')) {
                $table->string('proprietario_nome', 255)->nullable()->after('codigo_pessoa');
            }
            if (!Schema::hasColumn('clientes', 'proprietario_id')) {
                $table->unsignedBigInteger('proprietario_id')->nullable()->after('proprietario_nome');
            }

            // Dados pessoais PF (confirmados no registro real)
            if (!Schema::hasColumn('clientes', 'profissao')) {
                $table->string('profissao', 100)->nullable()->after('data_nascimento');
            }
            if (!Schema::hasColumn('clientes', 'sexo')) {
                $table->string('sexo', 5)->nullable()->after('profissao');
            }
            if (!Schema::hasColumn('clientes', 'estado_civil')) {
                $table->string('estado_civil', 50)->nullable()->after('sexo');
            }
            if (!Schema::hasColumn('clientes', 'nacionalidade')) {
                $table->string('nacionalidade', 50)->nullable()->after('estado_civil');
            }
            if (!Schema::hasColumn('clientes', 'rg')) {
                $table->string('rg', 30)->nullable()->after('nacionalidade');
            }

            // PJ (API: nomeFantasia)
            if (!Schema::hasColumn('clientes', 'nome_fantasia')) {
                $table->string('nome_fantasia', 255)->nullable()->after('rg');
            }

            // Situação cadastral Receita (API: situacaoCadastralReceita)
            if (!Schema::hasColumn('clientes', 'situacao_receita')) {
                $table->string('situacao_receita', 100)->nullable()->after('nome_fantasia');
            }

            // Índices
            if (!Schema::hasColumn('clientes', 'telefone_normalizado')) {
                // Já foi criado acima, mas índice precisa ser separado
            }
        });

        // Índice para telefone_normalizado (separado para evitar erro se coluna já existia)
        try {
            Schema::table('clientes', function (Blueprint $table) {
                $table->index('telefone_normalizado', 'idx_clientes_tel_norm');
            });
        } catch (\Exception $e) {
            // Índice já existe, ignorar
        }

        // =====================================================================
        // 2. CONTAS_RECEBER — FK sólida via pessoaId/clienteId da API
        //    Confirmado: 1.811/1.811 registros têm pessoaId no payload
        // =====================================================================
        Schema::table('contas_receber', function (Blueprint $table) {
            // pessoaId da API → datajuri_id da Pessoa (favorecido/pagador)
            if (!Schema::hasColumn('contas_receber', 'pessoa_datajuri_id')) {
                $table->unsignedBigInteger('pessoa_datajuri_id')->nullable()->after('cliente');
            }

            // clienteId da API → datajuri_id do Cliente analítico
            if (!Schema::hasColumn('contas_receber', 'cliente_datajuri_id')) {
                $table->unsignedBigInteger('cliente_datajuri_id')->nullable()->after('pessoa_datajuri_id');
            }

            // processoId da API → datajuri_id do Processo
            if (!Schema::hasColumn('contas_receber', 'processo_datajuri_id')) {
                $table->unsignedBigInteger('processo_datajuri_id')->nullable()->after('cliente_datajuri_id');
            }

            // contratoId da API → datajuri_id do Contrato
            if (!Schema::hasColumn('contas_receber', 'contrato_datajuri_id')) {
                $table->unsignedBigInteger('contrato_datajuri_id')->nullable()->after('processo_datajuri_id');
            }

            // Descrição / natureza do lançamento
            if (!Schema::hasColumn('contas_receber', 'descricao')) {
                $table->string('descricao', 500)->nullable()->after('datajuri_id');
            }

            // Observação / histórico
            if (!Schema::hasColumn('contas_receber', 'observacao')) {
                $table->text('observacao')->nullable()->after('tipo');
            }
        });

        // Índices para contas_receber (separados)
        try {
            Schema::table('contas_receber', function (Blueprint $table) {
                $table->index('pessoa_datajuri_id', 'idx_cr_pessoa_dj_id');
                $table->index('cliente_datajuri_id', 'idx_cr_cliente_dj_id');
            });
        } catch (\Exception $e) {
            // Índices já existem
        }

        // =====================================================================
        // 3. PROCESSOS — Campos faltantes confirmados via API
        //    clienteId, adverso, posicaoCliente, dataAbertura, faseAtual, etc.
        // =====================================================================
        Schema::table('processos', function (Blueprint $table) {
            // clienteId da API (datajuri_id do cliente)
            if (!Schema::hasColumn('processos', 'cliente_datajuri_id')) {
                $table->unsignedBigInteger('cliente_datajuri_id')->nullable()->after('cliente_id');
            }

            // Adverso
            if (!Schema::hasColumn('processos', 'adverso_nome')) {
                $table->string('adverso_nome', 255)->nullable()->after('advogado_id');
            }
            if (!Schema::hasColumn('processos', 'adverso_datajuri_id')) {
                $table->unsignedBigInteger('adverso_datajuri_id')->nullable()->after('adverso_nome');
            }

            // Posições
            if (!Schema::hasColumn('processos', 'posicao_cliente')) {
                $table->string('posicao_cliente', 50)->nullable()->after('adverso_datajuri_id');
            }
            if (!Schema::hasColumn('processos', 'posicao_adverso')) {
                $table->string('posicao_adverso', 50)->nullable()->after('posicao_cliente');
            }

            // Advogado do cliente
            if (!Schema::hasColumn('processos', 'advogado_cliente_nome')) {
                $table->string('advogado_cliente_nome', 255)->nullable()->after('posicao_adverso');
            }

            // Fase atual
            if (!Schema::hasColumn('processos', 'fase_atual_numero')) {
                $table->string('fase_atual_numero', 100)->nullable()->after('advogado_cliente_nome');
            }
            if (!Schema::hasColumn('processos', 'fase_atual_vara')) {
                $table->string('fase_atual_vara', 255)->nullable()->after('fase_atual_numero');
            }
            if (!Schema::hasColumn('processos', 'fase_atual_instancia')) {
                $table->string('fase_atual_instancia', 100)->nullable()->after('fase_atual_vara');
            }
            if (!Schema::hasColumn('processos', 'fase_atual_orgao')) {
                $table->string('fase_atual_orgao', 255)->nullable()->after('fase_atual_instancia');
            }

            // Tipo de processo
            if (!Schema::hasColumn('processos', 'tipo_processo')) {
                $table->string('tipo_processo', 100)->nullable()->after('tipo_acao');
            }

            // Data de abertura (diferente de data_distribuicao)
            if (!Schema::hasColumn('processos', 'data_abertura')) {
                $table->date('data_abertura')->nullable()->after('data_distribuicao');
            }

            // Data de encerramento
            if (!Schema::hasColumn('processos', 'data_encerramento')) {
                $table->date('data_encerramento')->nullable()->after('data_abertura');
            }

            // Observação / Relatório da Inicial
            if (!Schema::hasColumn('processos', 'observacao')) {
                $table->text('observacao')->nullable()->after('data_conclusao');
            }
        });

        // Índice para processos.cliente_datajuri_id
        try {
            Schema::table('processos', function (Blueprint $table) {
                $table->index('cliente_datajuri_id', 'idx_proc_cliente_dj_id');
            });
        } catch (\Exception $e) {
            // Índice já existe
        }

        // =====================================================================
        // 4. LEADS — Coluna email para match no resolver
        //    Confirmado: metadata tem legacy_email, mas campo próprio não existe
        // =====================================================================
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'email')) {
                $table->string('email', 255)->nullable()->after('telefone');
            }
        });

        try {
            Schema::table('leads', function (Blueprint $table) {
                $table->index('email', 'idx_leads_email');
            });
        } catch (\Exception $e) {
            // Índice já existe
        }
    }

    public function down(): void
    {
        // Clientes
        $clientesCols = [
            'celular', 'telefone_normalizado', 'outro_email',
            'endereco_rua', 'endereco_numero', 'endereco_complemento',
            'endereco_bairro', 'endereco_cep', 'endereco_cidade',
            'endereco_estado', 'endereco_pais', 'proprietario_nome',
            'proprietario_id', 'profissao', 'sexo', 'estado_civil',
            'nacionalidade', 'rg', 'nome_fantasia', 'situacao_receita',
        ];
        Schema::table('clientes', function (Blueprint $table) use ($clientesCols) {
            foreach ($clientesCols as $col) {
                if (Schema::hasColumn('clientes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Contas Receber
        $crCols = ['pessoa_datajuri_id', 'cliente_datajuri_id', 'processo_datajuri_id', 'contrato_datajuri_id', 'descricao', 'observacao'];
        Schema::table('contas_receber', function (Blueprint $table) use ($crCols) {
            foreach ($crCols as $col) {
                if (Schema::hasColumn('contas_receber', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Processos
        $procCols = [
            'cliente_datajuri_id', 'adverso_nome', 'adverso_datajuri_id',
            'posicao_cliente', 'posicao_adverso', 'advogado_cliente_nome',
            'fase_atual_numero', 'fase_atual_vara', 'fase_atual_instancia',
            'fase_atual_orgao', 'tipo_processo', 'data_abertura',
            'data_encerramento', 'observacao',
        ];
        Schema::table('processos', function (Blueprint $table) use ($procCols) {
            foreach ($procCols as $col) {
                if (Schema::hasColumn('processos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // Leads
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'email')) {
                $table->dropColumn('email');
            }
        });
    }
};
