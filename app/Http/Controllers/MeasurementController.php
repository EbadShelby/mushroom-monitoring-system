<?php

namespace App\Http\Controllers;

use App\Models\MushroomMeasurement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'growing_cycle_id' => ['required', 'integer', 'exists:growing_cycles,id'],
            'observed_date' => ['required', 'date'],
            'flush_number' => ['required', 'integer', 'min:1'],
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
