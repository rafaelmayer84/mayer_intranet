<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Cliente;
use App\Models\Oportunidade;
use App\Models\Processo;
use App\Models\PricingCalibration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PricingDataCollectorService
{
    /**
     * Busca lead/cliente por nome ou documento (CPF/CNPJ)
     */
    public function buscarProponente(string $busca): array
    {
        $resultados = [];

        // Buscar em leads
        $leads = Lead::where('nome', 'like', "%{$busca}%")
            ->orWhere('telefone', 'like', "%{$busca}%")
            ->limit(10)
            ->get(['id', 'nome', 'telefone', 'email', 'cidade', 'area_interesse', 'status',
                    'resumo_demanda', 'potencial_honorarios', 'urgencia', 'intencao_contratar',
                    'palavras_chave']);

        foreach ($leads as $lead) {
            $resultados[] = [
                'tipo' => 'lead',
                'id' => $lead->id,
                'nome' => $lead->nome,
                'telefone' => $lead->telefone,
                'email' => $lead->email,
                'info' => 'Lead - ' . ($lead->area_interesse ?? 'sem área') . ' - ' . ($lead->status ?? 'novo'),
                'lead' => $lead->toArray(),
            ];
        }

        // Buscar em clientes
        $clientes = Cliente::where('nome', 'like', "%{$busca}%")
            ->orWhere('cpf_cnpj', 'like', "%{$busca}%")
            ->orWhere('cpf', 'like', "%{$busca}%")
            ->orWhere('cnpj', 'like', "%{$busca}%")
            ->limit(10)
            ->get();

        foreach ($clientes as $cliente) {
            $resultados[] = [
                'tipo' => 'cliente',
                'id' => $cliente->id,
                'nome' => $cliente->nome,
                'documento' => $cliente->cpf_cnpj ?? $cliente->cpf ?? $cliente->cnpj,
                'info' => 'Cliente - ' . ($cliente->tipo ?? $cliente->tipo_pessoa ?? 'N/I'),
                'cliente' => $cliente->toArray(),
            ];
        }

        return $resultados;
    }

    /**
     * Coleta TODOS os dados disponíveis de um lead para a IA
     */
    public function coletarDadosLead(int $leadId): array
    {
        $lead = Lead::find($leadId);
        if (!$lead) {
            return ['erro' => 'Lead não encontrado'];
        }

        $dados = [
            'proponente' => [
                'nome' => $lead->nome,
                'telefone' => $lead->telefone,
                'email' => $lead->email,
                'cidade' => $lead->cidade,
                'tipo_pessoa' => 'PF', // Leads são geralmente PF
                'documento' => null,
            ],
            'demanda' => [
                'area_interesse' => $lead->area_interesse,
                'resumo_demanda' => $lead->resumo_demanda,
                'palavras_chave' => $lead->palavras_chave,
                'urgencia' => $lead->urgencia,
                'intencao_contratar' => $lead->intencao_contratar,
                'potencial_honorarios' => $lead->potencial_honorarios,
            ],
            'lead_id' => $leadId,
            'cliente_id' => $lead->cliente_id,
        ];

        // Se lead vinculado a cliente, puxar dados do cliente
        if ($lead->cliente_id) {
            $dadosCliente = $this->coletarDadosCliente($lead->cliente_id);
            $dados = array_merge($dados, $dadosCliente);
        }

        return $dados;
    }

    /**
     * Coleta TODOS os dados disponíveis de um cliente para a IA
     */
    public function coletarDadosCliente(int $clienteId): array
    {
        $cliente = Cliente::find($clienteId);
        if (!$cliente) {
            return ['erro' => 'Cliente não encontrado'];
        }

        $dados = [
            'proponente' => [
                'nome' => $cliente->nome,
                'telefone' => $cliente->telefone,
                'email' => $cliente->email,
                'cidade' => $cliente->endereco ?? null,
                'tipo_pessoa' => $cliente->tipo ?? $cliente->tipo_pessoa ?? 'PF',
                'documento' => $cliente->cpf_cnpj ?? $cliente->cpf ?? $cliente->cnpj,
            ],
            'cliente_id' => $clienteId,
        ];

        // Histórico de processos do cliente
        $processos = Processo::where('cliente_id', $clienteId)->get();
        $dados['historico_cliente'] = [
            'total_processos' => $processos->count(),
            'processos_ativos' => $processos->where('status', 'Ativo')->count(),
            'processos_encerrados' => $processos->whereIn('status', ['Encerrado', 'Arquivado', 'Baixado'])->count(),
            'valor_causa_total' => $processos->sum('valor_causa'),
            'valor_causa_medio' => $processos->count() > 0 ? $processos->avg('valor_causa') : 0,
            'areas' => $processos->pluck('natureza')->filter()->unique()->values()->toArray(),
        ];

        // Dados financeiros do cliente
        $dados['financeiro_cliente'] = $this->getDadosFinanceirosCliente($clienteId);

        // SIRIC - Análise de crédito (se existir)
        $dados['siric'] = $this->getSiricData($clienteId, $cliente->cpf_cnpj ?? $cliente->cpf ?? $cliente->cnpj);

        return $dados;
    }

    /**
     * Busca dados financeiros agregados do cliente
     */
    private function getDadosFinanceirosCliente(int $clienteId): array
    {
        try {
            // Total faturado
            $faturamento = DB::table('movimentos')
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('pessoa_id_datajuri', function ($q) use ($clienteId) {
                    $q->select('datajuri_id')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->sum('valor');

            // Contas em atraso
            $atraso = DB::table('contas_receber')
                ->where('cliente', function ($q) use ($clienteId) {
                    $q->select('nome')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->where('status', '!=', 'Pago')
                ->whereDate('data_vencimento', '<', now())
                ->sum('valor');

            // Pontualidade (% pago no prazo)
            $totalContas = DB::table('contas_receber')
                ->where('cliente', function ($q) use ($clienteId) {
                    $q->select('nome')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->count();

            $contasPagas = DB::table('contas_receber')
                ->where('cliente', function ($q) use ($clienteId) {
                    $q->select('nome')->from('clientes')->where('id', $clienteId)->limit(1);
                })
                ->where('status', 'Pago')
                ->count();

            return [
                'total_faturado' => round($faturamento, 2),
                'valor_em_atraso' => round($atraso, 2),
                'total_contas' => $totalContas,
                'contas_pagas' => $contasPagas,
                'taxa_pontualidade' => $totalContas > 0 ? round(($contasPagas / $totalContas) * 100, 1) : null,
            ];
        } catch (\Exception $e) {
            Log::warning('PricingDataCollector: Erro ao buscar financeiro do cliente', ['erro' => $e->getMessage()]);
            return ['total_faturado' => null, 'valor_em_atraso' => null];
        }
    }

    /**
     * Busca dados do SIRIC (análise de crédito já processada)
     */
    private function getSiricData(int $clienteId, ?string $documento): array
    {
        try {
            // Tenta buscar resultado SIRIC mais recente
            $siric = DB::table('siric_analises')
                ->where('cliente_id', $clienteId)
                ->orWhere('documento', $documento)
                ->orderByDesc('created_at')
                ->first();

            if ($siric) {
                return [
                    'score' => $siric->score ?? null,
                    'rating' => $siric->rating ?? null,
                    'limite_sugerido' => $siric->limite_sugerido ?? null,
                    'recomendacao' => $siric->recomendacao ?? null,
                    'data_analise' => $siric->created_at ?? null,
                ];
            }
        } catch (\Exception $e) {
            // Tabela SIRIC pode não existir ainda - graceful degradation
            Log::info('PricingDataCollector: SIRIC não disponível', ['erro' => $e->getMessage()]);
        }

        return ['score' => null, 'rating' => null, 'limite_sugerido' => null, 'recomendacao' => null];
    }

    /**
     * Coleta histórico agregado do escritório para casos similares
     */
    public function getHistoricoAgregado(?string $areaDireito): array
    {
        try {
            $query = DB::table('processos')->whereNotNull('valor_causa')->where('valor_causa', '>', 0);

            if ($areaDireito) {
                $query->where(function ($q) use ($areaDireito) {
                    $q->where('natureza', 'like', "%{$areaDireito}%")
                      ->orWhere('assunto', 'like', "%{$areaDireito}%");
                });
            }

            $stats = $query->selectRaw('
                COUNT(*) as total_casos,
                AVG(valor_causa) as valor_causa_medio,
                MIN(valor_causa) as valor_causa_min,
                MAX(valor_causa) as valor_causa_max,
                STDDEV(valor_causa) as valor_causa_desvio
            ')->first();

            // Tempo médio dos processos da mesma área
            $tempoMedio = DB::table('processos')
                ->whereNotNull('data_cadastro_dj')
                ->whereIn('status', ['Encerrado', 'Arquivado', 'Baixado'])
                ->when($areaDireito, function ($q) use ($areaDireito) {
                    $q->where('natureza', 'like', "%{$areaDireito}%");
                })
                ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as dias_medio')
                ->value('dias_medio');

            // Horas trabalhadas médias
            $horasMedia = DB::table('horas_trabalhadas_datajuri')
                ->selectRaw('AVG(total_hora_trabalhada) as media_horas')
                ->value('media_horas');

            // Receita média por caso na área
            $receitaMedia = DB::table('contratos')
                ->whereNotNull('valor')
                ->where('valor', '>', 0)
                ->selectRaw('AVG(valor) as receita_media, COUNT(*) as total_contratos')
                ->first();

            return [
                'area_filtro' => $areaDireito ?? 'Todas as áreas',
                'total_casos' => $stats->total_casos ?? 0,
                'valor_causa_medio' => round($stats->valor_causa_medio ?? 0, 2),
                'valor_causa_min' => round($stats->valor_causa_min ?? 0, 2),
                'valor_causa_max' => round($stats->valor_causa_max ?? 0, 2),
                'tempo_medio_dias' => round($tempoMedio ?? 0),
                'horas_media_caso' => round($horasMedia ?? 0, 1),
                'receita_media_contrato' => round($receitaMedia->receita_media ?? 0, 2),
                'total_contratos' => $receitaMedia->total_contratos ?? 0,
            ];
        } catch (\Exception $e) {
            Log::warning('PricingDataCollector: Erro no histórico agregado', ['erro' => $e->getMessage()]);
            return ['total_casos' => 0, 'valor_causa_medio' => 0];
        }
    }

    /**
     * Coleta dados macro do escritório (metas, capacidade)
     */
    public function getDadosMacroEscritorio(): array
    {
        try {
            $mesAtual = now()->month;
            $anoAtual = now()->year;

            // Receita do mês atual
            $receitaMes = DB::table('movimentos')
                ->whereIn('classificacao', ['RECEITA_PF', 'RECEITA_PJ'])
                ->where('mes', $mesAtual)
                ->where('ano', $anoAtual)
                ->sum('valor');

            // Meta do mês (se existir)
            $metaMes = DB::table('kpi_monthly_targets')
                ->where('mes', $mesAtual)
                ->where('ano', $anoAtual)
                ->where('kpi_key', 'receita_total')
                ->value('valor_meta');

            // Processos ativos
            $processosAtivos = DB::table('processos')
                ->where('status', 'Ativo')
                ->count();

            // Pipeline aberto
            $pipelineAberto = DB::table('oportunidades')
                ->whereNotIn('estagio', ['ganha', 'perdida', 'Closed Won', 'Closed Lost'])
                ->sum('valor');

            return [
                'receita_mes_atual' => round($receitaMes, 2),
                'meta_mes_atual' => $metaMes ? round($metaMes, 2) : null,
                'percentual_meta' => ($metaMes && $metaMes > 0) ? round(($receitaMes / $metaMes) * 100, 1) : null,
                'processos_ativos' => $processosAtivos,
                'pipeline_aberto' => round($pipelineAberto, 2),
                'mes_referencia' => $mesAtual . '/' . $anoAtual,
            ];
        } catch (\Exception $e) {
            Log::warning('PricingDataCollector: Erro nos dados macro', ['erro' => $e->getMessage()]);
            return ['receita_mes_atual' => 0];
        }
    }

    /**
     * Monta o pacote completo de dados para enviar à IA
     */
    public function montarPacoteIA(array $dadosProponente, ?string $areaDireito, ?string $contextoAdicional): array
    {
        $calibracao = PricingCalibration::getForPrompt();
        $historico = $this->getHistoricoAgregado($areaDireito);
        $macro = $this->getDadosMacroEscritorio();

        return [
            'proponente' => $dadosProponente['proponente'] ?? [],
            'demanda' => $dadosProponente['demanda'] ?? [],
            'historico_cliente' => $dadosProponente['historico_cliente'] ?? [],
            'financeiro_cliente' => $dadosProponente['financeiro_cliente'] ?? [],
            'siric' => $dadosProponente['siric'] ?? [],
            'historico_escritorio' => $historico,
            'macro_escritorio' => $macro,
            'calibracao_estrategica' => $calibracao,
            'contexto_adicional' => $contextoAdicional,
            'data_geracao' => now()->format('d/m/Y H:i'),
        ];
    }
}
