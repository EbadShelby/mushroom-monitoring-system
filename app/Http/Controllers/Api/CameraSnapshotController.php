<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CameraSnapshot;
use App\Models\GrowingCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CameraSnapshotController extends Controller
{
    public const CAPTURE_REQUEST_KEY = 'camera:capture_requested';

    public const MANUAL_CAPTURE_KEY = 'camera:manual_capture';

    /**
     * GET /api/camera-command
     *
     * The ESP32-CAM polls this endpoint. When a user pressed "Capture now" on
     * the dashboard, the pending request is consumed and `capture` is true.
     */
    public function command(Request $request): JsonResponse
    {
        if (! $this->hasValidToken($request)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        /** @var array{id: int, name: string}|null $pending */
        $pending = Cache::pull(self::CAPTURE_REQUEST_KEY);

        if ($pending !== null) {
            Cache::put(self::MANUAL_CAPTURE_KEY, $pending, now()->addMinutes(5));
        }

        return response()->json(['capture' => $pending !== null]);
    }

    /**
     * POST /api/camera-snapshot
     *
     * ESP32-CAM uploads a raw JPEG body (Content-Type: image/jpeg) every few hours
     * or when a manual capture was requested. The snapshot is attached to the
     * currently active growing cycle.
     */
    public function store(Request $request): JsonResponse
    {
        if (! $this->hasValidToken($request)) {
            return response()->json(['error' => 'Unauthorized.'], 401);
        }

        $image = $request->getContent();

        if ($image === '' || ! str_starts_with($image, "\xFF\xD8\xFF")) {
            return response()->json(['error' => 'Body must be a JPEG image.'], 422);
        }

        $cycle = GrowingCycle::query()->where('status', 'active')->latest('start_date')->first();

        $fileName = now()->format('Ymd_His').'_'.Str::lower(Str::random(6)).'.jpg';
        $path = 'snapshots/'.$fileName;

        if (! Storage::disk('public')->put($path, $image)) {
            return response()->json(['error' => 'File could not be saved.'], 500);
        }

        /** @var array{id: int, name: string}|null $manual */
        $manual = Cache::pull(self::MANUAL_CAPTURE_KEY);

        $snapshot = CameraSnapshot::create([
            'growing_cycle_id' => $cycle?->id,
            'file_path' => $path,
            'file_name' => $fileName,
            'flush_number' => 1,
            'captured_date' => now()->toDateString(),
            'notes' => $manual !== null ? "Manual capture by {$manual['name']}" : 'Auto-captured by ESP32-CAM',
            'uploaded_by' => $manual['id'] ?? null,
        ]);

        return response()->json(['success' => true, 'snapshot_id' => $snapshot->id], 201);
    }

    private function hasValidToken(Request $request): bool
    {
        $expectedToken = (string) config('services.camera.token');
        $providedToken = (string) $request->header('X-Camera-Token', '');

        return $expectedToken !== '' && hash_equals($expectedToken, $providedToken);
    }
}
