#!/bin/bash
# ============================================================
# PATCH LEADS v2 - Central de Leads Marketing
# 3 melhorias: Paginação | Export Google Ads | IA Recalibrada
# Data: 09/02/2026
# ============================================================
# EXECUTAR: cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
# DEPOIS:   bash patch_leads_v2/deploy_leads_v2.sh
# ============================================================

set -e
echo "============================================================"
echo "  PATCH LEADS v2 - Deploy Iniciando"
echo "============================================================"
echo ""

TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# =============================================
# PASSO 0: BACKUPS
# =============================================
echo "[0/6] Criando backups..."
cp app/Http/Controllers/LeadController.php app/Http/Controllers/LeadController.php.bak_${TIMESTAMP}
cp app/Services/LeadProcessingService.php app/Services/LeadProcessingService.php.bak_${TIMESTAMP}
cp resources/views/leads/index.blade.php resources/views/leads/index.blade.php.bak_${TIMESTAMP}
cp routes/_leads_routes.php routes/_leads_routes.php.bak_${TIMESTAMP}
echo "  ✓ Backups criados com timestamp ${TIMESTAMP}"
echo ""

# =============================================
# PASSO 1: CONTROLLER — Trocar limit(20) por paginate(25)
# =============================================
echo "[1/6] Corrigindo paginação no LeadController..."

# Trocar: $leads = (clone $query)->orderByDesc('data_entrada')->limit(20)->get();
# Por:    $leads = (clone $query)->orderByDesc('data_entrada')->paginate(25)->appends($request->query());
sed -i "s/\$leads = (clone \$query)->orderByDesc('data_entrada')->limit(20)->get();/\$leads = (clone \$query)->orderByDesc('data_entrada')->paginate(25)->appends(\$request->query());/" app/Http/Controllers/LeadController.php

# Verificar se a substituição funcionou
if grep -q "paginate(25)" app/Http/Controllers/LeadController.php; then
    echo "  ✓ Paginação ativada (25 por página)"
else
    echo "  ✗ ERRO: Substituição de paginação falhou. Verificar manualmente."
    exit 1
fi
echo ""

# =============================================
# PASSO 2: CONTROLLER — Adicionar método exportGoogleAds()
# =============================================
echo "[2/6] Adicionando método exportGoogleAds()..."

if grep -q "function exportGoogleAds" app/Http/Controllers/LeadController.php; then
    echo "  ✓ Método exportGoogleAds() já existe, pulando"
