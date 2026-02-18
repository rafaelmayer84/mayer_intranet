<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationIntranet extends Model
{
    protected $table = 'notifications_intranet';

    protected $fillable = [
        'user_id', 'tipo', 'titulo', 'mensagem', 'link', 'icone', 'lida',
    ];

    protected $casts = [
        'lida' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function enviar(int $userId, string $titulo, string $mensagem, ?string $link = null, string $tipo = 'info', string $icone = 'bell'): self
    {
        return self::create([
            'user_id' => $userId,
            'tipo' => $tipo,
            'titulo' => $titulo,
            'mensagem' => $mensagem,
            'link' => $link,
            'icone' => $icone,
        ]);
    }

    public static function naoLidas(int $userId): int
    {
        return self::where('user_id', $userId)->where('lida', false)->count();
    }
}
