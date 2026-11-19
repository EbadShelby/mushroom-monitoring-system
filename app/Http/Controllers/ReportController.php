<?php

namespace App\Http\Controllers;

use App\Models\GrowingCycle;
use App\Models\SensorReading;
use Barryvdh\DomPDF\Facade\Pdf;
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

        $breachSummary = $cycle->breachSummary();

        $measurements = $cycle->measurements()
            ->with('user:id,name')
            ->orderBy('observed_date')
            ->get();

        $dayCount = $cycle->dayCount();

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
