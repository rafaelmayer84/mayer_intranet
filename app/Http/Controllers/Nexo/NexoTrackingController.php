<?php

namespace App\Http\Controllers\Nexo;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NexoTrackingController extends Controller
{
    /**
     * ═══════════════════════════════════════════════════════════════
     * PRE-TRACK WHATSAPP LEAD
     * ═══════════════════════════════════════════════════════════════
     * 
     * Recebe dados de tracking ANTES do usuário enviar a primeira mensagem
     * no WhatsApp. Cria registro temporário em leads_tracking aguardando
     * o webhook processar a primeira mensagem.
     * 
     * POST /nexo/api/pre-track-whatsapp-lead
     * 
     * Body:
     * {
     *   "phone": "+5547999999999",
     *   "gclid": "CjwKCAiA...",
     *   "fbclid": "IwAR0...",
     *   "utm_source": "google",
     *   "utm_medium": "cpc",
     *   "utm_campaign": "advocacia_sc",
     *   "utm_content": "ad_variant_1",
     *   "utm_term": "advogado+itajai",
     *   "referrer_url": "https://google.com/search?q=...",
     *   "landing_page": "https://mayeradvogados.adv.br/areas/civil"
     * }
     * 
     * Response:
     * {
     *   "success": true,
     *   "tracking_id": 123
     * }
     */
    public function preTrackWhatsAppLead(Request $request)
    {
        // ─────────────────────────────────────────────────────────
        // Validação
        // ─────────────────────────────────────────────────────────
        
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|max:20',
            'gclid' => 'nullable|string|max:255',
            'fbclid' => 'nullable|string|max:255',
            'utm_source' => 'nullable|string|max:100',
            'utm_medium' => 'nullable|string|max:100',
            'utm_campaign' => 'nullable|string|max:100',
            'utm_content' => 'nullable|string|max:100',
            'utm_term' => 'nullable|string|max:100',
            'referrer_url' => 'nullable|string',
            'landing_page' => 'nullable|string'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // ─────────────────────────────────────────────────────────
        // Normalização do telefone
        // ─────────────────────────────────────────────────────────
        
        $phone = $this->normalizePhone($request->input('phone'));
        
        if (!$phone) {
            return response()->json([
                'success' => false,
                'error' => 'Telefone inválido'
            ], 422);
        }
        
        // ─────────────────────────────────────────────────────────
        // Verificação de dados relevantes
        // ─────────────────────────────────────────────────────────
        
        $hasTrackingData = $request->filled('gclid') || 
                          $request->filled('fbclid') || 
                          $request->filled('utm_source') || 
                          $request->filled('utm_medium');
        
        if (!$hasTrackingData) {
            return response()->json([
                'success' => false,
                'error' => 'Nenhum dado de tracking fornecido'
            ], 422);
        }
        
        // ─────────────────────────────────────────────────────────
        // Insert na tabela temporária
        // ─────────────────────────────────────────────────────────
        
        try {
            $trackingId = DB::table('leads_tracking')->insertGetId([
                'phone' => $phone,
                'gclid' => $request->input('gclid'),
                'fbclid' => $request->input('fbclid'),
                'utm_source' => $request->input('utm_source'),
                'utm_medium' => $request->input('utm_medium'),
                'utm_campaign' => $request->input('utm_campaign'),
                'utm_content' => $request->input('utm_content'),
                'utm_term' => $request->input('utm_term'),
                'referrer_url' => $request->input('referrer_url'),
                'landing_page' => $request->input('landing_page'),
                'created_at' => now()
            ]);
            
            return response()->json([
                'success' => true,
                'tracking_id' => $trackingId
            ]);
            
        } catch (\Exception $e) {
            \Log::error('NEXO Pre-Track Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao salvar dados de tracking'
            ], 500);
        }
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * NORMALIZAÇÃO DE TELEFONE
     * ═══════════════════════════════════════════════════════════════
     * 
     * Remove caracteres não numéricos e garante formato +55XXXXXXXXXXX
     */
    private function normalizePhone(string $phone): ?string
    {
        // Remove tudo que não for dígito
        $digits = preg_replace('/\D/', '', $phone);
        
        if (empty($digits)) {
            return null;
        }
        
        // Se começar com 55, já está OK
        if (str_starts_with($digits, '55')) {
            return '+' . $digits;
        }
        
        // Se começar com 0, remove
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        
        // Adiciona código do Brasil
        return '+55' . $digits;
    }
    
    /**
     * ═══════════════════════════════════════════════════════════════
     * CLEANUP DE REGISTROS ANTIGOS
     * ═══════════════════════════════════════════════════════════════
     * 
     * Remove registros de leads_tracking com mais de 7 dias que não
     * foram matchados com conversas.
     * 
     * Executar via cron: php artisan nexo:cleanup-tracking
     */
    public function cleanupOldTracking()
    {
        $deleted = DB::table('leads_tracking')
            ->where('created_at', '<', now()->subDays(7))
            ->whereNull('matched_at')
            ->delete();
        
        \Log::info("NEXO Tracking Cleanup: {$deleted} registros removidos");
        
        return $deleted;
    }
}