else
    # Criar patch temporário
    cat > /tmp/export_patch.php << 'EXPORTEOF'

    /**
     * Exportar leads no formato Google Ads Customer Match
     * GET /leads/export-google-ads?formato=csv|xls
     *
     * Formato: https://support.google.com/google-ads/answer/7659867
     */
    public function exportGoogleAds(Request $request)
    {
        $formato = $request->get('formato', 'csv');
        $filtroArea = $request->get('area', 'todos');
        $filtroIntencao = $request->get('intencao', 'todos');

        $query = Lead::query();

        if ($filtroArea !== 'todos') {
            $query->where('area_interesse', $filtroArea);
        }
        if ($filtroIntencao !== 'todos') {
            $query->where('intencao_contratar', $filtroIntencao);
        }

        $leads = $query->whereNotNull('telefone')
            ->where('telefone', '!=', '')
            ->orderByDesc('data_entrada')
            ->get();

        $filename = 'google_ads_customer_match_' . date('Y-m-d') . '.' . $formato;

        $headers = [
            'Content-Type' => $formato === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($leads) {
            $file = fopen('php://output', 'w');
            // UTF-8 BOM para compatibilidade Excel
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Header Google Ads Customer Match
            fputcsv($file, [
                'Phone', 'Email', 'First Name', 'Last Name',
                'Country', 'Zip',
                // Colunas extras para contexto (Google Ads ignora colunas desconhecidas)
                'Area Juridica', 'Intencao Contratar', 'Potencial Honorarios',
                'Origem Canal', 'Cidade', 'Data Entrada'
            ], ',');

            foreach ($leads as $lead) {
                // Normalizar telefone para formato E.164 com +
                $phone = preg_replace('/[^0-9]/', '', $lead->telefone ?? '');
                if (strlen($phone) >= 10 && !str_starts_with($phone, '55')) {
                    $phone = '55' . $phone;
                }
                if (!str_starts_with($phone, '+')) {
                    $phone = '+' . $phone;
                }

                // Separar nome em First/Last
                $nomeCompleto = trim($lead->nome ?? '');
                $partes = explode(' ', $nomeCompleto, 2);
                $firstName = $partes[0] ?? '';
                $lastName = $partes[1] ?? '';

                fputcsv($file, [
                    $phone,
                    $lead->email ?? '',
                    $firstName,
                    $lastName,
                    'BR',
                    '', // Zip - não temos CEP
                    $lead->area_interesse ?? '',
                    $lead->intencao_contratar ?? '',
                    $lead->potencial_honorarios ?? '',
                    $lead->origem_canal ?? '',
                    $lead->cidade ?? '',
                    $lead->data_entrada ? $lead->data_entrada->format('Y-m-d') : ''
                ], ',');
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
EXPORTEOF

    # Inserir antes da última } do controller
    LAST_BRACE_LINE=$(grep -n "^}" app/Http/Controllers/LeadController.php | tail -1 | cut -d: -f1)
    if [ -n "$LAST_BRACE_LINE" ]; then
        INSERT_LINE=$((LAST_BRACE_LINE - 1))
        sed -i "${INSERT_LINE}r /tmp/export_patch.php" app/Http/Controllers/LeadController.php
        rm /tmp/export_patch.php
        echo "  ✓ Método exportGoogleAds() adicionado"
    else
        echo "  ✗ ERRO: Não encontrei } final do controller"
        exit 1
    fi
fi
echo ""

# =============================================
# PASSO 3: ROTA — Adicionar export-google-ads
# =============================================
echo "[3/6] Adicionando rota /leads/export-google-ads..."

if grep -q "export-google-ads" routes/_leads_routes.php; then
    echo "  ✓ Rota já existe, pulando"
else
    # Adicionar após a linha do webhook
    sed -i '/webhook\/leads/a\
\
Route::get("/leads/export-google-ads", [\\App\\Http\\Controllers\\LeadController::class, "exportGoogleAds"])->name("leads.export-google-ads");' routes/_leads_routes.php
    echo "  ✓ Rota /leads/export-google-ads adicionada"
fi
echo ""

# =============================================
# PASSO 4: VIEW — Adicionar paginação + botão export
# =============================================
echo "[4/6] Atualizando view com paginação e botão export..."

# 4a: Trocar título "Leads Recentes" por "Todos os Leads"
sed -i 's/Leads Recentes/Todos os Leads/g' resources/views/leads/index.blade.php
sed -i 's/Últimos 20 leads/Todos os leads com paginação/g' resources/views/leads/index.blade.php

# 4b: Adicionar links de paginação após </table>
# Procurar a tag </table> e inserir paginação depois
if grep -q "leads->links()" resources/views/leads/index.blade.php; then
    echo "  ✓ Links de paginação já existem"
else
    sed -i '/<\/table>/a\
\
            {{-- Paginação --}}\
            <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">\
                {{ $leads->links() }}\
            </div>' resources/views/leads/index.blade.php
    echo "  ✓ Links de paginação adicionados"
fi

# 4c: Adicionar botões de exportação Google Ads
# Vou inserir antes da tabela (antes da linha <table)
if grep -q "export-google-ads" resources/views/leads/index.blade.php; then
    echo "  ✓ Botões de exportação já existem"
else
    sed -i '/<table class="min-w-full/i\
            {{-- Barra de Exportação Google Ads --}}\
            <div class="px-4 py-3 bg-gray-50 dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">\
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ $totalLeads }} leads encontrados</span>\
                <div class="flex gap-2">\
                    <a href="{{ route('"'"'leads.export-google-ads'"'"', array_merge(request()->query(), ['"'"'formato'"'"' => '"'"'csv'"'"'])) }}"\
                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 transition">\
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>\
                        CSV Google Ads\
                    </a>\
                    <a href="{{ route('"'"'leads.export-google-ads'"'"', array_merge(request()->query(), ['"'"'formato'"'"' => '"'"'xls'"'"'])) }}"\
                       class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 transition">\
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>\
                        XLS Google Ads\
                    </a>\
                </div>\
            </div>' resources/views/leads/index.blade.php
    echo "  ✓ Botões de exportação Google Ads adicionados"
fi
echo ""

# =============================================
# PASSO 5: IA — Recalibrar prompt OpenAI
# =============================================
echo "[5/6] Recalibrando prompt da IA de marketing..."

# Substituir o prompt inteiro por versão recalibrada
# O prompt atual começa com "Você é um ANALISTA SÊNIOR DE MARKETING JURÍDICO"
# Vou substituir por versão focada em tráfego pago / performance

cp patch_leads_v2/LeadProcessingService_prompt_patch.php /tmp/prompt_patch.php

# Encontrar a linha que contém o início do prompt
PROMPT_START=$(grep -n 'Você é um ANALISTA SÊNIOR DE MARKETING JURÍDICO' app/Services/LeadProcessingService.php | head -1 | cut -d: -f1)

if [ -z "$PROMPT_START" ]; then
    echo "  ✗ AVISO: Não encontrei o prompt atual. Verificar manualmente."
    echo "  → O arquivo LeadProcessingService_prompt_patch.php contém o novo prompt"
    echo "  → Substitua o conteúdo entre \$prompt = <<<PROMPT e PROMPT;"
else
    # Encontrar a linha PROMPT; (fim do heredoc)
    PROMPT_END=$(awk "NR>=$PROMPT_START && /^PROMPT;/{print NR; exit}" app/Services/LeadProcessingService.php)

    if [ -n "$PROMPT_END" ]; then
        # Substituir o bloco inteiro do prompt
        # Primeiro, extrair tudo antes do prompt
        head -n $((PROMPT_START - 1)) app/Services/LeadProcessingService.php > /tmp/lps_new.php
        # Inserir o novo prompt
        cat /tmp/prompt_patch.php >> /tmp/lps_new.php
        # Inserir tudo depois do PROMPT;
        tail -n +$((PROMPT_END + 1)) app/Services/LeadProcessingService.php >> /tmp/lps_new.php
        # Substituir
        cp /tmp/lps_new.php app/Services/LeadProcessingService.php
        rm /tmp/lps_new.php /tmp/prompt_patch.php
        echo "  ✓ Prompt da IA recalibrado com foco em tráfego pago"
    else
        echo "  ✗ AVISO: Não encontrei PROMPT; de fechamento"
    fi
fi
echo ""

# =============================================
# PASSO 6: LIMPAR CACHE
# =============================================
echo "[6/6] Limpando cache..."
php artisan route:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
echo "  ✓ Cache limpo"
echo ""

# =============================================
# VERIFICAÇÃO FINAL
# =============================================
echo "============================================================"
echo "  VERIFICAÇÃO FINAL"
echo "============================================================"

ERRORS=0

# Check 1: Paginação
if grep -q "paginate(25)" app/Http/Controllers/LeadController.php; then
    echo "  ✓ Paginação server-side ativa"
else
    echo "  ✗ Paginação NÃO aplicada"
    ERRORS=$((ERRORS + 1))
fi

# Check 2: Export method
if grep -q "function exportGoogleAds" app/Http/Controllers/LeadController.php; then
    echo "  ✓ Método exportGoogleAds() existe"
else
    echo "  ✗ Método exportGoogleAds() NÃO encontrado"
    ERRORS=$((ERRORS + 1))
fi

# Check 3: Rota
if grep -q "export-google-ads" routes/_leads_routes.php; then
    echo "  ✓ Rota /leads/export-google-ads configurada"
else
    echo "  ✗ Rota NÃO encontrada"
    ERRORS=$((ERRORS + 1))
fi

# Check 4: View paginação
if grep -q 'leads->links()' resources/views/leads/index.blade.php; then
    echo "  ✓ Links de paginação na view"
else
    echo "  ✗ Links de paginação NÃO encontrados"
    ERRORS=$((ERRORS + 1))
fi

# Check 5: View export buttons
if grep -q "export-google-ads" resources/views/leads/index.blade.php; then
    echo "  ✓ Botões de exportação na view"
else
    echo "  ✗ Botões de exportação NÃO encontrados"
    ERRORS=$((ERRORS + 1))
fi

# Check 6: Prompt recalibrado
if grep -q "ANALISTA DE TRÁFEGO PAGO" app/Services/LeadProcessingService.php; then
    echo "  ✓ Prompt IA recalibrado"
elif grep -q "ANALISTA SÊNIOR DE PERFORMANCE" app/Services/LeadProcessingService.php; then
    echo "  ✓ Prompt IA recalibrado"
else
    echo "  ⚠ Prompt IA pode não ter sido substituído — verificar manualmente"
fi

# Check 7: Teste de sintaxe PHP
echo ""
echo "  Testando sintaxe PHP..."
php -l app/Http/Controllers/LeadController.php 2>&1
php -l app/Services/LeadProcessingService.php 2>&1

# Check 8: Rotas
echo ""
echo "  Rotas de leads:"
php artisan route:list --path=leads 2>/dev/null | head -15

echo ""
if [ $ERRORS -eq 0 ]; then
    echo "============================================================"
    echo "  ✓ DEPLOY CONCLUÍDO COM SUCESSO"
    echo "============================================================"
else
    echo "============================================================"
    echo "  ⚠ DEPLOY COM $ERRORS AVISOS — VERIFICAR"
    echo "============================================================"
fi

echo ""
echo "PRÓXIMOS PASSOS:"
echo "  1. Acesse /leads → tabela agora mostra TODOS os 403 leads com paginação"
echo "  2. Clique 'CSV Google Ads' → baixa arquivo compatível Customer Match"
echo "  3. Para reprocessar leads com nova IA:"
echo "     php artisan leads:import-reprocess --step=reprocess --limit=5"
echo "  4. Verifique os primeiros 5 leads reprocessados antes de rodar em massa"
