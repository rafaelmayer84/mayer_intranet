{{-- resources/views/leads/partials/tracking-info.blade.php --}}
{{-- Seção de Rastreamento de Origem — incluir no show.blade.php --}}

@php
    $hasTracking = $lead->gclid || $lead->utm_source || $lead->utm_medium || $lead->utm_campaign || $lead->fbclid || $lead->landing_page || $lead->referrer_url;
@endphp

@if($hasTracking)
<div class="bg-white rounded-lg shadow p-6 mt-6">
    <h3 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
        <svg class="w-5 h-5 mr-2 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
        </svg>
        Rastreamento de Origem
    </h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Origem identificada --}}
        @if($lead->origem_canal)
        <div class="col-span-2">
            <span class="text-xs font-medium text-gray-500 uppercase">Origem do Canal</span>
            <div class="mt-1">
                @php
                    $origemColors = [
                        'google_ads' => 'bg-blue-100 text-blue-800',
                        'facebook_ads' => 'bg-indigo-100 text-indigo-800',
                        'instagram_ads' => 'bg-pink-100 text-pink-800',
                        'organico' => 'bg-green-100 text-green-800',
                        'redes_sociais' => 'bg-purple-100 text-purple-800',
                        'indicacao' => 'bg-yellow-100 text-yellow-800',
                        'acesso_direto' => 'bg-gray-100 text-gray-800',
                        'trafego_pago' => 'bg-orange-100 text-orange-800',
                    ];
                    $origemLabels = [
                        'google_ads' => '🔵 Google Ads',
                        'facebook_ads' => '🟣 Facebook Ads',
                        'instagram_ads' => '📸 Instagram Ads',
                        'organico' => '🟢 Busca Orgânica',
                        'redes_sociais' => '🌐 Redes Sociais',
                        'indicacao' => '🤝 Indicação',
                        'acesso_direto' => '➡️ Acesso Direto',
                        'trafego_pago' => '💰 Tráfego Pago',
                        'desconhecido' => '❓ Desconhecido',
                    ];
                    $color = $origemColors[$lead->origem_canal] ?? 'bg-gray-100 text-gray-800';
                    $label = $origemLabels[$lead->origem_canal] ?? ucfirst($lead->origem_canal);
                @endphp
                <span class="inline-flex items-center px-3 py-1.5 rounded-full text-sm font-semibold {{ $color }}">
                    {{ $label }}
                </span>
            </div>
        </div>
        @endif

        {{-- GCLID --}}
        @if($lead->gclid)
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase">GCLID (Google Click ID)</span>
            <p class="mt-1 text-sm text-gray-900 font-mono break-all">{{ Str::limit($lead->gclid, 60) }}</p>
        </div>
        @endif

        {{-- FBCLID --}}
        @if($lead->fbclid)
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase">FBCLID (Facebook Click ID)</span>
            <p class="mt-1 text-sm text-gray-900 font-mono break-all">{{ Str::limit($lead->fbclid, 60) }}</p>
        </div>
        @endif

        {{-- UTM Source --}}
        @if($lead->utm_source)
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase">UTM Source</span>
            <p class="mt-1 text-sm text-gray-900">{{ $lead->utm_source }}</p>
        </div>
        @endif

        {{-- UTM Medium --}}
        @if($lead->utm_medium)
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase">UTM Medium</span>
            <p class="mt-1 text-sm text-gray-900">{{ $lead->utm_medium }}</p>
        </div>
        @endif

        {{-- UTM Campaign --}}
        @if($lead->utm_campaign)
        <div class="col-span-2">
            <span class="text-xs font-medium text-gray-500 uppercase">UTM Campaign</span>
            <p class="mt-1 text-sm text-gray-900 font-medium">{{ $lead->utm_campaign }}</p>
        </div>
        @endif

        {{-- UTM Content --}}
        @if($lead->utm_content)
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase">UTM Content</span>
            <p class="mt-1 text-sm text-gray-900">{{ $lead->utm_content }}</p>
        </div>
        @endif

        {{-- UTM Term --}}
        @if($lead->utm_term)
        <div>
            <span class="text-xs font-medium text-gray-500 uppercase">UTM Term (Palavra-chave)</span>
            <p class="mt-1 text-sm text-gray-900 font-medium text-blue-700">{{ $lead->utm_term }}</p>
        </div>
        @endif

        {{-- Landing Page --}}
        @if($lead->landing_page)
        <div class="col-span-2">
            <span class="text-xs font-medium text-gray-500 uppercase">Página de Entrada</span>
            <p class="mt-1 text-sm text-blue-600 break-all">
                <a href="{{ $lead->landing_page }}" target="_blank" class="hover:underline">{{ Str::limit($lead->landing_page, 100) }}</a>
            </p>
        </div>
        @endif

        {{-- Referrer --}}
        @if($lead->referrer_url)
        <div class="col-span-2">
            <span class="text-xs font-medium text-gray-500 uppercase">Referrer (veio de)</span>
            <p class="mt-1 text-sm text-gray-600 break-all">{{ Str::limit($lead->referrer_url, 100) }}</p>
        </div>
        @endif
    </div>
</div>
@endif
