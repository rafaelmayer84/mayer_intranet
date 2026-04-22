<?php
/**
 * ESTAVEL desde 13/03/2026
 *
 * DOCUMENTACAO FUNCIONAL COMPLETA — GdpPenalizacaoScanner
 *
 * O QUE FAZ:
 * Scanner automatizado de conformidade que verifica o comportamento dos advogados
 * contra 26 regras de negocio (penalidades) distribuidas em 5 eixos:
 * Juridico (J01-J05), Financeiro (F01-F03), Atendimento (A01-A08),
 * Desenvolvimento (D01-D04), SIATE (S01) e Vigilia (V01-V03).
 * J06 e F04 foram desativadas por falta de dados operacionais.
 *
 * LOGICA DE NEGOCIO:
 * 1. Construtor recebe ciclo_id, mes e ano. Carrega tipos ativos com thresholds
 *    e pontos efetivos (suporta override por ciclo via gdp_penalizacao_configs).
 * 2. scanUsuario() executa todos os scan methods sequencialmente para um user.
 * 3. Cada scan method: consulta fonte de verdade, verifica threshold, registra
 *    penalidade se violacao detectada. Duplicatas evitadas por chave composta
 *    (ciclo_id + user_id + tipo_id + mes + ano + referencia_tipo + referencia_id).
 * 4. Penalidades gravadas em gdp_penalizacoes com pontos_desconto.
 * 5. SystemEvent::gdp() registra log de ocorrencia para cada penalidade criada.
 *
 * DE ONDE PUXA DADOS:
 * - processos (status, proprietario_id, data_encerramento, tipo_encerramento)
 * - atividades_datajuri (data_prazo_fatal, data_conclusao, proprietario_id)
 * - andamentos_fase (descricao, data_andamento, proprietario_id)
 * - contas_receber + processos (inadimplencia)
 * - wa_conversations + wa_messages (WhatsApp)
 * - nexo_tickets + nexo_ticket_notas (tickets atendimento)
 * - leads + crm_accounts + crm_activities (CRM)
 * - crm_opportunities (pipeline CRM)
 * - horas_trabalhadas_datajuri (registro de horas)
 * - avisos + avisos_lidos (comunicacao interna)
 * - gdp_snapshots + gdp_audit_log (acordo GDP)
 * - vigilia_cruzamentos (conclusoes suspeitas, sem_acao)
 * - crm_service_requests (SIATE SLA)
 * - users.datajuri_proprietario_id (mapeamento user->proprietario DataJuri)
 *
 * ONDE GRAVA: gdp_penalizacoes, system_events
 *
 * CRON: gdp:penalizacoes — diario 08:00 BRT (routes/console.php linha 124)
 *
 * DEPENDENCIAS: GdpPenalizacao, GdpPenalizacaoTipo, SystemEvent, Carbon
 *
 * FLUXO DE CONTESTACAO:
 * 1. Advogado ve penalidade em /gdp/penalizacoes ou /gdp/minha-performance
 * 2. Clica "Contestar" -> POST /gdp/penalizacoes/{id}/contestar com texto
 * 3. Status muda para contestada=true, contestacao_status='pendente'
 * 4. Admin ve pendentes em /gdp/penalizacoes (filtro contestacao=pendente)
 * 5. Admin julga: aceita (remove pontos) ou rejeita (mantem pontos)
 * 6. Scope efetivas() exclui contestacoes aceitas do calculo de desconto
 *
 * IMPACTO NO SCORE:
 * GdpApuracaoService chama GdpPenalizacao::totalDescontoEixo() que soma
 * pontos_desconto das penalidades efetivas por eixo/mes, com cap de 30pts/eixo.
 * Score final do eixo = score positivo - desconto (minimo 0).
 *
 * ALERTA: alteracoes exigem estudo previo. Modulo ESTAVEL.
 */

namespace App\Services\Gdp;

use Illuminate\Support\Facades\DB;
use App\Models\GdpPenalizacao;
use App\Models\GdpPenalizacaoTipo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\SystemEvent;

class GdpPenalizacaoScanner
{
    private int $cicloId;
    private int $mes;
    private int $ano;
    private array $tiposCache = [];
    private string $logPrefix = '[GDP-Scanner]';

    public function __construct(int $cicloId, int $mes, int $ano)
    {
        $this->cicloId = $cicloId;
        $this->mes = $mes;
        $this->ano = $ano;

        $tipos = GdpPenalizacaoTipo::ativos()->get();
        foreach ($tipos as $tipo) {
            if ($tipo->isAtivoNoCiclo($cicloId)) {
                $this->tiposCache[$tipo->codigo] = [
                    'model'     => $tipo,
                    'threshold' => $tipo->getThresholdEfetivo($cicloId),
                    'pontos'    => $tipo->getPontosEfetivo($cicloId),
                ];
            }
        }
    }

    /**
     * Executa todos os 22 scanners para um usuario
     */
    public function scanUsuario(int $userId): array
    {
        $total = 0;
        $detalhes = [];

        $methods = [
            'scanJ01','scanJ02','scanJ03','scanJ04','scanJ05','scanJ06',
            'scanF01','scanF02','scanF03','scanF04',
            'scanA01','scanA02','scanA03','scanA04','scanA05','scanA06','scanA07','scanA08',
            'scanD01','scanD02','scanD03','scanD04',
            'scanS01',
            'scanV01','scanV02','scanV03',
            'scanC01',
        ];

        foreach ($methods as $method) {
            try {
                $count = $this->{$method}($userId);
                $total += $count;
                if ($count > 0) {
                    $detalhes[$method] = $count;
                }
            } catch (\Exception $e) {
                Log::warning("{$this->logPrefix} {$method} falhou user {$userId}: " . $e->getMessage());
            }
        }

        return ['total_penalizacoes' => $total, 'detalhes' => $detalhes];
    }

    // ================================================================
    // EIXO JURIDICO
    // ================================================================

    /**
     * PEN-J01: Processo ativo sem movimentacao ha X dias
     * Fonte: processos (proprietario_id) + andamentos_fase (processo_pasta)
     */
    private function scanJ01(int $userId): int
    {
        $cfg = $this->getTipo('PEN-J01');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subDays($cfg['threshold'])->toDateString();

        $processos = DB::table('processos as p')
            ->where('p.proprietario_id', $userId)
            ->where('p.status', 'Ativo')
            ->whereNotExists(function ($q) use ($corte) {
                $q->select(DB::raw(1))
                  ->from('andamentos_fase as af')
                  ->whereColumn('af.processo_pasta', 'p.pasta')
                  ->where('af.data_andamento', '>=', $corte);
            })
            ->select('p.id', 'p.pasta')
            ->get();

        $count = 0;
        foreach ($processos as $proc) {
            $count += $this->registrar('PEN-J01', $userId, $cfg,
                "Processo pasta {$proc->pasta} sem movimentacao ha mais de {$cfg['threshold']} dias.",
                'processo', $proc->id);
        }
        return $count;
    }

