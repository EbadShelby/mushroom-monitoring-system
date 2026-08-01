<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use App\Services\FirebaseService;
use App\Services\SmsService;
use App\Services\ThresholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SensorController extends Controller
{
    public function __construct(
        private FirebaseService $firebase,
        private SmsService $sms,
        private ThresholdService $threshold,
    ) {}

    /**
     * POST /api/sensor-data
     *
     * Entry point for the ESP32. Validates incoming sensor payload, persists it
     * to MySQL, syncs the latest values to Firebase, checks thresholds and sends
     * SMS alerts / triggers actuator commands as needed.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'temperature' => ['nullable', 'numeric', 'min:-20', 'max:80'],
            'humidity' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'co2_raw' => ['nullable', 'integer', 'min:0', 'max:4095'],
            'light_level' => ['nullable', 'numeric', 'min:0'],
            'soil_moisture' => ['nullable', 'integer', 'min:0', 'max:100'],
            'soil_status' => ['nullable', 'string', 'in:moist,dry,critical,MOIST,DRY,CRITICAL'],
        ]);

        // ── 1. Find the active growing cycle (nullable — sensors may run without one) ──
        $activeCycle = GrowingCycle::where('status', 'active')->latest('start_date')->first();

        // ── 2. Persist to MySQL ────────────────────────────────────────────────
        $reading = SensorReading::create([
            'growing_cycle_id' => $activeCycle?->id,
            'temperature' => $data['temperature'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'co2_raw' => $data['co2_raw'] ?? null,
            'light_level' => $data['light_level'] ?? null,
            'soil_moisture' => $data['soil_moisture'] ?? null,
            'soil_status' => $data['soil_status'] ?? null,
            'recorded_at' => now(),
        ]);

        // ── 3. Push latest values to Firebase (non-blocking best-effort) ──────
        $this->firebase->updateSensors([
            'temperature' => $data['temperature'] ?? null,
            'humidity' => $data['humidity'] ?? null,
            'co2_raw' => $data['co2_raw'] ?? null,
            'light_level' => $data['light_level'] ?? null,
            'soil_moisture' => $data['soil_moisture'] ?? null,
            'soil_status' => $data['soil_status'] ?? null,
            'last_updated' => now()->toISOString(),
        ]);

        // ── 4. Evaluate thresholds and dispatch alerts / actuator commands ────
        $alerts = $this->threshold->evaluate($data);

        foreach ($alerts as $alert) {
            // Send SMS alert (with built-in cooldown)
            $this->sms->sendAlert([
                'sensor' => $alert['sensor'],
                'value' => $alert['value'],
                'threshold' => $alert['threshold'],
                'message' => $alert['message'],
            ]);

            // Trigger actuator if specified
            if ($alert['actuator'] && $alert['actuatorAction']) {
                $this->firebase->setActuator($alert['actuator'], $alert['actuatorAction']);

                $this->threshold->logActuatorCommand(
                    actuator: $alert['actuator'],
                    action: $alert['actuatorAction'],
                    trigger: 'automatic',
                );
            }
        }

        return response()->json([
            'success' => true,
            'reading_id' => $reading->id,
            'cycle_id' => $activeCycle?->id,
            'alerts_triggered' => count($alerts),
            'recorded_at' => $reading->recorded_at,
        ], 201);
    }
}
