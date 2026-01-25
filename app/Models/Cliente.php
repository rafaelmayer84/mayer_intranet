<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use SoftDeletes;

    protected $table = 'clientes';

    protected $fillable = [
        'nome',
        'cpf_cnpj',
        'tipo',
        'email',
        'telefone',
        'endereco',
        'datajuri_id',
        'espocrm_id',
        'status',
        'valor_carteira',
        'total_processos',
        'total_contratos',
        'data_primeiro_contato',
        'data_ultimo_contato',
        'metadata'
    ];

    protected $casts = [
        'valor_carteira' => 'decimal:2',
        'metadata' => 'array',
        'data_primeiro_contato' => 'date',
        'data_ultimo_contato' => 'date'
    ];

    public function oportunidades()
    {
        return $this->hasMany(Oportunidade::class);
    }

    public function processos()
    {
        return $this->hasMany(\App\Models\Processo::class, 'pessoa_id', 'datajuri_id');
    }

    public function contratos()
    {
        return $this->hasMany(\App\Models\Contrato::class, 'pessoa_id', 'datajuri_id');
    }

    // Scopes úteis
    public function scopeAtivos($query)
    {
        return $query->where('status', 'ativo');
    }

    public function scopePessoaFisica($query)
    {
        return $query->where('tipo', 'PF');
    }

    public function scopePessoaJuridica($query)
    {
        return $query->where('tipo', 'PJ');
    }

    // Accessors
    public function getNomeCurtoAttribute()
    {
        $partes = explode(' ', $this->nome);
        return $partes[0] . (isset($partes[1]) ? ' ' . $partes[1] : '');
    }

    public function getTelefoneLimpoAttribute()
    {
        return preg_replace('/[^0-9]/', '', $this->telefone ?? '');
    }
}
