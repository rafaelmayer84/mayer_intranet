@extends('layouts.app')
@section('title', 'JUSTUS — Auditoria & Calibração')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">JUSTUS — Auditoria & Calibração</h1>
            <p class="text-sm text-gray-500 mt-1">Audite conversas, calibre prompts e gerencie templates</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('justus.admin.config') }}" class="text-sm text-gray-600 hover:text-gray-800">Config</a>
            <a href="{{ route('justus.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; JUSTUS</a>
        </div>
    </div>

    {{-- Tabs --}}
    <div x-data="{ tab: 'conversas' }" class="space-y-4">
        <div class="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit">
            <button @click="tab='conversas'" :class="tab==='conversas' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-4 py-2 text-sm font-medium rounded-md transition">Conversas</button>
            <button @click="tab='templates'" :class="tab==='templates' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-4 py-2 text-sm font-medium rounded-md transition">Prompts Programados</button>
            <button @click="tab='metricas'" :class="tab==='metricas' ? 'bg-white shadow text-gray-900' : 'text-gray-500'" class="px-4 py-2 text-sm font-medium rounded-md transition">Métricas</button>
        </div>

        {{-- TAB: Conversas --}}
        <div x-show="tab==='conversas'" class="space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
                <div class="bg-white border rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-gray-900">{{ $feedbackStats['total'] }}</p>
                    <p class="text-xs text-gray-500">Feedbacks Total</p>
                </div>
                <div class="bg-white border rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-green-600">{{ $feedbackStats['positive'] }}</p>
                    <p class="text-xs text-gray-500">Positivos</p>
                </div>
                <div class="bg-white border rounded-lg p-3 text-center">
                    <p class="text-2xl font-bold text-red-600">{{ $feedbackStats['negative'] }}</p>
                    <p class="text-xs text-gray-500">Negativos</p>
                </div>
            </div>

            @foreach($conversations as $conv)
            <div class="bg-white border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-300 transition"
                 onclick="loadAudit({{ $conv->id }})">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm font-semibold text-gray-800">{{ $conv->title ?: 'Sem título' }}</span>
                        <span class="text-xs text-gray-400 ml-2">ID {{ $conv->id }}</span>
                        <span class="inline-flex ml-2 items-center px-2 py-0.5 rounded text-xs font-medium {{ $conv->mode === 'consultor' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $conv->mode }}</span>
                    </div>
                    <div class="flex items-center gap-4 text-xs text-gray-500">
                        <span>{{ $conv->user->name ?? '?' }}</span>
                        <span>{{ $conv->messages_count }} msgs</span>
                        <span>{{ $conv->updated_at->format('d/m H:i') }}</span>
                    </div>
                </div>
                @if($conv->processProfile)
                <div class="mt-2 flex gap-2 flex-wrap">
                    @if($conv->processProfile->classe)<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $conv->processProfile->classe }}</span>@endif
                    @if($conv->processProfile->tese_principal)<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ Str::limit($conv->processProfile->tese_principal, 60) }}</span>@endif
                </div>
                @endif
            </div>
            @endforeach
        </div>

        {{-- TAB: Templates --}}
        <div x-show="tab==='templates'" class="space-y-4">
            @foreach($templates as $tmpl)
            <div class="bg-white border border-gray-200 rounded-lg p-4" id="tmpl-{{ $tmpl->id }}">
                <div class="flex items-center justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-medium px-2 py-0.5 rounded {{ $tmpl->category === 'execucao' ? 'bg-red-100 text-red-700' : ($tmpl->category === 'analise_pecas' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') }}">{{ $tmpl->category }}</span>
                        <span class="text-xs text-gray-400">{{ $tmpl->mode }} / {{ $tmpl->type }}</span>
                    </div>
                    <label class="flex items-center gap-1 text-xs">
                        <input type="checkbox" {{ $tmpl->is_active ? 'checked' : '' }} onchange="toggleTemplate({{ $tmpl->id }}, this.checked)" class="rounded border-gray-300 text-blue-600">
                        Ativo
                    </label>
                </div>
                <input type="text" value="{{ $tmpl->label }}" id="tmpl-label-{{ $tmpl->id }}" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm mb-2 font-semibold">
                <textarea id="tmpl-prompt-{{ $tmpl->id }}" rows="6" class="w-full border border-gray-300 rounded px-3 py-2 text-xs font-mono">{{ $tmpl->prompt_text }}</textarea>
                <div class="flex justify-end mt-2">
                    <button onclick="saveTemplate({{ $tmpl->id }})" class="px-3 py-1.5 bg-gray-900 text-white text-xs rounded hover:bg-gray-800">Salvar</button>
                </div>
            </div>
            @endforeach
        </div>

        {{-- TAB: Métricas --}}
        <div x-show="tab==='metricas'" class="bg-white border rounded-lg p-6">
            <p class="text-sm text-gray-500">Em construção — dashboard de qualidade, custo por conversa, taxa de feedback, campos de profile mais vazios.</p>
        </div>
    </div>

    {{-- Modal de Auditoria de Conversa --}}
    <div id="audit-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);overflow-y:auto;" onclick="if(event.target===this)this.style.display='none'">
        <div style="background:white;max-width:min(900px, 95vw);margin:2rem auto;border-radius:1rem;overflow:hidden;" onclick="event.stopPropagation()">
            <div style="padding:1rem 1.5rem;background:linear-gradient(135deg,#1B334A,#385776);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="color:white;font-size:1rem;font-weight:700;margin:0;" id="audit-title">Auditoria</h3>
                <button onclick="document.getElementById('audit-modal').style.display='none'" style="color:rgba(255,255,255,0.6);background:none;border:none;font-size:1.5rem;cursor:pointer;">&times;</button>
            </div>
            <div id="audit-content" style="padding:1.5rem;max-height:80vh;overflow-y:auto;">
                <p class="text-sm text-gray-500">Carregando...</p>
            </div>
        </div>
    </div>
