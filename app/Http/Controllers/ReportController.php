<?php

namespace App\Http\Controllers;

use App\Models\GrowingCycle;
use App\Models\SensorReading;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $cycles = GrowingCycle::orderByDesc('start_date')->get([
            'id', 'name', 'mushroom_variety', 'start_date', 'end_date', 'status', 'created_at',
        ]);

        return Inertia::render('reports/Index', [
            'cycles' => $cycles,
        ]);
    }

    public function show(GrowingCycle $cycle): HttpResponse
    {
        $dailyAverages = SensorReading::query()
            ->where('growing_cycle_id', $cycle->id)
            ->selectRaw('DATE(recorded_at) as date,
                ROUND(AVG(temperature), 1) as avg_temperature,
                ROUND(AVG(humidity), 1) as avg_humidity,
                ROUND(AVG(co2_raw), 0) as avg_co2,
                ROUND(AVG(light_level), 1) as avg_light')
            ->groupByRaw('DATE(recorded_at)')
            ->orderBy('date')
            ->get();

        $readings = SensorReading::where('growing_cycle_id', $cycle->id)->get();

        $breachSummary = [
            'temperature' => $readings->filter(
                fn ($r) => $r->temperature !== null && ($r->temperature < 24 || $r->temperature > 30)
            )->count(),
            'humidity' => $readings->filter(fn ($r) => $r->humidity !== null && $r->humidity < 80)->count(),
            'co2' => $readings->filter(fn ($r) => $r->co2_raw !== null && $r->co2_raw > 1000)->count(),
            'soil_moisture' => $readings->filter(fn ($r) => $r->soil_moisture !== null && $r->soil_moisture < 30)->count(),
            'total_readings' => $readings->count(),
        ];

        $measurements = $cycle->measurements()
            ->with('user:id,name')
            ->orderBy('observed_date')
            ->get();

        $dayCount = $cycle->end_date
            ? (int) Carbon::parse($cycle->start_date)->diffInDays($cycle->end_date) + 1
            : (int) now()->diffInDays($cycle->start_date) + 1;

        $pdf = Pdf::loadView('reports.cycle-report', compact(
            'cycle',
            'dailyAverages',
            'breachSummary',
            'measurements',
            'dayCount',
        ))->setPaper('a4', 'portrait');

        $filename = 'cycle-report-'.str($cycle->name)->slug().'-'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }
}
