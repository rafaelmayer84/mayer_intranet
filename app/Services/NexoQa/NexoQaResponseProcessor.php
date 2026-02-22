<?php

namespace App\Services\NexoQa;

use App\Models\NexoQaResponseContent;
use App\Models\NexoQaResponseIdentity;
use App\Models\NexoQaSampledTarget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NexoQaResponseProcessor
{
    /**
     * Palavras-chave de opt-out (case-insensitive).
     */
    private const OPT_OUT_KEYWORDS = ['SAIR', 'PARAR', 'STOP', 'CANCELAR'];

    /**
     * Processa uma resposta recebida via webhook.
     *
     * @param string $messageText Texto da mensagem recebida
     * @param string $senderPhone Telefone do remetente (formato variável)
     * @param array  $rawPayload  Payload bruto do webhook
     *
     * @return array ['status' => 'ok'|'opt_out'|'invalid'|'not_found'|'duplicate', 'detail' => ...]
     */
    public function process(string $messageText, string $senderPhone, array $rawPayload = []): array
    {
        $normalized = NexoQaResolverService::normalizePhone($senderPhone);
        $phoneHash = hash('sha256', $normalized);
        $textTrimmed = trim($messageText);

        // Verificar opt-out global
        if ($this->isOptOut($textTrimmed)) {
            return $this->handleOptOut($phoneHash);
        }

        // Tentar extrair token da mensagem
        $parsed = $this->parseResponse($textTrimmed);

        if ($parsed === null) {
            return ['status' => 'invalid', 'detail' => 'Formato não reconhecido'];
        }

        // Buscar target pelo token (primeiros 8 chars do UUID, case-insensitive)
        $target = NexoQaSampledTarget::where('send_status', 'SENT')
            ->where(DB::raw('UPPER(LEFT(token, 8))'), strtoupper($parsed['token']))
            ->first();

        if ($target === null) {
            return ['status' => 'not_found', 'detail' => 'Token não encontrado ou target não SENT'];
        }

        // Verificar se já respondeu (idempotência)
        if (NexoQaResponseContent::where('target_id', $target->id)->exists()) {
            return ['status' => 'duplicate', 'detail' => 'Resposta já registrada para este token'];
        }

        // Gravar identidade (tabela separada do conteúdo)
        NexoQaResponseIdentity::create([
            'target_id' => $target->id,
            'phone_hash' => $phoneHash,
            'answered_at' => now(),
            'opted_out' => false,
            'created_at' => now(),
        ]);

        // Gravar conteúdo (free_text será criptografado pelo mutator)
        // NOTA: responsible_user_id NÃO é gravado no conteúdo; só via target
        NexoQaResponseContent::create([
            'target_id' => $target->id,
            'score_1_5' => $parsed['score_1_5'],
            'nps' => $parsed['nps'],
            'tags' => null,
            'free_text' => $parsed['free_text'],
            'raw_payload' => $rawPayload,
            'created_at' => now(),
        ]);

        Log::info('[NexoQA] Resposta registrada', [
            'target_id' => $target->id,
            'score_1_5' => $parsed['score_1_5'],
            'nps' => $parsed['nps'],
            'has_text' => !empty($parsed['free_text']),
        ]);

        return ['status' => 'ok', 'detail' => 'Resposta registrada com sucesso'];
    }

    /**
     * Tenta parsear a mensagem no formato: TOKEN NOTA1 NOTA2 COMENTÁRIO
     * Exemplo: "ABC12345 5 9 Ótimo atendimento!"
     *
     * @return array|null ['token' => ..., 'score_1_5' => ..., 'nps' => ..., 'free_text' => ...]
     */
    private function parseResponse(string $text): ?array
    {
        // Padrão: TOKEN(8chars) + opcionalmente nota1 + nota2 + texto livre
        // Match: alfanumérico 8 chars no início
        if (!preg_match('/^([A-Za-z0-9]{8})\s+(.*)/s', $text, $m)) {
            // Tentar só o token com nota colada
            if (!preg_match('/^([A-Za-z0-9]{8})\s*$/s', $text)) {
                return null;
            }
            // Apenas token sem notas
            return [
                'token' => strtoupper(substr($text, 0, 8)),
                'score_1_5' => null,
                'nps' => null,
                'free_text' => null,
            ];
        }

        $token = strtoupper($m[1]);
        $rest = trim($m[2]);

        // Extrair números do resto
        $parts = preg_split('/\s+/', $rest, 3);

        $score15 = null;
        $nps = null;
        $freeText = null;

        if (isset($parts[0]) && is_numeric($parts[0])) {
            $val = (int) $parts[0];
            if ($val >= 1 && $val <= 5) {
                $score15 = $val;
            }
        }

        if (isset($parts[1]) && is_numeric($parts[1])) {
            $val = (int) $parts[1];
            if ($val >= 0 && $val <= 10) {
                $nps = $val;
            }
        }

        // Texto livre: tudo após as notas
        if (isset($parts[2])) {
            $freeText = trim($parts[2]);
        } elseif (isset($parts[1]) && !is_numeric($parts[1])) {
            // Se parte[1] não é número, junta como texto
            $freeText = trim($parts[1]);
        } elseif (isset($parts[0]) && !is_numeric($parts[0])) {
            // Se parte[0] não é número, tudo é texto (sem notas)
            $freeText = trim($rest);
        }

        return [
            'token' => $token,
            'score_1_5' => $score15,
            'nps' => $nps,
            'free_text' => !empty($freeText) ? $freeText : null,
        ];
    }

    /**
     * Verifica se a mensagem é opt-out.
     */
    private function isOptOut(string $text): bool
    {
        return in_array(strtoupper(trim($text)), self::OPT_OUT_KEYWORDS, true);
    }

    /**
     * Marca opt-out para todas as identidades desse phone_hash.
     */
    private function handleOptOut(string $phoneHash): array
    {
        // Marcar todas as identidades existentes como opted_out
        NexoQaResponseIdentity::where('phone_hash', $phoneHash)
            ->update(['opted_out' => true]);

        // Criar registro de opt-out mesmo sem ter target ativo
        // (para impedir amostragens futuras via phone_hash)
        $target = NexoQaSampledTarget::where('phone_hash', $phoneHash)
            ->where('send_status', 'SENT')
            ->latest('sampled_at')
            ->first();

        if ($target !== null && !NexoQaResponseIdentity::where('target_id', $target->id)->exists()) {
            NexoQaResponseIdentity::create([
                'target_id' => $target->id,
                'phone_hash' => $phoneHash,
                'answered_at' => now(),
                'opted_out' => true,
                'created_at' => now(),
            ]);
        }

        Log::info('[NexoQA] Opt-out registrado', ['phone_hash' => substr($phoneHash, 0, 12) . '...']);

        return ['status' => 'opt_out', 'detail' => 'Opt-out registrado'];
    }
}
