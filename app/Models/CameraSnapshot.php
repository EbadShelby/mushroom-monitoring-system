<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $growing_cycle_id
 * @property string $file_path
 * @property string $file_name
 * @property int $flush_number
 * @property string $captured_date
 * @property string|null $notes
 * @property int $uploaded_by
 */
class CameraSnapshot extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'growing_cycle_id',
        'file_path',
        'file_name',
        'flush_number',
        'captured_date',
        'notes',
        'uploaded_by',
    ];

    public function growingCycle(): BelongsTo
    {
        return $this->belongsTo(GrowingCycle::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
