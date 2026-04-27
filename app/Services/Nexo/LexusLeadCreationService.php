<?php

namespace App\Services\Nexo;

use App\Models\Lead;
use App\Models\NexoLexusSessao;
use App\Services\Crm\CrmLeadSyncService;
use Illuminate\Support\Facades\Log;

class LexusLeadCreationService
{
    /*
     * Nota de arquitetura: LeadProcessingService::processLead() não é reutilizável
     * aqui porque requer payload do webhook SendPulse + chama API SendPulse + OpenAI.
     * O Lexus já tem todos os dados estruturados na sessão. Lead::create() direto é
     * a abordagem correta para este fluxo paralelo. Autorizado por Rafael em 27/04/2026.
     */

    private const ORIGEM = 'whatsapp_lexus_v3';

    public function criarOuAtualizar(NexoLexusSessao $sessao): ?int
    {
        // Idempotência: já tem lead, retorna
        if ($sessao->lead_id) {
            return $sessao->lead_id;
        }

        // Proteção: só qualificados geram lead
        if ($sessao->etapa !== 'qualificado') {
            return null;
        }

        try {
            $nome     = $sessao->nome_cliente ?: ($sessao->contato ?: 'Lead WhatsApp');
            $telefone = $sessao->phone;

            // Dedup: telefone único em leads
            $existente = Lead::where('telefone', $telefone)->first();
            if ($existente) {
                Log::warning('LEXUS-V3 lead_creation: dedup �� lead já existe', [
                    'sessao_id' => $sessao->id,
                    'lead_id'   => $existente->id,
                    'telefone'  => $telefone,
                ]);
                $sessao->lead_id = $existente->id;
                $sessao->saveQuietly();
                return $existente->id;
            }

            $lead = Lead::create([
                'nome'               => mb_substr($nome, 0, 250),
                'telefone'           => $telefone,
                'area_interesse'     => $sessao->area_provavel,
                'cidade'             => $sessao->cidade,
                'resumo_demanda'     => $sessao->resumo_caso,
                'intencao_contratar' => $this->mapearIntencao($sessao->intencao_contratar),
                'urgencia'           => $sessao->urgencia,
                'origem_canal'       => self::ORIGEM,
                'status'             => 'novo',
                'data_entrada'       => now(),
                'metadata'           => [
                    'sessao_lexus_id'   => $sessao->id,
                    'briefing_operador' => $sessao->briefing_operador,
                ],
            ]);

            // Persistir lead_id na sessão
            $sessao->lead_id = $lead->id;
            $sessao->saveQuietly();

            Log::warning('LEXUS-V3 lead_creation: criado', [
                'sessao_id' => $sessao->id,
                'lead_id'   => $lead->id,
                'area'      => $lead->area_interesse,
                'cidade'    => $lead->cidade,
            ]);

            // Sincronizar com CRM (mesmo padrão de processLead)
            try {
                (new CrmLeadSyncService())->syncLead($lead);
            } catch (\Throwable $e) {
                Log::warning('LEXUS-V3 lead_creation: CRM sync falhou', [
                    'lead_id' => $lead->id,
                    'erro'    => $e->getMessage(),
                ]);
            }

            return $lead->id;

        } catch (\Throwable $e) {
            Log::error('LEXUS-V3 lead_creation: falhou', [
                'sessao_id' => $sessao->id,
                'erro'      => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function mapearIntencao(?string $intencao): string
    {
        return match ($intencao) {
            'alta'  => 'sim',
            'media' => 'talvez',
            'baixa' => 'não',
            default => 'talvez',
        };
    }
}
