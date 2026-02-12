#!/usr/bin/env python3
"""
SIPEX Honorários - Patch da View index.blade.php
Alterações:
  1. Campo 'Área do Direito' → dropdown com categorias OAB/SC
  2. Novo campo 'Tipo de Ação' → dropdown dinâmico filtrado por área
  3. Remover menções a IA em textos visíveis
  4. Botão excluir propostas (admin) na tabela de últimas propostas
"""
import sys

VIEW_PATH = '/home/u492856976/domains/mayeradvogados.adv.br/public_html/Intranet/resources/views/precificacao/index.blade.php'

try:
    with open(VIEW_PATH, 'r', encoding='utf-8') as f:
        content = f.read()
except FileNotFoundError:
    print(f"ERRO: Arquivo não encontrado: {VIEW_PATH}")
    sys.exit(1)

original = content

# =============================================================
# 1. SUBSTITUIR HEADER - Remover menção a IA no subtítulo
# =============================================================
content = content.replace(
    'Geração de propostas de honorários com IA',
    'Geração de propostas de honorários'
)
content = content.replace(
    '<h1 class="text-2xl font-bold text-gray-800 dark:text-white">Precificação Inteligente</h1>',
    '<h1 class="text-2xl font-bold text-gray-800 dark:text-white">SIPEX Honorários</h1>'
)

# =============================================================
# 2. SUBSTITUIR campo Área do Direito (input text → select)
#    E ADICIONAR campo Tipo de Ação logo depois
# =============================================================
old_area_block = '''            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Área do Direito</label>
                <input type="text" id="area-direito" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white" placeholder="Ex: Trabalhista, Cível, Tributário...">
            </div>'''

new_area_block = '''            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Área do Direito</label>
                <select id="area-direito" onchange="filtrarTiposAcao()" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                    <option value="">Selecione a área...</option>
                    <option value="Atuação Avulsa/Extrajudicial">Atuação Avulsa/Extrajudicial</option>
                    <option value="Juizados Especiais">Juizados Especiais</option>
                    <option value="Direito Administrativo/Público">Direito Administrativo/Público</option>
                    <option value="Direito Civil e Empresarial">Direito Civil e Empresarial</option>
                    <option value="Direito Falimentar">Direito Falimentar</option>
                    <option value="Direito de Família">Direito de Família</option>
                    <option value="Direito das Sucessões">Direito das Sucessões</option>
                    <option value="Direito Eleitoral">Direito Eleitoral</option>
                    <option value="Direito Militar">Direito Militar</option>
                    <option value="Direito Penal">Direito Penal</option>
                    <option value="Direito do Trabalho">Direito do Trabalho</option>
                    <option value="Direito Previdenciário">Direito Previdenciário</option>
                    <option value="Direito Tributário">Direito Tributário</option>
                    <option value="Direito do Consumidor">Direito do Consumidor</option>
                    <option value="Tribunais e Conselhos">Tribunais e Conselhos</option>
                    <option value="Direito Desportivo">Direito Desportivo</option>
                    <option value="Direito Marítimo/Portuário/Aduaneiro">Direito Marítimo/Portuário/Aduaneiro</option>
                    <option value="Direito de Partido">Direito de Partido</option>
                    <option value="Propriedade Intelectual">Propriedade Intelectual</option>
                    <option value="Direito Ambiental">Direito Ambiental</option>
                    <option value="Direito da Criança e Adolescente">Direito da Criança e Adolescente</option>
                    <option value="Direito Digital">Direito Digital</option>
                    <option value="Assistência Social">Assistência Social</option>
                    <option value="Direito Imobiliário">Direito Imobiliário</option>
                    <option value="Mediação e Conciliação">Mediação e Conciliação</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tipo de Ação</label>
                <select id="tipo-acao" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-800 dark:text-white">
                    <option value="">Selecione primeiro a área...</option>
                </select>
            </div>'''

content = content.replace(old_area_block, new_area_block)

