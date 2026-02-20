<?php

namespace App\Services\Nexo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\SendPulseWhatsAppService;

class NexoNotificacaoService
{
    protected SendPulseWhatsAppService $sendPulse;

    public function __construct(SendPulseWhatsAppService $sendPulse)
    {
        $this->sendPulse = $sendPulse;
    }

    // =========================================================================
    // AUDIÊNCIAS — 100% automático
    // =========================================================================

    
    /**
     * Palavras-chave de andamentos RELEVANTES para notificar o cliente.
     * Exclui movimentos burocráticos (publicação, juntada, conclusos, expedição, etc.)
     */
    protected const ANDAMENTOS_RELEVANTES = [
        'despacho',
        'decisão',
        'sentença',
        'acórdão',
        'acordão',
        'transitado em julgado',
        'baixa definitiva',
        'homologação',
        'acordo',
        'transação',
        'tutela',
        'liminar',
        'julgamento',
        'audiência',
        'revogad',
        'suspen',
        'arquivamento',
        'cumprimento',
        'penhora',
        'bloqueio',
        'desbloqueio',
        'levantamento',
        'expedição de alvará',
        'condenação',
        'procedente',
        'improcedente',
    ];

    /**
     * Verifica se o andamento é relevante para notificação ao cliente.
     */
    protected function andamentoRelevante(?string $descricao): bool
    {
        if (empty($descricao)) return false;
        $lower = mb_strtolower($descricao);
        foreach (self::ANDAMENTOS_RELEVANTES as $keyword) {
            if (str_contains($lower, $keyword)) {
                return true;
            }
        }
        return false;
    }

/**
     * Busca audiências nos próximos $dias dias e envia lembrete WhatsApp.
     * Retorna array com contadores de sucesso/falha/skip.
     */
    public function processarLembretesAudiencia(int $dias = 10): array
    {
        $stats = ['total' => 0, 'enviados' => 0, 'sem_telefone' => 0, 'ja_enviados' => 0, 'falha' => 0, 'sem_processo' => 0];

        $audiencias = DB::table('atividades_datajuri')
            ->where('tipo_atividade', 'Compromisso (Audiência)')
            ->whereBetween('data_hora', [
                Carbon::now('America/Sao_Paulo')->startOfDay(),
                Carbon::now('America/Sao_Paulo')->addDays($dias)->endOfDay(),
            ])
            ->whereNotNull('processo_pasta')
            ->where('processo_pasta', '!=', '')
            ->get();

        $stats['total'] = $audiencias->count();
        Log::info("NexoNotificacao: {$stats['total']} audiencias encontradas nos proximos {$dias} dias");

        foreach ($audiencias as $aud) {
            // Idempotência
            if ($this->jaNotificado('audiencia', $aud->id)) {
                $stats['ja_enviados']++;
                continue;
            }

            // Cadeia: atividade → processo → cliente → telefone
            $processo = DB::table('processos')->where('pasta', $aud->processo_pasta)->first();
            if (!$processo || empty($processo->cliente_datajuri_id)) {
                $stats['sem_processo']++;
                $this->registrar('audiencia', $aud->id, 'atividades_datajuri', null, null, null, 'skipped', null, 'Processo ou cliente nao vinculado', $aud->processo_pasta);
                continue;
            }

            $cliente = DB::table('clientes')->where('datajuri_id', $processo->cliente_datajuri_id)->first();
            if (!$cliente) {
                $stats['sem_processo']++;
                $this->registrar('audiencia', $aud->id, 'atividades_datajuri', null, null, null, 'skipped', null, 'Cliente nao encontrado dj_id=' . $processo->cliente_datajuri_id, $aud->processo_pasta);
                continue;
            }

            $telefone = $this->obterTelefoneValido($cliente);
            if (!$telefone) {
                $stats['sem_telefone']++;
                $this->registrar('audiencia', $aud->id, 'atividades_datajuri', $cliente->id, $cliente->nome, null, 'skipped', null, 'Sem telefone valido', $aud->processo_pasta);
                continue;
            }

            // Montar template
            $dataHora = Carbon::parse($aud->data_hora);
            $templateVars = [
                ['type' => 'text', 'text' => $this->primeiroNome($cliente->nome)],
                ['type' => 'text', 'text' => $dataHora->format('d/m/Y')],
                ['type' => 'text', 'text' => $dataHora->format('H:i')],
                ['type' => 'text', 'text' => $aud->processo_pasta],
            ];

            $template = [
                'name'       => 'lembrete_de_audiencia',
                'language'   => ['code' => 'pt_BR'],
                'components' => [
                    [
                        'type'       => 'body',
                        'parameters' => $templateVars,
                    ],
                ],
            ];

            // Enviar
            try {
                $phoneFormatted = '+' . $telefone;
                $result = $this->sendPulse->sendTemplateByPhone($phoneFormatted, $template);

                if (isset($result['success']) && $result['success'] === false) {
                    throw new \RuntimeException($result['message'] ?? json_encode($result));
                }

                $this->registrar('audiencia', $aud->id, 'atividades_datajuri', $cliente->id, $cliente->nome, $telefone, 'sent', $templateVars, null, $aud->processo_pasta);
                $stats['enviados']++;

                Log::info("NexoNotificacao: lembrete enviado", [
                    'cliente' => $cliente->nome,
                    'telefone' => $telefone,
                    'audiencia_data' => $dataHora->format('d/m/Y H:i'),
                    'pasta' => $aud->processo_pasta,
                ]);
            } catch (\Throwable $e) {
                $stats['falha']++;
                $this->registrar('audiencia', $aud->id, 'atividades_datajuri', $cliente->id, $cliente->nome, $telefone, 'failed', $templateVars, $e->getMessage(), $aud->processo_pasta);
                Log::error("NexoNotificacao: falha envio lembrete", [
                    'cliente' => $cliente->nome,
                    'erro' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Obtém telefone válido do cliente (telefone_normalizado → celular → telefone).
     * Retorna string E.164 sem + ou null.
     */
    public function obterTelefoneValido(object $cliente): ?string
    {
        // 1. Tentar telefone_normalizado (já processado pelo Orchestrator)
        $norm = trim($cliente->telefone_normalizado ?? '');
        if ($norm && $this->validarTelefone($norm)) {
            return $norm;
        }

        // 2. Fallback: celular → telefone
        $cel = trim($cliente->celular ?? '');
        if ($cel) {
            $normalizado = $this->normalizarTelefone($cel);
            if ($normalizado && $this->validarTelefone($normalizado)) {
                return $normalizado;
            }
        }

        $tel = trim($cliente->telefone ?? '');
        if ($tel) {
            $normalizado = $this->normalizarTelefone($tel);
            if ($normalizado && $this->validarTelefone($normalizado)) {
                return $normalizado;
            }
        }

        return null;
    }

    /**
     * Normaliza telefone para E.164 sem +.
     */
    protected function normalizarTelefone(?string $telefone): ?string
    {
        if (empty($telefone)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $telefone);
        if (empty($digits)) {
            return null;
        }

        // Já tem DDI 55 + 12-13 dígitos
        if (preg_match('/^55\d{10,11}$/', $digits)) {
            return $digits;
        }

        // 10-11 dígitos: DDD + número → adicionar 55
        if (preg_match('/^\d{10,11}$/', $digits)) {
            return '55' . $digits;
        }

        return null;
    }

    /**
     * Valida telefone normalizado: exclui placeholders e 0800.
     */
    protected function validarTelefone(string $norm): bool
    {
        // Excluir placeholder (00) 0000-0000
        if (str_starts_with($norm, '5500')) {
            return false;
        }
        // Excluir 0800 (não WhatsApp)
        if (str_starts_with($norm, '5508')) {
            return false;
        }
        // Deve ter 12 ou 13 dígitos (55 + DDD 2 dígitos + 8 ou 9 dígitos)
        if (strlen($norm) < 12 || strlen($norm) > 13) {
            return false;
        }
        return true;
    }

    /**
     * Verifica se já existe notificação para esta entidade.
     */
    protected function jaNotificado(string $tipo, int $entidadeId): bool
    {
        return DB::table('nexo_notificacoes')
            ->where('tipo', $tipo)
            ->where('entidade_id', $entidadeId)
            ->whereIn('status', ['sent', 'pending', 'approved'])
            ->exists();
    }

    /**
     * Registra notificação na tabela de controle.
     */
    protected function registrar(
        string $tipo,
        int $entidadeId,
        string $entidadeType,
        ?int $clienteId,
        ?string $clienteNome,
        ?string $telefone,
        string $status,
        ?array $templateVars,
        ?string $error,
        ?string $processoPasta
    ): void {
        DB::table('nexo_notificacoes')->updateOrInsert(
            ['tipo' => $tipo, 'entidade_id' => $entidadeId],
            [
                'entidade_type'  => $entidadeType,
                'cliente_id'     => $clienteId,
                'cliente_nome'   => $clienteNome,
                'telefone'       => $telefone,
                'template_name'  => $tipo === 'audiencia' ? 'lembrete_de_audiencia' : null,
                'template_vars'  => $templateVars ? json_encode($templateVars) : null,
                'status'         => $status,
                'sent_at'        => $status === 'sent' ? now() : null,
                'error_message'  => $error,
                'processo_pasta' => $processoPasta,
                'updated_at'     => now(),
                'created_at'     => DB::raw('IFNULL(created_at, NOW())'),
            ]
        );
    }


    // =========================================================================
    // ANDAMENTOS PROCESSUAIS — híbrido (cron detecta → advogado aprova)
    // =========================================================================

    /**
     * Detecta andamentos novos desde última execução e cria notificações pendentes.
     * Retorna array com contadores.
     */
    public function processarAndamentosNovos(): array
    {
        $stats = ['total' => 0, 'criados' => 0, 'ja_existe' => 0, 'sem_cliente' => 0, 'sem_telefone' => 0, 'sem_advogado' => 0];

        // Buscar andamentos com data_andamento recente (últimos 3 dias)
        // Na operação normal (3x/dia), isso captura andamentos novos de cada sync
        $diasRetro = 3;
        $desde = Carbon::now('America/Sao_Paulo')->subDays($diasRetro)->startOfDay();

        $andamentos = DB::table('andamentos_fase')
            ->where('data_andamento', '>=', $desde->format('Y-m-d'))
            ->where('fase_processo_id_datajuri', '>', 0)
            ->orderByDesc('data_andamento')
            ->get();

        $stats['total'] = $andamentos->count();
        Log::info("NexoNotificacao: {$stats['total']} andamentos novos desde {$desde}");

        foreach ($andamentos as $and) {
            // Idempotência
            // Filtrar apenas andamentos relevantes para o cliente
            if (!$this->andamentoRelevante($and->descricao ?? null)) {
                continue;
            }

            if ($this->jaNotificado('andamento', $and->id)) {
                $stats['ja_existe']++;
                continue;
            }

            // Cadeia: andamento → fases_processo → processo → cliente
            $fase = DB::table('fases_processo')
                ->where('datajuri_id', $and->fase_processo_id_datajuri)
                ->first();
            $pastaProceso = $fase->processo_pasta ?? ($and->processo_pasta ?: null);
            if (!$pastaProceso) {
                $stats['sem_cliente']++;
                continue;
            }

            $processo = DB::table('processos')->where('pasta', $pastaProceso)->first();
            if (!$processo || empty($processo->cliente_datajuri_id)) {
                $stats['sem_cliente']++;
                continue;
            }

            $cliente = DB::table('clientes')->where('datajuri_id', $processo->cliente_datajuri_id)->first();
            if (!$cliente) {
                $stats['sem_cliente']++;
                continue;
            }

            $telefone = $this->obterTelefoneValido($cliente);
            if (!$telefone) {
                $stats['sem_telefone']++;
                continue;
            }

            // Advogado responsável: processo.proprietario_id → users.datajuri_proprietario_id
            $userId = null;
            if (!empty($processo->proprietario_id)) {
                $user = DB::table('users')
                    ->where('datajuri_proprietario_id', $processo->proprietario_id)
                    ->first();
                $userId = $user ? $user->id : null;
            }

            if (!$userId) {
                $stats['sem_advogado']++;
                // Fallback: atribuir ao admin (user 1)
                $userId = 1;
            }

            // Truncar descrição para 1024 chars (limite SendPulse)
            $descricao = mb_substr(strip_tags($and->descricao ?? 'Movimentação processual'), 0, 300);

            $templateVars = [
                ['type' => 'text', 'text' => $this->primeiroNome($cliente->nome)],
                ['type' => 'text', 'text' => $and->processo_pasta],
                ['type' => 'text', 'text' => $descricao],
            ];

            // Criar como PENDING (advogado precisa aprovar)
            $this->registrar(
                'andamento',
                $and->id,
                'andamentos_fase',
                $cliente->id,
                $cliente->nome,
                $telefone,
                'pending',
                $templateVars,
                null,
                $pastaProceso
            );

            // Atribuir user_id ao registro
            DB::table('nexo_notificacoes')
                ->where('tipo', 'andamento')
                ->where('entidade_id', $and->id)
                ->update(['user_id' => $userId]);

            $stats['criados']++;
        }

        return $stats;
    }

    /**
     * Aprovar e enviar notificação de andamento.
     */
    public function aprovarEEnviarAndamento(int $notificacaoId, ?string $descricaoCustom = null): array
    {
        $notif = DB::table('nexo_notificacoes')->where('id', $notificacaoId)->first();

        if (!$notif) {
            return ['success' => false, 'error' => 'Notificacao nao encontrada'];
        }
        if ($notif->status !== 'pending') {
            return ['success' => false, 'error' => 'Notificacao ja processada (status: ' . $notif->status . ')'];
        }
        if (empty($notif->telefone)) {
            return ['success' => false, 'error' => 'Sem telefone valido'];
        }

        // Se houver descrição customizada, atualizar template_vars
        $templateVars = json_decode($notif->template_vars, true);
        if ($descricaoCustom && !empty(trim($descricaoCustom))) {
            $templateVars[2]['text'] = mb_substr(trim($descricaoCustom), 0, 300);
        }

        $template = [
            'name'       => 'atualizacao_processo',
            'language'   => ['code' => 'pt_BR'],
            'components' => [
                [
                    'type'       => 'body',
                    'parameters' => $templateVars,
                ],
            ],
        ];

        try {
            $phoneFormatted = '+' . $notif->telefone;
            $result = $this->sendPulse->sendTemplateByPhone($phoneFormatted, $template);

            if (isset($result['success']) && $result['success'] === false) {
                throw new \RuntimeException($result['message'] ?? json_encode($result));
            }

            DB::table('nexo_notificacoes')->where('id', $notificacaoId)->update([
                'status'        => 'sent',
                'sent_at'       => now(),
                'template_vars' => json_encode($templateVars),
                'template_name' => 'atualizacao_processo',
                'updated_at'    => now(),
            ]);

            Log::info("NexoNotificacao: andamento aprovado e enviado", [
                'notificacao_id' => $notificacaoId,
                'cliente' => $notif->cliente_nome,
            ]);

            // Registrar no CRM
            $this->registrarAtividadeCRM(
                $notificacaoId,
                $notif->cliente_id ?? null,
                $notif->cliente_nome ?? null,
                'Notificação WhatsApp: Andamento processual',
                $descricaoCustom ?? 'Notificação de andamento enviada',
                $notif->user_id ?? null
            );
            return ['success' => true];
        } catch (\Throwable $e) {
            DB::table('nexo_notificacoes')->where('id', $notificacaoId)->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at'    => now(),
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Descartar notificação (advogado decide não enviar).
     */
    public function descartarNotificacao(int $notificacaoId): bool
    {
        return DB::table('nexo_notificacoes')
            ->where('id', $notificacaoId)
            ->where('status', 'pending')
            ->update([
                'status'     => 'skipped',
                'updated_at' => now(),
            ]) > 0;
    }

    /**
     * Listar notificações pendentes para um advogado (ou todas para admin).
     */
    public function listarPendentes(?int $userId = null, ?string $tipo = null): \Illuminate\Support\Collection
    {
        $query = DB::table('nexo_notificacoes')
            ->where('status', 'pending')
            ->orderByDesc('created_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }
        if ($tipo) {
            $query->where('tipo', $tipo);
        }

        return $query->get();
    }

    /**
     * Listar histórico de notificações enviadas.
     */
    public function listarHistorico(?int $userId = null, int $limit = 50): \Illuminate\Support\Collection
    {
        $query = DB::table('nexo_notificacoes')
            ->whereIn('status', ['sent', 'failed', 'skipped'])
            ->orderByDesc('updated_at')
            ->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    /**
     * Retorna primeiro nome do cliente para uso no template.
     */
    protected function primeiroNome(?string $nomeCompleto): string
    {
        if (empty($nomeCompleto)) {
            return 'Cliente';
        }
        $partes = explode(' ', trim($nomeCompleto));
        return ucfirst(mb_strtolower($partes[0]));
    }

    /**
     * Detecta OS com andamento recente e cria notificações pending.
     */
    public function processarOrdensServico(int $dias = 3): array
    {
        $stats = ['total' => 0, 'criados' => 0, 'ja_notificados' => 0, 'sem_advogado' => 0];

        $osList = DB::table('ordens_servico')
            ->whereNotIn('situacao', ['Concluído'])
            ->where('data_ultimo_andamento', '>=', now()->subDays($dias))
            ->get();

        $stats['total'] = $osList->count();

        foreach ($osList as $os) {
            if ($this->jaNotificado('os', $os->datajuri_id)) {
                $stats['ja_notificados']++;
                continue;
            }

            // Mapear advogado_id (datajuri pessoa) → user_id
            $userId = null;
            if ($os->advogado_id) {
                $user = DB::table('users')->where('datajuri_id', $os->advogado_id)->first();
                $userId = $user->id ?? null;
            }
            if (!$userId) {
                $stats['sem_advogado']++;
                continue;
            }

            // OS não tem vínculo cliente — criar pending sem cliente/telefone
            $this->registrar(
                'os',
                $os->datajuri_id,
                'ordens_servico',
                null,
                null,
                null,
                'pending',
                null,
                'OS #' . $os->numero . ' | ' . $os->situacao . ' | Adv: ' . $os->advogado_nome,
                null
            );
            // Gravar user_id separadamente (registrar() não tem esse param)
            DB::table('nexo_notificacoes')
                ->where('tipo', 'os')
                ->where('entidade_id', $os->datajuri_id)
                ->update(['user_id' => $userId, 'template_name' => 'atualizacao_servico']);
            $stats['criados']++;
        }

        return $stats;
    }

    /**
     * Aprovar e enviar notificação de OS (advogado seleciona cliente + mensagem).
     */
    public function aprovarEEnviarOS(int $notificacaoId, int $clienteId, string $mensagem): array
    {
        $notif = DB::table('nexo_notificacoes')->where('id', $notificacaoId)->first();

        if (!$notif || $notif->tipo !== 'os' || $notif->status !== 'pending') {
            return ['success' => false, 'message' => 'Notificação não encontrada ou já processada.'];
        }

        $cliente = DB::table('clientes')->where('id', $clienteId)->first();
        if (!$cliente) {
            return ['success' => false, 'message' => 'Cliente não encontrado.'];
        }

        $telefone = $this->obterTelefoneValido($cliente);
        if (!$telefone) {
            DB::table('nexo_notificacoes')->where('id', $notificacaoId)->update([
                'status' => 'failed',
                'error_message' => 'Cliente sem telefone válido',
                'updated_at' => now(),
            ]);
            return ['success' => false, 'message' => 'Cliente sem telefone válido.'];
        }

        // Extrair número da OS do error_message (campo usado como memo)
        $osNumero = '';
        if (preg_match('/OS #(\d+)/', $notif->error_message ?? '', $m)) {
            $osNumero = $m[1];
        }

        $primeiroNome = $this->primeiroNome($cliente->nome);
        $mensagemTrunc = mb_substr($mensagem, 0, 300);

        $templateVars = [
            'name' => 'atualizacao_servico',
            'language' => ['code' => 'pt_BR'],
            'components' => [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => $primeiroNome],
                        ['type' => 'text', 'text' => $osNumero],
                        ['type' => 'text', 'text' => $mensagemTrunc],
                    ],
                ],
            ],
        ];

        try {
            $sendPulse = app(\App\Services\SendPulseWhatsAppService::class);
            $result = $sendPulse->sendTemplateByPhone('+' . $telefone, $templateVars);

            if (!empty($result['success'])) {
                DB::table('nexo_notificacoes')->where('id', $notificacaoId)->update([
                    'status' => 'sent',
                    'cliente_id' => $clienteId,
                    'cliente_nome' => $cliente->nome,
                    'telefone' => $telefone,
                    'template_vars' => json_encode($templateVars),
                    'sent_at' => now(),
                    'error_message' => null,
                    'updated_at' => now(),
                ]);
                // Registrar no CRM
                $this->registrarAtividadeCRM(
                    $notificacaoId,
                    $clienteId,
                    $cliente->nome ?? null,
                    'Notificação WhatsApp: Ordem de Serviço #' . $osNumero,
                    $mensagemTrunc,
                    $notif->user_id ?? null
                );
                return ['success' => true, 'message' => 'Enviado para ' . $primeiroNome . '.'];
            }

            $erro = $result['message'] ?? $result['error'] ?? 'Erro desconhecido';
            DB::table('nexo_notificacoes')->where('id', $notificacaoId)->update([
                'status' => 'failed',
                'cliente_id' => $clienteId,
                'cliente_nome' => $cliente->nome,
                'telefone' => $telefone,
                'error_message' => mb_substr($erro, 0, 500),
                'updated_at' => now(),
            ]);
            return ['success' => false, 'message' => $erro];
        } catch (\Exception $e) {
            DB::table('nexo_notificacoes')->where('id', $notificacaoId)->update([
                'status' => 'failed',
                'error_message' => mb_substr($e->getMessage(), 0, 500),
                'updated_at' => now(),
            ]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Busca clientes para autocomplete (nome ou telefone).
     */
    public function buscarClientes(string $termo, int $limit = 10): array
    {
        return DB::table('clientes')
            ->where(function ($q) use ($termo) {
                $q->where('nome', 'LIKE', '%' . $termo . '%')
                  ->orWhere('telefone_normalizado', 'LIKE', '%' . $termo . '%')
                  ->orWhere('celular', 'LIKE', '%' . $termo . '%')
                  ->orWhere('cpf_cnpj', 'LIKE', '%' . $termo . '%');
            })
            ->orderBy('nome')
            ->limit($limit)
            ->get(['id', 'nome', 'telefone_normalizado', 'celular', 'cpf_cnpj'])
            ->map(function ($c) {
                $tel = $c->telefone_normalizado ?: $c->celular ?: '';
                return [
                    'id' => $c->id,
                    'nome' => $c->nome,
                    'telefone' => $tel,
                    'cpf_cnpj' => $c->cpf_cnpj,
                ];
            })
            ->toArray();
    }


    /**
     * Registra atividade WhatsApp no CRM após envio de notificação.
     */
    protected function registrarAtividadeCRM(int $notificacaoId, ?int $clienteId, ?string $clienteNome, string $titulo, string $corpo, ?int $userId): void
    {
        if (!$clienteId) return;

        // Buscar account no CRM pelo nome do cliente
        $account = DB::table('crm_accounts')
            ->where('name', $clienteNome)
            ->first();

        // Se não encontrou por nome, tentar pela identidade (telefone)
        if (!$account) {
            $notif = DB::table('nexo_notificacoes')->where('id', $notificacaoId)->first();
            if ($notif && $notif->telefone) {
                $identity = DB::table('crm_identities')
                    ->where('type', 'phone')
                    ->where('value', 'LIKE', '%' . substr($notif->telefone, -8) . '%')
                    ->first();
                if ($identity) {
                    $account = DB::table('crm_accounts')->where('id', $identity->account_id)->first();
                }
            }
        }

        if (!$account) return;

        DB::table('crm_activities')->insert([
            'account_id'         => $account->id,
            'opportunity_id'     => null,
            'type'               => 'whatsapp',
            'title'              => $titulo,
            'body'               => $corpo,
            'due_at'             => null,
            'done_at'            => now(),
            'created_by_user_id' => $userId,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

}
