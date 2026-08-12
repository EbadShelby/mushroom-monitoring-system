<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $growing_cycle_id
 * @property int $user_id
 * @property string $observed_date
 * @property int $flush_number
 * @property float|null $weight_g
 * @property float|null $height_cm
 * @property float|null $cap_diameter_cm
 * @property int|null $fruiting_body_count
 * @property string|null $photo_path
 * @property string|null $notes
 */
class MushroomMeasurement extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'growing_cycle_id',
        'user_id',
        'observed_date',
        'flush_number',
        'weight_g',
        'height_cm',
        'cap_diameter_cm',
        'fruiting_body_count',
        'photo_path',
        'notes',
    ];

    /** @return BelongsTo<GrowingCycle, $this> */
    public function growingCycle(): BelongsTo
    {
        return $this->belongsTo(GrowingCycle::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
