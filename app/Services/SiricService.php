<?php

/**
 * ============================================================================
 * SIRIC v2 — SiricService
 * ============================================================================
 *
 * Serviço principal de coleta e métricas do SIRIC (Sistema de Análise de Crédito).
 *
 * Responsabilidades:
 * - Localizar cliente(s) no BD por CPF/CNPJ, telefone ou email (fallback em cascata)
 * - Coletar dados internos: Contas a Receber, Movimentos, Processos, Leads, WhatsApp
 * - Calcular métricas financeiras (totais pagos, atrasos, ticket médio, recorrência)
 * - Avaliar nível de relacionamento com o escritório (forte/moderado/fraco/desconhecido)
 * - Calcular gate_score DETERMINÍSTICO (v2) — regras fixas no PHP, sem depender da IA
 * - Persistir snapshot JSON + evidências individuais por tipo
 * - Listar consultas com filtros para a tela index
 *
 * v2 mudanças:
 * - Gate score agora é calculado aqui (calcularGateScore) em vez de pela IA
 * - Threshold Serasa subiu de 30 para 50
 * - Bypass de Serasa para clientes com histórico forte (>= 12 meses, sem atrasos)
 * ============================================================================
 */

namespace App\Services;

use App\Models\Cliente;
use App\Models\ContaReceber;
use App\Models\Lead;
use App\Models\Movimento;
use App\Models\Processo;
use App\Models\SiricConsulta;
use App\Models\SiricEvidencia;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\WaConversation;
use App\Models\WaMessage;

