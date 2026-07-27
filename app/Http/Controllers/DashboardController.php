<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use App\Models\CameraSnapshot;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $activeCycle = GrowingCycle::where('status', 'active')
            ->orderByDesc('start_date')
            ->first();

        return Inertia::render('Dashboard', [
            'activeCycle' => Inertia::defer(fn () => $activeCycle ? [
                'id' => $activeCycle->id,
                'name' => $activeCycle->name,
                'mushroom_variety' => $activeCycle->mushroom_variety,
                'substrate_type' => $activeCycle->substrate_type,
                'start_date' => $activeCycle->start_date,
                'status' => $activeCycle->status,
                'day_count' => (int) now()->diffInDays($activeCycle->start_date) + 1,
            ] : null),

            'latestSnapshot' => Inertia::defer(fn () => $activeCycle
                ? CameraSnapshot::where('growing_cycle_id', $activeCycle->id)
                    ->orderByDesc('captured_at')
                    ->first(['id', 'file_path', 'file_name', 'captured_at'])
                : null),

            'lastAlert' => Inertia::defer(fn () => AlertLog::orderByDesc('sent_at')
                ->first(['id', 'sensor', 'value_at_alert', 'threshold_exceeded', 'message', 'status', 'sent_at'])),

            'chartData' => Inertia::defer(fn () => SensorReading::where('recorded_at', '>=', now()->subHour())
                ->orderBy('recorded_at')
                ->get(['recorded_at', 'temperature', 'humidity'])
                ->map(fn ($r) => [
                    'time' => $r->recorded_at->format('H:i'),
                    'temperature' => $r->temperature,
                    'humidity' => $r->humidity,
                ])),
        ]);
    }
}
