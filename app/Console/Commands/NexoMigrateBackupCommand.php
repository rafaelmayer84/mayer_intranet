<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Lead;
use App\Models\Cliente;

class NexoMigrateBackupCommand extends Command
{
    protected $signature = 'nexo:migrate-backup
                            {--dry-run : Simula sem gravar}
                            {--skip-autolink : Pula autolink com leads/clientes}
                            {--limit= : Limita quantidade de conversas}';

    protected $description = 'Migra conversas do banco de backup SendPulse (u492856976_backupsendpls) para as tabelas NEXO (wa_conversations / wa_messages)';

    private int $conversasCreated = 0;
    private int $conversasSkipped = 0;
    private int $messagesCreated = 0;
    private int $messagesSkipped = 0;
    private int $autolinksLead = 0;
    private int $autolinksCliente = 0;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $skipAutolink = $this->option('skip-autolink');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;

        $this->info('=== NEXO: Migração do Backup SendPulse ===');
        $this->info($isDryRun ? '⚠️  MODO DRY-RUN (nada será gravado)' : '🔴 MODO PRODUÇÃO');

        // Validar conexão com banco de backup
        try {
            $testCount = DB::connection('backup_sendpulse')->table('conversations')->count();
            $this->info("✅ Conexão OK — {$testCount} conversas encontradas no backup");
        } catch (\Exception $e) {
            $this->error("❌ Falha na conexão 'backup_sendpulse': " . $e->getMessage());
            $this->error("Verifique se a conexão está configurada no config/database.php");
            return 1;
        }

        // Buscar conversas do backup com dados do contato
        $query = DB::connection('backup_sendpulse')
            ->table('conversations as conv')
            ->join('contacts as c', 'c.id', '=', 'conv.contactId')
            ->select(
                'conv.id as conv_id',
                'conv.contactId',
                'conv.botId',
                'conv.status',
                'conv.openedAt',
                'conv.closedAt',
                'conv.createdAt as conv_created',
                'c.name as contact_name',
                'c.phone as contact_phone',
                'c.id as contact_sp_id'
            )
            ->orderBy('conv.id');

        if ($limit) {
            $query->limit($limit);
        }

        $conversas = $query->get();
        $total = $conversas->count();
        $this->info("📦 Processando {$total} conversas...");
        $bar = $this->output->createProgressBar($total);

        foreach ($conversas as $conv) {
            $bar->advance();
            $this->processConversation($conv, $isDryRun, $skipAutolink);
        }

        $bar->finish();
        $this->newLine(2);

