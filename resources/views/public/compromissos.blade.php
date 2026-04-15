@extends('layouts.public')

@section('title', 'Compromissos — Mayer Advogados')

@section('content')

<div class="mb-5">
    <h1 class="text-lg font-bold text-gray-800">Seus compromissos</h1>
    <p class="text-sm text-gray-500 mt-0.5">Audiências, prazos e movimentos relevantes encontrados.</p>
</div>

@if(($total ?? 0) > 0)

<div class="space-y-3 mb-5">
    @foreach(range(1, min($total, 5)) as $n)
        @php
            $processo = ${'comp'.$n.'_processo'}  ?? null;
            $data     = ${'comp'.$n.'_data'}       ?? null;
            $hora     = ${'comp'.$n.'_hora'}       ?? null;
            $desc     = ${'comp'.$n.'_desc'}       ?? null;
            $tipo     = ${'comp'.$n.'_tipo'}       ?? null;
            if (!$processo) continue;

            $tipoIcon = match(strtolower($tipo ?? '')) {
                'audiência', 'audiencia', 'pauta', 'sessão', 'sessao', 'julgamento', 'sustentação', 'sustentacao' => '⚖️',
                'perícia', 'pericia'     => '🔬',
                'prazo', 'liminar'       => '⏰',
                'intimação', 'intimacao' => '📬',
                default                  => '📅',
            };
            $tipoColor = match(strtolower($tipo ?? '')) {
                'audiência', 'audiencia', 'pauta', 'sessão', 'sessao', 'julgamento', 'sustentação', 'sustentacao' => 'bg-blue-100 text-blue-700',
                'perícia', 'pericia'     => 'bg-purple-100 text-purple-700',
                'prazo', 'liminar'       => 'bg-red-100 text-red-700',
                'intimação', 'intimacao' => 'bg-amber-100 text-amber-700',
                default                  => 'bg-gray-100 text-gray-600',
            };
        @endphp
        <div class="card">
            <div class="flex items-start gap-4">
                {{-- Data em destaque --}}
                <div class="shrink-0 text-center bg-navy-50 rounded-xl px-3 py-2 min-w-[56px]">
                    @php
                        $parts = explode('/', $data ?? '');
                        $dia = $parts[0] ?? '--';
                        $mes = match((int)($parts[1] ?? 0)) {
                            1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',
                            7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez',default=>'---'
                        };
                    @endphp
                    <p class="text-xl font-bold text-navy-700 leading-none">{{ $dia }}</p>
                    <p class="text-xs font-medium text-navy-500 mt-0.5">{{ $mes }}</p>
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="badge {{ $tipoColor }}">{{ $tipoIcon }} {{ $tipo }}</span>
                        @if($hora)
                            <span class="text-xs text-gray-400">{{ $hora }}</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-500 mb-1">Processo: <span class="font-medium text-gray-700">{{ $processo }}</span></p>
                    @if($desc)
                        <p class="text-sm text-gray-700 leading-snug line-clamp-2">{{ $desc }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endforeach
</div>

@else
<div class="card text-center py-12">
    <div class="text-4xl mb-3">📅</div>
    <p class="text-gray-500 text-sm">Nenhum compromisso identificado no momento.</p>
    <p class="text-xs text-gray-400 mt-1">Se tiver dúvidas sobre datas, fale com o escritório.</p>
</div>
@endif

<div class="card bg-blue-50 border-blue-100 text-center">
    <p class="text-sm text-blue-800 font-medium mb-2">Dúvidas sobre algum compromisso?</p>
    <a href="{{ $whatsappUrl }}" target="_blank"
       class="inline-flex items-center gap-1.5 bg-green-500 text-white text-xs font-semibold px-4 py-2 rounded-full hover:bg-green-600 transition-all">
        Falar com o escritório
    </a>
</div>

<p class="text-xs text-gray-400 text-center mt-5">Dados consultados em {{ $consultadoEm }}</p>

@endsection
