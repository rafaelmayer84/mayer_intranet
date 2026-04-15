<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmAdminProcess extends Model
{
    use SoftDeletes;

    protected $table = 'crm_admin_processes';

    protected $fillable = [
        'protocolo','account_id','opportunity_id','template_id','tipo','titulo','descricao',
        'status','prioridade','nivel_acesso','owner_user_id','com_user_id',
        'orgao_destino','numero_externo',
        'prazo_estimado','prazo_final','valor_honorarios','valor_despesas','client_visible',
        'suspended_reason','suspended_until','suspended_at','concluded_at',
        'cancelled_at','cancelled_reason',
    ];

    protected $casts = [
        'prazo_estimado'  => 'date',
        'prazo_final'     => 'date',
        'suspended_until' => 'datetime',
        'suspended_at'    => 'datetime',
        'concluded_at'    => 'datetime',
        'cancelled_at'    => 'datetime',
        'client_visible'  => 'boolean',
        'valor_honorarios'=> 'decimal:2',
        'valor_despesas'  => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function account()
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function owner()
    {
        return $this->belongsTo(\App\Models\User::class, 'owner_user_id');
    }

    public function comUsuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'com_user_id');
    }

    public function atos()
    {
        return $this->hasMany(CrmAdminProcessAto::class, 'admin_process_id')->orderBy('numero');
    }

    public function tramitacoes()
    {
        return $this->hasMany(CrmAdminProcessTramitacao::class, 'admin_process_id')->orderByDesc('created_at');
    }

    public function template()
    {
        return $this->belongsTo(CrmAdminProcessTemplate::class, 'template_id');
    }

    public function steps()
    {
        return $this->hasMany(CrmAdminProcessStep::class, 'admin_process_id')->orderBy('order');
    }

    public function timeline()
    {
        return $this->hasMany(CrmAdminProcessTimeline::class, 'admin_process_id')->orderByDesc('happened_at');
    }

    public function documents()
    {
        return $this->hasMany(CrmAdminProcessDocument::class, 'admin_process_id')->orderByDesc('created_at');
    }

    public function checklist()
    {
        return $this->hasMany(CrmAdminProcessChecklist::class, 'admin_process_id');
    }

    // ── Computed ───────────────────────────────────────────────

    public function getProgressoAttribute(): int
    {
        $steps = $this->steps;
        $total = $steps->whereNotIn('status', ['nao_aplicavel'])->count();
        if ($total === 0) return 0;
        $done = $steps->where('status', 'concluido')->count();
        return (int) round(($done / $total) * 100);
    }

    public function getEtapasConcluidasAttribute(): int
    {
        return $this->steps->where('status', 'concluido')->count();
    }

    public function getEtapasTotalAttribute(): int
    {
        return $this->steps->whereNotIn('status', ['nao_aplicavel'])->count();
    }

    public function getEtapaAtualAttribute(): ?CrmAdminProcessStep
    {
        return $this->steps
            ->whereIn('status', ['em_andamento', 'aguardando'])
            ->first()
            ?? $this->steps->where('status', 'pendente')->first();
    }

    public function isAtrasado(): bool
    {
        if ($this->prazo_final && $this->prazo_final->isPast() && !in_array($this->status, ['concluido','cancelado'])) {
            return true;
        }
        return $this->steps
            ->whereNotIn('status', ['concluido','nao_aplicavel'])
            ->filter(fn($s) => $s->deadline_at && \Carbon\Carbon::parse($s->deadline_at)->isPast())
            ->isNotEmpty();
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeAtivos($q)
    {
        return $q->whereNotIn('status', ['concluido','cancelado']);
    }

    public function scopeAtrasados($q)
    {
        return $q->whereNotIn('status', ['concluido','cancelado'])
                 ->where('prazo_final', '<', now()->toDateString());
    }

    // ── Helpers ────────────────────────────────────────────────

    public static function gerarProtocolo(): string
    {
        $ano  = now()->year;
        $last = self::whereYear('created_at', $ano)->max('id') ?? 0;
        $seq  = str_pad($last + 1, 4, '0', STR_PAD_LEFT);
        return "ADM-{$ano}-{$seq}";
    }

    public function statusLabel(): string
    {
        return match($this->status) {
            'rascunho'          => 'Rascunho',
            'aberto'            => 'Aberto',
            'em_andamento'      => 'Em andamento',
            'aguardando_cliente'=> 'Aguardando cliente',
            'aguardando_terceiro'=> 'Aguardando terceiro',
            'suspenso'          => 'Suspenso',
            'concluido'         => 'Concluído',
            'cancelado'         => 'Cancelado',
            default             => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match($this->status) {
            'rascunho'           => 'bg-gray-100 text-gray-600',
            'aberto'             => 'bg-blue-100 text-blue-700',
            'em_andamento'       => 'bg-indigo-100 text-indigo-700',
            'aguardando_cliente' => 'bg-yellow-100 text-yellow-700',
            'aguardando_terceiro'=> 'bg-orange-100 text-orange-700',
            'suspenso'           => 'bg-red-100 text-red-600',
            'concluido'          => 'bg-green-100 text-green-700',
            'cancelado'          => 'bg-gray-200 text-gray-500',
            default              => 'bg-gray-100 text-gray-600',
        };
    }

    public function tipoLabel(): string
    {
        return match($this->tipo) {
            'transferencia_imovel'   => 'Transferência de Imóvel',
            'inventario_extrajudicial'=> 'Inventário Extrajudicial',
            'divorcio_extrajudicial' => 'Divórcio Extrajudicial',
            'abertura_empresa'       => 'Abertura de Empresa',
            'usucapiao_extrajudicial'=> 'Usucapião Extrajudicial',
            'retificacao_registro'   => 'Retificação de Registro',
            'dissolucao_sociedade'   => 'Dissolução de Sociedade',
            'regularizacao_fundiaria'=> 'Regularização Fundiária',
            'testamento'             => 'Testamento',
            'emancipacao'            => 'Emancipação',
            'reconhecimento_paternidade'=> 'Reconhecimento de Paternidade',
            'alteracao_contratual'   => 'Alteração Contratual',
            'outro'                  => 'Outro',
            default                  => ucfirst(str_replace('_', ' ', $this->tipo)),
        };
    }
}