    /**
     * PEN-J02: Prazo judicial descumprido
     * Fonte: atividades_datajuri (data_prazo_fatal ultrapassada sem data_conclusao)
     */
    private function scanJ02(int $userId): int
    {
        $cfg = $this->getTipo('PEN-J02');
        if (!$cfg) return 0;

        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->toDateString();
        $hoje      = Carbon::now()->toDateString();

        $atividades = DB::table('atividades_datajuri')
            ->where('proprietario_id', $userId)
            ->whereNotNull('data_prazo_fatal')
            ->whereBetween('data_prazo_fatal', [$inicioMes, $fimMes])
            ->where('data_prazo_fatal', '<', $hoje)
            ->whereNull('data_conclusao')
            ->select('id', 'processo_pasta', 'data_prazo_fatal')
            ->get();

        $count = 0;
        foreach ($atividades as $at) {
            $count += $this->registrar('PEN-J02', $userId, $cfg,
                "Prazo fatal {$at->data_prazo_fatal} descumprido - pasta {$at->processo_pasta}.",
                'atividade', $at->id);
        }
        return $count;
    }

    /**
     * PEN-J03: Processo ativo sem tarefa futura agendada
     * Fonte: processos + atividades_datajuri (data_vencimento ou data_prazo_fatal futuro)
     */
    private function scanJ03(int $userId): int
    {
        $cfg = $this->getTipo('PEN-J03');
        if (!$cfg) return 0;

        $hoje = Carbon::now()->toDateString();

        $processos = DB::table('processos as p')
            ->where('p.proprietario_id', $userId)
            ->where('p.status', 'Ativo')
            ->whereNotExists(function ($q) use ($hoje) {
                $q->select(DB::raw(1))
                  ->from('atividades_datajuri as at')
                  ->whereColumn('at.processo_pasta', 'p.pasta')
                  ->whereNull('at.data_conclusao')
                  ->where(function ($qq) use ($hoje) {
                      $qq->where('at.data_vencimento', '>=', $hoje)
                         ->orWhere('at.data_prazo_fatal', '>=', $hoje);
                  });
            })
            ->select('p.id', 'p.pasta')
            ->get();

        $count = 0;
        foreach ($processos as $proc) {
            $count += $this->registrar('PEN-J03', $userId, $cfg,
                "Processo pasta {$proc->pasta} ativo sem tarefa futura agendada.",
                'processo', $proc->id);
        }
        return $count;
    }

    /**
     * PEN-J04: Prazo fatal em X dias sem preparacao
     * Fonte: atividades_datajuri (prazo iminente sem outra atividade concluida recente)
     */
    private function scanJ04(int $userId): int
    {
        $cfg = $this->getTipo('PEN-J04');
        if (!$cfg) return 0;

        $dias   = $cfg['threshold'];
        $hoje   = Carbon::now()->toDateString();
        $limite = Carbon::now()->addDays($dias)->toDateString();

        $atividades = DB::table('atividades_datajuri')
            ->where('proprietario_id', $userId)
            ->whereNotNull('data_prazo_fatal')
            ->whereBetween('data_prazo_fatal', [$hoje, $limite])
            ->whereNull('data_conclusao')
            ->select('id', 'processo_pasta', 'data_prazo_fatal')
            ->get();

        $count = 0;
        foreach ($atividades as $at) {
            $temPreparacao = DB::table('atividades_datajuri')
                ->where('proprietario_id', $userId)
                ->where('processo_pasta', $at->processo_pasta)
                ->where('id', '!=', $at->id)
                ->whereNotNull('data_conclusao')
                ->where('data_conclusao', '>=', Carbon::now()->subDays($dias)->toDateTimeString())
                ->exists();

            if (!$temPreparacao) {
                $count += $this->registrar('PEN-J04', $userId, $cfg,
                    "Prazo fatal {$at->data_prazo_fatal} pasta {$at->processo_pasta} sem preparacao nos ultimos {$dias} dias.",
                    'atividade', $at->id);
            }
        }
        return $count;
    }

    /**
     * PEN-J05: Publicacao/intimacao nao tratada em Xh
     * Fonte: andamentos_fase (tipo LIKE intimac/publicac/citac) + atividades_datajuri
     */
    private function scanJ05(int $userId): int
    {
        $cfg = $this->getTipo('PEN-J05');
        if (!$cfg) return 0;

        $horas     = $cfg['threshold'];
        $corte     = Carbon::now()->subHours($horas)->toDateTimeString();
        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->toDateString();

        $djPropId = DB::table('users')->where('id', $userId)->value('datajuri_proprietario_id');
        if (!$djPropId) return 0;

        $andamentos = DB::table('andamentos_fase')
            ->where('proprietario_id', $djPropId)
            ->whereBetween('data_andamento', [$inicioMes, $fimMes])
            ->where('data_andamento', '<=', Carbon::now()->subHours($horas)->toDateString())
            ->where(function ($q) {
                $q->where('descricao', 'LIKE', '%intimac%')
                  ->orWhere('descricao', 'LIKE', '%publicac%')
                  ->orWhere('descricao', 'LIKE', '%citac%');
            })
            ->select('datajuri_id', 'processo_pasta', 'data_andamento')
            ->get();

        $count = 0;
        foreach ($andamentos as $and) {
            $temTratativa = DB::table('atividades_datajuri')
                ->where('proprietario_id', $userId)
                ->where('processo_pasta', $and->processo_pasta)
                ->where('data_hora', '>', $and->data_andamento)
                ->exists();

            if (!$temTratativa) {
                $count += $this->registrar('PEN-J05', $userId, $cfg,
                    "Intimacao/publicacao {$and->data_andamento} pasta {$and->processo_pasta} sem tratativa em {$horas}h.",
                    'andamento', $and->datajuri_id);
            }
        }
        return $count;
    }

