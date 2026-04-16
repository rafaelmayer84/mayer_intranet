<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class RelatorioCeo extends Model
{
    protected $table = 'relatorios_ceo';

    protected $fillable = [
        'periodo_inicio',
        'periodo_fim',
        'status',
        'dados_json',
        'analise_json',
        'pdf_path',
        'metadata',
        'erro',
    ];

    protected $casts = [
        'periodo_inicio' => 'date',
        'periodo_fim'    => 'date',
        'metadata'       => 'array',
    ];

    public function analise(): array
    {
        if (!$this->analise_json) return [];
        return json_decode($this->analise_json, true) ?? [];
    }

    public function dados(): array
    {
        if (!$this->dados_json) return [];
        return json_decode($this->dados_json, true) ?? [];
    }

    public function isPdf(): bool
    {
        return $this->status === 'success' && $this->pdf_path
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->pdf_path);
    }

    public function labelStatus(): string
    {
        return match ($this->status) {
            'queued'  => 'Na fila',
            'running' => 'Gerando...',
            'success' => 'Pronto',
            'failed'  => 'Erro',
            default   => $this->status,
        };
    }
}
