<?php

namespace App\Http\Controllers;

use App\Services\Nexo\NexoTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NexoTicketController extends Controller
{
    private NexoTicketService $service;

    public function __construct(NexoTicketService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $filtros = $request->only([
            'status', 'tipo', 'prioridade', 'responsavel_id',
            'busca', 'data_inicio', 'data_fim',
        ]);

        if (empty($filtros['status'])) {
            $filtros['status'] = 'ativos';
        }

        $tickets = $this->service->listar($filtros);
        $kpis = $this->service->getKpis();
        $usuarios = \App\Models\User::orderBy('name')->get(['id', 'name', 'role']);

        return view('nexo.tickets.index', compact('tickets', 'kpis', 'filtros', 'usuarios'));
    }

    public function show(int $id)
    {
        $ticket = $this->service->detalhe($id);

        if (!$ticket) {
            return response()->json(['erro' => 'Ticket nao encontrado'], 404);
        }

        return response()->json([
            'ticket' => $ticket,
            'notas' => $ticket->notas->map(function ($nota) {
                return [
                    'id' => $nota->id,
                    'texto' => $nota->texto,
                    'tipo' => $nota->tipo ?? 'tratativa',
                    'usuario' => $nota->user->name ?? 'Sistema',
                    'notificou_cliente' => $nota->notificou_cliente,
                    'data' => $nota->created_at->format('d/m/Y H:i'),
                ];
            }),
        ]);
    }

    public function atribuir(Request $request, int $id)
    {
        $request->validate(['responsavel_id' => 'nullable|exists:users,id']);

        $ticket = $this->service->atribuirResponsavel($id, $request->responsavel_id, Auth::id());

        return response()->json([
            'sucesso' => true,
            'status' => $ticket->status,
            'responsavel' => $ticket->responsavel?->name,
        ]);
    }

    public function mudarStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:aberto,em_andamento,concluido,cancelado',
        ]);

        $ticket = $this->service->mudarStatus($id, $request->status);

        return response()->json([
            'sucesso' => true,
            'status' => $ticket->status,
            'resolvido_at' => $ticket->resolvido_at ? $ticket->resolvido_at->format('d/m/Y H:i') : null,
        ]);
    }

    public function adicionarNota(Request $request, int $id)
    {
        $request->validate([
            'texto' => 'required|string|max:2000',
            'notificar_cliente' => 'sometimes|boolean',
        ]);

        $nota = $this->service->adicionarNota(
            $id,
            Auth::id(),
            $request->texto,
            (bool) $request->notificar_cliente
        );

        return response()->json([
            'sucesso' => true,
            'nota' => [
                'id' => $nota->id,
                'texto' => $nota->texto,
                'usuario' => Auth::user()->name,
                'notificou_cliente' => $nota->notificou_cliente,
                'data' => $nota->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }


    public function resolver(Request $request, int $id)
    {
        $request->validate([
            'resolucao' => 'required|string|max:5000',
        ]);

        $ticket = $this->service->resolver($id, Auth::id(), $request->resolucao);

        return response()->json([
            'sucesso' => true,
            'status' => $ticket->status,
            'resolvido_at' => $ticket->resolvido_at->format('d/m/Y H:i'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'assunto' => 'required|string|max:255',
            'tipo' => 'required|in:geral,documento,agendamento,retorno,financeiro',
            'mensagem' => 'nullable|string',
            'prioridade' => 'required|in:normal,urgente',
            'nome_cliente' => 'nullable|string|max:255',
            'telefone' => 'nullable|string|max:20',
            'responsavel_id' => 'nullable|exists:users,id',
        ]);

        $ticket = $this->service->criarManual($request->all(), Auth::id());

        return response()->json([
            'sucesso' => true,
            'ticket_id' => $ticket->id,
            'protocolo' => $ticket->protocolo,
        ]);
    }

    public function destroy(int $id)
    {
        $ticket = \App\Models\NexoTicket::findOrFail($id);
        $ticket->notas()->delete();
        $ticket->delete();

        return response()->json(['sucesso' => true]);
    }

}
