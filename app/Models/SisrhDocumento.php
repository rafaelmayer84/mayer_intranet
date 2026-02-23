<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SisrhDocumento extends Model
{
    protected $table = 'sisrh_documentos';
    protected $fillable = [
        'user_id', 'categoria', 'nome_original', 'nome_storage',
        'mime_type', 'tamanho', 'descricao', 'uploaded_by',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
}
