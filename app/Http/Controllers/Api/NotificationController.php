<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NotificationIntranet;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function unread(Request $request)
    {
        $userId = auth()->id();
        $count = NotificationIntranet::naoLidas($userId);
        $items = NotificationIntranet::where('user_id', $userId)
            ->where('lida', false)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'tipo', 'titulo', 'mensagem', 'link', 'icone', 'created_at']);

        return response()->json(['count' => $count, 'items' => $items]);
    }

    public function markRead(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            NotificationIntranet::where('user_id', auth()->id())->where('lida', false)->update(['lida' => true]);
        } else {
            NotificationIntranet::where('user_id', auth()->id())->whereIn('id', $ids)->update(['lida' => true]);
        }
        return response()->json(['success' => true]);
    }
}
