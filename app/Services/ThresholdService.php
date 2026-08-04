<?php

namespace App\Services;

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\Setting;

class ThresholdService
{
    // ─── Stage: Colonization (spawn running — mycelium spreads through substrate) ─
    private const COLONIZATION_DEFAULTS = [
        'temp_min' => 24.0,  // ideal 25–27°C
        'temp_max' => 28.0,
        'humidity_low' => 70.0,  // alert below 70%
        'humidity_high' => 80.0,  // deactivate humidifier at 80%
        'co2_max' => 5000,  // high CO₂ acceptable during colonization
        'light_max' => 100.0, // keep dark/very dim — alert if > 100 lux
        'soil_warning' => 55,    // substrate moisture warning
        'soil_critical' => 50,    // substrate moisture critical
    ];

    // ─── Stage: Fruiting (mushrooms forming and growing) ──────────────────────────
    private const FRUITING_DEFAULTS = [
        'temp_min' => 20.0,  // ideal 22–24°C
        'temp_max' => 24.0,
        'humidity_low' => 85.0,  // alert below 85%
        'humidity_high' => 95.0,  // deactivate humidifier at 95%
        'co2_max' => 1000,  // keep below 1000 ppm for fruiting
        'light_min' => 200.0, // need indirect light — alert if < 200 lux
        'light_max' => 800.0, // alert if > 800 lux
        'soil_warning' => 55,    // substrate moisture warning
        'soil_critical' => 50,    // substrate moisture critical
    ];

    /** @var array<string, mixed> */
    private array $colonizationThresholds;

    /** @var array<string, mixed> */
    private array $fruitingThresholds;

    public function __construct(private FirebaseService $firebase)
    {
        $this->colonizationThresholds = $this->loadThresholds('col', self::COLONIZATION_DEFAULTS);
        $this->fruitingThresholds = $this->loadThresholds('fruit', self::FRUITING_DEFAULTS);
    }

    /**
     * Load threshold settings from DB for the given stage prefix.
     *
     * DB keys follow pattern: threshold_{prefix}_{key}
     * e.g. threshold_col_temp_min, threshold_fruit_humidity_low
     *
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    private function loadThresholds(string $prefix, array $defaults): array
    {
        $dbKeys = array_map(fn ($k) => "threshold_{$prefix}_{$k}", array_keys($defaults));

        $settings = Setting::whereIn('key', $dbKeys)->pluck('value', 'key');

        $thresholds = [];
        foreach ($defaults as $key => $default) {
            $dbKey = "threshold_{$prefix}_{$key}";
            $thresholds[$key] = $settings->has($dbKey) ? (float) $settings[$dbKey] : $default;
        }

        return $thresholds;
    }

    /**
     * Get the threshold set for a given stage string.
     *
     * @return array<string, mixed>
     */
    public function thresholdsForStage(string $stage): array
    {
        return $stage === 'fruiting' ? $this->fruitingThresholds : $this->colonizationThresholds;
    }