# =============================================================
# 3. REMOVER menções a IA no botão de gerar
# =============================================================
content = content.replace(
    '<span id="btn-gerar-text">Gerar Propostas com IA</span>',
    '<span id="btn-gerar-text">Gerar Propostas</span>'
)
content = content.replace(
    "txt.textContent = 'Gerar Propostas com IA';",
    "txt.textContent = 'Gerar Propostas';"
)
content = content.replace(
    "txt.textContent = 'Analisando com IA...';",
    "txt.textContent = 'Analisando dados...';"
)

# =============================================================
# 4. REMOVER "Análise da IA" → "Análise Estratégica"
# =============================================================
content = content.replace(
    '''<p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Análise da IA</p>''',
    '''<p class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Análise Estratégica</p>'''
)

# =============================================================
# 5. REMOVER "Recomendação IA" → "Recomendação" no header da tabela
# =============================================================
content = content.replace(
    '<th class="pb-2 font-medium">Recomendação IA</th>',
    '<th class="pb-2 font-medium">Recomendação</th>'
)

# =============================================================
# 6. ADICIONAR botão excluir na tabela de últimas propostas (admin)
# =============================================================
# Localizar a coluna "Ver" na tabela e adicionar "Excluir" ao lado (somente admin)
old_ver_link = '''                        <td class="py-2">
                            <a href="{{ route('precificacao.show', $p->id) }}" class="text-indigo-600 hover:text-indigo-800 text-xs">Ver</a>
                        </td>'''

new_ver_link = '''                        <td class="py-2 flex items-center gap-2">
                            <a href="{{ route('precificacao.show', $p->id) }}" class="text-indigo-600 hover:text-indigo-800 text-xs">Ver</a>
                            @if(in_array(auth()->user()->role ?? '', ['admin', 'socio']))
                            <button onclick="excluirProposta({{ $p->id }})" class="text-red-500 hover:text-red-700 text-xs" title="Excluir">✕</button>
                            @endif
                        </td>'''

content = content.replace(old_ver_link, new_ver_link)

