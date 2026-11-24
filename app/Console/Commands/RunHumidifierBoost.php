<?php

namespace App\Console\Commands;

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use App\Models\Setting;
use App\Services\FirebaseService;
use App\Services\ThresholdService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RunHumidifierBoost extends Command
{
    protected $signature = 'humidifier:boost';

    protected $description = 'Run a sensor-capped misting boost (up to 3 mins, stops early at 93% RH or skips if already >= 85% RH).';

    public function handle(FirebaseService $firebase): int
    {
        if (! config('humidifier.boost_enabled', true)) {
            $this->line('Scheduled humidifier boost is disabled.');

            return self::SUCCESS;
        }

        if (Setting::get('override_humidifier') === '1') {
            $this->line('Humidifier manual override active — skipping boost.');

            return self::SUCCESS;
        }

        if (! GrowingCycle::query()->where('status', 'active')->exists()) {
            $this->line('No active growing cycle — skipping boost.');

            return self::SUCCESS;
        }

        $actuators = $firebase->getActuators();
        if (($actuators['humidifier'] ?? 'off') === 'on') {
            $this->line('Humidifier already ON — skipping boost.');

            return self::SUCCESS;
        }

        $lastLog = ActuatorLog::query()
            ->where('actuator', 'humidifier')
            ->latest('triggered_at')
            ->first();

        if ($lastLog !== null && $lastLog->action === 'off' && $lastLog->triggered_at->gt(now()->subMinutes(3))) {
            $this->line('Humidifier in resting cooldown — skipping boost.');

            return self::SUCCESS;
        }

        $maxStartHumidity = (float) config('humidifier.boost_max_start_humidity', 85);
        $latestReading = SensorReading::query()
            ->whereNotNull('humidity')
            ->where('recorded_at', '>=', now()->subMinutes(5))
            ->latest('recorded_at')
            ->first();

        if ($latestReading === null) {
            $this->line('No recent humidity reading — skipping boost.');

            return self::SUCCESS;
        }

        if ((float) $latestReading->humidity >= $maxStartHumidity) {
            $this->line("Humidity is already {$latestReading->humidity}% (>= {$maxStartHumidity}%) — skipping boost.");

            return self::SUCCESS;
        }

        $durationMinutes = max(1, (int) config('humidifier.boost_duration_minutes', 3));

        Cache::put(
            ThresholdService::BOOST_UNTIL_KEY,
            now()->addMinutes($durationMinutes)->getTimestamp(),
            now()->addMinutes($durationMinutes + 5),
        );

        $firebase->setActuator('humidifier', 'on');

        ActuatorLog::create([
            'actuator' => 'humidifier',
            'action' => 'on',
            'trigger' => 'automatic',
            'triggered_by' => 'scheduled_boost',
            'triggered_at' => now(),
        ]);

        $this->info("Scheduled misting boost started ({$latestReading->humidity}% RH, max {$durationMinutes} mins).");

        return self::SUCCESS;
    }
}
