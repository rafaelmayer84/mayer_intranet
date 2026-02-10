<?php

namespace App\Traits;

use Illuminate\Support\Facades\Log;

trait NormalizesUtf8
{
    /**
     * Normaliza encoding UTF-8 de dados de APIs externas
     * 
     * Resolve problemas com APIs que retornam ISO-8859-1 ou Windows-1252
     * especialmente comuns em sistemas legados brasileiros
     * 
     * @param mixed $data
     * @return mixed
     */
    protected function normalizeUtf8($data)
    {
        if (is_string($data)) {
            // Remove BOM se presente
            $data = $this->removeBom($data);
            
            // Detecta encoding
            $encoding = mb_detect_encoding(
                $data, 
                ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], 
                true
            );
            
            // Converte para UTF-8 se necessário
            if ($encoding && $encoding !== 'UTF-8') {
                $data = mb_convert_encoding($data, 'UTF-8', $encoding);
                Log::debug("Convertido de {$encoding} para UTF-8");
            }
            
            // Remove caracteres inválidos (double encode fix)
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            
            return $data;
        }
        
        if (is_array($data)) {
            return array_map([$this, 'normalizeUtf8'], $data);
        }
        
        if (is_object($data)) {
            $normalized = clone $data;
            foreach ($normalized as $key => $value) {
                $normalized->$key = $this->normalizeUtf8($value);
            }
            return $normalized;
        }
        
        return $data;
    }

    /**
     * Remove BOM (Byte Order Mark) de strings
     * 
     * @param string $text
     * @return string
     */
    protected function removeBom(string $text): string
    {
        $bom = pack('H*','EFBBBF');
        return preg_replace("/^$bom/", '', $text);
    }

    /**
     * Garante que JSON será decodificado corretamente mesmo com encoding incorreto
     * 
     * @param string $json
     * @return array|null
     */
    protected function safeJsonDecode(string $json): ?array
    {
        // Primeiro tenta decodificar direto
        $decoded = json_decode($json, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->normalizeUtf8($decoded);
        }
        
        // Se falhar, normaliza UTF-8 e tenta novamente
        if (json_last_error() === JSON_ERROR_UTF8) {
            $json = $this->normalizeUtf8($json);
            $decoded = json_decode($json, true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        
        Log::error('Falha no decode JSON', [
            'error' => json_last_error_msg(),
            'preview' => substr($json, 0, 200)
        ]);
        
        return null;
    }
}
