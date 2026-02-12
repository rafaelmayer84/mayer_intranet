#!/usr/bin/env python3
"""
SIPEX Honorários - Patch nas rotas e migration
Alterações:
  1. Adicionar rota DELETE /{id}/excluir no arquivo de rotas
  2. Adicionar coluna tipo_acao na tabela pricing_proposals (migration)
"""
import sys

# =============================================================
# PARTE 1: ADICIONAR ROTA DELETE
# =============================================================
ROUTES_PATH = '/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/routes/_precificacao_routes.php'

try:
    with open(ROUTES_PATH, 'r', encoding='utf-8') as f:
        content = f.read()
except FileNotFoundError:
    print(f"ERRO: Arquivo de rotas não encontrado: {ROUTES_PATH}")
    sys.exit(1)

original_routes = content

if '/excluir' not in content:
    # Inserir rota DELETE após a rota de escolher
    content = content.replace(
        "Route::post('/{id}/escolher', [PrecificacaoController::class, 'escolher'])->name('precificacao.escolher')->whereNumber('id');",
        "Route::post('/{id}/escolher', [PrecificacaoController::class, 'escolher'])->name('precificacao.escolher')->whereNumber('id');\n\n    // Excluir proposta (admin only)\n    Route::delete('/{id}/excluir', [PrecificacaoController::class, 'excluir'])->name('precificacao.excluir')->whereNumber('id');"
    )

    with open(ROUTES_PATH, 'w', encoding='utf-8') as f:
        f.write(content)
    print("OK: Rota DELETE /precificacao/{id}/excluir adicionada")
else:
    print("SKIP: Rota de exclusão já existe")

# =============================================================
# PARTE 2: CRIAR MIGRATION PARA COLUNA tipo_acao
# =============================================================
import datetime

MIGRATION_PATH = '/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/database/migrations/2026_02_11_200000_add_tipo_acao_to_pricing_proposals_table.php'

migration_content = """<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->string('tipo_acao', 200)->nullable()->after('area_direito')->comment('Tipo de ação conforme tabela OAB/SC');
        });
    }

    public function down(): void
    {
        Schema::table('pricing_proposals', function (Blueprint $table) {
            $table->dropColumn('tipo_acao');
        });
    }
};
"""

with open(MIGRATION_PATH, 'w', encoding='utf-8') as f:
    f.write(migration_content)
print("OK: Migration criada para coluna tipo_acao")

# =============================================================
# PARTE 3: ADICIONAR tipo_acao no fillable do Model PricingProposal
# =============================================================
MODEL_PATH = '/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/app/Models/PricingProposal.php'

try:
    with open(MODEL_PATH, 'r', encoding='utf-8') as f:
        model_content = f.read()
except FileNotFoundError:
    print(f"ERRO: Model não encontrado: {MODEL_PATH}")
    sys.exit(1)

if "'tipo_acao'" not in model_content:
    model_content = model_content.replace(
        "'area_direito', 'descricao_demanda',",
        "'area_direito', 'tipo_acao', 'descricao_demanda',"
    )
    with open(MODEL_PATH, 'w', encoding='utf-8') as f:
        f.write(model_content)
    print("OK: tipo_acao adicionado ao fillable do PricingProposal")
else:
    print("SKIP: tipo_acao já está no fillable")

print("\n=== RESUMO ===")
print("  ✓ Rota: DELETE /precificacao/{id}/excluir")
print("  ✓ Migration: add tipo_acao to pricing_proposals")
print("  ✓ Model: tipo_acao no fillable")
