<?php

use App\Http\Controllers\Api\ActuatorController;
use App\Http\Controllers\Api\SensorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ESP32 / IoT API Routes
|--------------------------------------------------------------------------
|
| These routes are consumed by the ESP32 microcontroller.
| Security is handled at the network level (local LAN).
|
| Camera snapshots are uploaded MANUALLY by faculty through the web
| dashboard (/camera) — not via this API. The CCTV is not an ESP32-CAM.
|
*/

Route::post('/sensor-data', [SensorController::class, 'store']);
Route::get('/actuator-commands', [ActuatorController::class, 'getPending']);
