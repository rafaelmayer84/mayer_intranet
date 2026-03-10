<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmPulsoAlerta;
use App\Models\Crm\CrmPulsoConfig;
use App\Models\Crm\CrmPulsoPhoneUpload;
use App\Services\Crm\CrmPulsoPhoneService;
use App\Services\Crm\CrmPulsoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmPulsoController extends Controller
{
    /**
     * Dashboard gerencial — ranking de clientes.
     */
    public function index(Request $request)
    {
        $this->checkAccess();

        $dias = (int) ($request->input('dias', 7));
        $filtro = $request->input('classificacao');

        $service = app(CrmPulsoService::class);
        $ranking = $service->ranking($dias, $filtro);

        $alertasPendentes = CrmPulsoAlerta::where('status', 'pendente')->count();
        $emAtencao = collect($ranking)->where('classificacao', 'atencao')->count();
        $emExcessivo = collect($ranking)->where('classificacao', 'excessivo')->count();

        $uploadPendente = $this->uploadPendenteSemana();

        return view('crm.pulso.dashboard', compact(
            'ranking', 'dias', 'filtro', 'alertasPendentes',
            'emAtencao', 'emExcessivo', 'uploadPendente'
        ));
    }

    /**
     * Lista de alertas com ações.
     */
    public function alertas(Request $request)
    {
        $this->checkAccess();

        $status = $request->input('status', 'pendente');
        $alertas = CrmPulsoAlerta::with('account', 'resolvidoPorUser')
            ->when($status !== 'todos', fn($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('crm.pulso.alertas', compact('alertas', 'status'));
    }

    /**
     * Resolver alerta.
     */
    public function resolverAlerta(Request $request, int $id)
    {
        $this->checkAccess();

        $alerta = CrmPulsoAlerta::findOrFail($id);
        $alerta->update([
            'status'       => 'resolvido',
            'resolvido_por' => Auth::id(),
            'resolvido_em'  => now(),
        ]);

        return back()->with('success', 'Alerta resolvido.');
    }

    /**
     * Marcar alerta como visto.
     */
    public function vistoAlerta(int $id)
    {
        $this->checkAccess();

        $alerta = CrmPulsoAlerta::findOrFail($id);
        if ($alerta->status === 'pendente') {
            $alerta->update(['status' => 'visto']);
        }

        return back()->with('success', 'Alerta marcado como visto.');
    }

    /**
     * Tela de upload do relatório de ligações.
     */
    public function uploadForm()
    {
        $this->checkAdmin();

        $uploads = CrmPulsoPhoneUpload::with('user')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        return view('crm.pulso.upload', compact('uploads'));
    }

    /**
     * Processar upload CSV/XLSX.
     */
    public function uploadProcess(Request $request)
    {
        $this->checkAdmin();

        $request->validate([
            'arquivo' => 'required|file|mimes:csv,txt,xlsx|max:5120',
        ]);

        $service = app(CrmPulsoPhoneService::class);
        $result = $service->processarUpload($request->file('arquivo'));

        return back()->with('success', "Upload processado: {$result['processados']} ligações importadas, {$result['ignorados']} ignoradas. Período: {$result['periodo']}.");
    }

    /**
     * Tela de configuração de thresholds (admin).
     */
    public function configForm()
    {
        $this->checkAdmin();

        $configs = CrmPulsoConfig::orderBy('id')->get();
        return view('crm.pulso.config', compact('configs'));
    }

    /**
     * Salvar thresholds.
     */
    public function configSave(Request $request)
    {
        $this->checkAdmin();

        $dados = $request->input('config', []);
        foreach ($dados as $id => $valor) {
            CrmPulsoConfig::where('id', $id)->update([
                'valor'      => $valor,
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', 'Thresholds atualizados.');
    }

    /**
     * JSON — dados do Pulso para aba no Account 360.
     */
    public function accountPulso(int $accountId)
    {
        $service = app(CrmPulsoService::class);
        $dados = $service->dadosAccount($accountId);

        return response()->json($dados);
    }

    // --- Helpers ---

    protected function checkAccess(): void
    {
        if (!in_array(Auth::user()->role, ['admin', 'socio', 'coordenador'])) {
            abort(403);
        }
    }

    protected function checkAdmin(): void
    {
        if (!in_array(Auth::user()->role, ['admin', 'socio'])) {
            abort(403);
        }
    }

    protected function uploadPendenteSemana(): bool
    {
        $sexta = now('America/Sao_Paulo')->startOfWeek(\Carbon\Carbon::MONDAY);
        return !CrmPulsoPhoneUpload::where('created_at', '>=', $sexta)->exists();
    }
}
