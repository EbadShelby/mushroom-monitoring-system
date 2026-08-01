<?php

namespace App\Console\Commands;

use App\Models\ActuatorLog;
use App\Models\Setting;
use App\Services\FirebaseService;
use Illuminate\Console\Command;

class EnforceLedSchedule extends Command
{
    protected $signature = 'led:schedule';

    protected $description = 'Turn the LED grow light ON or OFF based on the configured schedule (led_on_hour / led_off_hour).';

    public function handle(FirebaseService $firebase): int
    {
        $onHour = (int) Setting::get('led_on_hour', 6);
        $offHour = (int) Setting::get('led_off_hour', 18);
        $currentHour = (int) now()->format('G');

        // Determine if we're inside the ON window.
        // Handles both normal ranges (e.g. 6 → 18) and overnight ranges (e.g. 20 → 6).
        $isOn = $onHour < $offHour
            ? $currentHour >= $onHour && $currentHour < $offHour
            : $currentHour >= $onHour || $currentHour < $offHour;

        $desiredAction = $isOn ? 'on' : 'off';

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
            'trigger' => 'schedule',
            'triggered_by' => 'system',
            'triggered_at' => now(),
        ]);

        $this->info("LED turned {$desiredAction} by schedule (on:{$onHour}:00 / off:{$offHour}:00).");

        return self::SUCCESS;
    }
}
