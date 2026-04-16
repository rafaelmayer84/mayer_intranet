@extends('layouts.app')

@section('title', 'Relatórios CEO')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-8">

  <div class="flex items-center justify-between mb-8">
    <div>
      <h1 class="text-2xl font-bold text-gray-900">Relatórios Executivos CEO</h1>
      <p class="text-sm text-gray-500 mt-1">Gerados automaticamente a cada 15 dias · Análise via Claude Opus 4.7</p>
    </div>
    <div class="text-right">
      <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
        Próximo: dia 1 ou 15 do mês, às 07:00
      </span>
    </div>
  </div>

  @if($relatorios->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
      <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
      </svg>
      <p class="text-gray-500 font-medium">Nenhum relatório gerado ainda.</p>
      <p class="text-gray-400 text-sm mt-1">O primeiro relatório será gerado automaticamente no próximo ciclo (dia 1 ou 15 do mês).</p>
    </div>
  @else
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
      <table class="w-full text-sm">
        <thead>
          <tr class="bg-gray-50 border-b border-gray-200">
            <th class="px-5 py-3 text-left font-semibold text-gray-600">#</th>
            <th class="px-5 py-3 text-left font-semibold text-gray-600">Período</th>
            <th class="px-5 py-3 text-left font-semibold text-gray-600">Score</th>
            <th class="px-5 py-3 text-left font-semibold text-gray-600">Status</th>
            <th class="px-5 py-3 text-left font-semibold text-gray-600">Gerado em</th>
            <th class="px-5 py-3 text-left font-semibold text-gray-600">Tamanho</th>
            <th class="px-5 py-3 text-right font-semibold text-gray-600">Ação</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          @foreach($relatorios as $rel)
            @php
              $analise = $rel->analise();
              $score = $analise['resumo_executivo']['score_geral'] ?? null;
              $titulo = $analise['resumo_executivo']['titulo'] ?? null;
              $meta = $rel->metadata ?? [];
              $tamanho = isset($meta['tamanho_pdf_kb']) ? $meta['tamanho_pdf_kb'] . ' KB' : '—';
            @endphp
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-5 py-4 text-gray-400 text-xs font-mono">{{ $rel->id }}</td>
              <td class="px-5 py-4">
                <div class="font-medium text-gray-900">
                  {{ $rel->periodo_inicio->format('d/m/Y') }} – {{ $rel->periodo_fim->format('d/m/Y') }}
                </div>
                @if($titulo)
                  <div class="text-xs text-gray-500 mt-0.5 truncate max-w-xs" title="{{ $titulo }}">{{ $titulo }}</div>
                @endif
              </td>
              <td class="px-5 py-4">
                @if($score !== null)
                  @php
                    $cor = $score >= 7 ? 'bg-green-100 text-green-800' : ($score >= 5 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                  @endphp
                  <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold {{ $cor }}">
                    {{ $score }}/10
                  </span>
                @else
                  <span class="text-gray-300">—</span>
                @endif
              </td>
              <td class="px-5 py-4">
                @php
                  $statusMap = [
                    'queued'  => ['text' => 'Na fila',    'class' => 'bg-gray-100 text-gray-600'],
                    'running' => ['text' => 'Gerando...', 'class' => 'bg-blue-100 text-blue-700 animate-pulse'],
                    'success' => ['text' => 'Pronto',     'class' => 'bg-green-100 text-green-700'],
                    'failed'  => ['text' => 'Erro',       'class' => 'bg-red-100 text-red-700'],
                  ];
                  $s = $statusMap[$rel->status] ?? ['text' => $rel->status, 'class' => 'bg-gray-100 text-gray-500'];
                @endphp
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $s['class'] }}">
                  {{ $s['text'] }}
                </span>
                @if($rel->status === 'failed' && $rel->erro)
                  <div class="text-xs text-red-500 mt-1 truncate max-w-xs" title="{{ $rel->erro }}">
                    {{ Str::limit($rel->erro, 60) }}
                  </div>
                @endif
              </td>
              <td class="px-5 py-4 text-gray-500 text-xs">
                {{ $rel->created_at->format('d/m/Y H:i') }}
              </td>
              <td class="px-5 py-4 text-gray-400 text-xs">{{ $tamanho }}</td>
              <td class="px-5 py-4 text-right">
                @if($rel->isPdf())
                  <a href="{{ route('admin.relatorios-ceo.download', $rel) }}"
                     class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Baixar PDF
                  </a>
                @elseif(in_array($rel->status, ['queued', 'running']))
                  <span class="text-xs text-gray-400 italic">Aguardando...</span>
                @else
                  <span class="text-xs text-gray-300">—</span>
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

  {{-- Painel de informação --}}
  <div class="mt-8 bg-amber-50 border border-amber-200 rounded-xl p-5">
    <div class="flex gap-3">
      <svg class="w-5 h-5 text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
      </svg>
      <div>
        <p class="text-sm font-medium text-amber-800">Relatório cobre</p>
        <p class="text-sm text-amber-700 mt-1">
          Financeiro (DRE + inadimplência) · Atendimentos WhatsApp (NEXO) · Performance GDP por advogado · Carteira de processos (VIGÍLIA) · Tendências do mercado jurídico Itajaí/SC · Recomendações gerenciais priorizadas
        </p>
        <p class="text-xs text-amber-600 mt-2">
          Para habilitar dados de Google Analytics: configure <code class="bg-amber-100 px-1 rounded">GA4_PROPERTY_ID</code> e <code class="bg-amber-100 px-1 rounded">GA_SERVICE_ACCOUNT_PATH</code> no .env
        </p>
      </div>
    </div>
  </div>

</div>
@endsection
