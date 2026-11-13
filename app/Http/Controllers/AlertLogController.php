<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlertLogController extends Controller
{
    public function __construct(private SmsService $sms) {}

    public function index(Request $request): Response
    {
        $sensor = $request->query('sensor');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = AlertLog::query()
            ->when($sensor, fn ($q) => $q->where('sensor', $sensor))
            ->when($from, fn ($q) => $q->where('sent_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('sent_at', '<=', $to.' 23:59:59'));

        return Inertia::render('AlertLogsView', [
            'logs' => $query->orderByDesc('sent_at')->paginate(20)->withQueryString(),
            'chartData' => AlertLog::query()
                ->selectRaw('sensor, COUNT(*) as count')
                ->groupBy('sensor')
                ->orderByDesc('count')
                ->get()
                ->map(fn (AlertLog $row) => ['sensor' => $row->sensor, 'count' => (int) $row->getAttribute('count')]),
            'filters' => [
                'sensor' => $sensor,
                'from' => $from,
                'to' => $to,
            ],
            'smsMute' => $this->sms->muteStatus(),
        ]);
    }

    public function mute(Request $request): JsonResponse
    {
        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
        ]);

        $minutes = isset($data['minutes']) ? (int) $data['minutes'] : null;

        return response()->json([
            'success' => true,
            'smsMute' => $this->sms->mute($minutes),
        ]);
    }

    public function unmute(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'smsMute' => $this->sms->unmute(),
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        $data = AlertLog::query()
            ->when($request->filled('sensor'), fn ($q) => $q->where('sensor', $request->input('sensor')))
            ->when($request->filled('from'), fn ($q) => $q->where('sent_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('sent_at', '<=', $request->input('to').' 23:59:59'))
            ->selectRaw('sensor, COUNT(*) as count')
            ->groupBy('sensor')
            ->orderByDesc('count')
            ->get();

        return response()->json($data);
    }
}
