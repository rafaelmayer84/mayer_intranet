@extends('layouts.app')

@section('title', 'Relatórios CEO')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8" x-data="relatorioCeo()">

  {{-- Cabeçalho --}}
  <div class="flex items-start justify-between mb-8 gap-4">
    <div>
      <h1 style="font-size:1.4rem;font-weight:700;color:var(--ds-navy,#1B334A);">Relatórios de Inteligência Executiva</h1>
      <p style="font-size:.82rem;color:var(--ds-text-3,#8896A6);margin-top:3px;">
        Análise quinzenal via Claude Opus 4.7 · Financeiro · WhatsApp · Leads · GDP · Processos
      </p>
    </div>
    <button @click="abrirModal()" class="ds-btn ds-btn-primary" style="display:flex;align-items:center;gap:6px;white-space:nowrap;flex-shrink:0;">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      Gerar Relatório
    </button>
  </div>

  {{-- Flash success --}}
  @if(session('success'))
  <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:var(--ds-radius,14px);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
    <svg class="w-5 h-5 flex-shrink-0" style="color:#16a34a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span style="font-size:.85rem;color:#15803d;">{{ session('success') }}</span>
  </div>
  @endif

  @if(session('error'))
  <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:var(--ds-radius,14px);padding:12px 16px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
    <svg class="w-5 h-5 flex-shrink-0" style="color:#dc2626;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <span style="font-size:.85rem;color:#dc2626;">{{ session('error') }}</span>
  </div>
  @endif

  {{-- Lista de relatórios --}}
  @if($relatorios->isEmpty())
  <div style="background:var(--ds-surface,#fff);border:1px solid var(--ds-border,#D8DEE6);border-radius:var(--ds-radius,14px);padding:60px 24px;text-align:center;">
    <svg style="width:48px;height:48px;color:#D8DEE6;margin:0 auto 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
    </svg>
    <p style="font-size:.9rem;font-weight:600;color:var(--ds-text,#1B334A);margin-bottom:6px;">Nenhum relatório gerado ainda</p>
    <p style="font-size:.8rem;color:var(--ds-text-3,#8896A6);margin-bottom:20px;">Clique em "Gerar Relatório" para criar o primeiro. A geração leva de 3 a 8 minutos.</p>
    <button @click="abrirModal()" class="ds-btn ds-btn-primary" style="display:inline-flex;align-items:center;gap:6px;">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
      </svg>
      Gerar agora
    </button>
  </div>
  @else
  <div style="background:var(--ds-surface,#fff);border:1px solid var(--ds-border,#D8DEE6);border-radius:var(--ds-radius,14px);overflow:hidden;">
    <table style="width:100%;border-collapse:collapse;font-size:.82rem;">
      <thead>
        <tr style="background:var(--ds-bg,#F2F5F8);border-bottom:1px solid var(--ds-border,#D8DEE6);">
          <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--ds-text-2,#4A5568);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Período</th>
          <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--ds-text-2,#4A5568);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Score</th>
          <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--ds-text-2,#4A5568);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Status</th>
          <th style="padding:10px 16px;text-align:left;font-weight:600;color:var(--ds-text-2,#4A5568);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Gerado em</th>
          <th style="padding:10px 16px;text-align:right;font-weight:600;color:var(--ds-text-2,#4A5568);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">PDF</th>
        </tr>
      </thead>
      <tbody>
        @foreach($relatorios as $rel)
          @php
            $analise = $rel->analise();
            $score   = $analise['score_geral'] ?? ($analise['resumo_executivo']['score_geral'] ?? null);
            $titulo  = $analise['titulo_periodo'] ?? ($analise['resumo_executivo']['titulo'] ?? null);
          @endphp
          <tr style="border-bottom:1px solid var(--ds-border-l,#E8ECF1);" class="hover-row">
            <td style="padding:12px 16px;">
              <div style="font-weight:600;color:var(--ds-navy,#1B334A);">
                {{ $rel->periodo_inicio->format('d/m/Y') }} – {{ $rel->periodo_fim->format('d/m/Y') }}
              </div>
              @if($titulo)
              <div style="font-size:.75rem;color:var(--ds-text-3,#8896A6);margin-top:2px;max-width:340px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $titulo }}">
                {{ $titulo }}
              </div>
              @endif
            </td>
            <td style="padding:12px 16px;">
              @if($score !== null)
                @php $cor = $score >= 7 ? '#16a34a' : ($score >= 5 ? '#ca8a04' : '#dc2626'); $bg = $score >= 7 ? '#f0fdf4' : ($score >= 5 ? '#fefce8' : '#fef2f2'); @endphp
                <span style="display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:700;background:{{$bg}};color:{{$cor}};">
                  {{ $score }}/10
                </span>
              @else
                <span style="color:#D8DEE6;">—</span>
              @endif
            </td>
            <td style="padding:12px 16px;">
              @php
                $statusCfg = [
                  'queued'  => ['label' => 'Na fila',   'bg' => '#f1f5f9', 'color' => '#64748b'],
                  'running' => ['label' => 'Gerando...','bg' => '#eff6ff', 'color' => '#2563eb'],
                  'success' => ['label' => 'Pronto',    'bg' => '#f0fdf4', 'color' => '#16a34a'],
                  'failed'  => ['label' => 'Erro',      'bg' => '#fef2f2', 'color' => '#dc2626'],
                ];
                $sc = $statusCfg[$rel->status] ?? ['label' => $rel->status, 'bg' => '#f1f5f9', 'color' => '#64748b'];
              @endphp
              <span style="display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:600;background:{{$sc['bg']}};color:{{$sc['color']}};"
                    @if($rel->status === 'running') class="animate-pulse" @endif>
                @if($rel->status === 'running')
                <svg style="width:10px;height:10px;" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2zm1 14.93V15a1 1 0 0 0-2 0v1.93A8 8 0 0 1 4.07 13H6a1 1 0 0 0 0-2H4.07A8 8 0 0 1 11 4.07V6a1 1 0 0 0 2 0V4.07A8 8 0 0 1 19.93 11H18a1 1 0 0 0 0 2h1.93A8 8 0 0 1 13 16.93z"/>
                </svg>
                @endif
                {{ $sc['label'] }}
              </span>
              @if($rel->status === 'failed' && $rel->erro)
              <div style="font-size:.7rem;color:#dc2626;margin-top:3px;max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="{{ $rel->erro }}">
                {{ Str::limit($rel->erro, 70) }}
              </div>
              @endif
            </td>
            <td style="padding:12px 16px;color:var(--ds-text-3,#8896A6);font-size:.78rem;">
              {{ $rel->created_at->format('d/m/Y H:i') }}
            </td>
            <td style="padding:12px 16px;text-align:right;">
              @if($rel->isPdf())
              <a href="{{ route('admin.relatorios-ceo.download', $rel) }}"
                 style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;background:var(--ds-navy,#1B334A);color:#fff;border-radius:var(--ds-radius-sm,10px);font-size:.78rem;font-weight:600;text-decoration:none;transition:opacity .15s;"
                 onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Baixar PDF
              </a>
              @elseif(in_array($rel->status, ['queued', 'running']))
              <span style="font-size:.75rem;color:var(--ds-text-3,#8896A6);font-style:italic;">Aguardando...</span>
              @else
              <span style="color:#D8DEE6;font-size:.75rem;">—</span>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  @if($relatorios->hasPages())
  <div class="mt-4">{{ $relatorios->links() }}</div>
  @endif
  @endif

  {{-- Info cobertura --}}
  <div style="margin-top:24px;background:var(--ds-surface,#fff);border:1px solid var(--ds-border,#D8DEE6);border-radius:var(--ds-radius,14px);padding:16px 20px;display:flex;gap:12px;align-items:flex-start;">
    <svg style="width:18px;height:18px;color:var(--ds-primary,#385776);flex-shrink:0;margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
    <div>
      <p style="font-size:.82rem;font-weight:600;color:var(--ds-navy,#1B334A);margin-bottom:4px;">O que o relatório analisa</p>
      <p style="font-size:.78rem;color:var(--ds-text-2,#4A5568);line-height:1.6;">
        Financeiro (DRE + inadimplência + histórico 6 meses) · Voz dos clientes (conteúdo real das mensagens WhatsApp) ·
        Inteligência de leads (gatilho emocional, perfil, potencial, campanhas) · Performance GDP por advogado ·
        Cruzamento GDP × QA NEXO · Carteira de processos e prazos fatais · Cruzamentos estratégicos entre fontes ·
        Recomendações priorizadas com prazo e justificativa
      </p>
    </div>
  </div>

</div>

{{-- ══ MODAL GERAR RELATÓRIO ══ --}}
<div x-show="modal" x-cloak
     style="position:fixed;inset:0;background:rgba(27,51,74,.5);backdrop-filter:blur(4px);z-index:9998;display:flex;align-items:center;justify-content:center;"
     @click.self="fecharModal()">
  <div style="background:#fff;border-radius:var(--ds-radius,14px);width:480px;max-width:95vw;box-shadow:0 8px 32px rgba(27,51,74,.15);"
       :style="modal ? 'transform:translateY(0);opacity:1;transition:all .2s' : 'transform:translateY(20px);opacity:0'">

    {{-- Header --}}
    <div style="padding:20px 24px 16px;border-bottom:1px solid var(--ds-border-l,#E8ECF1);display:flex;align-items:center;justify-content:space-between;">
      <div>
        <h3 style="font-size:1rem;font-weight:700;color:var(--ds-navy,#1B334A);">Gerar Relatório de Inteligência</h3>
        <p style="font-size:.75rem;color:var(--ds-text-3,#8896A6);margin-top:2px;">Selecione o período e aguarde de 3 a 8 minutos</p>
      </div>
      <button @click="fecharModal()" style="border:none;background:none;cursor:pointer;color:var(--ds-text-3,#8896A6);line-height:1;">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
        </svg>
      </button>
    </div>

    {{-- Form --}}
    <form action="{{ route('admin.relatorios-ceo.gerar') }}" method="POST" style="padding:20px 24px 24px;">
      @csrf

      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--ds-text,#1B334A);margin-bottom:6px;">Período início</label>
        <input type="date" name="periodo_inicio" x-model="inicio" required
               class="ds-input" style="width:100%;">
      </div>

      <div style="margin-bottom:20px;">
        <label style="display:block;font-size:.8rem;font-weight:600;color:var(--ds-text,#1B334A);margin-bottom:6px;">Período fim</label>
        <input type="date" name="periodo_fim" x-model="fim" required
               class="ds-input" style="width:100%;">
      </div>

      {{-- Atalhos de período --}}
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:20px;">
        <button type="button" @click="setPeriodo(15)" style="font-size:.73rem;padding:4px 10px;border-radius:6px;border:1px solid var(--ds-border,#D8DEE6);background:var(--ds-bg,#F2F5F8);cursor:pointer;color:var(--ds-text-2,#4A5568);">
          Últimos 15 dias
        </button>
        <button type="button" @click="setPeriodo(30)" style="font-size:.73rem;padding:4px 10px;border-radius:6px;border:1px solid var(--ds-border,#D8DEE6);background:var(--ds-bg,#F2F5F8);cursor:pointer;color:var(--ds-text-2,#4A5568);">
          Últimos 30 dias
        </button>
        <button type="button" @click="setMesAtual()" style="font-size:.73rem;padding:4px 10px;border-radius:6px;border:1px solid var(--ds-border,#D8DEE6);background:var(--ds-bg,#F2F5F8);cursor:pointer;color:var(--ds-text-2,#4A5568);">
          Mês atual
        </button>
        <button type="button" @click="setMesAnterior()" style="font-size:.73rem;padding:4px 10px;border-radius:6px;border:1px solid var(--ds-border,#D8DEE6);background:var(--ds-bg,#F2F5F8);cursor:pointer;color:var(--ds-text-2,#4A5568);">
          Mês anterior
        </button>
      </div>

      <div style="background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:.76rem;color:#92400e;display:flex;gap:8px;align-items:flex-start;">
        <svg style="width:14px;height:14px;flex-shrink:0;margin-top:1px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span>A geração é assíncrona e leva <strong>3 a 8 minutos</strong>. Você receberá uma notificação ao concluir.</span>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" @click="fecharModal()" class="ds-btn" style="padding:8px 18px;">Cancelar</button>
        <button type="submit" class="ds-btn ds-btn-primary" style="display:flex;align-items:center;gap:6px;padding:8px 20px;">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
          </svg>
          Gerar relatório
        </button>
      </div>
    </form>
  </div>
</div>

<style>
.hover-row:hover td { background: var(--ds-bg, #F2F5F8); }
</style>

<script>
function relatorioCeo() {
  return {
    modal: false,
    inicio: '',
    fim: '',

    abrirModal() {
      this.setPeriodo(15);
      this.modal = true;
    },

    fecharModal() {
      this.modal = false;
    },

    fmt(d) {
      return d.toISOString().slice(0, 10);
    },

    setPeriodo(dias) {
      const hoje = new Date();
      const ini  = new Date(hoje);
      ini.setDate(ini.getDate() - dias);
      this.inicio = this.fmt(ini);
      this.fim    = this.fmt(hoje);
    },

    setMesAtual() {
      const hoje = new Date();
      this.inicio = this.fmt(new Date(hoje.getFullYear(), hoje.getMonth(), 1));
      this.fim    = this.fmt(hoje);
    },

    setMesAnterior() {
      const hoje = new Date();
      const ini  = new Date(hoje.getFullYear(), hoje.getMonth() - 1, 1);
      const fim  = new Date(hoje.getFullYear(), hoje.getMonth(), 0);
      this.inicio = this.fmt(ini);
      this.fim    = this.fmt(fim);
    },
  }
}
</script>
@endsection
