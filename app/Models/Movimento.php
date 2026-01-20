<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Movimento extends Model
{
    use HasFactory;

    protected $table = 'movimentos';

    protected $fillable = [
        'datajuri_id',
        'data',
        'mes',
        'ano',
        'valor',
        'plano_contas',
        'codigo_plano',
        'classificacao',
        'classificacao_manual',
        'pessoa',
        'descricao',
        'observacao',
        'conta',
        'conciliado',
    ];

    protected $casts = [
        'data' => 'date',
        'valor' => 'decimal:2',
        'classificacao_manual' => 'boolean',
        'conciliado' => 'boolean',
    ];

    // Constantes de classificação
    const RECEITA_PF = 'RECEITA_PF';
    const RECEITA_PJ = 'RECEITA_PJ';
    const RECEITA_FINANCEIRA = 'RECEITA_FINANCEIRA';
    const PENDENTE_CLASSIFICACAO = 'PENDENTE_CLASSIFICACAO';
    const DESPESA = 'DESPESA';

    // Planos de conta para classificação automática
    const PLANOS_PF = ['3.01.01.01', '3.01.01.03'];
    const PLANOS_PJ = ['3.01.01.02', '3.01.01.05'];
    const PLANOS_FINANCEIRO = ['3.01.02.05'];
    const PLANOS_MANUAL = ['3.01.01.06', '3.01.02.01', '3.01.02.03', '3.01.02.04', '3.01.02.06', '3.01.02.07'];

    /**
     * Classificar automaticamente baseado no plano de contas
     */
    public static function classificarPorPlano(string $planoContas): string
    {
        // Receita PF
        foreach (self::PLANOS_PF as $codigo) {
            if (strpos($planoContas, $codigo) !== false) {
                return self::RECEITA_PF;
            }
        }

        // Receita PJ
        foreach (self::PLANOS_PJ as $codigo) {
            if (strpos($planoContas, $codigo) !== false) {
                return self::RECEITA_PJ;
            }
        }

        // Receita Financeira
        foreach (self::PLANOS_FINANCEIRO as $codigo) {
            if (strpos($planoContas, $codigo) !== false) {
                return self::RECEITA_FINANCEIRA;
            }
        }

        // Classificação Manual
        foreach (self::PLANOS_MANUAL as $codigo) {
            if (strpos($planoContas, $codigo) !== false) {
                return self::PENDENTE_CLASSIFICACAO;
            }
        }

        // Se não encontrou nenhum padrão conhecido, retorna pendente
        return self::PENDENTE_CLASSIFICACAO;
    }

    /**
     * Extrair código do plano de contas (ex: 3.01.01.01)
     */
    public static function extrairCodigoPlano(string $planoContas): ?string
    {
        // Buscar padrão de código no plano de contas
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $planoContas, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(\d+\.\d+\.\d+)/', $planoContas, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Scope para filtrar por mês/ano
     */
    public function scopePorPeriodo($query, int $mes, int $ano)
    {
        return $query->where('mes', $mes)->where('ano', $ano);
    }

    /**
     * Scope para filtrar por classificação
     */
    public function scopePorClassificacao($query, string $classificacao)
    {
        return $query->where('classificacao', $classificacao);
    }

    /**
     * Scope para receitas (valores positivos)
     */
    public function scopeReceitas($query)
    {
        return $query->where('valor', '>', 0);
    }

    /**
     * Scope para pendentes de classificação manual
     */
    public function scopePendentes($query)
    {
        return $query->where('classificacao', self::PENDENTE_CLASSIFICACAO)
            ->where(function($q) {
                // Aceitar NULL, 0, '0', false como "não manual"
                $q->whereNull('classificacao_manual')
                  ->orWhere('classificacao_manual', 0)
                  ->orWhere('classificacao_manual', '0')
                  ->orWhere('classificacao_manual', false);
            });
    }

    /**
     * Obter total por classificação em um período
     */
    public static function totalPorClassificacao(int $mes, int $ano, string $classificacao): float
    {
        return self::porPeriodo($mes, $ano)
                   ->porClassificacao($classificacao)
                   ->receitas()
                   ->sum('valor');
    }

    /**
     * Obter resumo do mês
     */
    public static function resumoMes(int $mes, int $ano): array
    {
        return [
            'mes' => $mes,
            'ano' => $ano,
            'receita_pf' => self::totalPorClassificacao($mes, $ano, self::RECEITA_PF),
            'receita_pj' => self::totalPorClassificacao($mes, $ano, self::RECEITA_PJ),
            'receita_financeira' => self::totalPorClassificacao($mes, $ano, self::RECEITA_FINANCEIRA),
            'pendentes' => self::porPeriodo($mes, $ano)->pendentes()->count(),
            'total_registros' => self::porPeriodo($mes, $ano)->receitas()->count(),
        ];
    }
}
