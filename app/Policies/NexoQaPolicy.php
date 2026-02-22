<?php

namespace App\Policies;

use App\Models\User;

class NexoQaPolicy
{
    /**
     * Roles que podem gerenciar campanhas e ver dados operacionais.
     */
    private const MANAGER_ROLES = ['admin', 'coordenador', 'socio'];

    /**
     * Pode acessar o módulo QA (lista de campanhas, stats agregados).
     */
    public function viewModule(User $user): bool
    {
        return in_array($user->role, self::MANAGER_ROLES, true);
    }

    /**
     * Pode criar/editar/ativar/pausar campanhas.
     */
    public function manageCampaigns(User $user): bool
    {
        return in_array($user->role, ['admin', 'socio'], true);
    }

    /**
     * Pode ver targets operacionais (PENDING/SENT/FAILED/SKIPPED).
     * Não expõe conteúdo de respostas.
     */
    public function viewTargets(User $user): bool
    {
        return in_array($user->role, self::MANAGER_ROLES, true);
    }

    /**
     * Pode ver respostas individuais (com telefone mascarado).
     * SOMENTE admin e coordenador.
     * Advogado NUNCA pode ver respostas individuais.
     */
    public function viewResponses(User $user): bool
    {
        return in_array($user->role, ['admin', 'coordenador'], true);
    }

    /**
     * Pode ver identidade completa do respondente.
     * SOMENTE admin.
     */
    public function viewIdentity(User $user): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Pode ver agregados por advogado (tabela semanal).
     * Manager roles podem ver; advogado só vê seus próprios no GDP.
     */
    public function viewAggregates(User $user): bool
    {
        return in_array($user->role, self::MANAGER_ROLES, true);
    }
}
