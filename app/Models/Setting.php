<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $key
 * @property string $value
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Retrieve a setting value by key.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }

    /**
     * Set a setting value by key (upsert).
     */
    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
