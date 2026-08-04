<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use App\Models\CameraSnapshot;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
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
                'growing_stage' => $activeCycle->growing_stage ?? 'colonization',
                'day_count' => (int) now()->diffInDays($activeCycle->start_date) + 1,
            ] : null),

            'latestSnapshot' => Inertia::defer(fn () => $activeCycle
                ? CameraSnapshot::where('growing_cycle_id', $activeCycle->id)
                    ->orderByDesc('captured_date')
                    ->first(['id', 'file_path', 'file_name', 'captured_date'])
                : null),

            'lastAlert' => Inertia::defer(fn () => AlertLog::orderByDesc('sent_at')
                ->first(['id', 'sensor', 'value_at_alert', 'threshold_exceeded', 'message', 'status', 'sent_at'])),

            'chartData' => Inertia::defer(function () use ($request) {
                $interval = $request->query('chart_interval', '1m');
                $timeWindow = match ($interval) {
                    '5m' => now()->subHours(6),
                    '15m' => now()->subHours(24),
                    '1h' => now()->subDays(7),
                    default => now()->subHour(),
                };

                $readings = SensorReading::where('recorded_at', '>=', $timeWindow)
                    ->orderBy('recorded_at')
                    ->get(['recorded_at', 'temperature', 'humidity']);

                if ($interval === '1m') {
                    return $readings->map(fn ($r) => [
                        'time' => $r->recorded_at->toIso8601String(),
                        'temperature' => $r->temperature,
                        'humidity' => $r->humidity,
                    ]);
                }

                $intervalSeconds = match ($interval) {
                    '5m' => 5 * 60,
                    '15m' => 15 * 60,
                    '1h' => 60 * 60,
                    default => 60,
                };

                $grouped = [];
                foreach ($readings as $r) {
                    $timestamp = $r->recorded_at->timestamp;
                    $roundedTime = floor($timestamp / $intervalSeconds) * $intervalSeconds;

                    if (! isset($grouped[$roundedTime])) {
                        $grouped[$roundedTime] = ['temp' => [], 'hum' => []];
                    }
                    $grouped[$roundedTime]['temp'][] = $r->temperature;
                    $grouped[$roundedTime]['hum'][] = $r->humidity;
                }

                $chartData = [];
                foreach ($grouped as $time => $values) {
                    $chartData[] = [
                        'time' => Carbon::createFromTimestamp($time)->toIso8601String(),
                        'temperature' => round(array_sum($values['temp']) / count($values['temp']), 1),
                        'humidity' => round(array_sum($values['hum']) / count($values['hum']), 1),
                    ];
                }

                return array_values($chartData);
            }),
        ]);
    }
}
