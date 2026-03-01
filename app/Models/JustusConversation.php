<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JustusConversation extends Model
{
    use SoftDeletes;

    protected $table = 'justus_conversations';

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'mode',
        'status',
        'total_input_tokens',
        'total_output_tokens',
        'total_cost_brl',
        'style_version',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'total_cost_brl' => 'decimal:4',
    ];

    public const MODE_LABELS = [
        'consultor' => 'Consultor Jurídico',
        'assessor' => 'Assessor Processual',
    ];

    public const MODE_COLORS = [
        'consultor' => 'blue',
        'assessor' => 'emerald',
    ];

    public const TYPE_LABELS = [
        'analise_estrategica' => 'Análise Estratégica',
        'analise_completa' => 'Análise Completa',
        'peca' => 'Projeto de Peça',
        'calculo_prazo' => 'Cálculo de Prazo',
        'higiene_autos' => 'Higiene de Autos',
    ];

    public const TYPE_TAGS = [
        'analise_estrategica' => ['label' => 'Análise Estratégica', 'color' => 'blue'],
        'analise_completa' => 'Análise Completa',
        'peca' => 'Projeto de Peça',
        'calculo_prazo' => 'Cálculo de Prazo',
        'higiene_autos' => 'Higiene de Autos',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(JustusMessage::class, 'conversation_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(JustusAttachment::class, 'conversation_id');
    }

    public function processProfile(): HasOne
    {
        return $this->hasOne(JustusProcessProfile::class, 'conversation_id');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(JustusApproval::class, 'conversation_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->type] ?? $this->type;
    }

    public function getModeLabelAttribute(): string
    {
        return self::MODE_LABELS[$this->mode] ?? $this->mode;
    }

    public function getModeColorAttribute(): string
    {
        return self::MODE_COLORS[$this->mode] ?? 'gray';
    }

    public function getLastMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }

    public function getTokenSummaryAttribute(): string
    {
        $total = $this->total_input_tokens + $this->total_output_tokens;
        if ($total >= 1000) {
            return number_format($total / 1000, 1) . 'k';
        }
        return (string) $total;
    }
}
