<script>
// ═══ Tab Switcher ═══
function switchTab(tab) {
    const panels = {contexto:'contexto360-panel',datajuri:'datajuri-panel',notes:'notes-panel',flows:'flows-panel'};
    const tabs = {contexto:'tab-contexto',datajuri:'tab-datajuri',notes:'tab-notes',flows:'tab-flows'};
    Object.values(panels).forEach(id=>{const el=document.getElementById(id);if(el){el.classList.add('hidden');el.classList.remove('flex')}});
    Object.values(tabs).forEach(id=>{const el=document.getElementById(id);if(el){el.classList.remove('text-[#1e3a5f]','border-[#1e3a5f]');el.classList.add('text-gray-500','border-transparent')}});
    const p=document.getElementById(panels[tab]),t=document.getElementById(tabs[tab]);
    if(p){p.classList.remove('hidden');if(['notes','flows'].includes(tab))p.classList.add('flex')}
    if(t){t.classList.remove('text-gray-500','border-transparent');t.classList.add('text-[#1e3a5f]','border-[#1e3a5f]')}
    if(tab==='datajuri'&&typeof NexoDataJuri!=='undefined')NexoDataJuri.carregar();
    if(tab==='notes')NexoApp.loadNotes();
    if(tab==='flows')NexoApp.loadFlows();
}
window.refreshContexto360=function(convId){
    const dj=document.getElementById('datajuri-panel');
    if(dj&&!dj.classList.contains('hidden')&&typeof NexoDataJuri!=='undefined')NexoDataJuri.carregar();
};

