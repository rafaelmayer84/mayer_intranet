<?php
namespace App\Http\Controllers\Reports;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportLeadsService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportLeadsController extends Controller
{
    protected ReportLeadsService $service;
    public function __construct(ReportLeadsService $service) { $this->service = $service; }

    public function funil(Request $request)
    {
        $filters = $request->only(['status','area','origem','busca','periodo_de','periodo_ate','sort','dir']);
        $data = $this->service->funil($filters, (int)$request->get('per_page',25));
        $stats = $this->service->funilStats();

        $origens = \DB::table('leads')->whereNotNull('origem_canal')->where('origem_canal','!=','')
            ->distinct()->pluck('origem_canal')->sort()->toArray();
        $areas = \DB::table('leads')->whereNotNull('area_interesse')->where('area_interesse','!=','')
            ->distinct()->pluck('area_interesse')->sort()->toArray();

        return view('reports._report-layout', [
            'reportTitle'=>'Funil de Leads (com Análise IA)','domainLabel'=>'CRM / Clientes & Leads',
            'columns'=>[
                ['key'=>'data_entrada','label'=>'Entrada','format'=>'date','sortable'=>true],
                ['key'=>'nome','label'=>'Nome','format'=>'text','sortable'=>true],
                ['key'=>'area_interesse','label'=>'Área','format'=>'text','sortable'=>true],
                ['key'=>'sub_area','label'=>'Sub-área (IA)','format'=>'text','sortable'=>false,'limit'=>30],
                ['key'=>'complexidade','label'=>'Complex. (IA)','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['baixa'=>'bg-emerald-100 text-emerald-700','média'=>'bg-amber-100 text-amber-700','alta'=>'bg-red-100 text-red-700']],
                ['key'=>'urgencia','label'=>'Urgência (IA)','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['baixa'=>'bg-gray-100 text-gray-600','média'=>'bg-amber-100 text-amber-700','alta'=>'bg-red-100 text-red-700']],
                ['key'=>'intencao_contratar','label'=>'Intenção (IA)','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['sim'=>'bg-emerald-100 text-emerald-700','talvez'=>'bg-amber-100 text-amber-700','não'=>'bg-red-100 text-red-700']],
                ['key'=>'potencial_honorarios','label'=>'Potencial (IA)','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['alto'=>'bg-emerald-100 text-emerald-700','médio'=>'bg-amber-100 text-amber-700','baixo'=>'bg-gray-100 text-gray-500']],
                ['key'=>'resumo_demanda','label'=>'Resumo IA','format'=>'text','sortable'=>false,'limit'=>60],
                ['key'=>'status','label'=>'Status','format'=>'badge','sortable'=>true,
                 'badge_colors'=>['novo'=>'bg-blue-100 text-blue-700','contatado'=>'bg-violet-100 text-violet-700','convertido'=>'bg-emerald-100 text-emerald-700','descartado'=>'bg-gray-100 text-gray-500','arquivado'=>'bg-gray-50 text-gray-400']],
                ['key'=>'origem_canal','label'=>'Origem','format'=>'text','sortable'=>true],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'status','label'=>'Status','type'=>'select','options'=>['novo'=>'Novo','contatado'=>'Contatado','convertido'=>'Convertido','descartado'=>'Descartado','arquivado'=>'Arquivado']],
                ['name'=>'area','label'=>'Área','type'=>'select','options'=>array_combine($areas,$areas)],
                ['name'=>'origem','label'=>'Origem','type'=>'select','options'=>array_combine($origens,$origens)],
                ['name'=>'busca','label'=>'Busca','type'=>'text','placeholder'=>'Nome ou demanda...'],
                ['name'=>'periodo_de','label'=>'De','type'=>'month'],
                ['name'=>'periodo_ate','label'=>'Até','type'=>'month'],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'leads','report'=>'funil']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportFunil(Request $request, string $type)
    {
        $data = $this->service->funil($request->only(['status','area','origem','busca','periodo_de','periodo_ate','sort','dir']), 999999);
        return ReportExportService::export($type, 'Funil de Leads', [
            ['key'=>'data_entrada','label'=>'Entrada','format'=>'date'],['key'=>'nome','label'=>'Nome','format'=>'text'],
            ['key'=>'area_interesse','label'=>'Área','format'=>'text'],['key'=>'sub_area','label'=>'Sub-área','format'=>'text'],
            ['key'=>'complexidade','label'=>'Complex.','format'=>'text'],['key'=>'urgencia','label'=>'Urgência','format'=>'text'],
            ['key'=>'intencao_contratar','label'=>'Intenção','format'=>'text'],['key'=>'potencial_honorarios','label'=>'Potencial','format'=>'text'],
            ['key'=>'resumo_demanda','label'=>'Resumo IA','format'=>'text'],['key'=>'status','label'=>'Status','format'=>'text'],
        ], collect($data->items()), []);
    }

    public function marketing(Request $request)
    {
        $data = $this->service->marketing($request->only([]));
        return view('reports._report-layout', [
            'reportTitle'=>'Performance Marketing','domainLabel'=>'CRM / Clientes & Leads',
            'columns'=>[
                ['key'=>'fonte','label'=>'Fonte/Canal','format'=>'text','sortable'=>false],
                ['key'=>'total_leads','label'=>'Total Leads','format'=>'text','sortable'=>false],
                ['key'=>'convertidos','label'=>'Convertidos','format'=>'text','sortable'=>false],
                ['key'=>'contatados','label'=>'Contatados','format'=>'text','sortable'=>false],
                ['key'=>'descartados','label'=>'Descartados','format'=>'text','sortable'=>false],
                ['key'=>'taxa_conversao','label'=>'Tx Conversão %','format'=>'text','sortable'=>false],
            ],
            'data'=>collect($data),
            'totals'=>['total_leads'=>array_sum(array_column($data,'total_leads')),'convertidos'=>array_sum(array_column($data,'convertidos'))],
            'filters'=>[],'exportRoute'=>null,
        ]);
    }
}
