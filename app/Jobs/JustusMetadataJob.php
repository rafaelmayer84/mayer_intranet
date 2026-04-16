<?php

namespace App\Jobs;

use App\Models\JustusConversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JustusMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;

    public function __construct(
        private int $conversationId,
        private string $userMessage,
        private string $assistantContent,
        private int $assistantCount,
    ) {}

    public function handle(): void
    {
        $conversation = JustusConversation::find($this->conversationId);
        if (!$conversation) return;

        $apiKey = config('justus.anthropic_api_key');
        if (empty($apiKey)) return;

        $needsRename = in_array($conversation->title, ['Nova Análise', 'Nova Analise', null, '']);
        $needsTitle  = $this->assistantCount <= 2 && str_starts_with($conversation->title ?? '', 'Nova Anal');

        if ($needsRename) {
            $this->autoRenameConversation($conversation, $apiKey);
        }

        if ($needsTitle && str_starts_with($conversation->fresh()->title ?? '', 'Nova Anal')) {
            $this->generateTitle($conversation, $apiKey);
        }
    }

    private function autoRenameConversation(JustusConversation $conversation, string $apiKey): void
    {
        try {
            $profile = $conversation->processProfile;
            if ($profile && ($profile->numero_cnj || $profile->classe || $profile->reu)) {
                $abrev = [
                    'Execução de Título Extrajudicial' => 'Exec. Título',
                    'Cumprimento de Sentença'           => 'Cumpr. Sentença',
                    'Ação de Cobrança'                  => 'Cobrança',
                    'Reclamação Trabalhista'             => 'Recl. Trabalhista',
                    'Ação Indenizatória'                => 'Indenizatória',
                    'Procedimento Comum'                => 'Proc. Comum',
                ];
                $parts = [];
                if ($profile->classe)      $parts[] = $abrev[$profile->classe] ?? mb_substr($profile->classe, 0, 25);
                if ($profile->numero_cnj)  $parts[] = $profile->numero_cnj;
                if ($profile->reu) {
                    $nomes = explode(' ', trim($profile->reu));
                    $parts[] = count($nomes) > 1 ? $nomes[0] . ' ' . end($nomes) : $nomes[0];
                }
                $conversation->update(['title' => mb_substr(implode(' — ', $parts), 0, 255)]);
                return;
            }

            $msgPreview = mb_substr($this->userMessage, 0, 500);
            $response = Http::withHeaders([
                'x-api-key'          => $apiKey,
                'anthropic-version'  => '2023-06-01',
                'content-type'       => 'application/json',
            ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 50,
                'system'     => 'Gere um título curto (máximo 60 caracteres) para esta análise jurídica. Responda APENAS o título, sem aspas, sem explicação.',
                'messages'   => [['role' => 'user', 'content' => "Mensagem: {$msgPreview}"]],
            ]);

            if ($response->successful()) {
                $title = trim(trim($response->json('content.0.text') ?? ''), '"\'');
                if (!empty($title) && mb_strlen($title) <= 255) {
                    $conversation->update(['title' => $title]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('JustusMetadataJob: auto-rename falhou', ['error' => $e->getMessage()]);
        }
    }

    private function generateTitle(JustusConversation $conversation, string $apiKey): void
    {
        try {
            $snippet     = mb_substr($this->userMessage, 0, 300);
            $respSnippet = mb_substr($this->assistantContent, 0, 200);

            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 50,
                'system'     => 'Gere um título curto (máximo 6 palavras) para esta conversa jurídica. Responda APENAS o título, sem aspas, sem pontuação final.',
                'messages'   => [['role' => 'user', 'content' => "Pergunta: {$snippet}\nResposta: {$respSnippet}"]],
            ]);

            if ($response->successful()) {
                $title = trim($response->json('content.0.text') ?? '');
                $title = str_replace(['"', "'", '.', '!', '?'], '', $title);
                if (!empty($title) && mb_strlen($title) <= 60) {
                    $conversation->update(['title' => $title]);
                }
            }
        } catch (\Exception $e) {
            Log::warning('JustusMetadataJob: generateTitle falhou', ['error' => $e->getMessage()]);
        }
    }
}
