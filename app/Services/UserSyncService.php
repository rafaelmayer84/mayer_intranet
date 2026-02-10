<?php

namespace App\Services;

use App\Models\User;
use App\Models\IntegrationLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserSyncService
{
    protected DataJuriService $dataJuriService;
    protected PermissaoService $permissaoService;

    public function __construct(DataJuriService $dataJuriService, PermissaoService $permissaoService)
    {
        $this->dataJuriService = $dataJuriService;
        $this->permissaoService = $permissaoService;
    }

    /**
     * Sincroniza usuários/proprietários do DataJuri
     * 
     * NÃO cria usuários automaticamente - apenas lista disponíveis para ativação
     */
    public function listarUsuariosDataJuri(): array
    {
        try {
            // Busca módulo Usuario do DataJuri
            $response = $this->dataJuriService->buscarModuloPaginado('Usuario', 1, 200);
            
            if (!isset($response['rows'])) {
                Log::warning('UserSyncService: Resposta sem rows', ['response' => $response]);
                return [];
            }

            $usuarios = [];
            foreach ($response['rows'] as $row) {
                $usuarios[] = [
                    'datajuri_id' => $row['id'] ?? null,
                    'nome' => $row['nome'] ?? $row['nomeCompleto'] ?? 'Sem nome',
                    'email' => $row['email'] ?? null,
                    'telefone' => $row['telefone'] ?? null,
                    'ativo_datajuri' => ($row['ativo'] ?? 'Sim') === 'Sim',
                    'cargo' => $row['cargo'] ?? $row['funcao'] ?? null,
                    'ja_vinculado' => User::where('datajuri_id', $row['id'] ?? 0)->exists(),
                ];
            }

            $this->registrarLog('listar', 'sucesso', count($usuarios) . ' usuários encontrados no DataJuri');

            return $usuarios;

        } catch (\Exception $e) {
            Log::error('UserSyncService: Erro ao listar usuários', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->registrarLog('listar', 'erro', $e->getMessage());

            return [];
        }
    }

    /**
     * Ativa um usuário do DataJuri na Intranet
     * 
     * Se já existir usuário com mesmo email, vincula.
     * Se não existir, cria novo usuário.
     */
    public function ativarUsuarioDataJuri(
        int $datajuriId, 
        string $nome, 
        string $email, 
        string $role = 'socio',
        ?string $telefone = null,
        ?string $cargo = null
    ): array {
        try {
            // Verifica se já existe usuário vinculado a este datajuri_id
            $existente = User::where('datajuri_id', $datajuriId)->first();
            if ($existente) {
                return [
                    'success' => false,
                    'message' => 'Este usuário do DataJuri já está vinculado ao usuário: ' . $existente->name,
                    'user' => $existente
                ];
            }

            // Verifica se existe usuário com mesmo email
            $userPorEmail = User::where('email', $email)->first();

            if ($userPorEmail) {
                // Vincula ao usuário existente
                $userPorEmail->update([
                    'datajuri_id' => $datajuriId,
                    'role' => $role,
                    'ativo' => true,
                    'telefone' => $telefone ?? $userPorEmail->telefone,
                    'cargo' => $cargo ?? $userPorEmail->cargo,
                ]);

                // Aplica permissões padrão do papel
                $this->permissaoService->aplicarPermissoesPadrao($userPorEmail);

                $this->registrarLog('ativar', 'sucesso', "Usuário existente vinculado: {$email}");

                return [
                    'success' => true,
                    'message' => 'Usuário existente vinculado ao DataJuri com sucesso',
                    'user' => $userPorEmail->fresh(),
                    'created' => false
                ];
            }

            // Cria novo usuário
            $senha = Str::random(12);
            
            $user = User::create([
                'name' => $nome,
                'email' => $email,
                'password' => Hash::make($senha),
                'role' => $role,
                'datajuri_id' => $datajuriId,
                'ativo' => true,
                'telefone' => $telefone,
                'cargo' => $cargo,
            ]);

            // Aplica permissões padrão do papel
            $this->permissaoService->aplicarPermissoesPadrao($user);

            $this->registrarLog('criar', 'sucesso', "Novo usuário criado: {$email}");

            return [
                'success' => true,
                'message' => 'Usuário criado com sucesso',
                'user' => $user,
                'created' => true,
                'senha_temporaria' => $senha // Mostrar apenas na criação!
            ];

        } catch (\Exception $e) {
            Log::error('UserSyncService: Erro ao ativar usuário', [
                'datajuri_id' => $datajuriId,
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            $this->registrarLog('ativar', 'erro', $e->getMessage());

            return [
                'success' => false,
                'message' => 'Erro ao ativar usuário: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Desativa um usuário (não exclui, apenas desativa)
     */
    public function desativarUsuario(User $user): bool
    {
        try {
            $user->update(['ativo' => false]);
            $this->registrarLog('desativar', 'sucesso', "Usuário desativado: {$user->email}");
            return true;
        } catch (\Exception $e) {
            Log::error('UserSyncService: Erro ao desativar usuário', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            $this->registrarLog('desativar', 'erro', $e->getMessage());
            return false;
        }
    }

    /**
     * Reativa um usuário
     */
    public function reativarUsuario(User $user): bool
    {
        try {
            $user->update(['ativo' => true]);
            $this->registrarLog('reativar', 'sucesso', "Usuário reativado: {$user->email}");
            return true;
        } catch (\Exception $e) {
            Log::error('UserSyncService: Erro ao reativar usuário', [
                'user_id' => $user->id,
                'error' => $e->getMessage()
            ]);
            $this->registrarLog('reativar', 'erro', $e->getMessage());
            return false;
        }
    }

    /**
     * Atualiza papel de um usuário
     */
    public function atualizarPapel(User $user, string $role, bool $aplicarPermissoesPadrao = true): bool
    {
        try {
            if (!in_array($role, ['admin', 'coordenador', 'socio'])) {
                throw new \InvalidArgumentException("Papel inválido: {$role}");
            }

            $user->update(['role' => $role]);

            if ($aplicarPermissoesPadrao) {
                $this->permissaoService->aplicarPermissoesPadrao($user);
            }

            $this->registrarLog('atualizar_papel', 'sucesso', "Papel atualizado para {$role}: {$user->email}");
            return true;

        } catch (\Exception $e) {
            Log::error('UserSyncService: Erro ao atualizar papel', [
                'user_id' => $user->id,
                'role' => $role,
                'error' => $e->getMessage()
            ]);
            $this->registrarLog('atualizar_papel', 'erro', $e->getMessage());
            return false;
        }
    }

    /**
     * Registra log de integração
     */
    private function registrarLog(string $tipo, string $status, string $mensagem): void
    {
        try {
            IntegrationLog::create([
                'sistema' => 'user_sync',
                'tipo' => $tipo,
                'status' => $status,
                'mensagem' => $mensagem,
            ]);
        } catch (\Exception $e) {
            Log::error('UserSyncService: Erro ao registrar log', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Estatísticas de usuários
     */
    public function getEstatisticas(): array
    {
        return [
            'total' => User::count(),
            'ativos' => User::where('ativo', true)->count(),
            'inativos' => User::where('ativo', false)->count(),
            'admins' => User::where('role', 'admin')->where('ativo', true)->count(),
            'coordenadores' => User::where('role', 'coordenador')->where('ativo', true)->count(),
            'socios' => User::where('role', 'socio')->where('ativo', true)->count(),
            'vinculados_datajuri' => User::whereNotNull('datajuri_id')->count(),
            'sem_vinculo' => User::whereNull('datajuri_id')->count(),
        ];
    }
}
