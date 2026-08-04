<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $mushroom_variety
 * @property string $substrate_type
 * @property string $start_date
 * @property string|null $end_date
 * @property string $status
 * @property string $growing_stage
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
        'growing_stage',
        'notes',
    ];

    public function sensorReadings(): HasMany
    {
        return $this->hasMany(SensorReading::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(MushroomMeasurement::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CameraSnapshot::class);
    }
}
