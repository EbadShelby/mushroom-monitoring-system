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
        'humidity_low' => 70.0,  // humidifier ON below 70%
        'humidity_high' => 80.0,  // humidifier OFF at 80%
        'co2_max' => 5000,  // high CO₂ acceptable during colonization
        'light_max' => 100.0, // keep dark/very dim — alert if > 100 lux
        'soil_warning' => 55,    // substrate moisture warning
        'soil_critical' => 50,    // substrate moisture critical
    ];

    // ─── Stage: Fruiting (mushrooms forming and growing) ──────────────────────────
    private const FRUITING_DEFAULTS = [
        'temp_min' => 20.0,  // ideal 22–24°C
        'temp_max' => 24.0,
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

        // ─── Humidifier ────────────────────────────────────────────────────────
        // null = no change, 'on' = turn on, 'off' = turn off
        $desiredHumidifier = null;

        if (isset($reading['humidity'])) {
            $humidity = (float) $reading['humidity'];

            if ($humidity < $t['humidity_low']) {
                if (! $humidifierCurrentlyOn) {
                    // Humidifier is off and needs to turn on — command it and alert
                    $desiredHumidifier = 'on';

                    $alerts[] = $this->alert(
                        sensor: 'humidity',
                        value: $humidity,
                        threshold: "below {$t['humidity_low']}%",
                        message: "💧 [CotSU Mushroom | {$stageLabel}] Humidity LOW: {$humidity}% (min: {$t['humidity_low']}%). Humidifier activated.",
                    );
                }
                // If humidifier is already on and humidity is still recovering — no alert.
                // An SMS was already sent when it turned on. Sending again would be spam.
            } elseif ($humidity >= $t['humidity_high'] && $humidifierCurrentlyOn) {
                // Humidity reached the upper bound — turn humidifier off.
                // This is normal operation (humidifier did its job), NOT an alert condition.
                $desiredHumidifier = 'off';
            }
        }

        // ─── Fan Interlock + Cooldown ──────────────────────────────────────────
        // Fan is blocked when:
        //   (a) humidifier is currently on, OR
        //   (b) humidifier will be turned on by this evaluation, OR
        //   (c) humidifier was turned off less than 5 minutes ago
        //       (allows humidity to build before ventilation disperses it)
        $humidifierWillBeOn = $humidifierCurrentlyOn || $desiredHumidifier === 'on';

        $fanAllowed = true;

        if ($humidifierWillBeOn) {
            $fanAllowed = false;
        } else {
            $lastHumidifierOff = ActuatorLog::where('actuator', 'humidifier')
                ->where('action', 'off')
                ->latest('triggered_at')
                ->first();

            if ($lastHumidifierOff && $lastHumidifierOff->triggered_at->gt(now()->subMinutes(5))) {
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
            } elseif ($temp > $t['temp_max']) {
                $tempHigh = true;
                $tempClear = false;

                // Only alert + message if the fan will actually turn on.
                // If fanAllowed = false, the fan is blocked by the humidifier —
                // sending an SMS would be non-actionable and would repeat every 15 min.
                if ($fanAllowed || $fanCurrentlyOn) {
                    $alerts[] = $this->alert(
                        sensor: 'temperature',
                        value: $temp,
                        threshold: "above {$t['temp_max']}°C",
                        message: "🌡️ [CotSU Mushroom | {$stageLabel}] Temperature HIGH: {$temp}°C (above {$t['temp_max']}°C). Intake fan activated to cool the chamber.",
                    );
                }
            } else {
                // Temperature is in range — check hysteresis deadband for fan deactivation.
                // Fan only turns off when temp is at least FAN_HYSTERESIS['temp'] below the max.
                $tempClear = $temp <= ($t['temp_max'] - self::FAN_HYSTERESIS['temp']);
            }
        }

        // ─── CO₂ ──────────────────────────────────────────────────────────────
        $co2High = false;
        $co2Clear = true; // used for hysteresis — co2 is safely below threshold

        if (isset($reading['co2_raw'])) {
            $co2 = (int) $reading['co2_raw'];

            if ($co2 > $t['co2_max']) {
                $co2High = true;
                $co2Clear = false;

                $co2Label = $stage === 'colonization'
                    ? "above {$t['co2_max']} ppm (colonization ceiling)"
                    : "above {$t['co2_max']} ppm (fruiting requires fresh air)";

                // Same principle as temperature: only alert if fan is actually going to act.
                if ($fanAllowed || $fanCurrentlyOn) {
                    $alerts[] = $this->alert(
                        sensor: 'co2',
                        value: $co2,
                        threshold: $co2Label,
                        message: "💨 [CotSU Mushroom | {$stageLabel}] CO₂ HIGH: {$co2} ppm (threshold: {$t['co2_max']} ppm). Intake fan activated for fresh air.",
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
        // Interlock: if humidifier is (or will be) on, fan is forced off.
        $desiredFan = null;

        $needsFan = ($tempHigh || $co2High) && $fanAllowed;

        // Both conditions must be clearly in the safe zone before allowing fan off.
        // If a sensor value is absent, treat that dimension as clear (safe default).
        $allConditionsClear = $tempClear && $co2Clear;

        if ($humidifierWillBeOn && $fanCurrentlyOn) {
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
