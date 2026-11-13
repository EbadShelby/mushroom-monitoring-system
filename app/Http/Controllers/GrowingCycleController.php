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
            'growing_stage' => ['sometimes', 'string', 'in:colonization,fruiting'],
            'notes' => ['nullable', 'string'],
        ]);

        $cycle = GrowingCycle::create([
            ...$data,
            'status' => 'active',
            'growing_stage' => $data['growing_stage'] ?? 'colonization',
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
                'growing_stage' => $cycle->growing_stage,
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

                // Use stage-appropriate breach boundaries
                $isColonization = $cycle->growing_stage === 'colonization';
                $tempMin = $isColonization ? 26 : 26;
                $tempMax = $isColonization ? 32 : 32;
                $humLow = 75;
                $humHigh = 95;
                $co2Max = $isColonization ? 5000 : 1000;
                $soilWarn = 40;

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
            'growing_stage' => ['sometimes', 'string', 'in:colonization,fruiting'],
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

    /**
     * Switch between colonization and fruiting stage for an active cycle.
     */
    public function switchStage(Request $request, GrowingCycle $cycle): JsonResponse
    {
        $data = $request->validate([
            'growing_stage' => ['required', 'string', 'in:colonization,fruiting'],
        ]);

        if ($cycle->status !== 'active') {
            return response()->json(['error' => 'Only active cycles can switch stages.'], 422);
        }

        $cycle->update(['growing_stage' => $data['growing_stage']]);

        UserLog::record('Switched cycle stage', "{$cycle->name} -> {$cycle->growing_stage}");

        return response()->json([
            'success' => true,
            'growing_stage' => $cycle->growing_stage,
            'cycle' => $cycle->fresh(),
        ]);
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
