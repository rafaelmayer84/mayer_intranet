<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class NexoInactivityController extends Controller
{
    /**
     * Verifica se o contato está inativo há mais de 6 horas.
     * Chamado pelo flow "Resposta padrão" do SendPulse.
     *
     * POST /api/nexo/verificar-inatividade
     * Body: {"telefone": "554791314240"}
     * Header: X-Sendpulse-Token = NEXO_CONSULTA_TOKEN
     *
     * Retorna: {"expirado": "sim"} ou {"expirado": "nao"}
     */
    public function verificarInatividade(Request $request)
    {
        // === AUTH ===
        $tokenRecebido = $request->header('X-Sendpulse-Token');
        $tokenEsperado = env('NEXO_CONSULTA_TOKEN');

        if (!$tokenRecebido || $tokenRecebido !== $tokenEsperado) {
            if (!$this->isIpSendPulse($request->ip())) {
                Log::warning('NexoInactivity: auth falhou', [
                    'ip' => $request->ip(),
                    'token_presente' => !empty($tokenRecebido),
                ]);
                return response()->json(['error' => 'Não autorizado'], 401);
            }
        }

        // === INPUT ===
        $telefone = $request->input('telefone', '');
        $telefone = preg_replace('/\D/', '', $telefone);

        if (empty($telefone)) {
            return response()->json(['expirado' => 'sim']);
        }

        if (strlen($telefone) <= 11) {
            $telefone = '55' . $telefone;
        }

        Log::info('NexoInactivity: verificando', ['telefone' => $telefone]);

        // === QUERY ===
        // Ignora mensagens dos últimos 60s (a mensagem atual que disparou o flow)
        $ultimaInteracao = DB::table('wa_messages as m')
            ->join('wa_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where('c.phone', $telefone)
            ->where('m.sent_at', '<', now()->subSeconds(60))
            ->max('m.sent_at');

        if (!$ultimaInteracao) {
            Log::info('NexoInactivity: sem histórico', ['telefone' => $telefone]);
            return response()->json(['expirado' => 'sim']);
        }

        $ultima = Carbon::parse($ultimaInteracao, 'America/Sao_Paulo');
        $agora = Carbon::now('America/Sao_Paulo');
        $horasDecorridas = $ultima->diffInHours($agora);

        $expirado = $horasDecorridas >= 6 ? 'sim' : 'nao';

        Log::info('NexoInactivity: resultado', [
            'telefone' => $telefone,
            'horas_decorridas' => $horasDecorridas,
            'expirado' => $expirado,
        ]);

        return response()->json([
            'expirado' => $expirado,
        ]);
    }

    private function isIpSendPulse(string $ip): bool
    {
        $ranges = [
            '185.23.85.', '185.23.86.', '185.23.87.',
            '91.229.95.', '178.32.',
            '2a02:4780:',
            '188.40.',
            '46.4.',
        ];

        foreach ($ranges as $range) {
            if (str_starts_with($ip, $range)) {
                return true;
            }
        }

        return false;
    }
}