        // Resumo
        $this->info('=== RESULTADO ===');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Conversas criadas', $this->conversasCreated],
                ['Conversas ignoradas (já existiam)', $this->conversasSkipped],
                ['Mensagens criadas', $this->messagesCreated],
                ['Mensagens ignoradas (duplicadas)', $this->messagesSkipped],
                ['Autolinks Lead', $this->autolinksLead],
                ['Autolinks Cliente', $this->autolinksCliente],
            ]
        );

        if ($isDryRun) {
            $this->warn('⚠️  Nenhum dado foi gravado (dry-run). Rode sem --dry-run para executar.');
        }

        Log::info('nexo:migrate-backup finalizado', [
            'dry_run' => $isDryRun,
            'conversas_created' => $this->conversasCreated,
            'conversas_skipped' => $this->conversasSkipped,
            'messages_created' => $this->messagesCreated,
            'messages_skipped' => $this->messagesSkipped,
            'autolinks_lead' => $this->autolinksLead,
            'autolinks_cliente' => $this->autolinksCliente,
        ]);

        return 0;
    }

    private function processConversation(object $conv, bool $isDryRun, bool $skipAutolink): void
    {
        $phone = $this->normalizePhone($conv->contact_phone);

        if (empty($phone)) {
            $this->conversasSkipped++;
            return;
        }

        // Gerar chat_id único baseado no contactId do backup
        $chatId = 'backup_' . $conv->contactId;

        // Verificar se já existe no NEXO (deduplicação por chat_id)
        $existing = DB::table('wa_conversations')->where('chat_id', $chatId)->first();
        if ($existing) {
            $this->conversasSkipped++;
            // Ainda importa mensagens que possam faltar
            $this->importMessages($conv->conv_id, $existing->id, $conv->contactId, $isDryRun);
            return;
        }

        // Buscar última mensagem para last_message_at
        $lastMsg = DB::connection('backup_sendpulse')
            ->table('messages')
            ->where('conversationId', $conv->conv_id)
            ->orderByDesc('timestamp')
            ->first();

        $lastMessageAt = $lastMsg
            ? date('Y-m-d H:i:s', intval($lastMsg->timestamp / 1000))
            : ($conv->openedAt ?? $conv->conv_created);

        // Buscar última mensagem incoming para last_incoming_at
        $lastIncoming = DB::connection('backup_sendpulse')
            ->table('messages')
            ->where('conversationId', $conv->conv_id)
            ->where('messageType', 'incoming')
            ->orderByDesc('timestamp')
            ->first();

        $lastIncomingAt = $lastIncoming
            ? date('Y-m-d H:i:s', intval($lastIncoming->timestamp / 1000))
            : null;

        // Contar mensagens incoming não lidas (últimas sem resposta outgoing depois)
        $msgCount = DB::connection('backup_sendpulse')
            ->table('messages')
            ->where('conversationId', $conv->conv_id)
            ->count();

        // Autolink
        $linkedLeadId = null;
        $linkedClienteId = null;

        if (!$skipAutolink && $phone) {
            // Buscar lead por telefone exato
            $leads = Lead::where('telefone', $phone)->get();
            if ($leads->count() === 1) {
                $linkedLeadId = $leads->first()->id;
                $this->autolinksLead++;
            }

            // Buscar cliente por telefone exato
            $clientes = Cliente::where('telefone', $phone)
                ->orWhere('celular', $phone)
                ->get();
            if ($clientes->count() === 1) {
                $linkedClienteId = $clientes->first()->id;
                $this->autolinksCliente++;
            }
        }

        $conversationData = [
            'provider' => 'sendpulse',
            'contact_id' => $conv->contactId,
            'chat_id' => $chatId,
            'phone' => $phone,
            'name' => $conv->contact_name,
            'status' => $conv->status ?? 'closed',
            'assigned_user_id' => null,
            'last_message_at' => $lastMessageAt,
            'last_incoming_at' => $lastIncomingAt,
            'first_response_at' => null,
            'unread_count' => 0,
            'linked_lead_id' => $linkedLeadId,
            'linked_cliente_id' => $linkedClienteId,
            'created_at' => $conv->openedAt ?? $conv->conv_created,
            'updated_at' => now(),
        ];

        if ($isDryRun) {
            $this->conversasCreated++;
            return;
        }

        $waConvId = DB::table('wa_conversations')->insertGetId($conversationData);
        $this->conversasCreated++;

        // Importar mensagens
        $this->importMessages($conv->conv_id, $waConvId, $conv->contactId, $isDryRun);
    }

    private function importMessages(int $backupConvId, int $waConvId, string $contactId, bool $isDryRun): void
    {
        $messages = DB::connection('backup_sendpulse')
            ->table('messages')
            ->where('conversationId', $backupConvId)
            ->orderBy('timestamp')
            ->get();

        $batch = [];

        foreach ($messages as $msg) {
            $providerMsgId = 'backup_' . $msg->id;

            // Verificar duplicata
            $exists = DB::table('wa_messages')
                ->where('provider_message_id', $providerMsgId)
                ->exists();

            if ($exists) {
                $this->messagesSkipped++;
                continue;
            }

            $sentAt = date('Y-m-d H:i:s', intval($msg->timestamp / 1000));

            // Mapear direction: backup usa 'incoming'/'outgoing', NEXO usa 1/2
            $direction = ($msg->messageType === 'incoming') ? 1 : 2;

            // Determinar message_type a partir do messageData
            $messageType = 'text';
            if ($msg->messageData) {
                $data = json_decode($msg->messageData, true);
                if (isset($data['info']['message']['channel_data']['message']['type'])) {
                    $messageType = $data['info']['message']['channel_data']['message']['type'];
                }
            }

            $batch[] = [
                'conversation_id' => $waConvId,
                'provider_message_id' => $providerMsgId,
                'direction' => $direction,
                'is_human' => false,
                'message_type' => $messageType,
                'body' => $msg->messageText,
                'raw_payload' => $msg->messageData,
                'sent_at' => $sentAt,
                'created_at' => $msg->createdAt ?? $sentAt,
                'updated_at' => now(),
            ];

            $this->messagesCreated++;

            // Inserir em lotes de 200
            if (count($batch) >= 200) {
                if (!$isDryRun) {
                    DB::table('wa_messages')->insert($batch);
                }
                $batch = [];
            }
        }

        // Inserir resto
        if (!empty($batch) && !$isDryRun) {
            DB::table('wa_messages')->insert($batch);
        }
    }

    private function normalizePhone(?string $phone): string
    {
        if (empty($phone)) {
            return '';
        }

        // Remover tudo que não é dígito
        $digits = preg_replace('/\D/', '', $phone);

        // Se tem 10 ou 11 dígitos, assumir Brasil e prefixar 55
        if (strlen($digits) >= 10 && strlen($digits) <= 11) {
            $digits = '55' . $digits;
        }

        // Se veio do contactId tipo "whatsapp_554791314240", extrair número
        if (empty($digits) && preg_match('/\d+/', $phone, $m)) {
            $digits = $m[0];
            if (strlen($digits) >= 10 && strlen($digits) <= 11) {
                $digits = '55' . $digits;
            }
        }

        return $digits;
    }
}
