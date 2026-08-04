<?php

use App\Http\Controllers\ActuatorController;
use App\Http\Controllers\AlertLogController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GrowingCycleController;
use App\Http\Controllers\HistoricalController;
use App\Http\Controllers\MeasurementController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\UserLogController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Historical
    Route::get('historical', [HistoricalController::class, 'index'])->name('historical');
    Route::get('api/historical', [HistoricalController::class, 'data'])->name('historical.data');
    Route::get('api/historical/export', [HistoricalController::class, 'export'])->name('historical.export');

    // Actuators (admin + faculty)
    Route::get('actuators', [ActuatorController::class, 'index'])->name('actuators');
    Route::post('api/actuators/toggle', [ActuatorController::class, 'toggle'])->name('actuators.toggle');
    Route::put('api/actuators/schedule', [ActuatorController::class, 'schedule'])->name('actuators.schedule');

    // Alert Logs
    Route::get('alerts', [AlertLogController::class, 'index'])->name('alerts');
    Route::get('api/alert-logs/chart', [AlertLogController::class, 'chart'])->name('alerts.chart');

    // User Logs (admin only)
    Route::get('user-logs', [UserLogController::class, 'index'])->name('user-logs');

    // User Management (admin only)
    Route::get('users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('api/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::put('api/users/{user}', [UserManagementController::class, 'update'])->name('users.update');
    Route::delete('api/users/{user}', [UserManagementController::class, 'deactivate'])->name('users.deactivate');
    Route::patch('api/users/{user}/activate', [UserManagementController::class, 'activate'])->name('users.activate');

    // System Settings (admin only)
    Route::get('settings', [SystemSettingsController::class, 'index'])->name('settings');
    Route::put('api/settings', [SystemSettingsController::class, 'updateSettings'])->name('settings.update');

    // Growing Cycles
    Route::get('cycles', [GrowingCycleController::class, 'index'])->name('cycles.index');
    Route::post('api/cycles', [GrowingCycleController::class, 'store'])->name('cycles.store');
    Route::get('cycles/{cycle}', [GrowingCycleController::class, 'show'])->name('cycles.show');
    Route::put('api/cycles/{cycle}', [GrowingCycleController::class, 'update'])->name('cycles.update');
    Route::post('api/cycles/{cycle}/end', [GrowingCycleController::class, 'endCycle'])->name('cycles.end');
    Route::post('api/cycles/{cycle}/switch-stage', [GrowingCycleController::class, 'switchStage'])->name('cycles.switch-stage');
    Route::delete('api/cycles/{cycle}', [GrowingCycleController::class, 'destroy'])->name('cycles.destroy');

    // Camera / Snapshots
    Route::get('camera', [CameraController::class, 'index'])->name('camera.index');
    Route::post('api/camera/upload', [CameraController::class, 'store'])->name('camera.store');
    Route::delete('api/camera/{cameraSnapshot}', [CameraController::class, 'destroy'])->name('camera.destroy');

    // Measurements
    Route::get('measurements', [MeasurementController::class, 'index'])->name('measurements.index');
    Route::post('api/measurements', [MeasurementController::class, 'store'])->name('measurements.store');
    Route::delete('api/measurements/{mushroomMeasurement}', [MeasurementController::class, 'destroy'])->name('measurements.destroy');

    // Reports (PDF download)
    // Reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/{cycle}', [ReportController::class, 'show'])->name('reports.show');
});

require __DIR__.'/settings.php';
