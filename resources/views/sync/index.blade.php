@extends('layouts.app')

@section('title', 'Sincronização DataJuri')

@section('content')
<div class="space-y-6">

  <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
    <div>
      <h2 class="text-2xl font-bold text-white mb-1">Sincronização com DataJuri</h2>
      <div class="text-gray-400">Conferência de importação (API x Banco) e sincronização por ano.</div>
    </div>

    <div class="flex flex-wrap gap-3 items-center">
      <form method="get" action="{{ route('sync.index') }}">
        <select name="ano" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
          @foreach($anosDisponiveis as $a)
            <option value="{{ $a }}" @if($a==$ano) selected @endif>{{ $a }}</option>
          @endforeach
        </select>
      </form>

      <button id="btnSyncAll" class="bg-blue-600 hover:bg-blue-700 text-white font-medium px-6 py-2 rounded-lg transition-colors" onclick="iniciarSincronizacao()">
        Sincronizar (ano {{ $ano }})
      </button>

      <a href="{{ route('classificacao') }}" class="bg-yellow-600 hover:bg-yellow-700 text-white font-medium px-6 py-2 rounded-lg transition-colors">
        Classificação Manual
      </a>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
    <div class="bg-gray-800 rounded-xl p-5 border border-gray-700">
      <div class="flex justify-between">
        <div>
          <div class="text-gray-400 text-sm">Status da Conexão</div>
          <div class="text-xl font-semibold text-white" id="statusText">Verificando...</div>
          <div class="text-gray-500 text-xs mt-2">Última atualização (movimentos {{ $ano }}):</div>
          <div class="text-gray-300 text-sm">
            {{ $ultimaSync ?? '-' }}
          </div>
        </div>
        <div class="text-right">
          <span id="statusIndicator" class="px-3 py-1 rounded-full text-xs font-medium bg-gray-600 text-gray-300">...</span>
        </div>
      </div>
    </div>

    <div class="lg:col-span-2 bg-gray-800 rounded-xl p-5 border border-gray-700">
      <div class="flex justify-between items-center mb-3">
        <div class="font-semibold text-white">Progresso</div>
        <div id="progressText" class="text-gray-400 text-sm">Aguardando...</div>
      </div>
      <div class="w-full bg-gray-700 rounded-full h-5 overflow-hidden">
        <div id="progressBar" class="bg-blue-600 h-5 rounded-full transition-all duration-300 flex items-center justify-center text-xs text-white font-medium" style="width: 0%">0%</div>
      </div>
    </div>
  </div>

  <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
      <div class="text-gray-400 text-sm">Advogados</div>
      <div class="text-2xl font-bold text-white" id="countAdvogados">{{ $counts['advogados'] ?? 0 }}</div>
    </div>
    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
      <div class="text-gray-400 text-sm">Processos</div>
      <div class="text-2xl font-bold text-white" id="countProcessos">{{ $counts['processos'] ?? 0 }}</div>
    </div>
    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
      <div class="text-gray-400 text-sm">Atividades</div>
      <div class="text-2xl font-bold text-white" id="countAtividades">{{ $counts['atividades'] ?? 0 }}</div>
    </div>
    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
      <div class="text-gray-400 text-sm">Horas</div>
      <div class="text-2xl font-bold text-white" id="countHoras">{{ $counts['horas'] ?? 0 }}</div>
    </div>
    <div class="bg-gray-800 rounded-xl p-4 border-2 border-green-600">
      <div class="text-green-400 text-sm">Movimentos ({{ $ano }})</div>
      <div class="text-2xl font-bold text-white" id="countMovimentos">{{ $counts['movimentos'] ?? 0 }}</div>
      <div class="text-gray-400 text-xs mt-1">PF: R$ {{ number_format($resumoMovimentos['receita_pf'] ?? 0,2,',','.') }}
        | PJ: R$ {{ number_format($resumoMovimentos['receita_pj'] ?? 0,2,',','.') }}
        | Fin: R$ {{ number_format($resumoMovimentos['receita_financeira'] ?? 0,2,',','.') }}
        | Pendentes: {{ $resumoMovimentos['pendentes'] ?? 0 }}
      </div>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <!-- Prévia da API -->
    <div class="bg-gray-800 rounded-xl border border-gray-700">
      <div class="p-4 border-b border-gray-700 flex justify-between items-center">
        <div class="font-semibold text-white">Prévia da API (Movimentos)</div>
        <div class="flex gap-2 items-center">
          <button class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-1 rounded transition-colors" onclick="carregarApiPreview(1)">Atualizar</button>
          <div class="text-gray-500 text-xs" id="apiInfo">-</div>
        </div>
      </div>
      <div class="overflow-x-auto" style="max-height: 340px;">
        <table class="w-full text-sm">
          <thead class="bg-gray-900 sticky top-0">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">API ID</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">Data</th>
              <th class="px-3 py-2 text-right text-xs font-medium text-gray-400 uppercase">Valor</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">Cód</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">Pessoa</th>
              <th class="px-3 py-2 text-center text-xs font-medium text-gray-400 uppercase">Gravado?</th>
            </tr>
          </thead>
          <tbody id="tbodyApi" class="divide-y divide-gray-700"></tbody>
        </table>
      </div>
      <div class="p-3 border-t border-gray-700 flex justify-between items-center">
        <button class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-1 rounded transition-colors" id="apiPrev" onclick="apiPrevPage()">«</button>
        <div class="text-gray-400 text-sm">Página <span id="apiPage">1</span></div>
        <button class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-1 rounded transition-colors" id="apiNext" onclick="apiNextPage()">»</button>
      </div>
    </div>

    <!-- Banco -->
    <div class="bg-gray-800 rounded-xl border border-gray-700">
      <div class="p-4 border-b border-gray-700 flex justify-between items-center">
        <div class="font-semibold text-white">Banco (Movimentos)</div>
        <div class="flex gap-2 items-center">
          <select id="dbFiltroClass" class="bg-gray-700 border border-gray-600 text-white text-sm rounded px-2 py-1 focus:ring-blue-500 focus:border-blue-500" onchange="carregarDb()">
            <option value="">Todas as classificações</option>
            <option value="RECEITA_PF">RECEITA_PF</option>
            <option value="RECEITA_PJ">RECEITA_PJ</option>
            <option value="RECEITA_FINANCEIRA">RECEITA_FINANCEIRA</option>
            <option value="PENDENTE_CLASSIFICACAO">PENDENTE_CLASSIFICACAO</option>
            <option value="DESPESA">DESPESA</option>
          </select>
          <button class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-1 rounded transition-colors" onclick="carregarDb()">Atualizar</button>
        </div>
      </div>
      <div class="overflow-x-auto" style="max-height: 340px;">
        <table class="w-full text-sm">
          <thead class="bg-gray-900 sticky top-0">
            <tr>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">ID</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">API ID</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">Data</th>
              <th class="px-3 py-2 text-right text-xs font-medium text-gray-400 uppercase">Valor</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">Cód</th>
              <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase">Classificação</th>
            </tr>
          </thead>
          <tbody id="tbodyDb" class="divide-y divide-gray-700"></tbody>
        </table>
      </div>
      <div class="p-3 border-t border-gray-700 flex justify-between items-center">
        <button class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-1 rounded transition-colors" id="dbPrev" onclick="dbPrevPage()">«</button>
        <div class="text-gray-400 text-sm">Registros <span id="dbOffset">0</span>–<span id="dbOffsetEnd">0</span> de <span id="dbTotal">0</span></div>
        <button class="bg-gray-700 hover:bg-gray-600 text-white text-sm px-3 py-1 rounded transition-colors" id="dbNext" onclick="dbNextPage()">»</button>
      </div>
    </div>
  </div>

  <!-- Log -->
  <div class="bg-gray-800 rounded-xl border border-gray-700">
    <div class="p-4 border-b border-gray-700">
      <div class="font-semibold text-white">Log</div>
    </div>
    <div class="p-4">
      <pre id="syncLog" class="bg-gray-900 text-gray-300 p-4 rounded-lg text-sm font-mono overflow-auto" style="max-height: 260px;">[Aguardando sincronização...]</pre>
    </div>
  </div>

