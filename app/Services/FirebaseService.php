<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    private string $baseUrl;

    private ?string $serviceAccountPath;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.firebase.database_url'), '/');
        $this->serviceAccountPath = config('services.firebase.service_account_path')
            ? base_path((string) config('services.firebase.service_account_path'))
            : null;
    }

    /**
     * Push the latest sensor readings to Firebase (merges fields, does not delete others).
     *
     * @param  array<string, mixed>  $data
     */
    public function updateSensors(array $data): void
    {
        $this->patch('/sensors', $data);
    }

    /**
     * Set a single actuator state ("on" or "off") in Firebase.
     */
    public function setActuator(string $actuator, string $state): void
    {
        $this->put("/actuators/{$actuator}", $state);
    }

    /**
     * Read the current actuator commands from Firebase.
     *
     * @return array<string, string>
     */
    public function getActuators(): array
    {
        $response = $this->get('/actuators');

        if ($response === null) {
            return [];
        }

        return is_array($response) ? $response : [];
    }

    // ─── OAuth 2.0 token (service account) ───────────────────────────────────

    /**
     * Returns a short-lived Bearer token for the Firebase Realtime Database scope.
     * Cached for 55 minutes (tokens expire in 60 min).
     */
    private function accessToken(): ?string
    {
        if (! $this->serviceAccountPath || ! file_exists($this->serviceAccountPath)) {
            Log::warning('Firebase service account JSON not found — unauthenticated request will be sent', [
                'path' => $this->serviceAccountPath,
            ]);

            return null;
        }

        return Cache::remember('firebase_access_token', now()->addMinutes(55), function () {
            $fileContents = file_get_contents($this->serviceAccountPath);

            if ($fileContents === false) {
                Log::error('Firebase service account JSON could not be read');

                return null;
            }

            $sa = json_decode($fileContents, true);

            $now = time();
            $payload = [
                'iss' => $sa['client_email'],
                'sub' => $sa['client_email'],
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
                'scope' => 'https://www.googleapis.com/auth/firebase.database https://www.googleapis.com/auth/userinfo.email',
            ];

            $headerJson = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
            $payloadJson = json_encode($payload);

            if ($headerJson === false || $payloadJson === false) {
                Log::error('Firebase JWT JSON encoding failed');

                return null;
            }

            $header = $this->base64url($headerJson);
            $body = $this->base64url($payloadJson);
            $signingInput = "{$header}.{$body}";

            $privateKey = openssl_pkey_get_private($sa['private_key']);

            if ($privateKey === false) {
                Log::error('Firebase private key could not be loaded');

                return null;
            }

            openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
            $jwt = "{$signingInput}.".$this->base64url($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                Log::error('Firebase OAuth token exchange failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('access_token');
        });
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    // ─── HTTP helpers ─────────────────────────────────────────────────────────

    private function url(string $path): string
    {
        return "{$this->baseUrl}{$path}.json";
    }

    /** @return array<string, string> */
    private function headers(): array
    {
        $token = $this->accessToken();

        return array_filter([
            'Content-Type' => 'application/json',
            'Authorization' => $token ? "Bearer {$token}" : null,
        ]);
    }

    /**
     * PATCH — merges fields without deleting existing keys.
     *
     * @param  array<string, mixed>  $data
     */
    private function patch(string $path, array $data): void
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders($this->headers())
                ->patch($this->url($path), $data);

            if (! $response->successful()) {
                Log::warning('Firebase PATCH failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Firebase PATCH exception', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    /**
     * PUT — overwrites the node at the given path.
     */
    private function put(string $path, mixed $data): void
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders($this->headers())
                ->put($this->url($path), $data);

            if (! $response->successful()) {
                Log::warning('Firebase PUT failed', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Firebase PUT exception', ['path' => $path, 'error' => $e->getMessage()]);
        }
    }

    /**
     * GET — reads data from Firebase.
     */
    private function get(string $path): mixed
    {
        try {
            $response = Http::timeout(5)
                ->withHeaders($this->headers())
                ->get($this->url($path));

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning('Firebase GET failed', [
                'path' => $path,
                'status' => $response->status(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Firebase GET exception', ['path' => $path, 'error' => $e->getMessage()]);
        }

        return null;
    }
}