// ═══ NexoApp Core ═══
const NexoApp = {
    conversas:[],conversaAtual:null,lastMsgId:null,lastMsgCount:0,
    filters:{status:'',unread:'',minhas:''},searchTerm:'',pollTimer:null,inboxTimer:null,flowsCache:null,
    priorityLevels:['normal','alta','urgente','critica'],
    csrf:document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')||'',

    init(){this.loadConversas();this.inboxTimer=setInterval(()=>this.loadConversas(true),8000)},

    async api(url,opts={}){
        const o={headers:{'Accept':'application/json',...(opts.headers||{})},...opts};
        if(o.body&&typeof o.body==='object'&&!(o.body instanceof FormData)){o.body=JSON.stringify(o.body);o.headers['Content-Type']='application/json'}
        if(o.method&&o.method!=='GET')o.headers['X-CSRF-TOKEN']=this.csrf;
        const r=await fetch(url,o);if(!r.ok)throw new Error(`HTTP ${r.status}`);return r.json();
    },

    // ═══ INBOX ═══
    async loadConversas(silent=false){
        if(!silent){document.getElementById('inbox-loading').classList.remove('hidden');document.getElementById('inbox-items').innerHTML='';document.getElementById('inbox-empty').classList.add('hidden')}
        const p=new URLSearchParams();
        if(this.filters.status)p.set('status',this.filters.status);
        if(this.filters.unread)p.set('unread',this.filters.unread);
        if(this.filters.minhas)p.set('minhas',this.filters.minhas);
        try{
            const j=await this.api(`/nexo/atendimento/conversas?${p}`);
            this.conversas=j.data||[];this.renderInbox();
            document.getElementById('inbox-total').textContent=`${j.total||this.conversas.length} conversas`;
        }catch(e){console.error('Inbox error:',e);if(!silent)document.getElementById('inbox-items').innerHTML='<p class="text-center text-red-500 text-sm py-6">Erro ao carregar</p>'}
        finally{document.getElementById('inbox-loading').classList.add('hidden')}
    },

    renderInbox(){
        const c=document.getElementById('inbox-items'),em=document.getElementById('inbox-empty');
        const s=this.searchTerm.toLowerCase();
        let list=this.conversas;
        if(s)list=list.filter(x=>(x.name||'').toLowerCase().includes(s)||(x.phone||'').includes(s));
        if(!list.length){c.innerHTML='';em.classList.remove('hidden');return}
        em.classList.add('hidden');
        c.innerHTML=list.map(x=>this.inboxItem(x)).join('');
    },

    inboxItem(c){
        const active=this.conversaAtual?.id===c.id;
        const ini=(c.name||'??').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
        const time=c.last_message_at?this.timeAgo(c.last_message_at):'';
        const unread=c.unread_count||0;
        const isUnread=unread>0||c.marked_unread;
        let lb='';
        if(c.linked_cliente_id)lb='<span class="text-[10px] px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded font-medium">CLI</span>';
        else if(c.linked_lead_id)lb='<span class="text-[10px] px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded font-medium">LEAD</span>';
        else lb='<span class="text-[10px] px-1.5 py-0.5 bg-gray-100 text-gray-500 rounded">?</span>';
        const pr=c.priority||'normal';
        const pb=pr!=='normal'?`<span class="w-2 h-2 rounded-full inline-block priority-${pr}"></span>`:'';
        return `<div class="inbox-item ${active?'active':''} ${isUnread?'unread':''} flex items-center gap-3 px-4 py-3.5 cursor-pointer border-b border-gray-100/60" data-conv-id="${c.id}" onclick="NexoApp.selectConversa(${c.id})" oncontextmenu="NexoApp.showContextMenu(event,${c.id},${isUnread})">
            <div class="relative flex-shrink-0"><div class="w-11 h-11 rounded-full bg-[#1e3a5f] text-white flex items-center justify-center text-[13px] font-bold shadow-sm">${ini}</div>${isUnread?`<span class="absolute -top-1 -right-1 unread-badge">${unread||'!'}</span>`:''}</div>
            <div class="flex-1 min-w-0"><div class="flex items-center justify-between"><span class="inbox-contact-name text-[13px] ${isUnread?'font-bold':'font-semibold'} text-[#111b21] truncate">${this.esc(c.name||'Sem nome')}</span><span class="text-[10px] ${isUnread?'text-[#25d366] font-semibold':'text-[#8696a0]'} flex-shrink-0 ml-2">${time}</span></div><div class="flex items-center justify-between mt-0.5"><span class="text-[11px] text-[#3b4a54] truncate">${this.fmtPhone(c.phone||'')}</span><div class="flex items-center gap-1 flex-shrink-0 ml-2">${pb} ${lb}</div></div></div></div>`;
    },

    setFilter(k,v){
        this.filters={status:'',unread:'',minhas:''};
        if(k&&v)this.filters[k]=v;
        document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
        if(!k||!v){document.querySelector('[data-filter="all"]').classList.add('active')}
        else{const fk=k==='minhas'?'minhas':(k==='unread'?'unread':v);const t=document.querySelector(`[data-filter="${fk}"]`);if(t)t.classList.add('active')}
        this.loadConversas();
    },
    filterLocal(t){this.searchTerm=t;this.renderInbox()},

    // ═══ SELECT CONVERSA ═══
    async selectConversa(id){
        if(this.pollTimer)clearInterval(this.pollTimer);
        this.lastMsgId=null;this.lastMsgCount=0;
        try{
            const j=await this.api(`/nexo/atendimento/conversas/${id}`);
            this.conversaAtual=j.conversation;
            const cv=j.conversation;
            document.getElementById('chat-empty').classList.add('hidden');
            ['chat-header','chat-messages'].forEach(x=>{const el=document.getElementById(x);el.classList.remove('hidden');if(x==='chat-header')el.classList.add('flex')});
            this.toggleBotUI(cv.bot_ativo);
            document.getElementById('btnReabrirConversa').classList.remove('hidden');
            if(window.innerWidth<1024){document.getElementById('inbox-panel').classList.add('hidden');const cp=document.getElementById('chat-panel');cp.classList.remove('hidden');cp.classList.add('flex')}
            const ini=(cv.name||'??').split(' ').map(w=>w[0]).slice(0,2).join('').toUpperCase();
            document.getElementById('chat-avatar').textContent=ini;
            document.getElementById('chat-contact-name').textContent=cv.name||'Sem nome';
            document.getElementById('chat-contact-phone').textContent=cv.phone?this.fmtPhone(cv.phone):'';
            const bd=document.getElementById('chat-status-badge');
            if(cv.status==='open'){bd.textContent='Aberta';bd.className='text-xs px-2 py-0.5 rounded-full bg-[#e7fce3] text-[#1e3a5f]'}
            else{bd.textContent='Fechada';bd.className='text-xs px-2 py-0.5 rounded-full bg-gray-200 text-gray-600'}
            document.getElementById('chat-toggle-status').textContent=cv.status==='open'?'Fechar':'Reabrir';
            document.getElementById('chat-assign-select').value=cv.assigned_user_id||'';
            this.updatePrioBadge(cv.priority||'normal');
            this.renderMessages(j.messages||[]);
            document.querySelectorAll('.inbox-item').forEach(el=>el.classList.remove('active'));
            const ai=document.querySelector(`.inbox-item[onclick*="selectConversa(${id})"]`);if(ai)ai.classList.add('active');
            this.loadContexto(id);
            if(typeof NexoDataJuri!=='undefined')NexoDataJuri.setConversation(id);
            switchTab('contexto');
            this.pollTimer=setInterval(()=>this.poll(id),5000);
        }catch(e){console.error('Select error:',e)}
    },

    // ═══ RENDER MENSAGENS COM MÍDIA ═══
    renderMessages(msgs){
        const c=document.getElementById('chat-messages');
        if(!msgs.length){c.innerHTML='<p class="text-center text-[#667781] text-sm py-8">Nenhuma mensagem</p>';this.lastMsgId=null;this.lastMsgCount=0;return}
        let lastDate='',html='';
        msgs.forEach(m=>{
            const md=m.sent_at?m.sent_at.split('T')[0]:'';
            if(md&&md!==lastDate){lastDate=md;html+=`<div class="flex justify-center my-4"><span class="nexo-day-sep">${this.fmtDate(md)}</span></div>`}
            const isIn=parseInt(m.direction)===1;
            const time=m.sent_at?new Date(m.sent_at).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'}):'';
            const hb=(!isIn&&m.is_human)?'<span class="text-[9px] text-[#53bdeb] ml-1">✓✓</span>':'';
            const bc=isIn?'msg-bubble-in':'msg-bubble-out';
            const al=isIn?'justify-start':'justify-end';
            const content=this.renderMsgContent(m);
            const pid=m.provider_message_id||'';
            const quoteHtml=this.renderQuote(m);
            const actionsHtml=pid?`<div class="nexo-msg-actions opacity-0 group-hover/msg:opacity-100 absolute ${isIn?'-right-2':'-left-2'} -top-1 flex gap-0.5 z-10 transition-opacity"><button onclick="NexoApp.startReply(${m.id},'${pid}')" class="w-6 h-6 flex items-center justify-center rounded-full bg-white shadow-md hover:bg-gray-100 text-[#8696a0] hover:text-[#3b4a54] transition-colors" title="Responder"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a5 5 0 015 5v4M3 10l6 6M3 10l6-6"/></svg></button><button onclick="NexoApp.showEmojiPicker(event,${m.id},'${pid}')" class="w-6 h-6 flex items-center justify-center rounded-full bg-white shadow-md hover:bg-gray-100 text-[#8696a0] hover:text-[#3b4a54] transition-colors" title="Reagir"><svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"><circle cx="12" cy="12" r="10" stroke-width="2"/><path d="M8 14s1.5 2 4 2 4-2 4-2" stroke-width="2" stroke-linecap="round"/><circle cx="9" cy="9" r="1" fill="currentColor" stroke="none"/><circle cx="15" cy="9" r="1" fill="currentColor" stroke="none"/></svg></button></div>`:'';
            html+=`<div class="flex ${al} mb-1" data-pmid="${pid}"><div class="relative group/msg max-w-[75%] lg:max-w-[60%]">${actionsHtml}<div class="${bc} px-3 py-2">${quoteHtml}${content}<p class="text-[10px] text-[#8696a0] text-right mt-1 leading-none select-none">${time}${hb}</p></div></div></div>`;
        });
        c.innerHTML=html;
        this._lastMsgs=msgs;
        this.lastMsgId=msgs[msgs.length-1].id;
        this.lastMsgCount=msgs.length;
        c.scrollTop=c.scrollHeight;
    },

    renderMsgContent(m){
        const type=m.message_type||'text';
        const body=this.esc(m.body||'');
        const url=m.media_url||'';
        const cap=m.media_caption?this.esc(m.media_caption):'';
        const fn=m.media_filename?this.esc(m.media_filename):'';
        const eu=this.esc(url);
        const txtP=(t)=>`<p class="text-[13.5px] text-[#111b21] whitespace-pre-wrap break-words leading-[22px]">${t}</p>`;

        switch(type){
            case 'image':case 'sticker':
                if(url){let h=`<img src="${eu}" class="msg-media-img" onclick="window.open('${eu}','_blank')" loading="lazy">`;if(cap)h+=txtP(cap);else if(body)h+=txtP(body);return h}
                return `<div class="flex items-center gap-2 text-xs text-[#667781]">📷 Imagem</div>`+(body?txtP(body):'');

            case 'audio':case 'voice':
                if(url)return `<audio controls class="msg-media-audio" preload="none"><source src="${eu}"></audio>`+(body&&body!==cap?txtP(body):'');
                return `<div class="text-xs text-[#667781]">🎵 Áudio</div>`+(body?txtP(body):'');

            case 'video':
                if(url){let h=`<video controls class="msg-media-img" preload="none"><source src="${eu}"></video>`;if(cap)h+=txtP(cap);return h}
                return `<div class="text-xs text-[#667781]">🎬 Vídeo</div>`;

            case 'document':
                const dn=fn||'Documento';
                if(url)return `<a href="${eu}" target="_blank" class="msg-media-doc"><svg class="w-8 h-8 text-[#667781] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg><div class="min-w-0"><p class="text-xs font-medium text-[#111b21] truncate">${dn}</p><p class="text-[10px] text-[#667781]">Abrir</p></div></a>`+(cap?txtP(cap):'');
                return `<div class="msg-media-doc"><span class="text-xs text-[#667781]">📄 ${dn}</span></div>`;

            case 'interactive':
                return body?txtP(body):'<p class="text-[13px] text-[#8696a0] italic">Resposta de botão</p>';

            default:
                return body?txtP(body):'<p class="text-[13px] text-[#8696a0] italic">Mensagem sem conteúdo</p>';
        }
    },

    // ═══ POLL ═══
    async poll(cid){
        if(!this.conversaAtual||this.conversaAtual.id!==cid)return;
        try{
            const j=await this.api(`/nexo/atendimento/conversas/${cid}/poll`);
            const msgs=j.messages||[];if(!msgs.length)return;
            const lid=msgs[msgs.length-1].id;
            if(lid!==this.lastMsgId||msgs.length!==this.lastMsgCount){this.renderMessages(msgs);this.loadConversas(true)}
        }catch(e){console.warn('Poll error:',e)}
    },

    // ═══ ENVIAR MENSAGEM ═══
    replyTo:null, // {id, provider_message_id, body, direction}

    toggleBotUI(botAtivo){
        const banner=document.getElementById('bot-ativo-banner');
        const input=document.getElementById('chat-input-bar');
        if(botAtivo){if(banner)banner.classList.remove('hidden');if(input)input.classList.add('hidden')}
        else{if(banner)banner.classList.add('hidden');if(input)input.classList.remove('hidden')}
    },
    async assumirConversa(){
        if(!this.conversaAtual)return;
        if(!confirm('Assumir esta conversa e desativar o bot?'))return;
        try{await this.api('/nexo/atendimento/conversas/'+this.conversaAtual.id+'/assumir',{method:'POST'});this.conversaAtual.bot_ativo=false;this.toggleBotUI(false);this.loadConversas(true)}catch(e){alert('Erro: '+e.message)}
    },
    async sendMessage(){
        if(!this.conversaAtual)return;
        const inp=document.getElementById('chat-input'),text=inp.value.trim();if(!text)return;
        inp.value='';inp.style.height='auto';
        const c=document.getElementById('chat-messages');
        const now=new Date().toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit'});
        c.insertAdjacentHTML('beforeend',`<div class="flex justify-end mb-1"><div class="msg-bubble-out px-3 py-2 max-w-[75%] lg:max-w-[60%] opacity-70"><p class="text-[13px] text-[#111b21] whitespace-pre-wrap break-words">${this.esc(text)}</p><p class="text-[10px] text-[#667781] text-right mt-0.5">⏳ ${now}</p></div></div>`);
        c.scrollTop=c.scrollHeight;
        const body={text};
        if(this.replyTo&&this.replyTo.provider_message_id){body.reply_to_message_id=this.replyTo.provider_message_id}
        this.cancelReply();
        try{
            await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/mensagens`,{method:'POST',body});
            const j=await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}`);
            this.renderMessages(j.messages||[]);
        }catch(e){console.error('Send error:',e);c.insertAdjacentHTML('beforeend','<p class="text-center text-red-500 text-xs py-1">Erro ao enviar</p>');c.scrollTop=c.scrollHeight}
    },

    // ═══ REPLY / QUOTE ═══
    startReply(msgId, providerMsgId){
        if(!this.conversaAtual)return;
        const msgs=this._lastMsgs||[];
        const m=msgs.find(x=>x.id===msgId);
        if(!m)return;
        this.replyTo={id:msgId,provider_message_id:providerMsgId,body:m.body||'[Mídia]',direction:m.direction};
        const bar=document.getElementById('reply-bar');
        const author=document.getElementById('reply-bar-author');
        const txt=document.getElementById('reply-bar-text');
        if(!bar)return;
        author.textContent=parseInt(m.direction)===1?(this.conversaAtual.contact_name||'Cliente'):'Você';
        txt.textContent=m.body||'[Mídia: '+( m.message_type||'arquivo')+']';
        bar.classList.remove('hidden');
        document.getElementById('chat-input').focus();
    },

    cancelReply(){
        this.replyTo=null;
        const bar=document.getElementById('reply-bar');
        if(bar)bar.classList.add('hidden');
    },

    renderQuote(m){
        if(!m.reply_to_message_id)return '';
        const msgs=this._lastMsgs||[];
        const orig=msgs.find(x=>x.provider_message_id===m.reply_to_message_id);
        const qText=orig?(this.esc(orig.body||'[Mídia]')):'[Mensagem original]';
        const qAuthor=orig?(parseInt(orig.direction)===1?(this.conversaAtual?.contact_name||'Cliente'):'Você'):'';
        return `<div class="mb-1.5 px-2 py-1.5 bg-[#e2e8f0]/50 rounded-lg border-l-3 border-[#1e3a5f]/40 cursor-pointer" onclick="NexoApp.scrollToMsg('${m.reply_to_message_id}')"><p class="text-[10px] font-semibold text-[#1e3a5f]/70">${qAuthor}</p><p class="text-[11px] text-[#667781] truncate">${qText}</p></div>`;
    },

    scrollToMsg(providerMsgId){
        const c=document.getElementById('chat-messages');
        if(!c)return;
        const bubbles=c.querySelectorAll('[data-pmid]');
        for(const b of bubbles){if(b.dataset.pmid===providerMsgId){b.scrollIntoView({behavior:'smooth',block:'center'});b.style.transition='background 0.3s';b.style.background='#fef9c3';setTimeout(()=>{b.style.background=''},1500);return}}
    },

    // ═══ REACTION (EMOJI) ═══
    showEmojiPicker(evt, msgId, providerMsgId){
        this.closeEmojiPicker();
        const emojis=['👍','❤️','😂','😮','😢','🙏'];
        const el=document.createElement('div');
        el.id='nexo-emoji-picker';
        el.className='fixed z-50 bg-white rounded-2xl shadow-xl border border-gray-200 px-2 py-1.5 flex gap-1 animate-in';
        el.style.left=(evt.clientX-80)+'px';el.style.top=(evt.clientY-50)+'px';
        emojis.forEach(e=>{
            const btn=document.createElement('button');
            btn.textContent=e;
            btn.className='text-xl hover:scale-125 transition-transform p-1 rounded-lg hover:bg-gray-100';
            btn.onclick=()=>this.sendReaction(msgId,providerMsgId,e);
            el.appendChild(btn);
        });
        document.body.appendChild(el);
        setTimeout(()=>document.addEventListener('click',this._closePicker=()=>this.closeEmojiPicker(),{once:true}),50);
    },

    closeEmojiPicker(){
        const p=document.getElementById('nexo-emoji-picker');
        if(p)p.remove();
    },

    async sendReaction(msgId, providerMsgId, emoji){
        this.closeEmojiPicker();
        if(!this.conversaAtual)return;
        try{
            await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/reaction`,{method:'POST',body:{provider_message_id:providerMsgId,emoji}});
        }catch(e){console.error('Reaction error:',e)}
    },

    // ═══ PRIORIDADE ═══
    updatePrioBadge(p){
        const b=document.getElementById('chat-priority-badge');
        const l={normal:'⚪ Normal',alta:'🟡 Alta',urgente:'🟠 Urgente',critica:'🔴 Crítica'};
        b.textContent=l[p]||l.normal;b.className=`text-[10px] px-2 py-0.5 rounded-full font-medium cursor-pointer priority-${p}`;
    },
    async cyclePriority(){
        if(!this.conversaAtual)return;
        const cur=this.conversaAtual.priority||'normal';
        const nxt=this.priorityLevels[(this.priorityLevels.indexOf(cur)+1)%this.priorityLevels.length];
        try{await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/priority`,{method:'PATCH',body:{priority:nxt}});this.conversaAtual.priority=nxt;this.updatePrioBadge(nxt);this.loadConversas(true)}catch(e){console.error('Priority error:',e)}
    },

    // ═══ NOTAS ═══
    async loadNotes(){
        if(!this.conversaAtual)return;
        const list=document.getElementById('notes-list');
        list.innerHTML='<div class="flex justify-center py-4"><div class="w-4 h-4 border-2 border-[#1e3a5f] border-t-transparent rounded-full animate-spin"></div></div>';
        try{
            const j=await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/notes`);
            const notes=j.notes||[];
            if(!notes.length){list.innerHTML='<p class="text-xs text-gray-400 text-center py-4">Nenhuma nota ainda</p>';return}
            list.innerHTML=notes.map(n=>{
                const d=new Date(n.created_at).toLocaleString('pt-BR',{day:'2-digit',month:'2-digit',hour:'2-digit',minute:'2-digit'});
                return `<div class="bg-amber-50 border border-amber-200 rounded-lg p-2.5 text-xs"><div class="flex justify-between items-start mb-1"><span class="font-medium text-amber-800">${this.esc(n.user?.name||'Sistema')}</span><div class="flex items-center gap-1.5"><span class="text-[10px] text-amber-600">${d}</span><button onclick="NexoApp.deleteNote(${n.id})" class="text-red-400 hover:text-red-600" title="Excluir">✕</button></div></div><p class="text-gray-700 whitespace-pre-wrap">${this.esc(n.content)}</p></div>`;
            }).join('');
        }catch(e){list.innerHTML='<p class="text-xs text-red-500 text-center py-4">Erro ao carregar notas</p>'}
    },
    async saveNote(){
        if(!this.conversaAtual)return;const inp=document.getElementById('note-input'),ct=inp.value.trim();if(!ct)return;
        try{const r=await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/notes`,{method:'POST',body:{content:ct}});if(r.success){inp.value='';this.loadNotes()}}catch(e){console.error('Note save error:',e)}
    },
    async deleteNote(nid){
        if(!this.conversaAtual||!confirm('Excluir esta nota?'))return;
        try{await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/notes/${nid}`,{method:'DELETE'});this.loadNotes()}catch(e){console.error('Note delete error:',e)}
    },

    // ═══ FLOWS ═══
    async loadFlows(){
        if(!this.conversaAtual)return;
        const ld=document.getElementById('flows-loading'),ls=document.getElementById('flows-list'),em=document.getElementById('flows-empty');
        ld.classList.remove('hidden');ls.classList.add('hidden');em.classList.add('hidden');
        try{
            if(!this.flowsCache){const j=await this.api('/nexo/atendimento/flows');this.flowsCache=j.flows||[]}
            ld.classList.add('hidden');
            if(!this.flowsCache.length){em.classList.remove('hidden');return}
            ls.classList.remove('hidden');
            ls.innerHTML=this.flowsCache.map(f=>{
                const nm=this.esc(f.name||f.title||'Flow');const st=f.status==='active'?'🟢':'⚪';const fid=f.id||f.flow_id||'';
                return `<div class="border border-gray-200 rounded-xl p-3.5 hover:border-[#1e3a5f]/30 transition-colors"><div class="flex items-center justify-between gap-3"><div class="flex items-center gap-2.5 min-w-0"><span class="text-base">${st}</span><span class="text-[13px] font-semibold text-gray-800 truncate">${nm}</span></div><button onclick="NexoApp.triggerFlow('${fid}','${nm}')" class="text-[12px] px-4 py-2 bg-white text-[#1e3a5f] border-2 border-[#1e3a5f] rounded-lg hover:bg-[#1e3a5f] hover:text-white font-semibold shadow-sm flex-shrink-0 transition-colors">Executar</button></div></div>`;
            }).join('');
        }catch(e){ld.classList.add('hidden');ls.classList.remove('hidden');ls.innerHTML='<p class="text-xs text-red-500 text-center py-4">Erro ao carregar flows</p>'}
    },
    async triggerFlow(fid,fname){
        if(!this.conversaAtual||!fid)return;if(!confirm(`Executar flow "${fname}"?`))return;
        try{const r=await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/run-flow`,{method:'POST',body:{flow_id:fid}});alert(r.success?'Flow executado!':'Erro: '+(r.error||'Falha'))}catch(e){alert('Erro ao executar flow')}
    },

    // ═══ CONTEXTO 360 ═══
    async loadContexto(cid){
        const em=document.getElementById('contexto-empty'),ld=document.getElementById('contexto-loading'),dt=document.getElementById('contexto-data'),lk=document.getElementById('contexto-link-actions');
        em.classList.add('hidden');ld.classList.remove('hidden');dt.classList.add('hidden');lk.classList.add('hidden');
        document.getElementById('contexto-panel').classList.remove('hidden');document.getElementById('contexto-panel').classList.add('xl:flex');
        try{
            const j=await this.api(`/nexo/atendimento/conversas/${cid}/contexto`);
            let html='';
            const tc={lead:{bg:'bg-blue-100',tx:'text-blue-700',lb:'Lead'},cliente:{bg:'bg-purple-100',tx:'text-purple-700',lb:'Cliente'},indefinido:{bg:'bg-gray-100',tx:'text-gray-600',lb:'Não vinculado'}};const catLabels={ADV:'Adv.Adverso',PERITO:'Perito',CORRESP:'Corresp.',FORN:'Fornecedor',OUTRO:'Outro'};
            const t=tc[j.link_type]||tc.indefinido;
            html+=`<div><span class="text-xs px-2.5 py-1 rounded-full ${t.bg} ${t.tx} font-medium">${t.lb}</span></div>`;

            // ── Category Select ──
            const cats = [
                {v:'',l:'Sem categoria'},
                {v:'CLI',l:'Cliente',c:'bg-purple-100 text-purple-700'},
                {v:'LEAD',l:'Lead',c:'bg-blue-100 text-blue-700'},
                {v:'ADV',l:'Adv. Adverso',c:'bg-red-100 text-red-700'},
                {v:'PERITO',l:'Perito',c:'bg-amber-100 text-amber-700'},
                {v:'CORRESP',l:'Correspondente',c:'bg-teal-100 text-teal-700'},
                {v:'FORN',l:'Fornecedor',c:'bg-indigo-100 text-indigo-700'},
                {v:'OUTRO',l:'Outro',c:'bg-gray-100 text-gray-600'},
            ];
            const curCat = this.conversaAtual?.category || j.category || '';
            html+=`<div class="mt-2"><label class="text-[10px] text-gray-500 font-medium">Categoria</label><select id="nexo-category-select" onchange="NexoApp.updateCategory(this.value)" class="mt-0.5 w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[#1e3a5f]">`;
            cats.forEach(c=>{html+=`<option value="${c.v}" ${c.v===curCat?'selected':''}>${c.l}</option>`});
            html+=`</select></div>`;

            // ── Tags Chips ──
            html+=`<div class="mt-2" id="nexo-tags-section"><label class="text-[10px] text-gray-500 font-medium">Tags</label><div id="nexo-tags-chips" class="flex flex-wrap gap-1 mt-1"><span class="text-[10px] text-gray-400 italic">Carregando...</span></div><div class="mt-1.5 flex gap-1"><select id="nexo-tag-add-select" class="flex-1 text-xs border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-[#1e3a5f]"><option value="">+ Adicionar tag...</option></select><button onclick="NexoApp.addTagFromSelect()" class="text-xs px-2 py-1 bg-emerald-50 text-emerald-700 rounded hover:bg-emerald-100 border border-emerald-200">+</button></div></div>`;

            if(j.lead){const l=j.lead;
                html+=`<div class="ctx-section"><div class="ctx-section-header" onclick="this.nextElementSibling.classList.toggle('hidden')"><span class="text-xs font-semibold text-gray-700">📋 Lead — ${this.esc(l.nome||'N/A')}</span><span class="text-gray-400 text-xs">▼</span></div><div class="ctx-section-body">`;
                const rows=[['Área',l.area_interesse],['Sub-área',l.sub_area],['Intenção',l.intencao_contratar],['Urgência',l.urgencia],['Complexidade',l.complexidade],['Potencial',l.potencial_honorarios],['Gatilho',l.gatilho_emocional],['Objeções',l.objecoes]];
                rows.forEach(([k,v])=>{html+=`<div class="ctx-row"><span class="ctx-label">${k}</span><span class="ctx-value">${this.esc(v||'N/A')}</span></div>`});
                if(l.resumo_demanda)html+=`<div class="mt-2 pt-2 border-t border-gray-100"><p class="text-[10px] text-gray-500 font-medium mb-1">Resumo</p><p class="text-xs text-gray-700 leading-relaxed">${this.esc(l.resumo_demanda)}</p></div>`;
                if(l.palavras_chave)html+=`<div class="mt-2 pt-2 border-t border-gray-100"><p class="text-[10px] text-gray-500 font-medium mb-1">Tags</p><div class="flex flex-wrap gap-1">${l.palavras_chave.split(',').map(k=>`<span class="text-[10px] px-1.5 py-0.5 bg-emerald-50 text-emerald-700 rounded">${this.esc(k.trim())}</span>`).join('')}</div></div>`;
                if(l.intencao_justificativa)html+=`<div class="mt-2 pt-2 border-t border-gray-100"><p class="text-[10px] text-gray-500 font-medium mb-1">IA</p><p class="text-xs text-gray-600 italic">${this.esc(l.intencao_justificativa)}</p></div>`;
                html+=`</div></div>`;
                // CRM Account + Oportunidades
                if(l.crm_account){
                    const a=l.crm_account;
                    html+=`<div class="ctx-section"><div class="ctx-section-header" onclick="this.nextElementSibling.classList.toggle('hidden')"><span class="text-xs font-semibold text-gray-700">\xf0\x9f\x8f\xa2 CRM \u2014 ${this.esc(a.name)}</span><span class="text-gray-400 text-xs">\u25bc</span></div><div class="ctx-section-body">`;
                    html+=`<div class="ctx-row"><span class="ctx-label">Status</span><span class="ctx-value">${this.esc(a.lifecycle||'N/A')}</span></div>`;
                    if(a.opportunities&&a.opportunities.length){
                        html+=`<p class="text-[10px] text-gray-500 font-medium mt-2 mb-1">Oportunidades (${a.opportunities.length})</p>`;
                        a.opportunities.forEach(o=>{
                            const sc={open:'bg-blue-100 text-blue-700',won:'bg-green-100 text-green-700',lost:'bg-red-100 text-red-700'};
                            const badge=sc[o.status]||'bg-gray-100 text-gray-600';
                            html+=`<div class="flex items-center justify-between py-1"><span class="text-xs text-gray-700 truncate flex-1">${this.esc(o.title||'Oportunidade')}</span><span class="text-[10px] px-1.5 py-0.5 rounded-full ${badge} ml-1">${o.status}</span></div>`;
                            if(o.value_estimated)html+=`<div class="text-[10px] text-gray-400 ml-2">R$ ${Number(o.value_estimated).toLocaleString('pt-BR')}</div>`;
                        });
                    }
                    html+=`</div></div>`;
                } else if(l.id && !l.crm_account_id){
                    html+=`<div class="mt-3 pt-2 border-t border-gray-100"><button onclick="NexoApp.promoverLeadCrm(${l.id})" class="w-full text-xs px-3 py-2 bg-[#385776] text-white rounded-lg hover:bg-[#1B334A] transition font-medium">Promover para CRM</button></div>`;
                }
                if(l.id){
                    html+=`<div class="mt-2 pt-2 border-t border-gray-100"><button onclick="NexoApp.abrirSipex(${l.id})" class="w-full text-xs px-3 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition font-medium">💰 Cotar no SIPEX</button></div>`;
                }
            }

            if(j.cliente){const cl=j.cliente;
                html+=`<div class="ctx-section"><div class="ctx-section-header" onclick="this.nextElementSibling.classList.toggle('hidden')"><span class="text-xs font-semibold text-gray-700">👤 Cliente — ${this.esc(cl.nome||'N/A')}</span><span class="text-gray-400 text-xs">▼</span></div><div class="ctx-section-body">`;
                html+=`<div class="ctx-row"><span class="ctx-label">Tipo</span><span class="ctx-value">${cl.tipo||cl.tipo_pessoa||'N/A'}</span></div>`;
                html+=`<div class="ctx-row"><span class="ctx-label">Doc</span><span class="ctx-value">${this.esc(cl.cpf_cnpj||cl.documento||'N/A')}</span></div>`;
                if(cl.processos_ativos?.length){html+=`<div class="mt-2 pt-2 border-t border-gray-100"><p class="text-[10px] text-gray-500 font-medium mb-1">Processos (${cl.processos_ativos.length})</p>`;cl.processos_ativos.forEach(p=>{html+=`<div class="text-xs text-gray-600 py-0.5 truncate">• ${this.esc(p.numero||p.titulo||'Processo')}</div>`});html+=`</div>`}
                if(cl.contas_abertas?.length){html+=`<div class="mt-2 pt-2 border-t border-gray-100"><p class="text-[10px] text-gray-500 font-medium mb-1">Contas (${cl.contas_abertas.length})</p>`;cl.contas_abertas.forEach(ct=>{html+=`<div class="text-xs text-gray-600 py-0.5">• R$ ${this.esc(ct.valor||'0')} — ${this.esc(ct.descricao||'')}</div>`});html+=`</div>`}
                html+=`</div></div>`;
            }

            if(j.link_type==='indefinido')lk.classList.remove('hidden');else{lk.classList.remove('hidden')}
            this.updateUnlinkButtons(j);
            dt.innerHTML=html;dt.classList.remove('hidden');
        }catch(e){console.error('Ctx error:',e);dt.innerHTML='<p class="text-sm text-red-500 text-center py-4">Erro ao carregar</p>';dt.classList.remove('hidden')}
        finally{ld.classList.add('hidden');this.loadTags()}
    },

    // ═══ AÇÕES ═══
    abrirSipex(leadId){
        window.open('/precificacao?lead_id=' + leadId, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
    },

    async promoverLeadCrm(leadId){
        if(!confirm('Promover este lead para o CRM? Sera criada uma conta prospect + oportunidade.'))return;
        try{
            const r=await this.api(`/nexo/atendimento/leads/${leadId}/promover-crm`,{method:'POST',body:{}});
            if(r.success){
                alert('Lead promovido! Account #'+r.account_id+', Oportunidade #'+r.opportunity_id);
                this.loadContexto(this.conversaAtual.id);
            }else{
                alert('Erro: '+(r.error||'Falha ao promover'));
            }
        }catch(e){console.error(e);alert('Erro ao promover lead')}
    },
    async assignResponsavel(uid){if(!this.conversaAtual)return;try{await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/assign`,{method:'PATCH',body:{user_id:uid||null}});this.loadConversas(true)}catch(e){console.error(e)}},
    async toggleStatus(){if(!this.conversaAtual)return;const ns=this.conversaAtual.status==='open'?'closed':'open';try{await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/status`,{method:'PATCH',body:{status:ns}});this.selectConversa(this.conversaAtual.id)}catch(e){console.error(e)}},

    // ═══ CATEGORY + TAGS ═══
    async updateCategory(val){
        if(!this.conversaAtual)return;
        try{
            await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/category`,{method:'PATCH',body:{category:val||null}});
            this.conversaAtual.category=val||null;
            this.loadConversas(true);
        }catch(e){console.error('updateCategory error:',e)}
    },

    async loadTags(){
        if(!this.conversaAtual)return;
        try{
            const data=await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/tags`);
            const chips=document.getElementById('nexo-tags-chips');
            const sel=document.getElementById('nexo-tag-add-select');
            if(!chips||!sel)return;

            const assigned=data.tags||data.assigned||[];
            const available=data.all_tags||data.available||[];

            if(assigned.length===0){
                chips.innerHTML='<span class="text-[10px] text-gray-400 italic">Nenhuma tag</span>';
            }else{
                chips.innerHTML=assigned.map(t=>`<span class="inline-flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-full font-medium" style="background:${t.color||'#6b7280'}22;color:${t.color||'#6b7280'}">${this.esc(t.name)}<button onclick="NexoApp.removeTag(${t.id})" class="ml-0.5 hover:opacity-70">&times;</button></span>`).join('');
            }

            sel.innerHTML='<option value="">+ Adicionar tag...</option>';
            available.filter(t=>!assigned.find(a=>a.id===t.id)).forEach(t=>{
                sel.innerHTML+=`<option value="${t.id}">${this.esc(t.name)}</option>`;
            });
        }catch(e){console.error('loadTags error:',e);const chips=document.getElementById('nexo-tags-chips');if(chips)chips.innerHTML='<span class="text-[10px] text-red-400">Erro ao carregar tags</span>'}
    },

    async addTagFromSelect(){
        const sel=document.getElementById('nexo-tag-add-select');
        if(!sel||!sel.value)return;
        await this.addTag(parseInt(sel.value));
    },

    async addTag(tagId){
        if(!this.conversaAtual||!tagId)return;
        try{
            const data=await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/tags`);
            const currentIds=(data.tags||[]).map(t=>t.id);
            if(!currentIds.includes(tagId))currentIds.push(tagId);
            await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/tags`,{method:'PATCH',body:{tag_ids:currentIds}});
            this.loadTags();
        }catch(e){console.error('addTag error:',e)}
    },

    async removeTag(tagId){
        if(!this.conversaAtual||!tagId)return;
        try{
            const data=await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/tags`);
            const currentIds=(data.tags||[]).map(t=>t.id).filter(id=>id!==tagId);
            await this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/tags`,{method:'PATCH',body:{tag_ids:currentIds}});
            this.loadTags();
        }catch(e){console.error('removeTag error:',e)}
    },
    _searchTimer:null,
    searchLink(type, q){
        clearTimeout(this._searchTimer);
        const res=document.getElementById(`link-${type}-results`);
        const btn=document.getElementById(`btn-link-${type}`);
        if(q.length<2){res.classList.add('hidden');btn.classList.add('opacity-50','pointer-events-none');return}
        this._searchTimer=setTimeout(async()=>{
            try{
                const j=await this.api(`/nexo/atendimento/search-contacts?q=${encodeURIComponent(q)}`);
                const items=type==='lead'?j.leads:j.clientes;
                if(!items||items.length===0){res.innerHTML='<div class="px-3 py-2 text-[11px] text-gray-400">Nenhum resultado</div>';res.classList.remove('hidden');return}
                res.innerHTML=items.map(i=>`<div class="px-3 py-2 hover:bg-gray-50 cursor-pointer text-[12px] border-b border-gray-50" onclick="NexoApp.selectLink('${type}',${i.id},'${this.esc(i.nome||i.name||"")}')"><span class="font-medium">${this.esc(i.nome||i.name||'')}</span> <span class="text-gray-400">#${i.id}</span>${i.telefone?' <span class="text-gray-400">'+this.esc(i.telefone)+'</span>':''}</div>`).join('');
                res.classList.remove('hidden');
            }catch(e){console.error(e)}
        },300);
    },
    selectLink(type,id,nome){
        document.getElementById(`link-${type}-id-hidden`).value=id;
        document.getElementById(`link-${type}-search`).value=nome+' #'+id;
        document.getElementById(`link-${type}-results`).classList.add('hidden');
        const btn=document.getElementById(`btn-link-${type}`);
        btn.classList.remove('opacity-50','pointer-events-none');
    },
    linkLead(id){if(!id||!this.conversaAtual)return;this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/link-lead`,{method:'POST',body:{lead_id:parseInt(id)}}).then(()=>{this.loadContexto(this.conversaAtual.id);this.loadConversas(true)}).catch(e=>console.error(e))},
    linkCliente(id){if(!id||!this.conversaAtual)return;this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/link-cliente`,{method:'POST',body:{cliente_id:parseInt(id)}}).then(()=>{this.loadContexto(this.conversaAtual.id);this.loadConversas(true)}).catch(e=>console.error(e))},
    unlinkLead(){if(!this.conversaAtual)return;if(!confirm('Remover vinculação com este Lead?'))return;this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/unlink-lead`,{method:'DELETE'}).then(()=>{this.loadContexto(this.conversaAtual.id);this.loadConversas(true)}).catch(e=>console.error(e))},
    unlinkCliente(){if(!this.conversaAtual)return;if(!confirm('Remover vinculação com este Cliente? O processo vinculado também será removido.'))return;this.api(`/nexo/atendimento/conversas/${this.conversaAtual.id}/unlink-cliente`,{method:'DELETE'}).then(()=>{this.loadContexto(this.conversaAtual.id);this.loadConversas(true);if(typeof NexoDJ!=='undefined')NexoDJ.init(this.conversaAtual.id,null,null)}).catch(e=>console.error(e))},
    updateUnlinkButtons(ctx){const ua=document.getElementById('unlink-actions');const bl=document.getElementById('btn-unlink-lead');const bc=document.getElementById('btn-unlink-cliente');if(!ua)return;let show=false;if(bl){if(ctx&&ctx.lead){bl.classList.remove('hidden');show=true}else{bl.classList.add('hidden')}}if(bc){if(ctx&&ctx.cliente){bc.classList.remove('hidden');show=true}else{bc.classList.add('hidden')}}if(show){ua.classList.remove('hidden')}else{ua.classList.add('hidden')}},


    // ═══ CONTEXT MENU ═══
    showContextMenu(e,convId,isUnread){
        e.preventDefault();
        this.hideContextMenu();
        const menu=document.createElement('div');
        menu.id='nexo-context-menu';
        menu.className='nexo-context-menu';
        menu.innerHTML=`
            <div class="nexo-context-menu-item" onclick="NexoApp.toggleMarkedUnread(${convId},${isUnread})">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isUnread?'M5 13l4 4L19 7':'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'}"/></svg>
                <span>${isUnread?'Marcar como lida':'Marcar como não lida'}</span>
            </div>
        `;
        menu.style.left=e.clientX+'px';
        menu.style.top=e.clientY+'px';
        document.body.appendChild(menu);
        // Ajustar se sair da tela
        const rect=menu.getBoundingClientRect();
        if(rect.right>window.innerWidth)menu.style.left=(window.innerWidth-rect.width-10)+'px';
        if(rect.bottom>window.innerHeight)menu.style.top=(window.innerHeight-rect.height-10)+'px';
        setTimeout(()=>document.addEventListener('click',this.hideContextMenu,{once:true}),10);
    },
    hideContextMenu(){
        const m=document.getElementById('nexo-context-menu');
        if(m)m.remove();
    },
    async toggleMarkedUnread(convId,currentlyUnread){
        this.hideContextMenu();
        try{
            await this.api(`/nexo/atendimento/conversas/${convId}/marked-unread`,{method:'PATCH'});
            this.loadConversas(true);
        }catch(e){console.error('toggleMarkedUnread error:',e)}
    },

    voltarInbox(){document.getElementById('inbox-panel').classList.remove('hidden');document.getElementById('chat-panel').classList.add('hidden');document.getElementById('chat-panel').classList.remove('flex')},
    toggleContexto(){
        const p=document.getElementById('contexto-panel');
        if(p.classList.contains('hidden')){p.classList.remove('hidden');p.classList.add('flex');if(window.innerWidth<1280){p.style.cssText='position:fixed;right:0;top:64px;bottom:0;z-index:50;width:320px;box-shadow:-4px 0 12px rgba(0,0,0,.1)'}}
        else{p.classList.add('hidden');p.classList.remove('flex');p.removeAttribute('style')}
    },

    // ═══ UTILS ═══
    esc(s){if(!s)return'';const d=document.createElement('div');d.textContent=s;return d.innerHTML},
    fmtPhone(p){if(!p)return'';const c=p.replace(/\D/g,'');if(c.length===13)return`+${c.slice(0,2)} (${c.slice(2,4)}) ${c.slice(4,9)}-${c.slice(9)}`;if(c.length===12)return`+${c.slice(0,2)} (${c.slice(2,4)}) ${c.slice(4,8)}-${c.slice(8)}`;return p},
    fmtDate(ds){const d=new Date(ds+'T00:00:00'),t=new Date(),y=new Date(t);y.setDate(y.getDate()-1);if(d.toDateString()===t.toDateString())return'Hoje';if(d.toDateString()===y.toDateString())return'Ontem';return d.toLocaleDateString('pt-BR',{day:'2-digit',month:'2-digit',year:'numeric'})},
    timeAgo(ds){const d=new Date(ds),dm=Math.floor((new Date()-d)/60000);if(dm<1)return'agora';if(dm<60)return dm+'m';if(dm<1440)return Math.floor(dm/60)+'h';return Math.floor(dm/1440)+'d'}
};

document.addEventListener('DOMContentLoaded',()=>NexoApp.init());
</script>