    /**
     * Evaluate all sensor readings against stage-appropriate thresholds.
     *
     * @param  array<string, mixed>  $reading  { temperature, humidity, co2_raw, light_level, soil_moisture }
     * @param  GrowingCycle|null  $cycle  The active growing cycle (stage determines thresholds)
     * @return list<array{sensor: string, value: float|int, threshold: string, message: string, actuator: string|null, actuator_action: string|null}>
     */
    public function evaluate(array $reading, ?GrowingCycle $cycle = null): array
    {
        $stage = $cycle?->growing_stage ?? 'fruiting'; // default to stricter fruiting if unknown
        $t = $this->thresholdsForStage($stage);
        $stageLabel = $stage === 'colonization' ? 'Colonization' : 'Fruiting';

        $alerts = [];

        // Load current actuator states from Firebase once to avoid redundant writes
        $currentActuators = $this->firebase->getActuators();

        // ─── Actuator Interlock Logic ─────────────────────────────────────────────
        $humidifierOn = ($currentActuators['humidifier'] ?? 'off') === 'on';
        $fanOn = ($currentActuators['fan'] ?? 'off') === 'on';

        // 1. Turn off fan if humidifier is on
        if ($humidifierOn && $fanOn) {
            $this->firebase->setActuator('fan', 'off');
            $this->logActuatorCommand('fan', 'off', 'humidifier_override');
            $fanOn = false; // Update local state
            $currentActuators['fan'] = 'off';
        }

        // 2. Determine if fan is allowed to turn on (delay after humidifier turns off)
        $fanAllowed = true;
        if ($humidifierOn) {
            $fanAllowed = false;
        } else {
            // Check if humidifier was turned off recently (e.g., within 5 minutes)
            $lastHumidifierOff = ActuatorLog::where('actuator', 'humidifier')
                ->where('action', 'off')
                ->latest('triggered_at')
                ->first();

            if ($lastHumidifierOff && $lastHumidifierOff->triggered_at->gt(now()->subMinutes(5))) {
                $fanAllowed = false;
            }
        }

        // ─── Temperature ──────────────────────────────────────────────────────────
        if (isset($reading['temperature'])) {
            $temp = (float) $reading['temperature'];

            if ($temp < $t['temp_min']) {
                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: "below {$t['temp_min']}°C",
                    message: "⚠️ [CotSU Mushroom | {$stageLabel}] Temperature LOW: {$temp}°C (min: {$t['temp_min']}°C). Check heating.",
                    actuator: null,
                    actuatorAction: null,
                );
            } elseif ($temp > $t['temp_max']) {
                $message = "🌡️ [CotSU Mushroom | {$stageLabel}] Temperature HIGH: {$temp}°C (above {$t['temp_max']}°C). Intake fan activated to cool the chamber.";
                if (! $fanAllowed) {
                    $message = "🌡️ [CotSU Mushroom | {$stageLabel}] Temperature HIGH: {$temp}°C (above {$t['temp_max']}°C). Fan activation delayed to allow humidity to build up.";
                }

                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: "above {$t['temp_max']}°C",
                    message: $message,
                    actuator: $fanAllowed ? 'fan' : null,
                    actuatorAction: $fanAllowed ? 'on' : null,
                );
            }

