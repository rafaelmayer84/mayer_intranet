<?php
namespace App\Services;

use App\Models\Crm\CrmServiceRequest;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ChamadoSlaService
{
    /**
     * Analisa chamado via IA: define SLA, complexidade, sugestão de atribuição, ações recomendadas.
     * Roda silenciosamente no backend (dispatch afterResponse).
     */
    public function analyze(CrmServiceRequest $sr): void
    {
        try {
            $categorias = CrmServiceRequest::categorias();
            $catLabel = $categorias[$sr->category]['label'] ?? $sr->category;

            $prompt = $this->buildPrompt($sr, $catLabel);
            $response = $this->callOpenAI($prompt);

            if (!$response) return;

            // Parse resposta IA
            $data = $this->parseResponse($response);
            if (!$data) return;

            $slaHours = floatval($data['sla_hours'] ?? 24);
            $deadline = Carbon::now()->addHours($slaHours);

            $updates = [
                'sla_hours'         => $slaHours,
                'sla_deadline'      => $deadline,
                'sla_complexity'    => $data['complexity'] ?? 'media',
                'sla_justification' => $data['justification'] ?? null,
                'sla_analyzed_at'   => now(),
                'ai_triage'         => $data,
            ];

            // Auto-atribuição se IA sugeriu e chamado não tem atribuído
            if (!$sr->assigned_to_user_id && !empty($data['suggested_assignee_role'])) {
                $assignee = $this->findBestAssignee($data['suggested_assignee_role'], $data['suggested_assignee_name'] ?? null);
                if ($assignee) {
                    $updates['assigned_to_user_id'] = $assignee->id;
                    $updates['assigned_at'] = now();
                }
            }

            // Se IA marcou como auto-resolvível, adiciona nota
            if (!empty($data['auto_resolution']) && $data['auto_resolution'] !== 'none') {
                $updates['resolution_notes'] = '[Pré-triagem] ' . ($data['auto_resolution_note'] ?? '');
            }

            // Se prioridade sugerida difere e faz sentido escalar
            if (!empty($data['suggested_priority']) && $data['suggested_priority'] !== $sr->priority) {
                if ($this->shouldEscalate($sr->priority, $data['suggested_priority'])) {
                    $updates['priority'] = $data['suggested_priority'];
                }
            }

            $sr->update($updates);

            Log::info("[SIATE] Triagem IA chamado #{$sr->id}: SLA={$slaHours}h, complexity={$data['complexity']}, assignee=" . ($updates['assigned_to_user_id'] ?? 'none'));

        } catch (\Throwable $e) {
            Log::warning("[SIATE] Falha triagem IA chamado #{$sr->id}: " . $e->getMessage());
        }
    }

    private function buildPrompt(CrmServiceRequest $sr, string $catLabel): string
    {
        $context = "Chamado #{$sr->id} de um escritório de advocacia.\n";
        $context .= "Categoria: {$catLabel}\n";
        $context .= "Prioridade informada: {$sr->priority}\n";
        $context .= "Assunto: {$sr->subject}\n";
        $context .= "Descrição: {$sr->description}\n";
        $context .= "Impacto: " . ($sr->impact ?? 'não informado') . "\n";
        $context .= "Valor estimado: " . ($sr->estimated_value ? 'R$' . number_format($sr->estimated_value, 2, ',', '.') : 'não informado') . "\n";
        $context .= "Prazo desejado: " . ($sr->desired_deadline ? $sr->desired_deadline->format('d/m/Y') : 'não informado') . "\n";
        $context .= "Vinculado a cliente: " . ($sr->account_id ? 'Sim' : 'Não (interno)') . "\n";
        $context .= "Requer aprovação: " . ($sr->requires_approval ? 'Sim' : 'Não') . "\n";

        return "Você é o sistema de triagem do escritório Mayer Advogados. Analise o chamado abaixo e retorne APENAS um JSON válido (sem markdown, sem explicação fora do JSON).\n\n"
            . $context . "\n"
            . "Equipe disponível:\n"
            . "- Rafael (admin/sócio): decisões estratégicas, TI, financeiro\n"
            . "- Patrícia (coordenador): operacional, gestão equipe, processos internos\n"
            . "- Anelise (advogado): jurídico, processos, clientes\n"
            . "- Franciéli (advogado): jurídico, processos, clientes\n\n"
            . "Retorne JSON com:\n"
            . "{\n"
            . "  \"sla_hours\": número de horas úteis para resolução (mínimo 1, máximo 720),\n"
            . "  \"complexity\": \"baixa\" | \"media\" | \"alta\" | \"critica\",\n"
            . "  \"justification\": \"justificativa do prazo em 1 frase\",\n"
            . "  \"suggested_priority\": \"baixa\" | \"normal\" | \"alta\" | \"urgente\",\n"
            . "  \"suggested_assignee_role\": \"admin\" | \"coordenador\" | \"advogado\" | null,\n"
            . "  \"suggested_assignee_name\": nome da pessoa ou null,\n"
            . "  \"requires_human_review\": true/false,\n"
            . "  \"human_review_reason\": motivo se requires_human_review=true ou null,\n"
            . "  \"auto_resolution\": \"none\" | \"info\" | \"redirect\",\n"
            . "  \"auto_resolution_note\": nota se auto_resolution != none ou null,\n"
            . "  \"tags\": [array de tags relevantes, max 3]\n"
            . "}";
    }

    private function callOpenAI(string $prompt): ?string
    {
        $apiKey = config('services.openai.api_key', env('OPENAI_API_KEY'));
        if (!$apiKey) return null;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
            'Content-Type'  => 'application/json',
        ])->timeout(30)->post('https://api.openai.com/v1/chat/completions', [
            'model'    => 'gpt-5-mini',
            'messages' => [
                ['role' => 'system', 'content' => 'Responda APENAS com JSON válido. Sem markdown, sem texto adicional.'],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_completion_tokens' => 1000,
        ]);

        if (!$response->successful()) {
            Log::warning('[SIATE] OpenAI HTTP ' . $response->status());
            return null;
        }

        return $response->json('choices.0.message.content');
    }

    private function parseResponse(?string $raw): ?array
    {
        if (!$raw) return null;
        $raw = trim($raw);
        $raw = preg_replace('/^```json\s*/', '', $raw);
        $raw = preg_replace('/\s*```$/', '', $raw);

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['sla_hours'])) {
            Log::warning('[SIATE] Parse falhou: ' . substr($raw, 0, 300));
            return null;
        }
        return $data;
    }

    private function findBestAssignee(string $role, ?string $name): ?User
    {
        if ($name) {
            $user = User::where('name', 'LIKE', "%{$name}%")->first();
            if ($user) return $user;
        }

        if ($role === 'admin') {
            return User::where('role', 'admin')->first();
        }

        if ($role === 'coordenador') {
            return User::where('role', 'coordenador')->first();
        }

        if ($role === 'advogado') {
            // Round-robin entre advogados ativos
            $advogados = User::where('role', 'advogado')
                ->whereNotNull('datajuri_proprietario_id')
                ->get();
            if ($advogados->isEmpty()) return null;

            $counts = [];
            foreach ($advogados as $adv) {
                $counts[$adv->id] = CrmServiceRequest::where('assigned_to_user_id', $adv->id)
                    ->whereIn('status', ['aberto', 'em_andamento'])
                    ->count();
            }
            asort($counts);
            $leastBusy = array_key_first($counts);
            return User::find($leastBusy);
        }

        return null;
    }

    private function shouldEscalate(string $current, string $suggested): bool
    {
        $levels = ['baixa' => 1, 'normal' => 2, 'alta' => 3, 'urgente' => 4];
        return ($levels[$suggested] ?? 0) > ($levels[$current] ?? 0);
    }
}
