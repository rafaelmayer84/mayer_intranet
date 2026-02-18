<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    // Constantes de papel
    const ROLE_ADMIN = 'admin';
    const ROLE_COORDENADOR = 'coordenador';
    const ROLE_SOCIO = 'socio';

    const ROLES = [
        self::ROLE_ADMIN => 'Administrador',
        self::ROLE_COORDENADOR => 'Coordenador',
        self::ROLE_SOCIO => 'Sócio',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'datajuri_id',
        'datajuri_proprietario_id',
        'ativo',
        'telefone',
        'cargo',
        'ultimo_acesso',
        'password_changed_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
            'ultimo_acesso' => 'datetime',
        ];
    }

    /**
     * Relacionamento com Advogado
     */
    public function advogado()
    {
        return $this->hasOne(Advogado::class, 'user_id');
    }

    /**
     * Permissões do usuário por módulo
     */
    public function permissions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\UserPermission::class);
    }

    /**
     * Verifica se é administrador
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Verifica se é coordenador
     */
    public function isCoordenador(): bool
    {
        return $this->role === self::ROLE_COORDENADOR;
    }

    /**
     * Verifica se é sócio
     */
    public function isSocio(): bool
    {
        return $this->role === self::ROLE_SOCIO;
    }

    /**
     * Retorna o nome do papel
     */
    public function getRoleNomeAttribute(): string
    {
        return self::ROLES[$this->role] ?? 'Desconhecido';
    }

    /**
     * Verifica se o usuário pode acessar um módulo
     */
    public function podeAcessar(string $moduloSlug): bool
    {
        // Admin tem acesso total
        if ($this->isAdmin()) {
            return true;
        }
        
        $modulo = \App\Models\Modulo::porSlug($moduloSlug);
        if (!$modulo) {
            return false;
        }
        
        $permission = $this->permissions()->where('modulo_id', $modulo->id)->first();
        
        return $permission && $permission->pode_visualizar;
    }

    /**
     * Verifica se o usuário pode editar em um módulo
     */
    public function podeEditar(string $moduloSlug): bool
    {
        // Admin tem acesso total
        if ($this->isAdmin()) {
            return true;
        }
        
        $modulo = \App\Models\Modulo::porSlug($moduloSlug);
        if (!$modulo) {
            return false;
        }
        
        $permission = $this->permissions()->where('modulo_id', $modulo->id)->first();
        
        return $permission && $permission->pode_editar;
    }

    /**
     * Verifica se o usuário pode executar ações em um módulo (ex: sincronizar)
     */
    public function podeExecutar(string $moduloSlug): bool
    {
        // Admin tem acesso total
        if ($this->isAdmin()) {
            return true;
        }
        
        $modulo = \App\Models\Modulo::porSlug($moduloSlug);
        if (!$modulo) {
            return false;
        }
        
        $permission = $this->permissions()->where('modulo_id', $modulo->id)->first();
        
        return $permission && $permission->pode_executar;
    }

    /**
     * Retorna o escopo de visualização para um módulo
     * 'proprio' = só vê seus dados
     * 'equipe' = vê dados da equipe
     * 'todos' = vê tudo
     */
    public function escopoVisualizacao(string $moduloSlug): string
    {
        // Admin vê tudo
        if ($this->isAdmin()) {
            return 'todos';
        }
        
        // Coordenador vê equipe por padrão
        if ($this->isCoordenador()) {
            $modulo = \App\Models\Modulo::porSlug($moduloSlug);
            if ($modulo) {
                $permission = $this->permissions()->where('modulo_id', $modulo->id)->first();
                return $permission ? $permission->escopo : 'equipe';
            }
            return 'equipe';
        }
        
        // Sócio - verificar permissão específica
        $modulo = \App\Models\Modulo::porSlug($moduloSlug);
        if ($modulo) {
            $permission = $this->permissions()->where('modulo_id', $modulo->id)->first();
            return $permission ? $permission->escopo : 'proprio';
        }
        
        return 'proprio';
    }

    /**
     * Retorna todas as permissões do usuário agrupadas por módulo
     */
    public function getPermissoesAgrupadas(): array
    {
        $permissoes = [];
        
        $userPermissions = $this->permissions()->with('modulo')->get();
        
        foreach ($userPermissions as $up) {
            if ($up->modulo) {
                $permissoes[$up->modulo->grupo][$up->modulo->slug] = [
                    'modulo' => $up->modulo,
                    'permissoes' => $up->permissoesAtivas(),
                    'escopo' => $up->escopo,
                ];
            }
        }
        
        return $permissoes;
    }

    /**
     * Aplica permissões padrão baseadas no papel
     */
    public function aplicarPermissoesPadrao(): void
    {
        $service = app(\App\Services\PermissaoService::class);
        $service->aplicarPermissoesPadrao($this);
    }

    /**
     * Apenas usuários ativos
     */
    public function scopeAtivos($query)
    {
        return $query->where('ativo', true);
    }

    /**
     * Filtrar por papel
     */
    public function scopePorRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Usuários vinculados ao DataJuri
     */
    public function scopeVinculadosDataJuri($query)
    {
        return $query->whereNotNull('datajuri_id');
    }

    /**
     * Grupos de manuais normativos atribuídos ao usuário.
     */
    public function manuaisGrupos()
    {
        return $this->belongsToMany(
            \App\Models\ManualGrupo::class,
            'manuais_grupo_user',
            'user_id',
            'grupo_id'
        )->withTimestamps();
    }

    /**
     * Verifica se a senha nunca foi alterada (primeiro acesso)
     */
    public function senhaExpirada(): bool
    {
        return is_null($this->password_changed_at);
    }

    /**
     * Dias restantes para expirar a senha (30 dias)
     */
    public function diasParaExpirarSenha(): ?int
    {
        if (is_null($this->password_changed_at)) {
            return 0;
        }

        $expira = \Carbon\Carbon::parse($this->password_changed_at)->addDays(30);
        $dias = (int) now()->diffInDays($expira, false);

        return max($dias, 0);
    }

}