    /**
     * PEN-J06: OS aberta sem andamento ha X dias
     * Fonte: ordens_servico (advogado_id, data_conclusao NULL, data_ultimo_andamento)
     */
    private function scanJ06(int $userId): int
    {
        $cfg = $this->getTipo('PEN-J06');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subDays($cfg['threshold'])->toDateString();

        $ordens = DB::table('ordens_servico')
            ->where('advogado_id', $userId)
            ->whereNull('data_conclusao')
            ->where(function ($q) use ($corte) {
                $q->whereNull('data_ultimo_andamento')
                  ->orWhere('data_ultimo_andamento', '<', $corte);
            })
            ->select('id', 'numero', 'data_ultimo_andamento')
            ->get();

        $count = 0;
        foreach ($ordens as $os) {
            $count += $this->registrar('PEN-J06', $userId, $cfg,
                "OS {$os->numero} sem andamento ha mais de {$cfg['threshold']} dias.",
                'ordem_servico', $os->id);
        }
        return $count;
    }

    // ================================================================
    // EIXO FINANCEIRO
    // ================================================================

    /**
     * PEN-F01: Inadimplente >15d sem contato
     * Fonte: contas_receber + processos (proprietario_id) + wa_messages/atividades_datajuri
     */
    private function scanF01(int $userId): int
    {
        $cfg = $this->getTipo('PEN-F01');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subDays($cfg['threshold'])->toDateString();
        $hoje  = Carbon::now()->toDateString();

        $titulos = DB::table('contas_receber as cr')
            ->join('processos as p', 'p.datajuri_id', '=', 'cr.processo_datajuri_id')
            ->where('p.proprietario_id', $userId)
            ->whereNull('cr.data_pagamento')
            ->whereNotIn('cr.status', ['Concluído', 'Excluido'])
            ->where('cr.data_vencimento', '<', $corte)
            ->select('cr.id', 'cr.cliente', 'cr.data_vencimento', 'cr.valor', 'cr.cliente_datajuri_id')
            ->get();

        $count = 0;
        foreach ($titulos as $titulo) {
            if (!$titulo->cliente_datajuri_id) continue;

            $temContato = $this->verificarContatoCliente($userId, $titulo->cliente_datajuri_id, $titulo->data_vencimento);

            if (!$temContato) {
                $dias = Carbon::parse($titulo->data_vencimento)->diffInDays(Carbon::now());
                $count += $this->registrar('PEN-F01', $userId, $cfg,
                    "Cliente {$titulo->cliente} inadimplente ha {$dias} dias (R\$ {$titulo->valor}) sem contato registrado.",
                    'conta_receber', $titulo->id);
            }
        }
        return $count;
    }

    /**
     * PEN-F02: Inadimplente >30d sem acao formal
     * Fonte: contas_receber + processos + atividades_datajuri
     */
    private function scanF02(int $userId): int
    {
        $cfg = $this->getTipo('PEN-F02');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subDays($cfg['threshold'])->toDateString();

        $titulos = DB::table('contas_receber as cr')
            ->join('processos as p', 'p.datajuri_id', '=', 'cr.processo_datajuri_id')
            ->where('p.proprietario_id', $userId)
            ->whereNull('cr.data_pagamento')
            ->whereNotIn('cr.status', ['Concluído', 'Excluido'])
            ->where('cr.data_vencimento', '<', $corte)
            ->select('cr.id', 'cr.cliente', 'cr.data_vencimento', 'cr.valor', 'cr.cliente_datajuri_id')
            ->get();

        $count = 0;
        foreach ($titulos as $titulo) {
            if (!$titulo->cliente_datajuri_id) continue;

            $temAcao = DB::table('atividades_datajuri')
                ->where('proprietario_id', $userId)
                ->where('data_hora', '>=', $titulo->data_vencimento)
                ->whereExists(function ($q) use ($titulo) {
                    $q->select(DB::raw(1))
                      ->from('processos')
                      ->whereColumn('processos.pasta', 'atividades_datajuri.processo_pasta')
                      ->where('processos.cliente_datajuri_id', $titulo->cliente_datajuri_id);
                })
                ->exists();

            if (!$temAcao) {
                $dias = Carbon::parse($titulo->data_vencimento)->diffInDays(Carbon::now());
                $count += $this->registrar('PEN-F02', $userId, $cfg,
                    "Cliente {$titulo->cliente} inadimplente ha {$dias} dias (R\$ {$titulo->valor}) sem acao formal.",
                    'conta_receber', $titulo->id);
            }
        }
        return $count;
    }

    /**
     * PEN-F03: Inadimplencia recorrente (3+ titulos em 6 meses sem plano)
     * Fonte: contas_receber agrupado por cliente + crm_activities
     */
    private function scanF03(int $userId): int
    {
        $cfg = $this->getTipo('PEN-F03');
        if (!$cfg) return 0;

        $minTitulos     = $cfg['threshold'];
        $semestroAtras  = Carbon::now()->subMonths(6)->toDateString();
        $hoje           = Carbon::now()->toDateString();

        $clientesRecorrentes = DB::table('contas_receber as cr')
            ->join('processos as p', 'p.datajuri_id', '=', 'cr.processo_datajuri_id')
            ->where('p.proprietario_id', $userId)
            ->whereNull('cr.data_pagamento')
            ->whereNotIn('cr.status', ['Concluído', 'Excluido'])
            ->where('cr.data_vencimento', '>=', $semestroAtras)
            ->where('cr.data_vencimento', '<', $hoje)
            ->whereNotNull('cr.cliente_datajuri_id')
            ->groupBy('cr.cliente_datajuri_id', 'cr.cliente')
            ->havingRaw('COUNT(*) >= ?', [$minTitulos])
            ->select('cr.cliente_datajuri_id', 'cr.cliente', DB::raw('COUNT(*) as qtd'))
            ->get();

        $count = 0;
        foreach ($clientesRecorrentes as $cli) {
            // Verificar se ha atividade CRM registrada pelo advogado para esse cliente
            $temPlano = DB::table('crm_activities as ca')
                ->join('crm_accounts as acc', 'acc.id', '=', 'ca.account_id')
                ->where('ca.created_by_user_id', $userId)
                ->where('ca.created_at', '>=', $semestroAtras)
                ->whereExists(function ($q) use ($cli) {
                    $q->select(DB::raw(1))
                      ->from('clientes as c')
                      ->where('c.datajuri_id', $cli->cliente_datajuri_id)
                      ->whereRaw('CAST(c.id AS UNSIGNED) = crm_accounts.id');
                })
                ->exists();

            if (!$temPlano) {
                $count += $this->registrar('PEN-F03', $userId, $cfg,
                    "Cliente {$cli->cliente} com {$cli->qtd} titulos vencidos em 6 meses sem plano de acao.",
                    'cliente', $cli->cliente_datajuri_id);
            }
        }
        return $count;
    }

