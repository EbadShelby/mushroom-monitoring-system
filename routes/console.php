<?php

use App\Console\Commands\EnforceLedSchedule;
use Illuminate\Support\Facades\Schedule;

// Run every minute to enforce the LED on/off schedule
Schedule::command(EnforceLedSchedule::class)->everyMinute()->withoutOverlapping();
