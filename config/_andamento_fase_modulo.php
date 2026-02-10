<?php

/**
 * Configuração do módulo AndamentoFase para adicionar ao config/datajuri.php
 *
 * Adicionar este item ao array 'modulos' dentro do config/datajuri.php existente.
 *
 * PATCH: Localizar o array 'modulos' => [...] e adicionar este item após 'OrdemServico'.
 */

return [
    // Adicionar ao array 'modulos' do config/datajuri.php:
    'AndamentoFase' => [
        'tabela'   => 'andamentos_fase',
        'endpoint' => 'AndamentoFase',
        'campos'   => [
            'datajuri_id'                 => 'id',
            'fase_processo_id_datajuri'   => 'faseProcesso.id',
            'processo_id_datajuri'        => 'processo.id',
            'processo_pasta'              => 'processo.pasta',
            'data_andamento'              => 'dataAndamento',
            'descricao'                   => 'descricao',
            'tipo'                        => 'tipo',
            'parecer'                     => 'parecer',
            'parecer_revisado'            => 'parecerRevisado',
            'parecer_revisado_por'        => 'parecerRevisadoPor',
            'data_parecer_revisado'       => 'dataParecerRevisado',
            'proprietario_id'             => 'proprietario.id',
            'proprietario_nome'           => 'proprietario.nome',
        ],
        'upsert_key' => 'datajuri_id',
    ],
];
