<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\UserLog;
use App\Rules\PhilippineMobileNumber;
use App\Services\ThresholdService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Translation\PotentiallyTranslatedString;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        if ($request->user()?->role !== 'admin') {
            return Inertia::render('Dashboard');
        }

        return Inertia::render('SystemSettings', [
            'settings' => Setting::all()->pluck('value', 'key'),
            'thresholdDefaults' => ThresholdService::defaultThresholdSettings(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        if (is_string($request->input('sms_recipients'))) {
            $numbers = array_filter(
                array_map(PhilippineMobileNumber::normalize(...), explode(',', $request->input('sms_recipients'))),
            );
            $request->merge(['sms_recipients' => implode(',', $numbers)]);
        }

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
            'sms_recipients' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $rule = new PhilippineMobileNumber;

                foreach (explode(',', $value) as $number) {
                    $rule->validate($attribute, $number, function (string $message) use ($fail, $number): PotentiallyTranslatedString {
                        return $fail("Invalid recipient number: {$number}. Use e.g. 09123456789.");
                    });
                }
            }],
            'system_name' => ['nullable', 'string', 'max:100'],
        ]);

        $updatedKeys = [];
        foreach ($data as $key => $value) {
            if ($value !== null) {
                Setting::set($key, (string) $value);
                $updatedKeys[] = $key;
            }
        }

        if ($updatedKeys !== []) {
            UserLog::record('Updated system settings', implode(', ', $updatedKeys));
        }

        return response()->json(['success' => true]);
    }
}
