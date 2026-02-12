#!/usr/bin/env python3
"""
SIPEX Honorários - Patch do PrecificacaoController.php
Alterações:
  1. Adicionar método excluir() (admin/sócio only)
  2. Adicionar tipo_acao na validation do gerar()
  3. Incluir tipo_acao no salvamento da proposta
"""
import sys

CTRL_PATH = '/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/app/Http/Controllers/PrecificacaoController.php'

try:
    with open(CTRL_PATH, 'r', encoding='utf-8') as f:
        content = f.read()
except FileNotFoundError:
    print(f"ERRO: Arquivo não encontrado: {CTRL_PATH}")
    sys.exit(1)

original = content

# =============================================================
# 1. ADICIONAR tipo_acao na validation do método gerar()
# =============================================================
content = content.replace(
    "'area_direito' => 'nullable|string|max:100',",
    "'area_direito' => 'nullable|string|max:100',\n            'tipo_acao' => 'nullable|string|max:200',"
)

# =============================================================
# 2. INCLUIR tipo_acao no array de criação da proposta
#    Inserir após 'area_direito' => $areaDireito,
# =============================================================
content = content.replace(
    "'area_direito' => $areaDireito,",
    "'area_direito' => $areaDireito,\n            'tipo_acao' => $request->tipo_acao,"
)

# =============================================================
# 3. INCLUIR tipo_acao nos dados da demanda passados à IA
#    Inserir no bloco que sobrescreve os inputs do formulário
# =============================================================
content = content.replace(
    """if ($request->filled('descricao_demanda')) {
            $dadosProponente['demanda']['descricao_completa'] = $request->descricao_demanda;
        }""",
    """if ($request->filled('descricao_demanda')) {
            $dadosProponente['demanda']['descricao_completa'] = $request->descricao_demanda;
        }
        if ($request->filled('tipo_acao')) {
            $dadosProponente['demanda']['tipo_acao'] = $request->tipo_acao;
        }"""
)

# =============================================================
# 4. ADICIONAR método excluir() antes do bloco de CALIBRAÇÃO
# =============================================================
excluir_method = '''
    /**
     * Excluir proposta (admin/sócio only)
     */
    public function excluir(int $id)
    {
        $userRole = Auth::user()->role ?? '';
        if (!in_array($userRole, ['admin', 'socio'])) {
            return response()->json(['erro' => 'Acesso restrito à administração'], 403);
        }

        $proposal = PricingProposal::findOrFail($id);
        $proposal->delete();

        Log::info('Precificação: Proposta excluída', ['id' => $id, 'user' => Auth::id()]);

        return response()->json(['success' => true]);
    }

    // ===================== CALIBRAÇÃO (ADMIN ONLY) ====================='''

content = content.replace(
    '    // ===================== CALIBRAÇÃO (ADMIN ONLY) =====================',
    excluir_method
)

# =============================================================
# SALVAR
# =============================================================
if content == original:
    print("ALERTA: Nenhuma alteração aplicada! Verifique os marcadores.")
    sys.exit(1)

with open(CTRL_PATH, 'w', encoding='utf-8') as f:
    f.write(content)

print("OK: PrecificacaoController.php atualizado")
print("  ✓ Validation: tipo_acao adicionado")
print("  ✓ Salvamento: tipo_acao persistido no banco")
print("  ✓ Dados IA: tipo_acao incluído no pacote")
print("  ✓ Método excluir(): admin/sócio only, DELETE")
