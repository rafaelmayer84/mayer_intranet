<?php

namespace App\Services\Nexo;

use App\Models\NexoLexusSessao;
use App\Models\WaConversation;
use Illuminate\Support\Facades\Log;

class LexusWaConversationLinker
{
    private const ETAPAS_ENCERRAMENTO = ['qualificado', 'desqualificado', 'spam', 'ja_cliente'];

    private const CATEGORY_MAP = [
        'qualificado'    => 'lead_qualificado',
        'desqualificado' => 'lead_desqualificado',
        'spam'           => 'FORN',
        'ja_cliente'     => 'cliente_ativo',
    ];

    public function linkar(NexoLexusSessao $sessao): void
    {
        $wa = WaConversation::where('phone', $sessao->phone)->latest()->first();

        if (!$wa) {
            Log::info('LEXUS-V3 wa_link: wa_conversation não encontrada', [
                'phone'     => $sessao->phone,
                'sessao_id' => $sessao->id,
            ]);
            return;
        }

        $updates = [];

        if ($sessao->lead_id && !$wa->linked_lead_id) {
            $updates['linked_lead_id'] = $sessao->lead_id;
        }

        if ($sessao->cliente_id && !$wa->linked_cliente_id) {
            $updates['linked_cliente_id'] = $sessao->cliente_id;
        }

        $category = self::CATEGORY_MAP[$sessao->etapa] ?? null;
        if ($category) {
            $updates['category'] = $category;
        }

        if (in_array($sessao->etapa, self::ETAPAS_ENCERRAMENTO, true)) {
            $updates['bot_ativo'] = false;
        }

        if (empty($updates)) {
            return;
        }

        $wa->update($updates);

        Log::warning('LEXUS-V3 wa_link: atualizado', [
            'wa_id'      => $wa->id,
            'sessao_id'  => $sessao->id,
            'etapa'      => $sessao->etapa,
            'updates'    => array_keys($updates),
        ]);
    }
}
