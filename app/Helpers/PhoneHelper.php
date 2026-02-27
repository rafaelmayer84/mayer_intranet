<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normaliza qualquer telefone brasileiro para formato canônico:
     * - Só dígitos
     * - Prefixo 55
     * - Celular com 9° dígito (13 chars total: 55 + DDD 2 + 9XXXX-XXXX)
     * - Fixo sem 9° dígito (12 chars total: 55 + DDD 2 + XXXX-XXXX)
     * - Retorna null para lixo irrecuperável
     *
     * DDDs válidos: 11-99 (exclui 00-10)
     */
    public static function normalize(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        // 1. Só dígitos
        $digits = preg_replace('/\D/', '', $phone);

        if (empty($digits) || strlen($digits) < 10) {
            return null;
        }

        // 2. Remover prefixo internacional duplicado ou +
        // Se começa com 5555 (duplo), remover um 55
        if (str_starts_with($digits, '5555') && strlen($digits) > 15) {
            $digits = substr($digits, 2);
        }

        // 3. Se começa com 0 (discagem interurbana), remover
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // 4. Adicionar prefixo 55 se necessário
        if (!str_starts_with($digits, '55')) {
            if (strlen($digits) >= 10 && strlen($digits) <= 11) {
                $digits = '55' . $digits;
            } else {
                return null; // formato irreconhecível sem prefixo 55
            }
        }

        // 5. Extrair DDD e número local
        $semPrefixo = substr($digits, 2); // remove 55
        $ddd = substr($semPrefixo, 0, 2);
        $local = substr($semPrefixo, 2);

        // DDD válido: 11-99
        $dddNum = (int) $ddd;
        if ($dddNum < 11 || $dddNum > 99) {
            return null;
        }

        // 6. Adicionar 9° dígito em celulares que não têm
        // Celular: 9XXXX-XXXX (9 dígitos) ou 9XXX-XXXX antigo (8 dígitos começando com 9)
        // Fixo: 2XXX-8XXX (8 dígitos, começa com 2-5)
        if (strlen($local) === 8) {
            $firstDigit = (int) $local[0];
            // Celulares: primeiro dígito 6-9 → adicionar 9 na frente
            if ($firstDigit >= 6) {
                $local = '9' . $local;
            }
            // Fixo (primeiro dígito 2-5): mantém 8 dígitos
        } elseif (strlen($local) === 9) {
            // Já tem 9 dígitos — ok (celular com 9° dígito)
            if ($local[0] !== '9') {
                return null; // 9 dígitos mas não começa com 9 = inválido
            }
        } else {
            return null; // número local com tamanho inválido
        }

        return '55' . $ddd . $local;
    }

    /**
     * Compara dois telefones normalizados.
     * Retorna true se representam o mesmo número.
     */
    public static function match(?string $a, ?string $b): bool
    {
        $na = self::normalize($a);
        $nb = self::normalize($b);

        if ($na === null || $nb === null) {
            return false;
        }

        return $na === $nb;
    }

    /**
     * Formata para exibição: +55 (47) 99742-5080
     */
    public static function format(?string $phone): ?string
    {
        $n = self::normalize($phone);
        if ($n === null) {
            return null;
        }

        $ddd = substr($n, 2, 2);
        $local = substr($n, 4);

        if (strlen($local) === 9) {
            return '+55 (' . $ddd . ') ' . substr($local, 0, 5) . '-' . substr($local, 5);
        }

        return '+55 (' . $ddd . ') ' . substr($local, 0, 4) . '-' . substr($local, 4);
    }
}
