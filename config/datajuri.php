<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DataJuri API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuração centralizada para integração com API DataJuri.
    | Todos os módulos e campos são definidos aqui para facilitar manutenção.
    |
    | ATUALIZADO: 04/02/2026 - Expansão para 8 módulos completos
    |
    */

    'base_url' => env('DATAJURI_BASE_URL', 'https://api.datajuri.com.br'),
    'client_id' => env('DATAJURI_CLIENT_ID', 'a79mtxvdhsq0pgob733z'),
    'secret_id' => env('DATAJURI_SECRET_ID', '829c2e85-8b6e-45e0-98d6-749033f62c1a'),

    'timeout' => env('DATAJURI_TIMEOUT', 120),
    'retry_attempts' => env('DATAJURI_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('DATAJURI_RETRY_DELAY', 5),

    'page_size' => env('DATAJURI_PAGE_SIZE', 1000),
    'username' => env('DATAJURI_USERNAME'),
    'password' => env('DATAJURI_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Módulos Sincronizáveis (8 módulos ativos)
    |--------------------------------------------------------------------------
    |
    | Lista de módulos da API DataJuri que devem ser sincronizados.
    | Cada módulo define: tabela local, campos a buscar, e mapeamento.
    |
    */

    'modulos' => [

        // =====================================================================
        // 1. PESSOAS (Clientes)
        // =====================================================================
        'Pessoa' => [
            'enabled' => true,
            'label' => 'Pessoas/Clientes',
            'icon' => '👥',
            'table' => 'clientes',
            'campos' => 'id,nome,email,outroEmail,telefone,celular,numeroDocumento,cpf,cnpj,tipoPessoa,statusPessoa,cliente,enderecoprua,enderecopnumero,enderecopcomplemento,enderecopbairro,enderecopcep,enderecopcidade,enderecopestado,enderecoppais,dataNascimento,profissao,sexo,estadoCivil,nacionalidade,rg,nomeFantasia,situacaoCadastralReceita,proprietario.nome,proprietarioId,codigoPessoa,valorHora,dataCadastro',
            'criterio' => null,
            'mapping' => [
                'id'                => 'datajuri_id',
                'nome'              => 'nome',
                'email'             => 'email',
                'outroEmail'        => 'outro_email',
                'telefone'          => 'telefone',
                'celular'           => 'celular',
                'numeroDocumento'   => 'cpf_cnpj',
                'cpf'               => 'cpf',
                'cnpj'              => 'cnpj',
                'tipoPessoa'        => 'tipo',
                'statusPessoa'      => 'status_pessoa',
                'enderecoprua'              => 'endereco_rua',
                'enderecopnumero'           => 'endereco_numero',
                'enderecopcomplemento'      => 'endereco_complemento',
                'enderecopbairro'           => 'endereco_bairro',
                'enderecopcep'              => 'endereco_cep',
                'enderecopcidade'           => 'endereco_cidade',
                'enderecopestado'           => 'endereco_estado',
                'enderecoppais'             => 'endereco_pais',
                'dataNascimento'    => 'data_nascimento',
                'profissao'         => 'profissao',
                'sexo'              => 'sexo',
                'estadoCivil'       => 'estado_civil',
                'nacionalidade'     => 'nacionalidade',
                'rg'                => 'rg',
                'nomeFantasia'      => 'nome_fantasia',
                'situacaoCadastralReceita' => 'situacao_receita',
                'proprietario.nome' => 'proprietario_nome',
                'proprietarioId'    => 'proprietario_id',
                'codigoPessoa'      => 'codigo_pessoa',
                'valorHora'         => 'valor_hora',
            ],
        ],

        // =====================================================================
        // 2. PROCESSOS
        // =====================================================================
        'Processo' => [
            'enabled' => true,
            'label' => 'Processos',
            'icon' => '⚖️',
            'table' => 'processos',
            'campos' => 'id,pasta,numero,status,tipoAcao,tipoProcesso,natureza,assunto,valorCausa,valorProvisionado,valorSentenca,possibilidade,ganhoCausa,tipoEncerramento,proprietario.nome,proprietario.id,cliente.nome,clienteId,cliente.numeroDocumento,adverso.nome,adversoId,posicaoCliente,posicaoAdverso,advogadoCliente.nome,faseAtual.numero,faseAtual.vara,faseAtual.instancia,faseAtual.orgao.nome,dataAbertura,dataDistribuicao,dataEncerrado,observacao,dataCadastro',
            'criterio' => null,
            'mapping' => [
                'id'                    => 'datajuri_id',
                'pasta'                 => 'pasta',
                'numero'                => 'numero',
                'status'                => 'status',
                'tipoAcao'              => 'tipo_acao',
                'tipoProcesso'          => 'tipo_processo',
                'natureza'              => 'natureza',
                'assunto'               => 'assunto',
                'valorCausa'            => 'valor_causa',
                'valorProvisionado'     => 'valor_provisionado',
                'valorSentenca'         => 'valor_sentenca',
                'possibilidade'         => 'possibilidade',
                'ganhoCausa'            => 'ganho_causa',
                'tipoEncerramento'      => 'tipo_encerramento',
                'proprietario.nome'     => 'proprietario_nome',
                'proprietario.id'       => 'proprietario_id',
                'cliente.nome'          => 'cliente_nome',
                'clienteId'             => 'cliente_datajuri_id',
                'cliente.numeroDocumento' => 'cliente_documento',
                'adverso.nome'          => 'adverso_nome',
                'adversoId'             => 'adverso_datajuri_id',
                'posicaoCliente'        => 'posicao_cliente',
                'posicaoAdverso'        => 'posicao_adverso',
                'advogadoCliente.nome'  => 'advogado_cliente_nome',
                'faseAtual.numero'      => 'fase_atual_numero',
                'faseAtual.vara'        => 'fase_atual_vara',
                'faseAtual.instancia'   => 'fase_atual_instancia',
                'faseAtual.orgao.nome'  => 'fase_atual_orgao',
                'dataAbertura'          => 'data_abertura',
                'dataDistribuicao'      => 'data_distribuicao',
                'dataEncerrado'         => 'data_encerramento',
                'observacao'            => 'observacao',
                'dataCadastro'          => 'data_cadastro_dj',
            ],
        ],

        // =====================================================================
        // 3. FASES DE PROCESSO
        // =====================================================================
        'FaseProcesso' => [
            'enabled' => true,
            'label' => 'Fases de Processo',
            'icon' => '📋',
            'table' => 'fases_processo',
            'campos' => 'id,processo.pasta,processo.id,tipoFase,localidade,instancia,data,faseAtual,diasFaseAtiva,dataUltimoAndamento,proprietario.nome,proprietario.id',
            'criterio' => null,
            'mapping' => [
                'id' => 'datajuri_id',
                'processo.pasta' => 'processo_pasta',
                'processo.id' => 'processo_id_datajuri',
                'tipoFase' => 'tipo_fase',
                'localidade' => 'localidade',
                'instancia' => 'instancia',
                'data' => 'data',
                'faseAtual' => 'fase_atual',
                'diasFaseAtiva' => 'dias_fase_ativa',
                'dataUltimoAndamento' => 'data_ultimo_andamento',
                'proprietario.nome' => 'proprietario_nome',
                'proprietario.id' => 'proprietario_id',
            ],
        ],

        // =====================================================================
        // 4. MOVIMENTOS FINANCEIROS
        // =====================================================================
        'Movimento' => [
            'enabled' => true,
            'label' => 'Movimentos Financeiros',
            'icon' => '💰',
            'table' => 'movimentos',
            'campos' => 'id,data,valor,valorComSinal,tipo,descricao,observacao,planoConta.nomeCompleto,planoConta.codigo,planoConta.id,pessoa.nome,pessoa.id,contrato.id,processo.pasta,proprietario.nome,proprietario.id,formaPagamento,conciliado,dataCadastro',
            'criterio' => null,
            'mapping' => [
                'id' => 'datajuri_id',
                'data' => 'data',
                'valor' => 'valor',
                'descricao' => 'descricao',
                'observacao' => 'observacao',
                'planoConta.nomeCompleto' => 'plano_contas',
                'planoConta.codigo' => 'codigo_plano',
                'planoConta.id' => 'plano_conta_id',
                'pessoa.nome' => 'pessoa',
                'pessoa.id' => 'pessoa_id_datajuri',
                'contrato.id' => 'contrato_id_datajuri',
                'processo.pasta' => 'processo_pasta',
                'proprietario.nome' => 'proprietario_nome',
                'proprietario.id' => 'proprietario_id',
                'conciliado' => 'conciliado',
            ],
        ],

        // =====================================================================
        // 5. CONTRATOS
        // =====================================================================
        'Contrato' => [
            'enabled' => true,
            'label' => 'Contratos',
            'icon' => '📝',
            'table' => 'contratos',
            'campos' => 'id,numero,valor,dataAssinatura,contratante.nome,contratante.id,proprietario.nome,proprietario.id,dataCadastro',
            'criterio' => null,
            'mapping' => [
                'id' => 'datajuri_id',
                'numero' => 'numero',
                'valor' => 'valor',
                'dataAssinatura' => 'data_assinatura',
                'contratante.nome' => 'contratante_nome',
                'contratante.id' => 'contratante_id_datajuri',
                'proprietario.nome' => 'proprietario_nome',
                'proprietario.id' => 'proprietario_id',
            ],
        ],

        // =====================================================================
        // 6. ATIVIDADES
        // =====================================================================
        'Atividade' => [
            'enabled' => true,
            'label' => 'Atividades',
            'icon' => '📅',
            'table' => 'atividades_datajuri',
            'campos' => 'id,status,tipoAtividade,dataHora,dataConclusao,dataVencimento,dataPrazoFatal,processo.pasta,processoId,proprietario.id,proprietario.nome,particular,dataCadastro',
            'criterio' => null,
            'mapping' => [
                'id' => 'datajuri_id',
                'status' => 'status',
                'tipoAtividade' => 'tipo_atividade',
                'dataHora' => 'data_hora',
                'dataConclusao' => 'data_conclusao',
                'dataVencimento' => 'data_vencimento',
                'dataPrazoFatal' => 'data_prazo_fatal',
                'processo.pasta' => 'processo_pasta',
                'proprietario.id' => 'proprietario_id',
                'proprietario.nome' => 'responsavel_nome',
                'particular' => 'particular',
            ],
        ],

        // =====================================================================
        // 7. HORAS TRABALHADAS
        // =====================================================================
        'HoraTrabalhada' => [
            'enabled' => true,
            'label' => 'Horas Trabalhadas',
            'icon' => '⏱️',
            'table' => 'horas_trabalhadas_datajuri',
            'campos' => 'id,data,duracaoOriginal,totalHoraTrabalhada,horaInicial,horaFinal,valorHora,valorTotalOriginal,assunto,tipo,status,proprietarioId,proprietario.nome,particular,dataFaturado,dataCadastro,observacao,horaTrabalhadaAprovada.aprovado,processo.pasta,processoId',
            'criterio' => null,
            'mapping' => [
                'id' => 'datajuri_id',
                'data' => 'data',
                'duracaoOriginal' => 'duracao_original',
                'totalHoraTrabalhada' => 'total_hora_trabalhada',
                'horaInicial' => 'hora_inicial',
                'horaFinal' => 'hora_final',
                'valorHora' => 'valor_hora',
                'valorTotalOriginal' => 'valor_total_original',
                'assunto' => 'assunto',
                'tipo' => 'tipo',
                'status' => 'status',
                'proprietarioId' => 'proprietario_id',
                'particular' => 'particular',
                'dataFaturado' => 'data_faturado',
            ],
        ],

        // =====================================================================
        // 8. ORDENS DE SERVIÇO
        // =====================================================================
        'OrdemServico' => [
            'enabled' => true,
            'label' => 'Ordens de Serviço',
            'icon' => '🔧',
            'table' => 'ordens_servico',
            'campos' => 'id,numero,situacao,dataConclusao,dataUltimoAndamento,advogado.nome,advogado.id,dataCadastro',
            'criterio' => null,
            'mapping' => [
                'id' => 'datajuri_id',
                'numero' => 'numero',
                'situacao' => 'situacao',
                'dataConclusao' => 'data_conclusao',
                'dataUltimoAndamento' => 'data_ultimo_andamento',
                'advogado.nome' => 'advogado_nome',
                'advogadoId' => 'advogado_id',
            ],
        ],
        'AndamentoFase' => [
            'enabled' => true,
            'label' => 'Andamentos de Fase',
            'icon' => '📜',
            'table' => 'andamentos_fase',
            'campos' => 'id,descricao,observacao,data,hora,parecer,parecerRevisado,parecerRevisadoPor,dataParecerRevisado,faseProcessoId',
            'criterio' => null,
            'mapping' => [
                'id' => 'datajuri_id',
                'descricao' => 'descricao',
                'data' => 'data_andamento',
                'parecer' => 'parecer',
                'parecerRevisado' => 'parecer_revisado',
                'parecerRevisadoPor' => 'parecer_revisado_por',
                'dataParecerRevisado' => 'data_parecer_revisado',
                'faseProcessoId' => 'fase_processo_id_datajuri',
            ],
        ],

        // =====================================================================
        // MÓDULOS DESABILITADOS (para referência futura)
        // =====================================================================
        'ContasReceber' => [
            'enabled' => true,
            'label' => 'Contas a Receber',
            'icon' => '💵',
            'table' => 'contas_receber',
            'campos' => 'id,descricao,valor,dataVencimento,dataPagamento,prazo,status,tipo,pessoa.nome,pessoaId,cliente.nome,clienteId,processo.pasta,processoId,contrato.numero,contratoId,observacao,dataCadastro',
            'criterio' => null,
            'mapping' => [
                'id'                => 'datajuri_id',
                'descricao'         => 'descricao',
                'valor'             => 'valor',
                'dataVencimento'    => 'data_vencimento',
                'dataPagamento'     => 'data_pagamento',
                // 'prazo'          => 'status',  // REMOVIDO: prazo traz HTML dinâmico
                'status'            => 'status',  // FIX: usar campo status real da API
                'tipo'              => 'tipo',
                'pessoa.nome'       => 'cliente',
                'pessoaId'          => 'pessoa_datajuri_id',
                'clienteId'         => 'cliente_datajuri_id',
                'processoId'        => 'processo_datajuri_id',
                'contratoId'        => 'contrato_datajuri_id',
                'observacao'        => 'observacao',
            ],
        ],

        'ContasPagar' => [
            'enabled' => false,
            'label' => 'Contas a Pagar',
            'icon' => '💳',
            'table' => 'contas_pagar',
            'campos' => 'id,descricao,valor,dataVencimento,dataPagamento,status,fornecedor.nome,dataCadastro',
            'criterio' => null,
            'mapping' => [],
        ],

        'Tarefa' => [
            'enabled' => false,
            'label' => 'Tarefas',
            'icon' => '✅',
            'table' => 'tarefas',
            'campos' => 'id,titulo,descricao,dataLimite,status,prioridade,responsavel.nome,dataCadastro',
            'criterio' => null,
            'mapping' => [],
        ],

        'Compromisso' => [
            'enabled' => false,
            'label' => 'Compromissos',
            'icon' => '📆',
            'table' => 'compromissos',
            'campos' => 'id,titulo,descricao,data,horaInicio,horaFim,local,participantes,dataCadastro',
            'criterio' => null,
            'mapping' => [],
        ],

        'Procuracao' => [
            'enabled' => false,
            'label' => 'Procurações',
            'icon' => '📄',
            'table' => 'procuracoes',
            'campos' => 'id,tipo,dataEmissao,dataValidade,outorgante.nome,outorgado.nome,dataCadastro',
            'criterio' => null,
            'mapping' => [],
        ],
    ],
];
