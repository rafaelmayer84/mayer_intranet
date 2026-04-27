<?php

namespace App\Http\Controllers;

use App\Models\WaConversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NexoLeadsAguardandoController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'user.active']);
    }

    public function index()
    {
        $conversas = WaConversation::query()
            ->whereNotNull('linked_lead_id')
            ->whereNull('first_response_at')
            ->where('status', 'open')
            ->where('last_incoming_at', '>=', now()->subHours(72))
            ->with([
                'lead'       => fn($q) => $q->select('id', 'nome', 'area_interesse', 'cidade', 'resumo_demanda', 'intencao_contratar', 'urgencia'),
                'lexusSessao' => fn($q) => $q->select('id', 'phone', 'briefing_operador', 'intencao_contratar', 'urgencia'),
            ])
            ->orderBy('last_incoming_at', 'asc')
            ->get()
            ->map(function ($conv) {
                $minutos = $conv->minutes_since_last_incoming ?? 0;
                $conv->sla_status = match (true) {
                    $minutos >= 180 => 'urgente',
                    $minutos >= 60  => 'atencao',
                    default         => 'recente',
                };
                $conv->sla_minutos = $minutos;
                return $conv;
            });

        $grupos = [
            'urgente' => $conversas->where('sla_status', 'urgente')->values(),
            'atencao' => $conversas->where('sla_status', 'atencao')->values(),
            'recente' => $conversas->where('sla_status', 'recente')->values(),
        ];

        return view('nexo.leads-aguardando', compact('grupos'));
    }

    public function markAtendido(int $convId): JsonResponse
    {
        $conv = WaConversation::findOrFail($convId);

        $conv->update([
            'first_response_at' => now(),
            'assigned_user_id'  => auth()->id(),
            'assigned_at'       => now(),
        ]);

        Log::info('NEXO-LEADS-AGUARDANDO: marcado como atendido', [
            'conv_id'  => $convId,
            'user_id'  => auth()->id(),
            'user'     => auth()->user()->name,
            'lead_id'  => $conv->linked_lead_id,
        ]);

        return response()->json(['ok' => true]);
    }
}
