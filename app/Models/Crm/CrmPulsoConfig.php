<?php

namespace App\Models\Crm;

use Illuminate\Database\Eloquent\Model;

class CrmPulsoConfig extends Model
{
    protected $table = 'crm_pulso_config';

    public $timestamps = false;

    protected $fillable = ['chave', 'valor', 'descricao', 'updated_at'];

    /**
     * Retorna valor de um threshold pelo nome da chave.
     */
    public static function threshold(string $chave, $default = null): mixed
    {
        $row = static::where('chave', $chave)->first();
        return $row ? $row->valor : $default;
    }

    /**
     * Retorna todos os thresholds como array chave => valor.
     */
    public static function allThresholds(): array
    {
        return static::pluck('valor', 'chave')->toArray();
    }
}
