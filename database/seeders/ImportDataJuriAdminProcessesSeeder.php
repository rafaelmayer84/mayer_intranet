<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Crm\CrmAdminProcess;
use App\Models\Crm\CrmAdminProcessTimeline;
use Carbon\Carbon;

/**
 * Importa 3 processos do DataJuri para o módulo de processos administrativos.
 *
 * Processos: 00243 (CMA), 00255 (Morra), 00260 (Raimondi)
 * Executar: php artisan db:seed --class=ImportDataJuriAdminProcessesSeeder
 */
class ImportDataJuriAdminProcessesSeeder extends Seeder
{
    // IDs confirmados via tinker
    const USER_RAFAEL  = 1;
    const USER_ANELISE = 7;
    const ACCOUNT_CMA      = 17;
    const ACCOUNT_MORRA    = 306;
    const ACCOUNT_RAIMONDI = 1508;

    public function run(): void
    {
        $this->importar00243();
        $this->importar00255();
        $this->importar00260();
    }

    // ── 00243 · CMA Administradora · ACP Praia Brava ───────────────────────────

    private function importar00243(): void
    {
        $processo = CrmAdminProcess::create([
            'protocolo'       => 'ADM-2025-00243',
            'account_id'      => self::ACCOUNT_CMA,
            'tipo'            => 'acompanhamento_extrajudicial',
            'titulo'          => 'ACP 08.2023.00101911-6 — Acordo com MP/10ª PJ (permuta terras Praia Brava)',
            'descricao'       => 'O cliente solicita a intervenção do advogado para promover o andamento do processo administrativo ACP 08.2023.00101911-6, que foi convertido em procedimento judicial. Desde maio, o processo encontra-se paralisado na 10ª PJ, pendente de assinatura do Promotor de Justiça; constatou-se que a titular está afastada da comarca, o que tem ocasionado morosidade no caso. O feito envolve a permuta de terras de alto valor na Praia Brava e no Distrito Industrial de Itajaí. Até o momento, não se verifica pretensão resistida.',
            'status'          => 'suspenso',
            'prioridade'      => 'alta',
            'owner_user_id'   => self::USER_RAFAEL,
            'orgao_destino'   => '10ª Promotoria de Justiça',
            'numero_externo'  => 'ACP 08.2023.00101911-6',
            'prazo_final'     => '2025-11-28',
            'suspended_reason'=> 'Demanda sobrestada para cumprimento de exigência externa (processo foi remetido à coordenadoria técnica novamente para análise).',
            'suspended_at'    => Carbon::parse('2026-01-30'),
            'client_visible'  => false,
        ]);

        $this->timeline($processo, [
            ['2025-10-20', 'andamento_manual', 'Reunião com Dr. Dornelles na procuradoria Geral do Município para compreender o que ocorre. PMI não se opõe — foco do problema está na 10ª PJ.'],
            ['2025-10-20', 'andamento_manual', 'Processo recebido na unidade.'],
            ['2025-10-27', 'andamento_manual', 'Conclusão do processo na unidade.'],
            ['2025-10-29', 'andamento_manual', 'Cancelada a audiência. Promotora estava em audiência e não pode sair. Ficou com remarcação provável para o dia 30/10.'],
            ['2025-10-29', 'andamento_manual', 'Conclusão do processo na unidade.'],
            ['2025-10-29', 'andamento_manual', 'Audiência marcada na 10ª PJ para as 15h.'],
            ['2025-10-29', 'andamento_manual', 'Processo recebido na unidade.'],
            ['2025-10-30', 'andamento_manual', 'Audiência realizada na 10ª PJ (15h–17h30). Presença da Dra. Cristina Balceiro da Motta.'],
            ['2026-01-30', 'suspenso',         'Demanda sobrestada para cumprimento de exigência externa (processo foi remetido à coordenadoria técnica novamente para análise).'],
        ]);

        $this->command->info("✔ 00243 — CMA Administradora importado (id: {$processo->id})");
    }

    // ── 00255 · Luis Alberto Lima Morra · Transferência MAXIN COMPANY (BVI) ───