class SiricService
{
    /**
     * Coleta todos os dados internos do BD para o CPF/CNPJ + telefone + email informados,
     * gera o snapshot JSON com métricas calculadas, e persiste nas evidências.
     */
    public function coletarDadosInternos(SiricConsulta $consulta): array
    {
        $docLimpo = preg_replace('/\D/', '', $consulta->cpf_cnpj);

        // Formatar CPF/CNPJ com máscara (BD armazena COM máscara)
        $docFormatado = $consulta->cpf_cnpj;
        if (strlen($docLimpo) === 11) {
            $docFormatado = substr($docLimpo,0,3).'.'.substr($docLimpo,3,3).'.'.substr($docLimpo,6,3).'-'.substr($docLimpo,9,2);
        } elseif (strlen($docLimpo) === 14) {
            $docFormatado = substr($docLimpo,0,2).'.'.substr($docLimpo,2,3).'.'.substr($docLimpo,5,3).'/'.substr($docLimpo,8,4).'-'.substr($docLimpo,12,2);
        }

        // ── 1. Localizar cliente(s) pelo documento (com E sem máscara) ──
        $clientes = Cliente::where(function($q) use ($docLimpo, $docFormatado) {
            $q->where('cpf_cnpj', $docFormatado)
              ->orWhere('cpf_cnpj', $docLimpo)
              ->orWhere('cpf_cnpj', 'LIKE', "%{$docLimpo}%");
        })->get();
        $clienteIds = $clientes->pluck('id')->toArray();
        $datajuriIds = $clientes->pluck('datajuri_id')->filter()->toArray();

        // Se não encontrou por documento, tenta por telefone ou email
        if (empty($clienteIds)) {
            if ($consulta->telefone) {
                $tel = preg_replace('/\D/', '', $consulta->telefone);
                $clientes = Cliente::where('telefone', 'LIKE', "%{$tel}%")->get();
                $clienteIds = $clientes->pluck('id')->toArray();
                $datajuriIds = $clientes->pluck('datajuri_id')->filter()->toArray();
            }
            if (empty($clienteIds) && $consulta->email) {
                $clientes = Cliente::where('email', $consulta->email)->get();
                $clienteIds = $clientes->pluck('id')->toArray();
                $datajuriIds = $clientes->pluck('datajuri_id')->filter()->toArray();
            }
        }

        // Vincular primeiro cliente encontrado à consulta
        if (!empty($clienteIds) && !$consulta->cliente_id) {
            
        // ── Conversas WhatsApp (NEXO) ──
        $clienteIdPrincipal = !empty($clienteIds) ? $clienteIds[0] : null;
        $conversasWa = $this->coletarConversasWhatsApp($consulta, $clienteIdPrincipal);

$consulta->update(['cliente_id' => $clienteIds[0]]);
        }

        // ── 2. Contas a Receber (por nome - não tem FK por ID) ──
        $contasReceber = $this->coletarContasReceber($consulta->nome);
        $metricasContas = $this->calcularMetricasContas($contasReceber);

        // ── 3. Movimentos financeiros (por pessoa_id_datajuri) ──
        $movimentos = $this->coletarMovimentos($datajuriIds, $consulta->nome);
        $metricasMovimentos = $this->calcularMetricasMovimentos($movimentos);

        // ── 4. Processos (por cliente_datajuri_id) ──
        $processos = $this->coletarProcessos($datajuriIds, $consulta->nome);

        // ── 5. Leads (por email/telefone) ──
        $leads = $this->coletarLeads($consulta->email, $consulta->telefone);

        // ── 6. Montar snapshot ──
        $snapshot = [
            'coletado_em'         => now()->toIso8601String(),
            'clientes_encontrados' => $clientes->count(),
            'cliente_ids'          => $clienteIds,
            'datajuri_ids'         => $datajuriIds,

            'contas_receber' => [
                'total_registros'    => $contasReceber->count(),
                'total_pago'         => $metricasContas['total_pago'],
                'saldo_aberto'       => $metricasContas['saldo_aberto'],
                'qtd_atrasos'        => $metricasContas['qtd_atrasos'],
                'max_dias_atraso'    => $metricasContas['max_dias_atraso'],
                'media_dias_atraso'  => $metricasContas['media_dias_atraso'],
                'ticket_medio_pago'  => $metricasContas['ticket_medio_pago'],
            ],

            'movimentos' => [
                'total_registros'     => $movimentos->count(),
                'total_receita'       => $metricasMovimentos['total_receita'],
                'total_despesa'       => $metricasMovimentos['total_despesa'],
                'recorrencia_meses'   => $metricasMovimentos['recorrencia_meses'],
            ],

            'processos' => [
                'total_ativos'    => $processos->where('status', 'Ativo')->count(),
                'total_inativos'  => $processos->where('status', '!=', 'Ativo')->count(),
                'lista' => $processos->map(fn($p) => [
                    'id'     => $p->id,
                    'numero' => $p->numero,
                    'status' => $p->status,
                    'titulo' => $p->titulo ?? $p->descricao ?? null,
                ])->toArray(),
            ],

            'leads' => [
                'total'  => $leads->count(),
                'lista'  => $leads->map(fn($l) => [
                    'id'     => $l->id,
                    'nome'   => $l->nome,
                    'status' => $l->status,
                    'origem' => $l->origem,
                ])->toArray(),
            ],

            'relacao_escritorio' => $this->avaliarRelacao($clientes, $metricasContas, $processos),
        ];

        // ── 7. Persistir snapshot na consulta ──
        $consulta->update([
            'snapshot_interno' => $snapshot,
            'status'           => 'coletado',
        ]);

        // ── 8. Salvar evidências individuais ──
        $this->salvarEvidencias($consulta, $contasReceber, $movimentos, $processos, $leads, $metricasContas);

        return $snapshot;
    }

    // ========================================================================
    // COLETA DE DADOS
    // ========================================================================

    /**
     * Busca contas a receber pelo cliente_id OU pelo campo 'cliente' (nome).
     * A tabela contas_receber tem: id, datajuri_id, cliente (varchar nome),
     * valor, data_vencimento, data_pagamento, status.
     */
    private function coletarContasReceber(string $nome): \Illuminate\Support\Collection
    {
        // contas_receber não tem FK por ID, apenas campo texto 'cliente'
        return ContaReceber::where('cliente', 'LIKE', "%{$nome}%")->get();
    }

    private function coletarMovimentos(array $datajuriIds, string $nome): \Illuminate\Support\Collection
    {
        // movimentos usa pessoa_id_datajuri (FK para datajuri_id do cliente)
        // Fallback por campo pessoa (nome texto) caso datajuri_id vazio
        $query = Movimento::query();

        if (!empty($datajuriIds)) {
            $query->where(function($q) use ($datajuriIds, $nome) {
                $q->whereIn('pessoa_id_datajuri', $datajuriIds)
                  ->orWhere('pessoa', 'LIKE', "%{$nome}%");
            });
        } else {
            $query->where('pessoa', 'LIKE', "%{$nome}%");
        }

        return $query->get();
    }

