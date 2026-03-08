<?php
namespace App\Http\Controllers\Reports;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportGdpService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportGdpController extends Controller
{
    protected ReportGdpService $service;
    public function __construct(ReportGdpService $service) { $this->service = $service; }

    public function performance(Request $request)
    {
        $filters = $request->only(['user_id','mes','sort','dir']);
        $data = $this->service->scorecard($filters, (int)$request->get('per_page',25));

        $users = \DB::table('gdp_snapshots as gs')->leftJoin('users as u','u.id','=','gs.user_id')
            ->whereNotNull('u.name')->distinct()->pluck('u.name','gs.user_id')->toArray();

        return view('reports._report-layout', [
            'reportTitle'=>'Scorecard GDP','domainLabel'=>'Performance (GDP)',
            'columns'=>[
                ['key'=>'advogado','label'=>'Advogado','format'=>'text','sortable'=>false],
                ['key'=>'mes','label'=>'Mês','format'=>'text','sortable'=>false],
                ['key'=>'ano','label'=>'Ano','format'=>'text','sortable'=>false],
                ['key'=>'score_juridico','label'=>'Jurídico','format'=>'text','sortable'=>false],
                ['key'=>'score_financeiro','label'=>'Financeiro','format'=>'text','sortable'=>false],
                ['key'=>'score_desenvolvimento','label'=>'Desenvolvimento','format'=>'text','sortable'=>false],
                ['key'=>'score_atendimento','label'=>'Atendimento','format'=>'text','sortable'=>false],
                ['key'=>'score_total','label'=>'Score Total','format'=>'text','sortable'=>false],
                ['key'=>'total_penalizacoes','label'=>'Penalizações','format'=>'text','sortable'=>false],
                ['key'=>'percentual_variavel','label'=>'% Variável','format'=>'text','sortable'=>false],
                ['key'=>'ranking','label'=>'Ranking','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['1'=>'bg-amber-200 text-amber-800','2'=>'bg-gray-200 text-gray-700','3'=>'bg-orange-100 text-orange-700','4'=>'bg-gray-100 text-gray-500']],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'user_id','label'=>'Advogado','type'=>'select','options'=>$users],
                ['name'=>'mes','label'=>'Mês','type'=>'select','options'=>array_combine(range(1,12),['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'])],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'gdp','report'=>'performance']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportPerformance(Request $request, string $type)
    {
        $data = $this->service->scorecard($request->only(['user_id','mes','sort','dir']), 999999);
        return ReportExportService::export($type, 'Scorecard GDP', [
            ['key'=>'advogado','label'=>'Advogado','format'=>'text'],['key'=>'mes','label'=>'Mês','format'=>'text'],
            ['key'=>'score_juridico','label'=>'Jurídico','format'=>'text'],['key'=>'score_financeiro','label'=>'Financeiro','format'=>'text'],
            ['key'=>'score_total','label'=>'Total','format'=>'text'],['key'=>'ranking','label'=>'Ranking','format'=>'text'],
        ], collect($data->items()), []);
    }

    public function penalizacoes(Request $request)
    {
        $filters = $request->only(['user_id','mes','sort','dir']);
        $data = $this->service->penalizacoes($filters, (int)$request->get('per_page',25));

        $users = \DB::table('gdp_penalizacoes as gp')->leftJoin('users as u','u.id','=','gp.user_id')
            ->whereNotNull('u.name')->distinct()->pluck('u.name','gp.user_id')->toArray();

        return view('reports._report-layout', [
            'reportTitle'=>'Penalizações GDP','domainLabel'=>'Performance (GDP)',
            'columns'=>[
                ['key'=>'created_at','label'=>'Data','format'=>'date','sortable'=>true],
                ['key'=>'advogado','label'=>'Advogado','format'=>'text','sortable'=>false],
                ['key'=>'codigo','label'=>'Código','format'=>'text','sortable'=>false],
                ['key'=>'tipo_nome','label'=>'Tipo','format'=>'text','sortable'=>false,'limit'=>40],
                ['key'=>'pontos_desconto','label'=>'Pontos','format'=>'text','sortable'=>false],
                ['key'=>'descricao_automatica','label'=>'Descrição','format'=>'text','sortable'=>false,'limit'=>80],
                ['key'=>'contestada','label'=>'Contestada','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['0'=>'bg-gray-100 text-gray-500','1'=>'bg-amber-100 text-amber-700']],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'user_id','label'=>'Advogado','type'=>'select','options'=>$users],
                ['name'=>'mes','label'=>'Mês','type'=>'select','options'=>array_combine(range(1,12),['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'])],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'gdp','report'=>'penalizacoes']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportPenalizacoes(Request $request, string $type)
    {
        $data = $this->service->penalizacoes($request->only(['user_id','mes','sort','dir']), 999999);
        return ReportExportService::export($type, 'Penalizações GDP', [
            ['key'=>'created_at','label'=>'Data','format'=>'date'],['key'=>'advogado','label'=>'Advogado','format'=>'text'],
            ['key'=>'codigo','label'=>'Código','format'=>'text'],['key'=>'tipo_nome','label'=>'Tipo','format'=>'text'],
            ['key'=>'pontos_desconto','label'=>'Pontos','format'=>'text'],['key'=>'descricao_automatica','label'=>'Descrição','format'=>'text'],
        ], collect($data->items()), []);
    }

    public function avaliacoes180(Request $request)
    {
        $data = $this->service->avaliacoes180($request->only(['avaliado','sort','dir']), (int)$request->get('per_page',25));
        return view('reports._report-layout', [
            'reportTitle'=>'Avaliações 180°','domainLabel'=>'Performance (GDP)',
            'columns'=>[
                ['key'=>'submitted_at','label'=>'Data','format'=>'date','sortable'=>true],
                ['key'=>'avaliado_nome','label'=>'Avaliado','format'=>'text','sortable'=>false],
                ['key'=>'avaliador_nome','label'=>'Avaliador','format'=>'text','sortable'=>false],
                ['key'=>'rater_type','label'=>'Tipo','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['autoavaliacao'=>'bg-blue-100 text-blue-700','pares'=>'bg-violet-100 text-violet-700','gestor'=>'bg-amber-100 text-amber-700']],
                ['key'=>'total_score','label'=>'Score Total','format'=>'text','sortable'=>false],
                ['key'=>'comment_text','label'=>'Comentário','format'=>'text','sortable'=>false,'limit'=>80],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'avaliado','label'=>'Avaliado','type'=>'text','placeholder'=>'Nome...'],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'gdp','report'=>'avaliacoes-180']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportAvaliacoes180(Request $request, string $type)
    {
        $data = $this->service->avaliacoes180($request->only(['avaliado','sort','dir']), 999999);
        return ReportExportService::export($type, 'Avaliações 180°', [
            ['key'=>'submitted_at','label'=>'Data','format'=>'date'],['key'=>'avaliado_nome','label'=>'Avaliado','format'=>'text'],
            ['key'=>'avaliador_nome','label'=>'Avaliador','format'=>'text'],['key'=>'rater_type','label'=>'Tipo','format'=>'text'],
            ['key'=>'total_score','label'=>'Score','format'=>'text'],['key'=>'comment_text','label'=>'Comentário','format'=>'text'],
        ], collect($data->items()), []);
    }
}
