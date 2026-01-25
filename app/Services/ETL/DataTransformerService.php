<?php

namespace App\Services\ETL;

use Illuminate\Support\Facades\Log;

/**
 * Serviço de transformação e normalização de dados
 * Converte dados de diferentes fontes (DataJuri, ESPO CRM) para formato padrão
 */
class DataTransformerService
{
    /**
     * Normalizar cliente de qualquer fonte
     */
    public function normalizeCliente(array $data, string $fonte = 'datajuri'): array
    {
        return match($fonte) {
            'datajuri' => $this->normalizeClienteDataJuri($data),
            'espocrm' => $this->normalizeClienteEspoCrm($data),
            default => []
        };
    }

    /**
     * Normalizar cliente do DataJuri (Pessoa)
     */
    private function normalizeClienteDataJuri(array $pessoa): array
    {
        return [
            'datajuri_id' => $pessoa['id'] ?? null,
            'nome' => $pessoa['nome'] ?? 'Sem Nome',
            'tipo' => $this->mapearTipoPessoa($pessoa['tipo'] ?? 'PF'),
            'cpf_cnpj' => $pessoa['cpfCnpj'] ?? null,
            'email' => $pessoa['email'] ?? null,
            'telefone' => $pessoa['telefone'] ?? null,
            'endereco' => $pessoa['endereco'] ?? null,
            'cidade' => $pessoa['cidade'] ?? null,
            'estado' => $pessoa['estado'] ?? null,
            'valor_carteira' => $this->calcularValorCarteira($pessoa),
            'data_cadastro' => $pessoa['dataCadastro'] ?? now(),
            'ativo' => true,
            'fonte' => 'datajuri',
            'data_sincronizacao' => now()
        ];
    }

    /**
     * Normalizar cliente do ESPO CRM (Account)
     */
    private function normalizeClienteEspoCrm(array $conta): array
    {
        return [
            'espocrm_id' => $conta['id'] ?? null,
            'nome' => $conta['name'] ?? 'Sem Nome',
            'tipo' => $this->mapearTipoEspoCrm($conta['type'] ?? 'PF'),
            'email' => $conta['emailAddress'] ?? null,
            'telefone' => preg_replace('/[^0-9]/', '', $conta['phoneNumber'] ?? ''),
            'website' => $conta['website'] ?? null,
            'valor_carteira' => 0, // ESPO CRM não tem valor de carteira, vem do DataJuri
            'data_cadastro' => $conta['createdAt'] ?? now(),
            'ativo' => true,
            'fonte' => 'espocrm',
            'data_sincronizacao' => now()
        ];
    }

    /**
     * Mapear tipo de pessoa (DataJuri)
     */
    private function mapearTipoPessoa(?string $tipo): string
    {
        if (!$tipo) return 'PF';
        
        $tipo_lower = strtolower($tipo);
        if (strpos($tipo_lower, 'jurídica') !== false || 
            strpos($tipo_lower, 'empresa') !== false ||
            strpos($tipo_lower, 'pj') !== false) {
            return 'PJ';
        }
        return 'PF';
    }

    /**
     * Mapear tipo de conta (ESPO CRM)
     */
    private function mapearTipoEspoCrm(?string $tipo): string
    {
        if (!$tipo) return 'PF';
        
        $tipo_lower = strtolower($tipo);
        if (strpos($tipo_lower, 'company') !== false || 
            strpos($tipo_lower, 'empresa') !== false ||
            strpos($tipo_lower, 'pj') !== false) {
            return 'PJ';
        }
        return 'PF';
    }

    /**
     * Calcular valor de carteira (soma de contratos ativos)
     * Este é um placeholder - em produção, seria necessário buscar contratos
     */
    private function calcularValorCarteira(array $pessoa): float
    {
        // Placeholder: retorna 0
        // Em produção, seria necessário fazer uma chamada adicional à API DataJuri
        // para buscar contratos associados a esta pessoa
        return 0;
    }

