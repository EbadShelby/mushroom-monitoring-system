<?php

namespace App\Http\Controllers;

use App\Models\GrowingCycle;
use App\Models\MushroomMeasurement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MeasurementController extends Controller
{
    public function index(): Response
    {
        $measurements = MushroomMeasurement::with(['growingCycle', 'user:id,name'])
            ->orderByDesc('observed_date')
            ->orderByDesc('id')
            ->paginate(15);

        $cycles = auth()->user()->role !== 'student'
            ? GrowingCycle::where('status', 'active')->get(['id', 'name'])
            : [];

        return Inertia::render('MeasurementsView', [
            'measurements' => $measurements,
            'activeCycles' => $cycles,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'growing_cycle_id' => ['required', 'integer', 'exists:growing_cycles,id'],
            'observed_date' => ['required', 'date'],
            'flush_number' => ['required', 'integer', 'min:1'],
            'weight_g' => ['nullable', 'numeric', 'min:0'],
            'height_cm' => ['nullable', 'numeric', 'min:0'],
            'cap_diameter_cm' => ['nullable', 'numeric', 'min:0'],
            'fruiting_body_count' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $measurement = MushroomMeasurement::create([
            ...$data,
            'user_id' => auth()->id(),
        ]);

        $measurement->load('user:id,name');

        return response()->json(['success' => true, 'measurement' => $measurement]);
    }

    public function destroy(MushroomMeasurement $mushroomMeasurement): JsonResponse
    {
        $mushroomMeasurement->delete();

        return response()->json(['success' => true]);
    }
}
