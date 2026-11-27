<?php

namespace App\Http\Controllers;

use App\Models\AlertLog;
use App\Models\GrowingCycle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AlertLogController extends Controller
{
    private const CHART_COOLDOWN_SECONDS = 900;

    public function index(Request $request): Response
    {
        $cycles = GrowingCycle::query()
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get(['id', 'name', 'status']);

        $activeCycle = $cycles->firstWhere('status', 'active');

        $rawCycleId = $request->query('cycle_id');
        if ($rawCycleId === null) {
            $cycleId = $activeCycle?->id;
            $selectedCycleFilter = $activeCycle ? (string) $activeCycle->id : 'all';
        } elseif ($rawCycleId === 'all' || $rawCycleId === '') {
            $cycleId = null;
            $selectedCycleFilter = 'all';
        } else {
            $cycleId = (int) $rawCycleId;
            $selectedCycleFilter = (string) $cycleId;
        }

        $sensor = $request->query('sensor');
        $from = $request->query('from');
        $to = $request->query('to');

        $query = $this->filteredQuery($cycleId, $sensor, $from, $to);

        return Inertia::render('AlertLogsView', [
            'logs' => (clone $query)
                ->with('growingCycle:id,name')
                ->orderByDesc('sent_at')
                ->paginate(20)
                ->withQueryString(),
            'chartData' => $this->buildChartData($query),
            'cycles' => $cycles,
            'filters' => [
                'cycle_id' => $selectedCycleFilter,
                'sensor' => $sensor,
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function chart(Request $request): JsonResponse
    {
        $rawCycleId = $request->input('cycle_id');
        $cycleId = ($rawCycleId !== null && $rawCycleId !== '' && $rawCycleId !== 'all')
            ? (int) $rawCycleId
            : null;

        $query = $this->filteredQuery(
            $cycleId,
            $request->filled('sensor') ? (string) $request->input('sensor') : null,
            $request->filled('from') ? (string) $request->input('from') : null,
            $request->filled('to') ? (string) $request->input('to') : null,
        );

        return response()->json($this->buildChartData($query));
    }

    /**
     * @return Builder<AlertLog>
     */
    private function filteredQuery(?int $cycleId, ?string $sensor, ?string $from, ?string $to): Builder
    {
        return AlertLog::query()
            ->when($cycleId !== null, fn ($q) => $q->where('growing_cycle_id', $cycleId))
            ->when($sensor, fn ($q) => $q->whereIn('sensor', $this->sensorAliases($sensor)))
            ->when($from, fn ($q) => $q->where('sent_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('sent_at', '<=', $to.' 23:59:59'));
    }

    /**
     * Count alerts per sensor using a rolling 15-minute cooldown so multi-recipient
     * rows and retry bursts count as a single alert event.
     *
     * @param  Builder<AlertLog>  $query
     * @return list<array{sensor: string, count: int}>
     */
    private function buildChartData($query): array
    {
        $rows = (clone $query)
            ->orderBy('sent_at')
            ->orderBy('id')
            ->get(['sensor', 'sent_at']);

        /** @var array<string, int> $counts */
        $counts = [];
        /** @var array<string, int> $lastCountedTs */
        $lastCountedTs = [];

        foreach ($rows as $row) {
            $sensor = $this->canonicalSensor($row->sensor);
            $ts = Carbon::parse($row->sent_at)->getTimestamp();

            if (! isset($lastCountedTs[$sensor]) || ($ts - $lastCountedTs[$sensor]) >= self::CHART_COOLDOWN_SECONDS) {
                $counts[$sensor] = ($counts[$sensor] ?? 0) + 1;
                $lastCountedTs[$sensor] = $ts;
            }
        }

        arsort($counts);

        $data = [];
        foreach ($counts as $sensor => $count) {
            $data[] = ['sensor' => $sensor, 'count' => $count];
        }

        return $data;
    }

    private function canonicalSensor(string $sensor): string
    {
        return match ($sensor) {
            'co2_raw' => 'co2',
            'light_level' => 'light',
            default => $sensor,
        };
    }

    /**
     * @return list<string>
     */
    private function sensorAliases(string $sensor): array
    {
        return match ($sensor) {
            'co2', 'co2_raw' => ['co2', 'co2_raw'],
            'light', 'light_level' => ['light', 'light_level'],
            default => [$sensor],
        };
    }
}
