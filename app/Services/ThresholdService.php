<?php

namespace App\Services;

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\Setting;

class ThresholdService
{
    // ─── Stage: Colonization (spawn running — mycelium spreads through substrate) ─
    private const COLONIZATION_DEFAULTS = [
        'temp_min' => 28.0,  // ideal 28–30°C
        'temp_max' => 30.0,
        'humidity_low' => 70.0,  // humidifier ON below 70%
        'humidity_high' => 80.0,  // humidifier OFF at 80%
        'co2_max' => 5000,  // high CO₂ acceptable during colonization
        'light_max' => 100.0, // keep dark/very dim — alert if > 100 lux
        'soil_warning' => 55,    // substrate moisture warning
        'soil_critical' => 50,    // substrate moisture critical
    ];

    // ─── Stage: Fruiting (mushrooms forming and growing) ──────────────────────────
    private const FRUITING_DEFAULTS = [
        'temp_min' => 28.0,  // ideal 28–30°C
        'temp_max' => 30.0,
        'humidity_low' => 85.0,  // humidifier ON below 85%
        'humidity_high' => 95.0,  // humidifier OFF at 95%
        'co2_max' => 1000,  // keep below 1000 ppm for fruiting
        'light_min' => 200.0, // need indirect light — alert if < 200 lux
        'light_max' => 800.0, // alert if > 800 lux
        'soil_warning' => 55,    // substrate moisture warning
        'soil_critical' => 50,    // substrate moisture critical
    ];

    /**
     * Hysteresis margins for fan deactivation.
     *
     * The fan turns ON when a threshold is exceeded, but turns OFF only when the
     * reading drops BELOW the threshold by this margin. This prevents rapid
     * on/off cycling when readings hover right at the boundary.
     *
     * e.g. fan turns ON when CO₂ > 1000 ppm, but only turns OFF when ≤ 900 ppm.
     */
    private const FAN_HYSTERESIS = [
        'temp' => 1.0,   // °C below temp_max before fan turns off
        'co2' => 100,    // ppm below co2_max before fan turns off
    ];

    /**
     * Max continuous runtime for the humidifier before forcing a resting cooldown (in minutes).
     * Protects the ultrasonic mist maker from running dry and overheating.
     */
    private const HUMIDIFIER_MAX_RUN_MINUTES = 15;

    /**
     * Cooldown period for the humidifier after being turned off (in minutes).
     * Gives the transducer plate rest before allowing another activation.
     */
    private const HUMIDIFIER_REST_MINUTES = 3;

    /**
     * Settling window after humidifier turns off before allowing ventilation fans (in minutes).
     * Gives ultrasonic mist time to deposit without trapping the chamber.
     */
    private const HUMIDIFIER_POST_SETTLE_MINUTES = 2;

