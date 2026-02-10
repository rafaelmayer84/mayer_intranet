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

    // Constantes de classificacao
    const RECEITA_PF = 'RECEITA_PF';
    const RECEITA_PJ = 'RECEITA_PJ';
    const RECEITA_FINANCEIRA = 'RECEITA_FINANCEIRA';
    const PENDENTE_CLASSIFICACAO = 'PENDENTE_CLASSIFICACAO';
    const DESPESA = 'DESPESA';
    const DESPESA_FINANCEIRA = 'DESPESA_FINANCEIRA';

    /**
     * FIX v3.0: Constante DEDUCAO para deducoes da receita (3.01.03.*)
     * Ex: Simples Nacional, INSS, Salarios, Distribuicao de lucros.
     * Valores na Arvore do Plano de Contas sao NEGATIVOS.
     * No DB sao armazenados como POSITIVOS (abs) com classificacao=DEDUCAO.
     */
    const DEDUCAO = 'DEDUCAO';

    // Planos de conta para classificacao automatica
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

        // Classificacao Manual
        foreach (self::PLANOS_MANUAL as $codigo) {
            if (strpos($planoContas, $codigo) !== false) {
                return self::PENDENTE_CLASSIFICACAO;
            }
        }

        return self::PENDENTE_CLASSIFICACAO;
    }

    /**
     * Extrair codigo do plano de contas (ex: 3.01.01.01)
     */
    public static function extrairCodigoPlano(string $planoContas): ?string
    {
        if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $planoContas, $matches)) {
            return $matches[1];
        }
        if (preg_match('/(\d+\.\d+\.\d+)/', $planoContas, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function scopePorPeriodo($query, int $mes, int $ano)
    {
        return $query->where('mes', $mes)->where('ano', $ano);
    }

    public function scopePorClassificacao($query, string $classificacao)
    {
        return $query->where('classificacao', $classificacao);
    }

    public function scopeReceitas($query)
    {
        return $query->where('valor', '>', 0);
    }

    public function scopePendentes($query)
    {
        return $query->where('classificacao', self::PENDENTE_CLASSIFICACAO)
            ->where(function($q) {
                $q->whereNull('classificacao_manual')
                  ->orWhere('classificacao_manual', 0)
                  ->orWhere('classificacao_manual', '0')
                  ->orWhere('classificacao_manual', false);
            });
    }

    public static function totalPorClassificacao(int $mes, int $ano, string $classificacao): float
    {
        return self::porPeriodo($mes, $ano)
                   ->porClassificacao($classificacao)
                   ->receitas()
                   ->sum('valor');
    }

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
