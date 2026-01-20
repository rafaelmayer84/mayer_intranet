<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracao extends Model
{
    protected $table = 'configuracoes';
    
    protected $fillable = [
        'chave',
        'valor',
        'tipo',
    ];

    public static function get(string $chave, $default = null)
    {
        $config = self::where('chave', $chave)->first();
        if (!$config) {
            return $default;
        }
        
        return match($config->tipo) {
            'integer' => (int) $config->valor,
            'float', 'decimal' => (float) $config->valor,
            'boolean' => filter_var($config->valor, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($config->valor, true),
            default => $config->valor,
        };
    }

    public static function set(string $chave, $valor, string $tipo = 'string'): void
    {
        self::updateOrCreate(
            ['chave' => $chave],
            ['valor' => is_array($valor) ? json_encode($valor) : (string) $valor, 'tipo' => $tipo]
        );
    }
}
