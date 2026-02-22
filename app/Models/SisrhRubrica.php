<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SisrhRubrica extends Model
{
    protected $table = 'sisrh_rubricas';
    protected $fillable = ['codigo', 'nome', 'tipo', 'automatica', 'formula', 'ativo', 'ordem'];
    protected $casts = ['automatica' => 'boolean', 'ativo' => 'boolean'];
}