    /**
     * Emergency thresholds that bypass the humidifier interlock and force ventilation.
     * Dangerous heat or toxic CO2 buildup takes absolute priority over humidity.
     */
    private const EMERGENCY_THRESHOLDS = [
        'temp' => 32.0,           // °C: extreme heat kills pinheads and stalls mycelium
        'co2_fruit' => 2000,      // ppm: severe CO2 in fruiting causing severe deformities
        'co2_colonization' => 6000, // ppm: critical asphyxiation level
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
     * Returns alerts (for SMS dispatch) and desired actuator commands (for the
     * caller to apply). The service itself performs no Firebase writes or DB logs —
     * all side-effects are handled by the caller (SensorController).
     *
     * ── Alert philosophy ────────────────────────────────────────────────────────
     * Alerts are only generated when there is something actionable or genuinely
     * abnormal. Specifically:
     *   - Humidifier: SMS only when humidity is LOW (actionable). The humidifier
     *     turning off at the high threshold is normal operation — no SMS.
     *   - Fan: SMS only when the fan is actually commanded ON. If the fan is
     *     blocked by the humidifier interlock (fanAllowed = false), no SMS is
     *     sent — the alert would be non-actionable and would repeat every 15 min.
     *   - Fan hysteresis: fan turns off only when readings drop below the threshold
     *     minus a deadband margin, preventing rapid on/off cycling.
     *   - Emergency Override: Critical heat (≥ 32°C) or toxic CO₂ (≥ 2000 ppm fruiting)
     *     bypasses the humidifier interlock, shuts down the humidifier, and forces
     *     ventilation push-pull fans ON.
     *
     * @param  array<string, mixed>  $reading  { temperature, humidity, co2_raw, light_level, soil_moisture }
     * @param  GrowingCycle|null  $cycle  The active growing cycle (stage determines thresholds)
     * @return array{
     *   alerts: list<array{sensor: string, value: float|int, threshold: string, message: string}>,
     *   commands: array{humidifier: string|null, fan: string|null}
     * }
     */
    public function evaluate(array $reading, ?GrowingCycle $cycle = null): array
    {
        $stage = $cycle !== null ? $cycle->growing_stage : 'fruiting'; // default to stricter fruiting if unknown
        $t = $this->thresholdsForStage($stage);
        $stageLabel = $stage === 'colonization' ? 'Colonization' : 'Fruiting';

        $alerts = [];

        // Load current actuator states from Firebase once
        $currentActuators = $this->firebase->getActuators();
        $humidifierCurrentlyOn = ($currentActuators['humidifier'] ?? 'off') === 'on';
        $fanCurrentlyOn = ($currentActuators['fan'] ?? 'off') === 'on';

        // ─── Emergency Environmental Check ─────────────────────────────────────
        // Severe heat (≥ 32°C) or dangerous CO₂ buildup (≥ 2000 ppm fruiting / ≥ 6000 ppm col)
        // threatens mushroom survival. Life-support ventilation takes precedence over humidity.
        $emergencyVentilation = false;

        if (isset($reading['temperature']) && (float) $reading['temperature'] >= self::EMERGENCY_THRESHOLDS['temp']) {
            $emergencyVentilation = true;
        }

        $co2EmergencyThreshold = $stage === 'colonization'
            ? self::EMERGENCY_THRESHOLDS['co2_colonization']
            : self::EMERGENCY_THRESHOLDS['co2_fruit'];

        if (isset($reading['co2_raw']) && (int) $reading['co2_raw'] >= $co2EmergencyThreshold) {
            $emergencyVentilation = true;
        }

        // ─── Humidifier ────────────────────────────────────────────────────────
        // null = no change, 'on' = turn on, 'off' = turn off
        $desiredHumidifier = null;

        $lastHumidifierLog = ActuatorLog::where('actuator', 'humidifier')
            ->latest('triggered_at')
            ->first();

        // 1. Check max runtime limit (if currently ON)
        $humidifierRuntimeExceeded = false;
        if ($humidifierCurrentlyOn && $lastHumidifierLog && $lastHumidifierLog->action === 'on') {
            if ($lastHumidifierLog->triggered_at->lte(now()->subMinutes(self::HUMIDIFIER_MAX_RUN_MINUTES))) {
                $humidifierRuntimeExceeded = true;
                $desiredHumidifier = 'off';

                $alerts[] = $this->alert(
                    sensor: 'humidity',
                    value: (float) ($reading['humidity'] ?? 0),
                    threshold: '15m continuous run limit',
                    message: "⚠️ [CotSU Mushroom | {$stageLabel}] Humidifier ran continuously for 15 mins. Auto-rest for ".self::HUMIDIFIER_REST_MINUTES.'m to protect hardware. Please check water reservoir!',
                );
            }
        }

        // 2. Check resting cooldown (if currently OFF)
        $humidifierRested = true;
        if (! $humidifierCurrentlyOn && $lastHumidifierLog && $lastHumidifierLog->action === 'off') {
            if ($lastHumidifierLog->triggered_at->gt(now()->subMinutes(self::HUMIDIFIER_REST_MINUTES))) {
                $humidifierRested = false;
            }
        }

        // 3. Normal humidity evaluation (only if not emergency ventilation and not runtime exceeded)
        if ($emergencyVentilation) {
            if ($humidifierCurrentlyOn) {
                $desiredHumidifier = 'off';
            }
        } elseif (! $humidifierRuntimeExceeded && isset($reading['humidity'])) {
            $humidity = (float) $reading['humidity'];

            if ($humidity < $t['humidity_low']) {
                if (! $humidifierCurrentlyOn && $humidifierRested) {
                    $desiredHumidifier = 'on';

                    $alerts[] = $this->alert(
                        sensor: 'humidity',
                        value: $humidity,
                        threshold: "below {$t['humidity_low']}%",
                        message: "💧 [CotSU Mushroom | {$stageLabel}] Humidity LOW: {$humidity}% (min: {$t['humidity_low']}%). Humidifier activated.",
                    );
                }
            } elseif ($humidity >= $t['humidity_high'] && $humidifierCurrentlyOn) {
                $desiredHumidifier = 'off';
            }
        }

        // ─── Fan Interlock + Cooldown ──────────────────────────────────────────
        // Fan is blocked when:
        //   (a) humidifier is currently on, OR
        //   (b) humidifier will be turned on by this evaluation, OR
        //   (c) humidifier was turned off less than 2 minutes ago (settling window)
        // EXCEPTION: Emergency ventilation (critical heat or dangerous CO₂) bypasses this interlock.
        $humidifierWillBeOn = ($humidifierCurrentlyOn && $desiredHumidifier !== 'off') || $desiredHumidifier === 'on';

        $fanAllowed = true;

        if ($emergencyVentilation) {
            $fanAllowed = true; // Emergency bypasses interlock
        } elseif ($humidifierWillBeOn) {
            $fanAllowed = false;
        } else {
            $lastHumidifierOff = ActuatorLog::where('actuator', 'humidifier')
                ->where('action', 'off')
                ->latest('triggered_at')
                ->first();

            if ($lastHumidifierOff && $lastHumidifierOff->triggered_at->gt(now()->subMinutes(self::HUMIDIFIER_POST_SETTLE_MINUTES))) {
                $fanAllowed = false;
            }
        }

        // ─── Temperature ──────────────────────────────────────────────────────
        $tempHigh = false;
        $tempClear = true; // used for hysteresis — temp is safely below threshold

        if (isset($reading['temperature'])) {
            $temp = (float) $reading['temperature'];

            if ($temp < $t['temp_min']) {
                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: "below {$t['temp_min']}°C",
                    message: "⚠️ [CotSU Mushroom | {$stageLabel}] Temperature LOW: {$temp}°C (min: {$t['temp_min']}°C). Check heating.",
                );
            } elseif ($temp >= self::EMERGENCY_THRESHOLDS['temp']) {
                $tempHigh = true;
                $tempClear = false;

                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: 'CRITICAL: ≥ '.self::EMERGENCY_THRESHOLDS['temp'].'°C',
                    message: "🚨 [CotSU Mushroom | {$stageLabel}] CRITICAL HEAT: {$temp}°C (≥ ".self::EMERGENCY_THRESHOLDS['temp'].'°C). Emergency ventilation activated; humidifier overridden.',
                );
            } elseif ($temp > $t['temp_max']) {
                $tempHigh = true;
                $tempClear = false;

                // Only alert + message if the fan will actually turn on.
                if ($fanAllowed || $fanCurrentlyOn) {
                    $alerts[] = $this->alert(
                        sensor: 'temperature',
                        value: $temp,
                        threshold: "above {$t['temp_max']}°C",
                        message: "🌡️ [CotSU Mushroom | {$stageLabel}] Temperature HIGH: {$temp}°C (above {$t['temp_max']}°C). Ventilation fans (intake & exhaust) activated to cool the chamber.",
                    );
                }
            } else {
                $tempClear = $temp <= ($t['temp_max'] - self::FAN_HYSTERESIS['temp']);
            }
        }

