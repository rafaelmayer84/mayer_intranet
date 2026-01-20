<?php

namespace App\Http\Controllers;

use App\Models\Advogado;
use App\Models\Processo;
use App\Models\Atividade;
use App\Models\HoraTrabalhada;
use App\Models\Movimento;
use App\Services\DataJuriService;
use App\Services\SyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SyncController extends Controller
{
    protected $dataJuriService;
    protected $syncService;

    public function __construct(DataJuriService $dataJuriService, SyncService $syncService)
    {
        $this->dataJuriService = $dataJuriService;
        $this->syncService = $syncService;
    }

    public function index(Request $request)
    {
        $ano = (int)($request->get('ano', date('Y')));

        $counts = [
            'advogados' => Advogado::count(),
            'processos' => Processo::count(),
            'atividades' => Atividade::count(),
            'horas' => HoraTrabalhada::count(),
            'movimentos' => Movimento::where('ano', $ano)->count(),
        ];

        $ultimaSync = Movimento::where('ano', $ano)->max('updated_at');

        $resumoMovimentos = [
            'receita_pf' => Movimento::where('ano', $ano)->where('classificacao', Movimento::RECEITA_PF)->sum('valor'),
            'receita_pj' => Movimento::where('ano', $ano)->where('classificacao', Movimento::RECEITA_PJ)->sum('valor'),
            'receita_financeira' => Movimento::where('ano', $ano)->where('classificacao', Movimento::RECEITA_FINANCEIRA)->sum('valor'),
            'pendentes' => Movimento::where('ano', $ano)->where('classificacao', Movimento::PENDENTE_CLASSIFICACAO)->count(),
        ];

        $anosDisponiveis = range(date('Y'), 2020);
        return view('sync.index', compact('counts', 'ano', 'ultimaSync', 'resumoMovimentos', 'anosDisponiveis'));
    }

    /**
     * Retorna movimentos do banco de dados (paginado)
     */
    public function dbMovimentos(Request $request)
    {
        $ano = (int)($request->get('ano', date('Y')));
        $offset = (int)($request->get('offset', 0));
        $limit = (int)($request->get('limit', 50));
        $classificacao = $request->get('classificacao');

        $query = Movimento::where('ano', $ano);

        if ($classificacao && $classificacao !== 'todas') {
            $query->where('classificacao', $classificacao);
        }

        $total = $query->count();
        $rows = $query->orderBy('data', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'datajuri_id' => $m->datajuri_id,
                    'data' => $m->data,
                    'valor' => $m->valor,
                    'pessoa' => $m->pessoa,
                    'plano_contas' => $m->plano_contas,
                    'codigo_plano' => $m->codigo_plano,
                    'classificacao' => $m->classificacao,
                ];
            });

        return response()->json([
            'success' => true,
            'ano' => $ano,
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'rows' => $rows,
        ]);
    }

    /**
     * Retorna prévia da API (uma página)
     */
    public function apiPreviewMovimentos(Request $request)
    {
        $ano = (int)($request->get('ano', date('Y')));
        $page = (int)($request->get('page', 1));
        $pageSize = (int)($request->get('pageSize', 50));

        $resp = $this->dataJuriService->buscarModuloPagina('Movimento', $page, $pageSize, []);

        $rows = $resp['rows'] ?? [];
        $listSize = $resp['listSize'] ?? 0;
        $pageSizeResp = $resp['pageSize'] ?? $pageSize;

        // Filtra pelo ano (baseado na data que vem no registro)
        $rowsAno = array_values(array_filter($rows, function ($r) use ($ano) {
            $data = $r['data'] ?? null;
            if (!$data) return false;
            // Data vem como dd/mm/yyyy
            $partes = explode('/', $data);
            if (count($partes) === 3) {
                return (int)$partes[2] === $ano;
            }
            return false;
        }));

        // Marca se já existe no banco (por datajuri_id)
        $ids = array_values(array_filter(array_map(fn($r) => $r['id'] ?? null, $rowsAno)));
        $existentes = Movimento::whereIn('datajuri_id', $ids)->pluck('datajuri_id')->flip();

        $out = array_map(function ($r) use ($existentes) {
            $plano = $r['planoConta.nomeCompleto'] ?? '';
            $codigo = '';
            if (preg_match('/\b(\d+\.\d+\.\d+\.\d+)\b/', $plano, $m)) $codigo = $m[1];

            // Parse valor
            $valorStr = strip_tags($r['valorComSinal'] ?? '0');
            $valorStr = str_replace(['.', ' '], ['', ''], $valorStr);
            $valorStr = str_replace(',', '.', $valorStr);
            $valor = (float)$valorStr;

            return [
                'datajuri_id' => $r['id'] ?? null,
                'data' => $r['data'] ?? null,
                'valor' => $valor,
                'pessoa' => $r['pessoa.nome'] ?? '',
                'plano_contas' => $plano,
                'codigo_plano' => $codigo,
                'ja_gravado' => isset($existentes[$r['id'] ?? '']),
            ];
        }, $rowsAno);

        return response()->json([
            'success' => true,
            'ano' => $ano,
            'page' => $page,
            'pageSize' => $pageSizeResp,
            'listSize' => $listSize,
            'rows' => $out,
        ]);
    }

    /**
     * PATCH 3 - Sincronização por LOTE (batch)
     * Evita timeout em hospedagem compartilhada
     */
    public function syncMovimentosBatch(Request $request)
    {
        $ano = (int)($request->input('ano', date('Y')));
        $page = (int)($request->input('page', 1));
        $pageSize = (int)($request->input('pageSize', 200));

        $stats = $this->syncService->syncMovimentosBatch($ano, $page, $pageSize);

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Verificar status da conexão com DataJuri
     */
    public function status()
    {
        try {
            $connected = $this->dataJuriService->testarConexao();
            return response()->json([
                'connected' => $connected,
                'message' => $connected ? 'Conectado' : 'Falha na conexão'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'connected' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sincronizar todos os dados
     */
    public function syncAll(Request $request)
    {
        try {
            $ano = (int)($request->input('ano', date('Y')));
            
            Log::info('Iniciando sincronização completa', ['ano' => $ano]);
            
            $results = $this->syncService->syncAll($ano);
            
            return response()->json([
                'success' => true,
                'message' => 'Sincronização concluída',
                'results' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Erro na sincronização', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function syncAdvogados()
    {
        try {
            $result = $this->syncService->syncAdvogados();
            return response()->json(['success' => true, 'count' => $result['count'] ?? 0]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncProcessos()
    {
        try {
            $result = $this->syncService->syncProcessos();
            return response()->json(['success' => true, 'count' => $result['count'] ?? 0]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncAtividades()
    {
        try {
            $result = $this->syncService->syncAtividades();
            return response()->json(['success' => true, 'count' => $result['count'] ?? 0]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncContasReceber(\Illuminate\Http\Request $request)
    {
        try {
            $dryRun = (bool)$request->boolean('dry_run', false);
            $result = $this->syncService->syncContasReceber($dryRun);
return response()->json(['success' => true, 'count' => $result['count'] ?? 0]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncHorasTrabalhadas()
    {
        try {
            $result = $this->syncService->syncHorasTrabalhadas();
            return response()->json(['success' => true, 'count' => $result['count'] ?? 0]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function syncMovimentos(Request $request)
    {
        try {
            $ano = (int)($request->input('ano', date('Y')));
            $result = $this->syncService->syncMovimentos(null, $ano);
            return response()->json(['success' => true, 'count' => $result['total'] ?? 0, 'result' => $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function debugLog()
    {
        $path = storage_path('logs/sync_debug.log');
        if (!file_exists($path)) {
            return response('sync_debug.log nao existe ainda. Rode uma sincronizacao.', 404)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $tail = array_slice($lines, -400);

        return response(implode(chr(10), $tail), 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
