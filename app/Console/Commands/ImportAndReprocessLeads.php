<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ImportAndReprocessLeads extends Command
{
    protected $signature = 'leads:import-reprocess {--step=all : all|import|reprocess} {--limit=0} {--days=0 : Reprocessar apenas leads dos últimos N dias} {--force : Forçar reprocessamento mesmo de leads já processados}';
    protected $description = 'Importa leads do sistema legado e reprocessa com IA de Marketing Jurídico';

    public function handle(LeadProcessingService $service)
    {
        $step = $this->option('step');
        $limit = (int) $this->option('limit');

        if ($step === 'all' || $step === 'import') {
            $this->importFromLegacy();
        }

        if ($step === 'all' || $step === 'reprocess') {
            $this->reprocessAll($service, $limit);
        }

        return 0;
    }

    private function importFromLegacy()
    {
        $this->info('');
        $this->info('=== ETAPA 1: IMPORTAÇÃO DO SISTEMA LEGADO ===');

        try {
            $legacyLeads = DB::connection('legacy')->table('leads')->get();
        } catch (\Exception $e) {
            $this->error('Erro ao conectar no banco legado: ' . $e->getMessage());
            $this->info('Verifique se a conexão "legacy" está configurada no database.php');
            return;
        }

        $total = $legacyLeads->count();
        $this->info("Leads no sistema legado: {$total}");

        $importados = 0;
        $atualizados = 0;
        $duplicados = 0;

        foreach ($legacyLeads as $legacy) {
            $phone = preg_replace('/[^0-9]/', '', $legacy->telefone ?? '');
            if (empty($phone)) {
                continue;
            }

            // Verificar se já existe pelo telefone
            $existing = Lead::where('telefone', $phone)->first();

            if ($existing) {
                // Atualizar dados que estão vazios na Intranet mas existem no legado
                $updates = [];
                if (empty($existing->resumo_demanda) && !empty($legacy->resumo_demanda)) {
                    $updates['resumo_demanda'] = $legacy->resumo_demanda;
                }
                if (empty($existing->cidade) && !empty($legacy->cidade)) {
                    $updates['cidade'] = $legacy->cidade;
                }
                if (empty($existing->area_interesse) && !empty($legacy->area_interesse)) {
                    $updates['area_interesse'] = $legacy->area_interesse;
                }
                if (empty($existing->palavras_chave) && !empty($legacy->palavras_chave)) {
                    $updates['palavras_chave'] = $legacy->palavras_chave;
                }
                if (empty($existing->gclid) && !empty($legacy->gclid)) {
                    $updates['gclid'] = $legacy->gclid;
                }
                if (empty($existing->intencao_contratar) && !empty($legacy->intencao_contratar)) {
                    $updates['intencao_contratar'] = $legacy->intencao_contratar;
                }

                if (!empty($updates)) {
                    $existing->update($updates);
                    $atualizados++;
                } else {
                    $duplicados++;
                }
                continue;
            }

            // Inserir novo lead
            Lead::create([
                'nome' => $legacy->nome ?? 'Desconhecido',
                'telefone' => $phone,
                'cidade' => $legacy->cidade ?? '',
                'area_interesse' => $legacy->area_interesse ?? '',
                'resumo_demanda' => $legacy->resumo_demanda ?? '',
                'palavras_chave' => $legacy->palavras_chave ?? '',
                'intencao_contratar' => $legacy->intencao_contratar ?? 'não',
                'gclid' => $legacy->gclid ?? '',
                'status' => $legacy->status ?? 'novo',
                'data_entrada' => $legacy->data_entrada ?? now(),
            ]);

            $importados++;
        }

        $this->info("✅ Importados: {$importados}");
        $this->info("🔄 Atualizados: {$atualizados}");
        $this->info("⏭ Já existiam: {$duplicados}");
        $this->newLine();
    }

    private function reprocessAll(LeadProcessingService $service, int $limit)
    {
        $this->info('=== ETAPA 2: REPROCESSAMENTO COM IA ===');

        // Buscar leads sem os novos campos preenchidos
        $query = Lead::where(function ($q) {
            $q->whereNull('sub_area')->orWhere('sub_area', '');
        })->orderBy('id', 'asc');

        // Filtro por período
        $days = (int) $this->option('days');
        if ($days > 0) {
            $query->where('data_entrada', '>=', now()->subDays($days));
            $this->info("Filtro: últimos {$days} dias");
        }

        // Forçar reprocessamento de todos (ignora filtro sub_area)
        if ($this->option('force')) {
            $query = Lead::orderBy('id', 'asc');
            if ($days > 0) {
                $query->where('data_entrada', '>=', now()->subDays($days));
            }
            if ($limit > 0) {
                $query->limit($limit);
            }
            $this->info("Modo FORCE: reprocessando todos, inclusive já processados");
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $leads = $query->get();
        $total = $leads->count();

        if ($total === 0) {
            $this->info('Nenhum lead para reprocessar.');
            return;
        }

        $this->info("Total a reprocessar: {$total}");
        $this->info("Tempo estimado: ~" . ceil($total * 12 / 60) . " minutos");
        $this->newLine();

        // Token SendPulse
        $this->info("Obtendo token SendPulse...");
        $token = $service->getSendPulseToken();
        if (!$token) {
            $this->error('Falha ao obter token SendPulse');
            return;
        }
        $this->info("Token obtido ✅");
        $this->newLine();

        $sucesso = 0;
        $erros = 0;
        $semMsg = 0;
        $semContato = 0;
        $tokenTime = time();

        foreach ($leads as $index => $lead) {
            $num = $index + 1;
            $this->line("[{$num}/{$total}] #{$lead->id} — {$lead->nome} ({$lead->telefone})");

            // Renovar token a cada 45 minutos
            if (time() - $tokenTime > 2700) {
                $this->info("  Renovando token...");
                $token = $service->getSendPulseToken();
                if (!$token) {
                    $this->error("  Falha ao renovar token, abortando.");
                    break;
                }
                $tokenTime = time();
            }

            try {
                $contactId = $lead->contact_id;

                // Buscar contact_id pelo telefone se não tem
                if (empty($contactId)) {
                    $phone = preg_replace('/[^0-9]/', '', $lead->telefone);
                    if (empty($phone)) {
                        $this->warn("  ⚠ Sem telefone");
                        $semContato++;
                        continue;
                    }

                    $contactId = $service->getContactIdByPhone($token, $phone);

                    if (!$contactId) {
                        $this->warn("  ⚠ Não encontrado no SendPulse");
                        $semContato++;
                        continue;
                    }

                    $lead->update(['contact_id' => $contactId]);
                    $this->line("  📌 Contact ID: {$contactId}");
                }

                // Buscar mensagens
                $messages = $service->getChatMessages($token, $contactId);

                if (!$messages || count($messages) === 0) {
                    $this->warn("  ⚠ Sem mensagens");
                    $semMsg++;
                    sleep(1);
                    continue;
                }

                $this->line("  📨 " . count($messages) . " mensagens");

                // Processar com OpenAI
                $aiResult = $service->processWithOpenAI($messages);

                if (!($aiResult['success'] ?? false)) {
                    $this->error("  ❌ Erro IA: " . ($aiResult['error'] ?? '?'));
                    $lead->update(['erro_processamento' => $aiResult['error'] ?? 'Erro IA']);
                    $erros++;
                    sleep(2);
                    continue;
                }

                // Atualizar com novos campos
                $lead->update([
                    'resumo_demanda' => $aiResult['resumo_demanda'] ?? $lead->resumo_demanda,
                    'palavras_chave' => $aiResult['palavras_chave'] ?? $lead->palavras_chave,
                    'intencao_contratar' => $aiResult['intencao_contratar'] ?? $lead->intencao_contratar,
                    'intencao_justificativa' => $aiResult['intencao_justificativa'] ?? '',
                    'sub_area' => $aiResult['sub_area'] ?? '',
                    'complexidade' => $aiResult['complexidade'] ?? '',
                    'urgencia' => $aiResult['urgencia'] ?? '',
                    'objecoes' => $aiResult['objecoes'] ?? '',
                    'gatilho_emocional' => $aiResult['gatilho_emocional'] ?? '',
                    'perfil_socioeconomico' => $aiResult['perfil_socioeconomico'] ?? '',
                    'potencial_honorarios' => $aiResult['potencial_honorarios'] ?? '',
                    'origem_canal' => $aiResult['origem_canal'] ?? 'nao_identificado',
                    'erro_processamento' => null,
                ]);

                if (empty($lead->cidade) && !empty($aiResult['cidade'])) {
                    $lead->update(['cidade' => $aiResult['cidade']]);
                }
                if ((!$lead->area_interesse || $lead->area_interesse === 'não identificado') && !empty($aiResult['area_direito'])) {
                    $lead->update(['area_interesse' => $aiResult['area_direito']]);
                }

                // Salvar mensagens
                if ($lead->messages()->count() === 0) {
                    $service->saveMessages($lead, $messages);
                }

                $intencao = $aiResult['intencao_contratar'] ?? '?';
                $potencial = $aiResult['potencial_honorarios'] ?? '?';
                $resumo = mb_substr($aiResult['resumo_demanda'] ?? '', 0, 50);
                $this->info("  ✅ {$intencao} | {$potencial} | {$resumo}...");
                $sucesso++;

            } catch (\Exception $e) {
                $this->error("  ❌ " . $e->getMessage());
                $erros++;
            }

            sleep(2);
        }

        $this->newLine();
        $this->info("=================================");
        $this->info("✅ Sucesso:      {$sucesso}");
        $this->info("❌ Erros:        {$erros}");
        $this->info("⚠ Sem mensagens: {$semMsg}");
        $this->info("🔍 Sem contato:  {$semContato}");
        $this->info("=================================");
    }
}