    private function coletarProcessos(array $datajuriIds, string $nome): \Illuminate\Support\Collection
    {
        // processos usa cliente_datajuri_id (FK para datajuri_id do cliente)
        // Fallback por campo cliente_nome (texto) caso datajuri_id vazio
        $query = Processo::query();

        if (!empty($datajuriIds)) {
            $query->where(function($q) use ($datajuriIds, $nome) {
                $q->whereIn('cliente_datajuri_id', $datajuriIds)
                  ->orWhere('cliente_nome', 'LIKE', "%{$nome}%");
            });
        } else {
            $query->where('cliente_nome', 'LIKE', "%{$nome}%");
        }

        return $query->get();
    }

    private function coletarLeads(?string $email, ?string $telefone): \Illuminate\Support\Collection
    {
        if (!$email && !$telefone) {
            return collect();
        }

        return Lead::where(function ($q) use ($email, $telefone) {
            if ($email) {
                $q->orWhere('email', $email);
            }
            if ($telefone) {
                $telLimpo = preg_replace('/\D/', '', $telefone);
                $q->orWhere('telefone', 'LIKE', "%{$telLimpo}%");
            }
        })->get();
    }

    // ========================================================================
    // CÁLCULO DE MÉTRICAS
    // ========================================================================

    private function calcularMetricasContas(\Illuminate\Support\Collection $contas): array
    {
        $hoje = Carbon::today();

        // Pagas = status 'Concluído' OU data_pagamento preenchida
        $pagas = $contas->filter(fn($c) => $c->status === 'Concluído' || $c->data_pagamento !== null);
        $totalPago = $pagas->sum('valor');
        $ticketMedio = $pagas->count() > 0 ? round($totalPago / $pagas->count(), 2) : 0;

        // Abertas = status 'Não lançado' E data_pagamento nula
        $abertas = $contas->filter(fn($c) => $c->status !== 'Concluído' && $c->status !== 'Excluido' && $c->data_pagamento === null);
        $saldoAberto = $abertas->sum('valor');

        // Atrasadas = abertas com data_vencimento < hoje
        $atrasadas = $abertas->filter(fn($c) => $c->data_vencimento && Carbon::parse($c->data_vencimento)->lt($hoje));
        $qtdAtrasos = $atrasadas->count();

        $diasAtraso = $atrasadas->map(fn($c) => $hoje->diffInDays(Carbon::parse($c->data_vencimento)));
        $maxDiasAtraso = $diasAtraso->max() ?? 0;
        $mediaDiasAtraso = $diasAtraso->count() > 0 ? round($diasAtraso->avg(), 1) : 0;

        return [
            'total_pago'        => round($totalPago, 2),
            'saldo_aberto'      => round($saldoAberto, 2),
            'qtd_atrasos'       => $qtdAtrasos,
            'max_dias_atraso'   => $maxDiasAtraso,
            'media_dias_atraso' => $mediaDiasAtraso,
            'ticket_medio_pago' => $ticketMedio,
        ];
    }

    private function calcularMetricasMovimentos(\Illuminate\Support\Collection $movimentos): array
    {
        // classificacao: RECEITA_PF, RECEITA_PJ = receita; DESPESA = despesa
        $receita = $movimentos->filter(fn($m) => in_array($m->classificacao, ['RECEITA_PF', 'RECEITA_PJ']))
                              ->sum(fn($m) => abs($m->valor));
        $despesa = $movimentos->filter(fn($m) => $m->classificacao === 'DESPESA')
                              ->sum(fn($m) => abs($m->valor));

        // Recorrência = quantos meses distintos teve movimentação
        $mesesDistintos = $movimentos->map(fn($m) => Carbon::parse($m->data)->format('Y-m'))->unique()->count();

        return [
            'total_receita'     => round($receita, 2),
            'total_despesa'     => round($despesa, 2),
            'recorrencia_meses' => $mesesDistintos,
        ];
    }

    // ========================================================================
    // AVALIAÇÃO QUALITATIVA DA RELAÇÃO COM O ESCRITÓRIO
    // ========================================================================

