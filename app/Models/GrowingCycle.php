<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

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
     * Count out-of-range threshold breaches, deduplicated to at most 1 per minute per sensor.
     * High-humidity spikes (> 95%) within 3 minutes after the humidifier shuts off are ignored
     * while residual mist settles.
     *
     * @return array{temperature: int, humidity: int, co2: int, soil_moisture: int, total_readings: int}
     */
    public function breachSummary(): array
    {
        $readings = $this->sensorReadings()->get(['temperature', 'humidity', 'co2_raw', 'soil_moisture', 'recorded_at']);

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

        $minuteBucket = static fn (SensorReading $r): string => Carbon::parse($r->recorded_at)->format('Y-m-d H:i');

        return [
            'temperature' => $readings->filter(
                fn (SensorReading $r) => $r->temperature !== null && ($r->temperature < 26 || $r->temperature > 32)
            )->unique($minuteBucket)->count(),
            'humidity' => $readings->filter(function (SensorReading $r) use ($isPostOffSettling): bool {
                if ($r->humidity === null) {
                    return false;
                }

                if ($r->humidity < 75) {
                    return true;
                }

                return $r->humidity > 95 && ! $isPostOffSettling($r);
            })->unique($minuteBucket)->count(),
            'co2' => $readings->filter(
                fn (SensorReading $r) => $r->co2_raw !== null && $r->co2_raw > 1000
            )->unique($minuteBucket)->count(),
            'soil_moisture' => $readings->filter(
                fn (SensorReading $r) => $r->soil_moisture !== null && $r->soil_moisture < 60
            )->unique($minuteBucket)->count(),
            'total_readings' => $readings->count(),
        ];
    }
}
