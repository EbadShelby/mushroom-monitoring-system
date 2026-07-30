<?php

namespace App\Services;

use App\Models\ActuatorLog;
use App\Models\Setting;

class ThresholdService
{
    // ─── Threshold defaults (fallback if DB settings missing) ─────────────────
    private const DEFAULTS = [
        'temp_min' => 28.0,
        'temp_max' => 32.0,
        'humidity_low' => 80.0,
        'humidity_high' => 90.0,
        'co2_max' => 1000,
        'light_min' => 50.0,
        'light_max' => 1000.0,
        'soil_warning' => 30,
        'soil_critical' => 20,
    ];

    /** @var array<string, mixed> */
    private array $thresholds;

    public function __construct()
    {
        // Load all threshold settings from DB in one query
        $settings = Setting::whereIn('key', array_map(
            fn ($k) => "threshold_{$k}",
            array_keys(self::DEFAULTS)
        ))->pluck('value', 'key');

        $this->thresholds = [];

        foreach (self::DEFAULTS as $key => $default) {
            $dbKey = "threshold_{$key}";
            $this->thresholds[$key] = $settings->has($dbKey) ? (float) $settings[$dbKey] : $default;
        }
    }

    /**
     * Evaluate all sensor readings against thresholds.
     *
     * Returns a list of alerts:
     *
     * @param  array<string, mixed>  $reading  { temperature, humidity, co2_raw, light_level, soil_moisture }
     * @return list<array{sensor: string, value: float|int, threshold: string, message: string, actuator: string|null, actuator_action: string|null}>
     */
    public function evaluate(array $reading): array
    {
        $alerts = [];

        // ─── Temperature ──────────────────────────────────────────────────────
        if (isset($reading['temperature'])) {
            $temp = (float) $reading['temperature'];

            if ($temp < $this->thresholds['temp_min']) {
                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: "below {$this->thresholds['temp_min']}°C",
                    message: "⚠️ [CotSU Mushroom] Temperature LOW: {$temp}°C (min: {$this->thresholds['temp_min']}°C). Check heating.",
                    actuator: null,
                    actuatorAction: null,
                );
            } elseif ($temp > $this->thresholds['temp_max']) {
                $alerts[] = $this->alert(
                    sensor: 'temperature',
                    value: $temp,
                    threshold: "above {$this->thresholds['temp_max']}°C",
                    message: "⚠️ [CotSU Mushroom] Temperature HIGH: {$temp}°C (max: {$this->thresholds['temp_max']}°C). Activating fan.",
                    actuator: 'fan',
                    actuatorAction: 'on',
                );
            }
        }

        // ─── Humidity ─────────────────────────────────────────────────────────
        if (isset($reading['humidity'])) {
            $humidity = (float) $reading['humidity'];

            if ($humidity < $this->thresholds['humidity_low']) {
                $alerts[] = $this->alert(
                    sensor: 'humidity',
                    value: $humidity,
                    threshold: "below {$this->thresholds['humidity_low']}%",
                    message: "💧 [CotSU Mushroom] Humidity LOW: {$humidity}% (min: {$this->thresholds['humidity_low']}%). Humidifier activated.",
                    actuator: 'humidifier',
                    actuatorAction: 'on',
                );
            }

            // Auto-deactivate humidifier when humidity is high enough
            if ($humidity >= $this->thresholds['humidity_high']) {
                $this->logActuatorCommand('humidifier', 'off', 'automatic');
            }
        }

        // ─── CO2 ──────────────────────────────────────────────────────────────
        if (isset($reading['co2_raw'])) {
            $co2 = (int) $reading['co2_raw'];

            if ($co2 > $this->thresholds['co2_max']) {
                $alerts[] = $this->alert(
                    sensor: 'co2',
                    value: $co2,
                    threshold: "above {$this->thresholds['co2_max']} raw",
                    message: "💨 [CotSU Mushroom] CO2 HIGH: {$co2} raw ADC (threshold: {$this->thresholds['co2_max']}). Fan activated for ventilation.",
                    actuator: 'fan',
                    actuatorAction: 'on',
                );
            }
        }

        // ─── Soil Moisture ────────────────────────────────────────────────────
        if (isset($reading['soil_moisture'])) {
            $soil = (int) $reading['soil_moisture'];

            if ($soil < $this->thresholds['soil_critical']) {
                $alerts[] = $this->alert(
                    sensor: 'soil_moisture',
                    value: $soil,
                    threshold: "below {$this->thresholds['soil_critical']}% (CRITICAL)",
                    message: "🌱 [CotSU Mushroom] Soil Moisture CRITICAL: {$soil}%. Immediate watering required!",
                    actuator: null,
                    actuatorAction: null,
                );
            } elseif ($soil < $this->thresholds['soil_warning']) {
                $alerts[] = $this->alert(
                    sensor: 'soil_moisture',
                    value: $soil,
                    threshold: "below {$this->thresholds['soil_warning']}% (DRY)",
                    message: "🌱 [CotSU Mushroom] Soil Moisture LOW: {$soil}%. Please water the substrate soon.",
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
