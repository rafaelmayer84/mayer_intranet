<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SisrhHoleriteLancamento extends Model
{
    protected $table = 'sisrh_holerite_lancamentos';
    protected $fillable = ['user_id', 'ano', 'mes', 'rubrica_id', 'referencia', 'valor', 'observacao', 'origem', 'created_by'];
    protected $casts = ['valor' => 'float'];

    public function user() { return $this->belongsTo(\App\Models\User::class); }
    public function rubrica() { return $this->belongsTo(SisrhRubrica::class, 'rubrica_id'); }
}
