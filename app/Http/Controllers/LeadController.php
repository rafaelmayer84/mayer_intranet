<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadMessage;
use App\Services\LeadProcessingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LeadController extends Controller
{
    public function __construct(
        protected LeadProcessingService $leadService
    ) {
    }

    /**
     * Dashboard principal da Central de Leads — Marketing Jurídico
     */
    public function index(Request $request): View
    {
        // Filtros
        $filtroArea = $request->get('area', 'todos');
        $filtroCidade = $request->get('cidade', 'todos');
        $filtroPeriodo = $request->get('periodo', 'todos');
        $filtroIntencao = $request->get('intencao', 'todos');
        $filtroOrigem = $request->get('origem', 'todos');
        $filtroPotencial = $request->get('potencial', 'todos');

        // Query base com filtros
        $query = Lead::query()
            ->area($filtroArea)
            ->cidade($filtroCidade)
            ->periodo($filtroPeriodo)
            ->intencao($filtroIntencao);

        // Filtros adicionais
        if ($filtroOrigem !== 'todos') {
            $query->where('origem_canal', $filtroOrigem);
        }
        if ($filtroPotencial !== 'todos') {
            $query->where('potencial_honorarios', $filtroPotencial);
        }

        // ========== ESTATÍSTICAS GERAIS ==========
        $totalLeads = $query->count();
        $leadsHoje = Lead::whereDate('data_entrada', today())->count();
        $leadsSemana = Lead::where('data_entrada', '>=', now()->subDays(7))->count();
        $leadsMes = Lead::where('data_entrada', '>=', now()->subDays(30))->count();

        // Taxas
        $leadsSim = (clone $query)->where('intencao_contratar', 'sim')->count();
        $leadsTalvez = (clone $query)->where('intencao_contratar', 'talvez')->count();
        $leadsNao = (clone $query)->where('intencao_contratar', 'não')->count();
        $taxaConversao = $totalLeads > 0 ? round(($leadsSim / $totalLeads) * 100, 1) : 0;
        $taxaInteresse = $totalLeads > 0 ? round((($leadsSim + $leadsTalvez) / $totalLeads) * 100, 1) : 0;

        // Leads com erro
        $leadsComErro = Lead::whereNotNull('erro_processamento')->count();

        // ========== DADOS PARA GRÁFICOS ==========

        // Por Área Jurídica
        $dadosArea = DB::table('leads')
            ->select('area_interesse', DB::raw('COUNT(*) as total'))
            ->whereNotNull('area_interesse')
            ->where('area_interesse', '!=', '')
            ->groupBy('area_interesse')
            ->orderByDesc('total')
            ->get();

        // Por Cidade
        $dadosCidade = DB::table('leads')
            ->select('cidade', DB::raw('COUNT(*) as total'))
            ->whereNotNull('cidade')
            ->where('cidade', '!=', '')
            ->where('cidade', '!=', 'não informado')
            ->groupBy('cidade')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Funil de Intenção
        $dadosIntencao = DB::table('leads')
            ->select('intencao_contratar', DB::raw('COUNT(*) as total'))
            ->whereNotNull('intencao_contratar')
            ->where('intencao_contratar', '!=', '')
            ->groupBy('intencao_contratar')
            ->get();

        // Por Origem/Canal
        $dadosOrigem = DB::table('leads')
            ->select('origem_canal', DB::raw('COUNT(*) as total'))
            ->whereNotNull('origem_canal')
            ->where('origem_canal', '!=', '')
            ->groupBy('origem_canal')
            ->orderByDesc('total')
            ->get();

        // Potencial de Honorários
        $dadosPotencial = DB::table('leads')
            ->select('potencial_honorarios', DB::raw('COUNT(*) as total'))
            ->whereNotNull('potencial_honorarios')
            ->where('potencial_honorarios', '!=', '')
            ->groupBy('potencial_honorarios')
            ->get();

        // Urgência
        $dadosUrgencia = DB::table('leads')
            ->select('urgencia', DB::raw('COUNT(*) as total'))
            ->whereNotNull('urgencia')
            ->where('urgencia', '!=', '')
            ->groupBy('urgencia')
            ->get();

        // Por Sub-área (top 10)
        $dadosSubArea = DB::table('leads')
            ->select('sub_area', DB::raw('COUNT(*) as total'))
            ->whereNotNull('sub_area')
            ->where('sub_area', '!=', '')
            ->groupBy('sub_area')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        // Gatilho Emocional
        $dadosGatilho = DB::table('leads')
            ->select('gatilho_emocional', DB::raw('COUNT(*) as total'))
            ->whereNotNull('gatilho_emocional')
            ->where('gatilho_emocional', '!=', '')
            ->groupBy('gatilho_emocional')
            ->orderByDesc('total')
            ->get();

        // Palavras-chave mais frequentes
        $todasPalavras = [];
        $palavrasRaw = Lead::whereNotNull('palavras_chave')
            ->where('palavras_chave', '!=', '')
            ->pluck('palavras_chave');

        foreach ($palavrasRaw as $palavrasStr) {
            $palavras = explode(',', $palavrasStr);
            foreach ($palavras as $palavra) {
                $palavra = mb_strtolower(trim($palavra));
                if (!empty($palavra) && mb_strlen($palavra) > 2) {
                    $todasPalavras[] = $palavra;
                }
            }
        }

        $contagemPalavras = array_count_values($todasPalavras);
        arsort($contagemPalavras);
        $topPalavras = array_slice($contagemPalavras, 0, 25, true);

        // Timeline (últimos 30 dias)
        $dadosTimeline = DB::table('leads')
            ->select(DB::raw('DATE(data_entrada) as data'), DB::raw('COUNT(*) as total'))
            ->where('data_entrada', '>=', now()->subDays(30))
            ->groupBy(DB::raw('DATE(data_entrada)'))
            ->orderBy('data')
            ->get();

        // Perfil Socioeconômico
        $dadosPerfil = DB::table('leads')
            ->select('perfil_socioeconomico', DB::raw('COUNT(*) as total'))
            ->whereNotNull('perfil_socioeconomico')
            ->where('perfil_socioeconomico', '!=', '')
            ->groupBy('perfil_socioeconomico')
            ->get();

        // Leads recentes (últimos 20)
        $leads = (clone $query)->orderByDesc('data_entrada')->paginate(25)->appends($request->query());

        // Opções para filtros
        $areas = Lead::whereNotNull('area_interesse')
            ->where('area_interesse', '!=', '')
            ->distinct()
            ->orderBy('area_interesse')
            ->pluck('area_interesse');

        $cidades = Lead::whereNotNull('cidade')
            ->where('cidade', '!=', '')
            ->where('cidade', '!=', 'não informado')
            ->distinct()
            ->orderBy('cidade')
            ->pluck('cidade');

        $origens = Lead::whereNotNull('origem_canal')
            ->where('origem_canal', '!=', '')
            ->distinct()
            ->pluck('origem_canal');

        return view('leads.index', compact(
            'totalLeads', 'leadsHoje', 'leadsSemana', 'leadsMes',
            'taxaConversao', 'taxaInteresse', 'leadsComErro',
            'leadsSim', 'leadsTalvez', 'leadsNao',
            'dadosArea', 'dadosCidade', 'topPalavras', 'dadosTimeline',
            'dadosIntencao', 'dadosOrigem', 'dadosPotencial', 'dadosUrgencia',
            'dadosSubArea', 'dadosGatilho', 'dadosPerfil',
            'leads', 'areas', 'cidades', 'origens',
            'filtroArea', 'filtroCidade', 'filtroPeriodo',
            'filtroIntencao', 'filtroOrigem', 'filtroPotencial'
        ));
    }

    /**
     * Detalhes de um lead específico
     */
    public function show(Lead $lead): View
    {
        $lead->load('messages');
        return view('leads.show', compact('lead'));
    }

    /**
     * Webhook do SendPulse
     */
    public function webhook(Request $request): JsonResponse
    {
        // ---- INÍCIO BLOCO NEXO v1.2 ----
        // Detectar se é incoming_message do SendPulse para o Nexo
        $rawPayload = $request->all();
        $event = is_array($rawPayload) && isset($rawPayload[0]) ? $rawPayload[0] : $rawPayload;

        if (data_get($event, 'title') === 'incoming_message') {
            // Validar por IP do SendPulse (SendPulse nao envia X-Webhook-Secret)
            $allowedIps = ["188.40.60.215", "188.40.60.216", "188.40.60.217"];
            if (!in_array($request->ip(), $allowedIps)) {
                \Log::warning("Nexo webhook: IP nao autorizado", ["ip" => $request->ip()]);
                return response()->json(["error" => "Forbidden"], 403);
            }

            try {
                $syncService = app(\App\Services\NexoConversationSyncService::class);
                $syncService->syncConversationFromWebhook($rawPayload);
            } catch (\Throwable $e) {
                \Log::error('Nexo webhook error', [
                    'error' => $e->getMessage(),
                    'line'  => $e->getLine(),
                ]);
            }
            // NÃO retornar aqui — deixar o fluxo de leads continuar
        }
        // ---- FIM BLOCO NEXO v1.2 ----

        // Log de metadados (sem payload por LGPD)
        Log::info('Webhook SendPulse recebido', [
            'ip'             => $request->ip(),
            'content_length' => $request->header('Content-Length'),
            'has_json'       => $request->isJson(),
        ]);

        try {
            $webhookData = $rawPayload ?? $request->all();
            if (empty($webhookData)) {
                return response()->json(['error' => 'Empty payload'], 400);
            }

            $lead = $this->leadService->processLead($webhookData);
            if (!$lead) {
                return response()->json(['error' => 'Failed to process lead'], 500);
            }

            return response()->json([
                'success' => true,
                'lead_id' => $lead->id,
                'message' => 'Lead processado com sucesso'
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Erro no webhook SendPulse', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Erro ao processar webhook'
            ], 500);
        }
    }

    /**
     * Atualizar status do lead
     */
    public function updateStatus(Request $request, Lead $lead): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:novo,contatado,qualificado,convertido,descartado'
        ]);

        $lead->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Status atualizado com sucesso'
        ]);
    }

    /**
     * Reprocessar lead
     */
    public function reprocess(Lead $lead): JsonResponse
    {
        $success = $this->leadService->reprocessLead($lead);

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Lead reprocessado com sucesso',
                'lead' => $lead->fresh()
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erro ao reprocessar lead',
            'error' => $lead->erro_processamento
        ], 500);
    }

    /**
     * Deletar lead
     */
    public function destroy(Lead $lead): JsonResponse
    {
        $leadId = $lead->id;
        $leadNome = $lead->nome;

        $lead->messages()->delete();
        $lead->delete();

        Log::info('Lead deletado', ['lead_id' => $leadId, 'nome' => $leadNome]);

        return response()->json([
            'success' => true,
            'message' => 'Lead deletado com sucesso'
        ]);
    }

    /**
     * Estatísticas para API
     */
    public function stats(): JsonResponse
    {
        $stats = [
            'total_leads' => Lead::count(),
            'hoje' => Lead::whereDate('data_entrada', today())->count(),
            'semana' => Lead::where('data_entrada', '>=', now()->subDays(7))->count(),
            'mes' => Lead::where('data_entrada', '>=', now()->subDays(30))->count(),
            'por_status' => Lead::select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'por_intencao' => Lead::select('intencao_contratar', DB::raw('COUNT(*) as total'))
                ->groupBy('intencao_contratar')
                ->pluck('total', 'intencao_contratar'),
            'por_origem' => Lead::select('origem_canal', DB::raw('COUNT(*) as total'))
                ->whereNotNull('origem_canal')
                ->groupBy('origem_canal')
                ->pluck('total', 'origem_canal'),
            'por_potencial' => Lead::select('potencial_honorarios', DB::raw('COUNT(*) as total'))
                ->whereNotNull('potencial_honorarios')
                ->groupBy('potencial_honorarios')
                ->pluck('total', 'potencial_honorarios'),
            'com_erro' => Lead::whereNotNull('erro_processamento')->count()
        ];

        return response()->json($stats);
    }

    /**
     * Exportar leads para CSV
     * GET /leads/export?tipo=completo|palavras_chave|gclid
     */
    public function export(Request $request)
    {
        $tipo = $request->get('tipo', 'completo');
        $filename = 'leads_' . $tipo . '_' . date('Y-m-d') . '.csv';

        $leads = \App\Models\Lead::query()
            ->when($request->filled('area'), fn($q) => $q->where('area_interesse', $request->area))
            ->when($request->filled('intencao'), fn($q) => $q->where('intencao_contratar', $request->intencao))
            ->when($request->filled('cidade'), fn($q) => $q->where('cidade', $request->cidade))
            ->orderBy('created_at', 'desc')
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($leads, $tipo) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM para Excel

            switch ($tipo) {
                case 'palavras_chave':
                    fputcsv($file, ['Palavra-chave', 'Frequência', 'Área Predominante'], ';');
                    $keywords = [];
                    $blacklist = ['lead', 'leads', 'migrado', 'legacy', 'sistema', 'anterior', 'ads', 'teste'];
                    foreach ($leads as $lead) {
                        if (empty($lead->palavras_chave)) continue;
                        $words = array_map('trim', explode(',', $lead->palavras_chave));
                        foreach ($words as $word) {
                            $normalized = mb_strtolower(trim($word));
                            if (mb_strlen($normalized) <= 2 || in_array($normalized, $blacklist, true)) continue;
                            if (!isset($keywords[$normalized])) {
                                $keywords[$normalized] = ['freq' => 0, 'areas' => []];
                            }
                            $keywords[$normalized]['freq']++;
                            if ($lead->area_interesse) {
                                $keywords[$normalized]['areas'][] = $lead->area_interesse;
                            }
                        }
                    }
                    uasort($keywords, fn($a, $b) => $b['freq'] <=> $a['freq']);
                    foreach ($keywords as $word => $data) {
                        $areaCounts = array_count_values($data['areas']);
                        arsort($areaCounts);
                        $topArea = !empty($areaCounts) ? array_key_first($areaCounts) : '-';
                        fputcsv($file, [$word, $data['freq'], $topArea], ';');
                    }
                    break;

                case 'gclid':
                    fputcsv($file, ['Nome', 'Telefone', 'GCLID', 'Área', 'Cidade', 'Intenção', 'Data'], ';');
                    foreach ($leads->where('gclid', '!=', null)->where('gclid', '!=', '') as $lead) {
                        fputcsv($file, [
                            $lead->nome, $lead->telefone, $lead->gclid,
                            $lead->area_interesse, $lead->cidade,
                            $lead->intencao_contratar, $lead->data_entrada ?? $lead->created_at
                        ], ';');
                    }
                    break;

                default: // completo
                    fputcsv($file, [
                        'ID', 'Nome', 'Telefone', 'Cidade', 'Área Jurídica', 'Sub-área',
                        'Resumo da Demanda', 'Palavras-chave', 'Intenção de Contratar',
                        'Justificativa', 'Urgência', 'Complexidade', 'Potencial Honorários',
                        'Perfil Socioeconômico', 'Gatilho Emocional', 'Objeções',
                        'Origem Canal', 'GCLID', 'Status', 'Data Entrada', 'Data Criação'
                    ], ';');
                    foreach ($leads as $lead) {
                        fputcsv($file, [
                            $lead->id, $lead->nome, $lead->telefone, $lead->cidade,
                            $lead->area_interesse, $lead->sub_area ?? '',
                            $lead->resumo_demanda, $lead->palavras_chave,
                            $lead->intencao_contratar, $lead->intencao_justificativa ?? '',
                            $lead->urgencia ?? '', $lead->complexidade ?? '',
                            $lead->potencial_honorarios ?? '', $lead->perfil_socioeconomico ?? '',
                            $lead->gatilho_emocional ?? '', $lead->objecoes ?? '',
                            $lead->origem_canal ?? '', $lead->gclid ?? '',
                            $lead->status, $lead->data_entrada ?? '',
                            $lead->created_at ? $lead->created_at->format('d/m/Y H:i') : ''
                        ], ';');
                    }
                    break;
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Exportar leads no formato Google Ads Customer Match
     * GET /leads/export-google-ads?formato=csv|xls
     *
     * Formato: https://support.google.com/google-ads/answer/7659867
     */
    public function exportGoogleAds(Request $request)
    {
        $formato = $request->get('formato', 'csv');
        $filtroArea = $request->get('area', 'todos');
        $filtroIntencao = $request->get('intencao', 'todos');

        $query = Lead::query();

        if ($filtroArea !== 'todos') {
            $query->where('area_interesse', $filtroArea);
        }
        if ($filtroIntencao !== 'todos') {
            $query->where('intencao_contratar', $filtroIntencao);
        }

        $leads = $query->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->orderByDesc('data_entrada')
            ->get();

        $filename = 'google_ads_customer_match_' . date('Y-m-d') . '.' . $formato;

        $headers = [
            'Content-Type' => $formato === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($leads) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM para compatibilidade Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header Google Ads Customer Match
            fputcsv($file, [
                'Phone', 'Email', 'First Name', 'Last Name',
                'Country', 'Zip',
                // Colunas extras para contexto (Google Ads ignora colunas desconhecidas)
                'Area Juridica', 'Intencao Contratar', 'Potencial Honorarios',
                'Origem Canal', 'Cidade', 'Data Entrada'
            ], ',');

            foreach ($leads as $lead) {
                // Normalizar telefone para formato E.164 com +
                $phone = preg_replace('/[^0-9]/', '', $lead->telefone ?? '');
                if (strlen($phone) >= 10 && !str_starts_with($phone, '55')) {
                    $phone = '55' . $phone;
                }
                if (!str_starts_with($phone, '+')) {
                    $phone = '+' . $phone;
                }

                // Separar nome em First/Last
                $nomeCompleto = trim($lead->nome ?? '');
                $partes = explode(' ', $nomeCompleto, 2);
                $firstName = $partes[0] ?? '';
                $lastName = $partes[1] ?? '';

                fputcsv($file, [
                    $phone,
                    $lead->email ?? '',
                    $firstName,
                    $lastName,
                    'BR',
                    '', // Zip - nÃ£o temos CEP
                    $lead->area_interesse ?? '',
                    $lead->intencao_contratar ?? '',
                    $lead->potencial_honorarios ?? '',
                    $lead->origem_canal ?? '',
                    $lead->cidade ?? '',
                    $lead->data_entrada ? $lead->data_entrada->format('Y-m-d') : ''
                ], ',');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
