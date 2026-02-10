<?php

namespace App\Http\Controllers;

use App\Models\Advogado;
use App\Models\User;
use App\Models\Configuracao;
use Illuminate\Http\Request;

class ConfiguracaoController extends Controller
{
    public function index()
    {
        $advogados = Advogado::where('ativo', true)->orderBy('nome')->get();
        $usuarios = User::with('advogado')->orderBy('name')->get();
        
        // Buscar configurações
        $configuracoes = [
            'ano_filtro' => Configuracao::get('ano_filtro', 2025),
            'nome_escritorio' => Configuracao::get('nome_escritorio', 'Mayer Advogados'),
            'meta_faturamento' => Configuracao::get('meta_faturamento', 100000),
            'meta_horas' => Configuracao::get('meta_horas', 1200),
            'meta_processos' => Configuracao::get('meta_processos', 50),
        ];
        
        $ultimaSync = Configuracao::get('ultima_sincronizacao', null);

        return view('configuracoes.index', compact('advogados', 'usuarios', 'configuracoes', 'ultimaSync'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'ano_filtro' => 'required|integer|min:2020|max:2030',
            'nome_escritorio' => 'nullable|string|max:255',
        ]);

        Configuracao::set('ano_filtro', $request->ano_filtro, 'integer');
        
        if ($request->nome_escritorio) {
            Configuracao::set('nome_escritorio', $request->nome_escritorio, 'string');
        }

        return redirect()->route('configuracoes.index')
            ->with('success', 'Configurações salvas com sucesso!');
    }

    public function salvarMetas(Request $request)
    {
        $request->validate([
            'meta_faturamento' => 'required|numeric|min:0',
            'meta_horas' => 'required|numeric|min:0',
            'meta_processos' => 'required|integer|min:0',
        ]);

        Configuracao::set('meta_faturamento', $request->meta_faturamento, 'float');
        Configuracao::set('meta_horas', $request->meta_horas, 'float');
        Configuracao::set('meta_processos', $request->meta_processos, 'integer');

        return redirect()->route('configuracoes.index')
            ->with('success', 'Metas salvas com sucesso!');
    }

    public function vincularUsuario(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'advogado_id' => 'nullable|exists:advogados,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->advogado_id = $request->advogado_id ?: null;
        $user->save();

        return redirect()->route('configuracoes.index')
            ->with('success', 'Usuário vinculado com sucesso!');
    }

    public function salvarPesos(Request $request)
    {
        $request->validate([
            'peso_financeiro' => 'required|integer|min:0|max:100',
            'peso_clientes' => 'required|integer|min:0|max:100',
            'peso_processos' => 'required|integer|min:0|max:100',
            'peso_aprendizado' => 'required|integer|min:0|max:100',
        ]);

        Configuracao::set('peso_financeiro', $request->peso_financeiro, 'integer');
        Configuracao::set('peso_clientes', $request->peso_clientes, 'integer');
        Configuracao::set('peso_processos', $request->peso_processos, 'integer');
        Configuracao::set('peso_aprendizado', $request->peso_aprendizado, 'integer');

        return redirect()->route('configuracoes.index')
            ->with('success', 'Pesos BSC salvos com sucesso!');
    }

    public function classificacaoContas()
    {
        $planoContas = config('plano_contas', []);
        $classificacoes = Configuracao::where('chave', 'like', 'classificacao_conta_%')
            ->pluck('valor', 'chave')
            ->toArray();
        
        $contas = [];
        foreach ($planoContas['receita_pf'] ?? [] as $codigo) {
            $chave = 'classificacao_conta_' . str_replace('.', '_', $codigo);
            $contas[$codigo] = [
                'codigo' => $codigo,
                'tipo' => 'Receita',
                'subtipo' => 'Pessoa Física',
                'classificacao' => $classificacoes[$chave] ?? 'RECEITA_PF',
            ];
        }
        
        foreach ($planoContas['receita_pj'] ?? [] as $codigo) {
            $chave = 'classificacao_conta_' . str_replace('.', '_', $codigo);
            $contas[$codigo] = [
                'codigo' => $codigo,
                'tipo' => 'Receita',
                'subtipo' => 'Pessoa Jurídica',
                'classificacao' => $classificacoes[$chave] ?? 'RECEITA_PJ',
            ];
        }
        
        foreach ($planoContas['receita_financeira'] ?? [] as $codigo) {
            $chave = 'classificacao_conta_' . str_replace('.', '_', $codigo);
            $contas[$codigo] = [
                'codigo' => $codigo,
                'tipo' => 'Receita',
                'subtipo' => 'Financeira',
                'classificacao' => $classificacoes[$chave] ?? 'RECEITA_FINANCEIRA',
            ];
        }
        
        foreach ($planoContas['manual'] ?? [] as $codigo) {
            $chave = 'classificacao_conta_' . str_replace('.', '_', $codigo);
            $contas[$codigo] = [
                'codigo' => $codigo,
                'tipo' => 'Manual',
                'subtipo' => 'Configurável',
                'classificacao' => $classificacoes[$chave] ?? 'PENDENTE_CLASSIFICACAO',
            ];
        }
        
        $opcoes = [
            'RECEITA_PF' => 'Receita - Pessoa Física',
            'RECEITA_PJ' => 'Receita - Pessoa Jurídica',
            'RECEITA_FINANCEIRA' => 'Receita - Financeira',
            'DESPESA' => 'Despesa',
            'PENDENTE_CLASSIFICACAO' => 'Pendente de Classificação',
        ];
        
        return view('configuracoes.classificacao-contas', compact('contas', 'opcoes'));
    }

    public function salvarClassificacao(Request $request)
    {
        $request->validate([
            'classificacoes' => 'required|array',
            'classificacoes.*.codigo' => 'required|string',
            'classificacoes.*.classificacao' => 'required|in:RECEITA_PF,RECEITA_PJ,RECEITA_FINANCEIRA,DESPESA,PENDENTE_CLASSIFICACAO',
        ]);
        
        try {
            DB::beginTransaction();
            
            foreach ($request->classificacoes as $item) {
                $codigo = $item['codigo'];
                $classificacao = $item['classificacao'];
                $chave = 'classificacao_conta_' . str_replace('.', '_', $codigo);
                
                Configuracao::updateOrCreate(
                    ['chave' => $chave],
                    [
                        'valor' => $classificacao,
                        'tipo' => 'string',
                        'descricao' => "Classificação da conta {$codigo}",
                    ]
                );
            }
            
            DB::commit();
            return redirect()->route('configuracoes.classificacao-contas')
                ->with('success', 'Classificações salvas com sucesso!');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->route('configuracoes.classificacao-contas')
                ->with('error', 'Erro ao salvar classificações: ' . $e->getMessage());
        }
    }

    public function resetarClassificacoes()
    {
        try {
            Configuracao::where('chave', 'like', 'classificacao_conta_%')->delete();
            return redirect()->route('configuracoes.classificacao-contas')
                ->with('success', 'Classificações resetadas para padrão!');
        } catch (\Throwable $e) {
            return redirect()->route('configuracoes.classificacao-contas')
                ->with('error', 'Erro ao resetar classificações: ' . $e->getMessage());
        }
    }
}
