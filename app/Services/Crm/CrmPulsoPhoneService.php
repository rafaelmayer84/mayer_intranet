<?php

namespace App\Services\Crm;

use App\Models\Crm\CrmIdentity;
use App\Models\Crm\CrmPulsoDiario;
use App\Models\Crm\CrmPulsoPhoneUpload;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CrmPulsoPhoneService
{
    /**
     * Números fixos comerciais a filtrar (telemarketing, robocall).
     */
    protected array $blacklistPrefixes = [
        '5511450070', // SP 4500-70xx (múltiplos)
        '5544302590', // PR 3025-9001
        '5544305110', // PR 3051-1102
        '5544305111', // PR 3051-11xx
    ];

    /**
     * Processa upload do CSV VoIP do Brasil.
     * Formato: sem cabeçalho, separador ;, 9 colunas.
     */
    public function processarUpload(UploadedFile $file): array
    {
        $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $processados = 0;
        $ignorados = 0;
        $datas = [];
        $detalhes = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $cols = str_getcsv($line, ';');
            if (count($cols) < 9) {
                $ignorados++;
                continue;
            }

            $dataHora = trim($cols[0]);
            $origem   = trim($cols[2]);
            $status   = trim($cols[8]);

            // Só contabilizar "Atendido"
            if ($status !== 'Atendido') {
                continue;
            }

            // Normalizar telefone de origem
            $phoneNorm = preg_replace('/\D/', '', $origem);

            // Filtrar blacklist
            if ($this->isBlacklisted($phoneNorm)) {
                continue;
            }

            // Parse da data
            $dateParsed = $this->parseDate($dataHora);
            if (!$dateParsed) {
                $ignorados++;
                continue;
            }

            $dateStr = $dateParsed->toDateString();
            $datas[] = $dateStr;

            // Resolver account via crm_identities
            $accountId = $this->resolveAccountByPhone($phoneNorm);

            if (!$accountId) {
                $ignorados++;
                $detalhes[] = "Sem match: {$origem} ({$dateStr})";
                continue;
            }

            // Incrementar phone_calls no crm_pulso_diario
            $registro = CrmPulsoDiario::firstOrCreate(
                ['account_id' => $accountId, 'data' => $dateStr],
                [
                    'wa_msgs_incoming'       => 0,
                    'wa_conversations_opened' => 0,
                    'tickets_abertos'        => 0,
                    'crm_interactions'       => 0,
                    'phone_calls'            => 0,
                    'total_contatos'         => 0,
                    'has_movimentacao'       => false,
                    'threshold_exceeded'     => false,
                ]
            );

            $registro->increment('phone_calls');
            $registro->total_contatos = $registro->wa_msgs_incoming
                + $registro->tickets_abertos
                + $registro->crm_interactions
                + $registro->phone_calls;
            $registro->save();

            $processados++;
        }

        // Registrar upload
        $datas = array_unique($datas);
        sort($datas);

        $upload = CrmPulsoPhoneUpload::create([
            'user_id'               => Auth::id() ?? 1,
            'filename'              => $file->getClientOriginalName(),
            'registros_processados' => $processados,
            'registros_ignorados'   => $ignorados,
            'periodo_inicio'        => !empty($datas) ? min($datas) : null,
            'periodo_fim'           => !empty($datas) ? max($datas) : null,
        ]);

        Log::info("[Pulso Phone] Upload #{$upload->id}: {$processados} processados, {$ignorados} ignorados, arquivo: {$file->getClientOriginalName()}");

        return [
            'upload_id'   => $upload->id,
            'processados' => $processados,
            'ignorados'   => $ignorados,
            'periodo'     => !empty($datas) ? min($datas) . ' a ' . max($datas) : 'N/A',
            'detalhes'    => array_slice($detalhes, 0, 20),
        ];
    }

    protected function isBlacklisted(string $phoneNorm): bool
    {
        foreach ($this->blacklistPrefixes as $prefix) {
            if (str_starts_with($phoneNorm, $prefix)) {
                return true;
            }
        }
        return false;
    }

    protected function parseDate(string $dataHora): ?Carbon
    {
        try {
            return Carbon::createFromFormat('d/m/Y H:i:s', trim($dataHora));
        } catch (\Exception $e) {
            try {
                return Carbon::createFromFormat('d/m/Y', trim(explode(' ', $dataHora)[0]));
            } catch (\Exception $e2) {
                return null;
            }
        }
    }

    protected function resolveAccountByPhone(string $phoneNorm): ?int
    {
        // Match exato
        $identity = CrmIdentity::where('kind', 'phone')
            ->where('value_norm', $phoneNorm)
            ->first();

        if ($identity) return $identity->account_id;

        // Match por sufixo (últimos 11 dígitos)
        if (strlen($phoneNorm) >= 11) {
            $suffix = substr($phoneNorm, -11);
            $identity = CrmIdentity::where('kind', 'phone')
                ->where('value_norm', 'LIKE', '%' . $suffix)
                ->first();

            if ($identity) return $identity->account_id;
        }

        return null;
    }
}
