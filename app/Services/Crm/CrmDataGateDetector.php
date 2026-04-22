<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmAccount;
use App\Models\Crm\CrmAccountDataGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Detecta divergencias entre DataJuri (status_pessoa) e a realidade derivada
 * (contratos, processos, contas_receber) das contas CRM.
 *
 * DJ e read-only pra gente: o unico jeito de corrigir a fonte e forcar o
 * advogado responsavel (owner) a ajustar no DJ. Este detector abre um gate
 * que bloqueia o acesso a conta no CRM ate a revisao ser feita.
 *
 * Regras (5 tipos):
 *   onboarding_com_contrato    — DJ Onboarding mas ha contrato/processo ativo
 *   status_cliente_sem_vinculo — DJ Cliente mas sem contrato nem processo
 *   adversa_com_contrato       — DJ Adversa/Contraparte/Fornecedor mas temos contrato
 *   inadimplencia_suspeita_2099 — contas c/ data_vencimento=2099-12-31 e DJ nao sinaliza
 *   sem_status_pessoa          — DJ nao veio com status_pessoa
 */
class CrmDataGateDetector
{
    public function detectar(?int $limit = null, bool $dryRun = false): array
    {
        $stats = [
            'analisadas' => 0,
            'gates_abertos' => 0,
            'gates_existentes' => 0,
            'por_tipo' => [],
            'exemplos' => [],
        ];

        $q = DB::table('crm_accounts as a')
            ->leftJoin('clientes as c', 'c.datajuri_id', '=', 'a.datajuri_pessoa_id')
            ->select([
                'a.id', 'a.name', 'a.datajuri_pessoa_id', 'a.owner_user_id',
                'c.status_pessoa', 'c.is_cliente',
            ]);
        if ($limit) $q->limit($limit);

        foreach ($q->get() as $acc) {
            $stats['analisadas']++;
            $tipos = $this->classificar($acc);

            foreach ($tipos as [$tipo, $evidencia]) {
                $existente = CrmAccountDataGate::where('account_id', $acc->id)
                    ->where('tipo', $tipo)
                    ->whereIn('status', CrmAccountDataGate::STATUS_ATIVOS)
                    ->first();

                if ($existente) {
                    $stats['gates_existentes']++;
                    continue;
                }

                if (!$dryRun) {
                    $gate = CrmAccountDataGate::create([
                        'account_id'        => $acc->id,
                        'owner_user_id'     => $acc->owner_user_id,
                        'tipo'              => $tipo,
                        'dj_valor_snapshot' => $acc->status_pessoa,
                        'evidencia_local'   => $evidencia,
                        'status'            => CrmAccountDataGate::STATUS_ABERTO,
                        'opened_at'         => now(),
                    ]);
                    try {
                        app(CrmGateNotifier::class)->notificarAbertura($gate);
                    } catch (\Throwable $e) {
                        Log::warning('[CrmDataGateDetector] notificar abertura falhou: ' . $e->getMessage());
                    }
                }
                $stats['gates_abertos']++;
                $stats['por_tipo'][$tipo] = ($stats['por_tipo'][$tipo] ?? 0) + 1;

                if (count($stats['exemplos']) < 20) {
                    $stats['exemplos'][] = [
                        'account_id' => $acc->id,
                        'name'       => $acc->name,
                        'tipo'       => $tipo,
                        'dj'         => $acc->status_pessoa,
                        'evidencia'  => $evidencia,
                    ];
                }
            }
        }

        if (!$dryRun) {
            Log::info('[CrmDataGateDetector] detectar', [
                'analisadas' => $stats['analisadas'],
                'gates_abertos' => $stats['gates_abertos'],
                'por_tipo' => $stats['por_tipo'],
            ]);
        }

        return $stats;
    }