</div>

@php
$anosDisponiveis = $anosDisponiveis ?? [2026, 2025];
@endphp

<script>
let sincronizando = false;

let apiPage = 1;
let apiPageSize = 50;

let dbOffset = 0;
let dbLimit = 50;

document.addEventListener('DOMContentLoaded', function() {
  verificarStatus();
  carregarApiPreview(1);
  carregarDb();
});

function addLog(msg) {
  const el = document.getElementById('syncLog');
  el.textContent += "\n" + msg;
  el.scrollTop = el.scrollHeight;
}

function verificarStatus() {
  fetch('/api/sync/status')
    .then(r => r.json())
    .then(data => {
      const indicator = document.getElementById('statusIndicator');
      const text = document.getElementById('statusText');
      if (data.connected) {
        indicator.className = 'px-3 py-1 rounded-full text-xs font-medium bg-green-600 text-white';
        indicator.textContent = 'Conectado';
        text.textContent = 'Conectado';
      } else {
        indicator.className = 'px-3 py-1 rounded-full text-xs font-medium bg-red-600 text-white';
        indicator.textContent = 'Falha';
        text.textContent = data.message || 'Desconectado';
      }
    })
    .catch(() => {
      const indicator = document.getElementById('statusIndicator');
      indicator.className = 'px-3 py-1 rounded-full text-xs font-medium bg-red-600 text-white';
      indicator.textContent = 'Erro';
      document.getElementById('statusText').textContent = 'Erro ao verificar';
    });
}

