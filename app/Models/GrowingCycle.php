<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $mushroom_variety
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
        'substrate_type',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

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
}
