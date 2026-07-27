<?php

use App\Http\Controllers\ActuatorController;
use App\Http\Controllers\AlertLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoricalController;
use App\Http\Controllers\SystemSettingsController;
use App\Http\Controllers\UserLogController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

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

    // System Settings (admin only)
    Route::get('settings', [SystemSettingsController::class, 'index'])->name('settings');
    Route::put('api/settings', [SystemSettingsController::class, 'updateSettings'])->name('settings.update');
    Route::post('api/users', [SystemSettingsController::class, 'createUser'])->name('users.create');
    Route::put('api/users/{user}', [SystemSettingsController::class, 'updateUser'])->name('users.update');
    Route::delete('api/users/{user}', [SystemSettingsController::class, 'deactivateUser'])->name('users.deactivate');
    Route::patch('api/users/{user}/activate', [SystemSettingsController::class, 'activateUser'])->name('users.activate');
});

require __DIR__.'/settings.php';
