<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'user_name', 'user_role', 'action', 'module',
        'description', 'ip_address', 'user_agent', 'route', 'method', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // ============================================================
    // HELPER PRINCIPAL — Chamar de qualquer lugar do sistema
    // ============================================================
    // AuditLog::register('login', 'auth', 'Login realizado com sucesso');
    // AuditLog::register('access_denied', 'gdp', 'Tentou acessar /gdp/acordo');
    // AuditLog::register('update', 'gdp.metas', 'Salvou acordo user #7');
    // AuditLog::register('sync', 'datajuri', 'Sync manual Movimento');
    // ============================================================
    public static function register(string $action, ?string $module = null, ?string $description = null, ?int $userId = null): void
    {
        try {
            $user = Auth::user();

            static::create([
                'user_id'    => $userId ?? ($user->id ?? null),
                'user_name'  => $user->name ?? null,
                'user_role'  => $user->role ?? null,
                'action'     => $action,
                'module'     => $module,
                'description'=> $description,
                'ip_address' => Request::ip(),
                'user_agent' => substr(Request::userAgent() ?? '', 0, 255),
                'route'      => substr(Request::path() ?? '', 0, 255),
                'method'     => Request::method(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Audit log NUNCA pode derrubar o sistema
            \Log::warning('AuditLog::register falhou: ' . $e->getMessage());
        }
    }

    // Relacionamento
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scope para limpeza
    public function scopeOlderThan($query, int $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }
}
