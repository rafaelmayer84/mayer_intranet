<?php

namespace App\Http\Controllers;

use App\Services\NexoGerencialService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * NexoGerencialController
 *
 * Fase 1: Endpoint JSON funcional — view Blade será entregue na Fase 3.
 */
class NexoGerencialController extends Controller
{
    public function __construct(
        protected NexoGerencialService $gerencialService
    ) {}

    /**
     * Tela principal — STUB Fase 1 (será substituída na Fase 3).
     */
    public function index()
    {
        // Fase 3: return view('nexo.gerencial.index', [...]);
        return response()->view('nexo.gerencial.stub', [
            'title' => 'Nexo — Visão Gerencial',
            'fase'  => 1,
        ]);
    }

    /**
     * Dados de KPIs e gráficos (JSON).
     * Já funcional na Fase 1 — a view da Fase 3 consumirá este endpoint.
     */
    public function data(Request $request): JsonResponse
    {
        $filtros = $request->only(['periodo', 'de', 'ate', 'advogado', 'status', 'tipo']);

        return response()->json([
            'kpis'    => $this->gerencialService->getKpis($filtros),
            'charts'  => $this->gerencialService->getCharts($filtros),
        ]);
    }
}
