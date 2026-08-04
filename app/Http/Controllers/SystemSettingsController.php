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
            // ── Colonization thresholds ────────────────────────────────────────
            'threshold_col_temp_min' => ['nullable', 'numeric'],
            'threshold_col_temp_max' => ['nullable', 'numeric'],
            'threshold_col_humidity_low' => ['nullable', 'numeric'],
            'threshold_col_humidity_high' => ['nullable', 'numeric'],
            'threshold_col_co2_max' => ['nullable', 'integer'],
            'threshold_col_light_max' => ['nullable', 'numeric'],
            'threshold_col_soil_warning' => ['nullable', 'integer'],
            'threshold_col_soil_critical' => ['nullable', 'integer'],
            // ── Fruiting thresholds ───────────────────────────────────────────
            'threshold_fruit_temp_min' => ['nullable', 'numeric'],
            'threshold_fruit_temp_max' => ['nullable', 'numeric'],
            'threshold_fruit_humidity_low' => ['nullable', 'numeric'],
            'threshold_fruit_humidity_high' => ['nullable', 'numeric'],
            'threshold_fruit_co2_max' => ['nullable', 'integer'],
            'threshold_fruit_light_min' => ['nullable', 'numeric'],
            'threshold_fruit_light_max' => ['nullable', 'numeric'],
            'threshold_fruit_soil_warning' => ['nullable', 'integer'],
            'threshold_fruit_soil_critical' => ['nullable', 'integer'],
            // ── Other ─────────────────────────────────────────────────────────
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
