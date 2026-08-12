<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $actuator
 * @property string $action
 * @property string $trigger
 * @property string|null $triggered_by
 * @property Carbon $triggered_at
 */
class ActuatorLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'actuator',
        'action',
        'trigger',
        'triggered_by',
        'triggered_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'triggered_at' => 'datetime',
    ];
}
