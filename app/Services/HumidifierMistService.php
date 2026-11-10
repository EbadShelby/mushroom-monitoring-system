<?php

namespace App\Services;

use App\Models\ActuatorLog;
use App\Models\Setting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class HumidifierMistService
{
    public const REFILLED_AT_KEY = 'humidifier_tank_refilled_at';

    public function rate(): float
    {
        return (float) config('humidifier.mist_rate_ml_per_second');
    }

    public function unitCount(): int
    {
        return max(1, (int) config('humidifier.unit_count'));
    }

    /**
     * Combined output of all units in millilitres per second.
     */
    public function totalRate(): float
    {
        return $this->rate() * $this->unitCount();
    }

    public function capacity(): float
    {
        return (float) config('humidifier.tank_capacity_ml');
    }

    public function lowTankPercent(): int
    {
        return (int) config('humidifier.low_tank_percent');
    }

    /**
     * Alert payload when a tracked tank is at or below the warning level, else null.
     *
     * @return array{sensor: string, value: float, threshold: string, message: string}|null
     */
    public function lowTankAlert(): ?array
    {
        $tank = $this->tank(now());

        if (! $tank['tracking'] || $tank['percent'] > $this->lowTankPercent()) {
            return null;
        }

        return [
            'sensor' => 'water_tank',
            'value' => $tank['remaining_ml'],
            'threshold' => "<= {$this->lowTankPercent()}%",
            'message' => "⚠️ [CotSU Mushroom] Humidifier water tanks are almost empty (~{$tank['remaining_ml']} mL left per tank, {$tank['percent']}%). Please refill all {$this->unitCount()} tanks and press \"Log refill\" in the app.",
        ];
    }

    /**
     * Total mist produced by all units over the given runtime.
     */
    public function mlFor(int|float $seconds): float
    {
        return round($seconds * $this->totalRate(), 1);
    }

    /**
     * Water drawn from a single unit's tank over the given runtime.
     */
    public function tankMlFor(int|float $seconds): float
    {
        return round($seconds * $this->rate(), 1);
    }

    /**
     * Build the on-intervals of the humidifier within a window.
     * A still-running session is closed at the end of the window.
     * Duplicate "on" or "off" entries are ignored.
     *
     * @return list<array{0: int, 1: int}> Unix timestamp pairs [start, end]
     */
    public function intervals(CarbonInterface $from, CarbonInterface $to): array
    {
        $previous = ActuatorLog::query()
            ->where('actuator', 'humidifier')
            ->where('triggered_at', '<', $from)
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->first();

        $onSince = $previous?->action === 'on' ? $from->getTimestamp() : null;
        $intervals = [];

        $logs = ActuatorLog::query()
            ->where('actuator', 'humidifier')
            ->whereBetween('triggered_at', [$from, $to])
            ->orderBy('triggered_at')
            ->orderBy('id')
            ->get(['action', 'triggered_at']);

        foreach ($logs as $log) {
            if ($log->action === 'on') {
                $onSince ??= $log->triggered_at->getTimestamp();
            } elseif ($onSince !== null) {
                $intervals[] = [$onSince, max($onSince, $log->triggered_at->getTimestamp())];
                $onSince = null;
            }
        }

        if ($onSince !== null) {
            $intervals[] = [$onSince, max($onSince, $to->getTimestamp())];
        }

        return $intervals;
    }

    /**
     * Seconds the humidifier was on within a window.
     */
    public function runtimeSeconds(CarbonInterface $from, CarbonInterface $to): int
    {
        return $this->overlap($this->intervals($from, $to), $from, $to);
    }

    /**
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    private function overlap(array $intervals, CarbonInterface $from, CarbonInterface $to): int
    {
        $total = 0;

        foreach ($intervals as [$start, $end]) {
            $total += max(0, min($end, $to->getTimestamp()) - max($start, $from->getTimestamp()));
        }

        return $total;
    }

    public function refillTank(): CarbonInterface
    {
        $now = now();
        Setting::set(self::REFILLED_AT_KEY, $now->toIso8601String());

        return $now;
    }

    public function isRunning(): bool
    {
        return ActuatorLog::query()
            ->where('actuator', 'humidifier')
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->value('action') === 'on';
    }

    /**
     * Summary data for the actuator page.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $now = now();
        $weekStart = $now->copy()->subDays(6)->startOfDay();
        $intervals = $this->intervals($weekStart, $now);

        $week = [];
        for ($day = 0; $day < 7; $day++) {
            $start = $weekStart->copy()->addDays($day);
            $seconds = $this->overlap($intervals, $start, $start->copy()->endOfDay());
            $week[] = [
                'date' => $start->toDateString(),
                'label' => $start->format('D'),
                'seconds' => $seconds,
                'ml' => $this->mlFor($seconds),
            ];
        }

        return [
            'as_of' => $now->toIso8601String(),
            'rate_ml_per_second' => $this->totalRate(),
            'unit_rate_ml_per_second' => $this->rate(),
            'unit_count' => $this->unitCount(),
            'running' => $this->isRunning(),
            'today' => ['seconds' => $week[6]['seconds'], 'ml' => $week[6]['ml']],
            'week' => $week,
            'tank' => $this->tank($now),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function tank(CarbonInterface $now): array
    {
        $refilledAt = Setting::get(self::REFILLED_AT_KEY);
        $capacity = $this->capacity();

        if ($refilledAt === null) {
            return [
                'tracking' => false,
                'capacity_ml' => $capacity,
                'used_ml' => 0.0,
                'remaining_ml' => $capacity,
                'percent' => 100,
                'low_percent' => $this->lowTankPercent(),
                'runtime_left_seconds' => (int) round($capacity / $this->rate()),
                'refilled_at' => null,
            ];
        }

        $since = Carbon::parse($refilledAt);
        $usedMl = min($capacity, $this->tankMlFor($this->runtimeSeconds($since, $now)));
        $remaining = max(0.0, round($capacity - $usedMl, 1));

        return [
            'tracking' => true,
            'capacity_ml' => $capacity,
            'used_ml' => $usedMl,
            'remaining_ml' => $remaining,
            'percent' => (int) round($remaining / $capacity * 100),
            'low_percent' => $this->lowTankPercent(),
            'runtime_left_seconds' => (int) round($remaining / $this->rate()),
            'refilled_at' => $since->toIso8601String(),
        ];
    }
}
