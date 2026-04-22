@extends('layouts.app')
@section('title', 'CRM - Distribuição de Carteira')

@section('content')
<div>
    {{-- ══════════════ HERO EDITORIAL ══════════════ --}}
    <section class="crm-hero">
        <div>
            <div class="crm-hero-eyebrow">IA · Otimização de carga por advogada</div>
            <h1>Distribuição <em>de carteira</em>.</h1>
            <p class="crm-hero-sub">Configure responsáveis, capacidades e deixe a IA propor distribuição balanceada.</p>
        </div>
        <div class="crm-hero-right">
            <form action="{{ route('crm.distribution.generate') }}" method="POST" style="display:inline;">
                @csrf
                <button type="submit" class="crm-section-head-action" style="border:1px solid var(--navy-700);background:var(--navy-700);color:white;padding:10px 18px;border-radius:var(--r-sm);cursor:pointer;font-family:var(--sans);letter-spacing:.04em;"
                        onclick="this.disabled=true; this.innerText='Gerando…'; this.form.submit();">
                    Gerar via IA →
                </button>
            </form>
        </div>
    </section>

    @if(session('success'))<div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm">{{ session('error') }}</div>@endif

    {{-- Perfis dos Responsáveis --}}
    <div class="crm-section-head">
        <div>
            <div class="crm-section-head-label">Equipe</div>
            <h2>Responsáveis <em>pela carteira</em>.</h2>
        </div>
        <div class="crm-section-head-line"></div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @foreach($profiles as $p)
        <div class="bg-white rounded-lg shadow-sm border p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-semibold text-[#1B334A]">{{ $p->user->name }}</h3>
                <span class="text-xs px-2 py-0.5 rounded-full {{ $p->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $p->active ? 'Ativo' : 'Inativo' }}
                </span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-gray-500">Clientes atuais</span>
                    <span class="font-medium {{ $p->current_count >= $p->max_accounts ? 'text-red-600' : 'text-[#1B334A]' }}">{{ $p->current_count }} / {{ $p->max_accounts }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-2">
                    <div class="h-full rounded-full {{ $p->current_count >= $p->max_accounts ? 'bg-red-500' : 'bg-[#385776]' }}" style="width: {{ min(100, round($p->current_count / max($p->max_accounts, 1) * 100)) }}%"></div>
                </div>
                <div class="flex justify-between">
                    <span class="text-gray-500">Peso</span>
                    <span class="font-medium">{{ $p->priority_weight }}/10</span>
                </div>
                <div>
                    <span class="text-gray-500 text-xs block mb-1">Especialidades</span>
                    <div class="flex flex-wrap gap-1">
                        @foreach($p->specialties ?? [] as $spec)
                        <span class="text-xs px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded">{{ $spec }}</span>
                        @endforeach
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">{{ $p->description }}</p>
            </div>
            {{-- Form de edição rápida --}}
            <form action="{{ route('crm.distribution.update-profile', $p->id) }}" method="POST" class="mt-3 pt-3 border-t">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-gray-500">Max clientes</label>
                        <input type="number" name="max_accounts" value="{{ $p->max_accounts }}" class="w-full border rounded px-2 py-1 text-sm">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Peso (1-10)</label>
                        <input type="number" name="priority_weight" value="{{ $p->priority_weight }}" min="1" max="10" class="w-full border rounded px-2 py-1 text-sm">
                    </div>
                </div>
                <button type="submit" class="mt-2 w-full text-xs px-2 py-1 bg-gray-100 hover:bg-gray-200 rounded text-gray-600">Salvar</button>
            </form>
        </div>
        @endforeach
    </div>

    {{-- Histórico de Propostas --}}
    <h2 class="text-lg font-semibold text-[#1B334A] mb-4">📋 Histórico de Distribuições</h2>
    @if($proposals->isNotEmpty())
    <div class="bg-white rounded-lg shadow-sm border overflow-hidden">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-gray-500 text-xs border-b"><th class="px-4 py-3 text-left">Data</th><th class="px-3 py-3 text-center">Status</th><th class="px-3 py-3 text-center">Clientes</th><th class="px-3 py-3">Criado por</th><th class="px-3 py-3">Ação</th></tr></thead>
            <tbody>
                @foreach($proposals as $prop)
                <tr class="border-b hover:bg-gray-50">
                    <td class="px-4 py-3">{{ $prop->created_at->format('d/m/Y H:i') }}</td>
                    <td class="px-3 py-3 text-center">
                        <span class="text-xs px-2 py-0.5 rounded-full {{ $prop->status === 'applied' ? 'bg-green-100 text-green-700' : ($prop->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-500') }}">{{ ucfirst($prop->status) }}</span>
                    </td>
                    <td class="px-3 py-3 text-center">{{ count($prop->assignments ?? []) }}</td>
                    <td class="px-3 py-3 text-gray-600">{{ $prop->creator?->name ?? '—' }}</td>
                    <td class="px-3 py-3">
                        @if($prop->status === 'pending')
                        <a href="{{ route('crm.distribution.review', $prop->id) }}" class="text-xs text-[#385776] hover:underline font-medium">Revisar →</a>
                        @else
                        <a href="{{ route('crm.distribution.review', $prop->id) }}" class="text-xs text-gray-400 hover:underline">Ver</a>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="bg-white rounded-lg shadow-sm border p-6 text-gray-400 text-sm">Nenhuma distribuição gerada ainda. Clique em "Gerar Distribuição via IA".</div>
    @endif
</div>

@push('scripts')
<script>
(function() {
    const msg = document.querySelector('.bg-green-50');
    if (!msg || !msg.textContent.includes('background')) return;

    let dots = 0;
    const btn = document.querySelector('button[type="submit"]');
    if (btn) {
        btn.disabled = true;
        btn.textContent = 'Gerando...';
        btn.classList.add('opacity-50', 'cursor-not-allowed');
    }

    const banner = document.createElement('div');
    banner.className = 'mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-sm flex items-center gap-3';
    banner.innerHTML = '<svg class="animate-spin h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg><span id="dist-status">A IA esta distribuindo os clientes. Isso leva 2-3 minutos. Nao feche esta pagina...</span>';

    const container = msg.parentNode;
    container.insertBefore(banner, msg.nextSibling);
    msg.style.display = 'none';

    const statusEl = document.getElementById('dist-status');
    const poll = setInterval(function() {
        dots = (dots + 1) % 4;
        const d = '.'.repeat(dots);

        fetch(window.location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const rows = doc.querySelectorAll('tbody tr');
                if (rows.length > 0) {
                    const firstStatus = rows[0].querySelector('td:nth-child(2)');
                    if (firstStatus && firstStatus.textContent.trim().toLowerCase() === 'pending') {
                        clearInterval(poll);
                        banner.className = 'mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700 text-sm';
                        banner.innerHTML = '&#9989; Proposta gerada com sucesso! Recarregando...';
                        setTimeout(function() { window.location.reload(); }, 1500);
                        return;
                    }
                }
                if (statusEl) statusEl.textContent = 'A IA esta distribuindo os clientes' + d + ' (~2-3 min)';
            })
            .catch(function() {
                if (statusEl) statusEl.textContent = 'Processando' + d;
            });
    }, 10000);

    // Timeout de seguranca: 5 minutos
    setTimeout(function() {
        clearInterval(poll);
        banner.className = 'mb-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-yellow-700 text-sm';
        banner.innerHTML = 'O processo esta demorando mais que o esperado. <a href="" class="underline font-medium">Recarregar pagina</a>';
    }, 300000);
})();
</script>
@endpush

@endsection
