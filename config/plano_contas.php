<?php

return [
    'receita_pf' => [
        '3.01.01.01',
        '3.01.01.03',
    ],

    'receita_pj' => [
        '3.01.01.02',
        '3.01.01.05',
    ],

    'receita_financeira' => [
        '3.01.02.05',
    ],

    'manual' => [
        '3.01.01.06',
        '3.01.02.01',
        '3.01.02.03',
        '3.01.02.04',
        '3.01.02.06',
        '3.01.02.07',
    ],

    // tudo que começar com isso não entra em receita
    'desprezar_prefix' => [
        '3.01.03', // deduções da receita
        '3.02',    // despesas
    ],
];
