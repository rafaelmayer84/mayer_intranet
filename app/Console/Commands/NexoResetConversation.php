<?php

namespace App\Console\Commands;

use App\Models\WaConversation;
use App\Services\SendPulseWhatsAppService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NexoResetConversation extends Command
{
    protected $signature = 'nexo:reset-conversa
                            {telefone : Número do WhatsApp (ex: 5547984001234)}
                            {--force : Reseta mesmo que bot_ativo já seja true}';

    protected $description = 'Reseta o estado de uma conversa WhatsApp para o fluxo inicial (bot_ativo=true, limpa atendimento humano)';

    public function __construct(private SendPulseWhatsAppService $sp)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $telefone = preg_replace('/\D/', '', $this->argument('telefone'));
        if (!str_starts_with($telefone, '55')) {
            $telefone = '55' . $telefone;
        }

        $this->info("Buscando conversa para: {$telefone}");

        // Buscar por telefone exato e variantes (nono dígito)
        $conv = WaConversation::where('phone', $telefone)->first();

        if (!$conv) {
            $ddd    = substr($telefone, 2, 2);
            $numero = substr($telefone, 4);
            if (strlen($numero) === 8) {
                $conv = WaConversation::where('phone', '55' . $ddd . '9' . $numero)->first();
            } elseif (strlen($numero) === 9 && $numero[0] === '9') {
                $conv = WaConversation::where('phone', '55' . $ddd . substr($numero, 1))->first();
            }
        }

        if (!$conv) {
            $this->error("Nenhuma conversa encontrada para o telefone {$telefone}");
            return self::FAILURE;
        }

        $this->table(
            ['Campo', 'Valor atual'],
            [
                ['status',           $conv->status],
                ['bot_ativo',        $conv->bot_ativo ? 'true' : 'false (ou null)'],
                ['assigned_user_id', $conv->assigned_user_id ?? 'null'],
                ['contact_id',       $conv->contact_id ?? 'null'],
                ['last_incoming_at', $conv->last_incoming_at?->format('d/m/Y H:i') ?? 'null'],
            ]
        );

        if ($conv->bot_ativo && !$this->option('force')) {
            $this->warn("bot_ativo já é true — nenhuma ação necessária. Use --force para forçar.");
            return self::SUCCESS;
        }

        if (!$this->confirm("Resetar esta conversa para o fluxo inicial?", true)) {
            $this->info("Cancelado.");
            return self::SUCCESS;
        }

        // 1. Atualizar DB
        $conv->update([
            'bot_ativo'               => true,
            'assigned_user_id'        => null,
            'status'                  => 'closed', // fechar para SendPulse recriar no próximo incoming
            'lembrete_inatividade_at' => null,
        ]);
        $this->info("✓ DB atualizado (bot_ativo=true, assigned_user_id=null, status=closed)");

        // 2. Reativar automação no SendPulse
        if ($conv->contact_id) {
            try {
                $ok = $this->sp->reativarAutomacao($conv->contact_id);
                if ($ok) {
                    $this->info("✓ Automação SendPulse reativada para contact_id: {$conv->contact_id}");
                } else {
                    $this->warn("⚠ reativarAutomacao retornou false — verifique manualmente no SendPulse");
                }
            } catch (\Throwable $e) {
                $this->warn("⚠ Erro ao reativar no SendPulse: " . $e->getMessage());
            }
        } else {
            $this->warn("contact_id vazio — não foi possível reativar no SendPulse. Quando a pessoa mandar mensagem, o sistema reseta automaticamente.");
        }

        $this->newLine();
        $this->info("Conversa resetada. Peça para a pessoa mandar qualquer mensagem no WhatsApp — o fluxo inicial será disparado.");

        return self::SUCCESS;
    }
}