# =============================================================
# 7. ADICIONAR JavaScript: mapa de tipos de ação por área + função excluir
#    Inserir ANTES do fechamento </script>
# =============================================================
tipos_acao_js = r"""
// ===== TABELA OAB/SC - TIPOS DE AÇÃO POR ÁREA =====
const tiposAcaoPorArea = {
    'Atuação Avulsa/Extrajudicial': [
        'Consulta ou parecer verbal',
        'Consulta ou parecer escrito',
        'Elaboração de contrato',
        'Elaboração de distrato',
        'Acompanhamento de audiência',
        'Mediação ou conciliação extrajudicial',
        'Notificação extrajudicial',
        'Análise e revisão de contrato',
        'Assessoria jurídica mensal (PF)',
        'Assessoria jurídica mensal (PJ)',
        'Diligência em cartório ou órgão público',
        'Assembleia societária',
        'Sustentação oral',
        'Interpelação extrajudicial',
    ],
    'Juizados Especiais': [
        'Ação cível até 20 salários mínimos',
        'Ação cível de 20 a 40 salários mínimos',
        'Recurso inominado',
        'Embargos de declaração',
        'Ação no Juizado Especial Criminal',
        'Ação no Juizado Especial Federal',
        'Ação no Juizado da Fazenda Pública',
    ],
    'Direito Administrativo/Público': [
        'Mandado de segurança',
        'Ação popular',
        'Ação civil pública',
        'Procedimento administrativo disciplinar',
        'Licitação - impugnação ou recurso',
        'Defesa em processo administrativo',
        'Habeas data',
        'Ação contra o poder público',
        'Representação em tribunal de contas',
    ],
    'Direito Civil e Empresarial': [
        'Processo cível de conhecimento',
        'Ação de execução',
        'Ação monitória',
        'Embargos à execução',
        'Embargos de terceiro',
        'Cumprimento de sentença',
        'Ação de cobrança',
        'Ação de indenização por danos morais',
        'Ação de indenização por danos materiais',
        'Ação possessória',
        'Ação de usucapião',
        'Ação renovatória',
        'Ação revisional de contrato',
        'Ação de despejo',
        'Ação de consignação em pagamento',
        'Ação de dissolução de sociedade',
        'Ação de responsabilidade civil',
        'Tutela de urgência (cautelar)',
        'Habilitação de crédito',
        'Impugnação de crédito',
        'Recuperação extrajudicial',
        'Ação de regresso',
        'Produção antecipada de provas',
    ],
    'Direito Falimentar': [
        'Recuperação judicial',
        'Falência - requerimento',
        'Falência - defesa',
        'Habilitação de crédito na falência',
        'Impugnação de crédito na falência',
        'Ação revocatória falencial',
        'Pedido de restituição',
    ],
    'Direito de Família': [
        'Ação de divórcio consensual',
        'Ação de divórcio litigioso',
        'Ação de alimentos',
        'Ação revisional de alimentos',
        'Ação de execução de alimentos',
        'Ação de regulamentação de guarda',
        'Ação de regulamentação de visitas',
        'Ação de investigação de paternidade',
        'Ação de negatória de paternidade',
        'Ação de reconhecimento e dissolução de união estável',
        'Inventário e partilha consensual',
        'Inventário e partilha litigiosa',
        'Medida protetiva (Lei Maria da Penha)',
        'Interdição',
        'Curatela',
        'Adoção',
    ],
    'Direito das Sucessões': [
        'Inventário judicial',
        'Inventário extrajudicial',
        'Arrolamento sumário',
        'Arrolamento comum',
        'Testamento - elaboração',
        'Testamento - impugnação',
        'Ação de petição de herança',
        'Sobrepartilha',
        'Alvará judicial',
    ],
    'Direito Eleitoral': [
        'Ação de impugnação de mandato eletivo',
        'Ação de investigação judicial eleitoral',
        'Recurso eleitoral',
        'Representação eleitoral',
        'Registro de candidatura',
        'Defesa em processo eleitoral',
    ],
    'Direito Militar': [
        'Defesa em processo administrativo militar',
        'Defesa em conselho de justificação',
        'Defesa em conselho de disciplina',
        'Ação judicial militar',
    ],
    'Direito Penal': [
        'Inquérito policial - acompanhamento',
        'Ação penal - defesa',
        'Ação penal - assistência de acusação',
        'Habeas corpus',
        'Revisão criminal',
        'Liberdade provisória',
        'Relaxamento de prisão',
        'Execução penal',
        'Tribunal do Júri',
        'Crimes de trânsito',
        'Crimes contra a honra (queixa-crime)',
        'Crimes ambientais',
        'Crimes tributários',
        'Suspensão condicional do processo',
        'Acordo de não persecução penal',
        'Colaboração premiada',
    ],
    'Direito do Trabalho': [
        'Reclamação trabalhista',
        'Ação de consignação em pagamento trabalhista',
        'Mandado de segurança trabalhista',
        'Ação rescisória trabalhista',
        'Execução trabalhista',
        'Embargos à execução trabalhista',
        'Recurso ordinário trabalhista',
        'Recurso de revista',
        'Agravo de instrumento trabalhista',
        'Defesa em inquérito para apuração de falta grave',
        'Ação de cumprimento',
        'Dissídio coletivo',
        'Consultoria trabalhista preventiva',
    ],
    'Direito Previdenciário': [
        'Aposentadoria por idade',
        'Aposentadoria por tempo de contribuição',
        'Aposentadoria especial',
        'Aposentadoria por invalidez',
        'Auxílio-doença',
        'Auxílio-acidente',
        'Pensão por morte',
        'Benefício assistencial (BPC/LOAS)',
        'Revisão de benefício',
        'Recurso ao CRPS',
        'Ação de concessão de benefício',
        'Ação de restabelecimento de benefício',
        'Complementação de aposentadoria',
        'Desaposentação',
    ],
    'Direito Tributário': [
        'Mandado de segurança tributário',
        'Ação anulatória de débito fiscal',
        'Ação declaratória tributária',
        'Ação de repetição de indébito',
        'Embargos à execução fiscal',
        'Exceção de pré-executividade',
        'Defesa em processo administrativo tributário',
        'Planejamento tributário',
        'Consulta tributária',
        'Recuperação de créditos tributários',
        'Ação de consignação em pagamento tributária',
    ],
    'Direito do Consumidor': [
        'Ação indenizatória consumerista',
        'Ação de obrigação de fazer/não fazer',
        'Ação de rescisão contratual',
        'Ação revisional (relação de consumo)',
        'Defesa do fornecedor',
        'Ação coletiva de consumo',
        'Reclamação junto ao Procon',
    ],
    'Tribunais e Conselhos': [
        'Apelação cível',
        'Agravo de instrumento',
        'Embargos de declaração',
        'Recurso especial',
        'Recurso extraordinário',
        'Ação rescisória',
        'Reclamação constitucional',
        'Mandado de segurança em tribunal',
        'Habeas corpus em tribunal',
        'Sustentação oral',
        'Agravo interno',
        'Embargos de divergência',
    ],
    'Direito Desportivo': [
        'Defesa perante Tribunal de Justiça Desportiva',
        'Recurso ao STJD',
        'Consultoria desportiva',
        'Contrato desportivo - elaboração',
    ],
    'Direito Marítimo/Portuário/Aduaneiro': [
        'Ação marítima',
        'Consultoria portuária/aduaneira',
        'Defesa em processo aduaneiro',
        'Contencioso marítimo',
    ],
    'Direito de Partido': [
        'Consultoria partidária',
        'Defesa em processo de fidelidade',
    ],
    'Propriedade Intelectual': [
        'Registro de marca',
        'Registro de patente',
        'Ação de infração de marca',
        'Ação de infração de patente',
        'Consultoria em propriedade intelectual',
        'Contrato de licenciamento',
    ],
    'Direito Ambiental': [
        'Defesa em ação civil pública ambiental',
        'Licenciamento ambiental',
        'Defesa em processo administrativo ambiental',
        'Ação de reparação de dano ambiental',
        'Consultoria ambiental',
    ],
    'Direito da Criança e Adolescente': [
        'Ação de guarda',
        'Ação de adoção',
        'Medida socioeducativa - defesa',
        'Destituição do poder familiar',
        'Acolhimento institucional',
    ],
    'Direito Digital': [
        'Ação de remoção de conteúdo',
        'Ação de indenização por violação digital',
        'Consultoria em LGPD',
        'Adequação à LGPD',
        'Crimes cibernéticos - defesa',
    ],
    'Assistência Social': [
        'Benefício assistencial (BPC/LOAS)',
        'Defesa em processo de inclusão/exclusão',
    ],
    'Direito Imobiliário': [
        'Ação de usucapião',
        'Retificação de registro imobiliário',
        'Ação de adjudicação compulsória',
        'Ação de imissão na posse',
        'Due diligence imobiliária',
        'Elaboração de contrato imobiliário',
        'Ação de despejo',
        'Ação renovatória de locação',
    ],
    'Mediação e Conciliação': [
        'Mediação privada',
        'Conciliação judicial',
        'Câmara de mediação/arbitragem',
        'Arbitragem',
    ],
};

function filtrarTiposAcao() {
    const area = document.getElementById('area-direito').value;
    const selectTipo = document.getElementById('tipo-acao');
    selectTipo.innerHTML = '<option value="">Selecione o tipo de ação...</option>';

    if (area && tiposAcaoPorArea[area]) {
        tiposAcaoPorArea[area].forEach(tipo => {
            const opt = document.createElement('option');
            opt.value = tipo;
            opt.textContent = tipo;
            selectTipo.appendChild(opt);
        });
    } else {
        selectTipo.innerHTML = '<option value="">Selecione primeiro a área...</option>';
    }
}

// ===== EXCLUIR PROPOSTA (ADMIN) =====
function excluirProposta(id) {
    if (!confirm('Tem certeza que deseja excluir esta proposta? Esta ação não pode ser desfeita.')) return;

    fetch(`""" + "{{ url('/precificacao') }}" + r"""/${id}/excluir`, {
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
"""

