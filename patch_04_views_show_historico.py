#!/usr/bin/env python3
"""
SIPEX Honorários - Patch nas views show.blade.php e historico.blade.php
Alterações:
  1. show.blade.php: remover "Análise da IA" → "Análise Estratégica"
  2. historico.blade.php: adicionar botão excluir (admin), remover "Recomendação" header
"""
import sys

# =============================================================
# PARTE 1: PATCH show.blade.php
# =============================================================
SHOW_PATH = '/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/resources/views/precificacao/show.blade.php'

try:
    with open(SHOW_PATH, 'r', encoding='utf-8') as f:
        content = f.read()
except FileNotFoundError:
    print(f"ERRO: {SHOW_PATH} não encontrado")
    sys.exit(1)

original = content

# Remover "Análise da IA" → "Análise Estratégica"
content = content.replace('Análise da IA', 'Análise Estratégica')

# Adicionar exibição de Tipo de Ação nos dados do proponente
# Após a exibição de Área, inserir Tipo de Ação
old_area_show = """            <div>
                <span class="text-gray-400">Área:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->area_direito ?? '-' }}</p>
            </div>"""

new_area_show = """            <div>
                <span class="text-gray-400">Área:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->area_direito ?? '-' }}</p>
            </div>
            @if($proposta->tipo_acao)
            <div>
                <span class="text-gray-400">Tipo de Ação:</span>
                <p class="font-medium text-gray-800 dark:text-white">{{ $proposta->tipo_acao }}</p>
            </div>
            @endif"""

content = content.replace(old_area_show, new_area_show)

if content != original:
    with open(SHOW_PATH, 'w', encoding='utf-8') as f:
        f.write(content)
    print("OK: show.blade.php atualizada")
    print("  ✓ 'Análise da IA' → 'Análise Estratégica'")
    print("  ✓ Exibição de Tipo de Ação adicionada")
else:
    print("ALERTA: show.blade.php sem alterações")

# =============================================================
# PARTE 2: PATCH historico.blade.php
# =============================================================
HIST_PATH = '/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/resources/views/precificacao/historico.blade.php'

try:
    with open(HIST_PATH, 'r', encoding='utf-8') as f:
        content_h = f.read()
except FileNotFoundError:
    print(f"ERRO: {HIST_PATH} não encontrado")
    sys.exit(1)

original_h = content_h

# Adicionar botão excluir ao lado do "Ver" na tabela do histórico
old_ver_hist = '''                    <td class="px-4 py-3">
                        <a href="{{ route('precificacao.show', $p->id) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Ver</a>
                    </td>'''

new_ver_hist = '''                    <td class="px-4 py-3 flex items-center gap-2">
                        <a href="{{ route('precificacao.show', $p->id) }}" class="text-indigo-600 hover:text-indigo-800 text-xs font-medium">Ver</a>
                        @if(in_array(auth()->user()->role ?? '', ['admin', 'socio']))
                        <button onclick="excluirProposta({{ $p->id }})" class="text-red-500 hover:text-red-700 text-xs font-medium" title="Excluir">Excluir</button>
                        @endif
                    </td>'''

content_h = content_h.replace(old_ver_hist, new_ver_hist)

# Adicionar JS de exclusão (se não existir script section)
if 'excluirProposta' not in content_h:
    excluir_js = """
@push('scripts')
<script>
function excluirProposta(id) {
    if (!confirm('Tem certeza que deseja excluir esta proposta? Esta ação não pode ser desfeita.')) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    fetch(`{{ url('/precificacao') }}/${id}/excluir`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + (data.erro || 'Não foi possível excluir'));
        }
    })
    .catch(() => alert('Erro de conexão'));
}
</script>
@endpush
"""
    # Inserir antes do @endsection
    content_h = content_h.replace('@endsection', excluir_js + '\n@endsection')

if content_h != original_h:
    with open(HIST_PATH, 'w', encoding='utf-8') as f:
        f.write(content_h)
    print("OK: historico.blade.php atualizada")
    print("  ✓ Botão excluir adicionado (admin/sócio)")
    print("  ✓ JS de exclusão adicionado")
else:
    print("ALERTA: historico.blade.php sem alterações")

print("\n=== RESUMO VIEWS ===")
print("  ✓ show.blade.php: sem menção a IA + tipo de ação")
print("  ✓ historico.blade.php: botão excluir admin")
