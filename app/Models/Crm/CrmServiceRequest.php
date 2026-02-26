<?php
namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmServiceRequest extends Model
{
    protected $table = 'crm_service_requests';

    protected $fillable = [
        'account_id', 'category', 'subject', 'description', 'priority', 'status',
        'requested_by_user_id', 'assigned_to_user_id', 'approved_by_user_id',
        'requires_approval', 'resolution_notes', 'assigned_at', 'approved_at', 'resolved_at',
    ];

    protected $casts = [
        'requires_approval' => 'boolean',
        'assigned_at' => 'datetime',
        'approved_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function account()
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function requestedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'requested_by_user_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by_user_id');
    }

    public function comments()
    {
        return $this->hasMany(CrmServiceRequestComment::class, 'service_request_id');
    }

    /**
     * Categorias com flag de aprovação obrigatória
     */
    public static function categorias(): array
    {
        return [
            'renuncia_mandato'     => ['label' => 'Renúncia de Mandato', 'approval' => true],
            'substabelecimento'    => ['label' => 'Substabelecimento', 'approval' => true],
            'alteracao_cadastral'  => ['label' => 'Alteração Cadastral', 'approval' => false],
            'emissao_procuracao'   => ['label' => 'Emissão de Procuração', 'approval' => false],
            'solicitacao_documentos' => ['label' => 'Solicitação de Documentos', 'approval' => false],
            'cobranca_honorarios'  => ['label' => 'Cobrança de Honorários', 'approval' => true],
            'acordo_judicial'      => ['label' => 'Acordo Judicial', 'approval' => true],
            'encerramento_caso'    => ['label' => 'Encerramento de Caso', 'approval' => true],
            'transferencia_responsavel' => ['label' => 'Transferência de Responsável', 'approval' => true],
            'solicitacao_ti'       => ['label' => 'Solicitação de TI', 'approval' => false],
            'solicitacao_financeiro' => ['label' => 'Solicitação Financeira', 'approval' => false],
            'solicitacao_rh'       => ['label' => 'Solicitação de RH', 'approval' => false],
            'outra'                => ['label' => 'Outra Solicitação', 'approval' => false],
        ];
    }

    /**
     * Verifica se a categoria exige aprovação
     */
    public static function categoriaRequerAprovacao(string $category): bool
    {
        return self::categorias()[$category]['approval'] ?? false;
    }

    public function isPendingApproval(): bool
    {
        return $this->requires_approval && $this->status === 'aguardando_aprovacao';
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['aberto', 'em_andamento', 'aguardando_aprovacao']);
    }

    public static function statusLabel(string $status): string
    {
        return match($status) {
            'aberto' => 'Aberto',
            'em_andamento' => 'Em Andamento',
            'aguardando_aprovacao' => 'Aguardando Aprovação',
            'aprovado' => 'Aprovado',
            'rejeitado' => 'Rejeitado',
            'concluido' => 'Concluído',
            'cancelado' => 'Cancelado',
            default => $status,
        };
    }

    public static function statusBadge(string $status): string
    {
        return match($status) {
            'aberto' => 'bg-blue-100 text-blue-700',
            'em_andamento' => 'bg-yellow-100 text-yellow-700',
            'aguardando_aprovacao' => 'bg-purple-100 text-purple-700',
            'aprovado' => 'bg-green-100 text-green-700',
            'rejeitado' => 'bg-red-100 text-red-700',
            'concluido' => 'bg-gray-100 text-gray-600',
            'cancelado' => 'bg-gray-100 text-gray-400',
            default => 'bg-gray-100 text-gray-600',
        };
    }

    public static function priorityBadge(string $priority): string
    {
        return match($priority) {
            'baixa' => 'bg-gray-100 text-gray-600',
            'normal' => 'bg-blue-50 text-blue-600',
            'alta' => 'bg-orange-100 text-orange-700',
            'urgente' => 'bg-red-100 text-red-700',
            default => 'bg-gray-100 text-gray-600',
        };
    }
}
