<?php

namespace Tests\Feature;

use App\Mail\NexoTicketAtribuido;
use App\Models\Crm\CrmServiceRequest;
use App\Models\NotificationIntranet;
use App\Models\NexoTicket;
use App\Models\User;
use App\Services\Nexo\NexoAutoatendimentoService;
use App\Services\Nexo\NexoTicketService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Testa os dois cenários de atribuição automática de responsável + notificação:
 *   1. Ticket criado manualmente via UI (NexoTicket)
 *   2. Ticket aberto pelo cliente via WhatsApp/autoatendimento (CrmServiceRequest)
 *
 * Usa DatabaseTransactions: todos os dados são revertidos após cada teste.
 * Usa Mail::fake(): emails são capturados sem envio real.
 */
class NexoTicketAtribuicaoTest extends TestCase
{
    use DatabaseTransactions;

    // Telefone de teste (fictício)
    private const TELEFONE_TESTE = '5548999990001';
    // Telefone no formato E.164
    private const TELEFONE_E164  = '+5548999990001';

    private User $advogado;
    private int  $crmAccountId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        // Cria o advogado responsável
        $this->advogado = User::factory()->create([
            'name'  => 'Dr. Teste Responsável',
            'email' => 'advogado.teste@mayeradvogados.adv.br',
            'role'  => 'advogado',
        ]);

        // Cria a conta CRM vinculada ao advogado
        $this->crmAccountId = DB::table('crm_accounts')->insertGetId([
            'name'               => 'Cliente Teste Fictício',
            'kind'               => 'client',
            'lifecycle'          => 'ativo',
            'phone_e164'         => self::TELEFONE_E164,
            'owner_user_id'      => $this->advogado->id,
            'datajuri_pessoa_id' => 99999999, // ID fictício
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);
    }

