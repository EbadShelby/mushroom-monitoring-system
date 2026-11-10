<?php

namespace App\Http\Controllers;

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\Setting;
use App\Services\FirebaseService;
use App\Services\ThresholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActuatorController extends Controller
{
    public function __construct(private FirebaseService $firebase, private ThresholdService $thresholdService) {}

    public function index(): Response
    {
        $activeCycle = GrowingCycle::where('status', 'active')->latest('start_date')->first();
        $stageThresholds = $this->thresholdService->thresholdsForStage($activeCycle?->growing_stage ?? 'fruiting');

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
                'temp_max' => (float) $stageThresholds['temp_max'],
                'humidity_low' => (float) $stageThresholds['humidity_low'],
                'humidity_high' => (float) $stageThresholds['humidity_high'],
                'co2_max' => (int) $stageThresholds['co2_max'],
                'soil_warning' => (int) $stageThresholds['soil_warning'],
                'soil_critical' => (int) $stageThresholds['soil_critical'],
            ],
            'overrides' => [
                'humidifier' => Setting::get('override_humidifier') === '1',
                'led' => Setting::get('override_led') === '1',
            ],
        ]);
    }

    public function toggle(Request $request): JsonResponse
    {
        $request->validate([
            'actuator' => ['required', 'string', 'in:humidifier,led'],
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
            'actuator' => ['required', 'string', 'in:humidifier,led'],
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
