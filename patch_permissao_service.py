#!/usr/bin/env python3
"""
PATCH DO PermissaoService - Adicionar módulos operacionais aos presets
Executar ANTES do ativar_seguranca.py
"""
import os

BASE = os.path.expanduser("~/domains/mayeradvogados.adv.br/public_html/Intranet")
filepath = os.path.join(BASE, "app/Services/PermissaoService.php")

with open(filepath, 'r') as f:
    content = f.read()

# -----------------------------------------------------------------------
# Substituir os presets do coordenador
# -----------------------------------------------------------------------
old_coordenador = """        'coordenador' => [
            // RESULTADOS - visualiza tudo
            'resultados.visao-gerencial' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.clientes-mercado' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.metas-kpi' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            
            // GDP - visualiza equipe
            'gdp.minha-performance' => ['permissoes' => ['visualizar'], 'escopo' => 'equipe'],
            'gdp.equipe' => ['permissoes' => ['visualizar'], 'escopo' => 'equipe'],
            'gdp.metas' => ['permissoes' => ['visualizar'], 'escopo' => 'equipe'],
            
            // Quadro de Avisos
            'avisos.listar' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
        ],"""

new_coordenador = """        'coordenador' => [
            // RESULTADOS - visualiza tudo
            'resultados.visao-gerencial' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.clientes-mercado' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.processos-internos' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.metas-kpi' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            
            // OPERACIONAL - acesso completo
            'operacional.leads' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'operacional.nexo' => ['permissoes' => ['visualizar', 'editar'], 'escopo' => 'todos'],
            'operacional.nexo-gerencial' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'operacional.siric' => ['permissoes' => ['visualizar', 'editar', 'executar'], 'escopo' => 'todos'],
            'operacional.precificacao' => ['permissoes' => ['visualizar', 'editar', 'executar'], 'escopo' => 'todos'],
            'operacional.manuais' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            
            // GDP - visualiza equipe
            'gdp.minha-performance' => ['permissoes' => ['visualizar'], 'escopo' => 'equipe'],
            'gdp.equipe' => ['permissoes' => ['visualizar'], 'escopo' => 'equipe'],
            'gdp.metas' => ['permissoes' => ['visualizar'], 'escopo' => 'equipe'],
            
            // Quadro de Avisos
            'avisos.listar' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
        ],"""

# -----------------------------------------------------------------------
# Substituir os presets do sócio
# -----------------------------------------------------------------------
old_socio = """        'socio' => [
            // RESULTADOS - visualiza tudo
            'resultados.visao-gerencial' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.clientes-mercado' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.metas-kpi' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            
            // GDP - apenas própria performance
            'gdp.minha-performance' => ['permissoes' => ['visualizar'], 'escopo' => 'proprio'],
            
            // Quadro de Avisos
            'avisos.listar' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
        ],"""

new_socio = """        'socio' => [
            // RESULTADOS - visualiza tudo
            'resultados.visao-gerencial' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.clientes-mercado' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.processos-internos' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'resultados.metas-kpi' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            
            // OPERACIONAL - acesso a ferramentas de trabalho
            'operacional.leads' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            'operacional.nexo' => ['permissoes' => ['visualizar', 'editar'], 'escopo' => 'todos'],
            'operacional.siric' => ['permissoes' => ['visualizar', 'editar', 'executar'], 'escopo' => 'todos'],
            'operacional.precificacao' => ['permissoes' => ['visualizar', 'editar', 'executar'], 'escopo' => 'todos'],
            'operacional.manuais' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
            
            // GDP - apenas própria performance
            'gdp.minha-performance' => ['permissoes' => ['visualizar'], 'escopo' => 'proprio'],
            
            // Quadro de Avisos
            'avisos.listar' => ['permissoes' => ['visualizar'], 'escopo' => 'todos'],
        ],"""

# Aplicar patches
if old_coordenador in content:
    content = content.replace(old_coordenador, new_coordenador)
    print("✓ Presets do COORDENADOR atualizados")
else:
    print("✗ Presets do coordenador não encontrados - verificar manualmente")

if old_socio in content:
    content = content.replace(old_socio, new_socio)
    print("✓ Presets do SÓCIO atualizados")
else:
    print("✗ Presets do sócio não encontrados - verificar manualmente")

with open(filepath, 'w') as f:
    f.write(content)

print("\nPermissaoService atualizado com sucesso!")
print("Módulos adicionados: operacional.leads, operacional.nexo, operacional.nexo-gerencial,")
print("  operacional.siric, operacional.precificacao, operacional.manuais, resultados.processos-internos")
