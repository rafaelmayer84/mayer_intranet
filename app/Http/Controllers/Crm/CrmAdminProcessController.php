<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\CrmAdminProcess;
use App\Models\Crm\CrmAdminProcessAto;
use App\Models\Crm\CrmAdminProcessAtoAnexo;
use App\Models\Crm\CrmAdminProcessTramitacao;
use App\Models\Crm\CrmAdminProcessStep;
use App\Models\Crm\CrmAdminProcessTimeline;
use App\Models\Crm\CrmAdminProcessChecklist;
use App\Models\Crm\CrmAdminProcessTemplate;
use App\Models\Crm\CrmAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CrmAdminProcessController extends Controller
{
    // ── Timeline helper ───────────────────────────────────────

    private function timeline(CrmAdminProcess $processo, string $tipo, string $titulo, ?string $corpo = null, bool $clientVisible = false, ?int $stepId = null): void
    {
        CrmAdminProcessTimeline::create([
            'admin_process_id' => $processo->id,
            'step_id'          => $stepId,
            'user_id'          => auth()->id(),
            'tipo'             => $tipo,
            'titulo'           => $titulo,
            'corpo'            => $corpo,
            'is_client_visible'=> $clientVisible,
            'happened_at'      => now(),
        ]);
    }

    // ── Listagem ───────────────────────────────────────────────

    public function index(Request $request)
    {
        $query = CrmAdminProcess::with(['account','owner','comUsuario','atos'])
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->when($request->tipo,   fn($q, $v) => $q->where('tipo', $v))
            ->when($request->owner,  fn($q, $v) => $q->where('owner_user_id', $v))
            ->when($request->search, fn($q, $v) => $q->where(function($sq) use ($v) {
                $sq->where('protocolo', 'like', "%{$v}%")
                   ->orWhere('titulo', 'like', "%{$v}%");
            }));

        // "Minha mesa" = processos que estão comigo
        if ($request->mesa === '1') {
            $query->where('com_user_id', auth()->id());
        }

        $processos = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $usuarios  = User::orderBy('name')->get(['id','name']);

        $stats = [
            'total'        => CrmAdminProcess::ativos()->count(),
            'na_minha_mesa'=> CrmAdminProcess::ativos()->where('com_user_id', auth()->id())->count(),
            'em_andamento' => CrmAdminProcess::where('status','em_andamento')->count(),
            'atrasados'    => CrmAdminProcess::ativos()
                                ->whereNotNull('prazo_final')
                                ->where('prazo_final', '<', now()->toDateString())->count(),
        ];

        return view('crm.admin-processes.index', compact('processos','usuarios','stats'));
    }

    // ── Detalhe (árvore de atos) ──────────────────────────────

    public function show(int $id)
    {
        $processo = CrmAdminProcess::with([
            'account','owner','comUsuario',
            'atos.autor','atos.anexos','atos.assinadoPor',
            'tramitacoes.de','tramitacoes.para',
            'steps.responsible',
            'checklist',
            'timeline.user',
        ])->findOrFail($id);

        $usuarios = User::orderBy('name')->get(['id','name']);

        return view('crm.admin-processes.show', compact('processo','usuarios'));
    }

    // ── Criar ──────────────────────────────────────────────────

    public function create(Request $request)
    {
        $templates = CrmAdminProcessTemplate::getTemplates();
        $accounts  = CrmAccount::where('kind','client')->orderBy('name')->get(['id','name']);
        $usuarios  = User::orderBy('name')->get(['id','name']);
        $accountId = $request->account_id;

        return view('crm.admin-processes.create', compact('templates','accounts','usuarios','accountId'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'account_id'       => 'required|exists:crm_accounts,id',
            'tipo'             => 'required|string',
            'titulo'           => 'required|string|max:255',
            'descricao'        => 'nullable|string',
            'owner_user_id'    => 'required|exists:users,id',
            'orgao_destino'    => 'nullable|string|max:255',
            'prazo_estimado'   => 'nullable|date',
            'prazo_final'      => 'nullable|date',
            'prioridade'       => 'required|in:baixa,normal,alta,urgente',
            'valor_honorarios' => 'nullable|numeric',
            'steps'            => 'nullable|array',
            'checklist_items'  => 'nullable|array',
        ]);

        $processo = CrmAdminProcess::create([
            'protocolo'       => CrmAdminProcess::gerarProtocolo(),
            'account_id'      => $data['account_id'],
            'tipo'            => $data['tipo'],
            'titulo'          => $data['titulo'],
            'descricao'       => $data['descricao'] ?? null,
            'owner_user_id'   => $data['owner_user_id'],
            'com_user_id'     => $data['owner_user_id'],  // começa na mesa do responsável
            'orgao_destino'   => $data['orgao_destino'] ?? null,
            'prazo_estimado'  => $data['prazo_estimado'] ?? null,
            'prazo_final'     => $data['prazo_final'] ?? null,
            'prioridade'      => $data['prioridade'],
            'valor_honorarios'=> $data['valor_honorarios'] ?? null,
            'status'          => 'em_andamento',
            'client_visible'  => true,
        ]);

        // Etapas (guia lateral)
        foreach (($data['steps'] ?? []) as $i => $step) {
            if (empty($step['titulo'])) continue;
            CrmAdminProcessStep::create([
                'admin_process_id'    => $processo->id,
                'order'               => $i + 1,
                'titulo'              => $step['titulo'],
                'tipo'                => $step['tipo'] ?? 'interno',
                'orgao'               => $step['orgao'] ?? null,
                'deadline_days'       => $step['deadline_days'] ?? null,
                'is_client_visible'   => !empty($step['is_client_visible']),
                'status'              => 'pendente',
                'responsible_user_id' => $data['owner_user_id'],
            ]);
        }

        // Checklist
        foreach (($data['checklist_items'] ?? []) as $item) {
            if (empty($item)) continue;
            CrmAdminProcessChecklist::create([
                'admin_process_id' => $processo->id,
                'nome'             => $item,
                'status'           => 'pendente',
            ]);
        }

        // Ato nº 1 — Abertura
        CrmAdminProcessAto::create([
            'admin_process_id' => $processo->id,
            'numero'           => 1,
            'user_id'          => auth()->id(),
            'tipo'             => 'abertura',
            'titulo'           => 'Abertura do Processo',
            'corpo'            => "Processo {$processo->protocolo} instaurado.\n\nTipo: {$processo->tipoLabel()}\nCliente: {$processo->account->name}\nResponsável: " . auth()->user()->name,
            'is_client_visible'=> true,
        ]);

        $this->timeline($processo, 'criado', 'Processo criado', "Tipo: {$processo->tipoLabel()}\nCliente: {$processo->account->name}", true);

        return redirect()->route('crm.admin-processes.show', $processo->id)
            ->with('success', "Processo {$processo->protocolo} criado.");
    }

    // ── Incluir Documento/Ato ──────────────────────────────────

    public function storeAto(Request $request, int $id)
    {
        $processo = CrmAdminProcess::findOrFail($id);

        $data = $request->validate([
            'tipo'              => 'required|string|max:40',
            'titulo'            => 'required|string|max:255',
            'corpo'             => 'nullable|string',
            'is_client_visible' => 'nullable',
            'anexos'            => 'nullable|array',
            'anexos.*'          => 'file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,odt',
        ]);

        $ato = CrmAdminProcessAto::create([
            'admin_process_id' => $processo->id,
            'numero'           => CrmAdminProcessAto::proximoNumero($processo->id),
            'user_id'          => auth()->id(),
            'tipo'             => $data['tipo'],
            'titulo'           => $data['titulo'],
            'corpo'            => $data['corpo'] ?? null,
            'is_client_visible'=> $request->boolean('is_client_visible'),
        ]);

        // Upload de anexos
        if ($request->hasFile('anexos')) {
            $storagePath = 'crm/admin-processes/' . $processo->id;
            foreach ($request->file('anexos') as $file) {
                $name = $ato->numero . '_' . time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs($storagePath, $name, 'public');

                CrmAdminProcessAtoAnexo::create([
                    'ato_id'              => $ato->id,
                    'uploaded_by_user_id' => auth()->id(),
                    'original_name'       => $file->getClientOriginalName(),
                    'disk_path'           => $path,
                    'mime_type'           => $file->getMimeType(),
                    'size_bytes'          => $file->getSize(),
                ]);
            }
        }

        $processo->touch();

        $this->timeline($processo, 'documento_adicionado', "Documento nº {$ato->numero}: {$ato->tipoLabel()}", $ato->titulo, $request->boolean('is_client_visible'));

        return back()->with('success', "Ato nº {$ato->numero} registrado — {$ato->tipoLabel()}");
    }

    // ── Tramitação (movimentar entre advogados) ────────────────

    public function tramitar(Request $request, int $id)
    {
        $processo = CrmAdminProcess::findOrFail($id);

        $data = $request->validate([
            'para_user_id' => 'required|exists:users,id',
            'despacho'     => 'nullable|string',
            'tipo'         => 'nullable|in:tramitacao,devolucao,encaminhamento',
        ]);

        $tipo = $data['tipo'] ?? 'tramitacao';

        CrmAdminProcessTramitacao::create([
            'admin_process_id' => $processo->id,
            'de_user_id'       => auth()->id(),
            'para_user_id'     => $data['para_user_id'],
            'tipo'             => $tipo,
            'despacho'         => $data['despacho'] ?? null,
        ]);

        $paraUser = User::find($data['para_user_id']);

        // Registrar ato de tramitação na árvore
        CrmAdminProcessAto::create([
            'admin_process_id' => $processo->id,
            'numero'           => CrmAdminProcessAto::proximoNumero($processo->id),
            'user_id'          => auth()->id(),
            'tipo'             => 'tramitacao',
            'titulo'           => ucfirst($tipo) . ' para ' . $paraUser->name,
            'corpo'            => $data['despacho'] ?? null,
            'is_client_visible'=> false,
        ]);

        // Atualizar quem está com o processo
        $processo->update(['com_user_id' => $data['para_user_id']]);

        $this->timeline($processo, 'andamento_manual', ucfirst($tipo) . " para {$paraUser->name}", $data['despacho'] ?? null);

        return back()->with('success', "Processo tramitado para {$paraUser->name}.");
    }

    // ── Alterar Status ─────────────────────────────────────────

    public function updateStatus(Request $request, int $id)
    {
        $processo = CrmAdminProcess::findOrFail($id);

        $data = $request->validate([
            'status'          => 'required|in:aberto,em_andamento,aguardando_cliente,aguardando_terceiro,suspenso,concluido,cancelado',
            'motivo'          => 'nullable|string',
            'suspended_until' => 'nullable|date',
        ]);

        $oldStatus = $processo->status;
        $processo->status = $data['status'];

        if ($data['status'] === 'suspenso') {
            $processo->suspended_at     = now();
            $processo->suspended_reason = $data['motivo'] ?? null;
            $processo->suspended_until  = isset($data['suspended_until']) ? Carbon::parse($data['suspended_until']) : null;
        } elseif ($data['status'] === 'concluido') {
            $processo->concluded_at = now();
        } elseif ($data['status'] === 'cancelado') {
            $processo->cancelled_at     = now();
            $processo->cancelled_reason = $data['motivo'] ?? null;
        }

        $processo->save();

        // Ato na árvore
        $label = (new CrmAdminProcess)->fill(['status' => $data['status']])->statusLabel();
        CrmAdminProcessAto::create([
            'admin_process_id' => $processo->id,
            'numero'           => CrmAdminProcessAto::proximoNumero($processo->id),
            'user_id'          => auth()->id(),
            'tipo'             => $data['status'] === 'concluido' ? 'conclusao' : 'despacho',
            'titulo'           => "Status alterado para: {$label}",
            'corpo'            => $data['motivo'] ?? null,
            'is_client_visible'=> in_array($data['status'], ['concluido','suspenso','cancelado','aguardando_cliente']),
        ]);

        $tipoTimeline = match($data['status']) {
            'suspenso'  => 'suspenso',
            'concluido' => 'concluido',
            'cancelado' => 'cancelado',
            default     => 'status_alterado',
        };
        $this->timeline($processo, $tipoTimeline, "Status: {$label}", $data['motivo'] ?? null, in_array($data['status'], ['concluido','suspenso','cancelado','aguardando_cliente']));

        return back()->with('success', 'Status atualizado.');
    }

    // ── Editar dados do processo ───────────────────────────────

    public function edit(int $id)
    {
        $processo = CrmAdminProcess::with('steps')->findOrFail($id);
        $usuarios = User::orderBy('name')->get(['id','name']);
        $accounts = CrmAccount::where('kind','client')->orderBy('name')->get(['id','name']);

        return view('crm.admin-processes.edit', compact('processo','usuarios','accounts'));
    }

    public function update(Request $request, int $id)
    {
        $processo = CrmAdminProcess::findOrFail($id);

        $data = $request->validate([
            'titulo'           => 'required|string|max:255',
            'descricao'        => 'nullable|string',
            'owner_user_id'    => 'required|exists:users,id',
            'orgao_destino'    => 'nullable|string|max:255',
            'numero_externo'   => 'nullable|string|max:100',
            'prazo_estimado'   => 'nullable|date',
            'prazo_final'      => 'nullable|date',
            'prioridade'       => 'required|in:baixa,normal,alta,urgente',
            'valor_honorarios' => 'nullable|numeric',
            'valor_despesas'   => 'nullable|numeric',
        ]);

        $processo->update($data);

        return redirect()->route('crm.admin-processes.show', $processo->id)
            ->with('success', 'Processo atualizado.');
    }

    // ── Etapas (manter para guia lateral) ──────────────────────

    public function updateStep(Request $request, int $processId, int $stepId)
    {
        $step = CrmAdminProcessStep::where('admin_process_id', $processId)->findOrFail($stepId);
        $action = $request->validate(['action' => 'required|in:iniciar,concluir,aguardar,nao_aplicavel,reabrir'])['action'];

        $map = ['iniciar'=>'em_andamento','concluir'=>'concluido','aguardar'=>'aguardando','nao_aplicavel'=>'nao_aplicavel','reabrir'=>'pendente'];
        $step->status = $map[$action];
        if ($action === 'iniciar' && !$step->started_at) $step->started_at = now();
        if ($action === 'concluir') $step->completed_at = now();
        $step->save();

        $processo = CrmAdminProcess::findOrFail($processId);
        $tipoTl = $action === 'concluir' ? 'etapa_concluida' : ($action === 'iniciar' ? 'etapa_iniciada' : 'status_alterado');
        $this->timeline($processo, $tipoTl, "Etapa #{$step->order}: {$step->statusLabel()}", $step->titulo, $step->is_client_visible, $step->id);

        return back()->with('success', 'Etapa atualizada.');
    }

    // ── Checklist (marcar recebido / dispensar) ──────────────────

    public function updateChecklist(Request $request, int $processId, int $itemId)
    {
        $item = CrmAdminProcessChecklist::where('admin_process_id', $processId)->findOrFail($itemId);
        $action = $request->validate(['action' => 'required|in:receber,dispensar,pendente'])['action'];

        if ($action === 'receber') {
            $item->update(['status' => 'recebido', 'received_at' => now()]);
        } elseif ($action === 'dispensar') {
            $item->update([
                'status'          => 'dispensado',
                'dispensed_reason' => $request->input('motivo'),
            ]);
        } else {
            $item->update(['status' => 'pendente', 'received_at' => null, 'dispensed_reason' => null]);
        }

        $processo = CrmAdminProcess::findOrFail($processId);
        $this->timeline($processo, 'documento_adicionado', "Checklist: {$item->nome} — {$item->status}");

        return back()->with('success', "Checklist atualizado: {$item->nome}");
    }

    // ── API: buscar template ───────────────────────────────────

    public function getTemplate(string $tipo)
    {
        $templates = CrmAdminProcessTemplate::getTemplates();
        if (!isset($templates[$tipo])) {
            return response()->json(['error' => 'Template não encontrado'], 404);
        }
        return response()->json($templates[$tipo]);
    }
}
