<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KpiTargetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class KpiMetasController extends Controller
{
    protected KpiTargetService $kpiTargetService;

    public function __construct(KpiTargetService $kpiTargetService)
    {
        $this->kpiTargetService = $kpiTargetService;
    }

    /**
     * GET /administracao/metas-kpi
     * Tela principal: lista metas vigentes + botão upload + download template
     */
    public function index(Request $request)
    {
        $ano = (int) ($request->get('ano') ?? date('Y'));

        // Metas vigentes agrupadas por módulo
        $metas = DB::table('kpi_monthly_targets')
            ->where('ano', $ano)
            ->whereNotNull('meta_valor')
            ->orderBy('modulo')
            ->orderBy('kpi_key')
            ->orderBy('mes')
            ->get();

        // Agrupar para exibição
        $agrupado = [];
        foreach ($metas as $m) {
            $agrupado[$m->modulo][$m->kpi_key]['descricao'] = $m->descricao ?? $m->kpi_key;
            $agrupado[$m->modulo][$m->kpi_key]['unidade'] = $m->unidade;
            $agrupado[$m->modulo][$m->kpi_key]['tipo_meta'] = $m->tipo_meta;
            $agrupado[$m->modulo][$m->kpi_key]['meses'][$m->mes] = $m->meta_valor;
        }

        // Contadores
        $stats = [
            'total_kpis'  => count($metas->groupBy(fn($m) => $m->modulo . '.' . $m->kpi_key)),
            'total_metas' => $metas->count(),
            'modulos'     => $metas->pluck('modulo')->unique()->count(),
        ];

        // Anos disponíveis para dropdown
        $anosDisponiveis = DB::table('kpi_monthly_targets')
            ->select('ano')
            ->distinct()
            ->orderByDesc('ano')
            ->pluck('ano')
            ->toArray();

        if (!in_array($ano, $anosDisponiveis)) {
            $anosDisponiveis[] = $ano;
            rsort($anosDisponiveis);
        }

        return view('admin.metas-kpi.index', compact('agrupado', 'stats', 'ano', 'anosDisponiveis'));
    }

    /**
     * GET /administracao/metas-kpi/template
     * Download do template XLS
     */
    public function downloadTemplate()
    {
        $templatePath = resource_path('templates/template_metas_kpi_2026.xlsx');

        if (!file_exists($templatePath)) {
            return back()->with('error', 'Template não encontrado no servidor.');
        }

        return response()->download($templatePath, 'template_metas_kpi_' . date('Y') . '.xlsx');
    }

    /**
     * POST /administracao/metas-kpi/upload
     * Recebe XLS, parseia e retorna preview para confirmação
     */
    public function upload(Request $request)
    {
        $request->validate([
            'arquivo' => 'required|file|mimes:xlsx,xls|max:5120',
        ]);

        $file = $request->file('arquivo');
        $tempDir = storage_path('app/temp');
        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0775, true);
        }
        $fileName = 'kpi_upload_' . time() . '.' . $file->getClientOriginalExtension();
        $fullPath = $tempDir . '/' . $fileName;
        $file->move($tempDir, $fileName);
        $path = 'temp/' . $fileName;

        try {
            $linhas = $this->parseXlsx($fullPath);
        } catch (\Exception $e) {
            @unlink($fullPath);
            return back()->with('error', 'Erro ao processar arquivo: ' . $e->getMessage());
        }

        if (empty($linhas)) {
            @unlink($fullPath);
            return back()->with('error', 'Planilha vazia ou formato inválido.');
        }

        // Separar válidos de inválidos para preview
        $validos = [];
        $invalidos = [];
        $registry = KpiTargetService::KPI_REGISTRY;

        foreach ($linhas as $i => $l) {
            $modulo = trim($l['modulo'] ?? '');
            $kpiKey = trim($l['kpi_key'] ?? '');

            if (!isset($registry[$modulo][$kpiKey])) {
                $l['_erro'] = "KPI '{$modulo}.{$kpiKey}' não reconhecido";
                $invalidos[] = $l;
                continue;
            }

            if ($l['meta_valor'] === null || $l['meta_valor'] === '') {
                continue; // Ignorar linhas sem meta
            }

            $l['_status'] = DB::table('kpi_monthly_targets')
                ->where('modulo', $modulo)
                ->where('kpi_key', $kpiKey)
                ->where('ano', $l['ano'] ?? 0)
                ->where('mes', $l['mes'] ?? 0)
                ->exists() ? 'atualizar' : 'novo';

            $validos[] = $l;
        }

        $ano = (int) ($request->get('ano') ?? date('Y'));

        // Guardar path temporário na sessão para confirmação
        session(['kpi_import_path' => $path, 'kpi_import_linhas' => $validos]);

        return view('admin.metas-kpi.preview', compact('validos', 'invalidos', 'ano'));
    }

    /**
     * POST /administracao/metas-kpi/confirmar
     * Grava metas confirmadas no banco
     */
    public function confirmar(Request $request)
    {
        $linhas = session('kpi_import_linhas', []);
        $path = session('kpi_import_path', '');

        if (empty($linhas)) {
            return redirect()->route('admin.metas-kpi.index')
                ->with('error', 'Sessão expirada. Faça upload novamente.');
        }

        $resultado = $this->kpiTargetService->importar($linhas, auth()->id());

        // Limpar temp
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
        session()->forget(['kpi_import_path', 'kpi_import_linhas']);

        $msg = "{$resultado['inseridos']} inseridos, {$resultado['atualizados']} atualizados, {$resultado['ignorados']} ignorados.";
        if (!empty($resultado['erros'])) {
            $msg .= ' Erros: ' . implode('; ', array_slice($resultado['erros'], 0, 5));
        }

        return redirect()->route('admin.metas-kpi.index')
            ->with('success', "Importação concluída: {$msg}");
    }

    /**
     * DELETE /administracao/metas-kpi/limpar
     * Remove todas as metas de um ano
     */
    public function limpar(Request $request)
    {
        $ano = (int) $request->get('ano', date('Y'));

        $deleted = DB::table('kpi_monthly_targets')
            ->where('ano', $ano)
            ->delete();

        return redirect()->route('admin.metas-kpi.index', ['ano' => $ano])
            ->with('success', "{$deleted} metas removidas para {$ano}.");
    }

    /**
     * Parseia XLS usando PhpSpreadsheet (via PhpOffice que Laravel suporta).
     * Fallback para leitura CSV simples se PhpSpreadsheet não disponível.
     */
    private function parseXlsx(string $filePath): array
    {
        // Tentar PhpSpreadsheet primeiro
        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            return $this->parseViaPhpSpreadsheet($filePath);
        }

        // Fallback: usar comando python para converter XLSX → JSON
        return $this->parseViaPython($filePath);
    }

    private function parseViaPhpSpreadsheet(string $filePath): array
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (count($rows) < 2) {
            return [];
        }

        // Primeira linha = headers
        $headers = array_map('strtolower', array_map('trim', $rows[1]));
        $linhas = [];

        for ($i = 2; $i <= count($rows); $i++) {
            $row = $rows[$i];
            $linha = [];
            foreach ($headers as $colLetter => $headerName) {
                $linha[$headerName] = $row[$colLetter] ?? null;
            }
            // Só incluir linhas com ao menos modulo e kpi_key
            if (!empty($linha['modulo']) && !empty($linha['kpi_key'])) {
                $linhas[] = $linha;
            }
        }

        return $linhas;
    }

    private function parseViaPython(string $filePath): array
    {
        $jsonPath = $filePath . '.json';
        $escapedFile = escapeshellarg($filePath);
        $escapedJson = escapeshellarg($jsonPath);

        $pyScript = <<<PYTHON
import json
try:
    from openpyxl import load_workbook
    wb = load_workbook({$escapedFile}, read_only=True, data_only=True)
    ws = wb.active
    rows = list(ws.iter_rows(values_only=True))
    if len(rows) < 2:
        json.dump([], open({$escapedJson}, 'w'))
    else:
        headers = [str(h).strip().lower() if h else '' for h in rows[0]]
        result = []
        for row in rows[1:]:
            d = {}
            for idx, h in enumerate(headers):
                if h:
                    val = row[idx] if idx < len(row) else None
                    d[h] = val
            if d.get('modulo') and d.get('kpi_key'):
                result.append(d)
        json.dump(result, open({$escapedJson}, 'w'), default=str)
    wb.close()
except Exception as e:
    json.dump({'error': str(e)}, open({$escapedJson}, 'w'))
PYTHON;

        exec("python3 -c " . escapeshellarg($pyScript) . " 2>&1", $output, $code);

        if (!file_exists($jsonPath)) {
            throw new \RuntimeException('Falha ao processar XLSX. Python output: ' . implode("\n", $output));
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        @unlink($jsonPath);

        if (isset($data['error'])) {
            throw new \RuntimeException('Erro no parser: ' . $data['error']);
        }

        return $data ?: [];
    }
}
