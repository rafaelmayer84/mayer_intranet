<?php

namespace App\Services\RelatorioCeo;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class WhatsAppContentCollector
{
    // Limite de chars por conversa para não explodir o contexto do Claude
    private const MAX_CHARS_POR_CONVERSA = 600;
    private const MAX_CONVERSAS          = 80;

    public function coletar(Carbon $inicio, Carbon $fim): array
    {
        $inicioStr = $inicio->toDateTimeString();
        $fimStr    = $fim->toDateTimeString();

        // Conversas ativas no período, priorizando as mais relevantes
        $conversas = DB::table('wa_conversations as c')
            ->where(function ($q) use ($inicioStr, $fimStr) {
                $q->whereBetween('c.created_at', [$inicioStr, $fimStr])
                  ->orWhereBetween('c.last_message_at', [$inicioStr, $fimStr]);
            })
            ->select(
                'c.id', 'c.name', 'c.status', 'c.priority',
                'c.category', 'c.created_at', 'c.last_message_at',
                'c.linked_lead_id', 'c.linked_cliente_id', 'c.linked_processo_id'
            )
            ->orderByRaw("FIELD(c.priority,'critica','urgente','alta','normal')")
            ->orderByDesc('c.last_message_at')
            ->limit(self::MAX_CONVERSAS)
            ->get()
            ->toArray();

        if (empty($conversas)) {
            return ['total_conversas_analisadas' => 0, 'conversas' => [], 'temas_recorrentes' => []];
        }

        $ids = array_column($conversas, 'id');

        // Busca mensagens incoming (direction=1) dessas conversas
        $mensagens = DB::table('wa_messages')
            ->whereIn('conversation_id', $ids)
            ->where('direction', 1) // incoming = cliente
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->whereBetween('sent_at', [$inicioStr, $fimStr])
            ->orderBy('conversation_id')
            ->orderBy('sent_at')
            ->get(['conversation_id', 'body', 'sent_at', 'is_human'])
            ->groupBy('conversation_id');

        // Monta estrutura por conversa
        $conversasDetalhadas = [];
        foreach ($conversas as $conv) {
            $conv = (array) $conv;
            $msgs = $mensagens->get($conv['id'], collect([]));

            if ($msgs->isEmpty()) {
                continue;
            }

            // Concatena mensagens do cliente até o limite de chars
            $textoCliente = '';
            foreach ($msgs as $msg) {
                $body = trim((string) $msg->body);
                if (empty($body) || mb_strlen($body) < 3) {
                    continue;
                }
                $linha = $body . ' ';
                if (mb_strlen($textoCliente) + mb_strlen($linha) > self::MAX_CHARS_POR_CONVERSA) {
                    break;
                }
                $textoCliente .= $linha;
            }

            if (empty(trim($textoCliente))) {
                continue;
            }

            $conversasDetalhadas[] = [
                'cliente'           => $conv['name'] ?: 'Não identificado',
                'status'            => $conv['status'],
                'priority'          => $conv['priority'],
                'category'          => $conv['category'],
                'tem_lead'          => !empty($conv['linked_lead_id']),
                'tem_processo'      => !empty($conv['linked_processo_id']),
                'iniciada_em'       => Carbon::parse($conv['created_at'])->format('d/m H:i'),
                'ultima_msg_em'     => Carbon::parse($conv['last_message_at'])->format('d/m H:i'),
                'total_msgs_cliente'=> $msgs->count(),
                'conteudo_cliente'  => trim($textoCliente),
            ];
        }

        // Palavras/expressões mais frequentes nas mensagens (top temas brutos)
        $temasFrequentes = $this->extrairTemasFrequentes($mensagens->toArray());

        // Stats de volume por dia
        $volumeDiario = DB::table('wa_messages')
            ->whereBetween('sent_at', [$inicioStr, $fimStr])
            ->where('direction', 1)
            ->whereNotNull('body')
            ->selectRaw('DATE(sent_at) as dia, count(*) as msgs')
            ->groupBy('dia')
            ->orderBy('dia')
            ->pluck('msgs', 'dia')
            ->toArray();

        // Urgências e críticas
        $criticas = array_filter($conversasDetalhadas, fn($c) => in_array($c['priority'], ['critica', 'urgente']));

        return [
            'total_conversas_analisadas' => count($conversasDetalhadas),
            'conversas_criticas_urgentes'=> count($criticas),
            'volume_diario_msgs'         => $volumeDiario,
            'conversas'                  => $conversasDetalhadas,
            'temas_recorrentes'          => $temasFrequentes,
        ];
    }

    private function extrairTemasFrequentes(array $mensagensPorConversa): array
    {
        $stopwords = [
            'de', 'a', 'o', 'que', 'e', 'do', 'da', 'em', 'um', 'para', 'é', 'com',
            'uma', 'os', 'no', 'se', 'na', 'por', 'mais', 'as', 'dos', 'como', 'mas',
            'foi', 'ao', 'ele', 'das', 'tem', 'à', 'seu', 'sua', 'ou', 'quando', 'muito',
            'já', 'eu', 'também', 'só', 'pelo', 'pela', 'até', 'isso', 'ela', 'entre',
            'era', 'depois', 'sem', 'mesmo', 'aos', 'seus', 'quem', 'nas', 'me', 'esse',
            'eles', 'você', 'essa', 'num', 'nem', 'suas', 'meu', 'minha', 'oi', 'bom',
            'dia', 'boa', 'tarde', 'noite', 'olá', 'ok', 'sim', 'não', 'tudo', 'bem',
            'então', 'pois', 'assim', 'ser', 'ter', 'fazer', 'porque', 'essa', 'esse',
            'aqui', 'agora', 'ainda', 'vou', 'vai', 'obrigado', 'obrigada', 'pode',
        ];

        $freq = [];
        foreach ($mensagensPorConversa as $msgs) {
            foreach ($msgs as $msg) {
                $palavras = preg_split('/\s+/', mb_strtolower(strip_tags($msg->body ?? '')));
                foreach ($palavras as $palavra) {
                    $palavra = preg_replace('/[^a-záéíóúâêîôûãõçü]/u', '', $palavra);
                    if (mb_strlen($palavra) < 4) {
                        continue;
                    }
                    if (in_array($palavra, $stopwords)) {
                        continue;
                    }
                    $freq[$palavra] = ($freq[$palavra] ?? 0) + 1;
                }
            }
        }

        arsort($freq);
        return array_slice($freq, 0, 40, true);
    }
}
