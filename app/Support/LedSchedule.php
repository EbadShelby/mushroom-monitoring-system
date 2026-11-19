<?php

namespace App\Support;

use App\Models\Setting;

class LedSchedule
{
    /**
     * Configured LED ON/OFF hours (0-23).
     *
     * @return array{on_hour: int, off_hour: int}
     */
    public static function window(): array
    {
        return [
            'on_hour' => (int) Setting::get('led_on_hour', 6),
            'off_hour' => (int) Setting::get('led_off_hour', 18),
        ];
    }

    /**
     * Determine whether the given hour (or current hour) falls inside the LED ON window.
     * Handles both daytime ranges (e.g. 6 -> 18) and overnight ranges (e.g. 20 -> 6).
     */
    public static function isWithinOnWindow(?int $hour = null): bool
    {
        ['on_hour' => $onHour, 'off_hour' => $offHour] = self::window();
        $currentHour = $hour ?? (int) now()->format('G');

        return $onHour < $offHour
            ? $currentHour >= $onHour && $currentHour < $offHour
            : $currentHour >= $onHour || $currentHour < $offHour;
    }
}
