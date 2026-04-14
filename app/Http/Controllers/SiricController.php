<?php

/**
 * ============================================================================
 * SIRIC v2 — SiricController
 * ============================================================================
 *
 * Controller principal que orquestra o fluxo completo de análise de crédito.
 *
 * Responsabilidades:
 * - CRUD de consultas (index, create, store, show, destroy)
 * - Coleta de dados internos do BD (coletarDados)
 * - Orquestração da análise de crédito em 4 passos (analisarIA):
 *   1. Coleta interna (se não feita)
 *   2. Gate Decision DETERMINÍSTICO (v2 — calculado no PHP, não pela IA)
 *   3. Consulta Serasa condicionada (se gate >= 50 e autorizado, sem bypass)
 *   4. Relatório Final via IA (único estágio de IA na v2)
 * - Registro da decisão humana final (salvarDecisao)
 *
 * v2 mudanças:
 * - Gate decision agora usa SiricService::calcularGateScore() (determinístico)
 * - Evidência Serasa persistida como SiricEvidencia (fonte: asaas_serasa)
 * - Dados Serasa não são mais duplicados dentro do gate_result
 * - Threshold Serasa: 50 (antes 30) com bypass para bom histórico
 * ============================================================================
 */

namespace App\Http\Controllers;

use App\Models\SiricConsulta;
use App\Models\SiricEvidencia;
use App\Services\SiricService;
use App\Services\SiricOpenAIService;
use App\Services\SiricAsaasService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SiricController extends Controller
{
    protected SiricService $service;

    public function __construct(SiricService $service)
    {
        $this->service = $service;
    }

    /**
     * Lista todas as consultas.
     */
    public function index(Request $request)
    {
        $filtros = $request->only(['busca', 'status', 'rating']);

        $query = SiricConsulta::query()->orderByDesc('created_at');

        if (!empty($filtros['busca'])) {
            $busca = $filtros['busca'];
            $query->where(function ($q) use ($busca) {
                $q->where('nome', 'like', "%{$busca}%")
                  ->orWhere('cpf_cnpj', 'like', "%{$busca}%")
                  ->orWhere('email', 'like', "%{$busca}%");
            });
        }

        if (!empty($filtros['status'])) {
            $query->where('status', $filtros['status']);
        }

        if (!empty($filtros['rating'])) {
            $query->where('rating', $filtros['rating']);
        }

        $consultas = $query->paginate(20)->appends($filtros);

        return view('siric.index', compact('consultas', 'filtros'));
    }

    /**
     * Formulário de nova consulta.
     */
    public function create()
    {
        return view('siric.create');
    }

    /**
     * Salvar nova consulta.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cpf_cnpj'                     => 'required|string|max:20',
            'nome'                         => 'required|string|max:255',
            'telefone'                     => 'nullable|string|max:30',
            'email'                        => 'nullable|email|max:255',
            'valor_total'                  => 'required|numeric|min:0',
            'parcelas_desejadas'           => 'required|integer|min:1|max:120',
            'renda_declarada'              => 'nullable|numeric|min:0',
            'observacoes'                  => 'nullable|string|max:5000',
            'autorizou_consultas_externas' => 'nullable|boolean',
        ]);

        $validated['cpf_cnpj'] = preg_replace('/\D/', '', $validated['cpf_cnpj']);
        $validated['autorizou_consultas_externas'] = $request->boolean('autorizou_consultas_externas');
        $validated['user_id'] = Auth::id();
        $validated['status'] = 'rascunho';

        $consulta = SiricConsulta::create($validated);

        return redirect()
            ->route('siric.show', $consulta->id)
            ->with('success', 'Consulta criada. Clique em "Coletar Dados" para prosseguir.');
    }

    /**
     * Exibe detalhe de uma consulta.
     */
    public function show(int $id)
    {
        $consulta = SiricConsulta::findOrFail($id);
        return view('siric.show', compact('consulta'));
    }

    /**
     * Excluir consulta.
     */
    public function destroy(int $id)
    {
        $consulta = SiricConsulta::findOrFail($id);
        $consulta->delete();

        return redirect()
            ->route('siric.index')
            ->with('success', 'Consulta excluída.');
    }

    /**
     * Coletar dados internos do BD.
     */
    public function coletarDados(int $id)
    {
        $consulta = SiricConsulta::findOrFail($id);

        try {
            $snapshot = $this->service->coletarDadosInternos($consulta);

            return redirect()
                ->route('siric.show', $consulta->id)
                ->with('success', sprintf(
                    'Dados internos coletados. %d cliente(s), %d contas a receber, %d processos.',
                    $snapshot['clientes_encontrados'] ?? 0,
                    $snapshot['contas_receber']['total_registros'] ?? 0,
                    ($snapshot['processos']['total_ativos'] ?? 0) + ($snapshot['processos']['total_inativos'] ?? 0)
                ));
        } catch (\Throwable $e) {
            Log::error('SIRIC coletarDados: ' . $e->getMessage());
            return redirect()
                ->route('siric.show', $consulta->id)
                ->with('error', 'Erro ao coletar dados: ' . $e->getMessage());
        }
    }

    /**
     * AÇÃO PRINCIPAL: Rodar Análise de Crédito (v2)
     *
     * Fluxo v2:
     * 1. Coleta dados internos (se ainda não coletou)
     * 2. Gate Decision DETERMINÍSTICO (PHP, não IA)
     * 3. Se gate >= 50 E autorizado E sem bypass → consulta Serasa
     * 4. Relatório Final via IA (único estágio de IA)
     * 5. Salva resultado + rating + score
     */
    public function analisarIA(int $id)
    {
        $consulta = SiricConsulta::findOrFail($id);

        // Validar status
        if (in_array($consulta->status, ['analisado', 'decidido'])) {
            return redirect()
                ->route('siric.show', $consulta->id)
                ->with('error', 'Esta consulta já foi analisada.');
        }

        try {
            $consulta->update(['status' => 'analisando']);

            // ── Passo 1: Coleta interna (se não feita) ──
            if (empty($consulta->snapshot_interno)) {
                $this->service->coletarDadosInternos($consulta);
                $consulta->refresh();
            }

            $snapshot = $consulta->snapshot_interno ?? [];
            $dadosFormulario = [
                'cpf_cnpj'                     => $consulta->cpf_cnpj,
                'nome'                         => $consulta->nome,
                'telefone'                     => $consulta->telefone,
                'email'                        => $consulta->email,
                'valor_total'                  => (float) ($consulta->valor_total ?? 0),
                'parcelas_desejadas'           => (int) ($consulta->parcelas_desejadas ?? 1),
                'renda_declarada'              => $consulta->renda_declarada ? (float) $consulta->renda_declarada : null,
                'observacoes'                  => $consulta->observacoes,
                'autorizou_consultas_externas' => (bool) $consulta->autorizou_consultas_externas,
            ];

            // ── Passo 2: Gate Decision DETERMINÍSTICO (v2) ──
            $gateResult = $this->service->calcularGateScore($consulta, $snapshot);

            Log::info('SIRIC Gate v2 (determinístico)', [
                'consulta_id' => $id,
                'gate_score' => $gateResult['gate_score'],
                'need_serasa' => $gateResult['need_serasa'],
                'serasa_bypass' => $gateResult['serasa_bypass'],
                'bom_historico' => $gateResult['bom_historico'],
            ]);

            $dadosSerasa = null;

            // ── Passo 3: Consulta Serasa condicionada (v2) ──
            if ($gateResult['need_serasa']) {
                try {
                    $asaas = app(SiricAsaasService::class);
                    $serasaResult = $asaas->solicitarRelatorio($consulta->cpf_cnpj);

                    if ($serasaResult['success'] ?? false) {
                        $dadosSerasa = $serasaResult['data'] ?? null;
                        $gateResult['serasa_consultado'] = true;

                        // v2: Persistir evidência Serasa
                        $this->salvarEvidenciaSerasa($consulta, $dadosSerasa);

                        Log::info('SIRIC Serasa consultado + evidência salva', ['consulta_id' => $id]);
                    } else {
                        $gateResult['serasa_consultado'] = false;
                        $gateResult['serasa_erro'] = $serasaResult['error'] ?? 'Falha na API Asaas';
                        Log::warning('SIRIC Serasa falhou', ['consulta_id' => $id, 'error' => $serasaResult['error'] ?? '']);
                    }
                } catch (\Throwable $e) {
                    $gateResult['serasa_consultado'] = false;
                    $gateResult['serasa_erro'] = $e->getMessage();
                    Log::warning('SIRIC Serasa exception', ['consulta_id' => $id, 'error' => $e->getMessage()]);
                }
            } elseif ($gateResult['serasa_bypass']) {
                $gateResult['serasa_consultado'] = false;
                $gateResult['serasa_motivo'] = 'Bypass v2: cliente com histórico forte no escritório';
            } elseif (!$consulta->autorizou_consultas_externas) {
                $gateResult['serasa_consultado'] = false;
                $gateResult['serasa_motivo'] = 'Cliente não autorizou consultas externas';
            } else {
                $gateResult['serasa_consultado'] = false;
                $gateResult['serasa_motivo'] = "Gate score {$gateResult['gate_score']} abaixo do threshold (50)";
            }

            // ── Passo 4: Relatório Final via IA (v2 — único estágio de IA) ──
            $openAI = app(SiricOpenAIService::class);
            $relatorio = $openAI->gerarRelatorioFinal($dadosFormulario, $snapshot, $gateResult, $dadosSerasa);

            if (!$relatorio['success']) {
                $consulta->update([
                    'status'     => 'erro',
                    'actions_ia' => ['gate_decision' => $gateResult, 'erro_relatorio' => $relatorio['error'] ?? ''],
                ]);
                return redirect()
                    ->route('siric.show', $consulta->id)
                    ->with('error', 'Erro no relatório: ' . ($relatorio['error'] ?? 'desconhecido'));
            }

            $rel = $relatorio['relatorio'] ?? [];

            // ── Passo 5: Salvar tudo ──
            $ratingLabels = [
                'A' => 'Excelente', 'B' => 'Bom', 'C' => 'Regular',
                'D' => 'Ruim', 'E' => 'Crítico',
            ];
            $ratingLabel = $ratingLabels[$rel['rating'] ?? ''] ?? 'Indefinido';

            $consulta->update([
                'status'                => 'analisado',
                'rating'                => $rel['rating'] ?? null,
                'score'                 => (int) ($rel['score_final'] ?? 0),
                'recomendacao'          => $rel['recomendacao'] ?? null,
                'comprometimento_max'   => isset($rel['comprometimento_max_sugerido']) && is_numeric($rel['comprometimento_max_sugerido']) ? (float) $rel['comprometimento_max_sugerido'] : null,
                'parcelas_max_sugeridas'=> $rel['parcelas_max_sugeridas'] ?? null,
                'motivos_ia'            => array_merge(
                    $rel['fatores_positivos'] ?? [],
                    $rel['fatores_negativos'] ?? []
                ),
                'dados_faltantes_ia'    => $gateResult['dados_faltantes'] ?? null,
                'actions_ia'            => [
                    'gate_decision' => $gateResult,
                    'relatorio'     => $rel,
                    'model_used'    => $relatorio['model_used'] ?? null,
                    'versao'        => 'v2',
                    'timestamp'     => now()->toISOString(),
                ],
            ]);

            return redirect()
                ->route('siric.show', $consulta->id)
                ->with('success', "Análise v2 concluída! Rating: " . ($rel['rating'] ?? '?') . " ({$ratingLabel}) — Score: " . ($rel['score_final'] ?? 0));

        } catch (\Throwable $e) {
            Log::error('SIRIC analisarIA exception: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            $consulta->update(['status' => 'erro']);
            return redirect()
                ->route('siric.show', $consulta->id)
                ->with('error', 'Erro na análise: ' . $e->getMessage());
        }
    }

    /**
     * v2: Salva evidência Serasa como SiricEvidencia.
     * Inclui dados estruturados extraídos do PDF.
     */
    private function salvarEvidenciaSerasa(SiricConsulta $consulta, ?array $dadosSerasa): void
    {
        if (!$dadosSerasa) return;

        // Limpar evidências Serasa anteriores (permite "reanalisar")
        $consulta->evidencias()->where('fonte', 'asaas_serasa')->delete();

        $estruturados = $dadosSerasa['dados_estruturados'] ?? [];
        $score = $estruturados['score'] ?? null;
        $protestos = $estruturados['protestos']['qtd'] ?? 0;
        $pendInternas = $estruturados['pendencias_internas']['qtd'] ?? 0;
        $pendFinanceiras = $estruturados['pendencias_financeiras']['qtd'] ?? 0;
        $totalPendencias = $pendInternas + $pendFinanceiras + $protestos;

        // Determinar impacto
        $impacto = 'neutro';
        if ($score !== null) {
            if ($score >= 600 && $totalPendencias === 0) {
                $impacto = 'positivo';
            } elseif ($score < 400 || $totalPendencias >= 3) {
                $impacto = 'negativo';
            } elseif ($score < 300 || $protestos > 0) {
                $impacto = 'risco';
            }
        }

        // Resumo legível
        $partes = [];
        if ($score !== null) $partes[] = "Score Serasa: {$score}";
        if ($pendInternas > 0) $partes[] = "Pendências internas: {$pendInternas}";
        if ($pendFinanceiras > 0) $partes[] = "Pendências financeiras: {$pendFinanceiras}";
        if ($protestos > 0) $partes[] = "Protestos: {$protestos}";
        if (empty($partes)) $partes[] = 'Consulta Serasa realizada (sem dados estruturados extraídos)';

        SiricEvidencia::create([
            'consulta_id' => $consulta->id,
            'fonte'       => 'asaas_serasa',
            'tipo'        => 'serasa_report',
            'payload'     => [
                'report_id'          => $dadosSerasa['id'] ?? null,
                'date_created'       => $dadosSerasa['dateCreated'] ?? null,
                'dados_estruturados' => $estruturados,
                'download_url'       => $dadosSerasa['downloadUrl'] ?? null,
            ],
            'impacto'     => $impacto,
            'resumo'      => implode('. ', $partes) . '.',
        ]);
    }

    /**
     * Salvar decisão humana final.
     */
    public function salvarDecisao(Request $request, int $id)
    {
        $consulta = SiricConsulta::findOrFail($id);

        $validated = $request->validate([
            'decisao_humana' => 'required|in:aprovado,negado,condicionado',
            'nota_decisao'   => 'nullable|string|max:2000',
        ]);

        $consulta->update([
            'decisao_humana'  => $validated['decisao_humana'],
            'nota_decisao'    => $validated['nota_decisao'] ?? null,
            'decisao_user_id' => Auth::id(),
            'status'          => 'decidido',
        ]);

        return redirect()
            ->route('siric.show', $consulta->id)
            ->with('success', 'Decisão registrada com sucesso.');
    }
}
