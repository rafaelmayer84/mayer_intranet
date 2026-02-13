<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    /**
     * Tela Meu Perfil - visualizar dados e alterar senha
     */
    public function index()
    {
        $usuario = Auth::user();
        $diasRestantes = $usuario->diasParaExpirarSenha();
        $senhaExpirada = $usuario->senhaExpirada();

        return view('perfil.index', compact('usuario', 'diasRestantes', 'senhaExpirada'));
    }

    /**
     * Alterar senha
     */
    public function alterarSenha(Request $request)
    {
        $request->validate([
            'senha_atual' => 'required',
            'nova_senha' => ['required', 'confirmed', 'min:8', 'different:senha_atual'],
        ], [
            'senha_atual.required' => 'Informe a senha atual.',
            'nova_senha.required' => 'Informe a nova senha.',
            'nova_senha.confirmed' => 'A confirmação da nova senha não confere.',
            'nova_senha.min' => 'A nova senha deve ter no mínimo 8 caracteres.',
            'nova_senha.different' => 'A nova senha deve ser diferente da atual.',
        ]);

        $usuario = Auth::user();

        // Verificar senha atual
        if (!Hash::check($request->senha_atual, $usuario->password)) {
            return back()->withErrors(['senha_atual' => 'A senha atual está incorreta.']);
        }

        // Atualizar senha
        $usuario->update([
            'password' => Hash::make($request->nova_senha),
            'password_changed_at' => now(),
        ]);

        return redirect()->route('perfil.index')
            ->with('success', 'Senha alterada com sucesso! Próxima troca em 30 dias.');
    }
}
