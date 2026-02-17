@extends('layouts.app')

@section('title', 'WhatsApp Tickets')

@section('content')
<div class="w-full px-4 sm:px-6 py-6">

    {{-- HEADER --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">🎫 WhatsApp Tickets</h1>
            <p class="text-sm text-gray-500 mt-1">Gerencie solicitações recebidas via autoatendimento</p>
        </div>
        <button onclick="openModalCriar()" class="mt-3 sm:mt-0 inline-flex items-center px-4 py-2 bg-[#1B334A] hover:bg-[#385776] text-white text-sm font-medium rounded-lg transition-colors shadow-sm">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Novo Ticket
        </button>
    </div>

    {{-- KPI CARDS --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
            <div class="text-2xl font-bold text-red-400">{{ $kpis['abertos'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Abertos</div>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
            <div class="text-2xl font-bold text-yellow-400">{{ $kpis['em_andamento'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Em andamento</div>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
            <div class="text-2xl font-bold text-green-400">{{ $kpis['concluidos_30d'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Concluídos (30d)</div>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
            <div class="text-2xl font-bold {{ $kpis['urgentes'] > 0 ? 'text-red-500 animate-pulse' : 'text-gray-500' }}">{{ $kpis['urgentes'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Urgentes</div>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
            <div class="text-2xl font-bold {{ $kpis['sla_violados'] > 0 ? 'text-orange-400' : 'text-gray-500' }}">{{ $kpis['sla_violados'] }}</div>
            <div class="text-xs text-gray-500 mt-1">SLA > 24h</div>
        </div>
        <div class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm">
            <div class="text-2xl font-bold text-blue-400">{{ $kpis['tempo_medio_horas'] ?? '—' }}h</div>
            <div class="text-xs text-gray-500 mt-1">Tempo médio resolução</div>
        </div>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('nexo.tickets') }}" class="bg-white rounded-lg p-4 border border-gray-200 shadow-sm mb-6">
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3">
            <div>
                <label class="text-xs text-gray-600">Status</label>
                <select name="status" class="w-full mt-1 bg-white border-gray-300 text-gray-700 text-sm rounded-lg focus:ring-[#385776] focus:border-[#385776]">
                    <option value="ativos" {{ ($filtros['status'] ?? '') === 'ativos' ? 'selected' : '' }}>Ativos</option>
                    <option value="aberto" {{ ($filtros['status'] ?? '') === 'aberto' ? 'selected' : '' }}>Aberto</option>
                    <option value="em_andamento" {{ ($filtros['status'] ?? '') === 'em_andamento' ? 'selected' : '' }}>Em andamento</option>
                    <option value="concluido" {{ ($filtros['status'] ?? '') === 'concluido' ? 'selected' : '' }}>Concluído</option>
                    <option value="cancelado" {{ ($filtros['status'] ?? '') === 'cancelado' ? 'selected' : '' }}>Cancelado</option>
                    <option value="" {{ empty($filtros['status']) ? 'selected' : '' }}>Todos</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-600">Tipo</label>
                <select name="tipo" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="documento" {{ ($filtros['tipo'] ?? '') === 'documento' ? 'selected' : '' }}>Documento</option>
                    <option value="agendamento" {{ ($filtros['tipo'] ?? '') === 'agendamento' ? 'selected' : '' }}>Agendamento</option>
                    <option value="retorno" {{ ($filtros['tipo'] ?? '') === 'retorno' ? 'selected' : '' }}>Retorno</option>
                    <option value="financeiro" {{ ($filtros['tipo'] ?? '') === 'financeiro' ? 'selected' : '' }}>Financeiro</option>
                    <option value="geral" {{ ($filtros['tipo'] ?? '') === 'geral' ? 'selected' : '' }}>Geral</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-600">Prioridade</label>
                <select name="prioridade" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <option value="urgente" {{ ($filtros['prioridade'] ?? '') === 'urgente' ? 'selected' : '' }}>Urgente</option>
                    <option value="normal" {{ ($filtros['prioridade'] ?? '') === 'normal' ? 'selected' : '' }}>Normal</option>
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-600">Responsável</label>
                <select name="responsavel_id" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="sem" {{ ($filtros['responsavel_id'] ?? '') === 'sem' ? 'selected' : '' }}>Sem responsável</option>
                    @foreach($usuarios as $u)
                        <option value="{{ $u->id }}" {{ ($filtros['responsavel_id'] ?? '') == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-600">Busca</label>
                <input type="text" name="busca" value="{{ $filtros['busca'] ?? '' }}" placeholder="Nome, protocolo..." class="w-full mt-1 bg-white border-gray-300 text-gray-700 text-sm rounded-lg placeholder-gray-400 focus:ring-[#385776] focus:border-[#385776]">
            </div>
            <div>
                <label class="text-xs text-gray-600">De</label>
                <input type="date" name="data_inicio" value="{{ $filtros['data_inicio'] ?? '' }}" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <label class="text-xs text-gray-600">Até</label>
                    <input type="date" name="data_fim" value="{{ $filtros['data_fim'] ?? '' }}" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                </div>
                <button type="submit" class="px-3 py-2 bg-[#385776] hover:bg-[#1B334A] text-white text-sm rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </button>
            </div>
        </div>
    </form>

    {{-- TABELA --}}
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase border-b border-gray-200">
                    <tr>
                        <th class="px-4 py-3">Protocolo</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Assunto</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Prior.</th>
                        <th class="px-4 py-3">Responsável</th>
                        <th class="px-4 py-3">Criado</th>
                        <th class="px-4 py-3">Notas</th>
                        <th class="px-4 py-3 text-center">Ações</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($tickets as $ticket)
                    <tr class="hover:bg-gray-50 transition-colors {{ $ticket->prioridade === 'urgente' && $ticket->isAberto() ? 'border-l-2 border-red-500' : '' }}" id="row-{{ $ticket->id }}">
                        <td class="px-4 py-3">
                            <span class="font-mono text-xs text-[#385776] font-semibold">{{ $ticket->protocolo ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $ticket->nome_cliente ?? 'Desconhecido' }}</div>
                            @if($ticket->telefone)
                            <div class="text-xs text-gray-400">{{ $ticket->telefone }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">{{ $ticket->tipo_label }}</td>
                        <td class="px-4 py-3">
                            <div class="text-gray-700 max-w-xs truncate" title="{{ $ticket->assunto }}">{{ $ticket->assunto }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <select onchange="mudarStatus({{ $ticket->id }}, this.value)" class="bg-white border-gray-300 text-xs rounded px-2 py-1 text-gray-700 focus:ring-[#385776]">
                                <option value="aberto" {{ $ticket->status === 'aberto' ? 'selected' : '' }}>🔴 Aberto</option>
                                <option value="em_andamento" {{ $ticket->status === 'em_andamento' ? 'selected' : '' }}>🟡 Em andamento</option>
                                <option value="concluido" {{ $ticket->status === 'concluido' ? 'selected' : '' }}>🟢 Concluído</option>
                                <option value="cancelado" {{ $ticket->status === 'cancelado' ? 'selected' : '' }}>⚪ Cancelado</option>
                            </select>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs {{ $ticket->prioridade === 'urgente' ? 'text-red-400 font-semibold' : 'text-green-400' }}">
                                {{ $ticket->prioridade === 'urgente' ? '🔴' : '🟢' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <select onchange="atribuirResponsavel({{ $ticket->id }}, this.value)" class="bg-white border-gray-300 text-xs rounded px-2 py-1 text-gray-800 focus:ring-blue-500">
                                <option value="">Ninguém</option>
                                @foreach($usuarios as $u)
                                    <option value="{{ $u->id }}" {{ $ticket->responsavel_id == $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">
                            {{ $ticket->created_at->format('d/m H:i') }}
                            @if($ticket->created_at->diffInHours(now()) > 24 && $ticket->isAberto())
                                <span class="text-orange-400 font-semibold ml-1" title="SLA > 24h">⚠️</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs text-gray-400">{{ $ticket->notas_count ?? 0 }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <button onclick="openModalDetalhe({{ $ticket->id }})" class="text-[#385776] hover:text-[#1B334A] transition-colors" title="Ver detalhes">
                                <svg class="w-5 h-5 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            </button>
                            @if(auth()->user()->role === 'admin')
                            <button onclick="excluirTicket({{ $ticket->id }})" class="text-red-400 hover:text-red-600 transition-colors ml-2" title="Excluir">
                                <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="10" class="px-4 py-8 text-center text-gray-400">Nenhum ticket encontrado.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())
        <div class="px-4 py-3 border-t border-gray-200">
            {{ $tickets->links() }}
        </div>
        @endif
    </div>
</div>

{{-- ============================================================ --}}
{{-- MODAL DETALHES --}}
{{-- ============================================================ --}}
<div id="modalDetalhe" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-60" onclick="closeModal('modalDetalhe')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl border border-gray-200 max-w-2xl w-full mx-auto p-6 max-h-[90vh] overflow-y-auto">
            <button onclick="closeModal('modalDetalhe')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <h2 class="text-lg font-bold text-gray-800 mb-4" id="detalhe-titulo">Detalhes do Ticket</h2>
            <div id="detalhe-loading" class="text-center py-8 text-gray-500">Carregando...</div>
            <div id="detalhe-erro" class="hidden text-center py-8 text-red-600"></div>

            <div id="detalhe-conteudo" class="hidden">
                <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
                    <div><span class="text-gray-500">Protocolo:</span> <span id="det-protocolo" class="font-mono text-[#385776] font-semibold"></span></div>
                    <div><span class="text-gray-500">Status:</span>
                        <select id="det-status-select" onchange="mudarStatusModal(this.value)" class="ml-1 text-xs border border-gray-300 rounded px-2 py-1 text-gray-700 focus:ring-[#385776]">
                            <option value="aberto">Aberto</option>
                            <option value="em_andamento">Em andamento</option>
                            <option value="concluido">Concluído</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div><span class="text-gray-500">Cliente:</span> <span id="det-cliente" class="text-gray-700"></span></div>
                    <div><span class="text-gray-500">Telefone:</span> <span id="det-telefone" class="text-gray-200"></span></div>
                    <div><span class="text-gray-500">Tipo:</span> <span id="det-tipo" class="text-gray-200"></span></div>
                    <div><span class="text-gray-500">Prioridade:</span> <span id="det-prioridade"></span></div>
                    <div><span class="text-gray-500">Criado:</span> <span id="det-criado" class="text-gray-200"></span></div>
                    <div><span class="text-gray-500">Origem:</span> <span id="det-origem" class="text-gray-200"></span></div>
                </div>

                <div class="mb-4">
                    <span class="text-gray-500 text-sm">Assunto:</span>
                    <div id="det-assunto" class="text-gray-800 font-medium mt-1"></div>
                </div>

                <div class="mb-4">
                    <span class="text-gray-500 text-sm">Mensagem:</span>
                    <div id="det-mensagem" class="text-gray-600 text-sm mt-1 bg-gray-50 rounded p-3 border border-gray-200 whitespace-pre-wrap max-h-32 overflow-y-auto"></div>
                </div>

                <div class="border-t border-gray-200 pt-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Responsável</h3>
                    <select id="det-responsavel-select" onchange="transferirResponsavel(this.value)" class="w-full text-sm border border-gray-300 rounded-lg px-3 py-2 text-gray-700 focus:ring-[#385776] focus:border-[#385776]">
                        <option value="">Ninguém</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="border-t border-gray-200 pt-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Resolução</h3>
                    <div id="det-resolucao-existente" class="hidden mb-2 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800 whitespace-pre-wrap"></div>
                    <div id="det-resolucao-form">
                        <textarea id="resolucao-texto" rows="3" placeholder="Descreva a resolução do ticket..." class="w-full bg-white border border-gray-300 text-gray-700 text-sm rounded-lg placeholder-gray-400 focus:ring-[#385776] focus:border-[#385776]"></textarea>
                        <div class="flex justify-end mt-2">
                            <button onclick="enviarResolucao()" id="btn-resolver" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm rounded-lg transition-colors">
                                Resolver Ticket
                            </button>
                        </div>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-4 mb-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Tratativas</h3>
                    <div id="det-notas" class="space-y-3 max-h-48 overflow-y-auto"></div>
                    <div id="det-notas-vazio" class="hidden text-sm text-gray-400 italic">Nenhuma tratativa registrada.</div>
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Adicionar tratativa</h3>
                    <textarea id="nota-texto" rows="3" placeholder="Descreva o que foi feito..." class="w-full bg-white border-gray-300 text-gray-700 text-sm rounded-lg placeholder-gray-400 focus:ring-[#385776] focus:border-[#385776]"></textarea>
                    <div class="flex items-center justify-between mt-2">
                        <label class="flex items-center text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox" id="nota-notificar" class="mr-2 rounded bg-white border-gray-300 text-[#385776] focus:ring-[#385776]">
                            Notificar cliente via WhatsApp
                        </label>
                        <button onclick="enviarNota()" id="btn-salvar-nota" class="px-4 py-2 bg-[#385776] hover:bg-[#1B334A] text-white text-sm rounded-lg transition-colors">
                            Salvar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ============================================================ --}}
{{-- MODAL CRIAR --}}
{{-- ============================================================ --}}
<div id="modalCriar" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="fixed inset-0 bg-black bg-opacity-60" onclick="closeModal('modalCriar')"></div>
        <div class="relative bg-white rounded-xl shadow-2xl border border-gray-200 max-w-lg w-full mx-auto p-6">
            <button onclick="closeModal('modalCriar')" class="absolute top-4 right-4 text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>

            <h2 class="text-lg font-bold text-gray-800 mb-4">Novo Ticket</h2>
            <div id="criar-erro" class="hidden mb-3 p-3 bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg"></div>

            <div class="space-y-3">
                <div>
                    <label class="text-xs text-gray-600">Assunto *</label>
                    <input type="text" id="criar-assunto" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500" placeholder="Descreva o assunto">
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-600">Tipo *</label>
                        <select id="criar-tipo" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="geral">Geral</option>
                            <option value="documento">Documento</option>
                            <option value="agendamento">Agendamento</option>
                            <option value="retorno">Retorno</option>
                            <option value="financeiro">Financeiro</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">Prioridade *</label>
                        <select id="criar-prioridade" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                            <option value="normal">Normal</option>
                            <option value="urgente">Urgente</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-600">Nome do cliente</label>
                        <input type="text" id="criar-nome" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">Telefone</label>
                        <input type="text" id="criar-telefone" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Responsável</label>
                    <select id="criar-responsavel" class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Ninguém</option>
                        @foreach($usuarios as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="text-xs text-gray-600">Mensagem</label>
                    <textarea id="criar-mensagem" rows="3" placeholder="Detalhes adicionais..." class="w-full mt-1 bg-white border-gray-300 text-gray-800 text-sm rounded-lg placeholder-gray-500 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                <div class="flex justify-end pt-2">
                    <button onclick="criarTicket()" id="btn-criar" class="px-4 py-2 bg-[#385776] hover:bg-[#1B334A] text-white text-sm font-medium rounded-lg transition-colors">
                        Criar Ticket
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const TICKET_URL = '{{ url("/nexo/tickets") }}';
    const CSRF = '{{ csrf_token() }}';
    let currentTicketId = null;

    function headers(isJson = true) {
        const h = {
            'X-CSRF-TOKEN': CSRF,
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        };
        if (isJson) h['Content-Type'] = 'application/json';
        return h;
    }

    // =========================================================
    // MODAL DETALHES
    // =========================================================
    function openModalDetalhe(id) {
        currentTicketId = id;
        document.getElementById('modalDetalhe').classList.remove('hidden');
        document.getElementById('detalhe-loading').classList.remove('hidden');
        document.getElementById('detalhe-conteudo').classList.add('hidden');
        document.getElementById('detalhe-erro').classList.add('hidden');

        fetch(`${TICKET_URL}/${id}`, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => {
                if (!r.ok) throw new Error('HTTP ' + r.status);
                return r.json();
            })
            .then(data => {
                if (data.erro) { showDetError(data.erro); return; }

                const t = data.ticket;
                document.getElementById('detalhe-titulo').textContent = 'Ticket ' + (t.protocolo || '#' + t.id);
                document.getElementById('det-protocolo').textContent = t.protocolo || '—';
                // status populado via det-status-select abaixo
                document.getElementById('det-cliente').textContent = t.nome_cliente || 'Desconhecido';
                document.getElementById('det-telefone').textContent = t.telefone || '—';
                document.getElementById('det-tipo').textContent = t.tipo || 'geral';
                document.getElementById('det-prioridade').innerHTML = t.prioridade === 'urgente' ? '<span class="text-red-400 font-semibold">Urgente</span>' : '<span class="text-green-400">Normal</span>';
                document.getElementById('det-criado').textContent = formatDate(t.created_at);
                document.getElementById('det-origem').textContent = t.origem || 'whatsapp';
                document.getElementById('det-assunto').textContent = t.assunto || '';
                document.getElementById('det-mensagem').textContent = t.mensagem || '(sem mensagem)';

                const notasDiv = document.getElementById('det-notas');
                const notasVazio = document.getElementById('det-notas-vazio');
                notasDiv.innerHTML = '';

                if (data.notas && data.notas.length > 0) {
                    notasVazio.classList.add('hidden');
                    data.notas.forEach(function(n) {
                        if (n.tipo === 'resolucao') return; // resolucao exibida no bloco proprio
                        var div = document.createElement('div');
                        div.className = (n.tipo === 'transferencia' ? 'bg-blue-50 border border-blue-200' : 'bg-gray-50 border border-gray-200') + ' rounded p-3 text-sm';
                        div.innerHTML = '<div class="flex justify-between text-xs text-gray-400 mb-1">'
                            + '<span class="font-medium text-gray-700">' + esc(n.usuario) + '</span>'
                            + '<span>' + esc(n.data) + (n.notificou_cliente ? ' 📱' : '') + '</span>'
                            + '</div>'
                            + '<div class="text-gray-600 whitespace-pre-wrap">' + esc(n.texto) + '</div>';
                        notasDiv.appendChild(div);
                    });
                } else {
                    notasVazio.classList.remove('hidden');
                }

                document.getElementById('nota-texto').value = '';
                document.getElementById('nota-notificar').checked = false;

                // Status dropdown
                var statusSel = document.getElementById('det-status-select');
                if (statusSel) statusSel.value = t.status;

                // Responsavel dropdown
                var respSel = document.getElementById('det-responsavel-select');
                if (respSel) respSel.value = t.responsavel_id || '';

                // Resolucao
                var resExist = document.getElementById('det-resolucao-existente');
                var resForm = document.getElementById('det-resolucao-form');
                if (t.resolucao) {
                    resExist.textContent = t.resolucao;
                    resExist.classList.remove('hidden');
                    resForm.classList.add('hidden');
                } else {
                    resExist.classList.add('hidden');
                    resForm.classList.remove('hidden');
                    document.getElementById('resolucao-texto').value = '';
                }
                document.getElementById('detalhe-loading').classList.add('hidden');
                document.getElementById('detalhe-conteudo').classList.remove('hidden');
            })
            .catch(function(err) {
                console.error('Erro ao carregar ticket:', err);
                showDetError('Erro ao carregar ticket: ' + err.message);
            });
    }

    function showDetError(msg) {
        document.getElementById('detalhe-loading').classList.add('hidden');
        document.getElementById('detalhe-conteudo').classList.add('hidden');
        var el = document.getElementById('detalhe-erro');
        el.textContent = msg;
        el.classList.remove('hidden');
    }

    function openModalCriar() {
        document.getElementById('modalCriar').classList.remove('hidden');
        document.getElementById('criar-assunto').value = '';
        document.getElementById('criar-mensagem').value = '';
        document.getElementById('criar-nome').value = '';
        document.getElementById('criar-telefone').value = '';
        document.getElementById('criar-responsavel').value = '';
        document.getElementById('criar-erro').classList.add('hidden');
    }

    function closeModal(id) {
        document.getElementById(id).classList.add('hidden');
    }

    // =========================================================
    // AÇÕES INLINE
    // =========================================================
    function mudarStatus(ticketId, novoStatus) {
        fetch(TICKET_URL + '/' + ticketId + '/status', {
            credentials: 'same-origin',
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify({ status: novoStatus })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sucesso) showToast('Status atualizado');
            else showToast('Erro: ' + (data.message || 'falha'), true);
        })
        .catch(function() { showToast('Erro ao atualizar status', true); });
    }

    function atribuirResponsavel(ticketId, userId) {
        fetch(TICKET_URL + '/' + ticketId + '/atribuir', {
            credentials: 'same-origin',
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify({ responsavel_id: userId || null })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sucesso) {
                showToast('Atribuido a ' + (data.responsavel || 'ninguem'));
                var row = document.getElementById('row-' + ticketId);
                if (row && data.status === 'em_andamento') {
                    var sel = row.querySelectorAll('select')[0];
                    if (sel) sel.value = 'em_andamento';
                }
            }
        })
        .catch(function() { showToast('Erro ao atribuir', true); });
    }

    // =========================================================
    // NOTAS
    // =========================================================
    function enviarNota() {
        var texto = document.getElementById('nota-texto').value.trim();
        if (!texto) { alert('Digite a tratativa'); return; }

        var notificar = document.getElementById('nota-notificar').checked;
        var btn = document.getElementById('btn-salvar-nota');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        fetch(TICKET_URL + '/' + currentTicketId + '/nota', {
            credentials: 'same-origin',
            method: 'POST',
            headers: headers(),
            body: JSON.stringify({ texto: texto, notificar_cliente: notificar })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Salvar';
            if (data.sucesso) {
                showToast(notificar ? 'Nota salva + WhatsApp enviado' : 'Nota salva');
                openModalDetalhe(currentTicketId);
            } else {
                showToast('Erro: ' + (data.message || 'falha'), true);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Salvar';
            showToast('Erro ao salvar nota', true);
        });
    }

    // =========================================================
    // CRIAR TICKET
    // =========================================================
    function criarTicket() {
        var assunto = document.getElementById('criar-assunto').value.trim();
        if (!assunto) { alert('Informe o assunto'); return; }

        var dados = {
            assunto: assunto,
            tipo: document.getElementById('criar-tipo').value,
            prioridade: document.getElementById('criar-prioridade').value,
            nome_cliente: document.getElementById('criar-nome').value,
            telefone: document.getElementById('criar-telefone').value,
            responsavel_id: document.getElementById('criar-responsavel').value || null,
            mensagem: document.getElementById('criar-mensagem').value
        };

        var btn = document.getElementById('btn-criar');
        var errDiv = document.getElementById('criar-erro');
        btn.disabled = true;
        btn.textContent = 'Criando...';
        errDiv.classList.add('hidden');

        fetch(TICKET_URL, {
            credentials: 'same-origin',
            method: 'POST',
            headers: headers(),
            body: JSON.stringify(dados)
        })
        .then(function(r) {
            if (!r.ok) return r.json().then(function(d) { throw d; });
            return r.json();
        })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Criar Ticket';
            if (data.sucesso) {
                showToast('Ticket criado: ' + data.protocolo);
                closeModal('modalCriar');
                location.reload();
            }
        })
        .catch(function(err) {
            btn.disabled = false;
            btn.textContent = 'Criar Ticket';
            var msg = 'Erro ao criar ticket';
            if (err && err.errors) {
                msg = Object.values(err.errors).flat().join(', ');
            } else if (err && err.message) {
                msg = err.message;
            }
            errDiv.textContent = msg;
            errDiv.classList.remove('hidden');
        });
    }

    // =========================================================
    // HELPERS
    // =========================================================
    function statusBadge(s) {
        var map = {
            aberto: '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">Aberto</span>',
            em_andamento: '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-700">Em andamento</span>',
            concluido: '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Concluído</span>',
            cancelado: '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Cancelado</span>'
        };
        return map[s] || s;
    }

    function formatDate(d) {
        if (!d) return '—';
        var dt = new Date(d);
        return dt.toLocaleDateString('pt-BR') + ' ' + dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    }

    function esc(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }


    function mudarStatusModal(novoStatus) {
        fetch(TICKET_URL + '/' + currentTicketId + '/status', {
            credentials: 'same-origin',
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify({ status: novoStatus })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sucesso) {
                showToast('Status atualizado para ' + novoStatus);
                // Atualizar linha da tabela se existir
                var row = document.getElementById('row-' + currentTicketId);
                if (row) {
                    var sel = row.querySelector('select[onchange*="mudarStatus"]');
                    if (sel) sel.value = novoStatus;
                }
            } else {
                showToast('Erro: ' + (data.message || 'falha'), true);
            }
        })
        .catch(function() { showToast('Erro ao atualizar status', true); });
    }

    function transferirResponsavel(userId) {
        fetch(TICKET_URL + '/' + currentTicketId + '/atribuir', {
            credentials: 'same-origin',
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify({ responsavel_id: userId || null })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sucesso) {
                showToast('Responsável transferido' + (data.responsavel ? ' para ' + data.responsavel : ''));
                openModalDetalhe(currentTicketId); // Recarrega para ver a nota de transferencia
            } else {
                showToast('Erro: ' + (data.message || 'falha'), true);
            }
        })
        .catch(function() { showToast('Erro ao transferir', true); });
    }

    function enviarResolucao() {
        var texto = document.getElementById('resolucao-texto').value.trim();
        if (!texto) { alert('Descreva a resolução'); return; }

        var btn = document.getElementById('btn-resolver');
        btn.disabled = true;
        btn.textContent = 'Resolvendo...';

        fetch(TICKET_URL + '/' + currentTicketId + '/resolver', {
            credentials: 'same-origin',
            method: 'PUT',
            headers: headers(),
            body: JSON.stringify({ resolucao: texto })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            btn.disabled = false;
            btn.textContent = 'Resolver Ticket';
            if (data.sucesso) {
                showToast('Ticket resolvido!');
                openModalDetalhe(currentTicketId);
                // Atualizar status na tabela
                var row = document.getElementById('row-' + currentTicketId);
                if (row) {
                    var sel = row.querySelector('select[onchange*="mudarStatus"]');
                    if (sel) sel.value = 'concluido';
                }
            } else {
                showToast('Erro: ' + (data.message || 'falha'), true);
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = 'Resolver Ticket';
            showToast('Erro ao resolver ticket', true);
        });
    }


    function excluirTicket(id) {
        if (!confirm('Tem certeza que deseja excluir este ticket? Esta ação não pode ser desfeita.')) return;
        fetch(TICKET_URL + '/' + id, {
            credentials: 'same-origin',
            method: 'DELETE',
            headers: headers()
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.sucesso) {
                showToast('Ticket excluído');
                var row = document.getElementById('row-' + id);
                if (row) row.remove();
                closeModal('modalDetalhe');
            } else {
                showToast('Erro: ' + (data.message || 'falha'), true);
            }
        })
        .catch(function() { showToast('Erro ao excluir', true); });
    }

    function showToast(msg, isError) {
        var toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 z-[60] px-4 py-3 rounded-lg shadow-lg text-sm font-medium transition-opacity duration-300 ' + (isError ? 'bg-red-500 text-white' : 'bg-green-500 text-white');
        toast.textContent = msg;
        document.body.appendChild(toast);
        setTimeout(function() { toast.style.opacity = '0'; setTimeout(function() { toast.remove(); }, 300); }, 3000);
    }
</script>
@endpush
