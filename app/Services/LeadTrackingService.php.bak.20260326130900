<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * LeadTrackingService — Busca dados de rastreamento de origem para um lead.
 *
 * Consulta a tabela lead_tracking pelo telefone normalizado (E.164)
 * dentro de uma janela de 48 horas e retorna os dados de tracking
 * mais recentes (GCLID, UTM, FBCLID, landing page, referrer).
 *
 * Este service é chamado APÓS o lead ser criado com sucesso.
 * Se não encontrar dados, retorna array vazio — zero efeito colateral.
 *
 * @version 1.0.0
 * @date    2026-02-09
 */
class LeadTrackingService
{
    /**
     * Janela de tempo para buscar tracking (horas).
     * Clique no site → mensagem no WhatsApp normalmente < 5min,
     * mas usamos 48h para cobrir casos de delay.
     */
    private const TRACKING_WINDOW_HOURS = 48;

    /**
     * Busca o tracking mais recente para o telefone informado.
     *
     * @param string $phone Telefone no formato E.164 (ex: 5547999990001)
     * @return array Dados de tracking ou array vazio
     */
    public static function findByPhone(string $phone): array
    {
        if (empty($phone)) {
            return [];
        }

        try {
            // Normalizar: apenas dígitos
            $normalized = preg_replace('/\D/', '', $phone);

            if (strlen($normalized) < 10) {
                return [];
            }

            // Buscar tracking mais recente dentro da janela de tempo
            $tracking = DB::table('lead_tracking')
                ->where('phone', $normalized)
                ->where('created_at', '>=', now()->subHours(self::TRACKING_WINDOW_HOURS))
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$tracking) {
                // Tentar variações do telefone (com/sem 55, com/sem 9° dígito)
                $variations = self::phoneVariations($normalized);

                foreach ($variations as $variation) {
                    $tracking = DB::table('lead_tracking')
                        ->where('phone', $variation)
                        ->where('created_at', '>=', now()->subHours(self::TRACKING_WINDOW_HOURS))
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if ($tracking) break;
                }
            }

            if (!$tracking) {
                return [];
            }

            Log::info('[LeadTracking] Match encontrado', [
                'phone' => $normalized,
                'gclid' => $tracking->gclid ? 'presente' : 'ausente',
                'utm_source' => $tracking->utm_source,
                'tracking_id' => $tracking->id,
            ]);

            return [
                'gclid'        => $tracking->gclid,
                'fbclid'       => $tracking->fbclid,
                'utm_source'   => $tracking->utm_source,
                'utm_medium'   => $tracking->utm_medium,
                'utm_campaign' => $tracking->utm_campaign,
                'utm_content'  => $tracking->utm_content,
                'utm_term'     => $tracking->utm_term,
                'landing_page' => $tracking->landing_page,
                'referrer_url' => $tracking->referrer,
            ];
        } catch (\Throwable $e) {
            Log::warning('[LeadTracking] Erro ao buscar tracking', [
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Aplica dados de tracking a um lead existente.
     *
     * @param \App\Models\Lead $lead
     * @return bool true se atualizou, false se não encontrou tracking
     */
    public static function applyToLead($lead): bool
    {
        if (!$lead || !$lead->telefone) {
            return false;
        }

        $tracking = self::findByPhone($lead->telefone);

        if (empty($tracking)) {
            return false;
        }

        $updates = [];

        // GCLID — só preenche se estiver vazio no lead
        if (!empty($tracking['gclid']) && empty($lead->gclid)) {
            $updates['gclid'] = $tracking['gclid'];
        }

        // Origem canal — determinar automaticamente pelo tracking
        if (empty($lead->origem_canal)) {
            $updates['origem_canal'] = self::determineOrigemCanal($tracking);
        }

        // UTMs e demais campos
        $trackingFields = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'fbclid', 'landing_page', 'referrer_url'];

        foreach ($trackingFields as $field) {
            if (!empty($tracking[$field]) && empty($lead->{$field})) {
                $updates[$field] = $tracking[$field];
            }
        }

        if (empty($updates)) {
            return false;
        }

        try {
            $lead->update($updates);

            Log::info('[LeadTracking] Tracking aplicado ao lead', [
                'lead_id' => $lead->id,
                'fields_updated' => array_keys($updates),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('[LeadTracking] Erro ao aplicar tracking', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Determina a origem do canal baseado nos dados de tracking.
     */
    private static function determineOrigemCanal(array $tracking): string
    {
        // GCLID presente = Google Ads (certeza absoluta)
        if (!empty($tracking['gclid'])) {
            return 'google_ads';
        }

        // FBCLID presente = Facebook/Meta Ads
        if (!empty($tracking['fbclid'])) {
            return 'facebook_ads';
        }

        // UTM source define a origem
        $source = strtolower($tracking['utm_source'] ?? '');
        if ($source) {
            $map = [
                'google'    => 'google_ads',
                'facebook'  => 'facebook_ads',
                'instagram' => 'instagram_ads',
                'meta'      => 'facebook_ads',
                'youtube'   => 'youtube_ads',
                'tiktok'    => 'tiktok_ads',
                'linkedin'  => 'linkedin_ads',
            ];

            foreach ($map as $key => $origin) {
                if (str_contains($source, $key)) {
                    return $origin;
                }
            }
        }

        // UTM medium = cpc/ppc = tráfego pago
        $medium = strtolower($tracking['utm_medium'] ?? '');
        if (in_array($medium, ['cpc', 'ppc', 'paid', 'paidsocial'])) {
            return 'trafego_pago';
        }

        // Referrer para determinar orgânico
        $referrer = strtolower($tracking['referrer_url'] ?? '');
        if ($referrer) {
            if (str_contains($referrer, 'google.com')) return 'organico';
            if (str_contains($referrer, 'bing.com')) return 'organico';
            if (str_contains($referrer, 'facebook.com')) return 'redes_sociais';
            if (str_contains($referrer, 'instagram.com')) return 'redes_sociais';
        }

        // Tem landing page mas sem referrer = acesso direto (pode ser indicação)
        if (!empty($tracking['landing_page']) && empty($referrer)) {
            return 'acesso_direto';
        }

        return 'desconhecido';
    }

    /**
     * Gera variações do telefone para matching flexível.
     * Ex: 5547999990001 → [47999990001, 554799990001, ...]
     */
    private static function phoneVariations(string $phone): array
    {
        $variations = [];

        // Se começa com 55, tentar sem
        if (str_starts_with($phone, '55') && strlen($phone) >= 12) {
            $variations[] = substr($phone, 2);
        }

        // Se NÃO começa com 55, tentar com
        if (!str_starts_with($phone, '55') && strlen($phone) >= 10) {
            $variations[] = '55' . $phone;
        }

        // Variação com/sem 9° dígito (celulares brasileiros)
        // 5547999990001 (13 dígitos com 9) → 554799990001 (12 dígitos sem 9)
        if (strlen($phone) === 13 && str_starts_with($phone, '55')) {
            $ddd = substr($phone, 2, 2);
            $ninthDigit = substr($phone, 4, 1);
            if ($ninthDigit === '9') {
                $variations[] = '55' . $ddd . substr($phone, 5);
            }
        }

        // 554799990001 (12 dígitos sem 9) → 5547999990001 (13 dígitos com 9)
        if (strlen($phone) === 12 && str_starts_with($phone, '55')) {
            $ddd = substr($phone, 2, 2);
            $variations[] = '55' . $ddd . '9' . substr($phone, 4);
        }

        return $variations;
    }
}
