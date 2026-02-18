#!/usr/bin/env python3
"""
SIPEX - Geração de Proposta Persuasiva para Cliente (PDF)
Patches cirúrgicos para 5 arquivos
Executar na raiz do projeto: python3 /tmp/patch_proposta_cliente.py
"""

import sys, os

PROJECT = os.path.expanduser("~/domains/mayeradvogados.adv.br/public_html/Intranet")
os.chdir(PROJECT)

errors = []

def patch(filepath, old, new, label):
    try:
        with open(filepath, 'r', encoding='utf-8') as f:
            content = f.read()
        if old not in content:
            errors.append(f"[SKIP] {label}: marcador não encontrado em {filepath}")
            return False
        if content.count(old) > 1:
            errors.append(f"[WARN] {label}: marcador duplicado em {filepath} - usando primeira ocorrência")
        content = content.replace(old, new, 1)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"[OK] {label}")
        return True
    except Exception as e:
        errors.append(f"[ERRO] {label}: {e}")
        return False

# =============================================================
# PATCH 1 - PricingAIService: novo método gerarTextoPropostaCliente()
# Inserir antes do fechamento da classe
# =============================================================
print("\n=== PATCH 1: PricingAIService - novo método ===")

AI_SERVICE = "app/Services/PricingAIService.php"

AI_OLD = """        $parsed['model_used'] = $response['model'] ?? 'unknown';

        return $parsed;
    }
}"""

AI_NEW = r"""        $parsed['model_used'] = $response['model'] ?? 'unknown';

        return $parsed;
    }

    /**
     * Gera texto persuasivo da proposta de honorários para envio ao cliente.
     * Retorna JSON estruturado com seções do documento.
     */
    public function gerarTextoPropostaCliente(PricingProposal $proposta, string $tipoEscolhido): array
    {
        $propostaData = $proposta->{'proposta_' . $tipoEscolhido} ?? [];
        $valor = $propostaData['valor_honorarios'] ?? 0;
        $parcelas = $propostaData['parcelas_sugeridas'] ?? 1;
        $tipoCobranca = $propostaData['tipo_cobranca'] ?? 'fixo';
        $justificativa = $propostaData['justificativa_estrategica'] ?? '';

        $systemPrompt = <<<'SYSTEM'
Você é um redator jurídico sênior do escritório Mayer Sociedade de Advogados (OAB/SC 2097), especializado em redigir propostas de honorários que convertem leads em clientes. Seu objetivo é produzir um documento persuasivo, profissional e coercitivo (no sentido positivo) que faça o destinatário desejar contratar imediatamente.

REGRAS DE ESTILO:
1. Tom: confiante, técnico mas acessível, empático com a dor do cliente, transmitindo autoridade e segurança.
2. NUNCA use bullet points, listas numeradas ou markdown. Redija em prosa fluida, em parágrafos completos.
3. Escrita em 3ª pessoa ("O Escritório", "A equipe jurídica"). Nunca "nós" ou "eu".
4. Use gatilhos de persuasão: escassez de tempo (prazos legais), autoridade (experiência do escritório), prova social (resultados anteriores na área), reciprocidade (diagnóstico gratuito já entregue), urgência (consequências da inação).
5. O diagnóstico deve demonstrar que o escritório JÁ entendeu profundamente o caso, gerando confiança.
6. A seção de diferenciais deve ser sutil e integrada, não uma lista de autoelogio.
7. O valor dos honorários deve ser apresentado com naturalidade e justificado pelo valor que entrega, não pelo custo.
8. Inclua uma seção sobre consequências de NÃO agir (sem ser alarmista, mas realista).

FORMATO DE RESPOSTA - JSON com estas chaves:
{
  "saudacao": "Parágrafo de abertura cordial e personalizado",
  "contexto_demanda": "Parágrafo(s) mostrando que o escritório entendeu a situação do cliente",
  "diagnostico": "Análise técnica preliminar demonstrando domínio da matéria",
  "escopo_servicos": "Descrição detalhada do que está incluído na prestação de serviços",
  "fases": [
    {"nome": "Fase 1 — Título", "descricao": "O que será feito nesta fase"},
    {"nome": "Fase 2 — Título", "descricao": "..."}
  ],
  "estrategia": "Abordagem jurídica que será adotada (transmitir competência)",
  "honorarios": {
    "descricao_valor": "Pró-labore: R$ X.XXX,XX",
    "forma_pagamento": "Condições de pagamento",
    "observacao": "Nota sobre o que está incluso no valor"
  },
  "honorarios_exito": "Parágrafo sobre honorários de êxito, se aplicável (ou null)",
  "despesas": "Parágrafo sobre custas e despesas processuais",
  "diferenciais": "Por que este escritório é a melhor escolha (sutil, integrado ao contexto)",
  "vigencia": "Condições de vigência, confidencialidade e próximos passos",
  "encerramento": "Parágrafo final cordial com call-to-action sutil"
}

Responda APENAS com o JSON, sem texto adicional, sem backticks, sem markdown.
SYSTEM;

        $dadosCaso = [
            'destinatario' => $proposta->nome_proponente,
            'tipo_pessoa' => $proposta->tipo_pessoa,
            'documento' => $proposta->documento_proponente,
            'area_direito' => $proposta->area_direito,
            'tipo_acao' => $proposta->tipo_acao,
            'descricao_demanda' => $proposta->descricao_demanda,
            'valor_causa' => $proposta->valor_causa,
            'valor_economico' => $proposta->valor_economico,
            'contexto_adicional' => $proposta->contexto_adicional,
            'siric_rating' => $proposta->siric_rating,
            'proposta_tipo' => $tipoEscolhido,
            'valor_honorarios' => $valor,
            'parcelas' => $parcelas,
            'tipo_cobranca' => $tipoCobranca,
            'justificativa_estrategica' => $justificativa,
            'valor_final_advogado' => $proposta->valor_final,
            'observacao_advogado' => $proposta->observacao_advogado,
        ];

        $userPrompt = "Gere a proposta de honorários para o seguinte caso:\n\n" .
            json_encode($dadosCaso, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $response = $this->callOpenAI($systemPrompt, $userPrompt);

        $content = $response['choices'][0]['message']['content'] ?? '{}';
        $content = trim($content);
        $content = preg_replace('/^```json\s*/', '', $content);
        $content = preg_replace('/\s*```$/', '', $content);

        $parsed = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            \Log::error('SIPEX PropostaCliente: JSON inválido da IA', ['raw' => $content]);
            return ['error' => 'Resposta inválida da IA. Tente novamente.'];
        }

        return $parsed;
    }
}"""