    private function avaliarRelacao($clientes, array $metricasContas, $processos): array
    {
        $clienteExiste = $clientes->count() > 0;
        $temProcessoAtivo = $processos->where('status', 'Ativo')->count() > 0;
        $temHistoricoPagamento = $metricasContas['total_pago'] > 0;
        $temAtrasoGrave = $metricasContas['max_dias_atraso'] > 90;

        $nivel = 'desconhecido';
        if ($clienteExiste && $temProcessoAtivo && $temHistoricoPagamento && !$temAtrasoGrave) {
            $nivel = 'forte';
        } elseif ($clienteExiste && $temHistoricoPagamento) {
            $nivel = 'moderado';
        } elseif ($clienteExiste) {
            $nivel = 'fraco';
        }

        return [
            'cliente_cadastrado'     => $clienteExiste,
            'processo_ativo'         => $temProcessoAtivo,
            'historico_pagamento'    => $temHistoricoPagamento,
            'atraso_grave'           => $temAtrasoGrave,
            'nivel'                  => $nivel,
        ];
    }

    // ========================================================================
    // PERSISTIR EVIDÊNCIAS
    // ========================================================================

    private function salvarEvidencias(
        SiricConsulta $consulta,
        $contasReceber, $movimentos, $processos, $leads,
        array $metricasContas
    ): void {
        // Limpar evidências internas anteriores (para permitir "recoletar")
        $consulta->evidencias()->where('fonte', 'interno')->delete();

        // Evidência: Contas a Receber
        if ($contasReceber->count() > 0) {
            SiricEvidencia::create([
                'consulta_id' => $consulta->id,
                'fonte'       => 'interno',
                'tipo'        => 'contas_receber',
                'payload'     => $metricasContas,
                'impacto'     => $metricasContas['qtd_atrasos'] > 3 ? 'negativo' : ($metricasContas['total_pago'] > 0 ? 'positivo' : 'neutro'),
                'resumo'      => sprintf(
                    '%d registros. Pago: R$ %s. Aberto: R$ %s. Atrasos: %d (máx %d dias).',
                    $contasReceber->count(),
                    number_format($metricasContas['total_pago'], 2, ',', '.'),
                    number_format($metricasContas['saldo_aberto'], 2, ',', '.'),
                    $metricasContas['qtd_atrasos'],
                    $metricasContas['max_dias_atraso']
                ),
            ]);
        }

        // Evidência: Processos
        if ($processos->count() > 0) {
            SiricEvidencia::create([
                'consulta_id' => $consulta->id,
                'fonte'       => 'interno',
                'tipo'        => 'processos',
                'payload'     => [
                    'ativos'   => $processos->where('status', 'Ativo')->count(),
                    'inativos' => $processos->where('status', '!=', 'Ativo')->count(),
                ],
                'impacto'     => $processos->where('status', 'Ativo')->count() > 0 ? 'positivo' : 'neutro',
                'resumo'      => sprintf(
                    '%d processo(s) ativo(s), %d inativo(s).',
                    $processos->where('status', 'Ativo')->count(),
                    $processos->where('status', '!=', 'Ativo')->count()
                ),
            ]);
        }

        // Evidência: Movimentos
        if ($movimentos->count() > 0) {
            SiricEvidencia::create([
                'consulta_id' => $consulta->id,
                'fonte'       => 'interno',
                'tipo'        => 'movimentos',
                'payload'     => [
                    'total_registros'   => $movimentos->count(),
                    'recorrencia_meses' => $movimentos->map(fn($m) => Carbon::parse($m->data)->format('Y-m'))->unique()->count(),
                ],
                'impacto'     => 'neutro',
                'resumo'      => sprintf('%d movimentos financeiros encontrados.', $movimentos->count()),
            ]);
        }

        // Evidência: Leads
        if ($leads->count() > 0) {
            SiricEvidencia::create([
                'consulta_id' => $consulta->id,
                'fonte'       => 'interno',
                'tipo'        => 'leads',
                'payload'     => $leads->map(fn($l) => [
                    'nome'   => $l->nome,
                    'status' => $l->status,
                    'origem' => $l->origem,
                ])->toArray(),
                'impacto'     => 'neutro',
                'resumo'      => sprintf('%d lead(s) encontrado(s).', $leads->count()),
            ]);
        }
    }

    // ========================================================================
    // LISTAGEM COM FILTROS
    // ========================================================================


