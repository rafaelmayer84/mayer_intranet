<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Services\LeadProcessingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReprocessAllLeads extends Command
{
    protected $signature = 'leads:reprocess-all {--limit=0 : Limitar quantidade} {--force : Reprocessar mesmo os já processados}';
    protected $description = 'Reprocessa todos os leads com a nova IA de Marketing Jurídico';

    public function handle(LeadProcessingService $service)
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $query = Lead::query();

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('sub_area')->orWhere('sub_area', '');
            });
        }

        $query->orderBy('id', 'asc');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $leads = $query->get();
        $total = $leads->count();

        if ($total === 0) {
            $this->info('Nenhum lead para reprocessar.');
            return 0;
        }

        $this->info("=== REPROCESSAMENTO EM MASSA ===");
        $this->info("Total a processar: {$total}");
        $this->info("================================");
        $this->newLine();

        $this->info("Obtendo token SendPulse...");
        $token = $service->getSendPulseToken();
        if (!$token) {
            $this->error('Falha ao obter token SendPulse');
            return 1;
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
            $this->line("[{$num}/{$total}] Lead #{$lead->id} — {$lead->nome} ({$lead->telefone})");

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

                // Se não tem contact_id, buscar pelo telefone
                if (empty($contactId)) {
                    $phone = preg_replace('/[^0-9]/', '', $lead->telefone);
                    if (empty($phone)) {
                        $this->warn("  ⚠ Sem telefone — pulando");
                        $semContato++;
                        continue;
                    }

                    $this->line("  🔍 Buscando contact_id pelo telefone {$phone}...");
                    $contactId = $service->getContactIdByPhone($token, $phone);

                    if (!$contactId) {
                        $this->warn("  ⚠ Não encontrado no SendPulse — pulando");
                        $semContato++;
                        continue;
                    }

                    // Salvar contact_id para futuro
                    $lead->update(['contact_id' => $contactId]);
                    $this->info("  📌 Contact ID encontrado: {$contactId}");
                }

                // Buscar mensagens
                $messages = $service->getChatMessages($token, $contactId);

                if (!$messages || count($messages) === 0) {
                    $this->warn("  ⚠ Sem mensagens — pulando");
                    $semMsg++;
                    continue;
                }

                $msgCount = count($messages);
                $this->line("  📨 {$msgCount} mensagens encontradas");

                // Processar com OpenAI
                $aiResult = $service->processWithOpenAI($messages);

                if (!($aiResult['success'] ?? false)) {
                    $this->error("  ❌ Erro OpenAI: " . ($aiResult['error'] ?? 'desconhecido'));
                    $lead->update(['erro_processamento' => $aiResult['error'] ?? 'Erro OpenAI']);
                    $erros++;
                    sleep(2);
                    continue;
                }

                // Atualizar lead com todos os novos campos
                $updateData = [
                    'resumo_demanda' => $aiResult['resumo_demanda'] ?? '',
                    'palavras_chave' => $aiResult['palavras_chave'] ?? '',
                    'intencao_contratar' => $aiResult['intencao_contratar'] ?? 'não',
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
                ];

                if (empty($lead->cidade) && !empty($aiResult['cidade'])) {
                    $updateData['cidade'] = $aiResult['cidade'];
                }
                if ((!$lead->area_interesse || $lead->area_interesse === 'não identificado') && !empty($aiResult['area_direito'])) {
                    $updateData['area_interesse'] = $aiResult['area_direito'];
                }
                if (empty($lead->gclid) && !empty($aiResult['gclid_extracted'])) {
                    $updateData['gclid'] = $aiResult['gclid_extracted'];
                }

                $lead->update($updateData);

                // Salvar mensagens
                if ($lead->messages()->count() === 0) {
                    $service->saveMessages($lead, $messages);
                }

                $resumo = mb_substr($aiResult['resumo_demanda'] ?? '', 0, 60);
                $this->info("  ✅ {$aiResult['intencao_contratar']} | {$aiResult['potencial_honorarios']} | {$resumo}...");
                $sucesso++;

            } catch (\Exception $e) {
                $this->error("  ❌ Exceção: " . $e->getMessage());
                $lead->update(['erro_processamento' => $e->getMessage()]);
                $erros++;
            }

            sleep(2);
        }

        $this->newLine();
        $this->info("================================");
        $this->info("✅ Sucesso: {$sucesso}");
        $this->info("❌ Erros: {$erros}");
        $this->info("⚠ Sem mensagens: {$semMsg}");
        $this->info("🔍 Não encontrado no SendPulse: {$semContato}");
        $this->info("================================");

        return 0;
    }
}