patch(AI_SERVICE, AI_OLD, AI_NEW, "PricingAIService::gerarTextoPropostaCliente()")

# Adicionar use do Model no topo do service (se não existir)
with open(AI_SERVICE, 'r', encoding='utf-8') as f:
    ai_content = f.read()

if 'use App\\Models\\PricingProposal;' not in ai_content:
    patch(AI_SERVICE,
        "use Illuminate\\Support\\Facades\\Log;",
        "use Illuminate\\Support\\Facades\\Log;\nuse App\\Models\\PricingProposal;",
        "PricingAIService: add use PricingProposal")

# =============================================================
# PATCH 2 - PrecificacaoController: novos métodos
# Inserir antes do método calibracao()
# =============================================================
print("\n=== PATCH 2: PrecificacaoController - novos métodos ===")

CTRL = "app/Http/Controllers/PrecificacaoController.php"

CTRL_OLD = """    public function calibracao()"""

CTRL_NEW = """    /**
     * Gera texto persuasivo da proposta via IA e salva no registro.
     */
    public function gerarPropostaCliente(Request $request, int $id)
    {
        $proposal = PricingProposal::where('user_id', Auth::id())->findOrFail($id);

        if (!$proposal->proposta_escolhida || $proposal->proposta_escolhida === 'nenhuma') {
            return response()->json(['error' => 'Escolha uma proposta antes de gerar o documento.'], 422);
        }

        try {
            $textoGerado = $this->ai->gerarTextoPropostaCliente($proposal, $proposal->proposta_escolhida);

            if (isset($textoGerado['error'])) {
                return response()->json(['error' => $textoGerado['error']], 500);
            }

            $proposal->update(['texto_proposta_cliente' => json_encode($textoGerado, JSON_UNESCAPED_UNICODE)]);

            return response()->json([
                'success' => true,
                'redirect' => route('precificacao.proposta.print', $id),
            ]);
        } catch (\\Exception $e) {
            \\Log::error('SIPEX gerarPropostaCliente erro', ['id' => $id, 'msg' => $e->getMessage()]);
            return response()->json(['error' => 'Erro ao gerar proposta: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Renderiza a proposta de honorários para impressão/PDF.
     */
    public function imprimirProposta(int $id)
    {
        $proposal = PricingProposal::where('user_id', Auth::id())->findOrFail($id);

        if (!$proposal->texto_proposta_cliente) {
            return redirect()->route('precificacao.show', $id)
                ->with('error', 'Gere a proposta para o cliente antes de imprimir.');
        }

        $meses = [1=>'janeiro',2=>'fevereiro',3=>'março',4=>'abril',5=>'maio',6=>'junho',
                   7=>'julho',8=>'agosto',9=>'setembro',10=>'outubro',11=>'novembro',12=>'dezembro'];
        $hoje = now();
        $dataFormatada = "Itajaí, {$hoje->day} de {$meses[(int)$hoje->month]} de {$hoje->year}.";

        return view('precificacao.proposta-print', [
            'proposta' => $proposal,
            'dataFormatada' => $dataFormatada,
        ]);
    }

    public function calibracao()"""

