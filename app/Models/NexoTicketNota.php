<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexoTicketNota extends Model
{
    protected $table = 'nexo_ticket_notas';

    protected $fillable = [
        'ticket_id',
        'user_id',
        'texto',
        'tipo',
        'notificou_cliente',
    ];

    protected $casts = [
        'notificou_cliente' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(NexoTicket::class, 'ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
