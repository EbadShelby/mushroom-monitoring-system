<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\JsonResponse;

class ActuatorController extends Controller
{
    public function __construct(private FirebaseService $firebase) {}

    /**
     * GET /api/actuator-commands
     *
     * ESP32 polls this endpoint to get the latest desired actuator states from
     * Firebase. Returns a simple JSON object the firmware can act on directly.
     *
     * Example response:
     * {
     *   "humidifier": "on",
     *   "fan": "off",
     *   "led": "off"
     * }
     */
    public function getPending(): JsonResponse
    {
        $actuators = $this->firebase->getActuators();

        // Ensure we always return all three keys with a safe default
        $defaults = ['humidifier' => 'off', 'fan' => 'off', 'led' => 'off'];
        $commands = array_merge($defaults, $actuators);

        return response()->json($commands);
    }
}
