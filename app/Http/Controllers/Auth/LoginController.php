<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Rate limiting: 5 tentativas por minuto por email+IP
        $throttleKey = Str::transliterate(Str::lower($request->input('email')) . '|' . $request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            AuditLog::register('login_blocked', 'auth', 'Bloqueado por tentativas excessivas: ' . $request->input('email') . ' (IP: ' . $request->ip() . ')');
            return back()->withErrors([
                'email' => 'Muitas tentativas de login. Tente novamente em ' . ceil($seconds / 60) . ' minuto(s).',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            AuditLog::register('login', 'auth', 'Login: ' . Auth::user()->name . ' (' . Auth::user()->role . ')');

            // Registrar ultimo acesso
            Auth::user()->update(['ultimo_acesso' => now()]);

            return redirect()->intended('/avisos');
        }

        RateLimiter::hit($throttleKey, 300); // bloqueio de 5 minutos apos 5 falhas
        AuditLog::register('login_failed', 'auth', 'Falha login: ' . $request->input('email') . ' (IP: ' . $request->ip() . ')');

        return back()->withErrors([
            'email' => 'As credenciais informadas nao correspondem aos nossos registros.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        AuditLog::register('logout', 'auth', 'Logout: ' . (Auth::user()->name ?? 'unknown'));
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }
}
