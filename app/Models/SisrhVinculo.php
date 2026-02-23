<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SisrhVinculo extends Model
{
    protected $table = 'sisrh_vinculos';
    protected $fillable = [
        'user_id', 'nivel_senioridade', 'data_inicio_exercicio', 'equipe_id',
        'ativo', 'observacoes', 'cpf', 'oab', 'rg',
        'endereco_rua', 'endereco_numero', 'endereco_complemento',
        'endereco_bairro', 'endereco_cep', 'endereco_cidade', 'endereco_estado',
        'nome_pai', 'nome_mae', 'created_by',
    ];
    protected $casts = ['ativo' => 'boolean', 'data_inicio_exercicio' => 'date'];

    public function user() { return $this->belongsTo(User::class); }
}