        // ─── CO₂ ──────────────────────────────────────────────────────────────
        $co2High = false;
        $co2Clear = true; // used for hysteresis — co2 is safely below threshold

        if (isset($reading['co2_raw'])) {
            $co2 = (int) $reading['co2_raw'];

            if ($co2 >= $co2EmergencyThreshold) {
                $co2High = true;
                $co2Clear = false;

                $alerts[] = $this->alert(
                    sensor: 'co2',
                    value: $co2,
                    threshold: "CRITICAL: ≥ {$co2EmergencyThreshold} ppm",
                    message: "🚨 [CotSU Mushroom | {$stageLabel}] CRITICAL CO₂: {$co2} ppm (dangerous buildup). Emergency ventilation activated; humidifier overridden.",
                );
            } elseif ($co2 > $t['co2_max']) {
                $co2High = true;
                $co2Clear = false;

                $co2Label = $stage === 'colonization'
                    ? "above {$t['co2_max']} ppm (colonization ceiling)"
                    : "above {$t['co2_max']} ppm (fruiting requires fresh air)";

                if ($fanAllowed || $fanCurrentlyOn) {
                    $alerts[] = $this->alert(
                        sensor: 'co2',
                        value: $co2,
                        threshold: $co2Label,
                        message: "💨 [CotSU Mushroom | {$stageLabel}] CO₂ HIGH: {$co2} ppm (threshold: {$t['co2_max']} ppm). Ventilation fans (intake & exhaust) activated for fresh air exchange.",
                    );
                }
            } else {
                // CO₂ in range — apply hysteresis deadband before allowing fan off
                $co2Clear = $co2 <= ($t['co2_max'] - self::FAN_HYSTERESIS['co2']);
            }
        }

