<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $growing_cycle_id
 * @property float|null $temperature
 * @property float|null $humidity
 * @property int|null $co2_raw
 * @property float|null $light_level
 * @property int|null $soil_moisture
 * @property string|null $soil_status
 * @property string $recorded_at
 */
class SensorReading extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'growing_cycle_id',
        'temperature',
        'humidity',
        'co2_raw',
        'light_level',
        'soil_moisture',
        'soil_status',
        'recorded_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    /** @return BelongsTo<GrowingCycle, $this> */
    public function growingCycle(): BelongsTo
    {
        return $this->belongsTo(GrowingCycle::class);
    }
}
