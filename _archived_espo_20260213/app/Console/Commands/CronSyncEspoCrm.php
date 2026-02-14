<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CronSyncEspoCrm extends Command
{
    protected $signature = 'cron:sync-espo';
    protected $description = 'Cron: Sincroniza Leads e Oportunidades ESPO CRM';

    private function getEspoHeaders()
    {
        return [
            'X-Api-Key' => env('ESPOCRM_API_KEY'),
            'Content-Type' => 'application/json',
        ];
    }

    public function handle()
    {
        try {
            Log::info('[CRON] Iniciando sync ESPO CRM');
            
            $baseUrl = rtrim(env('ESPOCRM_URL'), '/') . '/';
            $leads = $oportunidades = 0;
            
            // LEADS
            $response = Http::withHeaders($this->getEspoHeaders())
                ->get("{$baseUrl}Lead", ['maxSize' => 200]);
            
            if ($response->successful()) {
                foreach ($response->json('list', []) as $item) {
                    DB::table('leads')->updateOrInsert(
                        ['espocrm_id' => $item['id']],
                        [
                            'nome' => $item['name'] ?? 'Lead sem nome',
                            'status' => $item['status'] ?? 'novo',
                            'telefone' => $item['phoneNumber'] ?? '',
                            'email' => $item['emailAddress'] ?? null,
                            'origem_canal' => $item['source'] ?? null,
                            'updated_at' => now(),
                        ]
                    );
                }
                $leads = count($response->json('list', []));
            }
            
            // OPORTUNIDADES
            $response = Http::withHeaders($this->getEspoHeaders())
                ->get("{$baseUrl}Opportunity", ['maxSize' => 200]);
            
            if ($response->successful()) {
                foreach ($response->json('list', []) as $item) {
                    // Mapear stage ESPO ? estagio banco
                    $estagioMap = [
                        'Prospecting' => 'prospectando',
                        'Qualification' => 'qualificacao',
                        'Proposal' => 'proposta',
                        'Negotiation' => 'negociacao',
                        'Closed Won' => 'ganha',
                        'Closed Lost' => 'perdida',
                    ];
                    
                    $estagio = $estagioMap[$item['stage'] ?? ''] ?? 'prospectando';
                    
                    DB::table('oportunidades')->updateOrInsert(
                        ['espocrm_id' => $item['id']],
                        [
                            'nome' => $item['name'] ?? 'Oportunidade sem nome',
                            'estagio' => $estagio,
                            'valor' => $item['amount'] ?? 0,
                            'tipo' => 'PF',
                            'data_fechamento' => $item['closeDate'] ?? null,
                            'updated_at' => now(),
                        ]
                    );
                }
                $oportunidades = count($response->json('list', []));
            }
            
            Log::info('[CRON] Sync ESPO OK', [
                'leads' => $leads,
                'oportunidades' => $oportunidades
            ]);
            
            return 0;
            
        } catch (\Exception $e) {
            Log::error('[CRON] Erro sync ESPO: ' . $e->getMessage());
            return 1;
        }
    }
}