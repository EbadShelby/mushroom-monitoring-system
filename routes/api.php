<?php

use App\Http\Controllers\Api\ActuatorController;
use App\Http\Controllers\Api\CameraSnapshotController;
use App\Http\Controllers\Api\SensorController;
use App\Http\Middleware\VerifyDeviceToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| ESP32 / IoT API Routes
|--------------------------------------------------------------------------
|
| These routes are consumed by the ESP32 microcontrollers over HTTPS
| (protected by X-Device-Token and X-Camera-Token headers).
|
*/

Route::middleware(VerifyDeviceToken::class)->group(function () {
    Route::post('/sensor-data', [SensorController::class, 'store']);
    Route::get('/actuator-commands', [ActuatorController::class, 'getPending']);
});

Route::post('/camera-snapshot', [CameraSnapshotController::class, 'store']);
Route::get('/camera-command', [CameraSnapshotController::class, 'command']);
