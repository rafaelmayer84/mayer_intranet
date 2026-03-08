<?php
namespace App\Http\Controllers\Reports;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportSisrhService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportSisrhController extends Controller
{
    protected ReportSisrhService $service;
    public function __construct(ReportSisrhService $service) { $this->service = $service; }

    public function folha(Request $request)
    {
        $filters = $request->only(['colaborador','competencia','sort','dir']);
        $data = $this->service->folha($filters, (int)$request->get('per_page',25));

        return view('reports._report-layout', [
            'reportTitle'=>'Remuneração (RB + RV)','domainLabel'=>'RH (SISRH)',
            'columns'=>[
                ['key'=>'colaborador','label'=>'Colaborador','format'=>'text','sortable'=>false],
                ['key'=>'nivel_senioridade','label'=>'Nível','format'=>'text','sortable'=>false],
                ['key'=>'mes','label'=>'Mês','format'=>'text','sortable'=>false],
                ['key'=>'ano','label'=>'Ano','format'=>'text','sortable'=>false],
                ['key'=>'rb_valor','label'=>'RB (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'captacao_valor','label'=>'Captação (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'gdp_score','label'=>'GDP Score','format'=>'text','sortable'=>false],
                ['key'=>'percentual_faixa','label'=>'% Faixa','format'=>'text','sortable'=>false],
                ['key'=>'rv_bruta','label'=>'RV Bruta (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'reducao_total_pct','label'=>'Redução %','format'=>'text','sortable'=>false],
                ['key'=>'rv_aplicada','label'=>'RV Aplicada (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'status','label'=>'Status','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['closed'=>'bg-emerald-100 text-emerald-700','open'=>'bg-blue-100 text-blue-700','draft'=>'bg-gray-100 text-gray-500']],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'colaborador','label'=>'Colaborador','type'=>'text','placeholder'=>'Nome...'],
                ['name'=>'competencia','label'=>'Competência','type'=>'month'],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'sisrh','report'=>'folha']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportFolha(Request $request, string $type)
    {
        $data = $this->service->folha($request->only(['colaborador','competencia','sort','dir']), 999999);
        return ReportExportService::export($type, 'Remuneração RB+RV', [
            ['key'=>'colaborador','label'=>'Colaborador','format'=>'text'],['key'=>'mes','label'=>'Mês','format'=>'text'],
            ['key'=>'ano','label'=>'Ano','format'=>'text'],['key'=>'rb_valor','label'=>'RB','format'=>'currency'],
            ['key'=>'rv_aplicada','label'=>'RV','format'=>'currency'],['key'=>'gdp_score','label'=>'GDP','format'=>'text'],
        ], collect($data->items()), []);
    }

    public function custos(Request $request)
    {
        $data = $this->service->custos($request->only([]));
        return view('reports._report-layout', [
            'reportTitle'=>'Custos RH por Período','domainLabel'=>'RH (SISRH)',
            'columns'=>[
                ['key'=>'periodo','label'=>'Período','format'=>'text','sortable'=>false],
                ['key'=>'total_rb','label'=>'Total RB (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'total_rv','label'=>'Total RV (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'custo_total','label'=>'Custo Total (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'colaboradores','label'=>'Colaboradores','format'=>'text','sortable'=>false],
            ],
            'data'=>collect($data),
            'totals'=>[
                'total_rb'=>array_sum(array_column($data,'total_rb')),
                'total_rv'=>array_sum(array_column($data,'total_rv')),
                'custo_total'=>array_sum(array_column($data,'custo_total')),
            ],
            'filters'=>[],'exportRoute'=>null,
        ]);
    }
}
