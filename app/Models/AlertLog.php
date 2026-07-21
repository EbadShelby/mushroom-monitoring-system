<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $sensor
 * @property float $value_at_alert
 * @property string $threshold_exceeded
 * @property string $recipient_number
 * @property string $message
 * @property string $status
 * @property string $sent_at
 */
class AlertLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'sensor',
        'value_at_alert',
        'threshold_exceeded',
        'recipient_number',
        'message',
        'status',
        'sent_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'sent_at' => 'datetime',
    ];
}
