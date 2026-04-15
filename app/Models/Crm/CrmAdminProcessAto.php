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

    // ── Helpers ──

    public static function proximoNumero(int $processId): int
    {
        return (int) self::where('admin_process_id', $processId)->max('numero') + 1;
    }

    public function tipoLabel(): string
    {
        return match($this->tipo) {
            'despacho'       => 'Despacho',
            'parecer'        => 'Parecer',
            'oficio'         => 'Ofício',
            'requerimento'   => 'Requerimento',
            'certidao'       => 'Certidão',
            'protocolo'      => 'Protocolo',
            'procuracao'     => 'Procuração',
            'minuta'         => 'Minuta',
            'contrato'       => 'Contrato',
            'nota_interna'   => 'Nota Interna',
            'comunicacao'    => 'Comunicação',
            'recebimento'    => 'Recebimento',
            'guia_pagamento' => 'Guia de Pagamento',
            'comprovante'    => 'Comprovante',
            'peticao'        => 'Petição',
            'escritura'      => 'Escritura',
            'relatorio'      => 'Relatório',
            'tramitacao'     => 'Tramitação',
            'conclusao'      => 'Conclusão',
            'abertura'       => 'Abertura',
            'outro'          => 'Documento',
            default          => ucfirst(str_replace('_', ' ', $this->tipo)),
        };
    }

    public function tipoIcon(): string
    {
        return match($this->tipo) {
            'despacho'       => 'file-text',
            'parecer'        => 'file-check',
            'oficio'         => 'mail',
            'requerimento'   => 'file-plus',
            'certidao'       => 'award',
            'protocolo'      => 'clipboard',
            'procuracao'     => 'shield',
            'minuta'         => 'edit-3',
            'contrato'       => 'file-signature',
            'nota_interna'   => 'lock',
            'comunicacao'    => 'message-circle',
            'recebimento'    => 'download',
            'guia_pagamento' => 'dollar-sign',
            'comprovante'    => 'check-circle',
            'peticao'        => 'file-plus',
            'escritura'      => 'book-open',
            'relatorio'      => 'bar-chart-2',
            'tramitacao'     => 'send',
            'conclusao'      => 'flag',
            'abertura'       => 'folder-plus',
            default          => 'file',
        };
    }

    public function tipoColor(): string
    {
        return match($this->tipo) {
            'despacho'       => 'text-blue-600 bg-blue-50 border-blue-200',
            'parecer'        => 'text-indigo-600 bg-indigo-50 border-indigo-200',
            'oficio'         => 'text-gray-600 bg-gray-50 border-gray-200',
            'requerimento'   => 'text-purple-600 bg-purple-50 border-purple-200',
            'certidao'       => 'text-green-600 bg-green-50 border-green-200',
            'protocolo'      => 'text-orange-600 bg-orange-50 border-orange-200',
            'nota_interna'   => 'text-gray-500 bg-gray-100 border-gray-300',
            'comunicacao'    => 'text-sky-600 bg-sky-50 border-sky-200',
            'recebimento'    => 'text-teal-600 bg-teal-50 border-teal-200',
            'guia_pagamento' => 'text-yellow-700 bg-yellow-50 border-yellow-200',
            'comprovante'    => 'text-emerald-600 bg-emerald-50 border-emerald-200',
            'tramitacao'     => 'text-rose-600 bg-rose-50 border-rose-200',
            'conclusao'      => 'text-green-700 bg-green-100 border-green-300',
            'abertura'       => 'text-blue-700 bg-blue-100 border-blue-300',
            default          => 'text-gray-600 bg-gray-50 border-gray-200',
        };
    }
}