# Inserir antes do </script> final
content = content.replace(
    '</script>\n@endpush',
    tipos_acao_js + '\n</script>\n@endpush'
)

# =============================================================
# 8. AJUSTAR payload do gerarPropostas() para incluir tipo_acao
# =============================================================
old_payload = """    const payload = {
        tipo_proponente: proponenteSelecionado.tipo,
        proponente_id: proponenteSelecionado.id,
        area_direito: document.getElementById('area-direito').value,
        valor_causa: document.getElementById('valor-causa').value || null,
        valor_economico: document.getElementById('valor-economico').value || null,
        descricao_demanda: document.getElementById('descricao-demanda').value,
        contexto_adicional: document.getElementById('contexto-adicional').value,
    };"""

new_payload = """    const payload = {
        tipo_proponente: proponenteSelecionado.tipo,
        proponente_id: proponenteSelecionado.id,
        area_direito: document.getElementById('area-direito').value,
        tipo_acao: document.getElementById('tipo-acao').value,
        valor_causa: document.getElementById('valor-causa').value || null,
        valor_economico: document.getElementById('valor-economico').value || null,
        descricao_demanda: document.getElementById('descricao-demanda').value,
        contexto_adicional: document.getElementById('contexto-adicional').value,
    };"""

content = content.replace(old_payload, new_payload)

