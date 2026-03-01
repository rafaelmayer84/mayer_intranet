@extends('layouts.app')

@section('title', 'JUSTUS — Configuração')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">JUSTUS — Configuração</h1>
            <p class="text-sm text-gray-500 mt-1">Administração de prompts, regras e orçamento</p>
        </div>
        <a href="{{ route('justus.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Voltar ao JUSTUS</a>
    </div>

    {{-- Budget Summary --}}
    <div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Orçamento do Mês</h2>
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
            <div>
                <span class="text-gray-500">Gasto</span>
                <p class="font-semibold">R$ {{ number_format($budget['cost_brl'], 2, ',', '.') }}</p>
            </div>
            <div>
                <span class="text-gray-500">Limite Global</span>
                <p class="font-semibold">R$ {{ number_format($config['budget_monthly_max'], 2, ',', '.') }}</p>
            </div>
            <div>
                <span class="text-gray-500">Limite/Usuário</span>
                <p class="font-semibold">R$ {{ number_format($config['budget_user_max'], 2, ',', '.') }}</p>
            </div>
            <div>
                <span class="text-gray-500">Câmbio USD/BRL</span>
                <p class="font-semibold">{{ $config['usd_brl'] }}</p>
            </div>
            <div>
                <span class="text-gray-500">Modelo</span>
                <p class="font-semibold">{{ $config['model_default'] }}</p>
            </div>
        </div>
    </div>

    {{-- Style Guides --}}
    <div class="space-y-6">
        @foreach($guides as $guide)
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden" id="guide-{{ $guide->id }}">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium {{ $guide->mode === 'consultor' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' }}">
                        {{ $guide->mode === 'consultor' ? 'CONSULTOR' : 'ASSESSOR' }}
                    </span>
                    <span class="text-sm font-semibold text-gray-800">{{ $guide->name }}</span>
                    <span class="text-xs text-gray-400">v{{ $guide->version }}</span>
                </div>
                <div class="flex items-center gap-2">
                    @if($guide->is_active)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Ativo</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Inativo</span>
                    @endif
                </div>
            </div>

            <form onsubmit="return saveGuide(event, {{ $guide->id }})" class="p-4 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Nome do Perfil</label>
                    <input type="text" name="name" value="{{ $guide->name }}" class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Regras de Comportamento <span class="text-gray-400">(aplicadas a TODAS as respostas)</span></label>
                    <textarea name="behavior_rules" rows="6" class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">{{ $guide->behavior_rules }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Normativo AD003 <span class="text-gray-400">(disclaimer obrigatório)</span></label>
                    <textarea name="ad003_disclaimer" rows="5" class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">{{ $guide->ad003_disclaimer }}</textarea>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">System Prompt <span class="text-gray-400">(instrução principal do modo {{ $guide->mode }})</span></label>
                    <textarea name="system_prompt" rows="14" class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono focus:ring-blue-500 focus:border-blue-500">{{ $guide->system_prompt }}</textarea>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="is_active" value="1" {{ $guide->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        Ativo
                    </label>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm rounded hover:bg-gray-800 transition" id="btn-{{ $guide->id }}">
                        Salvar
                    </button>
                </div>

                <div id="status-{{ $guide->id }}" class="text-xs hidden"></div>
            </form>
        </div>
        @endforeach
    </div>

    {{-- Info --}}
    <div class="mt-6 bg-gray-50 border border-gray-200 rounded-lg p-4 text-xs text-gray-500 space-y-1">
        <p><strong>Como funciona:</strong> O prompt final enviado à API é montado em camadas: Regras de Comportamento + Normativo AD003 + System Prompt + Contexto do Processo + Documentos (RAG).</p>
        <p><strong>Consultor Jurídico:</strong> Para perguntas, análise de casos, pareceres e diagnósticos estratégicos.</p>
        <p><strong>Assessor Processual:</strong> Para análise de processos judicializados, redação de peças e auditoria de cálculos.</p>
        <p>Alterações nos prompts afetam apenas novas mensagens. Conversas existentes mantêm o histórico.</p>
    </div>

</div>

<script>
async function saveGuide(e, guideId) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('btn-' + guideId);
    const status = document.getElementById('status-' + guideId);
    btn.disabled = true;
    btn.textContent = 'Salvando...';

    const data = {
        name: form.querySelector('[name="name"]').value,
        system_prompt: form.querySelector('[name="system_prompt"]').value,
        behavior_rules: form.querySelector('[name="behavior_rules"]').value,
        ad003_disclaimer: form.querySelector('[name="ad003_disclaimer"]').value,
        is_active: form.querySelector('[name="is_active"]').checked ? 1 : 0,
    };

    try {
        const res = await fetch('/justus/admin/guides/' + guideId, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });
        const json = await res.json();
        status.className = 'text-xs mt-1 ' + (json.success ? 'text-green-600' : 'text-red-600');
        status.textContent = json.success ? 'Salvo com sucesso.' : (json.message || 'Erro ao salvar.');
        status.classList.remove('hidden');
        setTimeout(() => status.classList.add('hidden'), 3000);
    } catch (err) {
        status.className = 'text-xs mt-1 text-red-600';
        status.textContent = 'Erro de conexão.';
        status.classList.remove('hidden');
    }
    btn.disabled = false;
    btn.textContent = 'Salvar';
    return false;
}
</script>
@endsection
