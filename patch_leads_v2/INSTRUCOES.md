# INSTRUÇÕES DE DEPLOY — PATCH LEADS v2
# Data: 09/02/2026

## O QUE ESTE PATCH FAZ

### 1. Paginação — Exibir todos os 403 leads
- Troca `limit(20)` por `paginate(25)` no LeadController@index
- Adiciona links de paginação na view (navegação por página)
- Título muda de "Leads Recentes" para "Todos os Leads"

### 2. Exportação Google Ads Customer Match
- Nova rota GET `/leads/export-google-ads?formato=csv|xls`
- Gera arquivo compatível com Google Ads Customer Match
- Colunas: Phone (+5547...), Email, First Name, Last Name, Country (BR), Zip
- Colunas extras: Área Jurídica, Intenção, Potencial, Origem, Cidade, Data
- Botões "CSV Google Ads" e "XLS Google Ads" na tabela de leads
- Respeita filtros aplicados (área, intenção, etc.)

### 3. IA Recalibrada — Foco em Tráfego Pago
Mudanças no prompt:
- Persona: "Analista de Performance e Tráfego Pago" (antes: "Marketing Jurídico")
- Palavras-chave: Agora exige linguagem LEIGA de busca Google, proíbe termos técnicos
- Área do direito: Adicionadas áreas "Contratual", "Bancário", "Trânsito" (antes ausentes)
- Intenção: Critérios mais claros com exemplos concretos de cada nível
- Contexto: Explica que dados alimentam dashboards e decisões de investimento em mídia

## EXECUÇÃO

### Passo 1: Upload do patch
Faça upload da pasta `patch_leads_v2/` para a raiz do projeto:
```
~/domains/mayeradvogados.adv.br/public_html/Intranet/patch_leads_v2/
```

### Passo 2: Executar via SSH
```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
bash patch_leads_v2/deploy_leads_v2.sh
```

### Passo 3: Validar
1. Acesse `/leads` → verificar tabela com paginação (25 por página)
2. Clique "CSV Google Ads" → verificar download com colunas corretas
3. Reprocessar 5 leads teste com nova IA:
   ```bash
   php artisan leads:import-reprocess --step=reprocess --limit=5
   ```
4. Verificar se os 5 leads reprocessados têm:
   - Palavras-chave em linguagem leiga
   - Área do direito correta
   - Resumo objetivo e comercial

### Passo 4: Se tudo OK, reprocessar em massa
```bash
php artisan leads:import-reprocess --step=reprocess
```

## ROLLBACK
```bash
cd ~/domains/mayeradvogados.adv.br/public_html/Intranet
TIMESTAMP=XXXXX  # usar o timestamp do backup

cp app/Http/Controllers/LeadController.php.bak_${TIMESTAMP} app/Http/Controllers/LeadController.php
cp app/Services/LeadProcessingService.php.bak_${TIMESTAMP} app/Services/LeadProcessingService.php
cp resources/views/leads/index.blade.php.bak_${TIMESTAMP} resources/views/leads/index.blade.php
cp routes/_leads_routes.php.bak_${TIMESTAMP} routes/_leads_routes.php

php artisan route:clear && php artisan config:clear && php artisan cache:clear && php artisan view:clear
```
