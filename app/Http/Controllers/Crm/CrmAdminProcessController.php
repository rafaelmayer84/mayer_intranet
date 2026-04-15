<?php

/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║  MÓDULO: Processos Administrativos / Extrajudiciais                        ║
 * ║  Versão: 1.1.0                                                             ║
 * ║  Atualizado em: 2026-04-15                                                 ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║                                                                            ║
 * ║  OBJETIVO                                                                  ║
 * ║  Gerenciar processos administrativos e extrajudiciais (cartório, órgãos     ║
 * ║  públicos) dentro do CRM. Foco em movimentação processual, não em          ║
 * ║  documentação avulsa.                                                      ║
 * ║                                                                            ║
 * ║  ESTRUTURA DE ARQUIVOS                                                     ║
 * ║  Controller .. app/Http/Controllers/Crm/CrmAdminProcessController.php      ║
 * ║  Models (9) .. app/Models/Crm/CrmAdminProcess*.php                         ║
 * ║    - CrmAdminProcess          Processo principal (soft deletes)             ║
 * ║    - CrmAdminProcessTemplate  Templates por tipo de processo               ║
 * ║    - CrmAdminProcessStep      Etapas do guia lateral                       ║
 * ║    - CrmAdminProcessAto       Movimentações (árvore do processo)            ║
 * ║    - CrmAdminProcessAtoAnexo  Arquivos anexados às movimentações            ║
 * ║    - CrmAdminProcessTimeline  Linha do tempo (audit log automático)         ║
 * ║    - CrmAdminProcessTramitacao Tramitação entre advogados                  ║
 * ║    - CrmAdminProcessDocument  Documentos gerais (não usado na v1)          ║
 * ║    - CrmAdminProcessChecklist Checklist de documentos necessários           ║
 * ║  Migrations .. database/migrations/2026_04_15_1*.php (2 arquivos)          ║
 * ║  Views (4) ... resources/views/crm/admin-processes/                        ║
 * ║    - index.blade.php   Listagem com filtros e stats                        ║
 * ║    - create.blade.php  Criação com seleção de template                     ║
 * ║    - show.blade.php    Detalhe: árvore + side panels + modais              ║
 * ║    - edit.blade.php    Edição de dados básicos                             ║
 * ║  Rotas ....... routes/_crm_routes.php (prefixo: crm/processos-admin)       ║
 * ║  Seeder ...... database/seeders/CrmAdminProcessSeeder.php                  ║
 * ║  Storage ..... storage/app/public/crm/admin-processes/{processo_id}/        ║
 * ║                                                                            ║
 * ║  ROTAS (13)                                                                ║
 * ║  GET    /                        index       Listagem                      ║
 * ║  GET    /criar                   create      Formulário de criação         ║
 * ║  POST   /                        store       Salvar novo processo          ║
 * ║  GET    /api/template/{tipo}     getTemplate API: dados do template        ║
 * ║  GET    /{id}                    show        Detalhe (árvore de atos)      ║
 * ║  GET    /{id}/editar             edit        Formulário de edição          ║
 * ║  PUT    /{id}                    update      Salvar edição                 ║
 * ║  POST   /{id}/status             updateStatus Alterar status               ║
 * ║  POST   /{id}/ato                storeAto    Registrar movimentação        ║
 * ║  POST   /{id}/tramitar           tramitar    Tramitar para advogado        ║
 * ║  POST   /{id}/etapas/{stepId}    updateStep  Atualizar etapa              ║
 * ║  POST   /{id}/checklist/{itemId} updateChecklist Marcar checklist          ║
 * ║                                                                            ║
 * ║  FLUXO DE MOVIMENTAÇÃO                                                     ║
 * ║  O botão "Movimentar" abre modal com tipos padronizados (28 tipos).        ║
 * ║  O advogado seleciona o tipo e escreve observações. O título do ato é      ║
 * ║  gerado automaticamente a partir do tipo. Anexos são opcionais.            ║
 * ║  Tipos definidos em CrmAdminProcessAto::MOVIMENTACOES                      ║
 * ║  Tipos manuais: CrmAdminProcessAto::movimentacoesManuais()                 ║
 * ║  Tipos automáticos (sistema): abertura, tramitacao, conclusao              ║
 * ║                                                                            ║
 * ║  Categorias de movimentação:                                               ║
 * ║    Atos internos ........ despacho, parecer, nota_interna                  ║
 * ║    Docs elaborados ...... juntada, elaboracao_minuta, _peticao,            ║
 * ║                           _contrato, _procuracao, _escritura,              ║
 * ║                           _oficio, _requerimento                           ║
 * ║    Atos externos ........ protocolo_orgao, diligencia_externa,             ║
 * ║                           certidao_obtida, recebimento_documento,          ║
 * ║                           assinatura, registro_cartorio, averbacao         ║
 * ║    Financeiro ........... pagamento_taxa, comprovante_pagamento            ║
 * ║    Comunicação .......... comunicacao_cliente, _terceiro,                  ║
 * ║                           envio_documento                                  ║
 * ║    Aguardando ........... aguardando_cliente, _orgao, _terceiro            ║
 * ║                                                                            ║
 * ║  ÁRVORE DO PROCESSO (show.blade.php)                                       ║
 * ║  Tabela com 3 colunas:                                                     ║
 * ║    1. Movimentação — nº sequencial, tipo (badge), data, autor              ║
 * ║    2. Conteúdo — título + corpo sempre visíveis (sem colapsar)             ║
 * ║    3. Documentos — preview inline (PDF via iframe, imagens via <img>)      ║
 * ║                                                                            ║
 * ║  TIMELINE (automática)                                                     ║
 * ║  Cada ação gera entrada via método privado timeline():                     ║
 * ║    storeAto     → documento_adicionado                                     ║
 * ║    tramitar     → andamento_manual                                         ║
 * ║    updateStatus → status_alterado / suspenso / concluido / cancelado       ║
 * ║    updateStep   → etapa_iniciada / etapa_concluida                         ║
 * ║    updateChecklist → documento_adicionado                                  ║
 * ║                                                                            ║
 * ║  TRAMITAÇÃO                                                                ║
 * ║  Passa o processo de um advogado para outro (campo com_user_id).           ║
 * ║  Cria ato automático tipo 'tramitacao' na árvore + entrada na timeline.    ║
 * ║  Tipos: tramitacao, devolucao, encaminhamento.                             ║
 * ║                                                                            ║
 * ║  TEMPLATES DE PROCESSO                                                     ║
 * ║  8 tipos: transferencia_imovel, inventario_extrajudicial,                  ║
 * ║  divorcio_extrajudicial, abertura_empresa, usucapiao,                      ║
 * ║  retificacao_registro, dissolucao_sociedade, regularizacao_fundiaria       ║
 * ║  + tipo "outro" (totalmente customizado)                                   ║
 * ║  Cada template define etapas e checklist pré-preenchidos.                  ║
 * ║  Definidos em CrmAdminProcessTemplate::getTemplates()                      ║
 * ║                                                                            ║
 * ║  STATUS DO PROCESSO                                                        ║
 * ║  rascunho → aberto → em_andamento → [aguardando_* | suspenso]             ║
 * ║                                    → concluido | cancelado                 ║
 * ║                                                                            ║
 * ║  PROTOCOLO                                                                 ║
 * ║  Formato: ADM-YYYY-NNNN (gerado em CrmAdminProcess::gerarProtocolo())     ║
 * ║                                                                            ║
 * ║  SIDE PANELS (show.blade.php, botões no header)                            ║
 * ║    Tramitações — histórico de quem passou para quem                        ║
 * ║    Etapas — guia lateral com progresso e botões de ação                    ║
 * ║    Checklist — documentos necessários (pendente/recebido/dispensado)       ║
 * ║    Timeline — log cronológico de todas as ações                            ║
 * ║                                                                            ║
 * ║  MIDDLEWARE                                                                ║
 * ║  auth, modulo:operacional.crm,visualizar                                  ║
 * ║                                                                            ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  CHANGELOG                                                                 ║
 * ║  v1.0.0  2026-04-15  CRUD completo, árvore com collapse, 21 tipos ato     ║
 * ║  v1.1.0  2026-04-15  Movimentações padronizadas (28 tipos fixos),          ║
 * ║                       título auto-gerado, botão "Movimentar", árvore em    ║
 * ║                       3 colunas com preview inline de documentos,           ║
 * ║                       fix: modais Alpine.js dentro do escopo x-data        ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 */

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
use App\Services\Crm\CrmAdminProcessAlertService;
use App\Services\Crm\CrmAdminProcessChecklistAiService;
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

    // ── Geração de etapas via IA ─────────────────────────────

    private function gerarEtapasViaIa(CrmAdminProcess $processo, array $checklistItems, ?CrmAdminProcessChecklistAiService $service = null): void
    {
        $service = $service ?? app(CrmAdminProcessChecklistAiService::class);

        if (!$service->isAvailable()) return;

        $result = $service->gerarEtapas(
            checklistItems: $checklistItems,
            tipoProcesso:   $processo->tipoLabel(),
            tituloProcesso: $processo->titulo,
        );

        if (!$result['success'] || empty($result['steps'])) return;

        $ownerUserId = $processo->owner_user_id ?? (auth()->id() ?? 1);

        foreach ($result['steps'] as $i => $step) {
            CrmAdminProcessStep::create([
                'admin_process_id'    => $processo->id,
                'order'               => $i + 1,
                'titulo'              => $step['titulo'] ?? 'Etapa ' . ($i + 1),
                'tipo'                => $step['tipo'] ?? 'interno',
                'deadline_days'       => $step['deadline_days'] ?? null,
                'status'              => 'pendente',
                'responsible_user_id' => $ownerUserId,
            ]);
        }

        $totalEtapas = count($result['steps']);
        $this->timeline($processo, 'etapa_iniciada', "Etapas geradas automaticamente — {$totalEtapas} fase(s) criada(s)");
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

        // Gerar etapas via IA se tem checklist mas nenhuma etapa veio do template
        $checklistItems = $data['checklist_items'] ?? [];
        if (!empty($checklistItems) && empty($data['steps'])) {
            $this->gerarEtapasViaIa($processo, $checklistItems);
        }

        return redirect()->route('crm.admin-processes.show', $processo->id)
            ->with('success', "Processo {$processo->protocolo} criado.");
    }

    // ── Incluir Documento/Ato ──────────────────────────────────

    public function storeAto(Request $request, int $id)
    {
        $processo = CrmAdminProcess::findOrFail($id);

        $tiposValidos = implode(',', array_keys(CrmAdminProcessAto::movimentacoesManuais()));

        $data = $request->validate([
            'tipo'              => "required|in:{$tiposValidos}",
            'corpo'             => 'nullable|string',
            'is_client_visible' => 'nullable',
            'anexos'            => 'nullable|array',
            'anexos.*'          => 'file|max:20480|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,odt',
        ]);

        $titulo = CrmAdminProcessAto::MOVIMENTACOES[$data['tipo']] ?? ucfirst($data['tipo']);

        $ato = CrmAdminProcessAto::create([
            'admin_process_id' => $processo->id,
            'numero'           => CrmAdminProcessAto::proximoNumero($processo->id),
            'user_id'          => auth()->id(),
            'tipo'             => $data['tipo'],
            'titulo'           => $titulo,
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

        app(CrmAdminProcessAlertService::class)->notificarNovoAto($processo, $ato->tipoLabel(), auth()->id());

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

        app(CrmAdminProcessAlertService::class)->notificarTramitacao(
            $processo, auth()->user(), $paraUser, $data['despacho'] ?? null
        );

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

        app(CrmAdminProcessAlertService::class)->notificarMudancaStatus($processo, $oldStatus, auth()->id());

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

        if ($action === 'concluir') {
            app(CrmAdminProcessAlertService::class)->notificarEtapaConcluida($processo, $step, auth()->id());
        }

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

    // ── API: gerar checklist com IA ────────────────────────────

    public function checklistIa(Request $request)
    {
        $data = $request->validate([
            'tipo'        => 'required|string',
            'titulo'      => 'required|string|max:255',
            'descricao'   => 'nullable|string',
            'account_id'  => 'nullable|exists:crm_accounts,id',
        ]);

        $tipoLabel = (new CrmAdminProcess)->fill(['tipo' => $data['tipo']])->tipoLabel();
        $clienteNome = '';
        if (!empty($data['account_id'])) {
            $clienteNome = CrmAccount::find($data['account_id'])?->name ?? '';
        }

        $service = app(CrmAdminProcessChecklistAiService::class);

        if (!$service->isAvailable()) {
            return response()->json(['success' => false, 'error' => 'IA não disponível no momento.'], 503);
        }

        $result = $service->gerarChecklist(
            tipo:        $data['tipo'],
            tipoLabel:   $tipoLabel,
            titulo:      $data['titulo'],
            descricao:   $data['descricao'] ?? '',
            clienteNome: $clienteNome,
        );

        return response()->json($result);
    }

    // ── Importar checklist de arquivo Word ────────────────────

    public function importChecklistDocx(Request $request, int $id)
    {
        $processo = CrmAdminProcess::findOrFail($id);

        $request->validate([
            'docx' => 'required|file|max:10240',
        ]);

        $file = $request->file('docx');
        $ext  = strtolower($file->getClientOriginalExtension()) ?: 'docx';
        $path = $file->storeAs('tmp/checklist-imports', uniqid('cl_') . '.' . $ext);
        $fullPath = \Illuminate\Support\Facades\Storage::path($path);

        $service = app(CrmAdminProcessChecklistAiService::class);
        $result  = $service->extrairChecklistDeDocx($fullPath, $processo->titulo);

        // Remove arquivo temporário
        \Illuminate\Support\Facades\Storage::delete($path);

        if (!$result['success']) {
            return back()->with('error', 'Falha ao importar checklist: ' . $result['error']);
        }

        foreach ($result['items'] as $item) {
            if (empty($item)) continue;
            $existe = CrmAdminProcessChecklist::where('admin_process_id', $processo->id)
                ->where('nome', $item)->exists();
            if ($existe) continue;

            CrmAdminProcessChecklist::create([
                'admin_process_id' => $processo->id,
                'nome'             => $item,
                'status'           => 'pendente',
            ]);
        }

        $total = count($result['items']);
        $this->timeline($processo, 'documento_adicionado', "Checklist importado do Word — {$total} item(ns) adicionado(s)");

        // Gerar etapas automaticamente se o processo não tem nenhuma
        if ($processo->steps()->count() === 0) {
            $this->gerarEtapasViaIa($processo, $result['items'], $service);
        }

        return back()->with('success', "{$total} item(ns) importados do Word para o checklist.");
    }

    // ── Adicionar item de checklist manualmente ────────────────

    public function storeChecklistItem(Request $request, int $id)
    {
        $processo = CrmAdminProcess::findOrFail($id);

        $data = $request->validate([
            'nome'      => 'required|string|max:255',
            'descricao' => 'nullable|string|max:500',
        ]);

        CrmAdminProcessChecklist::create([
            'admin_process_id' => $processo->id,
            'nome'             => $data['nome'],
            'descricao'        => $data['descricao'] ?? null,
            'status'           => 'pendente',
        ]);

        $this->timeline($processo, 'documento_adicionado', "Checklist: item adicionado — {$data['nome']}");

        return back()->with('success', "Item adicionado ao checklist: {$data['nome']}");
    }
}
