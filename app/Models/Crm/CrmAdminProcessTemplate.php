<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessTemplate extends Model
{
    protected $table = 'crm_admin_process_templates';

    protected $fillable = [
        'tipo','nome','descricao','prazo_estimado_dias','steps','checklist','active',
    ];

    protected $casts = [
        'steps'     => 'array',
        'checklist' => 'array',
        'active'    => 'boolean',
    ];

    public static function getTemplates(): array
    {
        return [
            'transferencia_imovel' => [
                'nome'                => 'Transferência de Imóvel',
                'descricao'           => 'Processo de transferência de propriedade imobiliária via cartório.',
                'prazo_estimado_dias' => 60,
                'steps' => [
                    ['order'=>1,'titulo'=>'Receber documentação das partes','tipo'=>'cliente','deadline_days'=>5,'is_client_visible'=>true],
                    ['order'=>2,'titulo'=>'Análise e autuação dos documentos','tipo'=>'interno','deadline_days'=>3,'is_client_visible'=>false],
                    ['order'=>3,'titulo'=>'Calcular ITBI e emitir guia','tipo'=>'interno','deadline_days'=>2,'is_client_visible'=>false],
                    ['order'=>4,'titulo'=>'Pagamento do ITBI pelo cliente','tipo'=>'cliente','deadline_days'=>10,'is_client_visible'=>true],
                    ['order'=>5,'titulo'=>'Protocolo da escritura no tabelionato','tipo'=>'externo','deadline_days'=>3,'is_client_visible'=>true,'orgao'=>'Tabelionato'],
                    ['order'=>6,'titulo'=>'Lavratura e assinatura da escritura','tipo'=>'externo','deadline_days'=>15,'is_client_visible'=>true,'orgao'=>'Tabelionato'],
                    ['order'=>7,'titulo'=>'Retirar escritura e revisar','tipo'=>'interno','deadline_days'=>2,'is_client_visible'=>false],
                    ['order'=>8,'titulo'=>'Protocolo no Registro de Imóveis','tipo'=>'externo','deadline_days'=>3,'is_client_visible'=>true,'orgao'=>'Cartório de Registro de Imóveis'],
                    ['order'=>9,'titulo'=>'Aguardar análise e registro (RI)','tipo'=>'externo','deadline_days'=>30,'is_client_visible'=>true,'orgao'=>'Cartório de Registro de Imóveis'],
                    ['order'=>10,'titulo'=>'Retirar matrícula atualizada','tipo'=>'externo','deadline_days'=>5,'is_client_visible'=>false],
                    ['order'=>11,'titulo'=>'Entregar documentos ao cliente','tipo'=>'interno','deadline_days'=>3,'is_client_visible'=>true],
                    ['order'=>12,'titulo'=>'Encerramento e faturamento','tipo'=>'interno','deadline_days'=>2,'is_client_visible'=>false],
                ],
                'checklist' => [
                    'Certidão de matrícula do imóvel (atualizada)',
                    'Certidão negativa de IPTU',
                    'Certidão negativa de débitos municipais',
                    'RG e CPF do vendedor',
                    'RG e CPF do comprador',
                    'Certidão de casamento (se aplicável)',
                    'Comprovante de endereço das partes',
                    'Declaração do IR (última)',
                    'DARF ITBI pago',
                ],
            ],
            'inventario_extrajudicial' => [
                'nome'                => 'Inventário Extrajudicial',
                'descricao'           => 'Inventário e partilha de bens por escritura pública (sem litígio).',
                'prazo_estimado_dias' => 90,
                'steps' => [
                    ['order'=>1,'titulo'=>'Reunir certidões de óbito e documentos do falecido','tipo'=>'cliente','deadline_days'=>10,'is_client_visible'=>true],
                    ['order'=>2,'titulo'=>'Levantar todos os bens e dívidas do espólio','tipo'=>'interno','deadline_days'=>15,'is_client_visible'=>false],
                    ['order'=>3,'titulo'=>'Obter certidões dos imóveis','tipo'=>'externo','deadline_days'=>10,'is_client_visible'=>false,'orgao'=>'Cartório de Registro de Imóveis'],
                    ['order'=>4,'titulo'=>'Calcular ITCD e emitir guia','tipo'=>'interno','deadline_days'=>5,'is_client_visible'=>false],
                    ['order'=>5,'titulo'=>'Pagamento do ITCD pelos herdeiros','tipo'=>'cliente','deadline_days'=>15,'is_client_visible'=>true],
                    ['order'=>6,'titulo'=>'Elaborar minuta da escritura de inventário','tipo'=>'interno','deadline_days'=>10,'is_client_visible'=>false],
                    ['order'=>7,'titulo'=>'Assinatura da escritura pelos herdeiros','tipo'=>'externo','deadline_days'=>10,'is_client_visible'=>true,'orgao'=>'Tabelionato'],
                    ['order'=>8,'titulo'=>'Registro dos bens nos órgãos competentes','tipo'=>'externo','deadline_days'=>30,'is_client_visible'=>true],
                    ['order'=>9,'titulo'=>'Entrega dos documentos e encerramento','tipo'=>'interno','deadline_days'=>5,'is_client_visible'=>true],
                ],
                'checklist' => [
                    'Certidão de óbito',
                    'RG e CPF do falecido',
                    'Certidão de casamento/nascimento do falecido',
                    'RG e CPF de todos os herdeiros',
                    'Certidão de matrícula dos imóveis',
                    'Extrato de contas bancárias',
                    'Documentos de veículos (CRLV)',
                    'Declaração de IR (último)',
                    'Certidão negativa de débitos federais',
                ],
            ],
            'divorcio_extrajudicial' => [
                'nome'                => 'Divórcio Extrajudicial',
                'descricao'           => 'Divórcio consensual sem filhos menores via escritura pública.',
                'prazo_estimado_dias' => 30,
                'steps' => [
                    ['order'=>1,'titulo'=>'Coleta de documentos do casal','tipo'=>'cliente','deadline_days'=>7,'is_client_visible'=>true],
                    ['order'=>2,'titulo'=>'Análise patrimonial e minutagem do acordo','tipo'=>'interno','deadline_days'=>5,'is_client_visible'=>false],
                    ['order'=>3,'titulo'=>'Aprovação da minuta pelo casal','tipo'=>'aprovacao','deadline_days'=>5,'is_client_visible'=>true],
                    ['order'=>4,'titulo'=>'Assinatura da escritura no tabelionato','tipo'=>'externo','deadline_days'=>10,'is_client_visible'=>true,'orgao'=>'Tabelionato'],
                    ['order'=>5,'titulo'=>'Averbação no cartório do registro civil','tipo'=>'externo','deadline_days'=>10,'is_client_visible'=>true,'orgao'=>'Cartório de Registro Civil'],
                    ['order'=>6,'titulo'=>'Averbação nos registros de imóveis (se houver bens)','tipo'=>'externo','deadline_days'=>15,'is_client_visible'=>false],
                    ['order'=>7,'titulo'=>'Entrega de documentos e encerramento','tipo'=>'interno','deadline_days'=>3,'is_client_visible'=>true],
                ],
                'checklist' => [
                    'RG e CPF de ambos os cônjuges',
                    'Certidão de casamento',
                    'Certidão de nascimento dos filhos (se maiores)',
                    'Comprovante de endereço',
                    'Certidão de matrícula dos imóveis (se houver)',
                    'Documentos de veículos (se houver)',
                ],
            ],
            'abertura_empresa' => [
                'nome'                => 'Abertura de Empresa',
                'descricao'           => 'Constituição de sociedade limitada ou EIRELI.',
                'prazo_estimado_dias' => 30,
                'steps' => [
                    ['order'=>1,'titulo'=>'Coleta de dados dos sócios','tipo'=>'cliente','deadline_days'=>5,'is_client_visible'=>true],
                    ['order'=>2,'titulo'=>'Elaborar contrato social','tipo'=>'interno','deadline_days'=>5,'is_client_visible'=>false],
                    ['order'=>3,'titulo'=>'Aprovação do contrato pelos sócios','tipo'=>'aprovacao','deadline_days'=>5,'is_client_visible'=>true],
                    ['order'=>4,'titulo'=>'Registro na Junta Comercial','tipo'=>'externo','deadline_days'=>15,'is_client_visible'=>true,'orgao'=>'Junta Comercial'],
                    ['order'=>5,'titulo'=>'Obter CNPJ na Receita Federal','tipo'=>'externo','deadline_days'=>5,'is_client_visible'=>true,'orgao'=>'Receita Federal'],
                    ['order'=>6,'titulo'=>'Inscrições estadual e municipal','tipo'=>'externo','deadline_days'=>10,'is_client_visible'=>false],
                    ['order'=>7,'titulo'=>'Alvará de funcionamento','tipo'=>'externo','deadline_days'=>15,'is_client_visible'=>false,'orgao'=>'Prefeitura'],
                    ['order'=>8,'titulo'=>'Entrega de documentos e orientações','tipo'=>'interno','deadline_days'=>3,'is_client_visible'=>true],
                ],
                'checklist' => [
                    'RG e CPF de todos os sócios',
                    'Comprovante de endereço dos sócios',
                    'Comprovante do endereço da empresa',
                    'Declaração de não impedimento dos sócios',
                ],
            ],
        ];
    }
}
