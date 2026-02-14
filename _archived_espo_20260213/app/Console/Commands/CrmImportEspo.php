<?php

namespace App\Console\Commands;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmEvent;
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
        {--conn=mysql_espo : Nome da conexão database.php}
        {--dry-run : Simular sem gravar}
        {--skip-leads : Pular importação de leads}
        {--skip-opps : Pular importação de oportunidades}';

    protected $description = 'Importa leads, accounts e oportunidades do ESPO CRM (MySQL direto)';

    private string $conn;
    private bool $dryRun;
    private CrmIdentityResolver $resolver;
    private array $stageMap = [];
    private array $stats = [
        'leads_imported'  => 0,
        'leads_skipped'   => 0,
        'accounts_linked' => 0,
        'opps_imported'   => 0,
        'opps_skipped'    => 0,
        'opps_updated'    => 0,
        'errors'          => 0,
    ];

    public function handle(): int
    {
        $this->conn   = $this->option('conn');
        $this->dryRun = (bool) $this->option('dry-run');

        $this->info("Import ESPO CRM (conn={$this->conn})" . ($this->dryRun ? ' [DRY-RUN]' : ''));

        // Testar conexão
        try {
            DB::connection($this->conn)->getPdo();
            $this->info('✓ Conexão OK');
        } catch (\Exception $e) {
            $this->error("Falha na conexão '{$this->conn}': " . $e->getMessage());
            return 1;
        }

        $this->resolver = app(CrmIdentityResolver::class);
        $this->loadStageMap();

        if (!$this->option('skip-leads')) {
            $this->importLeads();
        }

        $this->importAccounts();

        if (!$this->option('skip-opps')) {
            $this->importOpportunities();
        }

        $this->printStats();

        return 0;
    }

    /**
     * Carrega mapeamento de stages ESPO → CRM V2.
     */
    private function loadStageMap(): void
    {
        $stages = CrmStage::orderBy('order')->get();

        // Mapeamento ESPO stage → CRM stage slug
        $map = [
            'Prospecting'          => 'prospeccao',
            'Qualification'        => 'qualificacao',
            'Proposal/Price Quote' => 'proposta',
            'Proposal'             => 'proposta',
            'Negotiation'          => 'negociacao',
            'Closed Won'           => null, // status=won
            'Closed Lost'          => null, // status=lost
        ];

        foreach ($stages as $stage) {
            $this->stageMap[$stage->slug] = $stage->id;
        }

        $this->stageMap['_espo_map'] = $map;
    }

    /**
     * Resolve stage ESPO → crm_stage_id + status.
     */
    private function resolveStage(string $espoStage): array
    {
        $map = $this->stageMap['_espo_map'] ?? [];

        if ($espoStage === 'Closed Won') {
            return [
                'stage_id' => $this->stageMap['fechamento'] ?? $this->stageMap['negociacao'] ?? null,
                'status'   => 'won',
            ];
        }

        if ($espoStage === 'Closed Lost') {
            return [
                'stage_id' => $this->stageMap['fechamento'] ?? $this->stageMap['negociacao'] ?? null,
                'status'   => 'lost',
            ];
        }

        $slug = $map[$espoStage] ?? 'prospeccao';
        return [
            'stage_id' => $this->stageMap[$slug] ?? CrmStage::first()?->id,
            'status'   => 'open',
        ];
    }

    /**
     * Importa leads do ESPO → crm_accounts (kind=prospect).
     */
    private function importLeads(): void
    {
        $this->info('--- Importando Leads ---');

        $leads = DB::connection($this->conn)
            ->table('lead')
            ->where('deleted', 0)
            ->get();

        $bar = $this->output->createProgressBar($leads->count());

        foreach ($leads as $lead) {
            try {
                $phone = $this->getEntityPhone($lead->id, 'Lead');
                $email = $this->getEntityEmail($lead->id, 'Lead');
                $name  = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''));

                if (empty($name) || $name === ' ') {
                    $this->stats['leads_skipped']++;
                    $bar->advance();
                    continue;
                }

                if ($this->dryRun) {
                    $this->stats['leads_imported']++;
                    $bar->advance();
                    continue;
                }

                $account = $this->resolver->resolve(
                    phone: $phone,
                    email: $email,
                    defaults: [
                        'name'      => $name,
                        'kind'      => 'prospect',
                        'lifecycle' => $this->mapLeadStatus($lead->status ?? 'New'),
                    ]
                );

                // Guardar referência ESPO
                if ($account && !CrmIdentity::where('account_id', $account->id)->where('kind', 'espo_lead')->exists()) {
                    CrmIdentity::create([
                        'account_id' => $account->id,
                        'kind'    => 'espo_lead',
                        'value' => $lead->id,
                        'value_norm' => $lead->id,
                    ]);
                }

                $this->stats['leads_imported']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::warning("CRM Import ESPO lead error [{$lead->id}]: " . $e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    /**
     * Importa accounts do ESPO → crm_accounts via Identity Resolver.
     */
    private function importAccounts(): void
    {
        $this->info('--- Importando Accounts ---');

        $accounts = DB::connection($this->conn)
            ->table('account')
            ->where('deleted', 0)
            ->get();

        $bar = $this->output->createProgressBar($accounts->count());

        foreach ($accounts as $acc) {
            try {
                $phone = $this->getEntityPhone($acc->id, 'Account');
                $email = $this->getEntityEmail($acc->id, 'Account');
                $name  = $acc->name ?? '';

                if (empty($name)) {
                    $bar->advance();
                    continue;
                }

                if ($this->dryRun) {
                    $this->stats['accounts_linked']++;
                    $bar->advance();
                    continue;
                }

                $crmAccount = $this->resolver->resolve(
                    phone: $phone,
                    email: $email,
                    defaults: [
                        'name' => $name,
                        'kind' => 'prospect',
                    ]
                );

                // Guardar referência ESPO account
                if ($crmAccount && !CrmIdentity::where('account_id', $crmAccount->id)->where('kind', 'espo_account')->exists()) {
                    CrmIdentity::create([
                        'account_id' => $crmAccount->id,
                        'kind'    => 'espo_account',
                        'value' => $acc->id,
                        'value_norm' => $acc->id,
                    ]);
                }

                $this->stats['accounts_linked']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::warning("CRM Import ESPO account error [{$acc->id}]: " . $e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    /**
     * Importa oportunidades do ESPO → crm_opportunities com campos completos.
     */
    private function importOpportunities(): void
    {
        $this->info('--- Importando Oportunidades ---');

        $opps = DB::connection($this->conn)
            ->table('opportunity')
            ->where('deleted', 0)
            ->get();

        $bar = $this->output->createProgressBar($opps->count());

        foreach ($opps as $opp) {
            try {
                // Já existe? (por espo_id no payload ou match)
                $existing = CrmOpportunity::where('espo_id', $opp->id)->first();
                if ($existing) {
                    // Atualizar campos que podem ter mudado
                    $stageInfo = $this->resolveStage($opp->stage ?? 'Prospecting');
                    $existing->update([
                        'status'         => $stageInfo['status'],
                        'stage_id'       => $stageInfo['stage_id'],
                        'value_estimated'         => $opp->amount ?? 0,
                        'lost_reason'    => $this->cleanLostReason($opp->c_lost_reason ?? null),
                        'tipo_demanda'   => $this->cleanTipoDemanda($opp->c_tipo_de_demanda ?? null),
                    ]);
                    $this->stats['opps_updated']++;
                    $bar->advance();
                    continue;
                }

                // Resolver account vinculada
                $accountId = $this->resolveOppAccount($opp);
                if (!$accountId) {
                    $this->stats['opps_skipped']++;
                    $bar->advance();
                    continue;
                }

                $stageInfo = $this->resolveStage($opp->stage ?? 'Prospecting');

                if ($this->dryRun) {
                    $this->stats['opps_imported']++;
                    $bar->advance();
                    continue;
                }

                $crmOpp = CrmOpportunity::create([
                    'account_id'     => $accountId,
                    'stage_id'       => $stageInfo['stage_id'],
                    'type'           => 'aquisicao',
                    'title'          => $opp->name ?? 'Oportunidade ESPO',
                    'value_estimated'         => $opp->amount ?? 0,
                    'status'         => $stageInfo['status'],
                    'lost_reason'    => $this->cleanLostReason($opp->c_lost_reason ?? null),
                    'tipo_demanda'   => $this->cleanTipoDemanda($opp->c_tipo_de_demanda ?? null),
                    'lead_source'    => $opp->lead_source,
                    'espo_id'        => $opp->id,
                    'created_at'     => $opp->created_at,
                    'updated_at'     => $opp->modified_at ?? $opp->created_at,
                ]);

                // Registrar evento
                CrmEvent::create([
                    'account_id'  => $accountId,
                    'type'        => 'opportunity_imported',
                    'payload'     => [
                        'source'    => 'espo',
                        'espo_id'   => $opp->id,
                        'stage'     => $opp->stage,
                        'value_estimated'    => $opp->amount,
                    ],
                    'happened_at' => $opp->created_at,
                ]);

                $this->stats['opps_imported']++;
            } catch (\Exception $e) {
                $this->stats['errors']++;
                Log::warning("CRM Import ESPO opp error [{$opp->id}]: " . $e->getMessage());
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
    }

    /**
     * Resolve account vinculada a uma oportunidade ESPO.
     */
    private function resolveOppAccount(object $opp): ?int
    {
        // 1. Tentar pelo account_id ESPO
        if (!empty($opp->account_id)) {
            $identity = CrmIdentity::where('kind', 'espo_account')
                ->where('value', $opp->account_id)
                ->first();
            if ($identity) return $identity->account_id;

            // Buscar nome da account no ESPO e resolver
            $espoAcc = DB::connection($this->conn)
                ->table('account')
                ->where('id', $opp->account_id)
                ->where('deleted', 0)
                ->first();

            if ($espoAcc) {
                $phone = $this->getEntityPhone($espoAcc->id, 'Account');
                $email = $this->getEntityEmail($espoAcc->id, 'Account');
                $crmAccount = $this->resolver->resolve(
                    phone: $phone,
                    email: $email,
                    defaults: ['name' => $espoAcc->name ?? 'Sem nome', 'kind' => 'prospect']
                );
                if ($crmAccount) {
                    CrmIdentity::firstOrCreate([
                        'account_id' => $crmAccount->id,
                        'kind'    => 'espo_account',
                        'value' => $espoAcc->id,
                        'value_norm' => $espoAcc->id,
                    ]);
                    return $crmAccount->id;
                }
            }
        }

        // 2. Tentar pelo contact_id ESPO
        if (!empty($opp->contact_id)) {
            $identity = CrmIdentity::where('kind', 'espo_contact')
                ->where('value', $opp->contact_id)
                ->first();
            if ($identity) return $identity->account_id;
        }

        // 3. Criar account genérica pelo nome da oportunidade
        $crmAccount = CrmAccount::create([
            'name'      => 'Lead: ' . ($opp->name ?? 'Sem nome'),
            'kind'      => 'prospect',
            'lifecycle' => 'onboarding',
        ]);
        return $crmAccount->id;
    }

    /**
     * Busca telefone de uma entidade ESPO.
     */
    private function getEntityPhone(string $entityId, string $entityType): ?string
    {
        $row = DB::connection($this->conn)
            ->table('entity_phone_number')
            ->join('phone_number', 'phone_number.id', '=', 'entity_phone_number.phone_number_id')
            ->where('entity_phone_number.entity_id', $entityId)
            ->where('entity_phone_number.entity_type', $entityType)
            ->where('phone_number.deleted', 0)
            ->orderByDesc('entity_phone_number.primary')
            ->first(['phone_number.name']);

        return $row->name ?? null;
    }

    /**
     * Busca email de uma entidade ESPO.
     */
    private function getEntityEmail(string $entityId, string $entityType): ?string
    {
        $row = DB::connection($this->conn)
            ->table('entity_email_address')
            ->join('email_address', 'email_address.id', '=', 'entity_email_address.email_address_id')
            ->where('entity_email_address.entity_id', $entityId)
            ->where('entity_email_address.entity_type', $entityType)
            ->where('email_address.deleted', 0)
            ->orderByDesc('entity_email_address.primary')
            ->first(['email_address.name']);

        return $row->name ?? null;
    }

    /**
     * Mapeia status de lead ESPO → lifecycle CRM.
     */
    private function mapLeadStatus(string $status): string
    {
        return match ($status) {
            'New'        => 'onboarding',
            'Assigned'   => 'onboarding',
            'In Process' => 'ativo',
            'Converted'  => 'ativo',
            'Dead'       => 'risco',
            default      => 'onboarding',
        };
    }

    /**
     * Limpa campo lost_reason.
     */
    private function cleanLostReason(?string $reason): ?string
    {
        if (!$reason || $reason === 'Selecionar' || $reason === 'Selecione') return null;
        return $reason;
    }

    /**
     * Limpa campo tipo_demanda.
     */
    private function cleanTipoDemanda(?string $tipo): ?string
    {
        if (!$tipo || $tipo === 'Selecione' || $tipo === 'Selecionar') return null;
        return $tipo;
    }

    private function printStats(): void
    {
        $this->newLine();
        $this->info('=== Resultado ===');
        $this->table(
            ['Métrica', 'Valor'],
            collect($this->stats)->map(fn($v, $k) => [$k, $v])->values()->toArray()
        );

        if (!$this->dryRun && $this->stats['opps_imported'] > 0) {
            $this->info('Flush cache dashboards...');
            cache()->forget('dashboard_finance_data');
        }
    }
}
