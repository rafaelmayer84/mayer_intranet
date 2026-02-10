@extends('layouts.app')

@section('title', 'Sincronização de Usuários - DataJuri')

@section('content')
<div class="space-y-6">
    {{-- Cabeçalho --}}
    <div class="flex items-center gap-4">
        <a href="{{ route('admin.usuarios.index') }}" 
           class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
            <svg class="w-5 h-5 text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Sincronização DataJuri</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Importe usuários do DataJuri para a Intranet</p>
        </div>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-800 rounded-lg p-4">
            <div class="flex">
                <span class="text-emerald-500 mr-2">✅</span>
                <p class="text-sm text-emerald-700 dark:text-emerald-300">{{ session('success') }}</p>
            </div>
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex">
                <span class="text-red-500 mr-2">❌</span>
                <p class="text-sm text-red-700 dark:text-red-300">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    {{-- Cards de Estatísticas --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">DataJuri</p>
                    <p class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ count($usuariosDataJuri) }}</p>
                </div>
                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                    <span class="text-xl">🔄</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Vinculados</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{{ $estatisticas['vinculados_datajuri'] }}</p>
                </div>
                <div class="w-10 h-10 bg-emerald-100 dark:bg-emerald-900/30 rounded-lg flex items-center justify-center">
                    <span class="text-xl">🔗</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Sem Vínculo</p>
                    <p class="text-2xl font-bold text-amber-600 dark:text-amber-400">{{ $estatisticas['sem_vinculo'] }}</p>
                </div>
                <div class="w-10 h-10 bg-amber-100 dark:bg-amber-900/30 rounded-lg flex items-center justify-center">
                    <span class="text-xl">⚠️</span>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl p-4 border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Disponíveis</p>
                    <p class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                        {{ count(array_filter($usuariosDataJuri, fn($u) => !$u['ja_vinculado'])) }}
                    </p>
                </div>
                <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/30 rounded-lg flex items-center justify-center">
                    <span class="text-xl">👤</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Info --}}
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex">
            <span class="text-blue-500 mr-2">ℹ️</span>
            <div class="text-sm text-blue-700 dark:text-blue-300">
                <p class="font-medium">Como funciona:</p>
                <ul class="mt-2 space-y-1 list-disc list-inside">
                    <li>Os usuários do DataJuri são listados abaixo</li>
                    <li>Clique em "Ativar" para importar um usuário para a Intranet</li>
                    <li>Uma senha temporária será gerada automaticamente</li>
                    <li>Você pode definir o papel (Admin, Coordenador, Sócio) ao ativar</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Lista de Usuários do DataJuri --}}
    <div class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Usuários do DataJuri</h2>
        </div>

        @if(count($usuariosDataJuri) > 0)
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-900/50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Usuário
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Cargo
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Status DataJuri
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Vínculo Intranet
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Ações
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($usuariosDataJuri as $userDJ)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-cyan-600 flex items-center justify-center text-white font-medium">
                                            {{ strtoupper(substr($userDJ['nome'], 0, 1)) }}
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $userDJ['nome'] }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $userDJ['email'] ?? 'Sem e-mail' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ $userDJ['cargo'] ?? '—' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($userDJ['ativo_datajuri'])
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300">
                                            Ativo
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                            Inativo
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($userDJ['ja_vinculado'])
                                        <span class="text-emerald-600 dark:text-emerald-400">🔗 Vinculado</span>
                                    @else
                                        <span class="text-gray-400">— Não vinculado</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    @if(!$userDJ['ja_vinculado'] && $userDJ['email'])
                                        <button type="button" 
                                                onclick="abrirModalAtivar({{ json_encode($userDJ) }})"
                                                class="inline-flex items-center px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium rounded-lg transition-colors">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                            </svg>
                                            Ativar
                                        </button>
                                    @elseif($userDJ['ja_vinculado'])
                                        <span class="text-sm text-gray-500 dark:text-gray-400">Já ativado</span>
                                    @else
                                        <span class="text-sm text-red-500 dark:text-red-400">Sem e-mail</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                <div class="flex flex-col items-center">
                    <span class="text-4xl mb-2">🔄</span>
                    <p>Nenhum usuário encontrado no DataJuri</p>
                    <p class="text-sm mt-1">Verifique a conexão com a API</p>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Modal de Ativação --}}
<div id="modalAtivar" class="fixed inset-0 z-50 hidden overflow-y-auto">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 transition-opacity bg-gray-500 bg-opacity-75 dark:bg-gray-900 dark:bg-opacity-75" onclick="fecharModal()"></div>
        
        <div class="relative inline-block w-full max-w-md p-6 overflow-hidden text-left align-middle transition-all transform bg-white dark:bg-gray-800 shadow-xl rounded-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Ativar Usuário</h3>
                <button onclick="fecharModal()" class="text-gray-400 hover:text-gray-500">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('admin.usuarios.ativar-datajuri') }}" method="POST">
                @csrf
                <input type="hidden" name="datajuri_id" id="modal_datajuri_id">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Nome</label>
                        <input type="text" name="nome" id="modal_nome" required
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">E-mail</label>
                        <input type="email" name="email" id="modal_email" required
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Telefone</label>
                        <input type="text" name="telefone" id="modal_telefone"
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Cargo</label>
                        <input type="text" name="cargo" id="modal_cargo"
                               class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Papel</label>
                        <select name="role" required
                                class="w-full px-4 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <option value="socio">Sócio</option>
                            <option value="coordenador">Coordenador</option>
                            <option value="admin">Administrador</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" onclick="fecharModal()"
                            class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors">
                        Ativar Usuário
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalAtivar(usuario) {
    document.getElementById('modal_datajuri_id').value = usuario.datajuri_id;
    document.getElementById('modal_nome').value = usuario.nome;
    document.getElementById('modal_email').value = usuario.email || '';
    document.getElementById('modal_telefone').value = usuario.telefone || '';
    document.getElementById('modal_cargo').value = usuario.cargo || '';
    document.getElementById('modalAtivar').classList.remove('hidden');
}

function fecharModal() {
    document.getElementById('modalAtivar').classList.add('hidden');
}

// Fechar com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') fecharModal();
});
</script>
@endsection
