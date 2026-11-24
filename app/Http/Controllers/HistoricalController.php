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
    /** @var list<string> */
    private const array ALLOWED_SENSORS = [
        'temperature',
        'humidity',
        'co2_raw',
        'light_level',
        'soil_moisture',
    ];

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

        $sensors = $request->input('sensors', self::ALLOWED_SENSORS);
        $columns = array_merge(['id', 'recorded_at'], $sensors);

        $query = SensorReading::query()
            ->select($columns)
            ->when($request->filled('from'), fn ($q) => $q->where('recorded_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('recorded_at', '<=', $request->input('to').' 23:59:59'))
            ->orderByDesc('recorded_at');

        $paginated = $query->paginate($request->integer('per_page', 50));

        return response()->json($paginated);
    }

    public function chart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'interval' => ['nullable', 'string', 'in:1m,5m,15m,1h,1d'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sensors' => ['nullable', 'array'],
            'sensors.*' => ['string', 'in:temperature,humidity,co2_raw,light_level,soil_moisture'],
        ]);

        /** @var string $interval */
        $interval = $validated['interval'] ?? '5m';
        /** @var list<string> $sensors */
        $sensors = $validated['sensors'] ?? self::ALLOWED_SENSORS;
        $hasCustomRange = $request->filled('from') || $request->filled('to');

        $query = SensorReading::query()
            ->select(array_merge(['recorded_at'], $sensors));

        if ($hasCustomRange) {
            if ($request->filled('from')) {
                $query->where('recorded_at', '>=', $request->string('from')->toString());
            }

            if ($request->filled('to')) {
                $query->where('recorded_at', '<=', $request->string('to')->toString().' 23:59:59');
            }
        } else {
            $timeWindow = match ($interval) {
                '1m' => now()->subHour(),
                '15m' => now()->subHours(24),
                '1h' => now()->subDays(7),
                '1d' => now()->subDays(30),
                default => now()->subHours(6),
            };

            $query->where('recorded_at', '>=', $timeWindow);
        }

        $query->orderBy('recorded_at');

        if ($interval === '1m') {
            $points = [];

            foreach ($query->cursor() as $reading) {
                $point = [
                    'time' => Carbon::parse($reading->recorded_at)->toIso8601String(),
                ];

                foreach ($sensors as $sensor) {
                    $value = $reading->getAttribute($sensor);
                    $point[$sensor] = is_numeric($value) ? (float) $value : null;
                }

                $points[] = $point;
            }

            return response()->json($points);
        }

        $intervalSeconds = match ($interval) {
            '15m' => 15 * 60,
            '1h' => 60 * 60,
            '1d' => 24 * 60 * 60,
            default => 5 * 60,
        };

        /** @var array<int, array<string, float>> $sums */
        $sums = [];
        /** @var array<int, array<string, int>> $counts */
        $counts = [];

        foreach ($query->cursor() as $reading) {
            $recordedAt = Carbon::parse($reading->recorded_at);
            $bucketTime = $interval === '1d'
                ? (int) $recordedAt->copy()->startOfDay()->timestamp
                : (int) (floor(((int) $recordedAt->timestamp) / $intervalSeconds) * $intervalSeconds);

            if (! isset($sums[$bucketTime])) {
                $sums[$bucketTime] = [];
                $counts[$bucketTime] = [];
            }

            foreach ($sensors as $sensor) {
                $value = $reading->getAttribute($sensor);
                if (is_numeric($value)) {
                    $sums[$bucketTime][$sensor] = ($sums[$bucketTime][$sensor] ?? 0.0) + (float) $value;
                    $counts[$bucketTime][$sensor] = ($counts[$bucketTime][$sensor] ?? 0) + 1;
                }
            }
        }

        ksort($sums);

        $chartData = [];
        foreach ($sums as $time => $sensorSums) {
            $point = [
                'time' => Carbon::createFromTimestamp($time)->toIso8601String(),
            ];

            foreach ($sensors as $sensor) {
                $count = $counts[$time][$sensor] ?? 0;
                $point[$sensor] = $count > 0
                    ? round($sensorSums[$sensor] / $count, 1)
                    : null;
            }

            $chartData[] = $point;
        }

        return response()->json($chartData);
    }

    public function export(Request $request): HttpResponse
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sensors' => ['nullable', 'array'],
            'sensors.*' => ['string', 'in:temperature,humidity,co2_raw,light_level,soil_moisture'],
        ]);

        $sensors = $request->input('sensors', self::ALLOWED_SENSORS);
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
