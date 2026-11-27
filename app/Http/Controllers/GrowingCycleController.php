<?php

namespace App\Http\Controllers;

use App\Models\GrowingCycle;
use App\Models\MushroomMeasurement;
use App\Models\SensorReading;
use App\Models\UserLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrowingCycleController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $cycles = GrowingCycle::query()
            ->withCount('measurements')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'active', 'completed', 'cancelled')")
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        $comparableCycles = GrowingCycle::query()
            ->withCount('measurements')
            ->whereIn('status', ['active', 'completed'])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'mushroom_variety', 'status', 'start_date', 'end_date']);

        return Inertia::render('cycles/Index', [
            'cycles' => $cycles,
            'comparableCycles' => $comparableCycles,
            'filters' => ['status' => $status],
            'hasActiveCycle' => GrowingCycle::where('status', 'active')->exists(),
        ]);
    }

    /**
     * Return side-by-side climate averages, breach counts, and harvest measurement
     * summaries for 2 to 10 selected growing cycles.
     */
    public function compare(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2', 'max:10'],
            'ids.*' => ['integer', 'distinct', 'exists:growing_cycles,id'],
        ]);

        $ids = array_map('intval', $data['ids']);

        $cycles = GrowingCycle::query()
            ->whereIn('id', $ids)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $sensorAverages = SensorReading::query()
            ->whereIn('growing_cycle_id', $ids)
            ->selectRaw('growing_cycle_id,
                COUNT(*) as readings_count,
                ROUND(AVG(temperature), 1) as avg_temperature,
                ROUND(AVG(humidity), 1) as avg_humidity,
                ROUND(AVG(co2_raw), 0) as avg_co2,
                ROUND(AVG(light_level), 1) as avg_light,
                ROUND(AVG(soil_moisture), 1) as avg_moisture')
            ->groupBy('growing_cycle_id')
            ->get()
            ->keyBy('growing_cycle_id');

        $harvestStats = MushroomMeasurement::query()
            ->whereIn('growing_cycle_id', $ids)
            ->selectRaw('growing_cycle_id,
                COUNT(*) as records_count,
                SUM(fruiting_body_count) as total_fruiting_bodies,
                ROUND(AVG(height_cm), 1) as avg_height_cm,
                ROUND(AVG(cap_diameter_cm), 1) as avg_cap_diameter_cm')
            ->groupBy('growing_cycle_id')
            ->get()
            ->keyBy('growing_cycle_id');

        $payload = $cycles->map(function (GrowingCycle $cycle) use ($sensorAverages, $harvestStats): array {
            $avg = $sensorAverages->get($cycle->id);
            $harvest = $harvestStats->get($cycle->id);
            $breaches = $cycle->breachSummary();
            $totalBreaches = $breaches['temperature']
                + $breaches['humidity']
                + $breaches['co2']
                + $breaches['soil_moisture'];

            return [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'mushroom_variety' => $cycle->mushroom_variety,
                'substrate_type' => $cycle->substrate_type,
                'status' => $cycle->status,
                'start_date' => $cycle->start_date,
                'end_date' => $cycle->end_date,
                'day_count' => $cycle->dayCount(),
                'readings_count' => $avg ? (int) $avg->readings_count : 0,
                'averages' => [
                    'temperature' => $avg?->avg_temperature !== null ? (float) $avg->avg_temperature : null,
                    'humidity' => $avg?->avg_humidity !== null ? (float) $avg->avg_humidity : null,
                    'co2' => $avg?->avg_co2 !== null ? (float) $avg->avg_co2 : null,
                    'light' => $avg?->avg_light !== null ? (float) $avg->avg_light : null,
                    'soil_moisture' => $avg?->avg_moisture !== null ? (float) $avg->avg_moisture : null,
                ],
                'breaches' => [
                    'temperature' => $breaches['temperature'],
                    'humidity' => $breaches['humidity'],
                    'co2' => $breaches['co2'],
                    'soil_moisture' => $breaches['soil_moisture'],
                    'total' => $totalBreaches,
                ],
                'harvest' => [
                    'records_count' => $harvest ? (int) $harvest->records_count : 0,
                    'total_fruiting_bodies' => $harvest?->total_fruiting_bodies !== null ? (int) $harvest->total_fruiting_bodies : null,
                    'avg_height_cm' => $harvest?->avg_height_cm !== null ? (float) $harvest->avg_height_cm : null,
                    'avg_cap_diameter_cm' => $harvest?->avg_cap_diameter_cm !== null ? (float) $harvest->avg_cap_diameter_cm : null,
                ],
            ];
        })->values()->all();

        return response()->json([
            'cycles' => $payload,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if (GrowingCycle::where('status', 'active')->exists()) {
            return response()->json([
                'message' => 'An active growing cycle is already running. Please end or cancel it before creating a new one.',
            ], 422);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mushroom_variety' => ['required_without:fruiting_bags', 'nullable', 'string', 'max:255'],
            'fruiting_bags' => ['nullable', 'array', 'min:1'],
            'fruiting_bags.*.variety' => ['required', 'string', 'max:255'],
            'fruiting_bags.*.count' => ['required', 'integer', 'min:1', 'max:10000'],
            'substrate_type' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        if (! empty($data['fruiting_bags'])) {
            $summary = collect($data['fruiting_bags'])
                ->map(fn (array $bag): string => "{$bag['count']}× {$bag['variety']}")
                ->implode(', ');
            $data['mushroom_variety'] = mb_substr($summary, 0, 255);
        }

        $cycle = GrowingCycle::create([
            ...$data,
            'status' => 'active',
        ]);

        UserLog::record('Created growing cycle', $cycle->name);

        return response()->json(['success' => true, 'cycle' => $cycle]);
    }

    public function show(GrowingCycle $cycle): Response
    {
        return Inertia::render('cycles/Show', [
            'cycle' => [
                'id' => $cycle->id,
                'name' => $cycle->name,
                'mushroom_variety' => $cycle->mushroom_variety,
                'fruiting_bags' => $cycle->fruiting_bags,
                'substrate_type' => $cycle->substrate_type,
                'start_date' => $cycle->start_date,
                'end_date' => $cycle->end_date,
                'status' => $cycle->status,
                'notes' => $cycle->notes,
                'day_count' => $cycle->dayCount(),
            ],

            'dailyAverages' => Inertia::defer(function () use ($cycle) {
                return SensorReading::query()
                    ->where('growing_cycle_id', $cycle->id)
                    ->selectRaw('DATE(recorded_at) as date,
                        ROUND(AVG(temperature), 1) as avg_temperature,
                        ROUND(AVG(humidity), 1) as avg_humidity,
                        ROUND(AVG(co2_raw), 0) as avg_co2,
                        ROUND(AVG(light_level), 1) as avg_light')
                    ->groupByRaw('DATE(recorded_at)')
                    ->orderBy('date')
                    ->get();
            }),

            'breachSummary' => Inertia::defer(fn () => $cycle->breachSummary()),

            'measurements' => Inertia::defer(fn () => $cycle->measurements()
                ->with('user:id,name')
                ->orderByDesc('observed_date')
                ->orderByDesc('id')
                ->get([
                    'id', 'growing_cycle_id', 'user_id', 'observed_date',
                    'flush_number', 'weight_g', 'height_cm', 'cap_diameter_cm',
                    'fruiting_body_count', 'notes',
                ])),

            'snapshots' => Inertia::defer(fn () => $cycle->snapshots()
                ->orderByDesc('captured_date')
                ->get(['id', 'file_path', 'file_name', 'flush_number', 'captured_date', 'notes'])),
        ]);
    }

    public function update(Request $request, GrowingCycle $cycle): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'mushroom_variety' => ['sometimes', 'nullable', 'string', 'max:255'],
            'fruiting_bags' => ['sometimes', 'nullable', 'array', 'min:1'],
            'fruiting_bags.*.variety' => ['required', 'string', 'max:255'],
            'fruiting_bags.*.count' => ['required', 'integer', 'min:1', 'max:10000'],
            'substrate_type' => ['sometimes', 'string', 'max:255'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'string', 'in:active,completed,cancelled'],
            'notes' => ['nullable', 'string'],
        ]);

        if (($data['status'] ?? null) === 'active' && $cycle->status !== 'active') {
            if (GrowingCycle::where('status', 'active')->where('id', '!=', $cycle->id)->exists()) {
                return response()->json([
                    'message' => 'Another growing cycle is already active. Please end or cancel it before reactivating this cycle.',
                ], 422);
            }
        }

        if (! empty($data['fruiting_bags'])) {
            $summary = collect($data['fruiting_bags'])
                ->map(fn (array $bag): string => "{$bag['count']}× {$bag['variety']}")
                ->implode(', ');
            $data['mushroom_variety'] = mb_substr($summary, 0, 255);
        }

        $cycle->update($data);

        UserLog::record('Updated growing cycle', $cycle->name);

        return response()->json(['success' => true, 'cycle' => $cycle->fresh()]);
    }

    public function endCycle(Request $request, GrowingCycle $cycle): JsonResponse
    {
        $data = $request->validate([
            'observed_date' => ['nullable', 'date'],
            'fruiting_body_count' => ['nullable', 'integer', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'cap_diameter_cm' => ['nullable', 'numeric', 'min:0'],
            'harvest_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $hasHarvestData = ($data['fruiting_body_count'] ?? null) !== null
            || ($data['height_cm'] ?? null) !== null
            || ($data['cap_diameter_cm'] ?? null) !== null
            || trim((string) ($data['harvest_notes'] ?? '')) !== '';

        if ($hasHarvestData) {
            MushroomMeasurement::create([
                'growing_cycle_id' => $cycle->id,
                'user_id' => $request->user()?->id,
                'observed_date' => $data['observed_date'] ?? now()->toDateString(),
                'flush_number' => 1,
                'fruiting_body_count' => $data['fruiting_body_count'] ?? null,
                'height_cm' => $data['height_cm'] ?? null,
                'cap_diameter_cm' => $data['cap_diameter_cm'] ?? null,
                'notes' => $data['harvest_notes'] ?? null,
            ]);
        }

        $cycle->update([
            'status' => 'completed',
            'end_date' => now()->toDateString(),
        ]);

        UserLog::record('Ended growing cycle', $cycle->name);

        return response()->json(['success' => true, 'cycle' => $cycle->fresh()]);
    }

    public function destroy(GrowingCycle $cycle): JsonResponse
    {
        $cycle->update(['status' => 'cancelled']);

        UserLog::record('Cancelled growing cycle', $cycle->name);

        return response()->json(['success' => true]);
    }
}