    /**
     * Coleta conversas WhatsApp (NEXO) vinculadas ao cliente.
     * Busca por linked_cliente_id ou por telefone normalizado.
     */
    public function coletarConversasWhatsApp(SiricConsulta $consulta, ?int $clienteId = null): array
    {
        $conversas = collect();

        // 1. Busca por linked_cliente_id
        if ($clienteId) {
            $conversas = WaConversation::where('linked_cliente_id', $clienteId)
                ->orderBy('last_message_at', 'desc')
                ->limit(10)
                ->get();
        }

        // 2. Fallback: busca por telefone do formulário
        if ($conversas->isEmpty() && $consulta->cpf_cnpj) {
            $docLimpo = preg_replace('/\D/', '', $consulta->cpf_cnpj);
            // Buscar cliente pelo documento para pegar telefone
            $cliente = \App\Models\Cliente::where('cpf_cnpj', 'LIKE', "%{$docLimpo}%")->first();
            if ($cliente && $cliente->telefone) {
                $telNorm = preg_replace('/\D/', '', $cliente->telefone);
                if (strlen($telNorm) >= 10) {
                    $conversas = WaConversation::where('phone', 'LIKE', "%{$telNorm}%")
                        ->orderBy('last_message_at', 'desc')
                        ->limit(10)
                        ->get();
                }
            }
        }

        if ($conversas->isEmpty()) {
            return [
                'encontrado' => false,
                'total_conversas' => 0,
                'total_mensagens' => 0,
                'mensagens' => [],
                'resumo' => 'Nenhuma conversa WhatsApp encontrada para este cliente.',
            ];
        }

        // Coletar mensagens das conversas (últimas 50 por conversa, máx 150 total)
        $todasMensagens = [];
        $totalMsgs = 0;

        foreach ($conversas as $conv) {
            $msgs = WaMessage::where('conversation_id', $conv->id)
                ->orderBy('sent_at', 'desc')
                ->limit(50)
                ->get()
                ->reverse()
                ->values();

            foreach ($msgs as $msg) {
                if ($totalMsgs >= 150) break;
                $todasMensagens[] = [
                    'data' => $msg->sent_at ? $msg->sent_at->format('d/m/Y H:i') : '—',
                    'direcao' => $msg->direction == 1 ? 'cliente' : 'escritorio',
                    'humano' => (bool) $msg->is_human,
                    'tipo' => $msg->message_type,
                    'texto' => mb_substr($msg->body ?? '', 0, 500),
                ];
                $totalMsgs++;
            }
        }

        // Gerar resumo textual
        $primeiraMsg = $conversas->min('created_at');
        $ultimaMsg = $conversas->max('last_message_at');

        return [
            'encontrado' => true,
            'total_conversas' => $conversas->count(),
            'total_mensagens' => $totalMsgs,
            'periodo' => ($primeiraMsg ? $primeiraMsg->format('d/m/Y') : '?') . ' a ' . ($ultimaMsg ? $ultimaMsg->format('d/m/Y') : '?'),
            'mensagens' => $todasMensagens,
            'resumo' => sprintf(
                '%d conversa(s) encontrada(s), %d mensagens no período de %s a %s.',
                $conversas->count(),
                $totalMsgs,
                $primeiraMsg ? $primeiraMsg->format('d/m/Y') : '?',
                $ultimaMsg ? $ultimaMsg->format('d/m/Y') : '?'
            ),
        ];
    }

    // ========================================================================
    // GATE SCORE DETERMINÍSTICO (v2)
    // ========================================================================

