<?php

namespace App\Http\Controllers;

use App\Models\GrowingCycle;
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
            ->when($status, fn ($q) => $q->where('status', $status))
            ->orderByRaw("FIELD(status, 'active', 'completed', 'cancelled')")
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('cycles/Index', [
            'cycles' => $cycles,
            'filters' => ['status' => $status],
            'hasActiveCycle' => GrowingCycle::where('status', 'active')->exists(),
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
            'mushroom_variety' => ['required', 'string', 'max:255'],
            'substrate_type' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

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

            'breachSummary' => Inertia::defer(function () use ($cycle) {
                $readings = SensorReading::where('growing_cycle_id', $cycle->id)->get();

                $tempMin = 26;
                $tempMax = 32;
                $humLow = 75;
                $humHigh = 95;
                $co2Max = 1000;
                $soilWarn = 30;

                return [
                    'temperature' => $readings->filter(
                        fn ($r) => $r->temperature !== null && ($r->temperature < $tempMin || $r->temperature > $tempMax)
                    )->count(),
                    'humidity' => $readings->filter(
                        fn ($r) => $r->humidity !== null && ($r->humidity < $humLow || $r->humidity > $humHigh)
                    )->count(),
                    'co2' => $readings->filter(
                        fn ($r) => $r->co2_raw !== null && $r->co2_raw > $co2Max
                    )->count(),
                    'soil_moisture' => $readings->filter(
                        fn ($r) => $r->soil_moisture !== null && $r->soil_moisture < $soilWarn
                    )->count(),
                    'total_readings' => $readings->count(),
                ];
            }),

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
            'mushroom_variety' => ['sometimes', 'string', 'max:255'],
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

        $cycle->update($data);

        UserLog::record('Updated growing cycle', $cycle->name);

        return response()->json(['success' => true, 'cycle' => $cycle->fresh()]);
    }

    public function endCycle(GrowingCycle $cycle): JsonResponse
    {
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