        // ─── Light Level ──────────────────────────────────────────────────────
        if (isset($reading['light_level'])) {
            $light = (float) $reading['light_level'];

            if ($stage === 'colonization') {
                if ($light > $t['light_max']) {
                    $alerts[] = $this->alert(
                        sensor: 'light',
                        value: $light,
                        threshold: "above {$t['light_max']} lux",
                        message: "💡 [CotSU Mushroom | Colonization] Light too HIGH: {$light} lux (max: {$t['light_max']} lux). Colonization requires darkness.",
                    );
                }
            } else {
                if ($light < $t['light_min']) {
                    $alerts[] = $this->alert(
                        sensor: 'light',
                        value: $light,
                        threshold: "below {$t['light_min']} lux",
                        message: "💡 [CotSU Mushroom | Fruiting] Light too LOW: {$light} lux (min: {$t['light_min']} lux). Fruiting needs indirect light.",
                    );
                } elseif ($light > $t['light_max']) {
                    $alerts[] = $this->alert(
                        sensor: 'light',
                        value: $light,
                        threshold: "above {$t['light_max']} lux",
                        message: "💡 [CotSU Mushroom | Fruiting] Light too HIGH: {$light} lux (max: {$t['light_max']} lux). Reduce direct light exposure.",
                    );
                }
            }
        }

        // ─── Soil Moisture ────────────────────────────────────────────────────
        if (isset($reading['soil_moisture'])) {
            $soil = (int) $reading['soil_moisture'];

            if ($soil < $t['soil_critical']) {
                $alerts[] = $this->alert(
                    sensor: 'soil_moisture',
                    value: $soil,
                    threshold: "below {$t['soil_critical']}% (CRITICAL)",
                    message: "🌱 [CotSU Mushroom | {$stageLabel}] Substrate Moisture CRITICAL: {$soil}%. Immediate watering required!",
                );
            } elseif ($soil < $t['soil_warning']) {
                $alerts[] = $this->alert(
                    sensor: 'soil_moisture',
                    value: $soil,
                    threshold: "below {$t['soil_warning']}% (DRY)",
                    message: "🌱 [CotSU Mushroom | {$stageLabel}] Substrate Moisture LOW: {$soil}%. Please water the substrate soon.",
                );
            }
        }

        // ─── Unified Fan Resolution ────────────────────────────────────────────
        // Fan state is decided ONCE here, after all sensors are evaluated.
        //
        // Turn-on: fan is needed ($needsFan) and currently off.
        // Turn-off: uses hysteresis — both temp AND co2 must be clearly below
        //   their respective thresholds (by FAN_HYSTERESIS margin) before the fan
        //   turns off. This prevents rapid on/off cycling when readings hover at
        //   the boundary.
        // Interlock: if humidifier is (or will be) on, fan is forced off (unless emergency).
        $desiredFan = null;

        $needsFan = ($tempHigh || $co2High) && $fanAllowed;

        // Both conditions must be clearly in the safe zone before allowing fan off.
        // If a sensor value is absent, treat that dimension as clear (safe default).
        $allConditionsClear = $tempClear && $co2Clear;

        if ($humidifierWillBeOn && $fanCurrentlyOn && ! $emergencyVentilation) {
            // Interlock: humidifier is (or will be) on — force fan off immediately
            $desiredFan = 'off';
        } elseif ($needsFan && ! $fanCurrentlyOn) {
            // Trigger(s) active and fan is allowed — turn on
            $desiredFan = 'on';
        } elseif ($allConditionsClear && ! $humidifierWillBeOn && $fanCurrentlyOn) {
            // All readings have dropped safely below their thresholds — turn fan off
            $desiredFan = 'off';
        }

        return [
            'alerts' => $alerts,
            'commands' => [
                'humidifier' => $desiredHumidifier,
                'fan' => $desiredFan,
            ],
        ];
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
     * @return array{sensor: string, value: float|int, threshold: string, message: string}
     */
    private function alert(
        string $sensor,
        float|int $value,
        string $threshold,
        string $message,
    ): array {
        return [
            'sensor' => $sensor,
            'value' => $value,
            'threshold' => $threshold,
            'message' => $message,
        ];
    }
}
