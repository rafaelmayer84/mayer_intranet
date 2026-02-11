<?php

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
