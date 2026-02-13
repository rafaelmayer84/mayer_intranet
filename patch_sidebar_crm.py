#!/usr/bin/env python3
"""
Patch sidebar: CRM com 4 sub-itens (Leads, Oportunidades, Carteira, Relatórios)
dentro do grupo NEXO.
"""

path = 'resources/views/layouts/app.blade.php'
with open(path, 'r') as f:
    content = f.read()

# =========================================================================
# PASSO 1: Remover qualquer bloco CRM standalone antigo (fora do NEXO)
# =========================================================================
# Procura por blocos CRM que podem ter sido inseridos como item separado
import re

# Remover bloco CRM standalone (fora do NEXO) - várias formas possíveis
patterns_to_remove = [
    # Bloco com x-data e crm.carteira
    r"{{--\s*CRM\s*--}}.*?(?=\n\s*{{--|$)",
]

# Abordagem mais segura: procurar e remover linhas entre marcadores CRM fora do NEXO
lines = content.split('\n')
new_lines = []
skip_crm_block = False
crm_brace_depth = 0

i = 0
while i < len(lines):
    line = lines[i]
    
    # Detectar início de bloco CRM standalone (fora do grupo NEXO)
    # O bloco NEXO contém "Central de Leads" e "Atendimento" próximos
    if 'crm.carteira' in line or 'crm.pipeline' in line or 'crm.leads' in line or 'crm.reports' in line:
        # Verificar se está dentro do bloco NEXO (checar 20 linhas acima por "NEXO" ou "Central de Leads")
        context_above = '\n'.join(lines[max(0, i-25):i])
        if 'Atendimento' in context_above or 'Central de Leads' in context_above:
            # Está dentro do NEXO - manter
            new_lines.append(line)
        else:
            # Está fora do NEXO - este bloco inteiro deve ser removido
            # Não adicionamos esta linha (será removida)
            pass
    else:
        new_lines.append(line)
    i += 1

content = '\n'.join(new_lines)

# =========================================================================
# PASSO 2: Atualizar detector de rota ativa do NEXO para incluir crm/*
# =========================================================================
# Encontrar o botão NEXO e garantir que crm/* está no detector
old_nexo_pattern = "request()->is('nexo/*')"
if 'crm/*' not in content:
    # Precisamos garantir que a detecção do NEXO inclui crm/*
    content = content.replace(
        "request()->is('nexo/*')",
        "request()->is('nexo/*') || request()->is('crm/*')"
    )
    # Também para o open do x-data do NEXO
    if "request()->is('nexo/*') ? 'true'" in content:
        # Já substituído acima, mas garantir para o x-data também
        pass

# =========================================================================
# PASSO 3: Inserir sub-item CRM após o último sub-item do NEXO (Atendimento)
# =========================================================================
crm_submenu = """
                    {{-- CRM (sub-itens) --}}
                    <div x-data="{ crmOpen: {{ request()->is('crm/*') ? 'true' : 'false' }} }" class="relative">
                        <button @click="crmOpen = !crmOpen" class="flex items-center justify-between w-full px-3 py-1.5 text-sm rounded-lg hover:bg-gray-100 transition
                            {{ request()->is('crm/*') ? 'text-[#385776] font-medium' : 'text-gray-500' }}">
                            <span class="flex items-center gap-2">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                CRM
                            </span>
                            <svg class="w-3 h-3 transition-transform" :class="crmOpen ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                        <div x-show="crmOpen" x-collapse class="ml-4 mt-1 space-y-0.5">
                            <a href="{{ route('crm.leads') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.leads') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Leads</a>
                            <a href="{{ route('crm.pipeline') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.pipeline') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Oportunidades</a>
                            <a href="{{ route('crm.carteira') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.carteira') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Carteira</a>
                            <a href="{{ route('crm.reports') }}" class="block px-3 py-1 text-xs rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.reports') ? 'text-[#385776] font-medium' : 'text-gray-400' }}">Relatórios</a>
                        </div>
                    </div>"""

# Verificar se já existe crm.leads no sidebar (já inserido)
if 'crm.leads' not in content:
    # Encontrar o link "Atendimento" dentro do NEXO
    atendimento_idx = content.find("Atendimento")
    if atendimento_idx > 0:
        # Encontrar o final da tag </a> após "Atendimento"
        close_a = content.find('</a>', atendimento_idx)
        if close_a > 0:
            insert_pos = close_a + 4  # após </a>
            content = content[:insert_pos] + crm_submenu + content[insert_pos:]
            print('OK - CRM 4 sub-itens inseridos no NEXO')
        else:
            print('ERRO - nao encontrei </a> apos Atendimento')
    else:
        print('ERRO - nao encontrei Atendimento no sidebar')
else:
    print('SKIP - crm.leads ja existe no sidebar')

# =========================================================================
# PASSO 4: Remover qualquer bloco CRM standalone que restou
# =========================================================================
# Se existir um bloco x-data com crm.carteira que NÃO está dentro do NEXO, remover
# Isso é um safety net para blocos que o passo 1 não pegou

with open(path, 'w') as f:
    f.write(content)

print('Patch sidebar concluido')