    /**
     * PEN-F04: Contrato/OS inconforme sem regularizacao ha X dias
     * Fonte: ordens_servico (campo situacao) vinculado ao advogado
     * NOTA: tabela contratos NAO tem campo situacao. ordens_servico tem.
     */
    private function scanF04(int $userId): int
    {
        $cfg = $this->getTipo('PEN-F04');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subDays($cfg['threshold'])->toDateString();

        $ordens = DB::table('ordens_servico')
            ->where('advogado_id', $userId)
            ->whereNull('data_conclusao')
            ->where(function ($q) {
                $q->where('situacao', 'LIKE', '%pendente%')
                  ->orWhere('situacao', 'LIKE', '%irregular%')
                  ->orWhere('situacao', 'LIKE', '%inconforme%')
                  ->orWhere('situacao', 'LIKE', '%atrasad%');
            })
            ->where(function ($q) use ($corte) {
                $q->whereNull('data_ultimo_andamento')
                  ->orWhere('data_ultimo_andamento', '<', $corte);
            })
            ->select('id', 'numero', 'situacao', 'advogado_nome')
            ->get();

        $count = 0;
        foreach ($ordens as $os) {
            $count += $this->registrar('PEN-F04', $userId, $cfg,
                "OS {$os->numero} situacao '{$os->situacao}' sem regularizacao ha mais de {$cfg['threshold']} dias.",
                'ordem_servico', $os->id);
        }
        return $count;
    }

    // ================================================================
    // EIXO ATENDIMENTO
    // ================================================================

    /**
     * PEN-A01: WhatsApp sem resposta >Xh
     * Fonte: wa_conversations (assigned_user_id) + wa_messages (direction: 0=out, 1=in)
     */
    private function scanA01(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A01');
        if (!$cfg) return 0;

        $horas     = $cfg['threshold'];
        $corte     = Carbon::now()->subHours($horas)->toDateTimeString();
        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateTimeString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        // Ultima incoming de cada conversa atribuida ao user, sem outgoing posterior
        $conversas = DB::table('wa_conversations as wc')
            ->where('wc.assigned_user_id', $userId)
            ->where('wc.status', 'open')
            ->whereNotNull('wc.last_incoming_at')
            ->where('wc.last_incoming_at', '<=', $corte)
            ->where('wc.last_incoming_at', '>=', $inicioMes)
            ->where('wc.last_incoming_at', '<=', $fimMes)
            ->where(function ($q) {
                // Sem resposta apos a ultima incoming
                $q->whereNull('wc.first_response_at')
                  ->orWhereColumn('wc.first_response_at', '<', 'wc.last_incoming_at');
            })
            ->select('wc.id', 'wc.name', 'wc.phone', 'wc.last_incoming_at')
            ->get();

        // Obter telefone do proprio usuario para excluir auto-conversas
        $userPhone = DB::table('users')->where('id', $userId)->value('telefone');
        $userPhoneSuffix = $userPhone ? substr(preg_replace('/\D/', '', $userPhone), -8) : null;

        $count = 0;
        foreach ($conversas as $conv) {
            // Excluir auto-conversas (usuario respondendo a si mesmo)
            if ($userPhoneSuffix && strlen($userPhoneSuffix) === 8) {
                $convPhoneClean = preg_replace('/\D/', '', $conv->phone ?? '');
                if (str_ends_with($convPhoneClean, $userPhoneSuffix)) continue;
            }

            // Ignorar incoming fora do horario comercial (8h-18h seg-sex)
            $incomingAt = Carbon::parse($conv->last_incoming_at);
            if ($incomingAt->isWeekend() || $incomingAt->hour < 8 || $incomingAt->hour >= 18) continue;

            // Verificar de forma mais granular se houve outgoing apos o last_incoming
            $respondeu = DB::table('wa_messages')
                ->where('conversation_id', $conv->id)
                ->where('direction', 2)
                ->where('sent_at', '>', $conv->last_incoming_at)
                ->exists();

            if (!$respondeu) {
                $count += $this->registrar('PEN-A01', $userId, $cfg,
                    "Conversa WhatsApp com {$conv->name} ({$conv->phone}) sem resposta ha mais de {$horas}h.",
                    'wa_conversation', $conv->id);
            }
        }
        return $count;
    }

