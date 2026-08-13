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
     * Returns alerts (for SMS dispatch) and desired actuator commands (for the
     * caller to apply). The service itself performs no Firebase writes or DB logs —
     * all side-effects are handled by the caller (SensorController).
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

        // ─── Determine desired humidifier state ───────────────────────────────
        // null = no change, 'on' = turn on, 'off' = turn off
        $desiredHumidifier = null;

        if (isset($reading['humidity'])) {
            $humidity = (float) $reading['humidity'];

            if ($humidity < $t['humidity_low']) {
                // Only command ON if it's not already on (avoid redundant writes)
                if (! $humidifierCurrentlyOn) {
                    $desiredHumidifier = 'on';
                }

                $alerts[] = $this->alert(
                    sensor: 'humidity',
                    value: $humidity,
                    threshold: "below {$t['humidity_low']}%",
                    message: "💧 [CotSU Mushroom | {$stageLabel}] Humidity LOW: {$humidity}% (min: {$t['humidity_low']}%). Humidifier activated.",
                );
            } elseif ($humidity >= $t['humidity_high'] && $humidifierCurrentlyOn) {
                // Humidity reached upper bound — turn humidifier off
                $desiredHumidifier = 'off';
            }
        }

        // ─── Fan Interlock + Cooldown Logic ───────────────────────────────────
        // Fan is blocked when:
        //   (a) humidifier is currently on, OR
        //   (b) humidifier will be turned on by this evaluation, OR
        //   (c) humidifier was turned off less than 5 minutes ago
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

                $message = $fanAllowed
                    ? "🌡️ [CotSU Mushroom | {$stageLabel}] Temperature HIGH: {$temp}°C (above {$t['temp_max']}°C). Intake fan activated to cool the chamber."
                    : "🌡️ [CotSU Mushroom | {$stageLabel}] Temperature HIGH: {$temp}°C (above {$t['temp_max']}°C). Fan activation delayed to allow humidity to build up.";

                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: "above {$t['temp_max']}°C",
                    message: $message,
                );
            }
        }

        // ─── CO₂ ──────────────────────────────────────────────────────────────
        $co2High = false;

        if (isset($reading['co2_raw'])) {
            $co2 = (int) $reading['co2_raw'];

            if ($co2 > $t['co2_max']) {
                $co2High = true;

                $co2Label = $stage === 'colonization'
                    ? "above {$t['co2_max']} ppm (colonization ceiling)"
                    : "above {$t['co2_max']} ppm (fruiting requires fresh air)";

                $message = $fanAllowed
                    ? "💨 [CotSU Mushroom | {$stageLabel}] CO₂ HIGH: {$co2} ppm (threshold: {$t['co2_max']} ppm). Intake fan activated for fresh air."
                    : "💨 [CotSU Mushroom | {$stageLabel}] CO₂ HIGH: {$co2} ppm. Fan activation delayed to allow humidity to build up.";

                $alerts[] = $this->alert(
                    sensor: 'co2',
                    value: $co2,
                    threshold: $co2Label,
                    message: $message,
                );
            }
        }

        // ─── Light Level ──────────────────────────────────────────────────────
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
        // Determine the single desired fan state based on ALL conditions.
        // This replaces the previous split per-sensor fan logic which left
        // the temperature-triggered fan permanently on.
        $desiredFan = null;

        $needsFan = ($tempHigh || $co2High) && $fanAllowed;

        if ($humidifierWillBeOn && $fanCurrentlyOn) {
            // Interlock: humidifier is (or will be) on — force fan off immediately
            $desiredFan = 'off';
        } elseif ($needsFan && ! $fanCurrentlyOn) {
            // One or both triggers are active and fan is allowed — turn on
            $desiredFan = 'on';
        } elseif (! $needsFan && ! $humidifierWillBeOn && $fanCurrentlyOn) {
            // All conditions cleared and no interlock — turn fan off
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
