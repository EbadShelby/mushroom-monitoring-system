<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string $name
 * @property string $mushroom_variety
 * @property list<array{variety: string, count: int}>|null $fruiting_bags
 * @property string $substrate_type
 * @property string $start_date
 * @property string|null $end_date
 * @property string $status
 * @property string|null $notes
 */
class GrowingCycle extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'mushroom_variety',
        'fruiting_bags',
        'substrate_type',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fruiting_bags' => 'array',
        ];
    }

    /** @return HasMany<SensorReading, $this> */
    public function sensorReadings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    /** @return HasMany<MushroomMeasurement, $this> */
    public function measurements(): HasMany
    {
        return $this->hasMany(MushroomMeasurement::class);
    }

    /** @return HasMany<CameraSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(CameraSnapshot::class);
    }

    /**
     * Number of days elapsed in this cycle (1-indexed, minimum 1).
     */
    public function dayCount(): int
    {
        $start = Carbon::parse($this->start_date)->startOfDay();
        $end = ($this->end_date !== null ? Carbon::parse($this->end_date) : now())->startOfDay();

        return max(1, (int) $start->diffInDays($end) + 1);
    }

    /**
     * Count out-of-range threshold breaches per sensor using a rolling 15-minute
     * cooldown (matching the SMS alert cooldown). High-humidity spikes (> 95%)
     * within 3 minutes after the humidifier shuts off are ignored while mist settles.
     *
     * @return array{temperature: int, humidity: int, co2: int, soil_moisture: int, total_readings: int}
     */
    public function breachSummary(): array
    {
        $readings = $this->sensorReadings()
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['temperature', 'humidity', 'co2_raw', 'soil_moisture', 'recorded_at']);

        $offTimestamps = ActuatorLog::query()
            ->where('actuator', 'humidifier')
            ->where('action', 'off')
            ->where('triggered_at', '>=', Carbon::parse($this->start_date)->startOfDay()->subMinutes(3))
            ->when($this->end_date !== null, fn ($q) => $q->where('triggered_at', '<=', Carbon::parse($this->end_date)->endOfDay()))
            ->pluck('triggered_at')
            ->map(fn ($ts) => Carbon::parse($ts)->getTimestamp())
            ->all();

        $isPostOffSettling = static function (SensorReading $r) use ($offTimestamps): bool {
            $ts = Carbon::parse($r->recorded_at)->getTimestamp();

            foreach ($offTimestamps as $offTs) {
                $diff = $ts - $offTs;
                if ($diff >= 0 && $diff <= 180) {
                    return true;
                }
            }

            return false;
        };

        return [
            'temperature' => $this->countBreachesWithCooldown(
                $readings,
                static fn (SensorReading $r): bool => $r->temperature !== null && ($r->temperature < 26 || $r->temperature > 32),
            ),
            'humidity' => $this->countBreachesWithCooldown(
                $readings,
                static fn (SensorReading $r): bool => $r->humidity !== null && ($r->humidity < 75 || ($r->humidity > 95 && ! $isPostOffSettling($r))),
            ),
            'co2' => $this->countBreachesWithCooldown(
                $readings,
                static fn (SensorReading $r): bool => $r->co2_raw !== null && $r->co2_raw > 1000,
            ),
            'soil_moisture' => $this->countBreachesWithCooldown(
                $readings,
                static fn (SensorReading $r): bool => $r->soil_moisture !== null && $r->soil_moisture < 60,
            ),
            'total_readings' => $readings->count(),
        ];
    }

    /**
     * Count chronologically ordered breaches with a rolling cooldown window.
     *
     * @param  Collection<int, SensorReading>  $readings
     * @param  callable(SensorReading): bool  $isBreach
     */
    private function countBreachesWithCooldown(
        Collection $readings,
        callable $isBreach,
        int $cooldownSeconds = 900,
    ): int {
        $count = 0;
        $lastCountedTs = null;

        foreach ($readings as $reading) {
            if (! $isBreach($reading)) {
                continue;
            }

            $ts = Carbon::parse($reading->recorded_at)->getTimestamp();

            if ($lastCountedTs === null || ($ts - $lastCountedTs) >= $cooldownSeconds) {
                $count++;
                $lastCountedTs = $ts;
            }
        }

        return $count;
    }
}
