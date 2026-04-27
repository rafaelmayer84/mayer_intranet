<?php

namespace App\Services\Nexo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LexusClienteLookupService
{
    private const STATUS_EXCLUIDOS = ['Adversa', 'Contraparte', 'Fornecedor'];
    private const CACHE_TTL = 300; // 5 minutos

    public function buscarPorPhone(string $phone): ?array
    {
        // TODO(dívida-técnica): app/Models/Cliente.php tem $fillable incompleto —
        // telefone_normalizado, celular, is_cliente NÃO estão protegidos via
        // mass assignment. Em produção isso não afeta este lookup (sync
        // DataJuri usa DB::table()->update bypassando $fillable), mas qualquer
        // código futuro que faça Cliente::create() não vai persistir esses
        // campos. Não é responsabilidade desta fase corrigir, mas registrar.

        $phoneNorm = $this->normalizar($phone);
        if ($phoneNorm === '') {
            return null;
        }

        return Cache::remember("lexus_lookup_{$phoneNorm}", self::CACHE_TTL, function () use ($phoneNorm) {
            return $this->consultar($phoneNorm);
        });
    }

    private function consultar(string $phoneNorm): ?array
    {
        // Variantes: com 55, sem 55, com/sem nono dígito
        $variants = $this->variantes($phoneNorm);
        $suffix8  = substr($phoneNorm, -8);

        $cliente = DB::table('clientes')
            ->where(function ($q) use ($variants, $suffix8) {
                $q->whereIn('telefone_normalizado', $variants)
                  ->orWhereIn('celular', $variants)
                  ->orWhere('celular', 'LIKE', '%' . $suffix8);
            })
            ->whereNotIn('status_pessoa', self::STATUS_EXCLUIDOS)
            ->whereNull('deleted_at')
            ->orderByDesc('is_cliente') // clientes reais primeiro
            ->first(['id', 'nome', 'datajuri_id', 'is_cliente', 'total_processos']);

        if (!$cliente) {
            Log::info('LEXUS-V3 lookup: não encontrado', ['phone' => $phoneNorm]);
            return null;
        }

        $result = [
            'cliente_id'          => $cliente->id,
            'nome'                => $cliente->nome,
            'datajuri_id'         => $cliente->datajuri_id,
            'is_cliente'          => (bool) $cliente->is_cliente,
            'tem_processos_ativos'=> ($cliente->total_processos ?? 0) > 0,
        ];

        Log::warning('LEXUS-V3 lookup: encontrado', [
            'phone'      => $phoneNorm,
            'cliente_id' => $result['cliente_id'],
            'is_cliente' => $result['is_cliente'],
        ]);

        return $result;
    }

    private function normalizar(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) < 10) return '';

        // Remove 55 duplicado (ex: 5555479...)
        if (preg_match('/^5555(\d{10,11})$/', $digits, $m)) {
            $digits = '55' . $m[1];
        }

        // Adiciona prefixo 55 se necessário
        if (preg_match('/^\d{10,11}$/', $digits)) {
            $digits = '55' . $digits;
        }

        return $digits;
    }

    private function variantes(string $phoneNorm): array
    {
        $v = [$phoneNorm];

        // Sem prefixo 55
        if (str_starts_with($phoneNorm, '55') && strlen($phoneNorm) >= 12) {
            $v[] = substr($phoneNorm, 2);
            $v[] = '+' . $phoneNorm;
        }

        // Com / sem nono dígito
        if (strlen($phoneNorm) === 13 && $phoneNorm[4] === '9') {
            $v[] = substr($phoneNorm, 0, 4) . substr($phoneNorm, 5); // remove nono
        }
        if (strlen($phoneNorm) === 12 && preg_match('/^55\d{2}[6-9]/', $phoneNorm)) {
            $v[] = substr($phoneNorm, 0, 4) . '9' . substr($phoneNorm, 4); // adiciona nono
        }

        return array_unique($v);
    }
}
