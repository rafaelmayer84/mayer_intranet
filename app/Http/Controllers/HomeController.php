<?php

namespace App\Http\Controllers;

use App\Services\HomeDashboardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HomeController extends Controller
{
    protected HomeDashboardService $service;

    public function __construct(HomeDashboardService $service)
    {
        $this->service = $service;
    }

    public function index()
    {
        $user   = Auth::user();
        $userId = $user->id;

        $gdpScore        = $this->service->getGdpScore($userId);
        $alertasCrm      = $this->service->getAlertasCrm($userId);
        $ticketsAbertos   = $this->service->getTicketsAbertos($userId);
        $resumoFinanceiro = $this->service->getResumoFinanceiro();
        $avisos           = $this->service->getAvisosNaoLidos($userId);
        $volumetria       = $this->service->getVolumetria();

        $hora = (int) now()->format('H');
        if ($hora < 12) $saudacao = 'Bom dia';
        elseif ($hora < 18) $saudacao = 'Boa tarde';
        else $saudacao = 'Boa noite';

        $primeiroNome = explode(' ', $user->name ?? $user->nome ?? 'Usuario')[0];

        return view('home.index', compact(
            'user', 'saudacao', 'primeiroNome',
            'gdpScore', 'alertasCrm', 'ticketsAbertos',
            'resumoFinanceiro', 'avisos', 'volumetria'
        ));
    }

    public function buscar(Request $request)
    {
        $query = trim($request->input('q', ''));
        if (mb_strlen($query) < 2) return response()->json([]);
        return response()->json($this->service->buscarGlobal($query));
    }
}
