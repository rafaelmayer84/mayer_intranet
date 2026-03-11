<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class CrmCheckInadimplencia extends Command
{
    protected $signature = 'crm:check-inadimplencia';
    protected $description = 'Verifica contas vencidas, notifica responsáveis e escala se não houver ação de cobrança';

    private const CHEFIA_USER_ID = 1;

    public function handle(): int
    {
        $hoje = Carbon::today();
        $hojeFmt = $hoje->toDateString();
        $notificados = 0;
        $escalados = 0;

        $inadimplentes = DB::table('contas_receber')
            ->where('is_stale', false)
            ->where('status', 'Não lançado')
            ->whereNotNull('data_vencimento')
            ->where('data_vencimento', '<', $hojeFmt)
            ->selectRaw('pessoa_datajuri_id, cliente, COUNT(*) as qty, SUM(valor) as total, MIN(data_vencimento) as venc_mais_antiga, MAX(data_vencimento) as venc_mais_recente')
            ->groupBy('pessoa_datajuri_id', 'cliente')
            ->having('qty', '>', 0)
            ->get();

        foreach ($inadimplentes as $inad) {
            $account = DB::table('crm_accounts')
                ->where('datajuri_pessoa_id', $inad->pessoa_datajuri_id)
                ->whereNotIn('lifecycle', ['archived'])
                ->first(['id', 'name', 'owner_user_id']);

            if (!$account) continue;

            $responsavelId = $account->owner_user_id ?? self::CHEFIA_USER_ID;
            $link = url('/crm/accounts/' . $account->id);
            $valorFmt = 'R$ ' . number_format($inad->total, 2, ',', '.');
            $cliente = $inad->cliente ?? $account->name;
            $diasAtraso = Carbon::parse($inad->venc_mais_antiga)->diffInDays($hoje);

            // Verificar se já notificou hoje para este cliente
            $jaNotificou = DB::table('notifications_intranet')
                ->where('user_id', $responsavelId)
                ->where('tipo', 'LIKE', 'crm_inadim%')
                ->where('link', $link)
                ->whereDate('created_at', $hojeFmt)
                ->exists();

            if ($jaNotificou) continue;

            // Verificar se houve interação de cobrança recente para este account
            $ultimaCobranca = DB::table('crm_activities')
                ->where('account_id', $account->id)
                ->where('purpose', 'cobranca')
                ->orderByDesc('created_at')
                ->first(['created_at']);

            $diasSemCobranca = $ultimaCobranca
                ? Carbon::parse($ultimaCobranca->created_at)->diffInDays($hoje)
                : null;

            // Determinar nível de escalação
            $nivel = $this->determinarNivel($diasAtraso, $diasSemCobranca);

            if ($nivel === 'amigavel') {
                DB::table('notifications_intranet')->insert([
                    'user_id'    => $responsavelId,
                    'tipo'       => 'crm_inadimplencia',
                    'titulo'     => 'Cobrança pendente: ' . mb_substr($cliente, 0, 40),
                    'mensagem'   => "{$cliente} possui {$inad->qty} título(s) vencido(s) totalizando {$valorFmt} ({$diasAtraso} dia(s) de atraso). Acesse o CRM e registre uma interação de cobrança (tipo: ligação, WhatsApp ou visita / propósito: cobrança).",
                    'link'       => $link,
                    'icone'      => 'alert-circle',
                    'lida'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $notificados++;

            } elseif ($nivel === 'reiteracao') {
                $cobMsg = $diasSemCobranca === null
                    ? 'Nenhuma interação de cobrança registrada até o momento.'
                    : "Última cobrança registrada há {$diasSemCobranca} dia(s).";

                DB::table('notifications_intranet')->insert([
                    'user_id'    => $responsavelId,
                    'tipo'       => 'crm_inadimplencia',
                    'titulo'     => '⚠ Cobrança não registrada: ' . mb_substr($cliente, 0, 35),
                    'mensagem'   => "REITERAÇÃO: {$cliente} possui {$inad->qty} título(s) vencido(s) ({$valorFmt}, {$diasAtraso}d de atraso). {$cobMsg} Registre uma interação de cobrança no CRM com urgência. A chefia será notificada caso a ação não seja registrada.",
                    'link'       => $link,
                    'icone'      => 'alert-triangle',
                    'lida'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $notificados++;

            } elseif ($nivel === 'escalacao') {
                $responsavel = DB::table('users')->where('id', $responsavelId)->first();
                $nomeResp = $responsavel->name ?? 'Não atribuído';
                $cobMsg = $diasSemCobranca === null
                    ? 'NUNCA foi registrada interação de cobrança.'
                    : "Última cobrança há {$diasSemCobranca} dia(s).";

                // Notificação para o advogado
                DB::table('notifications_intranet')->insert([
                    'user_id'    => $responsavelId,
                    'tipo'       => 'crm_inadimplencia',
                    'titulo'     => '⚠ Inadimplência sem ação: ' . mb_substr($cliente, 0, 35),
                    'mensagem'   => "ESCALAÇÃO: {$cliente} possui {$inad->qty} título(s) vencido(s) ({$valorFmt}, {$diasAtraso}d). {$cobMsg} A chefia foi notificada. A omissão na cobrança impacta o indicador de conformidade (PEN-D04) e será considerada na apuração do GDP.",
                    'link'       => $link,
                    'icone'      => 'alert-triangle',
                    'lida'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Notificação para chefia
                if ($responsavelId !== self::CHEFIA_USER_ID) {
                    DB::table('notifications_intranet')->insert([
                        'user_id'    => self::CHEFIA_USER_ID,
                        'tipo'       => 'crm_inadimplencia_chefia',
                        'titulo'     => "Inadimplência sem cobrança: {$nomeResp}",
                        'mensagem'   => "{$nomeResp} não registrou cobrança para {$cliente} — {$inad->qty} título(s), {$valorFmt}, {$diasAtraso}d de atraso. {$cobMsg}",
                        'link'       => $link,
                        'icone'      => 'alert-triangle',
                        'lida'       => false,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Email escalação
                if ($responsavel && $responsavel->email) {
                    try {
                        $chefia = DB::table('users')->where('id', self::CHEFIA_USER_ID)->first();
                        $ccEmail = $chefia && $chefia->email ? $chefia->email : null;

                        Mail::raw(
                            "Prezado(a) {$nomeResp},\n\n" .
                            "ESCALAÇÃO — INADIMPLÊNCIA SEM AÇÃO DE COBRANÇA\n\n" .
                            "O cliente {$cliente} possui {$inad->qty} título(s) vencido(s) totalizando {$valorFmt}, com {$diasAtraso} dias de atraso.\n\n" .
                            "{$cobMsg}\n\n" .
                            "Ações requeridas:\n" .
                            "1. Realize contato imediato com o cliente (telefone, WhatsApp ou visita).\n" .
                            "2. Registre a interação no CRM com propósito 'cobrança'.\n" .
                            "3. Registre o resultado: pagou, negociou prazo ou não atendeu.\n\n" .
                            "A ausência de ação de cobrança configura ocorrência de conformidade (PEN-D04) no GDP.\n\n" .
                            "Acesse: {$link}\n\n" .
                            "Atenciosamente,\nGestão — Mayer Advogados\n(Mensagem automática do Sistema RESULTADOS!)",
                            function ($msg) use ($responsavel, $ccEmail, $cliente) {
                                $msg->to($responsavel->email)
                                    ->subject("⚠ ESCALAÇÃO: Inadimplência sem cobrança — {$cliente}");
                                if ($ccEmail) {
                                    $msg->cc($ccEmail);
                                }
                            }
                        );
                    } catch (\Exception $e) {
                        Log::warning('CRM Inadimplência: falha email escalação', ['user' => $responsavelId, 'error' => $e->getMessage()]);
                    }
                }

                $escalados++;
                $notificados++;
            }
        }

        $msg = "Inadimplência: {$inadimplentes->count()} clientes, {$notificados} notificados, {$escalados} escalados à chefia.";
        $this->info($msg);
        Log::info('CRM Inadimplência', ['clientes' => $inadimplentes->count(), 'notificados' => $notificados, 'escalados' => $escalados]);

        return self::SUCCESS;
    }

    /**
     * Determinar nível de notificação:
     * - amigavel: atraso <= 5 dias OU cobrança registrada nos últimos 3 dias
     * - reiteracao: atraso 6-14 dias sem cobrança recente (últimos 3 dias)
     * - escalacao: atraso 15+ dias sem cobrança recente OU nunca cobrado com 6+ dias
     */
    private function determinarNivel(int $diasAtraso, ?int $diasSemCobranca): string
    {
        $cobrancaRecente = $diasSemCobranca !== null && $diasSemCobranca <= 3;

        if ($cobrancaRecente) {
            return 'amigavel';
        }

        if ($diasAtraso <= 5) {
            return 'amigavel';
        }

        if ($diasAtraso <= 14) {
            return 'reiteracao';
        }

        return 'escalacao';
    }
}
