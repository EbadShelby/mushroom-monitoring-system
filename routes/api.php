<?php

use App\Http\Controllers\Api\ActuatorController;
use App\Http\Controllers\Api\CameraSnapshotController;
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
| Camera snapshots are uploaded automatically by the ESP32-CAM to
| /api/camera-snapshot (protected by the X-Camera-Token header). Faculty can
| still upload photos manually through the web dashboard (/camera).
|
*/

Route::post('/sensor-data', [SensorController::class, 'store']);
Route::get('/actuator-commands', [ActuatorController::class, 'getPending']);
Route::post('/camera-snapshot', [CameraSnapshotController::class, 'store']);
Route::get('/camera-command', [CameraSnapshotController::class, 'command']);
