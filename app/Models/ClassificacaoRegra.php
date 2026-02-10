<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class ClassificacaoRegra extends Model
{
    protected $table = 'classificacao_regras';

    protected $fillable = [
        'codigo_plano',
        'nome_plano',
        'classificacao',
        'tipo_movimento',
        'origem',
        'ativo',
        'prioridade',
        'observacoes',
    ];

    protected $casts = [
        'ativo' => 'boolean',
        'prioridade' => 'integer',
    ];

    /**
     * Classificações disponíveis no sistema
     */
    const CLASSIFICACOES = [
        'RECEITA_PF' => 'Receita Pessoa Física',
        'RECEITA_PJ' => 'Receita Pessoa Jurídica',
        'DESPESA' => 'Despesa',
        'DEDUCAO' => 'Dedução',
        'RECEITA_FINANCEIRA' => 'Receita Financeira',
        'DESPESA_FINANCEIRA' => 'Despesa Financeira',
        'PENDENTE_CLASSIFICACAO' => 'Pendente de Classificação',
    ];

    /**
     * Tipos de movimento
     */
    const TIPOS_MOVIMENTO = [
        'RECEITA' => 'Receita',
        'DESPESA' => 'Despesa',
        'INDEFINIDO' => 'Indefinido',
    ];

    /**
     * Scope: apenas regras ativas
     */
    public function scopeAtivas(Builder $query): Builder
    {
        return $query->where('ativo', true);
    }

    /**
     * Scope: filtrar por código do plano
     */
    public function scopePorCodigo(Builder $query, string $codigo): Builder
    {
        return $query->where('codigo_plano', $codigo);
    }

    /**
     * Scope: ordenar por prioridade (maior primeiro)
     */
    public function scopePorPrioridade(Builder $query): Builder
    {
        return $query->orderBy('prioridade', 'desc');
    }

    /**
     * Verifica se o código fornecido corresponde ao padrão da regra (suporte a wildcard)
     */
    public function matches(string $codigo): bool
    {
        $pattern = $this->codigo_plano;

        // Se não tem wildcard, comparação exata
        if (strpos($pattern, '*') === false && strpos($pattern, '%') === false) {
            return $pattern === $codigo;
        }

        // Converter wildcards para regex
        $regex = str_replace(['*', '%'], '.*', preg_quote($pattern, '/'));
        return (bool) preg_match('/^' . $regex . '$/', $codigo);
    }

    /**
     * Retorna lista de classificações para select
     */
    public static function classificacoes(): array
    {
        return self::CLASSIFICACOES;
    }

    /**
     * Retorna lista de tipos de movimento para select
     */
    public static function tiposMovimento(): array
    {
        return self::TIPOS_MOVIMENTO;
    }

    /**
     * Busca classificação para um código de plano (método estático de conveniência)
     */
    public static function buscarClassificacao(string $codigoPlano): string
    {
        $regra = self::where('codigo_plano', $codigoPlano)
            ->where('ativo', true)
            ->orderBy('prioridade', 'desc')
            ->first();

        return $regra ? $regra->classificacao : 'PENDENTE_CLASSIFICACAO';
    }

    /**
     * Busca regra mais específica para um código (considera wildcards)
     */
    public static function buscarRegraMaisEspecifica(string $codigoPlano): ?self
    {
        // Primeiro tenta match exato
        $regra = self::ativas()
            ->porCodigo($codigoPlano)
            ->porPrioridade()
            ->first();

        if ($regra) {
            return $regra;
        }

        // Depois tenta wildcards
        $regras = self::ativas()
            ->where(function ($q) {
                $q->where('codigo_plano', 'like', '%*%')
                  ->orWhere('codigo_plano', 'like', '%\%%');
            })
            ->porPrioridade()
            ->get();

        foreach ($regras as $regra) {
            if ($regra->matches($codigoPlano)) {
                return $regra;
            }
        }

        return null;
    }
}
