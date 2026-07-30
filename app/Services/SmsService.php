<?php

namespace App\Services;

use App\Models\AlertLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $apiKey;

    private string $senderName;

    private const ENDPOINT = 'https://api.semaphore.co/api/v4/messages';

    public function __construct()
    {
        $this->apiKey = (string) config('services.semaphore.api_key');
        $this->senderName = (string) config('services.semaphore.sender_name', 'CotSU-IoT');
    }

    /**
     * Send an alert SMS to all faculty + admin users that have a contact number.
     *
     * Logs each send attempt to alert_logs, respecting a per-sensor cooldown to
     * avoid SMS flooding (one alert per sensor per $cooldownMinutes window).
     *
     * @param  array<string, mixed>  $payload  { sensor, value, threshold, message }
     */
    public function sendAlert(array $payload, int $cooldownMinutes = 15): void
    {
        $sensor = $payload['sensor'];
        $value = $payload['value'];
        $threshold = $payload['threshold'];
        $message = $payload['message'];

        if (! $this->apiKey) {
            Log::warning('Semaphore API key not configured — SMS skipped', compact('sensor'));

            return;
        }

        // ── Cooldown check: skip if a recent alert for this sensor exists ──
        $recentAlert = AlertLog::where('sensor', $sensor)
            ->where('status', 'sent')
            ->where('sent_at', '>=', now()->subMinutes($cooldownMinutes))
            ->exists();

        if ($recentAlert) {
            Log::info('SMS cooldown active — skipped', compact('sensor'));

            return;
        }

        // ── Gather recipients ──────────────────────────────────────────────
        $recipients = User::whereIn('role', ['admin', 'faculty'])
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->pluck('contact_number')
            ->toArray();

        if (empty($recipients)) {
            Log::warning('No SMS recipients found — skipped', compact('sensor'));

            return;
        }

        // ── Send to each recipient ─────────────────────────────────────────
        foreach ($recipients as $number) {
            $status = 'failed';

            try {
                $response = Http::timeout(10)->post(self::ENDPOINT, [
                    'apikey' => $this->apiKey,
                    'number' => $number,
                    'message' => $message,
                    'sendername' => $this->senderName,
                ]);

                $status = $response->successful() ? 'sent' : 'failed';

                if (! $response->successful()) {
                    Log::warning('Semaphore SMS failed', [
                        'number' => $number,
                        'status' => $response->status(),
                        'body' => $response->body(),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('SMS exception', ['number' => $number, 'error' => $e->getMessage()]);
            }

            AlertLog::create([
                'sensor' => $sensor,
                'value_at_alert' => $value,
                'threshold_exceeded' => $threshold,
                'recipient_number' => $number,
                'message' => $message,
                'status' => $status,
                'sent_at' => now(),
            ]);
        }
    }
}
