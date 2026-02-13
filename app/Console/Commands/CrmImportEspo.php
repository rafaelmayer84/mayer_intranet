<?php

namespace App\Console\Commands;

use App\Models\Crm\CrmActivity;
use App\Models\Crm\CrmIdentity;
use App\Models\Crm\CrmOpportunity;
use App\Models\Crm\CrmStage;
use App\Services\Crm\CrmIdentityResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CrmImportEspo extends Command
{
    protected $signature = 'crm:import-espo
        {--conn=mysql_espo : Nome da conexão MySQL do ESPO}
        {--dry-run : Simula sem gravar}';

    protected $description = 'Importa leads, oportunidades e atividades do ESPO CRM via MySQL direto';

    private CrmIdentityResolver $resolver;

    public function __construct(CrmIdentityResolver $resolver)
    {
        parent::__construct();
        $this->resolver = $resolver;
    }

    public function handle(): int
    {
        $conn = $this->option('conn');
        $dryRun = $this->option('dry-run');

        $this->info("Import ESPO CRM (conn={$conn})" . ($dryRun ? ' [DRY-RUN]' : ''));

        // Verificar conexão
        try {
            $test = DB::connection($conn)->select('SELECT 1');
        } catch (\Exception $e) {
            $this->error("Falha na conexão '{$conn}': {$e->getMessage()}");
            $this->line("Configure em config/database.php => connections => '{$conn}'");
            return self::FAILURE;
        }

        $this->importLeads($conn, $dryRun);
        $this->importOpportunities($conn, $dryRun);
        $this->importActivities($conn, $dryRun);

        $this->info('Import ESPO concluído.');
        return self::SUCCESS;
    }

    private function importLeads(string $conn, bool $dryRun): void
    {
        $this->info('--- Leads ---');

        // ESPO armazena leads na tabela 'lead'
        $leads = DB::connection($conn)->table('lead')
            ->where('deleted', 0)
            ->get();

        $created = $skipped = 0;

        foreach ($leads as $lead) {
            $espoId = $lead->id;

            // Idempotência: verificar se já importou
            $exists = CrmIdentity::where('kind', 'espocrm')
                ->where('value_norm', "lead:{$espoId}")
                ->exists();

            if ($exists) { $skipped++; continue; }

            $phone = $lead->phone_number ?? null;
            $email = $lead->email_address ?? null;

            // Tentar buscar email da tabela de emails do ESPO
            if (!$email) {
                $emailRow = DB::connection($conn)->table('email_address')
                    ->join('entity_email_address', 'entity_email_address.email_address_id', '=', 'email_address.id')
                    ->where('entity_email_address.entity_id', $espoId)
                    ->where('entity_email_address.entity_type', 'Lead')
                    ->where('email_address.deleted', 0)
                    ->first();
                $email = $emailRow->name ?? null;
            }

            // Tentar buscar phone da tabela de phones do ESPO
            if (!$phone) {
                $phoneRow = DB::connection($conn)->table('phone_number')
                    ->join('entity_phone_number', 'entity_phone_number.phone_number_id', '=', 'phone_number.id')
                    ->where('entity_phone_number.entity_id', $espoId)
                    ->where('entity_phone_number.entity_type', 'Lead')
                    ->where('phone_number.deleted', 0)
                    ->first();
                $phone = $phoneRow->name ?? null;
            }

            $name = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));
            if (empty($name)) $name = 'Lead ESPO #' . $espoId;

            if ($dryRun) {
                $this->line("  [DRY] Lead: {$name} | phone={$phone} | email={$email}");
                $created++;
                continue;
            }

            // Resolver account via IdentityResolver
            $account = $this->resolver->resolve($phone, $email, null, ['name' => $name]);

            // Registrar identity ESPO para idempotência
            CrmIdentity::firstOrCreate(
                ['kind' => 'espocrm', 'value_norm' => "lead:{$espoId}"],
                ['account_id' => $account->id, 'value' => "lead:{$espoId}"]
            );

            $created++;
        }

        $this->info("Leads: criados/vinculados={$created}, já existentes={$skipped}");
    }

    private function importOpportunities(string $conn, bool $dryRun): void
    {
        $this->info('--- Oportunidades ---');

        $opps = DB::connection($conn)->table('opportunity')
            ->where('deleted', 0)
            ->get();

        $stageMap = $this->buildStageMap();
        $created = $skipped = 0;

        foreach ($opps as $opp) {
            $espoId = $opp->id;

            $exists = CrmIdentity::where('kind', 'espocrm')
                ->where('value_norm', "opp:{$espoId}")
                ->exists();

            if ($exists) { $skipped++; continue; }

            // Tentar achar o account via contact vinculado
            $contact = DB::connection($conn)->table('opportunity_contact')
                ->where('opportunity_id', $espoId)
                ->where('deleted', 0)
                ->first();

            $account = null;
            if ($contact) {
                $ctRow = DB::connection($conn)->table('contact')
                    ->where('id', $contact->contact_id)
                    ->where('deleted', 0)
                    ->first();

                if ($ctRow) {
                    $ctEmail = DB::connection($conn)->table('email_address')
                        ->join('entity_email_address', 'entity_email_address.email_address_id', '=', 'email_address.id')
                        ->where('entity_email_address.entity_id', $ctRow->id)
                        ->where('entity_email_address.entity_type', 'Contact')
                        ->where('email_address.deleted', 0)
                        ->first();

                    $ctPhone = DB::connection($conn)->table('phone_number')
                        ->join('entity_phone_number', 'entity_phone_number.phone_number_id', '=', 'phone_number.id')
                        ->where('entity_phone_number.entity_id', $ctRow->id)
                        ->where('entity_phone_number.entity_type', 'Contact')
                        ->where('phone_number.deleted', 0)
                        ->first();

                    $ctName = trim(($ctRow->first_name ?? '') . ' ' . ($ctRow->last_name ?? ''));
                    $account = $this->resolver->resolve(
                        $ctPhone->name ?? null,
                        $ctEmail->name ?? null,
                        null,
                        ['name' => $ctName ?: 'Contato ESPO']
                    );
                }
            }

            if (!$account) {
                // Criar prospect genérico
                $account = $this->resolver->resolve(null, null, null, [
                    'name' => $opp->name ?? 'Opp ESPO #' . $espoId,
                ]);
            }

            if ($dryRun) {
                $this->line("  [DRY] Opp: {$opp->name} | account={$account->name} | stage={$opp->stage}");
                $created++;
                continue;
            }

            $stageId = $stageMap[$opp->stage ?? ''] ?? $stageMap['default'];
            $status = 'open';
            $wonAt = $lostAt = null;

            if (($opp->stage ?? '') === 'Closed Won') {
                $status = 'won';
                $wonAt = $opp->close_date ?? now();
            } elseif (($opp->stage ?? '') === 'Closed Lost') {
                $status = 'lost';
                $lostAt = $opp->close_date ?? now();
            }

            $crmOpp = CrmOpportunity::create([
                'account_id'     => $account->id,
                'stage_id'       => $stageId,
                'type'           => 'aquisicao',
                'title'          => $opp->name ?? 'Oportunidade ESPO',
                'source'         => 'espocrm',
                'value_estimated' => $opp->amount ?? null,
                'status'         => $status,
                'won_at'         => $wonAt,
                'lost_at'        => $lostAt,
            ]);

            CrmIdentity::firstOrCreate(
                ['kind' => 'espocrm', 'value_norm' => "opp:{$espoId}"],
                ['account_id' => $account->id, 'value' => "opp:{$espoId}"]
            );

            $created++;
        }

        $this->info("Oportunidades: criadas={$created}, já existentes={$skipped}");
    }

    private function importActivities(string $conn, bool $dryRun): void
    {
        $this->info('--- Atividades (tasks/calls/meetings) ---');
        $created = 0;

        // Tasks
        $tasks = DB::connection($conn)->table('task')
            ->where('deleted', 0)
            ->limit(500)
            ->get();

        foreach ($tasks as $task) {
            if ($dryRun) { $created++; continue; }

            $oppIdentity = CrmIdentity::where('kind', 'espocrm')
                ->where('value_norm', 'LIKE', 'opp:%')
                ->first();

            CrmActivity::firstOrCreate(
                ['title' => $task->name ?? 'Task ESPO', 'type' => 'task', 'due_at' => $task->date_end ?? null],
                [
                    'opportunity_id' => $oppIdentity ? CrmOpportunity::whereHas('account', fn($q) =>
                        $q->whereHas('identities', fn($q2) => $q2->where('kind', 'espocrm'))
                    )->first()?->id : null,
                    'body'    => $task->description ?? null,
                    'done_at' => ($task->status ?? '') === 'Completed' ? ($task->date_end ?? now()) : null,
                ]
            );
            $created++;
        }

        // Calls
        $calls = DB::connection($conn)->table('call')
            ->where('deleted', 0)
            ->limit(500)
            ->get();

        foreach ($calls as $call) {
            if ($dryRun) { $created++; continue; }

            CrmActivity::create([
                'type'    => 'call',
                'title'   => $call->name ?? 'Call ESPO',
                'body'    => $call->description ?? null,
                'due_at'  => $call->date_start ?? null,
                'done_at' => ($call->status ?? '') === 'Held' ? ($call->date_end ?? $call->date_start ?? now()) : null,
            ]);
            $created++;
        }

        // Meetings
        $meetings = DB::connection($conn)->table('meeting')
            ->where('deleted', 0)
            ->limit(500)
            ->get();

        foreach ($meetings as $meeting) {
            if ($dryRun) { $created++; continue; }

            CrmActivity::create([
                'type'    => 'meeting',
                'title'   => $meeting->name ?? 'Meeting ESPO',
                'body'    => $meeting->description ?? null,
                'due_at'  => $meeting->date_start ?? null,
                'done_at' => ($meeting->status ?? '') === 'Held' ? ($meeting->date_end ?? now()) : null,
            ]);
            $created++;
        }

        $this->info("Atividades importadas: {$created}");
    }

    private function buildStageMap(): array
    {
        $stages = CrmStage::all()->keyBy('slug');
        $first = CrmStage::active()->ordered()->first();

        return [
            'Prospecting'      => $stages['lead-novo']?->id ?? $first->id,
            'Qualification'    => $stages['em-contato']?->id ?? $first->id,
            'Proposal'         => $stages['proposta']?->id ?? $first->id,
            'Negotiation'      => $stages['negociacao']?->id ?? $first->id,
            'Closed Won'       => $stages['ganho']?->id ?? $first->id,
            'Closed Lost'      => $stages['perdido']?->id ?? $first->id,
            'Needs Analysis'   => $stages['em-contato']?->id ?? $first->id,
            'Value Proposition' => $stages['proposta']?->id ?? $first->id,
            'default'          => $first->id,
        ];
    }
}
