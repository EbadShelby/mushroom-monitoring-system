<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use App\Models\Setting;
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
     *
     * Actuator commands returned by ThresholdService are applied here — not inside
     * the service — so that all Firebase writes and logging are consolidated in one
     * place. Commands are skipped for any actuator with an active manual override.
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

        // ── 4. Evaluate thresholds ─────────────────────────────────────────────
        $result = $this->threshold->evaluate($data, $activeCycle);

        // ── 5. Dispatch SMS alerts ─────────────────────────────────────────────
        foreach ($result['alerts'] as $alert) {
            $this->sms->sendAlert([
                'sensor' => $alert['sensor'],
                'value' => $alert['value'],
                'threshold' => $alert['threshold'],
                'message' => $alert['message'],
            ]);
        }

        // ── 6. Apply actuator commands ─────────────────────────────────────────
        // Skip any actuator that has an active manual override (set via the UI).
        // This prevents automation from overwriting manual control during demos
        // or whenever the operator needs direct relay control.
        $commandsApplied = 0;

        foreach ($result['commands'] as $actuator => $desiredState) {
            if ($desiredState === null) {
                continue; // no change needed
            }

            if (Setting::get("override_{$actuator}") === '1') {
                continue; // manual override active — automation is paused for this relay
            }

            $this->firebase->setActuator($actuator, $desiredState);

            $this->threshold->logActuatorCommand(
                actuator: $actuator,
                action: $desiredState,
                trigger: 'automatic',
            );

            $commandsApplied++;
        }

        return response()->json([
            'success' => true,
            'reading_id' => $reading->id,
            'cycle_id' => $activeCycle?->id,
            'alerts_triggered' => count($result['alerts']),
            'commands_applied' => $commandsApplied,
            'recorded_at' => $reading->recorded_at,
        ], 201);
    }
}
