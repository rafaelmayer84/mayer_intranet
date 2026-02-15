@extends('layouts.app')

@section('title', 'Nova Consulta SIRIC')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-3">
            <a href="{{ route('siric.index') }}" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition">
                ← Voltar
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">Ficha de Análise de Crédito</h1>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">Preencha todos os campos obrigatórios (*) para iniciar a análise</p>
            </div>
        </div>
        <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 text-xs font-semibold rounded-full uppercase tracking-wide">SIRIC</span>
    </div>

    {{-- Validation errors --}}
    @if($errors->any())
        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-300 text-sm">
            <p class="font-semibold mb-1">Corrija os erros abaixo:</p>
            @foreach($errors->all() as $error)
                <div>• {{ $error }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('siric.store') }}" class="space-y-6">
        @csrf

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- SEÇÃO 1: IDENTIFICAÇÃO DO SOLICITANTE --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 border-t-4 border-t-blue-500 p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                <span class="bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">1</span>
                Identificação do Solicitante
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="cpf_cnpj" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CPF / CNPJ <span class="text-red-500">*</span></label>
                    <input type="text" name="cpf_cnpj" id="cpf_cnpj" value="{{ old('cpf_cnpj') }}" required
                           placeholder="000.000.000-00"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="nome" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome Completo <span class="text-red-500">*</span></label>
                    <input type="text" name="nome" id="nome" value="{{ old('nome') }}" required
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="rg" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">RG</label>
                    <input type="text" name="rg" id="rg" value="{{ old('rg') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="data_nascimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Data de Nascimento</label>
                    <input type="date" name="data_nascimento" id="data_nascimento" value="{{ old('data_nascimento') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="estado_civil" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Estado Civil</label>
                    <select name="estado_civil" id="estado_civil"
                            class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione...</option>
                        @foreach(['Solteiro(a)', 'Casado(a)', 'União Estável', 'Divorciado(a)', 'Viúvo(a)', 'Separado(a)'] as $ec)
                            <option value="{{ $ec }}" {{ old('estado_civil') === $ec ? 'selected' : '' }}>{{ $ec }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="nacionalidade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nacionalidade</label>
                    <input type="text" name="nacionalidade" id="nacionalidade" value="{{ old('nacionalidade', 'Brasileira') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="telefone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefone <span class="text-red-500">*</span></label>
                    <input type="text" name="telefone" id="telefone" value="{{ old('telefone') }}" required
                           placeholder="(47) 99999-0000"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="profissao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Profissão / Cargo</label>
                    <input type="text" name="profissao" id="profissao" value="{{ old('profissao') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- SEÇÃO 2: ENDEREÇO --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 border-t-4 border-t-indigo-500 p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                <span class="bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">2</span>
                Endereço Residencial
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="lg:col-span-1">
                    <label for="endereco_cep" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">CEP</label>
                    <input type="text" name="endereco_cep" id="endereco_cep" value="{{ old('endereco_cep') }}"
                           placeholder="00000-000"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="lg:col-span-2">
                    <label for="endereco_rua" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Logradouro</label>
                    <input type="text" name="endereco_rua" id="endereco_rua" value="{{ old('endereco_rua') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="lg:col-span-1">
                    <label for="endereco_numero" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Número</label>
                    <input type="text" name="endereco_numero" id="endereco_numero" value="{{ old('endereco_numero') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="endereco_complemento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Complemento</label>
                    <input type="text" name="endereco_complemento" id="endereco_complemento" value="{{ old('endereco_complemento') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="endereco_bairro" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bairro</label>
                    <input type="text" name="endereco_bairro" id="endereco_bairro" value="{{ old('endereco_bairro') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="endereco_cidade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cidade</label>
                    <input type="text" name="endereco_cidade" id="endereco_cidade" value="{{ old('endereco_cidade') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="endereco_uf" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">UF</label>
                    <select name="endereco_uf" id="endereco_uf"
                            class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">UF</option>
                        @foreach(['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf)
                            <option value="{{ $uf }}" {{ old('endereco_uf') === $uf ? 'selected' : '' }}>{{ $uf }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- SEÇÃO 3: RENDA E PATRIMÔNIO --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 border-t-4 border-t-emerald-500 p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                <span class="bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">3</span>
                Informações de Renda e Patrimônio
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label for="renda_declarada" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Renda Mensal Declarada (R$) <span class="text-red-500">*</span></label>
                    <input type="number" name="renda_declarada" id="renda_declarada" value="{{ old('renda_declarada') }}" required
                           step="0.01" min="0"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="fonte_renda" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Fonte de Renda Principal</label>
                    <select name="fonte_renda" id="fonte_renda"
                            class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione...</option>
                        @foreach(['CLT', 'Autônomo', 'Empresário', 'Servidor Público', 'Aposentado/Pensionista', 'Profissional Liberal', 'Outro'] as $fr)
                            <option value="{{ $fr }}" {{ old('fonte_renda') === $fr ? 'selected' : '' }}>{{ $fr }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="empresa_empregador" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Empresa / Empregador</label>
                    <input type="text" name="empresa_empregador" id="empresa_empregador" value="{{ old('empresa_empregador') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="tempo_emprego" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Tempo no Emprego Atual</label>
                    <input type="text" name="tempo_emprego" id="tempo_emprego" value="{{ old('tempo_emprego') }}"
                           placeholder="Ex: 3 anos e 6 meses"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="despesas_mensais" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Despesas Mensais Declaradas (R$)</label>
                    <input type="number" name="despesas_mensais" id="despesas_mensais" value="{{ old('despesas_mensais') }}"
                           step="0.01" min="0"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="outras_rendas" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Outras Rendas (R$)</label>
                    <input type="number" name="outras_rendas" id="outras_rendas" value="{{ old('outras_rendas') }}"
                           step="0.01" min="0"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div class="mt-4">
                <label for="descricao_outras_rendas" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Detalhamento de Outras Rendas</label>
                <textarea name="descricao_outras_rendas" id="descricao_outras_rendas" rows="2"
                          placeholder="Ex: Aluguel de imóvel R$ 1.500/mês, pensão alimentícia R$ 800/mês..."
                          class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">{{ old('descricao_outras_rendas') }}</textarea>
            </div>

            {{-- Patrimônio --}}
            <div class="mt-5 pt-4 border-t border-gray-100 dark:border-gray-700">
                <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-3">Patrimônio Declarado</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div>
                        <label for="patrimonio_estimado" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Patrimônio Total Estimado (R$)</label>
                        <input type="number" name="patrimonio_estimado" id="patrimonio_estimado" value="{{ old('patrimonio_estimado') }}"
                               step="0.01" min="0"
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="flex items-center gap-6 pt-5">
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="possui_imovel" value="1" {{ old('possui_imovel') ? 'checked' : '' }}
                                   class="rounded border-gray-400 dark:border-gray-500 shadow-sm text-blue-600 focus:ring-blue-500">
                            Possui imóvel
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                            <input type="checkbox" name="possui_veiculo" value="1" {{ old('possui_veiculo') ? 'checked' : '' }}
                                   class="rounded border-gray-400 dark:border-gray-500 shadow-sm text-blue-600 focus:ring-blue-500">
                            Possui veículo
                        </label>
                    </div>
                    <div>
                        <label for="valor_imovel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor do Imóvel (R$)</label>
                        <input type="number" name="valor_imovel" id="valor_imovel" value="{{ old('valor_imovel') }}"
                               step="0.01" min="0"
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="valor_veiculo" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor do Veículo (R$)</label>
                        <input type="number" name="valor_veiculo" id="valor_veiculo" value="{{ old('valor_veiculo') }}"
                               step="0.01" min="0"
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="mt-3">
                    <label for="descricao_patrimonio" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Descrição do Patrimônio</label>
                    <textarea name="descricao_patrimonio" id="descricao_patrimonio" rows="2"
                              placeholder="Ex: Apartamento em Itajaí (quitado), Fiat Toro 2022 (financiado)..."
                              class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">{{ old('descricao_patrimonio') }}</textarea>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- SEÇÃO 4: DADOS DO PARCELAMENTO --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 border-t-4 border-t-orange-500 p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                <span class="bg-orange-100 dark:bg-orange-900/40 text-orange-700 dark:text-orange-300 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">4</span>
                Dados do Parcelamento Pretendido
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label for="valor_total" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Valor Total (R$) <span class="text-red-500">*</span></label>
                    <input type="number" name="valor_total" id="valor_total" value="{{ old('valor_total') }}" required
                           step="0.01" min="0.01"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="parcelas_desejadas" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Parcelas Desejadas <span class="text-red-500">*</span></label>
                    <input type="number" name="parcelas_desejadas" id="parcelas_desejadas" value="{{ old('parcelas_desejadas') }}" required
                           min="1" max="120"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="finalidade" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Finalidade</label>
                    <select name="finalidade" id="finalidade"
                            class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione...</option>
                        @foreach(['Honorários advocatícios', 'Acordo judicial', 'Custas processuais', 'Acordo extrajudicial', 'Consultoria jurídica', 'Outro'] as $fin)
                            <option value="{{ $fin }}" {{ old('finalidade') === $fin ? 'selected' : '' }}>{{ $fin }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="data_primeiro_vencimento" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">1° Vencimento</label>
                    <input type="date" name="data_primeiro_vencimento" id="data_primeiro_vencimento" value="{{ old('data_primeiro_vencimento') }}"
                           class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            {{-- Valor da parcela (calculado) --}}
            <div class="mt-3 p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-200 dark:border-orange-800/50">
                <p class="text-sm text-orange-800 dark:text-orange-300">
                    <span class="font-semibold">Valor estimado da parcela:</span>
                    <span id="parcela_calculada" class="text-lg font-bold ml-1">R$ 0,00</span>
                </p>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- SEÇÃO 5: REFERÊNCIAS --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 border-t-4 border-t-purple-500 p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                <span class="bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">5</span>
                Referências Pessoais
            </h2>

            <div class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="referencia1_nome" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome (Referência 1)</label>
                        <input type="text" name="referencia1_nome" id="referencia1_nome" value="{{ old('referencia1_nome') }}"
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="referencia1_telefone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefone</label>
                        <input type="text" name="referencia1_telefone" id="referencia1_telefone" value="{{ old('referencia1_telefone') }}"
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="referencia1_relacao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Relação</label>
                        <input type="text" name="referencia1_relacao" id="referencia1_relacao" value="{{ old('referencia1_relacao') }}"
                               placeholder="Ex: Familiar, Colega de trabalho..."
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="referencia2_nome" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome (Referência 2)</label>
                        <input type="text" name="referencia2_nome" id="referencia2_nome" value="{{ old('referencia2_nome') }}"
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="referencia2_telefone" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefone</label>
                        <input type="text" name="referencia2_telefone" id="referencia2_telefone" value="{{ old('referencia2_telefone') }}"
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="referencia2_relacao" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Relação</label>
                        <input type="text" name="referencia2_relacao" id="referencia2_relacao" value="{{ old('referencia2_relacao') }}"
                               placeholder="Ex: Amigo, Vizinho..."
                               class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════ --}}
        {{-- SEÇÃO 6: OBSERVAÇÕES E AUTORIZAÇÃO --}}
        {{-- ══════════════════════════════════════════════════════════════ --}}
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 border-t-4 border-t-gray-400 p-6">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100 mb-4 flex items-center gap-2">
                <span class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">6</span>
                Observações e Autorização
            </h2>

            <div class="space-y-4">
                <div>
                    <label for="observacoes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Observações Internas</label>
                    <textarea name="observacoes" id="observacoes" rows="3"
                              placeholder="Informações adicionais relevantes para a análise..."
                              class="w-full rounded-lg border-gray-400 dark:border-gray-500 shadow-sm bg-gray-50 bg-gray-50 dark:bg-gray-700 dark:text-gray-100 text-sm focus:ring-blue-500 focus:border-blue-500">{{ old('observacoes') }}</textarea>
                </div>

                <div class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-800/50">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="autorizou_consultas_externas" value="1"
                               {{ old('autorizou_consultas_externas') ? 'checked' : '' }}
                               class="mt-1 rounded border-gray-400 dark:border-gray-500 shadow-sm text-amber-600 focus:ring-amber-500">
                        <div>
                            <span class="text-sm font-semibold text-amber-800 dark:text-amber-300">Autorização para Consultas Externas</span>
                            <p class="text-xs text-amber-700 dark:text-amber-400 mt-0.5">
                                O solicitante autoriza expressamente a consulta a bureaus de crédito (Serasa/SPC)
                                e demais fontes públicas para fins desta análise. Esta autorização é requisito
                                para a etapa de verificação externa.
                            </p>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Botões --}}
        <div class="flex justify-between items-center bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 px-6 py-4">
            <p class="text-xs text-gray-400">Campos com <span class="text-red-500">*</span> são obrigatórios</p>
            <div class="flex gap-3">
                <a href="{{ route('siric.index') }}"
                   class="px-5 py-2.5 border border-gray-400 dark:border-gray-500 shadow-sm text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    Cancelar
                </a>
                <button type="submit"
                        class="btn-mayer shadow">
                    Registrar Ficha de Crédito
                </button>
            </div>
        </div>
    </form>
</div>

{{-- Script para cálculo automático da parcela --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const valorInput = document.getElementById('valor_total');
    const parcelasInput = document.getElementById('parcelas_desejadas');
    const parcelaDisplay = document.getElementById('parcela_calculada');

    function calcularParcela() {
        const valor = parseFloat(valorInput.value) || 0;
        const parcelas = parseInt(parcelasInput.value) || 1;
        const valorParcela = parcelas > 0 ? valor / parcelas : 0;
        parcelaDisplay.textContent = 'R$ ' + valorParcela.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    valorInput.addEventListener('input', calcularParcela);
    parcelasInput.addEventListener('input', calcularParcela);
    calcularParcela();
});
</script>
@endsection