function updateProgress(percent, text) {
  const bar = document.getElementById('progressBar');
  const progressText = document.getElementById('progressText');
  bar.style.width = percent + '%';
  bar.textContent = percent + '%';
  if (text) progressText.textContent = text;

  if (percent >= 100) {
    bar.classList.remove('bg-blue-600');
    bar.classList.add('bg-green-600');
  }
}

// PATCH 3 - SINCRONIZAÇÃO POR LOTES (ChatGPT)
async function iniciarSincronizacao() {
  if (sincronizando) return;
  sincronizando = true;

  document.getElementById('syncLog').textContent = '[Sincronização iniciada...]';
  updateProgress(5, 'Iniciando...');

  const ano = {{ (int)$ano }};
  let page = 1;
  const pageSize = 200;

  let totalPages = null;
  let processados = 0;

  try {
    while (true) {
      addLog(`→ Processando página ${page} (pageSize=${pageSize})`);

      const resp = await fetch('/api/sync/movimentos/batch', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ ano, page, pageSize })
      });

      const data = await resp.json();
      if (!data.success) throw new Error(data.message || 'Falha no batch');

      const s = data.stats;
      processados += s.api_rows;

      if (!totalPages && s.listSize) {
        const effectivePageSize = (s.api_pageSize_reported && Number(s.api_pageSize_reported) > 0)
          ? Number(s.api_pageSize_reported)
          : pageSize;

        totalPages = Math.ceil(Number(s.listSize) / effectivePageSize);
        addLog(`Total de páginas calculado: ${totalPages} (listSize=${s.listSize} / pageSize=${effectivePageSize})`);
      }

      const pct = totalPages
        ? Math.min(100, Math.round((page / totalPages) * 100))
        : 90;

      updateProgress(pct, `Página ${page} de ${totalPages ?? '?'}`);

      if (totalPages && page >= totalPages) break;
      if (s.api_rows === 0) break;

      page++;
    }

    updateProgress(100, 'Concluído');
    addLog('✓ Sincronização finalizada com sucesso.');

  } catch (err) {
    addLog('ERRO: ' + err.message);
    updateProgress(100, 'Erro');
    document.getElementById('progressBar').classList.add('bg-danger');
  } finally {
    sincronizando = false;
    carregarApiPreview(1);
    dbOffset = 0;
    carregarDb();
  }
}

