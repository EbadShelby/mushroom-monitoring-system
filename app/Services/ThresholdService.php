<?php

namespace App\Services;

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\Setting;

class ThresholdService
{
    // ─── Stage: Colonization (spawn running — mycelium spreads through substrate) ─
    private const COLONIZATION_DEFAULTS = [
        'temp_min' => 26.0,  // ideal 26–32°C
        'temp_max' => 32.0,
        'humidity_low' => 75.0,  // humidifier ON below 75%
        'humidity_high' => 95.0,  // humidifier OFF at 95%
        'co2_max' => 5000,  // high CO₂ acceptable during colonization
        'light_max' => 100.0, // keep dark/very dim — alert if > 100 lux
        'soil_warning' => 30,    // substrate moisture warning (matches firmware SOIL_DRY)
        'soil_critical' => 20,    // substrate moisture critical (matches firmware SOIL_CRITICAL)
    ];

    // ─── Stage: Fruiting (mushrooms forming and growing) ──────────────────────────
    private const FRUITING_DEFAULTS = [
        'temp_min' => 26.0,  // ideal 26–32°C
        'temp_max' => 32.0,
        'humidity_low' => 75.0,  // humidifier ON below 75%
        'humidity_high' => 95.0,  // humidifier OFF at 95%
        'co2_max' => 1000,  // keep below 1000 ppm for fruiting
        'light_min' => 200.0, // need indirect light — alert if < 200 lux
        'light_max' => 800.0, // alert if > 800 lux
        'soil_warning' => 30,    // substrate moisture warning (matches firmware SOIL_DRY)
        'soil_critical' => 20,    // substrate moisture critical (matches firmware SOIL_CRITICAL)
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
     * Minutes of continuous humidifier runtime after which humidity still below the
     * low threshold is treated as a fault (empty tank, blocked mist or faulty sensor).
     */
    private const HUMIDIFIER_STALL_MINUTES = 5;

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
     * Default threshold values keyed by their setting key name.
     *
     * @return array<string, float|int>
     */
    public static function defaultThresholdSettings(): array
    {
        $defaults = [];

        foreach (self::COLONIZATION_DEFAULTS as $key => $value) {
            $defaults["threshold_col_{$key}"] = $value;
        }

        foreach (self::FRUITING_DEFAULTS as $key => $value) {
            $defaults["threshold_fruit_{$key}"] = $value;
        }

        return $defaults;
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
     *
     * @param  array<string, mixed>  $reading  { temperature, humidity, co2_raw, light_level, soil_moisture }
     * @param  GrowingCycle|null  $cycle  The active growing cycle (stage determines thresholds)
     * @return array{
     *   alerts: list<array{sensor: string, value: float|int, threshold: string, message: string}>,
     *   commands: array{humidifier: string|null}
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

        // 1b. Stall detection: running for a while yet humidity is still low
        if (! $humidifierRuntimeExceeded && $humidifierCurrentlyOn && $lastHumidifierLog && $lastHumidifierLog->action === 'on'
            && isset($reading['humidity']) && (float) $reading['humidity'] < $t['humidity_low']
            && $lastHumidifierLog->triggered_at->lte(now()->subMinutes(self::HUMIDIFIER_STALL_MINUTES))) {
            $alerts[] = $this->alert(
                sensor: 'humidity',
                value: (float) $reading['humidity'],
                threshold: self::HUMIDIFIER_STALL_MINUTES.'m on, humidity not rising',
                message: "⚠️ [CotSU Mushroom | {$stageLabel}] Humidity still LOW ({$reading['humidity']}%) after ".self::HUMIDIFIER_STALL_MINUTES.' mins of misting. Check the water tank, mist maker and humidity sensor.',
            );
        }

        // 2. Check resting cooldown (if currently OFF)
        $humidifierRested = true;
        if (! $humidifierCurrentlyOn && $lastHumidifierLog && $lastHumidifierLog->action === 'off') {
            if ($lastHumidifierLog->triggered_at->gt(now()->subMinutes(self::HUMIDIFIER_REST_MINUTES))) {
                $humidifierRested = false;
            }
        }

        // 3. Normal humidity evaluation (only if runtime limit not exceeded)
        if (! $humidifierRuntimeExceeded && isset($reading['humidity'])) {
            $humidity = (float) $reading['humidity'];

            // Control band sits inside the alert band so humidity is corrected
            // before it leaves the target range.
            ['on_at' => $turnOnAt, 'off_at' => $turnOffAt] = $this->humidityControlBand($t);

            if ($humidity <= $turnOnAt) {
                if (! $humidifierCurrentlyOn && $humidifierRested) {
                    $desiredHumidifier = 'on';

                    // SMS only when humidity actually left the target range.
                    if ($humidity < $t['humidity_low']) {
                        $alerts[] = $this->alert(
                            sensor: 'humidity',
                            value: $humidity,
                            threshold: "below {$t['humidity_low']}%",
                            message: "💧 [CotSU Mushroom | {$stageLabel}] Humidity LOW: {$humidity}% (min: {$t['humidity_low']}%). Humidifier activated.",
                        );
                    }
                }
            } elseif ($humidity >= $turnOffAt && $humidifierCurrentlyOn) {
                $desiredHumidifier = 'off';
            }
        }

        // ─── Temperature ──────────────────────────────────────────────────────
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
                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: "above {$t['temp_max']}°C",
                    message: "🌡️ [CotSU Mushroom | {$stageLabel}] Temperature HIGH: {$temp}°C (above {$t['temp_max']}°C). Please check chamber ventilation and cooling.",
                );
            }
        }

        // ─── CO₂ ──────────────────────────────────────────────────────────────
        if (isset($reading['co2_raw'])) {
            $co2 = (int) $reading['co2_raw'];

            if ($co2 > $t['co2_max']) {
                $co2Label = $stage === 'colonization'
                    ? "above {$t['co2_max']} ppm (colonization ceiling)"
                    : "above {$t['co2_max']} ppm (fruiting requires fresh air)";

                $alerts[] = $this->alert(
                    sensor: 'co2',
                    value: $co2,
                    threshold: $co2Label,
                    message: "💨 [CotSU Mushroom | {$stageLabel}] CO₂ HIGH: {$co2} ppm (threshold: {$t['co2_max']} ppm). Please air out the chamber manually.",
                );
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

        return [
            'alerts' => $alerts,
            'commands' => [
                'humidifier' => $desiredHumidifier,
            ],
        ];
    }

    /**
     * Humidity levels at which the humidifier switches ON (at or below) and OFF (at or above).
     *
     * @param  array<string, mixed>  $thresholds
     * @return array{on_at: float, off_at: float}
     */
    public function humidityControlBand(array $thresholds): array
    {
        $offAt = (float) $thresholds['humidity_high'] - (float) config('humidifier.humidity_off_margin');
        $onAt = min((float) $thresholds['humidity_low'] + (float) config('humidifier.humidity_on_margin'), $offAt - 1);

        return ['on_at' => $onAt, 'off_at' => $offAt];
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