            // Auto-deactivate fan when temp drops back to safe range
            if ($temp <= $t['temp_max'] && ($currentActuators['fan'] ?? 'off') === 'on') {
                // Fan may also be on for CO₂ — only deactivate if CO₂ is also safe
                // (handled in CO₂ section below)
            }
        }

        // ─── Humidity ─────────────────────────────────────────────────────────────
        if (isset($reading['humidity'])) {
            $humidity = (float) $reading['humidity'];

            if ($humidity < $t['humidity_low']) {
                $alerts[] = $this->alert(
                    sensor: 'humidity',
                    value: $humidity,
                    threshold: "below {$t['humidity_low']}%",
                    message: "💧 [CotSU Mushroom | {$stageLabel}] Humidity LOW: {$humidity}% (min: {$t['humidity_low']}%). Humidifier activated.",
                    actuator: 'humidifier',
                    actuatorAction: 'on',
                );
            }

            // Auto-deactivate humidifier when humidity is high enough
            if ($humidity >= $t['humidity_high'] && ($currentActuators['humidifier'] ?? 'off') === 'on') {
                $this->firebase->setActuator('humidifier', 'off');
                $this->logActuatorCommand('humidifier', 'off', 'automatic');
            }
        }

        // ─── CO₂ ──────────────────────────────────────────────────────────────────
        if (isset($reading['co2_raw'])) {
            $co2 = (int) $reading['co2_raw'];

            if ($co2 > $t['co2_max']) {
                $co2Label = $stage === 'colonization'
                    ? "above {$t['co2_max']} ppm (colonization ceiling)"
                    : "above {$t['co2_max']} ppm (fruiting requires fresh air)";

                $message = "💨 [CotSU Mushroom | {$stageLabel}] CO₂ HIGH: {$co2} ppm (threshold: {$t['co2_max']} ppm). Intake fan activated for fresh air.";
                if (! $fanAllowed) {
                    $message = "💨 [CotSU Mushroom | {$stageLabel}] CO₂ HIGH: {$co2} ppm. Fan activation delayed to allow humidity to build up.";
                }

                $alerts[] = $this->alert(
                    sensor: 'co2',
                    value: $co2,
                    threshold: $co2Label,
                    message: $message,
                    actuator: $fanAllowed ? 'fan' : null,
                    actuatorAction: $fanAllowed ? 'on' : null,
                );
            }

            // Auto-deactivate fan when CO₂ drops back below threshold
            if ($co2 <= $t['co2_max'] && ($currentActuators['fan'] ?? 'off') === 'on') {
                // Also check temp is not still high before turning fan off
                $tempSafe = ! isset($reading['temperature']) || (float) $reading['temperature'] <= $t['temp_max'];
                if ($tempSafe) {
                    $this->firebase->setActuator('fan', 'off');
                    $this->logActuatorCommand('fan', 'off', 'automatic');
                }
            }
        }

        // ─── Light Level ──────────────────────────────────────────────────────────
        if (isset($reading['light_level'])) {
            $light = (float) $reading['light_level'];

            if ($stage === 'colonization') {
                // Colonization: keep dark — alert if light exceeds max
                if ($light > $t['light_max']) {
                    $alerts[] = $this->alert(
                        sensor: 'light',
                        value: $light,
                        threshold: "above {$t['light_max']} lux",
                        message: "💡 [CotSU Mushroom | Colonization] Light too HIGH: {$light} lux (max: {$t['light_max']} lux). Colonization requires darkness.",
                        actuator: null,
                        actuatorAction: null,
                    );
                }
            } else {
                // Fruiting: needs 200–800 lux indirect light
                if ($light < $t['light_min']) {
                    $alerts[] = $this->alert(
                        sensor: 'light',
                        value: $light,
                        threshold: "below {$t['light_min']} lux",
                        message: "💡 [CotSU Mushroom | Fruiting] Light too LOW: {$light} lux (min: {$t['light_min']} lux). Fruiting needs indirect light.",
                        actuator: null,
                        actuatorAction: null,
                    );
                } elseif ($light > $t['light_max']) {
                    $alerts[] = $this->alert(
                        sensor: 'light',
                        value: $light,
                        threshold: "above {$t['light_max']} lux",
                        message: "💡 [CotSU Mushroom | Fruiting] Light too HIGH: {$light} lux (max: {$t['light_max']} lux). Reduce direct light exposure.",
                        actuator: null,
                        actuatorAction: null,
                    );
                }
            }
        }

        // ─── Soil Moisture ────────────────────────────────────────────────────────
        if (isset($reading['soil_moisture'])) {
            $soil = (int) $reading['soil_moisture'];

            if ($soil < $t['soil_critical']) {
                $alerts[] = $this->alert(
                    sensor: 'soil_moisture',
                    value: $soil,
                    threshold: "below {$t['soil_critical']}% (CRITICAL)",
                    message: "🌱 [CotSU Mushroom | {$stageLabel}] Substrate Moisture CRITICAL: {$soil}%. Immediate watering required!",
                    actuator: null,
                    actuatorAction: null,
                );
            } elseif ($soil < $t['soil_warning']) {
                $alerts[] = $this->alert(
                    sensor: 'soil_moisture',
                    value: $soil,
                    threshold: "below {$t['soil_warning']}% (DRY)",
                    message: "🌱 [CotSU Mushroom | {$stageLabel}] Substrate Moisture LOW: {$soil}%. Please water the substrate soon.",
                    actuator: null,
                    actuatorAction: null,
                );
            }
        }

        return $alerts;
    }

    /**
     * Log an actuator command to actuator_logs (system-triggered).
     */
    public function logActuatorCommand(string $actuator, string $action, string $trigger): void
    {
        ActuatorLog::create([
            'actuator' => $actuator,
            'action' => $action,
            'trigger' => $trigger,
            'triggered_by' => 'system',
            'triggered_at' => now(),
        ]);
    }

    /**
     * @return array{sensor: string, value: float|int, threshold: string, message: string, actuator: string|null, actuator_action: string|null}
     */
    private function alert(
        string $sensor,
        float|int $value,
        string $threshold,
        string $message,
        ?string $actuator,
        ?string $actuatorAction,
    ): array {
        return compact('sensor', 'value', 'threshold', 'message', 'actuator', 'actuatorAction');
    }
}
