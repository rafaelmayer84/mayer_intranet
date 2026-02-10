<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\SendPulseWhatsAppService;

class NexoEnrichNamesCommand extends Command
{
    protected $signature = 'nexo:enrich-names
                            {--dry-run : Simula sem gravar}
                            {--limit= : Limita quantidade de conversas}
                            {--source=all : Fonte: api, leads, clientes, all}';

    protected $description = 'Enriquece nomes das wa_conversations sem nome via API SendPulse, Leads e Clientes';

    private int $updatedApi = 0;
    private int $updatedLead = 0;
    private int $updatedCliente = 0;
    private int $notFound = 0;
    private int $skipped = 0;
    private int $apiErrors = 0;

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $source = $this->option('source') ?? 'all';

        $this->info('=== NEXO: Enriquecimento de Nomes ===');
        $this->info($isDryRun ? '⚠️  MODO DRY-RUN' : '🔴 MODO PRODUÇÃO');
        $this->info("Fonte: {$source}");

        // Buscar conversas sem nome
        $query = DB::table('wa_conversations')
            ->where(function ($q) {
                $q->whereNull('name')
                  ->orWhere('name', '')
                  ->orWhere('name', 'Sem nome');
            })
            ->where('phone', '!=', '')
            ->whereNotNull('phone')
            ->orderBy('last_message_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        $conversas = $query->get();
        $total = $conversas->count();

        if ($total === 0) {
            $this->info('✅ Todas as conversas já possuem nome.');
            return 0;
        }

        $this->info("📦 {$total} conversas sem nome encontradas");
        $bar = $this->output->createProgressBar($total);

        foreach ($conversas as $conv) {
            $bar->advance();
            $name = null;

            // 1. Tentar Lead vinculado
            if (in_array($source, ['all', 'leads']) && $conv->linked_lead_id) {
                $lead = DB::table('leads')->where('id', $conv->linked_lead_id)->first();
                if ($lead && !empty($lead->nome)) {
                    $name = $lead->nome;
                    $this->updatedLead++;
                }
            }

            // 2. Tentar Cliente vinculado
            if (!$name && in_array($source, ['all', 'clientes']) && $conv->linked_cliente_id) {
                $cliente = DB::table('clientes')->where('id', $conv->linked_cliente_id)->first();
                if ($cliente && !empty($cliente->nome)) {
                    $name = $cliente->nome;
                    $this->updatedCliente++;
                }
            }

            // 3. Tentar Lead por telefone (sem vínculo direto)
            if (!$name && in_array($source, ['all', 'leads'])) {
                $lead = DB::table('leads')
                    ->where('telefone', $conv->phone)
                    ->whereNotNull('nome')
                    ->where('nome', '!=', '')
                    ->first();
                if ($lead) {
                    $name = $lead->nome;
                    $this->updatedLead++;
                }
            }

            // 4. Tentar Cliente por telefone
            if (!$name && in_array($source, ['all', 'clientes'])) {
                $cliente = DB::table('clientes')
                    ->where(function ($q) use ($conv) {
                        $q->where('telefone', $conv->phone)
                          ->orWhere('celular', $conv->phone);
                    })
                    ->whereNotNull('nome')
                    ->where('nome', '!=', '')
                    ->first();
                if ($cliente) {
                    $name = $cliente->nome;
                    $this->updatedCliente++;
                }
            }

            // 5. Tentar API SendPulse
            if (!$name && in_array($source, ['all', 'api'])) {
                $name = $this->fetchNameFromApi($conv->phone);
                if ($name) {
                    $this->updatedApi++;
                }
            }

            // Atualizar
            if ($name) {
                $name = $this->cleanName($name);
                if (!$isDryRun) {
                    DB::table('wa_conversations')
                        ->where('id', $conv->id)
                        ->update(['name' => $name, 'updated_at' => now()]);
                }
            } else {
                $this->notFound++;
            }

            // Rate-limit para API (evitar throttle)
            if (in_array($source, ['all', 'api'])) {
                usleep(200000); // 200ms entre chamadas
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info('=== RESULTADO ===');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Nomes via Lead', $this->updatedLead],
                ['Nomes via Cliente', $this->updatedCliente],
                ['Nomes via API SendPulse', $this->updatedApi],
                ['Sem nome encontrado', $this->notFound],
                ['Erros de API', $this->apiErrors],
            ]
        );

        if ($isDryRun) {
            $this->warn('⚠️  Nenhum dado foi gravado (dry-run).');
        }

        Log::info('nexo:enrich-names finalizado', [
            'dry_run' => $isDryRun,
            'lead' => $this->updatedLead,
            'cliente' => $this->updatedCliente,
            'api' => $this->updatedApi,
            'not_found' => $this->notFound,
            'api_errors' => $this->apiErrors,
        ]);

        return 0;
    }

    private function fetchNameFromApi(string $phone): ?string
    {
        try {
            $service = app(SendPulseWhatsAppService::class);
            $contact = $service->getContactByPhone($phone);

            if (!$contact) {
                return null;
            }

            // SendPulse retorna 'name' no contato
            $name = data_get($contact, 'name');
            if (!empty($name) && is_string($name)) {
                return $name;
            }

            // Fallback: first_name + last_name
            $first = data_get($contact, 'first_name', '');
            $last = data_get($contact, 'last_name', '');
            $fullName = trim("{$first} {$last}");

            return !empty($fullName) ? $fullName : null;
        } catch (\Throwable $e) {
            $this->apiErrors++;
            Log::warning('nexo:enrich-names API error', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function cleanName(?string $name): string
    {
        if (empty($name)) {
            return '';
        }

        // Remover espaços extras
        $name = trim(preg_replace('/\s+/', ' ', $name));

        // Capitalizar
        $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        return $name;
    }
}
