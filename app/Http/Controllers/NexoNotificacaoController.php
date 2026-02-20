<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\Nexo\NexoNotificacaoService;
use Illuminate\Support\Facades\Auth;

class NexoNotificacaoController extends Controller
{
    protected NexoNotificacaoService $service;

    public function __construct(NexoNotificacaoService $service)
    {
        $this->service = $service;
    }

    /**
     * Painel de notificações pendentes + histórico.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $isAdmin = in_array($user->role, ['admin', 'socio', 'coordenador']);

        // Admin vê tudo, advogado vê só as suas
        $userId = $isAdmin ? null : $user->id;

        $pendentes = $this->service->listarPendentes($userId);
        $historico = $this->service->listarHistorico($userId, 50);

        // Contadores
        $countPendentes = $pendentes->count();
        $countEnviados = $historico->where('status', 'sent')->count();
        $countFalha = $historico->where('status', 'failed')->count();

        return view('nexo.notificacoes.index', compact(
            'pendentes', 'historico', 'countPendentes', 'countEnviados', 'countFalha', 'isAdmin'
        ));
    }

    /**
     * Aprovar e enviar notificação (AJAX).
     */
    public function aprovar(Request $request, $id)
    {
        $descricaoCustom = $request->input('descricao_custom');
        $result = $this->service->aprovarEEnviarAndamento((int) $id, $descricaoCustom);
        return response()->json($result);
    }

    /**
     * Descartar notificação (AJAX).
     */
    public function descartar(Request $request, $id)
    {
        $ok = $this->service->descartarNotificacao((int) $id);
        return response()->json(['success' => $ok]);
    }

    /**
     * Aprovar em massa (AJAX).
     */
    public function aprovarMassa(Request $request)
    {
        $ids = $request->input('ids', []);
        $enviados = 0;
        $falhas = 0;

        foreach ($ids as $id) {
            $result = $this->service->aprovarEEnviarAndamento((int) $id);
            if ($result['success'] ?? false) {
                $enviados++;
            } else {
                $falhas++;
            }
        }

        return response()->json([
            'success' => true,
            'enviados' => $enviados,
            'falhas' => $falhas,
        ]);
    }

    /**
     * Aprovar OS: recebe cliente_id + mensagem, envia template.
     */
    public function aprovarOS(Request $request, $id)
    {
        $request->validate([
            'cliente_id' => 'required|integer',
            'mensagem' => 'required|string|max:300',
        ]);

        $result = $this->service->aprovarEEnviarOS(
            (int) $id,
            (int) $request->input('cliente_id'),
            $request->input('mensagem')
        );

        return response()->json($result);
    }

    /**
     * Autocomplete de clientes para seleção na OS.
     */
    public function buscarClientes(Request $request)
    {
        $termo = $request->input('q', '');
        if (mb_strlen($termo) < 2) {
            return response()->json([]);
        }

        $clientes = $this->service->buscarClientes($termo);
        return response()->json($clientes);
    }

}
