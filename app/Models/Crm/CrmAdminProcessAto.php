<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmAdminProcessAto extends Model
{
    protected $table = 'crm_admin_process_atos';

    protected $fillable = [
        'admin_process_id','numero','user_id','tipo','titulo','corpo',
        'is_client_visible','assinado_por_user_id','assinado_at',
    ];

    protected $casts = [
        'is_client_visible' => 'boolean',
        'assinado_at'       => 'datetime',
    ];

    // ── Relationships ──

    public function process()
    {
        return $this->belongsTo(CrmAdminProcess::class, 'admin_process_id');
    }

    public function autor()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function assinadoPor()
    {
        return $this->belongsTo(\App\Models\User::class, 'assinado_por_user_id');
    }

    public function anexos()
    {
        return $this->hasMany(CrmAdminProcessAtoAnexo::class, 'ato_id');
    }

    // ── Movimentações padrão ──

    public const MOVIMENTACOES = [
        // Atos internos do escritório
        'despacho'             => 'Despacho',
        'parecer'              => 'Parecer / Análise',
        'nota_interna'         => 'Nota Interna',

        // Documentos juntados / produzidos
        'juntada'              => 'Juntada de Documento',
        'elaboracao_minuta'    => 'Elaboração de Minuta',
        'elaboracao_peticao'   => 'Elaboração de Petição',
        'elaboracao_contrato'  => 'Elaboração de Contrato',
        'elaboracao_procuracao'=> 'Elaboração de Procuração',
        'elaboracao_escritura' => 'Elaboração de Escritura',
        'elaboracao_oficio'    => 'Elaboração de Ofício',
        'elaboracao_requerimento' => 'Elaboração de Requerimento',

        // Atos externos / cartório / órgão
        'protocolo_orgao'      => 'Protocolo em Órgão / Cartório',
        'diligencia_externa'   => 'Diligência Externa',
        'certidao_obtida'      => 'Certidão Obtida',
        'recebimento_documento'=> 'Recebimento de Documento',
        'assinatura'           => 'Assinatura de Documento',
        'registro_cartorio'    => 'Registro em Cartório',
        'averbacao'            => 'Averbação',

        // Financeiro
        'pagamento_taxa'       => 'Pagamento de Taxa / Emolumento',
        'comprovante_pagamento'=> 'Comprovante de Pagamento',

        // Comunicação
        'comunicacao_cliente'  => 'Comunicação ao Cliente',
        'comunicacao_terceiro' => 'Comunicação a Terceiro',
        'envio_documento'      => 'Envio de Documento',

        // Aguardando
        'aguardando_cliente'   => 'Aguardando Providência do Cliente',
        'aguardando_orgao'     => 'Aguardando Órgão / Cartório',
        'aguardando_terceiro'  => 'Aguardando Terceiro',

        // Automáticos (gerados pelo sistema)
        'tramitacao'           => 'Tramitação',
        'conclusao'            => 'Conclusão',
        'abertura'             => 'Abertura',
    ];

    // ── Helpers ──

    public static function proximoNumero(int $processId): int
    {
        return (int) self::where('admin_process_id', $processId)->max('numero') + 1;
    }

    public static function movimentacoesManuais(): array
    {
        // Retorna só as que o advogado pode selecionar (exclui as automáticas)
        return collect(self::MOVIMENTACOES)
            ->except(['tramitacao', 'conclusao', 'abertura'])
            ->all();
    }

    public function tipoLabel(): string
    {
        return self::MOVIMENTACOES[$this->tipo]
            ?? ucfirst(str_replace('_', ' ', $this->tipo));
    }

    public function tipoColor(): string
    {
        return match($this->tipo) {
            'despacho'              => 'text-blue-600 bg-blue-50 border-blue-200',
            'parecer'               => 'text-indigo-600 bg-indigo-50 border-indigo-200',
            'nota_interna'          => 'text-gray-500 bg-gray-100 border-gray-300',

            'juntada',
            'elaboracao_minuta',
            'elaboracao_peticao',
            'elaboracao_contrato',
            'elaboracao_procuracao',
            'elaboracao_escritura',
            'elaboracao_oficio',
            'elaboracao_requerimento' => 'text-purple-600 bg-purple-50 border-purple-200',

            'protocolo_orgao'       => 'text-orange-600 bg-orange-50 border-orange-200',
            'diligencia_externa'    => 'text-amber-600 bg-amber-50 border-amber-200',
            'certidao_obtida'       => 'text-green-600 bg-green-50 border-green-200',
            'recebimento_documento' => 'text-teal-600 bg-teal-50 border-teal-200',
            'assinatura'            => 'text-emerald-700 bg-emerald-50 border-emerald-200',
            'registro_cartorio'     => 'text-lime-700 bg-lime-50 border-lime-200',
            'averbacao'             => 'text-green-700 bg-green-50 border-green-200',

            'pagamento_taxa'        => 'text-yellow-700 bg-yellow-50 border-yellow-200',
            'comprovante_pagamento' => 'text-emerald-600 bg-emerald-50 border-emerald-200',

            'comunicacao_cliente'   => 'text-sky-600 bg-sky-50 border-sky-200',
            'comunicacao_terceiro'  => 'text-cyan-600 bg-cyan-50 border-cyan-200',
            'envio_documento'       => 'text-blue-500 bg-blue-50 border-blue-200',

            'aguardando_cliente'    => 'text-yellow-600 bg-yellow-50 border-yellow-200',
            'aguardando_orgao'      => 'text-orange-500 bg-orange-50 border-orange-200',
            'aguardando_terceiro'   => 'text-amber-500 bg-amber-50 border-amber-200',

            'tramitacao'            => 'text-rose-600 bg-rose-50 border-rose-200',
            'conclusao'             => 'text-green-700 bg-green-100 border-green-300',
            'abertura'              => 'text-blue-700 bg-blue-100 border-blue-300',

            default                 => 'text-gray-600 bg-gray-50 border-gray-200',
        };
    }
}