function money(v) {
  return 'R$ ' + (v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function carregarApiPreview(page) {
  apiPage = page;
  const ano = {{ (int)$ano }};

  fetch(`/api/sync/movimentos/api-preview?ano=${ano}&page=${apiPage}&pageSize=${apiPageSize}`)
    .then(r => r.json())
    .then(data => {
      if (!data.success) throw new Error('Falha na prévia da API');
      document.getElementById('apiPage').textContent = apiPage;
      document.getElementById('apiInfo').textContent = `listSize: ${data.listSize} | pageSize: ${data.pageSize}`;

      const tbody = document.getElementById('tbodyApi');
      tbody.innerHTML = '';
      (data.rows || []).forEach(row => {
        const badge = row.ja_gravado ? '<span class="px-2 py-1 rounded text-xs bg-green-600 text-white">SIM</span>' : '<span class="px-2 py-1 rounded text-xs bg-gray-600 text-gray-300">NÃO</span>';
        tbody.innerHTML += `
          <tr class="hover:bg-gray-700/50">
            <td class="px-3 py-2 text-gray-300">${row.datajuri_id ?? ''}</td>
            <td class="px-3 py-2 text-gray-300">${row.data ?? ''}</td>
            <td class="px-3 py-2 text-right text-gray-300">${money(row.valor)}</td>
            <td class="px-3 py-2 text-gray-400">${row.codigo_plano ?? ''}</td>
            <td class="px-3 py-2 text-gray-300" title="${row.plano_contas ?? ''}">${(row.pessoa ?? '').slice(0, 28)}</td>
            <td class="px-3 py-2 text-center">${badge}</td>
          </tr>
        `;
      });
    })
    .catch(err => addLog('API Preview erro: ' + err.message));
}

function apiPrevPage() { if (apiPage > 1) carregarApiPreview(apiPage - 1); }
function apiNextPage() { carregarApiPreview(apiPage + 1); }

function carregarDb() {
  const ano = {{ (int)$ano }};
  const classificacao = document.getElementById('dbFiltroClass').value;

  let url = `/api/sync/movimentos/db?ano=${ano}&limit=${dbLimit}&offset=${dbOffset}`;
  if (classificacao) url += `&classificacao=${encodeURIComponent(classificacao)}`;

  fetch(url)
    .then(r => r.json())
    .then(data => {
      if (!data.success) throw new Error('Falha no DB');
      document.getElementById('dbTotal').textContent = data.total || 0;
      document.getElementById('dbOffset').textContent = data.offset || 0;
      document.getElementById('dbOffsetEnd').textContent = Math.min((data.offset||0) + (data.limit||0), data.total||0);

      const tbody = document.getElementById('tbodyDb');
      tbody.innerHTML = '';
      (data.rows || []).forEach(row => {
        const manual = row.manual ? ' <span class="px-1 py-0.5 rounded text-xs bg-yellow-600 text-white">manual</span>' : '';
        tbody.innerHTML += `
          <tr class="hover:bg-gray-700/50">
            <td class="px-3 py-2 text-gray-300">${row.id}</td>
            <td class="px-3 py-2 text-gray-400">${row.datajuri_id ?? ''}</td>
            <td class="px-3 py-2 text-gray-300">${row.data ?? ''}</td>
            <td class="px-3 py-2 text-right text-gray-300">${money(row.valor)}</td>
            <td class="px-3 py-2 text-gray-400">${row.codigo_plano ?? ''}</td>
            <td class="px-3 py-2 text-gray-300">${row.classificacao ?? ''}${manual}</td>
          </tr>
        `;
      });
    })
    .catch(err => addLog('DB erro: ' + err.message));
}

function dbPrevPage() { dbOffset = Math.max(0, dbOffset - dbLimit); carregarDb(); }
function dbNextPage() { dbOffset = dbOffset + dbLimit; carregarDb(); }
</script>
@endsection