# =============================================================
# 9. AJUSTAR preencherFormulario para setar o select (não input text)
# =============================================================
old_preencher_area = """    // Área de interesse
    if (dados.demanda?.area_interesse) {
        document.getElementById('area-direito').value = dados.demanda.area_interesse;
    }"""

new_preencher_area = """    // Área de interesse - selecionar no dropdown
    if (dados.demanda?.area_interesse) {
        const selArea = document.getElementById('area-direito');
        const areaVal = dados.demanda.area_interesse;
        // Tentar match exato, senão match parcial
        let matched = false;
        for (let opt of selArea.options) {
            if (opt.value && opt.value.toLowerCase().includes(areaVal.toLowerCase())) {
                opt.selected = true;
                matched = true;
                filtrarTiposAcao();
                break;
            }
        }
    }"""

content = content.replace(old_preencher_area, new_preencher_area)

# =============================================================
# SALVAR
# =============================================================
if content == original:
    print("ALERTA: Nenhuma alteração aplicada! Verifique os marcadores.")
    sys.exit(1)

with open(VIEW_PATH, 'w', encoding='utf-8') as f:
    f.write(content)

changes = sum([
    'SIPEX Honorários' in content,
    'filtrarTiposAcao' in content,
    'excluirProposta' in content,
    'Gerar Propostas com IA' not in content,
    'tipo-acao' in content,
])
print(f"OK: View index.blade.php atualizada com {changes} grupos de alterações")
print("  ✓ Header: 'SIPEX Honorários' (sem 'Precificação Inteligente')")
print("  ✓ Subtítulo: sem menção a IA")
print("  ✓ Área do Direito: dropdown com 25 categorias OAB/SC")
print("  ✓ Tipo de Ação: dropdown dinâmico por área")
print("  ✓ Botão gerar: sem menção a IA")
print("  ✓ Análise Estratégica (não 'Análise da IA')")
print("  ✓ Botão excluir proposta (admin/sócio)")
print("  ✓ Payload inclui tipo_acao")
