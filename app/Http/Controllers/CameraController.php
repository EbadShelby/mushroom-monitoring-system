<?php

namespace App\Http\Controllers;

use App\Models\CameraSnapshot;
use App\Models\GrowingCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CameraController extends Controller
{
    public function index(Request $request): Response
    {
        $cycleId = $request->query('cycle_id');
        $date = $request->query('date');

        $cycles = GrowingCycle::orderByRaw("FIELD(status, 'active', 'completed', 'cancelled')")
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'status']);

        $snapshots = CameraSnapshot::query()
            ->with('growingCycle:id,name')
            ->with('uploader:id,name')
            ->when($cycleId, fn ($q) => $q->where('growing_cycle_id', $cycleId))
            ->when($date, fn ($q) => $q->whereDate('captured_date', $date))
            ->orderByDesc('captured_date')
            ->orderByDesc('id')
            ->get(['id', 'growing_cycle_id', 'file_path', 'file_name', 'flush_number', 'captured_date', 'notes', 'uploaded_by']);

        return Inertia::render('camera/Index', [
            'snapshots' => $snapshots,
            'cycles' => $cycles,
            'filters' => ['cycle_id' => $cycleId ? (int) $cycleId : null, 'date' => $date],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'growing_cycle_id' => ['required', 'integer', 'exists:growing_cycles,id'],
            'flush_number' => ['required', 'integer', 'min:1'],
            'captured_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        $path = $request->file('photo')->store('snapshots', 'public');

        if ($path === false) {
            return response()->json(['error' => 'File upload failed.'], 500);
        }

        $fileName = basename($path);

        $snapshot = CameraSnapshot::create([
            'growing_cycle_id' => $data['growing_cycle_id'],
            'flush_number' => $data['flush_number'],
            'captured_date' => $data['captured_date'],
            'notes' => $data['notes'] ?? null,
            'file_path' => $path,
            'file_name' => $fileName,
            'uploaded_by' => auth()->id(),
        ]);

        return response()->json(['success' => true, 'snapshot' => $snapshot]);
    }

    public function destroy(CameraSnapshot $cameraSnapshot): JsonResponse
    {
        Storage::disk('public')->delete($cameraSnapshot->file_path);
        $cameraSnapshot->delete();

        return response()->json(['success' => true]);
    }
}
