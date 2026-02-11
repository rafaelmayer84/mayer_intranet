<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PricingCalibration extends Model
{
    protected $table = 'pricing_calibrations';

    protected $fillable = [
        'eixo',
        'label',
        'descricao',
        'label_min',
        'label_max',
        'valor',
        'updated_by',
    ];

    protected $casts = [
        'valor' => 'integer',
    ];

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Retorna todos os eixos como array associativo eixo => valor
     */
    public static function getSnapshot(): array
    {
        return self::pluck('valor', 'eixo')->toArray();
    }

    /**
     * Retorna todos os eixos com labels para o prompt da IA
     */
    public static function getForPrompt(): array
    {
        return self::all()->map(function ($c) {
            return [
                'eixo' => $c->eixo,
                'label' => $c->label,
                'valor' => $c->valor,
                'interpretacao' => $c->label_min . ' (0) ←→ ' . $c->label_max . ' (100)',
            ];
        })->toArray();
    }
}
