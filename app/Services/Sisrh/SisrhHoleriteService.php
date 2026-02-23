<?php
namespace App\Services\Sisrh;

use Illuminate\Support\Facades\DB;
use App\Models\SisrhRubrica;
use App\Models\SisrhHoleriteLancamento;

class SisrhHoleriteService
{
    private const INSS_ALIQUOTA = 0.11;
    private const INSS_TETO_BASE = 8475.55;

    public function gerarContracheque(int $userId, int $ano, int $mes): array
    {
        $user = DB::table('users')->where('id', $userId)->first();
        if (!$user) return ['erro' => 'Usuário não encontrado', 'user' => null, 'proventos' => [], 'descontos' => [], 'total_proventos' => 0, 'total_descontos' => 0, 'liquido' => 0];

        $rubricas = SisrhRubrica::where('ativo', true)->orderBy('ordem')->get();
        $lancamentosManuais = SisrhHoleriteLancamento::where('user_id', $userId)
            ->where('ano', $ano)->where('mes', $mes)->get()->keyBy('rubrica_id');

        $rbValor = $this->obterRb($userId, $ano);
        $rvValor = $this->obterRv($userId, $ano, $mes);

        $linhas = [];
        $totalProventos = 0;
        $totalDescontos = 0;

        foreach ($rubricas as $rubrica) {
            $valor = 0; $referencia = ''; $origem = 'manual';

            if ($rubrica->automatica && $rubrica->formula) {
                $origem = 'automatico';
                switch ($rubrica->formula) {
                    case 'rb':
                        $valor = $rbValor;
                        $referencia = str_pad($mes, 2, '0', STR_PAD_LEFT) . '/' . $ano;
                        break;
                    case 'rv':
                        $valor = $rvValor;
                        $referencia = str_pad($mes, 2, '0', STR_PAD_LEFT) . '/' . $ano;
                        break;
                    case 'inss':
                        continue 2; // calculado depois
                }
            } else {
                $lanc = $lancamentosManuais->get($rubrica->id);
                if (!$lanc) continue;
                $valor = $lanc->valor;
                $referencia = $lanc->referencia ?? '';
            }

            if ($valor == 0 && !$rubrica->automatica) continue;

            $linhas[] = [
                'rubrica_id' => $rubrica->id, 'codigo' => $rubrica->codigo,
                'nome' => $rubrica->nome, 'tipo' => $rubrica->tipo,
                'referencia' => $referencia, 'valor' => round($valor, 2), 'origem' => $origem,
            ];

            if ($rubrica->tipo === 'provento') $totalProventos += $valor;
            else $totalDescontos += $valor;
        }

        // INSS CI: 11% sobre proventos, teto R$ 8.475,55
        $baseInss = min($totalProventos, self::INSS_TETO_BASE);
        $inssValor = round($baseInss * self::INSS_ALIQUOTA, 2);
        $maxInss = round(self::INSS_TETO_BASE * self::INSS_ALIQUOTA, 2);
        $inssValor = min($inssValor, $maxInss);

        $rubricaInss = $rubricas->firstWhere('formula', 'inss');
        if ($rubricaInss) {
            $lancInss = $lancamentosManuais->get($rubricaInss->id);
            if ($lancInss) $inssValor = $lancInss->valor;

            $linhas[] = [
                'rubrica_id' => $rubricaInss->id, 'codigo' => $rubricaInss->codigo,
                'nome' => $rubricaInss->nome . ' (informativo)', 'tipo' => 'informativo',
                'referencia' => '045', 'valor' => $inssValor,
                'origem' => $lancInss ? 'manual' : 'automatico',
            ];
        }

        $proventos = array_values(array_filter($linhas, fn($l) => $l['tipo'] === 'provento'));
        $descontos = array_values(array_filter($linhas, fn($l) => $l['tipo'] === 'desconto'));
        $informativos = array_values(array_filter($linhas, fn($l) => $l['tipo'] === 'informativo'));

        return [
            'user' => $user, 'ano' => $ano, 'mes' => $mes,
            'proventos' => $proventos, 'descontos' => $descontos, 'informativos' => $informativos,
            'total_proventos' => round($totalProventos, 2),
            'total_descontos' => round($totalDescontos, 2),
            'liquido' => round($totalProventos - $totalDescontos, 2),
            'inss_base' => $baseInss, 'inss_aliquota' => self::INSS_ALIQUOTA * 100,
            'inss_teto' => self::INSS_TETO_BASE,
        ];
    }

    public function gerarFolha(int $ano, int $mes): array
    {
        $users = DB::table('users')
            ->join('sisrh_vinculos as sv', 'users.id', '=', 'sv.user_id')
            ->where('sv.ativo', true)
            ->whereIn('users.role', ['advogado', 'coordenador', 'socio', 'admin'])
            ->select('users.*')
            ->orderBy('users.name')->get();

        $folha = [];
        $totais = ['proventos' => 0, 'descontos' => 0, 'liquido' => 0];

        foreach ($users as $user) {
            $h = $this->gerarContracheque($user->id, $ano, $mes);
            $folha[] = $h;
            $totais['proventos'] += $h['total_proventos'];
            $totais['descontos'] += $h['total_descontos'];
            $totais['liquido'] += $h['liquido'];
        }

        return [
            'competencia' => str_pad($mes, 2, '0', STR_PAD_LEFT) . '/' . $ano,
            'folha' => $folha,
            'totais' => array_map(fn($v) => round($v, 2), $totais),
        ];
    }

    private function obterRb(int $userId, int $ano): float
    {
        $override = DB::table('sisrh_rb_overrides')
            ->where('user_id', $userId)->orderByDesc('id')->value('valor_rb');
        if ($override !== null) return (float) $override;

        $nivel = DB::table('users')->where('id', $userId)->value('nivel_senioridade');
        if ($nivel) {
            $rbNivel = DB::table('sisrh_rb_niveis')
                ->where('nivel', $nivel)->orderByDesc('id')->value('valor_rb');
            if ($rbNivel !== null) return (float) $rbNivel;
        }
        return 0.00;
    }

    private function obterRv(int $userId, int $ano, int $mes): float
    {
        $ap = DB::table('sisrh_apuracoes')
            ->where('user_id', $userId)->where('ano', $ano)
            ->where('mes', $mes)->where('status', 'closed')->first();
        return $ap ? (float) $ap->rv_aplicada : 0.00;
    }
}
