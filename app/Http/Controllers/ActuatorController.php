<?php

namespace App\Http\Controllers;

use App\Models\ActuatorLog;
use App\Models\Setting;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActuatorController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    public function index(): Response
    {
        return Inertia::render('ActuatorView', [
            'logs' => Inertia::defer(fn () => ActuatorLog::query()
                ->orderByDesc('triggered_at')
                ->paginate(20)
            ),
            'ledSchedule' => [
                'on_hour' => (int) Setting::get('led_on_hour', 6),
                'off_hour' => (int) Setting::get('led_off_hour', 18),
            ],
            'thresholds' => [
                'temp_max' => (float) Setting::get('threshold_temp_max', 32),
                'humidity_low' => (float) Setting::get('threshold_humidity_low', 80),
                'humidity_high' => (float) Setting::get('threshold_humidity_high', 90),
                'co2_max' => (int) Setting::get('threshold_co2_max', 1000),
                'soil_warning' => (int) Setting::get('threshold_soil_warning', 30),
                'soil_critical' => (int) Setting::get('threshold_soil_critical', 20),
            ],
            'overrides' => [
                'humidifier' => Setting::get('override_humidifier') === '1',
                'fan' => Setting::get('override_fan') === '1',
                'led' => Setting::get('override_led') === '1',
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'actuator' => ['required', 'string', 'in:humidifier,fan,led'],
            'action' => ['required', 'string', 'in:on,off'],
        ]);

        $actuator = $request->input('actuator');
        $action = $request->input('action');

        // Write command to Firebase via FirebaseService (handles auth + correct serialization)
        $this->firebase->setActuator($actuator, $action);

        // Log the manual toggle
        ActuatorLog::create([
            'actuator' => $actuator,
            'action' => $action,
            'trigger' => 'manual',
            'triggered_by' => auth()->user()->name ?? 'system',
            'triggered_at' => now(),
        ]);

        return response()->json(['success' => true, 'actuator' => $actuator, 'action' => $action]);
    }

    /**
     * Toggle the manual override for a relay.
     *
     * When override is active for an actuator, the automation pipeline (ThresholdService)
     * will skip all automatic commands for that relay, giving the operator full manual control.
     * The LED schedule cron also respects this override flag.
     */
    public function toggleOverride(Request $request): JsonResponse
    {
        $request->validate([
            'actuator' => ['required', 'string', 'in:humidifier,fan,led'],
            'active' => ['required', 'boolean'],
        ]);

        $actuator = $request->input('actuator');
        $active = $request->boolean('active');

        Setting::set("override_{$actuator}", $active ? '1' : '0');

        return response()->json([
            'success' => true,
            'actuator' => $actuator,
            'override_active' => $active,
        ]);
    }

    public function schedule(Request $request): JsonResponse
    {
        $request->validate([
            'on_hour' => ['required', 'integer', 'min:0', 'max:23'],
            'off_hour' => ['required', 'integer', 'min:0', 'max:23'],
        ]);

        Setting::set('led_on_hour', (string) $request->integer('on_hour'));
        Setting::set('led_off_hour', (string) $request->integer('off_hour'));

        return response()->json([
            'success' => true,
            'on_hour' => $request->integer('on_hour'),
            'off_hour' => $request->integer('off_hour'),
        ]);
    }
}
