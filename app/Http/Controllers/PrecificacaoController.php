<?php

namespace App\Http\Controllers;

use App\Models\PricingCalibration;
use App\Models\PricingProposal;
use App\Services\PricingDataCollectorService;
use App\Services\PricingAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PrecificacaoController extends Controller
{
    private PricingDataCollectorService $collector;
    private PricingAIService $ai;

    public function __construct(PricingDataCollectorService $collector, PricingAIService $ai)
    {
        $this->collector = $collector;
        $this->ai = $ai;
    }

    /**
     * Tela principal - formulário de geração de proposta
     */
    public function index()
    {
        $propostas = PricingProposal::where('user_id', Auth::id())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('precificacao.index', compact('propostas'));
    }

    /**
     * API: Buscar proponente por nome/CPF (autocomplete)
     */
    public function buscar(Request $request)
    {
        $busca = $request->input('q', '');
        if (strlen($busca) < 3) {
            return response()->json([]);
        }

        $resultados = $this->collector->buscarProponente($busca);
        return response()->json($resultados);
    }

    /**
     * API: Carregar dados completos de um lead
     */
    public function carregarLead(int $id)
    {
        $dados = $this->collector->coletarDadosLead($id);
        return response()->json($dados);
    }

    /**
     * API: Carregar dados completos de um cliente
     */
    public function carregarCliente(int $id)
    {
        $dados = $this->collector->coletarDadosCliente($id);
        return response()->json($dados);
    }

    /**
     * Gerar propostas via IA
     */
    public function gerar(Request $request)
    {
        $request->validate([
            'tipo_proponente' => 'required|in:lead,cliente',
            'proponente_id' => 'required|integer',
            'area_direito' => 'nullable|string|max:100',
            'valor_causa' => 'nullable|numeric',
            'valor_economico' => 'nullable|numeric',
            'descricao_demanda' => 'nullable|string|max:5000',
            'contexto_adicional' => 'nullable|string|max:2000',
        ]);

        // 1. Coletar dados do proponente
        if ($request->tipo_proponente === 'lead') {
            $dadosProponente = $this->collector->coletarDadosLead($request->proponente_id);
        } else {
            $dadosProponente = $this->collector->coletarDadosCliente($request->proponente_id);
        }

        if (isset($dadosProponente['erro'])) {
            return response()->json(['erro' => $dadosProponente['erro']], 404);
        }

        // Sobrescrever/complementar com inputs do formulário
        if ($request->filled('area_direito')) {
            $dadosProponente['demanda']['area_interesse'] = $request->area_direito;
        }
        if ($request->filled('valor_causa')) {
            $dadosProponente['demanda']['valor_causa'] = $request->valor_causa;
        }
        if ($request->filled('valor_economico')) {
            $dadosProponente['demanda']['valor_economico'] = $request->valor_economico;
        }
        if ($request->filled('descricao_demanda')) {
            $dadosProponente['demanda']['descricao_completa'] = $request->descricao_demanda;
        }

        $areaDireito = $request->area_direito ?? ($dadosProponente['demanda']['area_interesse'] ?? null);

        // 2. Montar pacote completo
        $pacote = $this->collector->montarPacoteIA(
            $dadosProponente,
            $areaDireito,
            $request->contexto_adicional
        );

        // 3. Chamar IA
        $resultado = $this->ai->gerarPropostas($pacote);

        if (isset($resultado['erro'])) {
            return response()->json(['erro' => $resultado['erro']], 500);
        }

        // 4. Salvar proposta no banco
        $proposal = PricingProposal::create([
            'user_id' => Auth::id(),
            'lead_id' => $request->tipo_proponente === 'lead' ? $request->proponente_id : null,
            'cliente_id' => $dadosProponente['cliente_id'] ?? ($request->tipo_proponente === 'cliente' ? $request->proponente_id : null),
            'nome_proponente' => $dadosProponente['proponente']['nome'] ?? null,
            'documento_proponente' => $dadosProponente['proponente']['documento'] ?? null,
            'tipo_pessoa' => $dadosProponente['proponente']['tipo_pessoa'] ?? null,
            'area_direito' => $areaDireito,
            'descricao_demanda' => $request->descricao_demanda ?? ($dadosProponente['demanda']['resumo_demanda'] ?? null),
            'valor_causa' => $request->valor_causa,
            'valor_economico' => $request->valor_economico,
            'contexto_adicional' => $request->contexto_adicional,
            'siric_score' => $dadosProponente['siric']['score'] ?? null,
            'siric_rating' => $dadosProponente['siric']['rating'] ?? null,
            'siric_limite' => $dadosProponente['siric']['limite_sugerido'] ?? null,
            'siric_recomendacao' => $dadosProponente['siric']['recomendacao'] ?? null,
            'calibracao_snapshot' => PricingCalibration::getSnapshot(),
            'historico_agregado' => $pacote['historico_escritorio'],
            'proposta_rapida' => $resultado['proposta_rapida'],
            'proposta_equilibrada' => $resultado['proposta_equilibrada'],
            'proposta_premium' => $resultado['proposta_premium'],
            'recomendacao_ia' => $resultado['recomendacao'],
            'justificativa_ia' => $resultado['justificativa_recomendacao'] ?? null,
            'status' => 'gerada',
        ]);

        Log::info('Precificação: Proposta gerada', ['id' => $proposal->id, 'user' => Auth::id()]);

        return response()->json([
            'success' => true,
            'proposal_id' => $proposal->id,
            'resultado' => $resultado,
        ]);
    }

    /**
     * Registrar escolha do advogado
     */
    public function escolher(Request $request, int $id)
    {
        $request->validate([
            'proposta_escolhida' => 'required|in:rapida,equilibrada,premium,nenhuma',
            'valor_final' => 'nullable|numeric',
            'observacao' => 'nullable|string|max:2000',
        ]);

        $proposal = PricingProposal::where('user_id', Auth::id())->findOrFail($id);

        $proposal->update([
            'proposta_escolhida' => $request->proposta_escolhida,
            'valor_final' => $request->valor_final,
            'observacao_advogado' => $request->observacao,
            'status' => 'enviada',
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Ver detalhes de uma proposta
     */
    public function show(int $id)
    {
        $proposta = PricingProposal::where('user_id', Auth::id())->findOrFail($id);
        return view('precificacao.show', compact('proposta'));
    }

    /**
     * Histórico de propostas
     */
    public function historico(Request $request)
    {
        $query = PricingProposal::query();

        // Admin vê tudo, advogado vê só as suas
        if (!in_array(Auth::user()->role ?? '', ['admin', 'socio'])) {
            $query->where('user_id', Auth::id());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $propostas = $query->orderByDesc('created_at')->paginate(20);
        return view('precificacao.historico', compact('propostas'));
    }

    // ===================== CALIBRAÇÃO (ADMIN ONLY) =====================

    /**
     * Tela de calibração estratégica (admin/sócios)
     */
    public function calibracao()
    {
        $userRole = Auth::user()->role ?? '';
        if (!in_array($userRole, ['admin', 'socio'])) {
            abort(403, 'Acesso restrito à administração');
        }

        $eixos = PricingCalibration::orderBy('id')->get();
        return view('precificacao.calibracao', compact('eixos'));
    }

    /**
     * Salvar calibração
     */
    public function salvarCalibracao(Request $request)
    {
        $userRole = Auth::user()->role ?? '';
        if (!in_array($userRole, ['admin', 'socio'])) {
            abort(403);
        }

        $request->validate([
            'eixos' => 'required|array',
            'eixos.*' => 'required|integer|min:0|max:100',
        ]);

        foreach ($request->eixos as $eixo => $valor) {
            PricingCalibration::where('eixo', $eixo)->update([
                'valor' => $valor,
                'updated_by' => Auth::id(),
            ]);
        }

        return response()->json(['success' => true, 'message' => 'Calibração salva com sucesso']);
    }
}
