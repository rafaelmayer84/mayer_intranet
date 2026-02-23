<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SystemEventController extends Controller
{
    public function index(Request $request)
    {
        $totalHoje = SystemEvent::today()->count();
        $errorsHoje = SystemEvent::today()->whereIn('severity', ['error', 'critical'])->count();
        $warningsHoje = SystemEvent::today()->severity('warning')->count();
        $total7dias = SystemEvent::lastDays(7)->count();

        $porCategoria = SystemEvent::lastDays(7)
            ->select('category', DB::raw('COUNT(*) as total'))
            ->groupBy('category')
            ->pluck('total', 'category')
            ->toArray();

        $query = SystemEvent::query()->orderByDesc('created_at');

        if ($request->filled('category')) { $query->where('category', $request->category); }
        if ($request->filled('severity')) { $query->where('severity', $request->severity); }
        if ($request->filled('event_type')) { $query->where('event_type', 'like', '%' . $request->event_type . '%'); }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%");
            });
        }
        if ($request->filled('data_inicio')) { $query->whereDate('created_at', '>=', $request->data_inicio); }
        if ($request->filled('data_fim')) { $query->whereDate('created_at', '<=', $request->data_fim); }

        $eventos = $query->paginate(25)->appends($request->all());

        $tendencia = SystemEvent::lastDays(30)
            ->select(DB::raw('DATE(created_at) as data'), DB::raw('COUNT(*) as total'))
            ->groupBy('data')->orderBy('data')
            ->get()->pluck('total', 'data')->toArray();

        $distCategoria = SystemEvent::lastDays(30)
            ->select('category', DB::raw('COUNT(*) as total'))
            ->groupBy('category')->pluck('total', 'category')->toArray();

        $distSeveridade = SystemEvent::lastDays(30)
            ->select('severity', DB::raw('COUNT(*) as total'))
            ->groupBy('severity')->pluck('total', 'severity')->toArray();

        $topEventTypes = SystemEvent::lastDays(30)
            ->select('event_type', DB::raw('COUNT(*) as total'))
            ->groupBy('event_type')->orderByDesc('total')->limit(10)->get();

        return view('admin.ocorrencias.index', compact(
            'totalHoje', 'errorsHoje', 'warningsHoje', 'total7dias',
            'porCategoria', 'eventos', 'tendencia', 'distCategoria',
            'distSeveridade', 'topEventTypes'
        ));
    }

    public function show(SystemEvent $systemEvent)
    {
        return response()->json([
            'id'             => $systemEvent->id,
            'category'       => $systemEvent->category,
            'category_label' => $systemEvent->category_label,
            'severity'       => $systemEvent->severity,
            'event_type'     => $systemEvent->event_type,
            'title'          => $systemEvent->title,
            'description'    => $systemEvent->description,
            'metadata'       => $systemEvent->metadata,
            'related_model'  => $systemEvent->related_model,
            'related_id'     => $systemEvent->related_id,
            'user_name'      => $systemEvent->user_name,
            'ip_address'     => $systemEvent->ip_address,
            'created_at'     => $systemEvent->created_at->format('d/m/Y H:i:s'),
        ]);
    }
}
