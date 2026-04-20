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
    protected $description = 'Verifica contas vencidas, cria tarefas de cobrança, notifica responsáveis e escala se não houver ação';

    private const CHEFIA_USER_ID = 1;

    public function handle(): int
    {
        $hoje = Carbon::today();
        $hojeFmt = $hoje->toDateString();
        $notificados = 0;
        $escalados = 0;
        $tarefasCriadas = 0;

        // Expirar decisões "aguardar" vencidas e notificar admin para revisão
        $this->expirarDecisoesVencidas($hoje);

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
                ->whereNotIn('lifecycle', ['arquivado'])
                ->first(['id', 'name', 'owner_user_id']);

            if (!$account) continue;

            // Pular conta com decisão ativa de "aguardar" (deliberada pelo admin)
            $temDecisaoAtiva = DB::table('crm_inadimplencia_decisoes')
                ->where('account_id', $account->id)
                ->where('status', 'ativa')
                ->where('decisao', 'aguardar')
                ->where('prazo_revisao', '>=', $hojeFmt)
                ->exists();

            if ($temDecisaoAtiva) continue;

            // Pular conta sinistrada (encerramento permanente)
            $sinistrada = DB::table('crm_inadimplencia_decisoes')
                ->where('account_id', $account->id)
                ->where('status', 'ativa')
                ->where('decisao', 'sinistrar')
                ->exists();

            if ($sinistrada) continue;

            $responsavelId = $account->owner_user_id ?? self::CHEFIA_USER_ID;
            $link = url('/crm/accounts/' . $account->id);
            $valorFmt = 'R$ ' . number_format($inad->total, 2, ',', '.');
            $cliente = $inad->cliente ?? $account->name;
            $diasAtraso = Carbon::parse($inad->venc_mais_antiga)->diffInDays($hoje);

            // Verificar última interação de cobrança
            $ultimaCobranca = DB::table('crm_activities')
                ->where('account_id', $account->id)
                ->where('purpose', 'cobranca')
                ->orderByDesc('created_at')
                ->first(['created_at']);

            $diasSemCobranca = $ultimaCobranca
                ? Carbon::parse($ultimaCobranca->created_at)->diffInDays($hoje)
                : null;

            $nivel = $this->determinarNivel($diasAtraso, $diasSemCobranca);

            // Amigável e Reiteração: criar tarefa formal de cobrança (idempotente)
            if (in_array($nivel, ['amigavel', 'reiteracao'])) {
                $tarefaAberta = DB::table('crm_activities')
                    ->where('account_id', $account->id)
                    ->where('type', 'task')
                    ->where('purpose', 'cobranca')
                    ->where('requires_evidence', true)
                    ->whereNull('done_at')
                    ->exists();

                if (!$tarefaAberta) {
                    $prazo = $nivel === 'amigavel'
                        ? $hoje->copy()->addDays(3)->toDateTimeString()
                        : $hoje->copy()->addDay()->toDateTimeString();

                    DB::table('crm_activities')->insert([
                        'account_id'         => $account->id,
                        'type'               => 'task',
                        'purpose'            => 'cobranca',
                        'requires_evidence'  => true,
                        'title'              => "Cobrar inadimplência: {$cliente}",
                        'body'               => "{$inad->qty} título(s) vencido(s) — {$valorFmt} — {$diasAtraso}d de atraso. Registre o contato e anexe a evidência (print WhatsApp, e-mail, etc.).",
                        'due_at'             => $prazo,
                        'created_by_user_id' => self::CHEFIA_USER_ID,
                        'created_at'         => now(),
                        'updated_at'         => now(),
                    ]);
                    $tarefasCriadas++;
                }
            }

            // Deduplicar notificação do dia
            $jaNotificou = DB::table('notifications_intranet')
                ->where('user_id', $responsavelId)
                ->where('tipo', 'LIKE', 'crm_inadim%')
                ->where('link', $link)
                ->whereDate('created_at', $hojeFmt)
                ->exists();

            if ($jaNotificou) continue;

            if ($nivel === 'amigavel') {
                DB::table('notifications_intranet')->insert([
                    'user_id'    => $responsavelId,
                    'tipo'       => 'crm_inadimplencia',
                    'titulo'     => 'Cobrança pendente: ' . mb_substr($cliente, 0, 40),
                    'mensagem'   => "{$cliente} possui {$inad->qty} título(s) vencido(s) totalizando {$valorFmt} ({$diasAtraso} dia(s) de atraso). Uma tarefa de cobrança foi criada — registre o contato e anexe a evidência.",
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
                    'mensagem'   => "REITERAÇÃO: {$cliente} possui {$inad->qty} título(s) vencido(s) ({$valorFmt}, {$diasAtraso}d de atraso). {$cobMsg} A tarefa de cobrança exige evidência anexada. A chefia será notificada se não houver ação.",
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

                DB::table('notifications_intranet')->insert([
                    'user_id'    => $responsavelId,
                    'tipo'       => 'crm_inadimplencia',
                    'titulo'     => '⚠ Inadimplência sem ação: ' . mb_substr($cliente, 0, 35),
                    'mensagem'   => "ESCALAÇÃO: {$cliente} possui {$inad->qty} título(s) vencido(s) ({$valorFmt}, {$diasAtraso}d). {$cobMsg} A chefia foi notificada. A omissão impacta o indicador PEN-D04 no GDP.",
                    'link'       => $link,
                    'icone'      => 'alert-triangle',
                    'lida'       => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($responsavelId !== self::CHEFIA_USER_ID) {
                    // Verificar se chefia já foi notificada hoje para esta escalação
                    $chefiaJaNotificada = DB::table('notifications_intranet')
                        ->where('user_id', self::CHEFIA_USER_ID)
                        ->where('tipo', 'crm_inadimplencia_chefia')
                        ->where('link', $link)
                        ->whereDate('created_at', $hojeFmt)
                        ->exists();

                    if (!$chefiaJaNotificada) {
                        DB::table('notifications_intranet')->insert([
                            'user_id'    => self::CHEFIA_USER_ID,
                            'tipo'       => 'crm_inadimplencia_chefia',
                            'titulo'     => "Inadimplência sem cobrança: {$nomeResp}",
                            'mensagem'   => "{$nomeResp} não registrou cobrança para {$cliente} — {$inad->qty} título(s), {$valorFmt}, {$diasAtraso}d de atraso. {$cobMsg} Acesse para deliberar: Aguardar / Renegociar / Sinistrar.",
                            'link'       => $link,
                            'icone'      => 'alert-triangle',
                            'lida'       => false,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

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
                            "3. Anexe a evidência (print, e-mail, etc.) na tarefa aberta no CRM.\n\n" .
                            "A ausência de ação configura ocorrência PEN-D04 no GDP.\n\n" .
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

        $msg = "Inadimplência: {$inadimplentes->count()} clientes, {$notificados} notificados, {$escalados} escalados à chefia, {$tarefasCriadas} tarefas criadas.";
        $this->info($msg);
        Log::info('CRM Inadimplência', [
            'clientes'       => $inadimplentes->count(),
            'notificados'    => $notificados,
            'escalados'      => $escalados,
            'tarefas_criadas' => $tarefasCriadas,
        ]);

        return self::SUCCESS;
    }

    private function expirarDecisoesVencidas(Carbon $hoje): void
    {
        $expiradas = DB::table('crm_inadimplencia_decisoes')
            ->where('status', 'ativa')
            ->where('decisao', 'aguardar')
            ->where('prazo_revisao', '<', $hoje->toDateString())
            ->get(['id', 'account_id']);

        foreach ($expiradas as $dec) {
            DB::table('crm_inadimplencia_decisoes')
                ->where('id', $dec->id)
                ->update(['status' => 'expirada', 'updated_at' => now()]);

            $account = DB::table('crm_accounts')->where('id', $dec->account_id)->first(['name']);
            $link = url('/crm/accounts/' . $dec->account_id);

            DB::table('notifications_intranet')->insert([
                'user_id'    => self::CHEFIA_USER_ID,
                'tipo'       => 'crm_inadimplencia_revisao',
                'titulo'     => 'Revisão de inadimplência: ' . mb_substr($account->name ?? 'Cliente', 0, 40),
                'mensagem'   => "O prazo de 30 dias para aguardar a inadimplência de " . ($account->name ?? 'cliente') . " expirou. É necessário deliberar novamente: Aguardar, Renegociar ou Sinistrar.",
                'link'       => $link,
                'icone'      => 'clock',
                'lida'       => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('CRM Inadimplência: decisão aguardar expirada', ['decisao_id' => $dec->id, 'account_id' => $dec->account_id]);
        }
    }

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
