<?php
namespace App\Http\Controllers;

use App\Models\Crm\CrmServiceRequest;
use App\Models\Crm\CrmAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChamadosController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdmin = in_array($user->role, ['admin', 'socio', 'coordenador']);

        $query = CrmServiceRequest::with(['account', 'requestedBy', 'assignedTo']);

        // Filtro: admin vê todos, demais vêem os próprios
        if (!$isAdmin) {
            $query->where(function ($q) use ($user) {
                $q->where('requested_by_user_id', $user->id)
                  ->orWhere('assigned_to_user_id', $user->id);
            });
        }

        // Filtro por status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filtro por categoria
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        // Filtro por tipo (cliente ou operacional)
        if ($request->input('tipo') === 'cliente') {
            $query->whereNotNull('account_id');
        } elseif ($request->input('tipo') === 'operacional') {
            $query->whereNull('account_id');
        }

        $chamados = $query->orderByDesc('created_at')->paginate(20);

        // Contadores
        $baseQuery = CrmServiceRequest::query();
        if (!$isAdmin) {
            $baseQuery->where(function ($q) use ($user) {
                $q->where('requested_by_user_id', $user->id)
                  ->orWhere('assigned_to_user_id', $user->id);
            });
        }
        $contadores = [
            'total'     => (clone $baseQuery)->count(),
            'abertas'   => (clone $baseQuery)->whereIn('status', ['aberto', 'em_andamento', 'aguardando_aprovacao', 'aprovado'])->count(),
            'concluidas'=> (clone $baseQuery)->where('status', 'concluido')->count(),
            'sla_risco' => (clone $baseQuery)->whereIn('status', ['aberto', 'em_andamento', 'aguardando_aprovacao', 'aprovado'])->whereNotNull('sla_deadline')->where('sla_deadline', '<', now())->count(),
        ];

        $categorias = CrmServiceRequest::categorias();
        $users = User::orderBy('name')->get(['id', 'name']);
        $accounts = CrmAccount::where('lifecycle', 'client')->orderBy('name')->get(['id', 'name']);

        return view('chamados.index', compact('chamados', 'contadores', 'categorias', 'users', 'accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'category'            => 'required|string|max:60',
            'subject'             => 'required|string|max:255',
            'description'         => 'required|string|max:3000',
            'priority'            => 'required|in:baixa,normal,alta,urgente',
            'assigned_to_user_id' => 'nullable|exists:users,id',
            'account_id'          => 'nullable|exists:crm_accounts,id',
            'desired_deadline'    => 'nullable|date|after:today',
            'cost_center'         => 'nullable|in:escritorio,cliente,projeto',
            'estimated_value'     => 'nullable|numeric|min:0',
            'impact'              => 'nullable|in:individual,equipe,escritorio,cliente',
            'attachments.*'       => 'nullable|file|max:10240',
        ]);

        // Upload de anexos
        $attachmentPaths = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $attachmentPaths[] = $file->store('chamados', 'public');
            }
        }

        $requiresApproval = CrmServiceRequest::categoriaRequerAprovacao($request->category);

        $sr = CrmServiceRequest::create([
            'account_id'           => $request->account_id ?: null,
            'category'             => $request->category,
            'subject'              => $request->subject,
            'description'          => $request->description,
            'priority'             => $request->priority,
            'status'               => 'aberto',
            'requested_by_user_id' => auth()->id(),
            'assigned_to_user_id'  => $request->assigned_to_user_id,
            'requires_approval'    => $requiresApproval,
            'assigned_at'          => $request->assigned_to_user_id ? now() : null,
            'desired_deadline'     => $request->desired_deadline,
            'cost_center'          => $request->cost_center,
            'estimated_value'      => $request->estimated_value,
            'impact'               => $request->impact,
        ]);

        // Auto-aprovação se admin/sócio
        if ($requiresApproval && in_array(auth()->user()->role, ['admin', 'socio'])) {
            $sr->update([
                'status'              => 'aprovado',
                'approved_by_user_id' => auth()->id(),
                'approved_at'         => now(),
            ]);
        }

        // Triagem IA silenciosa (async)
        dispatch(function () use ($sr) {
            try {
                app(\App\Services\ChamadoSlaService::class)->analyze($sr);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[SIATE] Triagem falhou: ' . $e->getMessage());
            }
        })->afterResponse();

        return back()->with('success', 'Chamado #' . $sr->id . ' registrado.');
    }
}
