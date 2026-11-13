<?php

namespace App\Http\Controllers;

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\Setting;
use App\Models\UserLog;
use App\Services\FirebaseService;
use App\Services\HumidifierMistService;
use App\Services\ThresholdService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActuatorController extends Controller
{
    public function __construct(private FirebaseService $firebase, private ThresholdService $thresholdService, private HumidifierMistService $mist) {}

    public function index(): Response
    {
        $activeCycle = GrowingCycle::where('status', 'active')->latest('start_date')->first();
        $stageThresholds = $this->thresholdService->thresholdsForStage($activeCycle->growing_stage ?? 'fruiting');

        $humidityBand = $this->thresholdService->humidityControlBand($stageThresholds);

        return Inertia::render('ActuatorView', [
            'logs' => Inertia::defer(fn () => ActuatorLog::query()
                ->orderByDesc('triggered_at')
                ->paginate(20)
                ->through(fn (ActuatorLog $log) => $this->withMistData($log))
            ),
            'mist' => fn () => $this->mist->summary(),
            'ledSchedule' => [
                'on_hour' => (int) Setting::get('led_on_hour', 6),
                'off_hour' => (int) Setting::get('led_off_hour', 18),
            ],
            'thresholds' => [
                'temp_max' => (float) $stageThresholds['temp_max'],
                'humidity_low' => (float) $stageThresholds['humidity_low'],
                'humidity_high' => (float) $stageThresholds['humidity_high'],
                'humidifier_on_at' => $humidityBand['on_at'],
                'humidifier_off_at' => $humidityBand['off_at'],
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

    /**
     * Attach on-duration and estimated mist to humidifier "off" entries.
     *
     * @return array<string, mixed>
     */
    private function withMistData(ActuatorLog $log): array
    {
        $data = $log->toArray();
        $data['duration_seconds'] = null;
        $data['mist_ml'] = null;

        if ($log->actuator !== 'humidifier' || $log->action !== 'off') {
            return $data;
        }

        $previous = ActuatorLog::query()
            ->where('actuator', 'humidifier')
            ->where(fn ($query) => $query
                ->where('triggered_at', '<', $log->triggered_at)
                ->orWhere(fn ($tie) => $tie->where('triggered_at', $log->triggered_at)->where('id', '<', $log->id)))
            ->orderByDesc('triggered_at')
            ->orderByDesc('id')
            ->first();

        if ($previous?->action === 'on') {
            $seconds = max(0, $log->triggered_at->getTimestamp() - $previous->triggered_at->getTimestamp());
            $data['duration_seconds'] = $seconds;
            $data['mist_ml'] = $this->mist->mlFor($seconds);
        }

        return $data;
    }

    public function refillTank(): JsonResponse
    {
        $refilledAt = $this->mist->refillTank();

        UserLog::record('Refilled water tank');

        return response()->json([
            'success' => true,
            'refilled_at' => $refilledAt->toIso8601String(),
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
            'triggered_by' => $request->user()->name ?? 'system',
            'triggered_at' => now(),
        ]);

        UserLog::record('Toggled actuator', "{$actuator} -> {$action}");

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

        UserLog::record(
            $active ? 'Enabled manual override' : 'Disabled manual override',
            $actuator,
        );

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

        UserLog::record(
            'Updated LED schedule',
            sprintf('%02d:00 - %02d:00', $request->integer('on_hour'), $request->integer('off_hour')),
        );

        return response()->json([
            'success' => true,
            'on_hour' => $request->integer('on_hour'),
            'off_hour' => $request->integer('off_hour'),
        ]);
    }
}
