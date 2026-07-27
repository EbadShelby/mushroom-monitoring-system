<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(): Response
    {
        if (auth()->user()?->role !== 'admin') {
            return Inertia::render('Dashboard');
        }

        return Inertia::render('SystemSettings', [
            'settings' => Setting::all()->pluck('value', 'key'),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'threshold_temperature_min' => ['nullable', 'numeric'],
            'threshold_temperature_max' => ['nullable', 'numeric'],
            'threshold_humidity_low' => ['nullable', 'numeric'],
            'threshold_humidity_high' => ['nullable', 'numeric'],
            'threshold_co2_max' => ['nullable', 'integer'],
            'threshold_soil_warning' => ['nullable', 'integer'],
            'threshold_soil_critical' => ['nullable', 'integer'],
            'led_on_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'led_off_hour' => ['nullable', 'integer', 'min:0', 'max:23'],
            'sms_recipients' => ['nullable', 'string'],
            'system_name' => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data as $key => $value) {
            if ($value !== null) {
                Setting::set($key, (string) $value);
            }
        }

        return response()->json(['success' => true]);
    }
}
