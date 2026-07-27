<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertLogController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('AlertLogsView', [
            'logs' => Inertia::defer(fn () => AlertLog::query()
                ->when($request->filled('sensor'), fn ($q) => $q->where('sensor', $request->input('sensor')))
                ->when($request->filled('from'), fn ($q) => $q->where('sent_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->where('sent_at', '<=', $request->input('to').' 23:59:59'))
                ->orderByDesc('sent_at')
                ->paginate(20)
            ),
            'chartData' => Inertia::defer(fn () => AlertLog::query()
                ->selectRaw('sensor, COUNT(*) as count')
                ->groupBy('sensor')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['sensor' => $row->sensor, 'count' => $row->count])
            ),
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        $data = AlertLog::query()
            ->when($request->filled('sensor'), fn ($q) => $q->where('sensor', $request->input('sensor')))
            ->when($request->filled('from'), fn ($q) => $q->where('sent_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('sent_at', '<=', $request->input('to').' 23:59:59'))
            ->selectRaw('sensor, COUNT(*) as count')
            ->groupBy('sensor')
            ->orderByDesc('count')
            ->get();

        return response()->json($data);
    }
}
