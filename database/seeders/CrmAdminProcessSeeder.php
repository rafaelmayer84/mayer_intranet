<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\CrmAdminProcess;
use App\Models\Crm\CrmAdminProcessStep;
use App\Models\Crm\CrmAdminProcessAto;
use App\Models\Crm\CrmAdminProcessTramitacao;
use App\Models\Crm\CrmAdminProcessChecklist;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CrmAdminProcessSeeder extends Seeder
{
    public function run(): void
    {
        // Limpar dados existentes
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('crm_admin_process_ato_anexos')->truncate();
        DB::table('crm_admin_process_atos')->truncate();
        DB::table('crm_admin_process_tramitacoes')->truncate();
        DB::table('crm_admin_process_checklist')->truncate();
        DB::table('crm_admin_process_timeline')->truncate();
        DB::table('crm_admin_process_steps')->truncate();
        DB::table('crm_admin_processes')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $rafael   = 1;
        $patricia = 3;
        $anelise  = 7;

        // ════════════════════════════════════════════════════════════
        // PROCESSO 1: Transferência de Imóvel — com vários atos e tramitação
        // ════════════════════════════════════════════════════════════
        $p1 = CrmAdminProcess::create([
            'protocolo'       => 'ADM-2026-0001',
            'account_id'      => 1,
            'tipo'            => 'transferencia_imovel',
            'titulo'          => 'Transferência de Imóvel — Rua das Flores 420, apto 201, Florianópolis/SC',
            'descricao'       => 'Transferência de propriedade do imóvel matriculado sob nº 45.892 no 3º CRI de Florianópolis. Vendedor: João Carlos Pereira. Comprador: Eric Santos de Almeida. Valor: R$ 420.000,00.',
            'status'          => 'em_andamento',
            'prioridade'      => 'normal',
            'owner_user_id'   => $rafael,
            'com_user_id'     => $rafael,
            'orgao_destino'   => '3º Cartório de Registro de Imóveis de Florianópolis',
            'numero_externo'  => '2026/45.892-7',
            'prazo_estimado'  => now()->addDays(25)->toDateString(),
            'prazo_final'     => now()->addDays(35)->toDateString(),
            'valor_honorarios'=> 4500.00,
            'valor_despesas'  => 1200.00,
            'client_visible'  => true,
            'created_at'      => now()->subDays(35),
            'updated_at'      => now()->subDays(1),
        ]);

        // --- Atos do P1 (árvore do processo) ---
        $atosP1 = [
            [
                'numero'  => 1,
                'user_id' => $rafael,
                'tipo'    => 'abertura',
                'titulo'  => 'Abertura do Processo',
                'corpo'   => "Processo ADM-2026-0001 instaurado.\n\nTipo: Transferência de Imóvel\nCliente: Eric Santos de Almeida\nResponsável: Rafael Mayer\nVendedor: João Carlos Pereira\nImóvel: Rua das Flores 420, apto 201 — matrícula 45.892",
                'is_client_visible' => true,
                'created_at' => now()->subDays(35),
            ],
            [
                'numero'  => 2,
                'user_id' => $rafael,
                'tipo'    => 'despacho',
                'titulo'  => 'Solicitação de documentos ao cliente',
                'corpo'   => "Solicitados ao cliente os seguintes documentos para instrução do processo:\n- RG e CPF do comprador\n- Comprovante de endereço atualizado\n- Declaração de IR (último exercício)\n\nEnviados os documentos do vendedor já em mãos: certidão de matrícula atualizada e certidões negativas.",
                'is_client_visible' => true,
                'created_at' => now()->subDays(34),
            ],
            [
                'numero'  => 3,
                'user_id' => $rafael,
                'tipo'    => 'recebimento',
                'titulo'  => 'Documentação do comprador recebida',
                'corpo'   => "Recebidos via e-mail os documentos do comprador Eric Santos:\n- RG e CPF (cópias autenticadas)\n- Comprovante de endereço (conta de energia)\n- Declaração de IR 2025",
                'is_client_visible' => false,
                'created_at' => now()->subDays(32),
            ],
            [
                'numero'  => 4,
                'user_id' => $rafael,
                'tipo'    => 'parecer',
                'titulo'  => 'Parecer — Análise documental',
                'corpo'   => "Analisada toda a documentação das partes.\n\nVENDEDOR: Documentação em ordem. Certidão de matrícula sem ônus ou gravames.\nCOMPRADOR: Documentação em ordem. Sem restrições cadastrais.\n\nITBI: Valor venal de referência R\$ 420.000,00. Alíquota: 2%. ITBI estimado: R\$ 8.400,00.\n\nPARECER: Apto para lavratura de escritura pública de compra e venda.",
                'is_client_visible' => false,
                'created_at' => now()->subDays(28),
            ],
            [
                'numero'  => 5,
                'user_id' => $rafael,
                'tipo'    => 'tramitacao',
                'titulo'  => 'Tramitação para Dra. Patrícia — cálculo de ITBI',
                'corpo'   => 'Encaminhado à Dra. Patrícia para cálculo e emissão da guia de ITBI junto à Prefeitura.',
                'is_client_visible' => false,
                'created_at' => now()->subDays(27),
            ],
            [
                'numero'  => 6,
                'user_id' => $patricia,
                'tipo'    => 'guia_pagamento',
                'titulo'  => 'Guia de ITBI emitida',
                'corpo'   => "ITBI calculado: R\$ 8.400,00\nVencimento: " . now()->subDays(15)->format('d/m/Y') . "\n\nGuia emitida pelo portal da Prefeitura de Florianópolis e enviada ao cliente para pagamento.",
                'is_client_visible' => true,
                'created_at' => now()->subDays(25),
            ],
            [
                'numero'  => 7,
                'user_id' => $patricia,
                'tipo'    => 'tramitacao',
                'titulo'  => 'Devolvido ao Dr. Rafael Mayer',
                'corpo'   => 'Guia de ITBI emitida e enviada ao cliente. Devolvendo processo ao responsável.',
                'is_client_visible' => false,
                'created_at' => now()->subDays(24),
            ],
            [
                'numero'  => 8,
                'user_id' => $rafael,
                'tipo'    => 'comprovante',
                'titulo'  => 'Comprovante de pagamento do ITBI recebido',
                'corpo'   => "Cliente encaminhou comprovante de pagamento do ITBI.\nValor pago: R\$ 8.400,00\nData: " . now()->subDays(20)->format('d/m/Y'),
                'is_client_visible' => true,
                'created_at' => now()->subDays(20),
            ],
            [
                'numero'  => 9,
                'user_id' => $rafael,
                'tipo'    => 'protocolo',
                'titulo'  => 'Escritura protocolada no 1º Tabelionato de Notas',
                'corpo'   => "Escritura pública de compra e venda protocolada no 1º Tabelionato de Notas de Florianópolis.\n\nProtocolo nº 2026/1.547\nPrevisão de lavratura: " . now()->subDays(8)->format('d/m/Y'),
                'is_client_visible' => true,
                'created_at' => now()->subDays(15),
            ],
            [
                'numero'  => 10,
                'user_id' => $rafael,
                'tipo'    => 'escritura',
                'titulo'  => 'Escritura lavrada e assinada pelas partes',
                'corpo'   => "Escritura pública de compra e venda lavrada e assinada por todas as partes.\n\nLivro: 042\nFolha: 218\nTabelião: Dr. Marcos Antônio da Silva\n\nPresentes: vendedor João Carlos Pereira, comprador Eric Santos de Almeida, e advogado Rafael Mayer.",
                'is_client_visible' => true,
                'created_at' => now()->subDays(8),
            ],
            [
                'numero'  => 11,
                'user_id' => $rafael,
                'tipo'    => 'despacho',
                'titulo'  => 'Conclusão ao advogado — retirada da escritura',
                'corpo'   => "Escritura retirada do tabelionato. Revisão final realizada.\nPróximo passo: protocolar no Registro de Imóveis para transferência definitiva da matrícula.",
                'is_client_visible' => false,
                'created_at' => now()->subDays(2),
            ],
        ];

        foreach ($atosP1 as $a) {
            CrmAdminProcessAto::create(array_merge($a, ['admin_process_id' => $p1->id]));
        }

        // --- Tramitações P1 ---
        CrmAdminProcessTramitacao::create([
            'admin_process_id' => $p1->id,
            'de_user_id'       => $rafael,
            'para_user_id'     => $patricia,
            'tipo'             => 'encaminhamento',
            'despacho'         => 'Para cálculo e emissão da guia de ITBI.',
            'recebido_at'      => now()->subDays(26),
            'created_at'       => now()->subDays(27),
        ]);

        CrmAdminProcessTramitacao::create([
            'admin_process_id' => $p1->id,
            'de_user_id'       => $patricia,
            'para_user_id'     => $rafael,
            'tipo'             => 'devolucao',
            'despacho'         => 'Guia emitida. Retornando.',
            'recebido_at'      => now()->subDays(23),
            'created_at'       => now()->subDays(24),
        ]);

        // --- Checklist P1 ---
        $checkP1 = [
            ['nome'=>'Certidão de matrícula atualizada',        'status'=>'recebido','received_at'=>now()->subDays(32)],
            ['nome'=>'Certidão negativa de IPTU',               'status'=>'recebido','received_at'=>now()->subDays(32)],
            ['nome'=>'Certidão negativa de débitos municipais', 'status'=>'recebido','received_at'=>now()->subDays(30)],
            ['nome'=>'RG e CPF do vendedor',                    'status'=>'recebido','received_at'=>now()->subDays(32)],
            ['nome'=>'RG e CPF do comprador',                   'status'=>'recebido','received_at'=>now()->subDays(32)],
            ['nome'=>'Comprovante de endereço',                 'status'=>'recebido','received_at'=>now()->subDays(32)],
            ['nome'=>'Declaração de IR (último)',                'status'=>'recebido','received_at'=>now()->subDays(32)],
            ['nome'=>'Comprovante de pagamento ITBI',           'status'=>'recebido','received_at'=>now()->subDays(20)],
            ['nome'=>'Certidão de casamento',                   'status'=>'dispensado','dispensed_reason'=>'Partes solteiras'],
        ];
        foreach ($checkP1 as $c) {
            CrmAdminProcessChecklist::create(array_merge($c, ['admin_process_id' => $p1->id]));
        }

        // --- Etapas P1 (guia) ---
        $stepsP1 = [
            ['order'=>1, 'titulo'=>'Receber documentação das partes', 'tipo'=>'cliente','status'=>'concluido','completed_at'=>now()->subDays(32)],
            ['order'=>2, 'titulo'=>'Análise documental e parecer',    'tipo'=>'interno','status'=>'concluido','completed_at'=>now()->subDays(28)],
            ['order'=>3, 'titulo'=>'Calcular ITBI e emitir guia',     'tipo'=>'interno','status'=>'concluido','completed_at'=>now()->subDays(25)],
            ['order'=>4, 'titulo'=>'Pagamento do ITBI',               'tipo'=>'cliente','status'=>'concluido','completed_at'=>now()->subDays(20)],
            ['order'=>5, 'titulo'=>'Escritura no tabelionato',        'tipo'=>'externo','status'=>'concluido','completed_at'=>now()->subDays(8)],
            ['order'=>6, 'titulo'=>'Protocolo no Registro de Imóveis','tipo'=>'externo','status'=>'pendente'],
            ['order'=>7, 'titulo'=>'Aguardar registro (RI)',          'tipo'=>'externo','status'=>'pendente'],
            ['order'=>8, 'titulo'=>'Entrega ao cliente',              'tipo'=>'interno','status'=>'pendente'],
        ];
        foreach ($stepsP1 as $s) {
            CrmAdminProcessStep::create(array_merge($s, [
                'admin_process_id' => $p1->id,
                'responsible_user_id' => $rafael,
            ]));
        }

        // ════════════════════════════════════════════════════════════
        // PROCESSO 2: Inventário — aguardando cliente
        // ════════════════════════════════════════════════════════════
        $p2 = CrmAdminProcess::create([
            'protocolo'       => 'ADM-2026-0002',
            'account_id'      => 2,
            'tipo'            => 'inventario_extrajudicial',
            'titulo'          => 'Inventário Extrajudicial — Espólio de Carlos Roberto Valetim',
            'descricao'       => 'Inventário e partilha dos bens do falecido Carlos Roberto Valetim. Herdeiros: Tabata e Pedro. Bens: imóvel em São José/SC e 2 veículos.',
            'status'          => 'aguardando_cliente',
            'prioridade'      => 'alta',
            'owner_user_id'   => $patricia,
            'com_user_id'     => $patricia,
            'orgao_destino'   => '2º Tabelionato de Notas de São José',
            'prazo_estimado'  => now()->addDays(55)->toDateString(),
            'prazo_final'     => now()->addDays(70)->toDateString(),
            'valor_honorarios'=> 8000.00,
            'client_visible'  => true,
            'created_at'      => now()->subDays(20),
            'updated_at'      => now()->subDays(2),
        ]);

        $atosP2 = [
            [
                'numero'  => 1,
                'user_id' => $patricia,
                'tipo'    => 'abertura',
                'titulo'  => 'Abertura do Processo',
                'corpo'   => "Processo ADM-2026-0002 instaurado.\n\nInventário extrajudicial do espólio de Carlos Roberto Valetim.\nÓbito: " . now()->subDays(45)->format('d/m/Y') . "\nHerdeiros: Tabata Valetim (filha), Pedro Valetim (filho).",
                'is_client_visible' => true,
                'created_at' => now()->subDays(20),
            ],
            [
                'numero'  => 2,
                'user_id' => $patricia,
                'tipo'    => 'recebimento',
                'titulo'  => 'Certidão de óbito e documentos pessoais recebidos',
                'corpo'   => "Recebidos da herdeira Tabata Valetim:\n- Certidão de óbito\n- RG e CPF do falecido\n- RG e CPF dos herdeiros\n- Certidão de nascimento dos herdeiros",
                'is_client_visible' => false,
                'created_at' => now()->subDays(18),
            ],
            [
                'numero'  => 3,
                'user_id' => $patricia,
                'tipo'    => 'certidao',
                'titulo'  => 'Certidões de matrícula do imóvel obtidas',
                'corpo'   => "Obtida certidão de matrícula nº 32.441 do Cartório de Registro de Imóveis de São José.\nValor venal: R\$ 320.000,00\nSem ônus ou gravames.",
                'is_client_visible' => false,
                'created_at' => now()->subDays(12),
            ],
            [
                'numero'  => 4,
                'user_id' => $patricia,
                'tipo'    => 'parecer',
                'titulo'  => 'Parecer — Levantamento patrimonial',
                'corpo'   => "BENS DO ESPÓLIO:\n\n1) Imóvel: Rua XV de Novembro, 1230, São José/SC — matrícula 32.441 — R\$ 320.000,00\n2) Veículo: VW Gol 1.0 2018/2019 — Placa ABC-1234 — R\$ 35.000,00\n3) Veículo: Honda HR-V 2021/2021 — Placa DEF-5678 — R\$ 95.000,00\n\nTotal do espólio: R\$ 450.000,00\nDívidas: Nenhuma identificada\n\nITCD estimado (4%): R\$ 18.000,00 (R\$ 9.000 por herdeiro)\n\nPARECER: Apto para inventário extrajudicial. Não há menores, incapazes ou litígio.",
                'is_client_visible' => false,
                'created_at' => now()->subDays(10),
            ],
            [
                'numero'  => 5,
                'user_id' => $patricia,
                'tipo'    => 'guia_pagamento',
                'titulo'  => 'Guia de ITCD emitida — aguardando pagamento',
                'corpo'   => "Guia de ITCD emitida pela SEF/SC:\nValor: R\$ 18.000,00 (R\$ 9.000 por herdeiro)\nVencimento: " . now()->addDays(5)->format('d/m/Y') . "\n\nGuia encaminhada aos herdeiros via WhatsApp.",
                'is_client_visible' => true,
                'created_at' => now()->subDays(5),
            ],
            [
                'numero'  => 6,
                'user_id' => $patricia,
                'tipo'    => 'comunicacao',
                'titulo'  => 'Lembrete aos herdeiros — pagamento do ITCD',
                'corpo'   => "Enviado lembrete via WhatsApp para Tabata e Pedro sobre o prazo de pagamento da guia de ITCD.\nAguardando comprovante de pagamento.",
                'is_client_visible' => false,
                'created_at' => now()->subDays(2),
            ],
        ];
        foreach ($atosP2 as $a) {
            CrmAdminProcessAto::create(array_merge($a, ['admin_process_id' => $p2->id]));
        }

        $checkP2 = [
            ['nome'=>'Certidão de óbito',                      'status'=>'recebido','received_at'=>now()->subDays(18)],
            ['nome'=>'RG e CPF do falecido',                   'status'=>'recebido','received_at'=>now()->subDays(18)],
            ['nome'=>'Certidão de nascimento dos herdeiros',   'status'=>'recebido','received_at'=>now()->subDays(18)],
            ['nome'=>'RG e CPF de todos os herdeiros',         'status'=>'recebido','received_at'=>now()->subDays(18)],
            ['nome'=>'Certidão de matrícula dos imóveis',      'status'=>'recebido','received_at'=>now()->subDays(12)],
            ['nome'=>'Documentos dos veículos (CRLV)',         'status'=>'recebido','received_at'=>now()->subDays(15)],
            ['nome'=>'Extrato bancário do falecido',           'status'=>'recebido','received_at'=>now()->subDays(10)],
            ['nome'=>'Declaração de IR do falecido',           'status'=>'pendente'],
            ['nome'=>'Comprovante de pagamento ITCD',          'status'=>'pendente'],
        ];
        foreach ($checkP2 as $c) {
            CrmAdminProcessChecklist::create(array_merge($c, ['admin_process_id' => $p2->id]));
        }

        // ════════════════════════════════════════════════════════════
        // PROCESSO 3: Abertura de empresa — início, poucos atos
        // ════════════════════════════════════════════════════════════
        $p3 = CrmAdminProcess::create([
            'protocolo'       => 'ADM-2026-0003',
            'account_id'      => 3,
            'tipo'            => 'abertura_empresa',
            'titulo'          => 'Constituição de LTDA — L&R Comércio de Alimentos Naturais',
            'descricao'       => 'Constituição de sociedade limitada. Sócias: Luciele C. Rosa (60%) e Renata Souza (40%). CNAE: comércio varejista de alimentos naturais.',
            'status'          => 'em_andamento',
            'prioridade'      => 'normal',
            'owner_user_id'   => $anelise,
            'com_user_id'     => $anelise,
            'prazo_estimado'  => now()->addDays(30)->toDateString(),
            'valor_honorarios'=> 2800.00,
            'client_visible'  => true,
            'created_at'      => now()->subDays(3),
            'updated_at'      => now()->subDays(1),
        ]);

        $atosP3 = [
            [
                'numero'  => 1,
                'user_id' => $anelise,
                'tipo'    => 'abertura',
                'titulo'  => 'Abertura do Processo',
                'corpo'   => "Processo ADM-2026-0003 instaurado.\n\nConstituição de LTDA: L&R Comércio de Alimentos Naturais\nSócias: Luciele Cristina Rosa (60%) e Renata Souza (40%)\nCapital social: R\$ 50.000,00",
                'is_client_visible' => true,
                'created_at' => now()->subDays(3),
            ],
            [
                'numero'  => 2,
                'user_id' => $anelise,
                'tipo'    => 'despacho',
                'titulo'  => 'Solicitação de dados e documentos às sócias',
                'corpo'   => "Formulário de dados enviado às sócias com as seguintes informações pendentes:\n- Endereço completo da sede da empresa\n- Declaração de não impedimento\n- Cópia do IPTU ou contrato de locação do imóvel sede",
                'is_client_visible' => true,
                'created_at' => now()->subDays(2),
            ],
            [
                'numero'  => 3,
                'user_id' => $anelise,
                'tipo'    => 'recebimento',
                'titulo'  => 'Documentos pessoais das sócias recebidos',
                'corpo'   => "Recebidos via WhatsApp:\n- RG e CPF de Luciele Rosa\n- RG e CPF de Renata Souza\n- Comprovantes de endereço\n\nAguardando ainda: comprovante do endereço da empresa e declaração de não impedimento.",
                'is_client_visible' => false,
                'created_at' => now()->subDays(1),
            ],
        ];
        foreach ($atosP3 as $a) {
            CrmAdminProcessAto::create(array_merge($a, ['admin_process_id' => $p3->id]));
        }

        $checkP3 = [
            ['nome'=>'RG e CPF de todos os sócios',             'status'=>'recebido','received_at'=>now()->subDays(1)],
            ['nome'=>'Comprovante de endereço dos sócios',      'status'=>'recebido','received_at'=>now()->subDays(1)],
            ['nome'=>'Comprovante do endereço da empresa',      'status'=>'pendente'],
            ['nome'=>'Declaração de não impedimento dos sócios','status'=>'pendente'],
        ];
        foreach ($checkP3 as $c) {
            CrmAdminProcessChecklist::create(array_merge($c, ['admin_process_id' => $p3->id]));
        }

        $this->command->info('Seeder executado: 3 processos com atos, tramitações e checklist.');
    }
}
