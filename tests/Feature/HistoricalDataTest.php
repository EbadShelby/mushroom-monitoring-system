<?php

use App\Models\SensorReading;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests are unauthorized when requesting historical chart data', function () {
    $this->getJson(route('historical.chart'))
        ->assertUnauthorized();
});

test('historical chart defaults to 5-minute buckets over the last 6 hours and ignores null values in averages', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 12:00:00'));

    try {
        $user = User::factory()->create();
        $bucketStart = now()->subHours(2)->startOfHour();

        // Older than 6 hours — excluded from default 5m rolling window
        SensorReading::create([
            'temperature' => 20.0,
            'humidity' => 70.0,
            'co2_raw' => 400,
            'light_level' => 100.0,
            'soil_moisture' => 50,
            'recorded_at' => now()->subHours(7),
        ]);

        SensorReading::create([
            'temperature' => 28.0,
            'humidity' => 84.0,
            'co2_raw' => 600,
            'light_level' => 300.0,
            'soil_moisture' => 65,
            'recorded_at' => $bucketStart->copy()->addMinute(),
        ]);

        // Second reading in same 5-minute bucket with null co2_raw
        SensorReading::create([
            'temperature' => 30.0,
            'humidity' => 86.0,
            'co2_raw' => null,
            'light_level' => 500.0,
            'soil_moisture' => 75,
            'recorded_at' => $bucketStart->copy()->addMinutes(3),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('historical.chart'));

        $response->assertSuccessful()
            ->assertJsonCount(1)
            ->assertJsonPath('0.temperature', 29)
            ->assertJsonPath('0.humidity', 85)
            ->assertJsonPath('0.co2_raw', 600)
            ->assertJsonPath('0.light_level', 400)
            ->assertJsonPath('0.soil_moisture', 70);
    } finally {
        Carbon::setTestNow();
    }
});

test('historical chart returns raw readings for 1m interval over the last hour', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 12:00:00'));

    try {
        $user = User::factory()->create();

        // Older than 1 hour — excluded
        SensorReading::create([
            'temperature' => 25.0,
            'humidity' => 80.0,
            'recorded_at' => now()->subMinutes(75),
        ]);

        SensorReading::create([
            'temperature' => 28.2,
            'humidity' => 82.0,
            'recorded_at' => now()->subMinutes(10),
        ]);

        SensorReading::create([
            'temperature' => 28.6,
            'humidity' => 83.5,
            'recorded_at' => now()->subMinutes(9),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('historical.chart', ['interval' => '1m']));

        $response->assertSuccessful()
            ->assertJsonCount(2)
            ->assertJsonPath('0.temperature', 28.2)
            ->assertJsonPath('1.temperature', 28.6);
    } finally {
        Carbon::setTestNow();
    }
});

test('historical chart supports 15m, 1h, and 1d rolling windows', function (string $interval, int $insideMinutesAgo, int $outsideMinutesAgo) {
    Carbon::setTestNow(Carbon::parse('2026-11-24 12:00:00'));

    try {
        $user = User::factory()->create();

        SensorReading::create([
            'temperature' => 21.0,
            'humidity' => 70.0,
            'recorded_at' => now()->subMinutes($outsideMinutesAgo),
        ]);

        SensorReading::create([
            'temperature' => 29.5,
            'humidity' => 88.0,
            'recorded_at' => now()->subMinutes($insideMinutesAgo),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('historical.chart', ['interval' => $interval]));

        $response->assertSuccessful()
            ->assertJsonCount(1)
            ->assertJsonPath('0.temperature', 29.5)
            ->assertJsonPath('0.humidity', 88);
    } finally {
        Carbon::setTestNow();
    }
})->with([
    '15m (last 24h)' => ['15m', 12 * 60, 25 * 60],
    '1h (last 7d)' => ['1h', 3 * 24 * 60, 8 * 24 * 60],
    '1d (last 30d)' => ['1d', 15 * 24 * 60, 31 * 24 * 60],
]);

test('historical chart respects custom date range and buckets readings by chosen interval', function () {
    Carbon::setTestNow(Carbon::parse('2026-11-24 12:00:00'));

    try {
        $user = User::factory()->create();

        // Outside custom range (before)
        SensorReading::create([
            'temperature' => 22.0,
            'humidity' => 75.0,
            'recorded_at' => '2026-10-01 10:00:00',
        ]);

        // Inside custom range — two readings on same day
        SensorReading::create([
            'temperature' => 26.0,
            'humidity' => 80.0,
            'recorded_at' => '2026-10-10 08:00:00',
        ]);

        SensorReading::create([
            'temperature' => 30.0,
            'humidity' => 90.0,
            'recorded_at' => '2026-10-10 18:00:00',
        ]);

        // Inside custom range — next day
        SensorReading::create([
            'temperature' => 27.5,
            'humidity' => 85.0,
            'recorded_at' => '2026-10-11 09:00:00',
        ]);

        // Outside custom range (after)
        SensorReading::create([
            'temperature' => 31.0,
            'humidity' => 92.0,
            'recorded_at' => '2026-10-15 12:00:00',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('historical.chart', [
                'interval' => '1d',
                'from' => '2026-10-10',
                'to' => '2026-10-11',
                'sensors' => ['temperature', 'humidity'],
            ]));

        $response->assertSuccessful()
            ->assertJsonCount(2)
            ->assertJsonPath('0.temperature', 28)
            ->assertJsonPath('0.humidity', 85)
            ->assertJsonPath('1.temperature', 27.5)
            ->assertJsonPath('1.humidity', 85);

        expect($response->json('0'))->not->toHaveKey('co2_raw');
    } finally {
        Carbon::setTestNow();
    }
});

test('historical chart rejects invalid intervals and sensors', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('historical.chart', ['interval' => '99m']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('interval');

    $this->actingAs($user)
        ->getJson(route('historical.chart', ['sensors' => ['invalid_sensor']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sensors.0');
});
