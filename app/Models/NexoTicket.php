<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexoTicket extends Model
{
    protected $table = 'nexo_tickets';

    protected $fillable = [
        'cliente_id',
        'datajuri_id',
        'telefone',
        'nome_cliente',
        'assunto',
        'mensagem',
        'status',
        'atendente',
        'resposta_interna',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }
}
