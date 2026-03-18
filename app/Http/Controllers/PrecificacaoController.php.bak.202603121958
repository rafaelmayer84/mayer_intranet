<?php

namespace App\Http\Controllers;

use App\Models\PricingCalibration;
use App\Models\PricingProposal;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmAccount;
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
            'tipo_acao' => 'nullable|string|max:200',
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
        if ($request->filled('tipo_acao')) {
            $dadosProponente['demanda']['tipo_acao'] = $request->tipo_acao;
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
            'tipo_acao' => $request->tipo_acao,
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

        // === INTEGRAÇÃO SIPEX → CRM: criar oportunidade automaticamente ===
        try {
            $valorEquilibrada = $resultado['proposta_equilibrada']['valor_honorarios'] ?? null;
            $nomeProponente = $dadosProponente['proponente']['nome'] ?? 'Proposta SIPEX';
            $documento = $dadosProponente['proponente']['documento'] ?? null;
            $telefone = $dadosProponente['proponente']['telefone'] ?? null;

            // Tentar localizar account no CRM por documento ou telefone
            $accountId = null;
            if ($documento) {
                $digits = preg_replace('/\D/', '', $documento);
                $account = CrmAccount::where('doc_digits', $digits)->first();
                if ($account) $accountId = $account->id;
            }
            if (!$accountId && $telefone) {
                $phoneClean = preg_replace('/\D/', '', $telefone);
                if (strlen($phoneClean) >= 10) {
                    $account = CrmAccount::where('phone_e164', 'like', "%{$phoneClean}%")->first();
                    if ($account) $accountId = $account->id;
                }
            }
            // Se não encontrou, criar prospect
            if (!$accountId) {
                $newAccount = CrmAccount::create([
                    'name' => $nomeProponente,
                    'kind' => 'prospect',
                    'doc_digits' => $documento ? preg_replace('/\D/', '', $documento) : null,
                    'phone_e164' => $telefone ? preg_replace('/\D/', '', $telefone) : null,
                    'owner_user_id' => Auth::id(),
                ]);
                $accountId = $newAccount->id;
            }

            $opp = CrmOpportunity::create([
                'account_id' => $accountId,
                'stage_id' => 3, // Proposta
                'type' => 'aquisicao',
                'title' => ($areaDireito ?? 'Demanda') . ' - ' . $nomeProponente,
                'area' => $areaDireito,
                'tipo_demanda' => $areaDireito,
                'source' => 'sipex',
                'value_estimated' => $valorEquilibrada,
                'owner_user_id' => Auth::id(),
                'status' => 'open',
                'sipex_proposal_id' => $proposal->id,
            ]);

            $proposal->update(['crm_opportunity_id' => $opp->id]);
            Log::info('SIPEX->CRM: Oportunidade criada', ['opp_id' => $opp->id, 'proposal_id' => $proposal->id]);
        } catch (\Exception $e) {
            Log::warning('SIPEX->CRM: Falha ao criar oportunidade', ['erro' => $e->getMessage()]);
            // Não bloqueia o retorno da proposta
        }

        return response()->json([
            'success' => true,
            'proposal_id' => $proposal->id,
            'crm_opportunity_id' => $opp->id ?? null,
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
        $query = PricingProposal::query();
        if (Auth::user()->role !== 'admin') {
            $query->where('user_id', Auth::id());
        }
        $proposta = $query->findOrFail($id);
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


    /**
     * Excluir proposta (admin/sócio only)
     */
    public function excluir(int $id)
    {
        $userRole = Auth::user()->role ?? '';
        if (!in_array($userRole, ['admin', 'socio'])) {
            return response()->json(['erro' => 'Acesso restrito à administração'], 403);
        }

        $proposal = PricingProposal::findOrFail($id);
        $proposal->delete();

        Log::info('Precificação: Proposta excluída', ['id' => $id, 'user' => Auth::id()]);

        return response()->json(['success' => true]);
    }

    // ===================== CALIBRAÇÃO (ADMIN ONLY) =====================

    /**
     * Tela de calibração estratégica (admin/sócios)
     */
    /**
     * Gera texto persuasivo da proposta via IA e salva no registro.
     */
    public function gerarPropostaCliente(Request $request, int $id)
    {
        $proposal = PricingProposal::where('user_id', Auth::id())->findOrFail($id);

        if (!$proposal->proposta_escolhida || $proposal->proposta_escolhida === 'nenhuma') {
            return response()->json(['error' => 'Escolha uma proposta antes de gerar o documento.'], 422);
        }

        try {
            $textoGerado = $this->ai->gerarTextoPropostaCliente($proposal, $proposal->proposta_escolhida);

            if (isset($textoGerado['error'])) {
                return response()->json(['error' => $textoGerado['error']], 500);
            }

            $proposal->update(['texto_proposta_cliente' => json_encode($textoGerado, JSON_UNESCAPED_UNICODE)]);

            return response()->json([
                'success' => true,
                'redirect' => route('precificacao.proposta.print', $id),
            ]);
        } catch (\Exception $e) {
            \Log::error('SIPEX gerarPropostaCliente erro', ['id' => $id, 'msg' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao gerar proposta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Renderiza a proposta de honorários para impressão/PDF.
     */
    public function imprimirProposta(int $id)
    {
        $proposal = PricingProposal::where('user_id', Auth::id())->findOrFail($id);

        if (!$proposal->texto_proposta_cliente) {
            return redirect()->route('precificacao.show', $id)
                ->with('error', 'Gere a proposta para o cliente antes de imprimir.');
        }

        $meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                   7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
        $hoje = now();
        $dataFormatada = "Itajaí, {$hoje->day} de {$meses[(int)$hoje->month]} de {$hoje->year}.";

        return view('precificacao.proposta-print', [
            'proposta' => $proposal,
            'dataFormatada' => $dataFormatada,
        ]);
    }

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
