{{-- 
    SNIPPET SIDEBAR CRM V2
    Inserir dentro do menu lateral em layouts/app.blade.php
    Após o último item de menu existente (ex: SIPEX ou Manuais)
--}}

{{-- CRM --}}
<div x-data="{ open: {{ request()->is('crm/*') ? 'true' : 'false' }} }" class="mt-1">
    <button @click="open = !open" class="flex items-center justify-between w-full px-3 py-2 text-sm rounded-lg hover:bg-[#385776]/10 transition
        {{ request()->is('crm/*') ? 'bg-[#385776]/10 text-[#1B334A] font-semibold' : 'text-gray-600' }}">
        <span class="flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            CRM
        </span>
        <svg class="w-3 h-3 transition-transform" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>
    <div x-show="open" x-collapse class="ml-6 mt-1 space-y-1">
        <a href="{{ route('crm.carteira') }}" class="block px-3 py-1.5 text-sm rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.carteira') ? 'text-[#385776] font-medium' : 'text-gray-500' }}">Carteira</a>
        <a href="{{ route('crm.pipeline') }}" class="block px-3 py-1.5 text-sm rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.pipeline') ? 'text-[#385776] font-medium' : 'text-gray-500' }}">Pipeline</a>
        <a href="{{ route('crm.reports') }}" class="block px-3 py-1.5 text-sm rounded-lg hover:bg-gray-100 {{ request()->routeIs('crm.reports') ? 'text-[#385776] font-medium' : 'text-gray-500' }}">Relatórios</a>
    </div>
</div>
