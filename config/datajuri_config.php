<?php

/**
 * Configuração Centralizada da API DataJuri
 * 
 * ATENÇÃO: NÃO ALTERAR ESTE ARQUIVO SEM CONSULTAR A DOCUMENTAÇÃO
 * Arquivo de referência: DATAJURI_API_DOCUMENTACAO.md
 * 
 * Data de Criação: 07/01/2026
 * Status: FUNCIONANDO
 */

return [
    /*
    |--------------------------------------------------------------------------
    | URL Base da API
    |--------------------------------------------------------------------------
    */
    'base_url' => 'https://api.datajuri.com.br',

    /*
    |--------------------------------------------------------------------------
    | Endpoints da API
    |--------------------------------------------------------------------------
    | IMPORTANTE: Usar /v1/entidades/ para buscar dados
    | NÃO USAR: /v1/modulo/ (retorna 404)
    */
    'endpoints' => [
        'auth' => '/oauth/token',
        'entidades' => '/v1/entidades/',  // CORRETO
        'campos' => '/v1/campos/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Nomes dos Módulos
    |--------------------------------------------------------------------------
    | IMPORTANTE: Usar exatamente estes nomes
    | Verificado e funcionando em 07/01/2026
    */
    'modulos' => [
        'usuarios' => 'Usuario',           // Para advogados
        'processos' => 'Processo',
        'atividades' => 'Atividade',
        'contas_receber' => 'ContasReceber', // COM 'S' no final
        'horas' => 'HoraTrabalhada',         // Singular, não LancamentoHora
        'movimentos' => 'Movimento',
    ],

    /*
    |--------------------------------------------------------------------------
    | Estrutura das Respostas
    |--------------------------------------------------------------------------
    | A API retorna dados nestas chaves
    */
    'response_keys' => [
        'data' => 'rows',           // Array com os registros
        'total' => 'listSize',      // Total de registros
        'per_page' => 'pageSize',   // Registros por página
        'current_page' => 'page',   // Página atual
    ],

    /*
    |--------------------------------------------------------------------------
    | Autenticação
    |--------------------------------------------------------------------------
    | IMPORTANTE: O separador entre clientId e secretId é DOIS PONTOS (:)
    | NÃO USAR arroba (@)
    */
    'auth' => [
        'separator' => ':',  // CORRETO: clientId:secretId
        'grant_type' => 'password',
        'content_type' => 'application/x-www-form-urlencoded',
    ],

    /*
    |--------------------------------------------------------------------------
    | Paginação
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default_per_page' => 100,
        'max_per_page' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Classificação PF/PJ
    |--------------------------------------------------------------------------
    | Padrões para identificar receitas de Pessoa Física ou Jurídica
    | baseado no campo planoConta.nomeCompleto
    */
    'classificacao_pfpj' => [
        'pessoa_fisica' => [
            '3.01.01.01',
            'Contrato PF',
            'Pessoa Física',
            'PF',
        ],
        'pessoa_juridica' => [
            '3.01.01.02',
            'Contrato PJ',
            'Pessoa Jurídica',
            'PJ',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversões de Campos
    |--------------------------------------------------------------------------
    | Campos que precisam de conversão antes de salvar no banco
    */
    'conversoes' => [
        'ativo' => [
            'Sim' => 1,
            'Não' => 0,
            true => 1,
            false => 0,
            1 => 1,
            0 => 0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout das Requisições
    |--------------------------------------------------------------------------
    */
    'timeout' => [
        'auth' => 30,
        'busca' => 60,
    ],
];
