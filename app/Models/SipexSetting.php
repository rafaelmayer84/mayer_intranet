<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SipexSetting extends Model
{
    protected $table = 'sipex_settings';

    protected $fillable = ['chave', 'valor', 'descricao', 'updated_by'];

    /**
     * Retorna o valor de uma configuracao pelo nome da chave
     */
    public static function get(string $chave, ?string $default = null): ?string
    {
        return static::where('chave', $chave)->value('valor') ?? $default;
    }

    /**
     * Define o valor de uma configuracao
     */
    public static function set(string $chave, string $valor, ?int $userId = null): void
    {
        static::updateOrCreate(
            ['chave' => $chave],
            ['valor' => $valor, 'updated_by' => $userId]
        );
    }

    /**
     * Lista de modelos disponiveis para o SIPEX
     */
    public static function modelosDisponiveis(): array
    {
        return [
            'gpt-5.4'           => 'GPT-5.4 (OpenAI) — Reasoning, rapido',
            'gpt-5.2'           => 'GPT-5.2 (OpenAI) — Padrao atual',
            'gpt-4o-mini'       => 'GPT-4o Mini (OpenAI) — Economico',
            'claude-opus-4-6'   => 'Claude Opus 4.6 (Anthropic) — Reasoning profundo',
            'claude-sonnet-4-6' => 'Claude Sonnet 4.6 (Anthropic) — Equilibrado',
        ];
    }

    /**
     * Verifica se um modelo e da Anthropic (Claude)
     */
    public static function isClaudeModel(string $model): bool
    {
        return str_starts_with($model, 'claude-');
    }
}
