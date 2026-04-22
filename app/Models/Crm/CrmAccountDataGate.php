<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmAccountDataGate extends Model
{
    protected $table = 'crm_account_data_gates';

    protected $fillable = [
        'account_id', 'owner_user_id', 'tipo',
        'dj_valor_snapshot', 'evidencia_local', 'status',
        'opened_at', 'first_seen_by_owner_at', 'resolved_at',
        'escalated_at', 'dj_valor_no_fechamento', 'penalidade_registrada',
        'excecao_justificativa', 'excecao_by_user_id', 'excecao_at',
    ];

    protected $casts = [
        'evidencia_local'        => 'array',
        'opened_at'              => 'datetime',
        'first_seen_by_owner_at' => 'datetime',
        'resolved_at'            => 'datetime',
        'escalated_at'           => 'datetime',
        'excecao_at'             => 'datetime',
        'penalidade_registrada'  => 'bool',
    ];

    public const STATUS_ABERTO           = 'aberto';
    public const STATUS_EM_REVISAO       = 'em_revisao';
    public const STATUS_RESOLVIDO_AUTO   = 'resolvido_auto';
    public const STATUS_RESOLVIDO_MANUAL = 'resolvido_manual';
    public const STATUS_EXCECAO          = 'excecao_justificada';
    public const STATUS_ESCALADO         = 'escalado';
    public const STATUS_CANCELADO        = 'cancelado';

    public const STATUS_ATIVOS = [
        self::STATUS_ABERTO,
        self::STATUS_EM_REVISAO,
        self::STATUS_ESCALADO,
    ];

    public const TIPO_ONBOARDING_COM_CONTRATO     = 'onboarding_com_contrato';
    public const TIPO_STATUS_CLIENTE_SEM_VINCULO  = 'status_cliente_sem_vinculo';
    public const TIPO_ADVERSA_COM_CONTRATO        = 'adversa_com_contrato';
    public const TIPO_INADIMPLENCIA_SUSPEITA_2099 = 'inadimplencia_suspeita_2099';
    public const TIPO_SEM_STATUS_PESSOA           = 'sem_status_pessoa';

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_user_id');
    }

    public function isAtivo(): bool
    {
        return in_array($this->status, self::STATUS_ATIVOS, true);
    }

    public function requerRevisao(): bool
    {
        return $this->status === self::STATUS_ABERTO;
    }

    public function jaEscalado(): bool
    {
        return $this->status === self::STATUS_ESCALADO;
    }
}
