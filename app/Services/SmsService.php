<?php

namespace App\Services;

use App\Models\AlertLog;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private string $apiKey;

    private string $senderName;

    private const ENDPOINT = 'https://api.semaphore.co/api/v4/messages';

    private const MUTE_SETTING_KEY = 'sms_muted_until';

    public function __construct()
    {
        $this->apiKey = (string) config('services.semaphore.api_key');
        $this->senderName = (string) config('services.semaphore.sender_name', 'CotSU-IoT');
    }

    /**
     * Whether SMS alerts are currently muted.
     */
    public function isMuted(): bool
    {
        $raw = (string) Setting::get(self::MUTE_SETTING_KEY, '');

        if ($raw === '') {
            return false;
        }

        if ($raw === 'indefinite') {
            return true;
        }

        try {
            return now()->lt(Carbon::parse($raw));
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Current SMS mute state for UI display.
     *
     * @return array{muted: bool, indefinite: bool, muted_until: string|null}
     */
    public function muteStatus(): array
    {
        $raw = (string) Setting::get(self::MUTE_SETTING_KEY, '');

        if ($raw === 'indefinite') {
            return [
                'muted' => true,
                'indefinite' => true,
                'muted_until' => null,
            ];
        }

        if ($raw !== '') {
            try {
                $until = Carbon::parse($raw);
                if (now()->lt($until)) {
                    return [
                        'muted' => true,
                        'indefinite' => false,
                        'muted_until' => $until->toIso8601String(),
                    ];
                }
            } catch (\Throwable) {
                // Treat invalid values as unmuted.
            }
        }

        return [
            'muted' => false,
            'indefinite' => false,
            'muted_until' => null,
        ];
    }

    /**
     * Mute SMS alerts for a number of minutes, or indefinitely if null.
     *
     * @return array{muted: bool, indefinite: bool, muted_until: string|null}
     */
    public function mute(?int $minutes = null): array
    {
        $value = $minutes === null
            ? 'indefinite'
            : now()->addMinutes($minutes)->toIso8601String();

        Setting::set(self::MUTE_SETTING_KEY, $value);

        return $this->muteStatus();
    }

    /**
     * Resume SMS alerts immediately.
     *
     * @return array{muted: bool, indefinite: bool, muted_until: string|null}
     */
    public function unmute(): array
    {
        Setting::set(self::MUTE_SETTING_KEY, '');

        return $this->muteStatus();
    }

    /**
     * Admin/faculty contact numbers merged with the "sms_recipients" setting,
     * normalized to 09XXXXXXXXX and de-duplicated.
     *
     * @return list<string>
     */
    private function recipientNumbers(): array
    {
        $userNumbers = User::whereIn('role', ['admin', 'faculty'])
            ->whereNotNull('contact_number')
            ->where('contact_number', '!=', '')
            ->pluck('contact_number')
            ->all();

        $settingNumbers = explode(',', (string) Setting::get('sms_recipients', ''));

        $numbers = array_map(
            PhilippineMobileNumber::normalize(...),
            [...$userNumbers, ...$settingNumbers],
        );

        return array_values(array_unique(array_filter($numbers)));
    }

    /**
     * Send an alert SMS to all faculty + admin users that have a contact number,
     * plus any numbers listed in the SMS recipients setting.
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

        if ($this->isMuted()) {
            Log::info('SMS alerts muted — skipped', compact('sensor'));

            return;
        }

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
        $recipients = $this->recipientNumbers();

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