    /**
     * Retorna lista de [tipo, evidencia] aplicaveis a conta.
     */
    private function classificar(object $acc): array
    {
        $djId = $acc->datajuri_pessoa_id;
        $statusDj = $acc->status_pessoa;
        $tipos = [];

        // Sinais locais
        $contratos = $djId
            ? DB::table('contratos')
                ->where('contratante_id_datajuri', $djId)
                ->get(['id', 'numero', 'data_assinatura', 'valor'])
            : collect();

        $processosCliente = $djId
            ? DB::table('processos')
                ->where('cliente_datajuri_id', $djId)
                ->count()
            : 0;

        $processosAdverso = $djId
            ? DB::table('processos')
                ->where('adverso_datajuri_id', $djId)
                ->count()
            : 0;

        $temVinculo = $contratos->count() > 0 || $processosCliente > 0;

        // Regra 1: DJ Onboarding mas ha contrato assinado OU processo como cliente
        if ($statusDj && stripos($statusDj, 'Onboarding') !== false && $temVinculo) {
            $tipos[] = [CrmAccountDataGate::TIPO_ONBOARDING_COM_CONTRATO, [
                'contratos' => $contratos->count(),
                'contrato_mais_antigo' => optional($contratos->min('data_assinatura'))->toString()
                    ?? $contratos->pluck('data_assinatura')->filter()->min(),
                'processos_cliente' => $processosCliente,
                'dica' => 'DJ diz Onboarding mas ja ha relacionamento efetivo — promover para Cliente Ativo no DJ.',
            ]];
        }

        // Regra 2: DJ marca Cliente (qualquer variacao) sem nenhum vinculo
        $ehClienteRotulo = $statusDj && stripos($statusDj, 'Cliente') !== false;
        if ($ehClienteRotulo && !$temVinculo && $djId) {
            $tipos[] = [CrmAccountDataGate::TIPO_STATUS_CLIENTE_SEM_VINCULO, [
                'contratos' => 0,
                'processos_cliente' => 0,
                'dica' => 'DJ marca como Cliente mas nao ha contrato nem processo — revisar status.',
            ]];
        }

        // Regra 3: DJ Adversa/Contraparte/Fornecedor mas temos contrato com a pessoa
        if ($statusDj && preg_match('/Adversa|Contraparte|Fornecedor/i', $statusDj) && $contratos->count() > 0) {
            $tipos[] = [CrmAccountDataGate::TIPO_ADVERSA_COM_CONTRATO, [
                'status_dj' => $statusDj,
                'contratos' => $contratos->count(),
                'dica' => 'DJ classifica como adversa/contraparte mas ha contrato assinado — conflito grave, verificar.',
            ]];
        }

        // Regra 4: contas_receber com data_vencimento=2099-12-31 (convencao "judicial/indefinido")
        //          e DJ nao marca Inadimplente
        if ($djId && (!$statusDj || stripos($statusDj, 'Inadimplente') === false)) {
            $contas2099 = DB::table('contas_receber')
                ->where('pessoa_datajuri_id', $djId)
                ->where('is_stale', false)
                ->whereDate('data_vencimento', '2099-12-31')
                ->whereNotIn('status', ['Excluido', 'Excluído', 'Concluído', 'Concluido'])
                ->where('valor', '>', 0)
                ->get(['id', 'valor']);
            if ($contas2099->count() > 0) {
                $tipos[] = [CrmAccountDataGate::TIPO_INADIMPLENCIA_SUSPEITA_2099, [
                    'contas' => $contas2099->count(),
                    'valor_total' => $contas2099->sum('valor'),
                    'dica' => 'Ha contas com vencimento 2099-12-31 (convencao judicial/indefinido). Classificar como inadimplente no DJ ou corrigir data.',
                ]];
            }
        }

        // Regra 5: DJ nao veio com status_pessoa
        if ($djId && empty($statusDj)) {
            $tipos[] = [CrmAccountDataGate::TIPO_SEM_STATUS_PESSOA, [
                'dica' => 'Cadastro DJ sem status_pessoa preenchido — classificar no DJ.',
            ]];
        }

        return $tipos;
    }
}
