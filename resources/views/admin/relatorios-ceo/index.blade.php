@extends('layouts.app')

@section('title', 'Relatórios CEO')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-8">

  {{-- Cabeçalho --}}
  <div class="flex items-start justify-between mb-8 gap-4">
    <div>
      <h1 style="font-size:1.4rem;font-weight:700;color:var(--ds-navy,#1B334A);">Relatórios de Inteligência Executiva</h1>
      <p style="font-size:.82rem;color:var(--ds-text-3,#8896A6);margin-top:3px;">
        Geração automática quinzenal via Claude Opus 4.7 · Financeiro · WhatsApp · Leads · GDP · Processos
      </p>
    </div>
    <div>
      <button onclick="document.getElementById('modal-gerar').style.display='flex'"
              style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:var(--ds-navy,#1B334A);color:#fff;border-radius:var(--ds-radius-sm,10px);font-size:.82rem;font-weight:600;border:none;cursor:pointer;">
        <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Gerar agora
      </button>
    </div>
  </div>

  {{-- Modal de geração manual --}}
  <div id="modal-gerar" style="display:none;position:fixed;inset:0;z-index:50;align-items:center;justify-content:center;background:rgba(0,0,0,.4);" onclick="if(event.target===this)this.style.display='none'">
    <div style="background:#fff;border-radius:16px;padding:28px 32px;width:420px;max-width:95vw;box-shadow:0 20px 60px rgba(0,0,0,.2);">
      <h2 style="font-size:1rem;font-weight:700;color:var(--ds-navy,#1B334A);margin-bottom:4px;">Gerar relatório manualmente</h2>
      <p style="font-size:.78rem;color:var(--ds-text-3,#8896A6);margin-bottom:20px;">O job será enfileirado e processado pelo queue worker. Pode levar até 10 minutos.</p>
      <form method="POST" action="{{ route('admin.relatorios-ceo.gerar') }}">
        @csrf
        <div style="margin-bottom:14px;">
          <label style="font-size:.78rem;font-weight:600;color:var(--ds-text-2,#4A5568);display:block;margin-bottom:4px;">Início do período</label>
          <input type="date" name="periodo_inicio" value="{{ now()->startOfMonth()->toDateString() }}"
                 style="width:100%;padding:8px 12px;border:1px solid var(--ds-border,#D8DEE6);border-radius:8px;font-size:.85rem;color:var(--ds-navy,#1B334A);" required>
        </div>
        <div style="margin-bottom:20px;">
          <label style="font-size:.78rem;font-weight:600;color:var(--ds-text-2,#4A5568);display:block;margin-bottom:4px;">Fim do período</label>
          <input type="date" name="periodo_fim" value="{{ now()->toDateString() }}"
                 style="width:100%;padding:8px 12px;border:1px solid var(--ds-border,#D8DEE6);border-radius:8px;font-size:.85rem;color:var(--ds-navy,#1B334A);" required>
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" onclick="document.getElementById('modal-gerar').style.display='none'"
                  style="padding:8px 16px;border:1px solid var(--ds-border,#D8DEE6);border-radius:8px;font-size:.82rem;font-weight:600;color:var(--ds-text-2,#4A5568);background:#fff;cursor:pointer;">
            Cancelar
          </button>
          <button type="submit"
                  style="padding:8px 20px;background:var(--ds-navy,#1B334A);color:#fff;border:none;border-radius:8px;font-size:.82rem;font-weight:600;cursor:pointer;">
            Gerar relatório
          </button>
        </div>
      </form>
    </div>
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
    <p style="font-size:.8rem;color:var(--ds-text-3,#8896A6);">O primeiro relatório será gerado automaticamente no próximo ciclo quinzenal (dias 1 e 15 de cada mês).</p>
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

<style>
.hover-row:hover td { background: var(--ds-bg, #F2F5F8); }
</style>
@endsection
