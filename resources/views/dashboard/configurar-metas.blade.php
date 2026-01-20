@extends('layouts.app')

@section('title', 'Configurar Metas')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-3xl font-bold text-white">Configurar Metas</h1>
            <p class="text-gray-400 mt-1">Defina as metas mensais para {{ $ano }}</p>
        </div>
        <div class="flex items-center space-x-4">
            <select id="ano-filter" class="bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                @for($y = 2020; $y <= date('Y') + 1; $y++)
                    <option value="{{ $y }}" {{ $ano == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-500/20 border border-green-500 text-green-400 px-4 py-3 rounded-lg">
        {{ session('success') }}
    </div>
    @endif

    <!-- Meta PF -->
    <form id="form-pf" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        @csrf
        @method('PUT')
        <input type="hidden" name="ano" value="{{ $ano }}">
        <input type="hidden" name="tipo" value="pf">
        
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
            </svg>
            Meta Receita PF (Pessoa Física)
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach(['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'] as $index => $mes)
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">{{ $mes }}</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">R$</span>
                    <input type="number" step="0.01" name="meta_pf[{{ $index + 1 }}]" 
                           value="{{ $metas['pf'][$index + 1] ?? 0 }}"
                           class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-4 pt-4 border-t border-gray-700 flex justify-between items-center">
            <span class="text-gray-400">Total Anual:</span>
            <span class="text-xl font-bold text-white" id="total-pf">R$ 0,00</span>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" class="btn-salvar px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Meta PF
            </button>
        </div>
    </form>

    <!-- Meta PJ -->
    <form id="form-pj" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        @csrf
        @method('PUT')
        <input type="hidden" name="ano" value="{{ $ano }}">
        <input type="hidden" name="tipo" value="pj">
        
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            Meta Receita PJ (Pessoa Jurídica)
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach(['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'] as $index => $mes)
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">{{ $mes }}</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">R$</span>
                    <input type="number" step="0.01" name="meta_pj[{{ $index + 1 }}]" 
                           value="{{ $metas['pj'][$index + 1] ?? 0 }}"
                           class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-4 pt-4 border-t border-gray-700 flex justify-between items-center">
            <span class="text-gray-400">Total Anual:</span>
            <span class="text-xl font-bold text-white" id="total-pj">R$ 0,00</span>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" class="btn-salvar px-4 py-2 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Meta PJ
            </button>
        </div>
    </form>

    <!-- Meta Despesas -->
    <form id="form-despesas" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        @csrf
        @method('PUT')
        <input type="hidden" name="ano" value="{{ $ano }}">
        <input type="hidden" name="tipo" value="despesas">
        
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            Meta de Despesas (Máximo)
        </h2>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            @foreach(['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'] as $index => $mes)
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">{{ $mes }}</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">R$</span>
                    <input type="number" step="0.01" name="meta_despesas[{{ $index + 1 }}]" 
                           value="{{ $metas['despesas'][$index + 1] ?? 0 }}"
                           class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            @endforeach
        </div>
        <div class="mt-4 pt-4 border-t border-gray-700 flex justify-between items-center">
            <span class="text-gray-400">Total Anual:</span>
            <span class="text-xl font-bold text-white" id="total-despesas">R$ 0,00</span>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" class="btn-salvar px-4 py-2 bg-red-600 hover:bg-red-700 text-white font-medium rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Meta Despesas
            </button>
        </div>
    </form>

    <!-- Metas Anuais -->
    <form id="form-anuais" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        @csrf
        @method('PUT')
        <input type="hidden" name="ano" value="{{ $ano }}">

    {{-- Metas Mensais - Resultado --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Resultado (mensal)</h2>
            <button type="submit" form="form-resultado" class="btn btn-sm btn-primary">Salvar</button>
        </div>
        <div class="card-body">
            <form id="form-resultado" action="{{ route('configurar-metas.update') }}" method="POST" class="meta-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="ano" value="{{ $ano }}">
                <input type="hidden" name="tipo" value="resultado">
                <div class="row g-3">
                    @for($mes = 1; $mes <= 12; $mes++)
                        <div class="col-6 col-md-3">
                            <label class="form-label">{{ \Carbon\Carbon::create()->month($mes)->translatedFormat('F') }}</label>
                            <input type="number" step="0.01" class="form-control"
                                   name="meta_resultado_{{ $mes }}"
                                   value="{{ $metas['resultado'][$mes] ?? 0 }}">
                        </div>
                    @endfor
                </div>
            </form>
        </div>
    </div>

    {{-- Metas Mensais - Margem (%) --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Margem (mensal) %</h2>
            <button type="submit" form="form-margem" class="btn btn-sm btn-primary">Salvar</button>
        </div>
        <div class="card-body">
            <form id="form-margem" action="{{ route('configurar-metas.update') }}" method="POST" class="meta-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="ano" value="{{ $ano }}">
                <input type="hidden" name="tipo" value="margem">
                <div class="row g-3">
                    @for($mes = 1; $mes <= 12; $mes++)
                        <div class="col-6 col-md-3">
                            <label class="form-label">{{ \Carbon\Carbon::create()->month($mes)->translatedFormat('F') }}</label>
                            <input type="number" step="0.01" class="form-control"
                                   name="meta_margem_{{ $mes }}"
                                   value="{{ $metas['margem'][$mes] ?? 0 }}">
                        </div>
                    @endfor
                </div>
            </form>
        </div>
    </div>

    {{-- Metas Mensais - Dias de Atraso --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Dias de Atraso (mensal)</h2>
            <button type="submit" form="form-dias-atraso" class="btn btn-sm btn-primary">Salvar</button>
        </div>
        <div class="card-body">
            <form id="form-dias-atraso" action="{{ route('configurar-metas.update') }}" method="POST" class="meta-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="ano" value="{{ $ano }}">
                <input type="hidden" name="tipo" value="dias_atraso">
                <div class="row g-3">
                    @for($mes = 1; $mes <= 12; $mes++)
                        <div class="col-6 col-md-3">
                            <label class="form-label">{{ \Carbon\Carbon::create()->month($mes)->translatedFormat('F') }}</label>
                            <input type="number" step="1" class="form-control"
                                   name="meta_dias_atraso_{{ $mes }}"
                                   value="{{ $metas['dias_atraso'][$mes] ?? 0 }}">
                        </div>
                    @endfor
                </div>
            </form>
        </div>
    </div>

    {{-- Metas Mensais - Taxa de Cobrança (%) --}}
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0">Taxa de Cobrança (mensal) %</h2>
            <button type="submit" form="form-taxa-cobranca" class="btn btn-sm btn-primary">Salvar</button>
        </div>
        <div class="card-body">
            <form id="form-taxa-cobranca" action="{{ route('configurar-metas.update') }}" method="POST" class="meta-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="ano" value="{{ $ano }}">
                <input type="hidden" name="tipo" value="taxa_cobranca">
                <div class="row g-3">
                    @for($mes = 1; $mes <= 12; $mes++)
                        <div class="col-6 col-md-3">
                            <label class="form-label">{{ \Carbon\Carbon::create()->month($mes)->translatedFormat('F') }}</label>
                            <input type="number" step="0.01" class="form-control"
                                   name="meta_taxa_cobranca_{{ $mes }}"
                                   value="{{ $metas['taxa_cobranca'][$mes] ?? 0 }}">
                        </div>
                    @endfor
                </div>
            </form>
        </div>
    </div>

        <input type="hidden" name="tipo" value="anuais">
        
        <h2 class="text-xl font-semibold text-white mb-4 flex items-center">
            <svg class="w-5 h-5 mr-2 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Metas Anuais
        </h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <!-- Avaliações Google -->
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Meta Avaliações Google</label>
                <input type="number" name="meta_avaliacoes" value="{{ $metas['avaliacoes'] ?? 250 }}"
                       class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Quantidade de avaliações</p>
            </div>
            
            <!-- Meta Contratos -->
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Meta de Contratos</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">R$</span>
                    <input type="number" step="0.01" name="meta_contratos" value="{{ $metas['contratos'] ?? 400000 }}"
                           class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <p class="text-xs text-gray-500 mt-1">Valor total de contratos</p>
            </div>
            
            <!-- Meta Inadimplência -->
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Limite Inadimplência</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500 text-sm">R$</span>
                    <input type="number" step="0.01" name="meta_inadimplencia" value="{{ $metas['inadimplencia'] ?? 1000 }}"
                           class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg pl-10 pr-3 py-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <p class="text-xs text-gray-500 mt-1">Valor máximo tolerado</p>
            </div>
            
            <!-- Meta Clientes -->
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Meta de Clientes</label>
                <input type="number" name="meta_clientes" value="{{ $metas['clientes'] ?? 80 }}"
                       class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-gray-500 mt-1">Quantidade de clientes ativos</p>
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" class="btn-salvar px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white font-medium rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Metas Anuais
            </button>
        </div>
    </form>
    
    <!-- Valores Atuais (Manuais) -->
    <form id="form-atuais" class="bg-gray-800 rounded-xl p-6 border border-gray-700">
        @csrf
        @method('PUT')
        <input type="hidden" name="ano" value="{{ $ano }}">
        <input type="hidden" name="tipo" value="atuais">
        
        <h2 class="text-xl font-semibold text-white mb-2 flex items-center">
            <svg class="w-5 h-5 mr-2 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            Valores Atuais (Entrada Manual)
        </h2>
        <p class="text-gray-400 text-sm mb-4">Estes valores não são sincronizados automaticamente</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-400 mb-1">Avaliações Google Atual</label>
                <input type="number" name="atual_avaliacoes" value="{{ $metas['atual_avaliacoes'] ?? 227 }}"
                       class="w-full bg-gray-700 border border-gray-600 text-white rounded-lg px-4 py-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <button type="button" class="btn-salvar px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white font-medium rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                Salvar Valores Atuais
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    // Filtro de ano
    document.getElementById('ano-filter').addEventListener('change', function() {
        window.location.href = '{{ route("configurar-metas") }}?ano=' + this.value;
    });
    
    // Calcular totais
    function calcularTotais() {
        ['pf', 'pj', 'despesas'].forEach(tipo => {
            let total = 0;
            document.querySelectorAll(`input[name^="meta_${tipo}["]`).forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            const elem = document.getElementById(`total-${tipo}`);
            if (elem) {
                elem.textContent = 'R$ ' + total.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }
        });
    }
    
    // Recalcular ao alterar valores
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.addEventListener('input', calcularTotais);
    });
    
    // Calcular totais iniciais
    calcularTotais();

    // Salvar cada formulário independentemente
    document.querySelectorAll('.btn-salvar').forEach(btn => {
        btn.addEventListener('click', async function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const formData = new FormData(form);
            
            // Mostrar loading
            const originalText = this.innerHTML;
            this.innerHTML = 'Salvando';
            this.disabled = true;
            
            try {
                // Obter o token CSRF do meta tag
                const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
                
                const response = await fetch('{{ route("configurar-metas.update") }}', {
                    method: 'PUT',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': token
                    }
                });
                
                if (response.ok) {
                    // Mostrar sucesso
                    this.innerHTML = '<svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Salvo com Sucesso!';
                    this.classList.add('bg-opacity-75');
                    
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.disabled = false;
                        this.classList.remove('bg-opacity-75');
                    }, 3000);
                } else {
                    alert('Erro ao salvar. Tente novamente.');
                    this.innerHTML = originalText;
                    this.disabled = false;
                }
            } catch (error) {
                console.error('Erro:', error);
                alert('Erro ao salvar. Tente novamente.');
                this.innerHTML = originalText;
                this.disabled = false;
            }
        });
    });
</script>
@endpush
@endsection