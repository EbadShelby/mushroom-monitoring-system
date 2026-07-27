<?php

namespace App\Http\Controllers;

use App\Models\SensorReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class HistoricalController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('HistoricalView');
    }

    public function data(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sensors' => ['nullable', 'array'],
            'sensors.*' => ['string', 'in:temperature,humidity,co2_raw,light_level,soil_moisture'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $sensors = $request->input('sensors', ['temperature', 'humidity', 'co2_raw', 'light_level', 'soil_moisture']);
        $columns = array_merge(['id', 'recorded_at'], $sensors);

        $query = SensorReading::query()
            ->select($columns)
            ->when($request->filled('from'), fn ($q) => $q->where('recorded_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('recorded_at', '<=', $request->input('to').' 23:59:59'))
            ->orderByDesc('recorded_at');

        $paginated = $query->paginate($request->integer('per_page', 50));

        return response()->json($paginated);
    }

    public function export(Request $request): HttpResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sensors' => ['nullable', 'array'],
            'sensors.*' => ['string', 'in:temperature,humidity,co2_raw,light_level,soil_moisture'],
        ]);

        $sensors = $request->input('sensors', ['temperature', 'humidity', 'co2_raw', 'light_level', 'soil_moisture']);
        $columns = array_merge(['recorded_at'], $sensors);

        $readings = SensorReading::query()
            ->select($columns)
            ->when($request->filled('from'), fn ($q) => $q->where('recorded_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('recorded_at', '<=', $request->input('to').' 23:59:59'))
            ->orderBy('recorded_at')
            ->get();

        $csvHeaders = array_map(fn ($col) => match ($col) {
            'recorded_at' => 'Recorded At',
            'temperature' => 'Temperature (°C)',
            'humidity' => 'Humidity (%)',
            'co2_raw' => 'CO2 (ppm)',
            'light_level' => 'Light (lux)',
            'soil_moisture' => 'Soil Moisture (%)',
            default => $col,
        }, $columns);

        $csv = implode(',', $csvHeaders)."\n";
        foreach ($readings as $row) {
            $values = array_map(fn ($col) => $row->$col instanceof Carbon
                ? $row->$col->format('Y-m-d H:i:s')
                : ($row->$col ?? ''), $columns);
            $csv .= implode(',', $values)."\n";
        }

        $filename = 'sensor_readings_'.now()->format('Ymd_His').'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