    /**
     * Normalizar lead do ESPO CRM
     */
    public function normalizeLead(array $lead): array
    {
        return [
            'espocrm_id' => $lead['id'] ?? null,
            'nome' => $lead['name'] ?? 'Sem Nome',
            'email' => $lead['emailAddress'] ?? null,
            'telefone' => preg_replace('/[^0-9]/', '', $lead['phoneNumber'] ?? ''),
            'origem' => $this->mapearOrigemLead($lead['source'] ?? 'Outro'),
            'status' => $this->mapearStatusLead($lead['status'] ?? 'novo'),
            'data_criacao' => $lead['createdAt'] ?? now(),
            'data_sincronizacao' => now()
        ];
    }

    /**
     * Mapear origem de lead
     */
    private function mapearOrigemLead(?string $origem): string
    {
        if (!$origem) return 'Outro';
        
        $origem_lower = strtolower($origem);
        
        if (strpos($origem_lower, 'whatsapp') !== false) return 'WhatsApp Bot';
        if (strpos($origem_lower, 'parceiro') !== false) return 'Parceiros';
        if (strpos($origem_lower, 'site') !== false) return 'Website';
        if (strpos($origem_lower, 'referral') !== false) return 'Referência';
        if (strpos($origem_lower, 'email') !== false) return 'Email';
        
        return 'Outro';
    }

    /**
     * Mapear status de lead
     */
    private function mapearStatusLead(?string $status): string
    {
        if (!$status) return 'novo';
        
        $status_lower = strtolower($status);
        
        if (strpos($status_lower, 'novo') !== false) return 'novo';
        if (strpos($status_lower, 'qualificado') !== false) return 'qualificado';
        if (strpos($status_lower, 'convertido') !== false) return 'convertido';
        if (strpos($status_lower, 'perdido') !== false) return 'perdido';
        if (strpos($status_lower, 'inativo') !== false) return 'inativo';
        
        return 'novo';
    }

    /**
     * Normalizar oportunidade do ESPO CRM
     */
    public function normalizeOportunidade(array $oportunidade): array
    {
        return [
            'espocrm_id' => $oportunidade['id'] ?? null,
            'nome' => $oportunidade['name'] ?? 'Sem Nome',
            'valor' => (float)($oportunidade['amount'] ?? 0),
            'estagio' => $this->mapearEstagioOportunidade($oportunidade['stage'] ?? 'prospectando'),
            'probabilidade' => $this->calcularProbabilidade($oportunidade['stage'] ?? 'prospectando'),
            'data_criacao' => $oportunidade['createdAt'] ?? now(),
            'data_sincronizacao' => now()
        ];
    }

    /**
     * Mapear estágio de oportunidade
     */
    private function mapearEstagioOportunidade(?string $estagio): string
    {
        if (!$estagio) return 'prospectando';
        
        $estagio_lower = strtolower($estagio);
        
        if (strpos($estagio_lower, 'prospect') !== false) return 'prospectando';
        if (strpos($estagio_lower, 'qualif') !== false) return 'qualificacao';
        if (strpos($estagio_lower, 'proposta') !== false) return 'proposta';
        if (strpos($estagio_lower, 'negocia') !== false) return 'negociacao';
        if (strpos($estagio_lower, 'ganha') !== false || strpos($estagio_lower, 'won') !== false) return 'ganha';
        if (strpos($estagio_lower, 'perdida') !== false || strpos($estagio_lower, 'lost') !== false) return 'perdida';
        
        return 'prospectando';
    }

    /**
     * Calcular probabilidade baseado no estágio
     */
    private function calcularProbabilidade(string $estagio): int
    {
        $probabilidades = [
            'prospectando' => 10,
            'qualificacao' => 25,
            'proposta' => 50,
            'negociacao' => 75,
            'ganha' => 100,
            'perdida' => 0
        ];

        return $probabilidades[$estagio] ?? 10;
    }
}