patch(CTRL, CTRL_OLD, CTRL_NEW, "PrecificacaoController: gerarPropostaCliente() + imprimirProposta()")

# =============================================================
# PATCH 3 - Model PricingProposal: adicionar texto_proposta_cliente
# =============================================================
print("\n=== PATCH 3: Model PricingProposal - fillable + cast ===")

MODEL = "app/Models/PricingProposal.php"

patch(MODEL,
    "'proposta_escolhida', 'valor_final', 'status', 'observacao_advogado',",
    "'proposta_escolhida', 'valor_final', 'status', 'observacao_advogado',\n        'texto_proposta_cliente',",
    "Model fillable: texto_proposta_cliente")

# Adicionar cast para array
patch(MODEL,
    "'proposta_premium' => 'array',",
    "'proposta_premium' => 'array',\n        'texto_proposta_cliente' => 'array',",
    "Model cast: texto_proposta_cliente => array")

# =============================================================
# PATCH 4 - Rotas: adicionar 2 novas rotas
# Inserir após a rota de escolher
# =============================================================
print("\n=== PATCH 4: Rotas ===")

ROUTES = "routes/_precificacao_routes.php"

ROUTES_OLD = """    // Excluir proposta (admin only)"""

ROUTES_NEW = """    // Gerar proposta persuasiva para cliente (IA)
    Route::post('/{id}/gerar-proposta-cliente', [PrecificacaoController::class, 'gerarPropostaCliente'])->name('precificacao.gerar-proposta-cliente')->whereNumber('id');

    // Imprimir proposta para cliente (HTML → PDF via browser)
    Route::get('/{id}/proposta-print', [PrecificacaoController::class, 'imprimirProposta'])->name('precificacao.proposta.print')->whereNumber('id');

    // Excluir proposta (admin only)"""

patch(ROUTES, ROUTES_OLD, ROUTES_NEW, "Rotas: gerar-proposta-cliente + proposta-print")

# =============================================================
# PATCH 5 - View show.blade.php: botão "Gerar Proposta para Cliente"
# Inserir após a seção de "Decisão do Advogado"
# =============================================================
print("\n=== PATCH 5: View show.blade.php - botão gerar proposta ===")

SHOW = "resources/views/precificacao/show.blade.php"

SHOW_OLD = """    @if($proposta->proposta_escolhida)
    <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl border border-green-200 dark:border-green-800 p-6">
        <h2 class="text-sm font-semibold text-green-700 dark:text-green-300 uppercase tracking-wider mb-2">Decisão do Advogado</h2>
        <p class="text-sm text-green-800 dark:text-green-200">
            Proposta escolhida: <strong>{{ ucfirst($proposta->proposta_escolhida) }}</strong>
            @if($proposta->valor_final)
                | Valor final: <strong>R$ {{ number_format($proposta->valor_final, 2, ',', '.') }}</strong>
            @endif
        </p>
        @if($proposta->observacao_advogado)
            <p class="text-sm text-green-700 dark:text-green-300 mt-2">{{ $proposta->observacao_advogado }}</p>
        @endif
    </div>
    @endif"""