    /**
     * Calcula o gate_score de forma determinística no PHP.
     * Retorna array com score, breakdown, decisão sobre Serasa e justificativas.
     *
     * Regras de pontuação (quanto maior = mais risco):
     * - Histórico: +35 sem cliente no BD, +40 inadimplência, +25 atraso>=15d, +25 acordo quebrado
     * - Bom histórico: -30 se >= 12 meses, >= 6 parcelas sem atraso, saldo_vencido = 0
     * - Renda: +25 ausente, +15 parcela/renda > 20%, +30 se > 30%, +25 se folga negativa
     * - Completude: +15 se faltam >= 2 campos-chave, +30 se >= 4
     * - Consistência: +25 se telefone/email divergem do BD
     * - Exposição: -10 se < R$400, +15 se >= R$800, +25 se >= R$2.000
     *
     * Decisão (v2 — threshold 50, com bypass):
     * - Bypass Serasa se histórico forte (>= 12 meses, >= 6 parcelas pagas, 0 atrasos)
     * - gate_score >= 50 → need_serasa = true (antes era 30)
     * - 25 <= gate_score < 50 → need_web_intel = true
     * - gate_score < 25 → nenhuma consulta externa
     */
    public function calcularGateScore(SiricConsulta $consulta, array $snapshot): array
    {
        $breakdown = [
            'historico' => 0,
            'bom_historico_desconto' => 0,
            'renda' => 0,
            'completude' => 0,
            'consistencia' => 0,
            'exposicao' => 0,
        ];
        $riscos = [];
        $dadosFaltantes = [];

        $contas = $snapshot['contas_receber'] ?? [];
        $relacao = $snapshot['relacao_escritorio'] ?? [];
        $movimentos = $snapshot['movimentos'] ?? [];

        $valorPretendido = (float) ($consulta->valor_total ?? 0);
        $numParcelas = max(1, (int) ($consulta->parcelas_desejadas ?? 1));
        $parcelaEstimada = round($valorPretendido / $numParcelas, 2);
        $renda = $consulta->renda_declarada ? (float) $consulta->renda_declarada : null;

        // ── HISTÓRICO ──
        $clienteEncontrado = ($snapshot['clientes_encontrados'] ?? 0) > 0;
        if (!$clienteEncontrado) {
            $breakdown['historico'] += 35;
            $riscos[] = 'Cliente não encontrado no banco de dados interno';
        }

        $saldoVencido = (float) ($contas['saldo_aberto'] ?? 0); // contas abertas = potencial vencido
        $qtdAtrasos = (int) ($contas['qtd_atrasos'] ?? 0);
        $maxDiasAtraso = (int) ($contas['max_dias_atraso'] ?? 0);

        if ($qtdAtrasos > 0 && $saldoVencido > 0) {
            $breakdown['historico'] += 40;
            $riscos[] = "Inadimplência ativa: {$qtdAtrasos} atraso(s), saldo R$ " . number_format($saldoVencido, 2, ',', '.');
        }

        if ($maxDiasAtraso >= 15) {
            $breakdown['historico'] += 25;
            $riscos[] = "Atraso máximo de {$maxDiasAtraso} dias (>= 15)";
        }

        // ── BOM HISTÓRICO (desconto) ──
        $recorrenciaMeses = (int) ($movimentos['recorrencia_meses'] ?? 0);
        $totalPago = (float) ($contas['total_pago'] ?? 0);
        $ticketMedio = (float) ($contas['ticket_medio_pago'] ?? 0);
        $totalRegistros = (int) ($contas['total_registros'] ?? 0);

        $temBomHistorico = $recorrenciaMeses >= 12
            && $totalRegistros >= 6
            && $qtdAtrasos === 0
            && $saldoVencido <= 0;

        if ($temBomHistorico) {
            $breakdown['bom_historico_desconto'] = -30;
        }

        // ── RENDA ──
        if ($renda === null || $renda <= 0) {
            $breakdown['renda'] += 25;
            $dadosFaltantes[] = 'renda_declarada';
            $riscos[] = 'Renda não declarada';
        } else {
            $comprometimento = $parcelaEstimada / $renda;
            if ($comprometimento > 0.30) {
                $breakdown['renda'] += 30;
                $riscos[] = sprintf('Comprometimento de renda %.1f%% (> 30%%)', $comprometimento * 100);
            } elseif ($comprometimento > 0.20) {
                $breakdown['renda'] += 15;
                $riscos[] = sprintf('Comprometimento de renda %.1f%% (> 20%%)', $comprometimento * 100);
            }

            $despesas = (float) ($consulta->despesas_mensais ?? 0);
            if ($despesas > 0 && ($renda - $despesas) < $parcelaEstimada) {
                $breakdown['renda'] += 25;
                $riscos[] = 'Folga de caixa insuficiente (renda - despesas < parcela)';
            }
        }

        // ── COMPLETUDE DE DADOS ──
        $camposChave = [
            'telefone' => $consulta->telefone,
            'email' => $consulta->email,
            'endereco_cep' => $consulta->endereco_cep,
            'profissao' => $consulta->profissao,
            'empresa_empregador' => $consulta->empresa_empregador,
            'tempo_emprego' => $consulta->tempo_emprego,
        ];

        $faltantes = 0;
        foreach ($camposChave as $campo => $valor) {
            if (empty($valor)) {
                $faltantes++;
                $dadosFaltantes[] = $campo;
            }
        }

        if ($faltantes >= 4) {
            $breakdown['completude'] += 30;
            $riscos[] = "{$faltantes} campos-chave ausentes (>= 4)";
        } elseif ($faltantes >= 2) {
            $breakdown['completude'] += 15;
            $riscos[] = "{$faltantes} campos-chave ausentes (>= 2)";
        }

        // ── CONSISTÊNCIA ──
        if ($clienteEncontrado) {
            $clienteInfo = $snapshot['cliente'] ?? [];
            // Verificar divergência de telefone
            if ($consulta->telefone && !empty($clienteInfo['telefone'])) {
                $telForm = preg_replace('/\D/', '', $consulta->telefone);
                $telBD = preg_replace('/\D/', '', $clienteInfo['telefone']);
                if ($telForm && $telBD && !str_contains($telBD, $telForm) && !str_contains($telForm, $telBD)) {
                    $breakdown['consistencia'] += 25;
                    $riscos[] = 'Telefone do formulário diverge do cadastrado no BD';
                }
            }
            // Verificar divergência de email
            if ($consulta->email && !empty($clienteInfo['email'])) {
                if (strtolower(trim($consulta->email)) !== strtolower(trim($clienteInfo['email']))) {
                    $breakdown['consistencia'] += 25;
                    $riscos[] = 'Email do formulário diverge do cadastrado no BD';
                }
            }
        }

        // ── EXPOSIÇÃO ──
        if ($valorPretendido >= 2000) {
            $breakdown['exposicao'] += 25;
        } elseif ($valorPretendido >= 800) {
            $breakdown['exposicao'] += 15;
        } elseif ($valorPretendido < 400) {
            $breakdown['exposicao'] -= 10;
        }

        // ── CÁLCULO FINAL ──
        $gateScore = max(0, array_sum($breakdown));

        // ── DECISÃO SERASA (v2) ──
        $needSerasa = false;
        $needWebIntel = false;
        $serasaBypass = false;
        $cpfValido = in_array(strlen(preg_replace('/\D/', '', $consulta->cpf_cnpj)), [11, 14]);
        $autorizou = (bool) $consulta->autorizou_consultas_externas;

        if (!$autorizou || !$cpfValido) {
            // Sem autorização ou CPF inválido
            $needSerasa = false;
        } elseif ($temBomHistorico && $gateScore < 70) {
            // BYPASS v2: cliente com histórico forte não precisa de Serasa (a menos que score muito alto)
            $serasaBypass = true;
            $needSerasa = false;
        } elseif ($gateScore >= 50) {
            $needSerasa = true;
        } elseif ($gateScore >= 25) {
            $needWebIntel = true;
        }

        $justificativa = $this->gerarJustificativaGate($gateScore, $needSerasa, $needWebIntel, $serasaBypass, $temBomHistorico);

        return [
            'gate_score' => $gateScore,
            'gate_score_breakdown' => $breakdown,
            'need_serasa' => $needSerasa,
            'need_web_intel' => $needWebIntel,
            'serasa_bypass' => $serasaBypass,
            'bom_historico' => $temBomHistorico,
            'justificativa' => $justificativa,
            'riscos_preliminares' => $riscos,
            'dados_faltantes' => $dadosFaltantes,
            'parcela_estimada' => $parcelaEstimada,
            'comprometimento_pct' => $renda && $renda > 0 ? round(($parcelaEstimada / $renda) * 100, 1) : null,
        ];
    }

    private function gerarJustificativaGate(int $score, bool $serasa, bool $webIntel, bool $bypass, bool $bomHist): string
    {
        if ($bypass) {
            return "Gate score {$score}. Serasa dispensado: cliente com histórico forte no escritório (>= 12 meses, sem atrasos).";
        }
        if ($serasa) {
            return "Gate score {$score} (>= 50). Consulta Serasa recomendada para mitigar riscos identificados.";
        }
        if ($webIntel) {
            return "Gate score {$score} (25-49). Web intelligence recomendada antes de Serasa.";
        }
        return "Gate score {$score} (< 25). Risco baixo, nenhuma consulta externa necessária.";
    }

    public function listar(array $filtros = [], int $perPage = 15)
    {
        $query = SiricConsulta::with('user')
            ->orderBy('created_at', 'desc');

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }
        if (!empty($filtros['rating'])) {
            $query->where('rating', $filtros['rating']);
        }
        if (!empty($filtros['busca'])) {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'LIKE', "%{$busca}%")
                  ->orWhere('cpf_cnpj', 'LIKE', "%{$busca}%");
            });
        }

        return $query->paginate($perPage);
    }
}