</div>

<script>
async function loadAudit(convId) {
    document.getElementById('audit-modal').style.display = 'block';
    document.getElementById('audit-content').innerHTML = '<p class="text-sm text-gray-500">Carregando...</p>';

    const resp = await fetch('/justus/admin/audit/' + convId, { headers: { 'Accept': 'application/json' }});
    const data = await resp.json();

    document.getElementById('audit-title').textContent = 'Auditoria — ' + (data.conversation.title || 'ID ' + convId);

    let html = '';

    // Profile
    if (data.profile) {
        html += '<div class="mb-4 p-3 bg-blue-50 rounded-lg"><h4 class="text-xs font-bold text-blue-800 mb-2">PERFIL EXTRAÍDO</h4><div class="grid grid-cols-2 gap-2 text-xs">';
        ['classe','autor','reu','relator_vara','fase_atual','tese_principal','objetivo_analise','orgao'].forEach(f => {
            var val = data.profile[f] || '<span class="text-red-400">vazio</span>';
            html += '<div><span class="font-medium text-blue-700">' + f + ':</span> ' + val + '</div>';
        });
        html += '</div></div>';
    }

    // Messages
    data.messages.forEach(function(m) {
        var bg = m.role === 'assistant' ? 'bg-gray-50' : 'bg-white';
        var border = m.feedback === 'positive' ? 'border-l-4 border-l-green-400' : (m.feedback === 'negative' ? 'border-l-4 border-l-red-400' : '');

        html += '<div class="mb-3 p-3 rounded-lg ' + bg + ' ' + border + ' border border-gray-200">';
        html += '<div class="flex items-center justify-between mb-2">';
        html += '<span class="text-xs font-bold ' + (m.role === 'assistant' ? 'text-blue-700' : 'text-gray-700') + '">' + m.role.toUpperCase() + '</span>';
        html += '<div class="flex items-center gap-3 text-xs text-gray-400">';
        if (m.model_used) html += '<span>' + m.model_used + '</span>';
        if (m.input_tokens) html += '<span>' + m.input_tokens + '/' + m.output_tokens + ' tokens</span>';
        if (m.cost_brl) html += '<span>R$ ' + parseFloat(m.cost_brl).toFixed(2) + '</span>';
        if (m.feedback) html += '<span class="' + (m.feedback === 'positive' ? 'text-green-600' : 'text-red-600') + '">' + m.feedback + '</span>';
        html += '<span>' + m.created_at + '</span>';
        html += '</div></div>';

        // Content (truncado para assistant)
        var content = m.content || '';
        if (m.role === 'assistant' && content.length > 500) {
            html += '<div class="text-xs text-gray-600 whitespace-pre-wrap">' + content.substring(0, 500) + '...</div>';
            html += '<button onclick="this.previousElementSibling.textContent=this.dataset.full;this.remove()" data-full="' + content.replace(/"/g, '&quot;').replace(/</g, '&lt;') + '" class="text-xs text-blue-600 mt-1">Ver completo</button>';
        } else {
            html += '<div class="text-xs text-gray-600 whitespace-pre-wrap">' + content.substring(0, 2000) + '</div>';
        }

        // Debug info
        if (m.role === 'assistant' && m.debug_prompt) {
            html += '<details class="mt-2"><summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">Ver prompt completo (' + (m.system_prompt_length || '?') + ' chars, ' + (m.rag_chunks_count || 0) + ' chunks' + (m.juris_injected ? ', com juris' : '') + ')</summary>';
            html += '<pre class="mt-1 p-2 bg-gray-100 rounded text-xs text-gray-600 max-h-64 overflow-y-auto whitespace-pre-wrap">' + m.debug_prompt.replace(/</g, '&lt;') + '</pre></details>';
        }

        html += '</div>';
    });

    document.getElementById('audit-content').innerHTML = html;
}

async function saveTemplate(id) {
    const resp = await fetch('/justus/admin/templates/' + id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: JSON.stringify({
            label: document.getElementById('tmpl-label-' + id).value,
            prompt_text: document.getElementById('tmpl-prompt-' + id).value,
        })
    });
    if (resp.ok) alert('Template salvo!');
}

async function toggleTemplate(id, active) {
    await fetch('/justus/admin/templates/' + id, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json' },
        body: JSON.stringify({ is_active: active })
    });
}
</script>
@endsection