    private function importar00255(): void
    {
        $processo = CrmAdminProcess::create([
            'protocolo'       => 'ADM-2026-00255',
            'account_id'      => self::ACCOUNT_MORRA,
            'tipo'            => 'transferencia_imovel',
            'titulo'          => 'Transferência de imóveis em BC para empresa estrangeira MAXIN COMPANY LTD (BVI)',
            'descricao'       => 'Planejamento e execução da transferência de titularidade de imóveis localizados em Balneário Camboriú/SC para a empresa estrangeira MAXIN COMPANY LTD., registrada nas Ilhas Virgens Britânicas (BVI). O cliente é proprietário de dois apartamentos e vagas de garagem adquiridos por escritura pública em 2002, com interesse em transferi-los para a MAXIN COMPANY LTD. (BVI), da qual é beneficiário econômico, com finalidade de organização patrimonial e governança. Escopo: (i) due diligence registral e fiscal; (ii) validação e preparação da documentação da empresa estrangeira (poderes, traduções juramentadas, apostilamentos); (iii) saneamento das restrições registrais (penhora antiga no Ap. 901); (iv) lavratura da escritura pública; (v) acompanhamento até o registro definitivo. Execução por marcos — custos externos não incluídos nos honorários.',
            'status'          => 'em_andamento',
            'prioridade'      => 'normal',
            'owner_user_id'   => self::USER_ANELISE,
            'com_user_id'     => self::USER_RAFAEL,
            'orgao_destino'   => 'Cartório de Registro de Imóveis — BC',
            'prazo_final'     => '2026-07-31',
            'client_visible'  => false,
        ]);

        $this->timeline($processo, [
            ['2026-02-02', 'andamento_manual', 'Processo recebido na unidade.'],
            ['2026-02-02', 'andamento_manual', 'Diligências para obtenção do processo judicial constante na matrícula. Processo SAJ nº 0015695-73.1999.8.24.0005 — será necessária petição de pedido de desarquivamento.'],
            ['2026-02-03', 'andamento_manual', 'Atendimento solicitado na central 212178-JHRQJR-2026.'],
            ['2026-02-03', 'andamento_manual', 'Processo recebido na unidade.'],
            ['2026-02-05', 'andamento_manual', 'Novo pedido de desarquivamento solicitado na central (212542-OILNQE-2026).'],
            ['2026-02-05', 'andamento_manual', 'Reabertura do processo na unidade.'],
            ['2026-03-11', 'andamento_manual', 'Processo recebido na unidade.'],
            ['2026-03-11', 'andamento_manual', 'Processo desarquivado — 0015695-73.1999.8.24.0005 — penhoras canceladas.'],
            ['2026-04-08', 'andamento_manual', 'Processo remetido pela unidade para Anelise Muller.'],
        ]);

        $this->command->info("✔ 00255 — Luis Alberto Lima Morra importado (id: {$processo->id})");
    }

    // ── 00260 · Artefatos de Cimento Raimondi · Consultoria Governança ─────────

    private function importar00260(): void
    {
        $processo = CrmAdminProcess::create([
            'protocolo'       => 'ADM-2026-00260',
            'account_id'      => self::ACCOUNT_RAIMONDI,
            'tipo'            => 'consultoria_assessoria',
            'titulo'          => 'Consultoria governança operacional e gestão contratual',
            'descricao'       => 'Consultoria jurídica contínua e incremental junto à empresa. Escopo: (i) diagnóstico de rotinas e gargalos por setor; (ii) estruturação de sistema de governança documental com manuais, procedimentos e checklists operacionais; (iii) implantação de rotina de gestão contratual (minutas-base, trilha de aprovações, controle de prazos); (iv) elaboração de documentos internos de governança; (v) aconselhamento jurídico-administrativo de rotina. Entregáveis organizados em etapas, dependentes do fornecimento de informações pela contratante.',
            'status'          => 'em_andamento',
            'prioridade'      => 'normal',
            'owner_user_id'   => self::USER_ANELISE,
            'prazo_final'     => '2026-06-30',
            'client_visible'  => false,
        ]);

        $this->timeline($processo, [
            ['2026-02-23', 'andamento_manual', 'Processo recebido na unidade.'],
        ]);

        $this->command->info("✔ 00260 — Artefatos de Cimento Raimondi importado (id: {$processo->id})");
    }

    // ── Helper ─────────────────────────────────────────────────────────────────

    private function timeline(CrmAdminProcess $processo, array $entradas): void
    {
        foreach ($entradas as [$data, $tipo, $titulo]) {
            CrmAdminProcessTimeline::create([
                'admin_process_id' => $processo->id,
                'tipo'             => $tipo,
                'titulo'           => $titulo,
                'happened_at'      => Carbon::parse($data),
                'user_id'          => self::USER_RAFAEL,
            ]);
        }
    }
}
