<?php
namespace App\Http\Controllers\Reports;
use App\Http\Controllers\Controller;
use App\Services\Reports\ReportNexoService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportNexoController extends Controller
{
    protected ReportNexoService $service;
    public function __construct(ReportNexoService $service) { $this->service = $service; }

    public function conversas(Request $request)
    {
        $filters = $request->only(['status','atendente','periodo_de','periodo_ate','sort','dir']);
        $data = $this->service->conversas($filters, (int)$request->get('per_page',25));
        $stats = $this->service->conversasStats();

        $atendentes = \DB::table('wa_conversations as wc')->leftJoin('users as u','u.id','=','wc.assigned_user_id')
            ->whereNotNull('u.name')->distinct()->pluck('u.name')->sort()->toArray();

        return view('reports._report-layout', [
            'reportTitle'=>'Conversas WhatsApp','domainLabel'=>'Atendimento (NEXO)',
            'columns'=>[
                ['key'=>'created_at','label'=>'Abertura','format'=>'date','sortable'=>true],
                ['key'=>'phone','label'=>'Telefone','format'=>'text','sortable'=>false],
                ['key'=>'name','label'=>'Nome','format'=>'text','sortable'=>false],
                ['key'=>'atendente','label'=>'Atendente','format'=>'text','sortable'=>false],
                ['key'=>'total_msgs','label'=>'Msgs','format'=>'text','sortable'=>false],
                ['key'=>'min_1a_resposta','label'=>'1ª Resp (min)','format'=>'text','sortable'=>false],
                ['key'=>'status','label'=>'Status','format'=>'badge','sortable'=>true,
                 'badge_colors'=>['open'=>'bg-blue-100 text-blue-700','closed'=>'bg-gray-100 text-gray-500']],
                ['key'=>'category','label'=>'Categoria','format'=>'text','sortable'=>false],
                ['key'=>'last_message_at','label'=>'Última Msg','format'=>'date','sortable'=>true],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'status','label'=>'Status','type'=>'select','options'=>['open'=>'Aberta','closed'=>'Fechada']],
                ['name'=>'atendente','label'=>'Atendente','type'=>'select','options'=>array_combine($atendentes,$atendentes)],
                ['name'=>'periodo_de','label'=>'De','type'=>'month'],
                ['name'=>'periodo_ate','label'=>'Até','type'=>'month'],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'nexo','report'=>'conversas']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportConversas(Request $request, string $type)
    {
        $data = $this->service->conversas($request->only(['status','atendente','periodo_de','periodo_ate','sort','dir']), 999999);
        return ReportExportService::export($type, 'Conversas WhatsApp', [
            ['key'=>'created_at','label'=>'Abertura','format'=>'date'],['key'=>'phone','label'=>'Telefone','format'=>'text'],
            ['key'=>'name','label'=>'Nome','format'=>'text'],['key'=>'atendente','label'=>'Atendente','format'=>'text'],
            ['key'=>'total_msgs','label'=>'Msgs','format'=>'text'],['key'=>'status','label'=>'Status','format'=>'text'],
        ], collect($data->items()), []);
    }

    public function tickets(Request $request)
    {
        $data = $this->service->tickets($request->only(['status','prioridade','sort','dir']), (int)$request->get('per_page',25));
        return view('reports._report-layout', [
            'reportTitle'=>'Tickets de Atendimento','domainLabel'=>'Atendimento (NEXO)',
            'columns'=>[
                ['key'=>'protocolo','label'=>'Protocolo','format'=>'text','sortable'=>false],
                ['key'=>'nome_cliente','label'=>'Cliente','format'=>'text','sortable'=>false],
                ['key'=>'assunto','label'=>'Assunto','format'=>'text','sortable'=>false,'limit'=>50],
                ['key'=>'tipo','label'=>'Tipo','format'=>'text','sortable'=>false],
                ['key'=>'status','label'=>'Status','format'=>'badge','sortable'=>true,
                 'badge_colors'=>['aberto'=>'bg-blue-100 text-blue-700','em_andamento'=>'bg-amber-100 text-amber-700','resolvido'=>'bg-emerald-100 text-emerald-700']],
                ['key'=>'prioridade','label'=>'Prioridade','format'=>'badge','sortable'=>true,
                 'badge_colors'=>['normal'=>'bg-gray-100 text-gray-600','alta'=>'bg-orange-100 text-orange-700','urgente'=>'bg-red-100 text-red-700']],
                ['key'=>'atendente','label'=>'Atendente','format'=>'text','sortable'=>false],
                ['key'=>'horas_resolucao','label'=>'Horas Resolução','format'=>'text','sortable'=>false],
                ['key'=>'created_at','label'=>'Aberto em','format'=>'date','sortable'=>true],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'status','label'=>'Status','type'=>'select','options'=>['aberto'=>'Aberto','em_andamento'=>'Em Andamento','resolvido'=>'Resolvido']],
                ['name'=>'prioridade','label'=>'Prioridade','type'=>'select','options'=>['normal'=>'Normal','alta'=>'Alta','urgente'=>'Urgente']],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'nexo','report'=>'tickets']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportTickets(Request $request, string $type)
    {
        $data = $this->service->tickets($request->only(['status','prioridade','sort','dir']), 999999);
        return ReportExportService::export($type, 'Tickets NEXO', [
            ['key'=>'protocolo','label'=>'Protocolo','format'=>'text'],['key'=>'nome_cliente','label'=>'Cliente','format'=>'text'],
            ['key'=>'assunto','label'=>'Assunto','format'=>'text'],['key'=>'status','label'=>'Status','format'=>'text'],
            ['key'=>'prioridade','label'=>'Prioridade','format'=>'text'],['key'=>'atendente','label'=>'Atendente','format'=>'text'],
        ], collect($data->items()), []);
    }

    public function qa(Request $request)
    {
        $data = $this->service->qa($request->only(['nps_class','sort','dir']), (int)$request->get('per_page',25));
        return view('reports._report-layout', [
            'reportTitle'=>'Satisfação (QA)','domainLabel'=>'Atendimento (NEXO)',
            'columns'=>[
                ['key'=>'answered_at','label'=>'Data Resposta','format'=>'date','sortable'=>false],
                ['key'=>'score_1_5','label'=>'Nota Serviço (1-5)','format'=>'text','sortable'=>false],
                ['key'=>'nps','label'=>'NPS (0-10)','format'=>'text','sortable'=>false],
                ['key'=>'nps_class','label'=>'Classificação','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['Promotor'=>'bg-emerald-100 text-emerald-700','Neutro'=>'bg-yellow-100 text-yellow-700','Detrator'=>'bg-red-100 text-red-700']],
                ['key'=>'tags','label'=>'Tags','format'=>'text','sortable'=>false],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'nps_class','label'=>'Classificação','type'=>'select','options'=>['promotor'=>'Promotor','neutro'=>'Neutro','detrator'=>'Detrator']],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'nexo','report'=>'qa']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportQa(Request $request, string $type)
    {
        $data = $this->service->qa($request->only(['nps_class','sort','dir']), 999999);
        return ReportExportService::export($type, 'Satisfação QA', [
            ['key'=>'answered_at','label'=>'Data','format'=>'date'],['key'=>'score_1_5','label'=>'Nota','format'=>'text'],
            ['key'=>'nps','label'=>'NPS','format'=>'text'],['key'=>'nps_class','label'=>'Class.','format'=>'text'],
            ['key'=>'tags','label'=>'Tags','format'=>'text'],
        ], collect($data->items()), []);
    }

    public function performanceAtendentes(Request $request)
    {
        $data = $this->service->performanceAtendentes($request->only([]));
        return view('reports._report-layout', [
            'reportTitle'=>'Performance Atendentes','domainLabel'=>'Atendimento (NEXO)',
            'columns'=>[
                ['key'=>'atendente','label'=>'Atendente','format'=>'text','sortable'=>false],
                ['key'=>'total_conversas','label'=>'Conversas','format'=>'text','sortable'=>false],
                ['key'=>'fechadas','label'=>'Fechadas','format'=>'text','sortable'=>false],
                ['key'=>'avg_1a_resp_min','label'=>'Méd. 1ª Resp (min)','format'=>'text','sortable'=>false],
                ['key'=>'tickets_resolvidos','label'=>'Tickets','format'=>'text','sortable'=>false],
            ],
            'data'=>collect($data),'totals'=>[],'filters'=>[],'exportRoute'=>null,
        ]);
    }
}