SHOW_NEW = """    @if($proposta->proposta_escolhida)
    <div class="bg-green-50 dark:bg-green-900/20 rounded-2xl border border-green-200 dark:border-green-800 p-6">
        <h2 class="text-sm font-semibold text-green-700 dark:text-green-300 uppercase tracking-wider mb-2">Decisão do Advogado</h2>
        <p class="text-sm text-green-800 dark:text-green-200">
            Proposta escolhida: <strong>{{ ucfirst($proposta->proposta_escolhida) }}</strong>
            @if($proposta->valor_final)
                | Valor final: <strong>R$ {{ number_format($proposta->valor_final, 2, ',', '.') }}</strong>
            @endif
        </p>
        @if($proposta->observacao_advogado)
            <p class="text-sm text-green-700 dark:text-green-300 mt-2">{{ $proposta->observacao_advogado }}</p>
        @endif
    </div>

    {{-- Botão: Gerar Proposta para Cliente --}}
    <div class="mt-4 flex items-center gap-3">
        <button id="btn-gerar-proposta"
            onclick="gerarPropostaCliente({{ $proposta->id }})"
            class="px-5 py-2.5 bg-brand text-white rounded-xl text-sm font-medium hover:opacity-90 transition flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span id="btn-gerar-texto">Gerar Proposta para Cliente</span>
        </button>

        @if($proposta->texto_proposta_cliente)
            <a href="{{ route('precificacao.proposta.print', $proposta->id) }}" target="_blank"
                class="px-5 py-2.5 bg-gray-600 text-white rounded-xl text-sm font-medium hover:bg-gray-700 transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Ver Proposta Gerada
            </a>
        @endif
    </div>

    <div id="proposta-status" class="mt-2 text-sm hidden"></div>
    @endif"""

patch(SHOW, SHOW_OLD, SHOW_NEW, "View show: botão gerar proposta + link ver proposta")

# Adicionar script JS antes do @endsection
SHOW_SCRIPT_OLD = """@endsection"""

SHOW_SCRIPT_NEW = """@push('scripts')
<script>
function gerarPropostaCliente(id) {
    const btn = document.getElementById('btn-gerar-proposta');
    const btnTexto = document.getElementById('btn-gerar-texto');
    const status = document.getElementById('proposta-status');

    btn.disabled = true;
    btnTexto.textContent = 'Gerando proposta...';
    btn.classList.add('opacity-60');
    status.classList.remove('hidden', 'text-red-600', 'text-green-600');
    status.textContent = 'A IA está redigindo a proposta persuasiva. Aguarde ~15-30 segundos...';
    status.classList.add('text-gray-500');

    fetch(`/precificacao/${id}/gerar-proposta-cliente`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            status.textContent = 'Proposta gerada com sucesso! Abrindo...';
            status.classList.remove('text-gray-500');
            status.classList.add('text-green-600');
            window.open(data.redirect, '_blank');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    })
    .catch(err => {
        status.textContent = 'Erro: ' + err.message;
        status.classList.remove('text-gray-500');
        status.classList.add('text-red-600');
        btn.disabled = false;
        btnTexto.textContent = 'Gerar Proposta para Cliente';
        btn.classList.remove('opacity-60');
    });
}
</script>
@endpush
@endsection"""

patch(SHOW, SHOW_SCRIPT_OLD, SHOW_SCRIPT_NEW, "View show: script JS gerarPropostaCliente()")

# =============================================================
# RESULTADO
# =============================================================
print("\n" + "=" * 60)
if errors:
    print("ERROS/AVISOS:")
    for e in errors:
        print(f"  {e}")
else:
    print("TODOS OS PATCHES APLICADOS COM SUCESSO!")

print("\nPróximos passos manuais:")
print("1. Copiar migration para database/migrations/")
print("2. Copiar view para resources/views/precificacao/")
print("3. php artisan migrate")
print("4. php artisan config:clear && cache:clear && route:clear && view:clear")
print("5. Testar")