    // =========================================================================
    // CENÁRIO 1: Ticket manual (UI)
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_manual_atribui_responsavel_via_crm(): void
    {
        $service = app(NexoTicketService::class);

        $ticket = $service->criarManual([
            'assunto'      => 'Preciso de informações sobre meu processo',
            'tipo'         => 'geral',
            'prioridade'   => 'normal',
            'nome_cliente' => 'Cliente Teste Fictício',
            'telefone'     => self::TELEFONE_TESTE,
            'mensagem'     => 'Mensagem de teste gerada automaticamente.',
        ], $this->advogado->id);

        $this->assertNotNull($ticket->responsavel_id,
            'Responsável deveria ter sido atribuído via CRM.');

        $this->assertEquals($this->advogado->id, $ticket->responsavel_id,
            'O responsável deveria ser o dono da conta CRM.');

        $this->assertEquals('em_andamento', $ticket->status,
            'Status deveria ser em_andamento quando há responsável.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_manual_cria_notificacao_bell_para_responsavel(): void
    {
        $service = app(NexoTicketService::class);

        $service->criarManual([
            'assunto'      => 'Solicito retorno urgente',
            'tipo'         => 'retorno',
            'prioridade'   => 'urgente',
            'nome_cliente' => 'Cliente Teste Fictício',
            'telefone'     => self::TELEFONE_TESTE,
        ], $this->advogado->id);

        $notificacao = NotificationIntranet::where('user_id', $this->advogado->id)
            ->where('lida', false)
            ->latest()
            ->first();

        $this->assertNotNull($notificacao,
            'Deveria existir notificação bell para o responsável.');

        $this->assertStringContainsString('TKT-', $notificacao->titulo,
            'Título da notificação deveria conter o protocolo.');

        $this->assertStringContainsString('Cliente Teste Fictício', $notificacao->mensagem,
            'Mensagem da notificação deveria conter o nome do cliente.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_manual_envia_email_para_responsavel(): void
    {
        $service = app(NexoTicketService::class);

        $service->criarManual([
            'assunto'      => 'Dúvida sobre audiência',
            'tipo'         => 'geral',
            'prioridade'   => 'normal',
            'nome_cliente' => 'Cliente Teste Fictício',
            'telefone'     => self::TELEFONE_TESTE,
        ], $this->advogado->id);

        Mail::assertSent(NexoTicketAtribuido::class, function ($mail) {
            return $mail->hasTo($this->advogado->email)
                && $mail->responsavel->id === $this->advogado->id
                && str_contains($mail->dados['assunto'] ?? '', 'audiência');
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_manual_sem_telefone_nao_atribui_responsavel(): void
    {
        $service = app(NexoTicketService::class);

        $ticket = $service->criarManual([
            'assunto'    => 'Ticket sem telefone',
            'tipo'       => 'geral',
            'prioridade' => 'normal',
        ], $this->advogado->id);

        $this->assertNull($ticket->responsavel_id,
            'Sem telefone não deve atribuir responsável automaticamente.');

        $this->assertEquals('aberto', $ticket->status,
            'Status deveria ser aberto quando não há responsável.');

        Mail::assertNotSent(NexoTicketAtribuido::class);
    }

    // =========================================================================
    // CENÁRIO 2: Ticket via WhatsApp / autoatendimento
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_whatsapp_atribui_responsavel_via_crm(): void
    {
        $this->seedNexoAuthAttempt(self::TELEFONE_TESTE);

        $service = app(NexoAutoatendimentoService::class);

        $resultado = $service->abrirTicket(
            self::TELEFONE_TESTE,
            'Preciso de segunda via do contrato',
            'Perdi minha cópia e preciso do documento.'
        );

        $this->assertTrue($resultado['sucesso'],
            'abrirTicket deveria retornar sucesso. Erro: ' . ($resultado['mensagem'] ?? ''));

        $ticket = CrmServiceRequest::where('protocolo', $resultado['protocolo'])->first();
        $this->assertNotNull($ticket, 'Ticket deveria ter sido criado no banco.');

        $this->assertEquals($this->advogado->id, $ticket->assigned_to_user_id,
            'O responsável deveria ser o dono da conta CRM.');

        $this->assertEquals('em_andamento', $ticket->status,
            'Status deveria ser em_andamento quando há responsável.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_whatsapp_cria_notificacao_bell_para_responsavel(): void
    {
        $this->seedNexoAuthAttempt(self::TELEFONE_TESTE);

        $service  = app(NexoAutoatendimentoService::class);
        $resultado = $service->abrirTicket(
            self::TELEFONE_TESTE,
            'Quero agendar uma reunião',
        );

        $this->assertTrue($resultado['sucesso']);

        $notificacao = NotificationIntranet::where('user_id', $this->advogado->id)
            ->where('lida', false)
            ->latest()
            ->first();

        $this->assertNotNull($notificacao,
            'Deveria existir notificação bell para o responsável.');

        $this->assertStringContainsString('SIATE-', $notificacao->titulo,
            'Título deve conter o protocolo SIATE.');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_whatsapp_envia_email_para_responsavel(): void
    {
        $this->seedNexoAuthAttempt(self::TELEFONE_TESTE);

        $service = app(NexoAutoatendimentoService::class);
        $service->abrirTicket(
            self::TELEFONE_TESTE,
            'Solicito informações financeiras',
            'Quero saber o saldo dos meus honorários.'
        );

        Mail::assertSent(NexoTicketAtribuido::class, function ($mail) {
            return $mail->hasTo($this->advogado->email)
                && $mail->responsavel->id === $this->advogado->id;
        });
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function ticket_whatsapp_sem_conta_crm_nao_atribui_responsavel(): void
    {
        // Telefone que não existe no CRM
        $telefoneDesconhecido = '5548999990099';
        $this->seedNexoAuthAttempt($telefoneDesconhecido);

        $service  = app(NexoAutoatendimentoService::class);
        $resultado = $service->abrirTicket(
            $telefoneDesconhecido,
            'Contato de número desconhecido',
        );

        $this->assertTrue($resultado['sucesso'],
            'Ticket deve ser criado mesmo sem CRM match.');

        $ticket = CrmServiceRequest::where('protocolo', $resultado['protocolo'])->first();
        $this->assertNotNull($ticket);
        $this->assertNull($ticket->assigned_to_user_id,
            'Sem conta CRM não deve ter responsável atribuído.');
        $this->assertEquals('aberto', $ticket->status);

        Mail::assertNotSent(NexoTicketAtribuido::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Cria um registro em nexo_auth_attempts simulando cliente autenticado.
     */
    private function seedNexoAuthAttempt(string $telefone): void
    {
        DB::table('nexo_auth_attempts')->insert([
            'telefone'       => preg_replace('/\D/', '', $telefone),
            'autenticado_ate' => now()->addHour(),
            'bloqueado'      => false,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }
}
