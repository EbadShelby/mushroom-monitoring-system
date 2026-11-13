<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property int $user_id
 * @property string $action
 * @property string|null $details
 * @property string|null $ip_address
 * @property string $performed_at
 */
class UserLog extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'action',
        'details',
        'ip_address',
        'performed_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'performed_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record a user audit log entry.
     */
    public static function record(string $action, ?string $details = null, ?User $user = null): void
    {
        $actor = $user ?? Auth::user();

        if (! $actor instanceof User) {
            return;
        }

        static::create([
            'user_id' => $actor->id,
            'action' => $action,
            'details' => $details,
            'ip_address' => request()->ip(),
            'performed_at' => now(),
        ]);
    }
}
