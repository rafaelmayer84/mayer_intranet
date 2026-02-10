<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Modulo;
use App\Services\UserSyncService;
use App\Services\PermissaoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuariosController extends Controller
{
    public function __construct(
        protected UserSyncService $userSyncService,
        protected PermissaoService $permissaoService
    ) {}

    /**
     * Lista de usuários
     */
    public function index(Request $request)
    {
        $query = User::query();

        // Filtros
        if ($request->filled('busca')) {
            $busca = $request->input('busca');
            $query->where(function ($q) use ($busca) {
                $q->where('name', 'like', "%{$busca}%")
                  ->orWhere('email', 'like', "%{$busca}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $query->where('ativo', $request->input('status') === 'ativo');
        }

        $usuarios = $query->orderBy('name')->paginate(20);
        $estatisticas = $this->userSyncService->getEstatisticas();

        return view('admin.usuarios.index', compact('usuarios', 'estatisticas'));
    }

    /**
     * Formulário de criação
     */
    public function create()
    {
        $roles = User::ROLES;
        return view('admin.usuarios.form', compact('roles'));
    }

    /**
     * Salvar novo usuário
    /**
     * Salvar novo usuário
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'role' => ['required', Rule::in(['admin', 'coordenador', 'socio'])],
            'telefone' => 'nullable|string|max:20',
            'cargo' => 'nullable|string|max:100',
            'ativo' => 'boolean',
        ]);

        // Gera senha temporária
        $senhaTemporaria = Str::random(12);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($senhaTemporaria),
            'role' => $validated['role'],
            'telefone' => $validated['telefone'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'ativo' => $validated['ativo'] ?? true,
        ]);

        // Aplica permissões padrão
        $this->permissaoService->aplicarPermissoesPadrao($user);

        // Envia email de boas-vindas com a senha
        try {
            \Illuminate\Support\Facades\Mail::to($user->email)
                ->send(new \App\Mail\BoasVindasUsuario($user, $senhaTemporaria));
            
            $mensagem = "Usuário criado com sucesso! Email de boas-vindas enviado para {$user->email}";
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Erro ao enviar email de boas-vindas: ' . $e->getMessage());
            $mensagem = "Usuário criado com sucesso! Houve erro ao enviar o email. Senha temporária: {$senhaTemporaria}";
        }

        return redirect()
            ->route('admin.usuarios.show', $user)
            ->with('success', $mensagem);
    }

    public function show(User $usuario)
    {
        $matrizPermissoes = $this->permissaoService->getMatrizPermissoes($usuario);
        $roles = User::ROLES;

        return view('admin.usuarios.show', compact('usuario', 'matrizPermissoes', 'roles'));
    }

    /**
     * Formulário de edição
     */
    public function edit(User $usuario)
    {
        $roles = User::ROLES;
        $matrizPermissoes = $this->permissaoService->getMatrizPermissoes($usuario);

        return view('admin.usuarios.form', compact('usuario', 'roles', 'matrizPermissoes'));
    }

    /**
     * Atualizar usuário
     */
    public function update(Request $request, User $usuario)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($usuario->id)],
            'role' => ['required', Rule::in(['admin', 'coordenador', 'socio'])],
            'telefone' => 'nullable|string|max:20',
            'cargo' => 'nullable|string|max:100',
            'ativo' => 'boolean',
        ]);

        $roleAnterior = $usuario->role;

        $usuario->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'telefone' => $validated['telefone'] ?? null,
            'cargo' => $validated['cargo'] ?? null,
            'ativo' => $validated['ativo'] ?? false,
        ]);

        // Se o papel mudou, reaplica permissões padrão
        if ($roleAnterior !== $validated['role']) {
            $this->permissaoService->aplicarPermissoesPadrao($usuario);
        }

        return redirect()
            ->route('admin.usuarios.show', $usuario)
            ->with('success', 'Usuário atualizado com sucesso!');
    }

    /**
     * Resetar senha
     */
    public function resetarSenha(User $usuario)
    {
        $novaSenha = Str::random(12);
        $usuario->update(['password' => Hash::make($novaSenha)]);

        return redirect()
            ->route('admin.usuarios.show', $usuario)
            ->with('success', "Senha resetada com sucesso! Nova senha: {$novaSenha}")
            ->with('senha_temporaria', $novaSenha);
    }

    /**
     * Ativar/Desativar usuário
     */
    public function toggleStatus(User $usuario)
    {
        // Não permitir desativar a si mesmo
        if ($usuario->id === auth()->id()) {
            return redirect()
                ->back()
                ->with('error', 'Você não pode desativar sua própria conta.');
        }

        $novoStatus = !$usuario->ativo;
        $usuario->update(['ativo' => $novoStatus]);

        $mensagem = $novoStatus ? 'Usuário ativado com sucesso!' : 'Usuário desativado com sucesso!';

        return redirect()
            ->back()
            ->with('success', $mensagem);
    }

    /**
     * Página de sincronização com DataJuri
     */
    public function sincronizacao()
    {
        $usuariosDataJuri = $this->userSyncService->listarUsuariosDataJuri();
        $estatisticas = $this->userSyncService->getEstatisticas();

        return view('admin.usuarios.sincronizacao', compact('usuariosDataJuri', 'estatisticas'));
    }

    /**
     * Ativar usuário do DataJuri
     */
    public function ativarDataJuri(Request $request)
    {
        $validated = $request->validate([
            'datajuri_id' => 'required|integer',
            'nome' => 'required|string|max:255',
            'email' => 'required|email',
            'role' => ['required', Rule::in(['admin', 'coordenador', 'socio'])],
            'telefone' => 'nullable|string|max:20',
            'cargo' => 'nullable|string|max:100',
        ]);

        $resultado = $this->userSyncService->ativarUsuarioDataJuri(
            $validated['datajuri_id'],
            $validated['nome'],
            $validated['email'],
            $validated['role'],
            $validated['telefone'] ?? null,
            $validated['cargo'] ?? null
        );

        if ($resultado['success']) {
            $mensagem = $resultado['message'];
            if (!empty($resultado['senha_temporaria'])) {
                $mensagem .= " Senha temporária: {$resultado['senha_temporaria']}";
            }
            return redirect()
                ->route('admin.usuarios.show', $resultado['user'])
                ->with('success', $mensagem)
                ->with('senha_temporaria', $resultado['senha_temporaria'] ?? null);
        }

        return redirect()
            ->back()
            ->with('error', $resultado['message']);
    }

    /**
     * Salvar permissões personalizadas
     */
    public function salvarPermissoes(Request $request, User $usuario)
    {
        // Admin não precisa de permissões específicas
        if ($usuario->isAdmin()) {
            return redirect()
                ->back()
                ->with('info', 'Administradores têm acesso total - permissões não são necessárias.');
        }

        $permissoes = $request->input('permissoes', []);
        $this->permissaoService->salvarPermissoesEmLote($usuario, $permissoes);

        return redirect()
            ->route('admin.usuarios.show', $usuario)
            ->with('success', 'Permissões atualizadas com sucesso!');
    }

    /**
     * Aplicar permissões padrão do papel
     */
    public function aplicarPermissoesPadrao(User $usuario)
    {
        $this->permissaoService->aplicarPermissoesPadrao($usuario);

        return redirect()
            ->route('admin.usuarios.show', $usuario)
            ->with('success', 'Permissões padrão aplicadas com sucesso!');
    }
}
