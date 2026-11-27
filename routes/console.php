<?php

use App\Console\Commands\EnforceLedSchedule;
use App\Console\Commands\RunHumidifierBoost;
use App\Http\Controllers\Api\CameraSnapshotController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Run every minute to enforce the LED on/off schedule
Schedule::command(EnforceLedSchedule::class)->everyMinute()->withoutOverlapping(2);

// Sensor-capped misting boost three times daily (7:00 AM, 1:00 PM & 7:00 PM; skips if active recently or RH >= 85%)
Schedule::command(RunHumidifierBoost::class)->cron('0 7,13,19 * * *')->timezone('Asia/Manila')->withoutOverlapping(2);

$scheduleCapture = static function (): void {
    Cache::add(
        CameraSnapshotController::CAPTURE_REQUEST_KEY,
        ['scheduled' => true],
        now()->addMinutes(5),
    );
};

// Daytime captures using the grow LED: 06:01 (after 6am ON), 09:00, 12:00, 15:00, 17:59 (before 6pm OFF)
Schedule::call($scheduleCapture)->cron('1 6 * * *')->timezone('Asia/Manila')->name('camera-capture-6am');
Schedule::call($scheduleCapture)->cron('0 9,12,15 * * *')->timezone('Asia/Manila')->name('camera-capture-day');
Schedule::call($scheduleCapture)->cron('59 17 * * *')->timezone('Asia/Manila')->name('camera-capture-6pm');

// Nighttime captures (rely on ESP32-CAM flash): 9pm, 12am, 3am
Schedule::call($scheduleCapture)->cron('0 0,3,21 * * *')->timezone('Asia/Manila')->name('camera-capture-night');
