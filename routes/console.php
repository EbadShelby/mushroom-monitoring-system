<?php

use App\Console\Commands\EnforceLedSchedule;
use App\Http\Controllers\Api\CameraSnapshotController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

// Run every minute to enforce the LED on/off schedule
Schedule::command(EnforceLedSchedule::class)->everyMinute()->withoutOverlapping();

// Request a scheduled ESP32-CAM snapshot every 3 hours on the clock (12am, 3am, 6am, 9am, 12pm, 3pm, 6pm, 9pm)
Schedule::call(function (): void {
    Cache::add(
        CameraSnapshotController::CAPTURE_REQUEST_KEY,
        ['scheduled' => true],
        now()->addMinutes(5),
    );
})->cron('0 */3 * * *')->timezone('Asia/Manila')->name('camera-scheduled-capture');
