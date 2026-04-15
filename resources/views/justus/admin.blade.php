@extends('layouts.app')
@section('title', 'JUSTUS — Administração')
@section('content')
<div class="max-w-7xl mx-auto px-4 py-6" x-data="{ tab: 'config' }">

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900">JUSTUS — Administração</h1>
            <p class="text-sm text-gray-500 mt-1">Configuração, auditoria e calibração</p>
        </div>
        <a href="{{ route('justus.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; Voltar ao JUSTUS</a>
    </div>

    {{-- Tabs --}}
    <div class="flex gap-1 bg-gray-100 p-1 rounded-lg w-fit mb-6">
        <button @click="tab='config'" :class="tab==='config' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium rounded-md transition">Configuração</button>
        <button @click="tab='auditoria'" :class="tab==='auditoria' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium rounded-md transition">Auditoria</button>
        <button @click="tab='templates'" :class="tab==='templates' ? 'bg-white shadow text-gray-900' : 'text-gray-500 hover:text-gray-700'" class="px-4 py-2 text-sm font-medium rounded-md transition">Prompts</button>
    </div>

    {{-- ====== TAB: CONFIGURAÇÃO ====== --}}
    <div x-show="tab==='config'" class="space-y-6">
        {{-- Budget --}}
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Orçamento do Mês</h2>
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                <div><span class="text-gray-500">Gasto</span><p class="font-semibold">R$ {{ number_format($budget['cost_brl'], 2, ',', '.') }}</p></div>
                <div><span class="text-gray-500">Limite Global</span><p class="font-semibold">R$ {{ number_format($config['budget_monthly_max'], 2, ',', '.') }}</p></div>
                <div><span class="text-gray-500">Limite/Usuário</span><p class="font-semibold">R$ {{ number_format($config['budget_user_max'], 2, ',', '.') }}</p></div>
                <div><span class="text-gray-500">Câmbio USD/BRL</span><p class="font-semibold">{{ $config['usd_brl'] }}</p></div>
                <div><span class="text-gray-500">Modelo</span><p class="font-semibold">{{ $config['model_default'] }}</p></div>
            </div>
        </div>

        {{-- Style Guides --}}
        @foreach($guides as $guide)
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden" id="guide-{{ $guide->id }}">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium {{ $guide->mode === 'consultor' ? 'bg-blue-100 text-blue-800' : 'bg-emerald-100 text-emerald-800' }}">{{ strtoupper($guide->mode) }}</span>
                    <span class="text-sm font-semibold text-gray-800">{{ $guide->name }}</span>
                    <span class="text-xs text-gray-400">v{{ $guide->version }}</span>
                </div>
                @if($guide->is_active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-green-100 text-green-700">Ativo</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-500">Inativo</span>
                @endif
            </div>
            <form onsubmit="return saveGuide(event, {{ $guide->id }})" class="p-4 space-y-4">
                @csrf
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Nome do Perfil</label>
                <input type="text" name="name" value="{{ $guide->name }}" class="w-full border border-gray-300 rounded px-3 py-2 text-sm"></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Regras de Comportamento</label>
                <textarea name="behavior_rules" rows="6" class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono">{{ $guide->behavior_rules }}</textarea></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">Normativo AD003</label>
                <textarea name="ad003_disclaimer" rows="5" class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono">{{ $guide->ad003_disclaimer }}</textarea></div>
                <div><label class="block text-xs font-medium text-gray-600 mb-1">System Prompt ({{ $guide->mode }})</label>
                <textarea name="system_prompt" rows="14" class="w-full border border-gray-300 rounded px-3 py-2 text-sm font-mono">{{ $guide->system_prompt }}</textarea></div>
                <div class="flex items-center justify-between pt-2">
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_active" value="1" {{ $guide->is_active ? 'checked' : '' }} class="rounded border-gray-300 text-blue-600"> Ativo</label>
                    <button type="submit" class="px-4 py-2 bg-gray-900 text-white text-sm rounded hover:bg-gray-800" id="btn-{{ $guide->id }}">Salvar</button>
                </div>
                <div id="status-{{ $guide->id }}" class="text-xs hidden"></div>
            </form>
        </div>
        @endforeach

        <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-xs text-gray-500 space-y-1">
            <p><strong>Prompt final:</strong> Regras de Comportamento + AD003 + System Prompt + Contexto do Processo + RAG + Jurisprudência.</p>
            <p>Alterações afetam apenas novas mensagens.</p>
        </div>
    </div>

    {{-- ====== TAB: AUDITORIA ====== --}}
    <div x-show="tab==='auditoria'" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-white border rounded-lg p-3 text-center"><p class="text-2xl font-bold text-gray-900">{{ $feedbackStats['total'] }}</p><p class="text-xs text-gray-500">Feedbacks</p></div>
            <div class="bg-white border rounded-lg p-3 text-center"><p class="text-2xl font-bold text-green-600">{{ $feedbackStats['positive'] }}</p><p class="text-xs text-gray-500">Positivos</p></div>
            <div class="bg-white border rounded-lg p-3 text-center"><p class="text-2xl font-bold text-red-600">{{ $feedbackStats['negative'] }}</p><p class="text-xs text-gray-500">Negativos</p></div>
        </div>

        @foreach($conversations as $conv)
        <div class="bg-white border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-blue-300 transition" onclick="loadAudit({{ $conv->id }})">
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
            @if($conv->processProfile && ($conv->processProfile->classe || $conv->processProfile->tese_principal))
            <div class="mt-2 flex gap-2 flex-wrap">
                @if($conv->processProfile->classe)<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ $conv->processProfile->classe }}</span>@endif
                @if($conv->processProfile->tese_principal)<span class="text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded">{{ Str::limit($conv->processProfile->tese_principal, 60) }}</span>@endif
            </div>
            @endif
        </div>
        @endforeach
    </div>

    {{-- ====== TAB: PROMPTS PROGRAMADOS ====== --}}
    <div x-show="tab==='templates'" class="space-y-4">
        @foreach($templates as $tmpl)
        <div class="bg-white border border-gray-200 rounded-lg p-4">
            <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-medium px-2 py-0.5 rounded {{ $tmpl->category === 'execucao' ? 'bg-red-100 text-red-700' : ($tmpl->category === 'analise_pecas' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-700') }}">{{ $tmpl->category }}</span>
                    <span class="text-xs text-gray-400">{{ $tmpl->mode }} / {{ $tmpl->type }}</span>
                </div>
                <label class="flex items-center gap-1 text-xs"><input type="checkbox" {{ $tmpl->is_active ? 'checked' : '' }} onchange="toggleTemplate({{ $tmpl->id }}, this.checked)" class="rounded border-gray-300 text-blue-600"> Ativo</label>
            </div>
            <input type="text" value="{{ $tmpl->label }}" id="tmpl-label-{{ $tmpl->id }}" class="w-full border border-gray-300 rounded px-3 py-1.5 text-sm mb-2 font-semibold">
            <textarea id="tmpl-prompt-{{ $tmpl->id }}" rows="5" class="w-full border border-gray-300 rounded px-3 py-2 text-xs font-mono">{{ $tmpl->prompt_text }}</textarea>
            <div class="flex justify-end mt-2"><button onclick="saveTemplate({{ $tmpl->id }})" class="px-3 py-1.5 bg-gray-900 text-white text-xs rounded hover:bg-gray-800">Salvar</button></div>
        </div>
        @endforeach
    </div>

    {{-- Modal Auditoria --}}
    <div id="audit-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);overflow-y:auto;" onclick="if(event.target===this)this.style.display='none'">
        <div style="background:white;max-width:min(900px, 95vw);margin:2rem auto;border-radius:1rem;overflow:hidden;" onclick="event.stopPropagation()">
            <div style="padding:1rem 1.5rem;background:linear-gradient(135deg,#1B334A,#385776);display:flex;justify-content:space-between;align-items:center;">
                <h3 style="color:white;font-size:1rem;font-weight:700;margin:0;" id="audit-title">Auditoria</h3>
                <button onclick="document.getElementById('audit-modal').style.display='none'" style="color:rgba(255,255,255,0.6);background:none;border:none;font-size:1.5rem;cursor:pointer;">&times;</button>
            </div>
            <div id="audit-content" style="padding:1.5rem;max-height:80vh;overflow-y:auto;"><p class="text-sm text-gray-500">Carregando...</p></div>
        </div>
    </div>
</div>

<script>
async function saveGuide(e, guideId) {
    e.preventDefault();
    var form = e.target, btn = document.getElementById('btn-' + guideId), status = document.getElementById('status-' + guideId);
    btn.disabled = true; btn.textContent = 'Salvando...';
    try {
        var res = await fetch('/justus/admin/guides/' + guideId, { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}, body:JSON.stringify({ name:form.querySelector('[name="name"]').value, system_prompt:form.querySelector('[name="system_prompt"]').value, behavior_rules:form.querySelector('[name="behavior_rules"]').value, ad003_disclaimer:form.querySelector('[name="ad003_disclaimer"]').value, is_active:form.querySelector('[name="is_active"]').checked?1:0 })});
        var json = await res.json();
        status.className = 'text-xs mt-1 ' + (json.success?'text-green-600':'text-red-600');
        status.textContent = json.success?'Salvo com sucesso.':(json.message||'Erro.');
        status.classList.remove('hidden'); setTimeout(()=>status.classList.add('hidden'),3000);
    } catch(err) { status.className='text-xs mt-1 text-red-600'; status.textContent='Erro.'; status.classList.remove('hidden'); }
    btn.disabled=false; btn.textContent='Salvar'; return false;
}

async function loadAudit(convId) {
    document.getElementById('audit-modal').style.display = 'block';
    document.getElementById('audit-content').innerHTML = '<p class="text-sm text-gray-500">Carregando...</p>';
    var resp = await fetch('/justus/admin/audit/' + convId, {headers:{'Accept':'application/json'}});
    var data = await resp.json();
    document.getElementById('audit-title').textContent = 'Auditoria — ' + (data.conversation.title || 'ID ' + convId);
    var html = '';
    if (data.profile) {
        html += '<div class="mb-4 p-3 bg-blue-50 rounded-lg"><h4 class="text-xs font-bold text-blue-800 mb-2">PERFIL EXTRAÍDO</h4><div class="grid grid-cols-2 gap-2 text-xs">';
        ['classe','autor','reu','relator_vara','fase_atual','tese_principal','objetivo_analise','orgao'].forEach(function(f){ html += '<div><span class="font-medium text-blue-700">'+f+':</span> '+(data.profile[f]||'<span class="text-red-400">vazio</span>')+'</div>'; });
        html += '</div></div>';
    }
    data.messages.forEach(function(m) {
        var bg = m.role==='assistant'?'bg-gray-50':'bg-white';
        var border = m.feedback==='positive'?'border-l-4 border-l-green-400':(m.feedback==='negative'?'border-l-4 border-l-red-400':'');
        html += '<div class="mb-3 p-3 rounded-lg '+bg+' '+border+' border border-gray-200">';
        html += '<div class="flex items-center justify-between mb-2"><span class="text-xs font-bold '+(m.role==='assistant'?'text-blue-700':'text-gray-700')+'">'+m.role.toUpperCase()+'</span>';
        html += '<div class="flex items-center gap-3 text-xs text-gray-400">';
        if(m.model_used) html += '<span>'+m.model_used+'</span>';
        if(m.input_tokens) html += '<span>'+m.input_tokens+'/'+m.output_tokens+' tok</span>';
        if(m.cost_brl) html += '<span>R$ '+parseFloat(m.cost_brl).toFixed(2)+'</span>';
        if(m.feedback) html += '<span class="'+(m.feedback==='positive'?'text-green-600':'text-red-600')+'">'+m.feedback+'</span>';
        html += '<span>'+m.created_at+'</span></div></div>';
        var c = (m.content||'').replace(/</g,'&lt;');
        if(m.role==='assistant' && c.length>800) { html += '<div class="text-xs text-gray-600 whitespace-pre-wrap" id="msg-'+m.id+'">'+c.substring(0,800)+'...</div><button onclick="document.getElementById(\'msg-'+m.id+'\').textContent=decodeURIComponent(this.dataset.f);this.remove()" data-f="'+encodeURIComponent(m.content)+'" class="text-xs text-blue-600 mt-1 hover:underline">Ver completo</button>'; }
        else { html += '<div class="text-xs text-gray-600 whitespace-pre-wrap">'+c+'</div>'; }
        if(m.role==='assistant' && m.debug_prompt) {
            html += '<details class="mt-2"><summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">Prompt completo ('+(m.system_prompt_length||'?')+' chars, '+(m.rag_chunks_count||0)+' chunks'+(m.juris_injected?', juris':'')+')' + '</summary>';
            html += '<pre class="mt-1 p-2 bg-gray-100 rounded text-xs text-gray-600 max-h-64 overflow-y-auto whitespace-pre-wrap">'+m.debug_prompt.replace(/</g,'&lt;')+'</pre></details>';
        }
        html += '</div>';
    });
    document.getElementById('audit-content').innerHTML = html;
}

async function saveTemplate(id) {
    var resp = await fetch('/justus/admin/templates/'+id, { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}, body:JSON.stringify({ label:document.getElementById('tmpl-label-'+id).value, prompt_text:document.getElementById('tmpl-prompt-'+id).value })});
    if(resp.ok) alert('Template salvo!');
}

async function toggleTemplate(id, active) {
    await fetch('/justus/admin/templates/'+id, { method:'PUT', headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'}, body:JSON.stringify({is_active:active})});
}
</script>
@endsection