    /**
     * PEN-A02: Ticket nao tratado em Xh
     * Fonte: nexo_tickets + nexo_ticket_notas
     */
    private function scanA02(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A02');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subHours($cfg['threshold'])->toDateTimeString();

        $tickets = DB::table('nexo_tickets as t')
            ->where('t.responsavel_id', $userId)
            ->whereIn('t.status', ['aberto', 'em_andamento'])
            ->where('t.created_at', '<=', $corte)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('nexo_ticket_notas as n')
                  ->whereColumn('n.ticket_id', 't.id')
                  ->whereColumn('n.user_id', 't.responsavel_id');
            })
            ->select('t.id', 't.protocolo', 't.nome_cliente', 't.created_at')
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            $h = Carbon::parse($ticket->created_at)->diffInHours(Carbon::now());
            $count += $this->registrar('PEN-A02', $userId, $cfg,
                "Ticket {$ticket->protocolo} ({$ticket->nome_cliente}) aberto ha {$h}h sem tratativa.",
                'ticket', $ticket->id);
        }
        return $count;
    }

    /**
     * PEN-A03: Transferencia sem nota de contexto
     * Fonte: nexo_ticket_notas (tipo=transferencia com texto vazio/curto)
     */
    private function scanA03(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A03');
        if (!$cfg) return 0;

        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateTimeString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        $transferencias = DB::table('nexo_ticket_notas')
            ->where('user_id', $userId)
            ->where('tipo', 'transferencia')
            ->whereBetween('created_at', [$inicioMes, $fimMes])
            ->where(function ($q) {
                $q->whereNull('texto')
                  ->orWhere('texto', '')
                  ->orWhereRaw('CHAR_LENGTH(TRIM(texto)) < 5');
            })
            ->select('id', 'ticket_id', 'created_at')
            ->get();

        $count = 0;
        foreach ($transferencias as $tr) {
            $count += $this->registrar('PEN-A03', $userId, $cfg,
                "Transferencia de ticket #{$tr->ticket_id} em " . Carbon::parse($tr->created_at)->format('d/m') . " sem nota explicativa.",
                'ticket_nota', $tr->id);
        }
        return $count;
    }

    /**
     * PEN-A04: Bot assumido e abandonado (sem resposta em Xmin)
     * Fonte: wa_conversations (bot_ativo=0, assigned_at) + wa_messages
     * Logica: Penaliza se havia msg pendente do cliente no momento de assumir
     *         e nao foi respondida em X minutos apos essa msg (nao apos assigned_at)
     */
    private function scanA04(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A04');
        if (!$cfg) return 0;

        $minutos   = $cfg['threshold'];
        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateTimeString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        $conversas = DB::table('wa_conversations')
            ->where('assigned_user_id', $userId)
            ->where('bot_ativo', 0)
            ->whereNotNull('assigned_at')
            ->whereBetween('assigned_at', [$inicioMes, $fimMes])
            ->select('id', 'name', 'phone', 'assigned_at')
            ->get();

        $count = 0;
        foreach ($conversas as $conv) {
            $assignedAt = Carbon::parse($conv->assigned_at);

            // Ignorar assumidas fora do horario comercial (8h-18h seg-sex)
            if ($assignedAt->isWeekend() || $assignedAt->hour < 8 || $assignedAt->hour >= 18) continue;

            // Buscar ultima msg do CLIENTE antes ou no momento de assumir
            $ultimaMsgCliente = DB::table('wa_messages')
                ->where('conversation_id', $conv->id)
                ->where('direction', 1)
                ->where('sent_at', '<=', $conv->assigned_at)
                ->orderByDesc('sent_at')
                ->first();

            // Se nao havia msg do cliente, nada a responder
            if (!$ultimaMsgCliente) continue;

            $msgClienteAt = Carbon::parse($ultimaMsgCliente->sent_at);

            // Verificar se JA havia resposta humana APOS essa msg do cliente (antes de assumir)
            $jaRespondida = DB::table('wa_messages')
                ->where('conversation_id', $conv->id)
                ->where('direction', 2)
                ->where('sent_at', '>', $ultimaMsgCliente->sent_at)
                ->where('sent_at', '<=', $conv->assigned_at)
                ->exists();

            // Se ja tinha resposta antes de assumir, nao penalizar
            if ($jaRespondida) continue;

            // Deadline: X minutos apos a msg do cliente (nao apos assigned_at)
            $deadline = $msgClienteAt->copy()->addMinutes($minutos);
            if (!$deadline->isPast()) continue; // Ainda dentro do prazo

            // Verificar se respondeu dentro do prazo
            $respondeu = DB::table('wa_messages')
                ->where('conversation_id', $conv->id)
                ->where('direction', 2)
                ->where('sent_at', '>', $ultimaMsgCliente->sent_at)
                ->where('sent_at', '<=', $deadline->toDateTimeString())
                ->exists();

            if (!$respondeu) {
                $count += $this->registrar('PEN-A04', $userId, $cfg,
                    "Bot assumido para {$conv->name} ({$conv->phone}) em " . $assignedAt->format('d/m H:i') . " sem resposta em {$minutos}min.",
                    'wa_conversation', $conv->id);
            }
        }
        return $count;
    }

    /**
     * PEN-A05: Reclamacao sem tratativa em Xh
     * Fonte: nexo_tickets (tipo=reclamacao) + nexo_ticket_notas
     */
    private function scanA05(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A05');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subHours($cfg['threshold'])->toDateTimeString();

        $tickets = DB::table('nexo_tickets as t')
            ->where('t.responsavel_id', $userId)
            ->where('t.tipo', 'reclamacao')
            ->whereIn('t.status', ['aberto', 'em_andamento'])
            ->where('t.created_at', '<=', $corte)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('nexo_ticket_notas as n')
                  ->whereColumn('n.ticket_id', 't.id')
                  ->whereColumn('n.user_id', 't.responsavel_id');
            })
            ->select('t.id', 't.protocolo', 't.nome_cliente')
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            $count += $this->registrar('PEN-A05', $userId, $cfg,
                "Reclamacao ticket {$ticket->protocolo} ({$ticket->nome_cliente}) sem tratativa em {$cfg['threshold']}h.",
                'ticket', $ticket->id);
        }
        return $count;
    }

    /**
     * PEN-A06: Follow-up forcado pelo cliente (reenviou msg apos Xh sem resposta)
     * Fonte: wa_messages (incoming consecutivas sem outgoing entre elas)
     */
    private function scanA06(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A06');
        if (!$cfg) return 0;

        $horas     = $cfg['threshold'];
        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateTimeString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        $convIds = DB::table('wa_conversations')
            ->where('assigned_user_id', $userId)
            ->pluck('id');

        if ($convIds->isEmpty()) return 0;

        $count = 0;
        foreach ($convIds as $convId) {
            // Pular conversas muito antigas (mais de 1 mes antes do periodo)
            $convCriada = DB::table('wa_conversations')->where('id', $convId)->value('created_at');
            if ($convCriada && Carbon::parse($convCriada)->lt(Carbon::create($this->ano, $this->mes, 1)->subMonths(1))) {
                continue;
            }

            $incomings = DB::table('wa_messages')
                ->where('conversation_id', $convId)
                ->where('direction', 1)
                ->whereBetween('sent_at', [$inicioMes, $fimMes])
                ->orderBy('sent_at')
                ->select('id', 'sent_at')
                ->limit(100)
                ->get();

            $jaRegistrouConversa = false; // Max 1 ocorrencia por conversa/mes

            for ($i = 0; $i < count($incomings) - 1; $i++) {
                if ($jaRegistrouConversa) break;

                $msg1 = $incomings[$i];
                $msg2 = $incomings[$i + 1];

                $diffHoras = Carbon::parse($msg1->sent_at)->diffInMinutes(Carbon::parse($msg2->sent_at)) / 60;
                if ($diffHoras < $horas) continue;
                if ($diffHoras > 168) continue; // Ignorar intervalos > 7 dias

                $respondeu = DB::table('wa_messages')
                    ->where('conversation_id', $convId)
                    ->where('direction', 2)
                    ->where('sent_at', '>', $msg1->sent_at)
                    ->where('sent_at', '<', $msg2->sent_at)
                    ->exists();

                if (!$respondeu) {
                    $diffArred = round($diffHoras, 1);
                    $count += $this->registrar('PEN-A06', $userId, $cfg,
                        "Cliente reenviou mensagem apos {$diffArred}h sem resposta (conversa #{$convId}).",
                        'wa_conversation', $convId);
                    $jaRegistrouConversa = true;
                }
            }
        }
        return $count;
    }

    /**
     * PEN-A07: Lead qualificado sem follow-up Xh
     * Fonte: leads (intencao_contratar=sim) + crm_activities
     */
    private function scanA07(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A07');
        if (!$cfg) return 0;

        $corte     = Carbon::now()->subHours($cfg['threshold'])->toDateTimeString();
        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateTimeString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->endOfDay()->toDateTimeString();

        // Somente leads cujo crm_account pertence (owner_user_id) ao advogado sendo escaneado
        $leads = DB::table('leads as l')
            ->join('crm_accounts as ca', 'ca.id', '=', 'l.crm_account_id')
            ->where('l.intencao_contratar', 'sim')
            ->whereBetween('l.created_at', [$inicioMes, $fimMes])
            ->where('l.created_at', '<=', $corte)
            ->whereNotNull('l.crm_account_id')
            ->where('ca.owner_user_id', $userId)
            ->whereNotExists(function ($q) use ($userId) {
                $q->select(DB::raw(1))
                  ->from('crm_activities as act')
                  ->whereColumn('act.account_id', 'l.crm_account_id')
                  ->where('act.created_by_user_id', $userId)
                  ->whereColumn('act.created_at', '>=', 'l.created_at');
            })
            ->select('l.id', 'l.nome', 'l.telefone', 'l.created_at')
            ->get();

        $count = 0;
        foreach ($leads as $lead) {
            $count += $this->registrar('PEN-A07', $userId, $cfg,
                "Lead qualificado {$lead->nome} ({$lead->telefone}) sem follow-up CRM em {$cfg['threshold']}h.",
                'lead', $lead->id);
        }
        return $count;
    }

    /**
     * PEN-A08: Oportunidade CRM estagnada ha X dias
     * Fonte: crm_opportunities + crm_activities
     */
    private function scanA08(int $userId): int
    {
        $cfg = $this->getTipo('PEN-A08');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subDays($cfg['threshold'])->toDateTimeString();

        $opps = DB::table('crm_opportunities as o')
            ->where('o.owner_user_id', $userId)
            ->where('o.status', 'open')
            ->where('o.updated_at', '<', $corte)
            ->whereNotExists(function ($q) use ($corte) {
                $q->select(DB::raw(1))
                  ->from('crm_activities as ca')
                  ->whereColumn('ca.opportunity_id', 'o.id')
                  ->where('ca.created_at', '>=', $corte);
            })
            ->select('o.id', 'o.title', 'o.updated_at')
            ->get();

        $count = 0;
        foreach ($opps as $opp) {
            $dias = Carbon::parse($opp->updated_at)->diffInDays(Carbon::now());
            $count += $this->registrar('PEN-A08', $userId, $cfg,
                "Oportunidade '{$opp->title}' estagnada ha {$dias} dias sem atividade.",
                'crm_opportunity', $opp->id);
        }
        return $count;
    }

    // ================================================================
    // EIXO DESENVOLVIMENTO / COMPLIANCE
    // ================================================================

    /**
     * PEN-D01: Semana sem registro de horas
     * Fonte: horas_trabalhadas_datajuri (proprietario_id, data)
     */
    private function scanD01(int $userId): int
    {
        $cfg = $this->getTipo('PEN-D01');
        if (!$cfg) return 0;

        // Buscar datajuri_proprietario_id do usuario (tabela horas usa ID do DataJuri, nao da Intranet)
        $proprietarioId = DB::table('users')->where('id', $userId)->value('datajuri_proprietario_id');
        if (!$proprietarioId) return 0; // Usuario sem vinculo DataJuri

        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth();
        $hoje      = Carbon::now();

        $count  = 0;
        $semana = $inicioMes->copy()->startOfWeek(Carbon::MONDAY);

        while ($semana->lte($fimMes) && $semana->lte($hoje)) {
            $fimSemana = $semana->copy()->endOfWeek(Carbon::FRIDAY);
            if ($fimSemana->gt($hoje)) break; // Nao penalizar semana em andamento

            $temRegistro = DB::table('horas_trabalhadas_datajuri')
                ->where('proprietario_id', $proprietarioId)
                ->whereBetween('data', [$semana->toDateString(), $fimSemana->toDateString()])
                ->exists();

            if (!$temRegistro) {
                $label = $semana->format('d/m') . '-' . $fimSemana->format('d/m');
                $count += $this->registrar('PEN-D01', $userId, $cfg,
                    "Semana {$label} sem nenhum registro de horas trabalhadas.",
                    'semana', (int) $semana->format('Ymd'));
            }

            $semana->addWeek();
        }
        return $count;
    }

    /**
     * PEN-D02: Aviso prioritario nao lido em Xh
     * Fonte: avisos (prioridade=alta/critica) + avisos_lidos
     */
    private function scanD02(int $userId): int
    {
        $cfg = $this->getTipo('PEN-D02');
        if (!$cfg) return 0;

        $corte = Carbon::now()->subHours($cfg['threshold'])->toDateTimeString();

        $avisos = DB::table('avisos as a')
            ->whereIn('a.prioridade', ['alta', 'critica'])
            ->where('a.status', 'ativo')
            ->where('a.created_at', '<=', $corte)
            ->whereNotExists(function ($q) use ($userId) {
                $q->select(DB::raw(1))
                  ->from('avisos_lidos as al')
                  ->whereColumn('al.aviso_id', 'a.id')
                  ->where('al.usuario_id', $userId);
            })
            ->select('a.id', 'a.titulo', 'a.prioridade', 'a.created_at')
            ->get();

        $count = 0;
        foreach ($avisos as $aviso) {
            $count += $this->registrar('PEN-D02', $userId, $cfg,
                "Aviso prioritario '{$aviso->titulo}' ({$aviso->prioridade}) nao lido ha mais de {$cfg['threshold']}h.",
                'aviso', $aviso->id);
        }
        return $count;
    }

    /**
     * PEN-D03: Acordo GDP nao aceito no prazo
     * Fonte: gdp_snapshots (congelado) + gdp_metas_individuais (created_at)
     */
    private function scanD03(int $userId): int
    {
        $cfg = $this->getTipo('PEN-D03');
        if (!$cfg) return 0;

        $dias = $cfg['threshold'];

        // Verificar se ja aceitou (snapshot congelado)
        $jaAceitou = DB::table('gdp_snapshots')
            ->where('ciclo_id', $this->cicloId)
            ->where('user_id', $userId)
            ->where('congelado', true)
            ->exists();

        if ($jaAceitou) return 0;

        // Verificar se o acordo foi FORMALMENTE enviado ao advogado
        // Evidencia: registro no audit_log de 'acordo_salvo' para este usuario
        $envioFormal = DB::table('gdp_audit_log')
            ->where('entidade', 'gdp_metas_individuais')
            ->where('entidade_id', $userId)
            ->where('campo', 'acordo_salvo')
            ->min('created_at');

        // Se nunca foi enviado formalmente, nao penalizar
        if (!$envioFormal) return 0;

        $diasDesdeEnvio = (int) Carbon::parse($envioFormal)->diffInDays(Carbon::now());
        if ($diasDesdeEnvio <= $dias) return 0;

        return $this->registrar('PEN-D03', $userId, $cfg,
            "Acordo GDP enviado em " . Carbon::parse($envioFormal)->format('d/m/Y') . " nao aceito ha {$diasDesdeEnvio} dias (limite: {$dias} dias).",
            'gdp_audit', 0);
    }

    /**
     * PEN-D04: Processo encerrado com insucesso sem justificativa
     * Fonte: processos (tipo_encerramento) + atividades_datajuri
     */
    private function scanD04(int $userId): int
    {
        $cfg = $this->getTipo('PEN-D04');
        if (!$cfg) return 0;

        $inicioMes = Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateString();
        $fimMes    = Carbon::create($this->ano, $this->mes, 1)->endOfMonth()->toDateString();

        $processos = DB::table('processos')
            ->where('proprietario_id', $userId)
            ->where('status', 'Encerrado')
            ->whereNotNull('data_encerramento')
            ->whereBetween('data_encerramento', [$inicioMes, $fimMes])
            ->whereNotNull('tipo_encerramento')
            ->where(function ($q) {
                $q->where('tipo_encerramento', 'LIKE', '%insucesso%')
                  ->orWhere('tipo_encerramento', 'LIKE', '%improcedente%')
                  ->orWhere('tipo_encerramento', 'LIKE', '%desfavoravel%')
                  ->orWhere('tipo_encerramento', 'LIKE', '%desfavorável%');
            })
            ->select('id', 'pasta', 'tipo_encerramento')
            ->get();

        $count = 0;
        foreach ($processos as $proc) {
            $temJustificativa = DB::table('atividades_datajuri')
                ->where('proprietario_id', $userId)
                ->where('processo_pasta', $proc->pasta)
                ->where('data_hora', '>=', $inicioMes)
                ->exists();

            if (!$temJustificativa) {
                $count += $this->registrar('PEN-D04', $userId, $cfg,
                    "Processo pasta {$proc->pasta} encerrado com '{$proc->tipo_encerramento}' sem justificativa registrada.",
                    'processo', $proc->id);
            }
        }
        return $count;
    }


    // ================================================================
    // EIXO VIGILIA (JURIDICO)
    // ================================================================

    /**
     * PEN-V01: Conclusao suspeita - atividade Concluido sem andamento do escritorio
     * Fonte: vigilia_cruzamentos (status_cruzamento=suspeito)
     */
    private function scanV01(int $userId): int
    {
        $cfg = $this->getTipo('PEN-V01');
        if (!$cfg) return 0;

        $djPropId = DB::table('users')->where('id', $userId)->value('datajuri_proprietario_id');
        if (!$djPropId) return 0;

        $suspeitos = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('vc.status_cruzamento', 'suspeito')
            ->where('ad.proprietario_id', $djPropId)
            ->where('ad.status', 'Concluído')
            ->whereYear('ad.data_hora', $this->ano)
            ->whereMonth('ad.data_hora', $this->mes)
            ->select('vc.id', 'ad.processo_pasta', 'ad.tipo_atividade', 'ad.data_hora', 'vc.observacao')
            ->get();

        $count = 0;
        foreach ($suspeitos as $s) {
            $count += $this->registrar('PEN-V01', $userId, $cfg,
                "Conclusao suspeita: " . ($s->tipo_atividade ?? 'atividade') . " pasta " . $s->processo_pasta . " em " . Carbon::parse($s->data_hora)->format('d/m') . " sem andamento do escritorio.",
                'vigilia_cruzamento', $s->id);
        }
        return $count;
    }

    /**
     * PEN-V02: Prazo vencido sem acao - atividade nao concluida e sem acao no processo
     * Fonte: vigilia_cruzamentos (status_cruzamento=sem_acao)
     */
    private function scanV02(int $userId): int
    {
        $cfg = $this->getTipo('PEN-V02');
        if (!$cfg) return 0;

        $djPropId = DB::table('users')->where('id', $userId)->value('datajuri_proprietario_id');
        if (!$djPropId) return 0;

        $semAcao = DB::table('vigilia_cruzamentos as vc')
            ->join('atividades_datajuri as ad', 'ad.id', '=', 'vc.atividade_datajuri_id')
            ->where('vc.status_cruzamento', 'sem_acao')
            ->where('ad.proprietario_id', $djPropId)
            ->whereYear('ad.data_hora', $this->ano)
            ->whereMonth('ad.data_hora', $this->mes)
            ->select('vc.id', 'ad.processo_pasta', 'ad.tipo_atividade', 'ad.data_prazo_fatal', 'vc.observacao')
            ->get();

        $count = 0;
        foreach ($semAcao as $s) {
            $prazo = $s->data_prazo_fatal ? Carbon::parse($s->data_prazo_fatal)->format('d/m') : 'sem prazo';
            $count += $this->registrar('PEN-V02', $userId, $cfg,
                "Sem acao: " . ($s->tipo_atividade ?? 'atividade') . " pasta " . $s->processo_pasta . " (prazo: " . $prazo . ") sem conclusao e sem peticao no processo.",
                'vigilia_cruzamento', $s->id);
        }
        return $count;
    }

    /**
     * PEN-V03: Tarefa trigger vencida >48h sem conclusao
     * Fonte: atividades_datajuri (tipo=Tarefa, assunto trigger, >48h sem conclusao)
     */
    private function scanV03(int $userId): int
    {
        $cfg = $this->getTipo('PEN-V03');
        if (!$cfg) return 0;

        $djPropId = DB::table('users')->where('id', $userId)->value('datajuri_proprietario_id');
        if (!$djPropId) return 0;

        $horas = $cfg['threshold'] ?: 48;
        $corte = Carbon::now()->subHours($horas)->toDateTimeString();
        $triggers = ['Analise de Decisao', 'Providencias Pos-Audiencia', 'Relatorio de Ocorrencia', 'Verificacao de Cumprimento',
                     'Análise de Decisão', 'Providências Pós-Audiência', 'Relatório de Ocorrência', 'Verificação de Cumprimento'];

        $tarefas = DB::table('atividades_datajuri')
            ->where('proprietario_id', $djPropId)
            ->where('tipo_atividade', 'Tarefa')
            ->where(function($q) use ($triggers) {
                $q->whereIn('assunto', $triggers);
                if (in_array('assunto_original', \Schema::getColumnListing('atividades_datajuri'))) {
                    $q->orWhereIn('assunto_original', $triggers);
                }
            })
            ->whereIn('status', ['Nao iniciado', 'Não iniciado', 'Aguardando outra pessoa'])
            ->where('created_at', '<=', $corte)
            ->where('data_hora', '>=', Carbon::create($this->ano, $this->mes, 1)->startOfMonth()->toDateTimeString())
            ->select('id', 'processo_pasta', 'assunto', 'data_hora', 'created_at')
            ->get();

        $count = 0;
        foreach ($tarefas as $t) {
            $h = Carbon::parse($t->created_at)->diffInHours(Carbon::now());
            $count += $this->registrar('PEN-V03', $userId, $cfg,
                "Trigger vencido: " . $t->assunto . " pasta " . ($t->processo_pasta ?? 'N/A') . " ha " . $h . "h sem conclusao.",
                'atividade', $t->id);
        }
        return $count;
    }

    // ================================================================
    // EIXO SIATE (ATENDIMENTO)
    // ================================================================

    /**
     * PEN-S01: Chamado SIATE com SLA estourado
     * Fonte: crm_service_requests (assigned_to_user_id, sla_deadline, status)
     */
    private function scanS01(int $userId): int
    {
        $cfg = $this->getTipo('PEN-S01');
        if (!$cfg) return 0;

        $chamados = DB::table('crm_service_requests')
            ->where('assigned_to_user_id', $userId)
            ->whereNotNull('sla_deadline')
            ->where('sla_deadline', '<', Carbon::now()->toDateTimeString())
            ->whereNotIn('status', ['resolvido', 'fechado', 'cancelado', 'concluido'])
            ->select('id', 'protocolo', 'subject', 'sla_deadline', 'status')
            ->get();

        $count = 0;
        foreach ($chamados as $ch) {
            $horas = Carbon::parse($ch->sla_deadline)->diffInHours(Carbon::now());
            $count += $this->registrar('PEN-S01', $userId, $cfg,
                "Chamado " . $ch->protocolo . " com SLA estourado ha " . $horas . "h (status: " . $ch->status . ").",
                'service_request', $ch->id);
        }
        return $count;
    }

    /**
     * PEN-C01: Gate de qualidade de dados CRM escalado (>7d sem correcao no DJ).
     * Fonte: crm_account_data_gates (owner_user_id, status=escalado, penalidade_registrada=0)
     * Eixo: Atendimento (4). Peso: 3 pts.
     */
    private function scanC01(int $userId): int
    {
        $cfg = $this->getTipo('PEN-C01');
        if (!$cfg) return 0;

        $gates = DB::table('crm_account_data_gates as g')
            ->leftJoin('crm_accounts as a', 'a.id', '=', 'g.account_id')
            ->where('g.owner_user_id', $userId)
            ->where('g.status', 'escalado')
            ->where('g.penalidade_registrada', false)
            ->select('g.id', 'g.tipo', 'g.escalated_at', 'a.name as account_name')
            ->get();

        $count = 0;
        foreach ($gates as $g) {
            $inserido = $this->registrar('PEN-C01', $userId, $cfg,
                "Gate '{$g->tipo}' na conta '{$g->account_name}' sem correcao no DJ em 7+ dias.",
                'crm_account_data_gate', $g->id);

            if ($inserido > 0) {
                DB::table('crm_account_data_gates')
                    ->where('id', $g->id)
                    ->update(['penalidade_registrada' => true, 'updated_at' => now()]);
            }
            $count += $inserido;
        }
        return $count;
    }

    // ================================================================
    // HELPERS
    // ================================================================

    private function getTipo(string $codigo): ?array
    {
        return $this->tiposCache[$codigo] ?? null;
    }

    /**
     * Verifica se houve contato com cliente (WA ou atividade) desde uma data
     */
    private function verificarContatoCliente(int $userId, int $clienteDjId, string $desde): bool
    {
        // 1. Verificar via WhatsApp
        $clientePhone = DB::table('clientes')
            ->where('datajuri_id', $clienteDjId)
            ->value('telefone');

        if ($clientePhone) {
            $phoneClean = preg_replace('/\D/', '', $clientePhone);
            $suffix = substr($phoneClean, -8);
            if ($suffix && strlen($suffix) === 8) {
                $temWA = DB::table('wa_messages as wm')
                    ->join('wa_conversations as wc', 'wc.id', '=', 'wm.conversation_id')
                    ->where('wc.phone', 'LIKE', '%' . $suffix)
                    ->where('wm.direction', 2)
                    ->where('wm.sent_at', '>=', $desde)
                    ->exists();
                if ($temWA) return true;
            }
        }

        // 2. Verificar via atividade DataJuri
        $temAtividade = DB::table('atividades_datajuri')
            ->where('proprietario_id', $userId)
            ->where('data_hora', '>=', $desde)
            ->whereExists(function ($q) use ($clienteDjId) {
                $q->select(DB::raw(1))
                  ->from('processos')
                  ->whereColumn('processos.pasta', 'atividades_datajuri.processo_pasta')
                  ->where('processos.cliente_datajuri_id', $clienteDjId);
            })
            ->exists();

        return $temAtividade;
    }

    /**
     * Registra penalizacao evitando duplicata
     * Retorna 1 se inseriu, 0 se ja existia
     */
    private function registrar(string $codigo, int $userId, array $cfg, string $descricao, string $refTipo, $refId): int
    {
        $tipo   = $cfg['model'];
        $pontos = $cfg['pontos'];

        $exists = GdpPenalizacao::where('ciclo_id', $this->cicloId)
            ->where('user_id', $userId)
            ->where('tipo_id', $tipo->id)
            ->where('mes', $this->mes)
            ->where('ano', $this->ano)
            ->where('referencia_tipo', $refTipo)
            ->where('referencia_id', $refId)
            ->exists();

        if ($exists) return 0;

        GdpPenalizacao::create([
            'ciclo_id'             => $this->cicloId,
            'user_id'              => $userId,
            'tipo_id'              => $tipo->id,
            'mes'                  => $this->mes,
            'ano'                  => $this->ano,
            'pontos_desconto'      => $pontos,
            'descricao_automatica' => mb_substr($descricao, 0, 500),
            'referencia_tipo'      => $refTipo,
            'referencia_id'        => $refId,
            'automatica'           => true,
            'contestada'           => false,
        ]);

        // Log de Ocorrencias
        $userName = DB::table('users')->where('id', $userId)->value('name') ?? 'ID:' . $userId;
        SystemEvent::gdp('penalidade.criada', 'warning', 'Penalidade: ' . $codigo . ' - ' . $userName, $descricao, ['user_id' => $userId, 'pontos' => $pontos, 'mes' => $this->mes, 'ano' => $this->ano, 'codigo' => $codigo]);

        return 1;
    }
}
