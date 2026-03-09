<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\ReportCrmService;
use App\Exports\ReportExportService;
use Illuminate\Http\Request;

class ReportCrmController extends Controller
{
    protected ReportCrmService $service;

    public function __construct(ReportCrmService $service) { $this->service = $service; }

    private function ownerOptions(): array
    {
        $list = \DB::table('crm_accounts as ca')
            ->leftJoin('users as u','u.id','=','ca.owner_user_id')
            ->whereNotNull('u.name')->distinct()->pluck('u.name')->sort()->values()->toArray();
        return array_combine($list, $list);
    }

    private function segmentOptions(): array
    {
        $list = \DB::table('crm_accounts')->whereNotNull('segment')->where('segment','!=','')
            ->distinct()->pluck('segment')->sort()->values()->toArray();
        return array_combine($list, $list);
    }

    // ── REL-C01: Base de Clientes ────────────────────────────
    public function baseClientes(Request $request)
    {
        $filters = $request->only(['kind','lifecycle','segment','responsavel','busca','health_min','health_max','sem_contato_dias','sort','dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->baseClientes($filters, $perPage);
        $stats = $this->service->baseClientesStats();

        $columns = [
            ['key'=>'name','label'=>'Nome','format'=>'text','sortable'=>true],
            ['key'=>'kind','label'=>'Tipo','format'=>'badge','sortable'=>true,
             'badge_colors'=>['client'=>'bg-emerald-100 text-emerald-700','prospect'=>'bg-blue-100 text-blue-700']],
            ['key'=>'lifecycle','label'=>'Lifecycle','format'=>'badge','sortable'=>true,
             'badge_colors'=>['ativo'=>'bg-emerald-100 text-emerald-700','onboarding'=>'bg-blue-100 text-blue-700','adormecido'=>'bg-yellow-100 text-yellow-700','arquivado'=>'bg-gray-100 text-gray-500']],
            ['key'=>'segment','label'=>'Segmento IA','format'=>'text','sortable'=>false,'limit'=>25],
            ['key'=>'health_score','label'=>'Score','format'=>'text','sortable'=>true],
            ['key'=>'processos_ativos','label'=>'Proc. Ativos','format'=>'text','sortable'=>true],
            ['key'=>'receita_acumulada','label'=>'Receita (R$)','format'=>'currency','sortable'=>true],
            ['key'=>'responsavel','label'=>'Responsável','format'=>'text','sortable'=>false],
            ['key'=>'last_touch_at','label'=>'Últ. Contato','format'=>'date','sortable'=>true],
            ['key'=>'dias_sem_contato','label'=>'Dias s/ Contato','format'=>'text','sortable'=>true],
        ];

        $filterDefs = [
            ['name'=>'kind','label'=>'Tipo','type'=>'select','options'=>['client'=>'Cliente','prospect'=>'Prospect']],
            ['name'=>'lifecycle','label'=>'Lifecycle','type'=>'select','options'=>['ativo'=>'Ativo','onboarding'=>'Onboarding','adormecido'=>'Adormecido','arquivado'=>'Arquivado']],
            ['name'=>'segment','label'=>'Segmento IA','type'=>'select','options'=>$this->segmentOptions()],
            ['name'=>'responsavel','label'=>'Responsável','type'=>'select','options'=>$this->ownerOptions()],
            ['name'=>'busca','label'=>'Busca','type'=>'text','placeholder'=>'Nome, email, doc...'],
            ['name'=>'sem_contato_dias','label'=>'Sem contato (dias)','type'=>'select','options'=>['30'=>'30+ dias','60'=>'60+ dias','90'=>'90+ dias']],
        ];

        $exportRoute = route('relatorios.export',['domain'=>'crm','report'=>'base-clientes']).'?'.http_build_query(array_filter($request->all()));

        return view('reports.crm.base-clientes', [
            'reportTitle'=>'Base de Clientes Consolidada','domainLabel'=>'CRM / Clientes',
            'columns'=>$columns,'data'=>$data,'totals'=>[],'filters'=>$filterDefs,
            'exportRoute'=>$exportRoute,'stats'=>$stats,
        ]);
    }

    public function exportBaseclientes(Request $request, string $type)
    {
        $filters = $request->only(['kind','lifecycle','segment','responsavel','busca','health_min','health_max','sem_contato_dias','sort','dir']);
        $data = $this->service->baseClientes($filters, 999999);
        $columns = [
            ['key'=>'name','label'=>'Nome','format'=>'text'],['key'=>'kind','label'=>'Tipo','format'=>'text'],
            ['key'=>'lifecycle','label'=>'Lifecycle','format'=>'text'],['key'=>'segment','label'=>'Segmento','format'=>'text'],
            ['key'=>'health_score','label'=>'Score','format'=>'text'],['key'=>'processos_ativos','label'=>'Proc.','format'=>'text'],
            ['key'=>'receita_acumulada','label'=>'Receita','format'=>'currency'],['key'=>'responsavel','label'=>'Resp.','format'=>'text'],
            ['key'=>'dias_sem_contato','label'=>'Dias s/Contato','format'=>'text'],
        ];
        return ReportExportService::export($type, 'Base de Clientes', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-C02: Pipeline ────────────────────────────────────
    public function pipeline(Request $request)
    {
        $filters = $request->only(['status','stage','tipo_demanda','responsavel','busca','periodo_de','periodo_ate','sort','dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->pipeline($filters, $perPage);
        $stats = $this->service->pipelineStats();

        $stages = \DB::table('crm_stages')->orderBy('order')->pluck('name','name')->toArray();
        $badgeStages = [];
        foreach (\DB::table('crm_stages')->get() as $s) {
            $badgeStages[$s->name] = 'bg-['.$s->color.']/20 text-['.$s->color.']';
        }

        $columns = [
            ['key'=>'title','label'=>'Oportunidade','format'=>'text','sortable'=>true],
            ['key'=>'account_name','label'=>'Account','format'=>'text','sortable'=>false],
            ['key'=>'estagio','label'=>'Estágio','format'=>'badge','sortable'=>false,
             'badge_colors'=>['Lead Novo'=>'bg-blue-100 text-blue-700','Em Contato'=>'bg-violet-100 text-violet-700','Proposta'=>'bg-amber-100 text-amber-700','Negociação'=>'bg-orange-100 text-orange-700','Ganho'=>'bg-emerald-100 text-emerald-700','Perdido'=>'bg-red-100 text-red-700']],
            ['key'=>'status','label'=>'Status','format'=>'badge','sortable'=>true,
             'badge_colors'=>['won'=>'bg-emerald-100 text-emerald-700','lost'=>'bg-red-100 text-red-700','open'=>'bg-blue-100 text-blue-700']],
            ['key'=>'tipo_demanda','label'=>'Demanda','format'=>'text','sortable'=>true],
            ['key'=>'value_estimated','label'=>'Valor Est. (R$)','format'=>'currency','sortable'=>true],
            ['key'=>'responsavel','label'=>'Responsável','format'=>'text','sortable'=>false],
            ['key'=>'dias_pipeline','label'=>'Dias Pipeline','format'=>'text','sortable'=>true],
            ['key'=>'criado_em','label'=>'Criado em','format'=>'date','sortable'=>true],
        ];

        $filterDefs = [
            ['name'=>'status','label'=>'Status','type'=>'select','options'=>['won'=>'Ganho','lost'=>'Perdido','open'=>'Aberto']],
            ['name'=>'stage','label'=>'Estágio','type'=>'select','options'=>$stages],
            ['name'=>'tipo_demanda','label'=>'Demanda','type'=>'text','placeholder'=>'Cível, Trabalhista...'],
            ['name'=>'busca','label'=>'Busca','type'=>'text','placeholder'=>'Título ou account...'],
            ['name'=>'periodo_de','label'=>'Criado De','type'=>'month'],
            ['name'=>'periodo_ate','label'=>'Criado Até','type'=>'month'],
        ];

        $exportRoute = route('relatorios.export',['domain'=>'crm','report'=>'pipeline']).'?'.http_build_query(array_filter($request->all()));

        return view('reports.crm.pipeline', [
            'reportTitle'=>'Pipeline de Oportunidades','domainLabel'=>'CRM / Clientes',
            'columns'=>$columns,'data'=>$data,'totals'=>[],'filters'=>$filterDefs,
            'exportRoute'=>$exportRoute,'stats'=>$stats,
        ]);
    }

    public function exportPipeline(Request $request, string $type)
    {
        $filters = $request->only(['status','stage','tipo_demanda','responsavel','busca','periodo_de','periodo_ate','sort','dir']);
        $data = $this->service->pipeline($filters, 999999);
        $columns = [
            ['key'=>'title','label'=>'Oportunidade','format'=>'text'],['key'=>'account_name','label'=>'Account','format'=>'text'],
            ['key'=>'estagio','label'=>'Estágio','format'=>'text'],['key'=>'status','label'=>'Status','format'=>'text'],
            ['key'=>'value_estimated','label'=>'Valor Est.','format'=>'currency'],['key'=>'responsavel','label'=>'Resp.','format'=>'text'],
            ['key'=>'dias_pipeline','label'=>'Dias','format'=>'text'],
        ];
        return ReportExportService::export($type, 'Pipeline Oportunidades', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-C03: Health Score & Segmentação ──────────────────
    public function healthSegmentacao(Request $request)
    {
        $filters = $request->only(['segment','faixa','kind','busca','sort','dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->healthSegmentacao($filters, $perPage);

        $columns = [
            ['key'=>'name','label'=>'Account','format'=>'text','sortable'=>true],
            ['key'=>'kind','label'=>'Tipo','format'=>'badge','sortable'=>true,
             'badge_colors'=>['client'=>'bg-emerald-100 text-emerald-700','prospect'=>'bg-blue-100 text-blue-700']],
            ['key'=>'lifecycle','label'=>'Lifecycle','format'=>'badge','sortable'=>true,
             'badge_colors'=>['ativo'=>'bg-emerald-100 text-emerald-700','onboarding'=>'bg-blue-100 text-blue-700','adormecido'=>'bg-yellow-100 text-yellow-700','arquivado'=>'bg-gray-100 text-gray-500']],
            ['key'=>'health_score','label'=>'Score','format'=>'text','sortable'=>true],
            ['key'=>'faixa_score','label'=>'Faixa','format'=>'badge','sortable'=>false,
             'badge_colors'=>['Excelente'=>'bg-emerald-200 text-emerald-800','Bom'=>'bg-blue-100 text-blue-700','Atenção'=>'bg-yellow-100 text-yellow-700','Crítico'=>'bg-orange-100 text-orange-700','Perdido'=>'bg-red-200 text-red-800','Sem Score'=>'bg-gray-100 text-gray-500']],
            ['key'=>'segment','label'=>'Segmento IA','format'=>'text','sortable'=>false,'limit'=>22],
            ['key'=>'segment_summary','label'=>'Análise IA','format'=>'text','sortable'=>false,'limit'=>80],
            ['key'=>'receita_12m','label'=>'Receita 12m','format'=>'currency','sortable'=>true],
            ['key'=>'last_touch_at','label'=>'Últ. Contato','format'=>'date','sortable'=>true],
            ['key'=>'responsavel','label'=>'Resp.','format'=>'text','sortable'=>false],
        ];

        $filterDefs = [
            ['name'=>'faixa','label'=>'Faixa Score','type'=>'select','options'=>['excelente'=>'Excelente (80+)','bom'=>'Bom (60-79)','atencao'=>'Atenção (40-59)','critico'=>'Crítico (20-39)','perdido'=>'Perdido (<20)','sem'=>'Sem Score']],
            ['name'=>'segment','label'=>'Segmento IA','type'=>'select','options'=>$this->segmentOptions()],
            ['name'=>'kind','label'=>'Tipo','type'=>'select','options'=>['client'=>'Cliente','prospect'=>'Prospect']],
            ['name'=>'busca','label'=>'Busca','type'=>'text','placeholder'=>'Nome...'],
        ];

        $exportRoute = route('relatorios.export',['domain'=>'crm','report'=>'health-segmentacao']).'?'.http_build_query(array_filter($request->all()));

        return view('reports._report-layout', [
            'reportTitle'=>'Health Score & Segmentação IA','domainLabel'=>'CRM / Clientes',
            'columns'=>$columns,'data'=>$data,'totals'=>[],'filters'=>$filterDefs,
            'exportRoute'=>$exportRoute,
        ]);
    }

    public function exportHealthsegmentacao(Request $request, string $type)
    {
        $filters = $request->only(['segment','faixa','kind','busca','sort','dir']);
        $data = $this->service->healthSegmentacao($filters, 999999);
        $columns = [
            ['key'=>'name','label'=>'Account','format'=>'text'],['key'=>'kind','label'=>'Tipo','format'=>'text'],
            ['key'=>'health_score','label'=>'Score','format'=>'text'],['key'=>'faixa_score','label'=>'Faixa','format'=>'text'],
            ['key'=>'segment','label'=>'Segmento','format'=>'text'],['key'=>'segment_summary','label'=>'Análise IA','format'=>'text'],
            ['key'=>'receita_12m','label'=>'Receita 12m','format'=>'currency'],
        ];
        return ReportExportService::export($type, 'Health Score e Segmentação', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-C04: Atividades CRM ──────────────────────────────
    public function atividades(Request $request)
    {
        $filters = $request->only(['type','purpose','busca','periodo_de','periodo_ate','sort','dir']);
        $perPage = (int) $request->get('per_page', 25);
        $data = $this->service->atividades($filters, $perPage);
        $stats = $this->service->atividadesStats();

        $columns = [
            ['key'=>'criado_em','label'=>'Data','format'=>'date','sortable'=>true],
            ['key'=>'account_name','label'=>'Account','format'=>'text','sortable'=>false],
            ['key'=>'type','label'=>'Tipo','format'=>'badge','sortable'=>true,
             'badge_colors'=>['note'=>'bg-gray-100 text-gray-600','task'=>'bg-blue-100 text-blue-700','call'=>'bg-emerald-100 text-emerald-700','meeting'=>'bg-violet-100 text-violet-700','visit'=>'bg-amber-100 text-amber-700','whatsapp'=>'bg-green-100 text-green-700','whatsapp_incoming'=>'bg-green-50 text-green-600']],
            ['key'=>'purpose','label'=>'Finalidade','format'=>'text','sortable'=>false],
            ['key'=>'title','label'=>'Título','format'=>'text','sortable'=>false,'limit'=>50],
            ['key'=>'body','label'=>'Detalhe','format'=>'text','sortable'=>false,'limit'=>60],
            ['key'=>'criado_por','label'=>'Criado por','format'=>'text','sortable'=>false],
            ['key'=>'done_at','label'=>'Concluído em','format'=>'date','sortable'=>true],
        ];

        $types = ['note'=>'Nota','task'=>'Tarefa','call'=>'Ligação','meeting'=>'Reunião','visit'=>'Visita','whatsapp'=>'WhatsApp Enviado','whatsapp_incoming'=>'WhatsApp Recebido'];

        $filterDefs = [
            ['name'=>'type','label'=>'Tipo','type'=>'select','options'=>$types],
            ['name'=>'busca','label'=>'Busca','type'=>'text','placeholder'=>'Título, body, account...'],
            ['name'=>'periodo_de','label'=>'Período De','type'=>'month'],
            ['name'=>'periodo_ate','label'=>'Período Até','type'=>'month'],
        ];

        $exportRoute = route('relatorios.export',['domain'=>'crm','report'=>'atividades']).'?'.http_build_query(array_filter($request->all()));

        return view('reports._report-layout', [
            'reportTitle'=>'Atividades CRM','domainLabel'=>'CRM / Clientes',
            'columns'=>$columns,'data'=>$data,'totals'=>[],'filters'=>$filterDefs,
            'exportRoute'=>$exportRoute,
        ]);
    }

    public function exportAtividades(Request $request, string $type)
    {
        $filters = $request->only(['type','purpose','busca','periodo_de','periodo_ate','sort','dir']);
        $data = $this->service->atividades($filters, 999999);
        $columns = [
            ['key'=>'criado_em','label'=>'Data','format'=>'date'],['key'=>'account_name','label'=>'Account','format'=>'text'],
            ['key'=>'type','label'=>'Tipo','format'=>'text'],['key'=>'title','label'=>'Título','format'=>'text'],
            ['key'=>'body','label'=>'Detalhe','format'=>'text'],['key'=>'criado_por','label'=>'Criado por','format'=>'text'],
        ];
        return ReportExportService::export($type, 'Atividades CRM', $columns, collect($data->items()), [], 'landscape');
    }

    // ── REL-IA01: SIRIC — Análises de Crédito ────────────────
    public function siric(Request $request)
    {
        $filters = $request->only(['rating','recomendacao','busca','sort','dir']);
        $data = $this->service->siricConsultas($filters, (int)$request->get('per_page',25));

        return view('reports._report-layout', [
            'reportTitle'=>'SIRIC — Análises de Crédito (IA)','domainLabel'=>'CRM / Clientes & Leads',
            'columns'=>[
                ['key'=>'created_at','label'=>'Data','format'=>'date','sortable'=>true],
                ['key'=>'nome','label'=>'Nome','format'=>'text','sortable'=>false],
                ['key'=>'cpf_cnpj','label'=>'CPF/CNPJ','format'=>'text','sortable'=>false],
                ['key'=>'valor_total','label'=>'Valor (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'parcelas_desejadas','label'=>'Parcelas','format'=>'text','sortable'=>false],
                ['key'=>'renda_declarada','label'=>'Renda (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'rating','label'=>'Rating IA','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['A'=>'bg-emerald-200 text-emerald-800','B'=>'bg-blue-100 text-blue-700','C'=>'bg-amber-100 text-amber-700','D'=>'bg-orange-200 text-orange-800','E'=>'bg-red-200 text-red-800']],
                ['key'=>'score','label'=>'Score IA','format'=>'text','sortable'=>false],
                ['key'=>'recomendacao','label'=>'Recomendação IA','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['aprovado'=>'bg-emerald-100 text-emerald-700','aprovado_condicional'=>'bg-amber-100 text-amber-700','negado'=>'bg-red-100 text-red-700']],
                ['key'=>'decisao_humana','label'=>'Decisão Humana','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['aprovado'=>'bg-emerald-100 text-emerald-700','negado'=>'bg-red-100 text-red-700']],
                ['key'=>'analista','label'=>'Analista','format'=>'text','sortable'=>false],
                ['key'=>'resumo_ia','label'=>'Resumo IA','format'=>'text','sortable'=>false,'limit'=>100],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'rating','label'=>'Rating','type'=>'select','options'=>['A'=>'A','B'=>'B','C'=>'C','D'=>'D','E'=>'E']],
                ['name'=>'recomendacao','label'=>'Recomendação','type'=>'select','options'=>['aprovado'=>'Aprovado','aprovado_condicional'=>'Condicional','negado'=>'Negado']],
                ['name'=>'busca','label'=>'Busca','type'=>'text','placeholder'=>'Nome ou CPF...'],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'crm','report'=>'siric']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportSiric(Request $request, string $type)
    {
        $data = $this->service->siricConsultas($request->only(['rating','recomendacao','busca','sort','dir']), 999999);
        return ReportExportService::export($type, 'SIRIC Análises Crédito IA', [
            ['key'=>'created_at','label'=>'Data','format'=>'date'],['key'=>'nome','label'=>'Nome','format'=>'text'],
            ['key'=>'cpf_cnpj','label'=>'CPF/CNPJ','format'=>'text'],['key'=>'valor_total','label'=>'Valor','format'=>'currency'],
            ['key'=>'rating','label'=>'Rating','format'=>'text'],['key'=>'score','label'=>'Score','format'=>'text'],
            ['key'=>'recomendacao','label'=>'Recom. IA','format'=>'text'],['key'=>'decisao_humana','label'=>'Decisão','format'=>'text'],
            ['key'=>'resumo_ia','label'=>'Resumo IA','format'=>'text'],
        ], collect($data->items()), []);
    }

    // ── REL-IA02: SIPEX — Propostas Precificação IA ──────────
    public function sipex(Request $request)
    {
        $filters = $request->only(['area','status','recomendacao','busca','sort','dir']);
        $data = $this->service->sipexPropostas($filters, (int)$request->get('per_page',25));

        return view('reports._report-layout', [
            'reportTitle'=>'SIPEX — Propostas de Honorários (IA)','domainLabel'=>'CRM / Clientes & Leads',
            'columns'=>[
                ['key'=>'created_at','label'=>'Data','format'=>'date','sortable'=>true],
                ['key'=>'nome_proponente','label'=>'Proponente','format'=>'text','sortable'=>false],
                ['key'=>'tipo_pessoa','label'=>'Tipo','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['PF'=>'bg-blue-100 text-blue-700','PJ'=>'bg-violet-100 text-violet-700']],
                ['key'=>'area_direito','label'=>'Área','format'=>'text','sortable'=>false,'limit'=>25],
                ['key'=>'tipo_acao','label'=>'Tipo Ação','format'=>'text','sortable'=>false,'limit'=>30],
                ['key'=>'valor_rapida','label'=>'Rápida (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'valor_equilibrada','label'=>'Equilibrada (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'valor_premium','label'=>'Premium (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'recomendacao_ia','label'=>'Recom. IA','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['rapida'=>'bg-blue-100 text-blue-700','equilibrada'=>'bg-emerald-100 text-emerald-700','premium'=>'bg-amber-100 text-amber-700']],
                ['key'=>'valor_final','label'=>'Valor Final (R$)','format'=>'currency','sortable'=>false],
                ['key'=>'status','label'=>'Status','format'=>'badge','sortable'=>false,
                 'badge_colors'=>['gerada'=>'bg-blue-100 text-blue-700','aceita'=>'bg-emerald-100 text-emerald-700','rejeitada'=>'bg-red-100 text-red-700','enviada'=>'bg-amber-100 text-amber-700']],
                ['key'=>'advogado','label'=>'Advogado','format'=>'text','sortable'=>false],
                ['key'=>'justificativa_ia','label'=>'Justificativa IA','format'=>'text','sortable'=>false,'limit'=>80],
            ],
            'data'=>$data,'totals'=>[],
            'filters'=>[
                ['name'=>'area','label'=>'Área','type'=>'text','placeholder'=>'Cível, Trabalhista...'],
                ['name'=>'status','label'=>'Status','type'=>'select','options'=>['gerada'=>'Gerada','aceita'=>'Aceita','enviada'=>'Enviada','rejeitada'=>'Rejeitada']],
                ['name'=>'recomendacao','label'=>'Recom. IA','type'=>'select','options'=>['rapida'=>'Rápida','equilibrada'=>'Equilibrada','premium'=>'Premium']],
                ['name'=>'busca','label'=>'Busca','type'=>'text','placeholder'=>'Nome ou tipo ação...'],
            ],
            'exportRoute'=>route('relatorios.export',['domain'=>'crm','report'=>'sipex']).'?'.http_build_query(array_filter($request->all())),
        ]);
    }

    public function exportSipex(Request $request, string $type)
    {
        $data = $this->service->sipexPropostas($request->only(['area','status','recomendacao','busca','sort','dir']), 999999);
        return ReportExportService::export($type, 'SIPEX Propostas IA', [
            ['key'=>'created_at','label'=>'Data','format'=>'date'],['key'=>'nome_proponente','label'=>'Proponente','format'=>'text'],
            ['key'=>'area_direito','label'=>'Área','format'=>'text'],['key'=>'tipo_acao','label'=>'Tipo Ação','format'=>'text'],
            ['key'=>'valor_rapida','label'=>'Rápida','format'=>'currency'],['key'=>'valor_equilibrada','label'=>'Equilibrada','format'=>'currency'],
            ['key'=>'valor_premium','label'=>'Premium','format'=>'currency'],['key'=>'recomendacao_ia','label'=>'Recom.','format'=>'text'],
            ['key'=>'valor_final','label'=>'Final','format'=>'currency'],['key'=>'justificativa_ia','label'=>'Justificativa IA','format'=>'text'],
        ], collect($data->items()), []);
    }

}
