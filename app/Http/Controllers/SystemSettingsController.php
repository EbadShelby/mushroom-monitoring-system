<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
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

        $activeSmsUsers = User::query()
            ->whereIn('role', ['admin', 'faculty'])
            ->where('is_active', true)
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->orderBy('name')
            ->get(['id', 'name', 'role', 'contact_number']);

        return Inertia::render('SystemSettings', [
            'settings' => Setting::all()->pluck('value', 'key'),
            'thresholdDefaults' => ThresholdService::defaultThresholdSettings(),
            'activeSmsUsers' => $activeSmsUsers,
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
            // ── Environmental thresholds ──────────────────────────────────────
            'threshold_temp_min' => ['nullable', 'numeric'],
            'threshold_temp_max' => ['nullable', 'numeric'],
            'threshold_humidity_low' => ['nullable', 'numeric'],
            'threshold_humidity_high' => ['nullable', 'numeric'],
            'threshold_co2_max' => ['nullable', 'integer'],
            'threshold_light_min' => ['nullable', 'numeric'],
            'threshold_light_max' => ['nullable', 'numeric'],
            'threshold_soil_warning' => ['nullable', 'integer'],
            'threshold_soil_critical' => ['nullable', 'integer'],
            // ── Humidifier automation ─────────────────────────────────────────
            'humidifier_on_at' => ['nullable', 'numeric', 'min:40', 'max:97'],
            'humidifier_off_at' => ['nullable', 'numeric', 'min:42', 'max:99'],
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

        $defaults = ThresholdService::defaultThresholdSettings();
        $hasHumidifierInput = array_key_exists('humidifier_on_at', $data) && $data['humidifier_on_at'] !== null
            || array_key_exists('humidifier_off_at', $data) && $data['humidifier_off_at'] !== null;

        if ($hasHumidifierInput) {
            $effectiveOn = (float) ($data['humidifier_on_at'] ?? Setting::get('humidifier_on_at') ?? $defaults['humidifier_on_at']);
            $effectiveOff = (float) ($data['humidifier_off_at'] ?? Setting::get('humidifier_off_at') ?? $defaults['humidifier_off_at']);

            if ($effectiveOff < $effectiveOn + 2) {
                return response()->json([
                    'message' => 'Humidifier Auto OFF must be at least 2% higher than Auto ON.',
                    'errors' => [
                        'humidifier_off_at' => ['Humidifier Auto OFF must be at least 2% higher than Auto ON.'],
                    ],
                ], 422);
            }
        }

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
