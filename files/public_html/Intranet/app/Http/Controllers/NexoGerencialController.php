<?php

namespace App\Http\Controllers;

use App\Models\NexoEscalaDiaria;
use App\Models\User;
use App\Services\NexoGerencialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class NexoGerencialController extends Controller
{
    public function __construct(
        protected NexoGerencialService $service
    ) {}

    // ═══════════════════════════════════════════════════════
    //  PAINEL PRINCIPAL
    // ═══════════════════════════════════════════════════════

    public function index(Request $request)
    {
        $usuarios = User::select('id', 'name', 'role')
            ->whereIn('role', ['admin', 'coordenador', 'socio'])
            ->orderBy('name')
            ->get();

        return view('nexo.gerencial.index', [
            'title'    => 'NEXO — Painel Gerencial',
            'usuarios' => $usuarios,
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        $filtros = $request->only(['periodo', 'de', 'ate', 'somente_janela']);

        return response()->json($this->service->getDadosPainel($filtros));
    }

    // ═══════════════════════════════════════════════════════
    //  DRILL-DOWN
    // ═══════════════════════════════════════════════════════

    public function drillDown(Request $request, string $tipo): JsonResponse
    {
        $filtros = $request->only(['periodo', 'de', 'ate', 'somente_janela', 'data']);

        return response()->json([
            'tipo'  => $tipo,
            'items' => $this->service->drillDown($tipo, $filtros),
        ]);
    }

    // ═══════════════════════════════════════════════════════
    //  ESCALA — CRUD (Admin only)
    // ═══════════════════════════════════════════════════════

    public function escala(Request $request)
    {
        $mes = $request->query('mes', Carbon::now()->format('Y-m'));

        $escalas  = $this->service->listarEscala($mes);
        $usuarios = User::select('id', 'name', 'role')
            ->whereIn('role', ['admin', 'coordenador', 'socio'])
            ->orderBy('name')
            ->get();

        return view('nexo.gerencial.escala', [
            'title'    => 'NEXO — Escala de Atendimento',
            'mes'      => $mes,
            'escalas'  => $escalas,
            'usuarios' => $usuarios,
        ]);
    }

    public function escalaStore(Request $request): JsonResponse
    {
        $request->validate([
            'data'    => 'required|date',
            'user_id' => 'required|exists:users,id',
            'inicio'  => 'nullable|date_format:H:i',
            'fim'     => 'nullable|date_format:H:i',
            'observacao' => 'nullable|string|max:255',
        ]);

        $escala = $this->service->salvarEscala($request->only(['data', 'user_id', 'inicio', 'fim', 'observacao']));

        return response()->json(['success' => true, 'escala' => $escala->load('usuario:id,name')]);
    }

    public function escalaDestroy(int $id): JsonResponse
    {
        $ok = $this->service->excluirEscala($id);

        return response()->json(['success' => $ok]);
    }
}
