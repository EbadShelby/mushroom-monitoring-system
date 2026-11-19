<?php

namespace App\Console\Commands;

use App\Models\ActuatorLog;
use App\Models\Setting;
use App\Services\FirebaseService;
use App\Support\LedSchedule;
use Illuminate\Console\Command;

class EnforceLedSchedule extends Command
{
    protected $signature = 'led:schedule';

    protected $description = 'Turn the LED grow light ON or OFF based on the configured schedule (led_on_hour / led_off_hour).';

    public function handle(FirebaseService $firebase): int
    {
        // Respect manual override — if the operator has locked the LED relay,
        // skip the schedule entirely so they retain full control.
        if (Setting::get('override_led') === '1') {
            $this->line('LED override active — skipping schedule.');

            return self::SUCCESS;
        }

        ['on_hour' => $onHour, 'off_hour' => $offHour] = LedSchedule::window();
        $desiredAction = LedSchedule::isWithinOnWindow() ? 'on' : 'off';

        // Fetch the current LED state from Firebase to avoid redundant writes/logs
        $currentActuators = $firebase->getActuators();
        $currentLedState = $currentActuators['led'] ?? null;

        if ($currentLedState === $desiredAction) {
            // Nothing to do — already in the correct state
            $this->line("LED already {$desiredAction} — no change needed.");

            return self::SUCCESS;
        }

        // Push the new state to Firebase
        $firebase->setActuator('led', $desiredAction);

        // Log the schedule-triggered action
        ActuatorLog::create([
            'actuator' => 'led',
            'action' => $desiredAction,
            'trigger' => 'automatic',
            'triggered_by' => 'system',
            'triggered_at' => now(),
        ]);

        $this->info("LED turned {$desiredAction} by schedule (on:{$onHour}:00 / off:{$offHour}:00).");

        return self::SUCCESS;
    }
}
