<?php

use App\Models\AlertLog;
use App\Models\GrowingCycle;
use App\Models\User;
use App\Services\FirebaseService;
use App\Services\SmsService;
use Illuminate\Support\Facades\Http;

test('authenticated user can view alert logs page', function () {
    $user = User::factory()->create(['role' => 'admin']);

    AlertLog::create([
        'sensor' => 'temperature',
        'value_at_alert' => 35.5,
        'threshold_exceeded' => 'above 32°C',
        'recipient_number' => '09123456789',
        'message' => 'Too hot',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $response = $this->actingAs($user)->get('/alerts');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('AlertLogsView')
        ->has('logs.data', 1)
        ->where('logs.data.0.sensor', 'temperature')
        ->has('chartData', 1)
    );
});

test('sensor data does not send environmental SMS alerts when no cycle is active', function () {
    User::factory()->create(['role' => 'admin', 'contact_number' => '09171234567']);
    config([
        'services.semaphore.api_key' => 'test-key',
        'services.device.token' => 'test-device-token',
    ]);
    Http::fake();

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('updateSensors')->once();
    $firebase->shouldReceive('getActuators')->andReturn(['humidifier' => 'off', 'led' => 'off']);
    $firebase->shouldReceive('setActuator')->zeroOrMoreTimes();
    $this->app->instance(FirebaseService::class, $firebase);

    $this->postJson('/api/sensor-data', [
        'temperature' => 38.0,
        'humidity' => 50.0,
    ], ['X-Device-Token' => 'test-device-token'])->assertCreated()
        ->assertJsonPath('cycle_id', null)
        ->assertJsonPath('alerts_triggered', 0);

    Http::assertNothingSent();
});

test('sensor data sends environmental SMS alerts when an active cycle exists', function () {
    User::factory()->create(['role' => 'admin', 'contact_number' => '09171234567']);
    config([
        'services.semaphore.api_key' => 'test-key',
        'services.device.token' => 'test-device-token',
    ]);
    Http::fake(['https://api.semaphore.co/*' => Http::response(['message_id' => 1], 200)]);

    $cycle = GrowingCycle::create([
        'name' => 'Cycle 1',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('updateSensors')->once();
    $firebase->shouldReceive('getActuators')->andReturn(['humidifier' => 'off', 'led' => 'off']);
    $firebase->shouldReceive('setActuator')->zeroOrMoreTimes();
    $this->app->instance(FirebaseService::class, $firebase);

    $this->postJson('/api/sensor-data', [
        'temperature' => 38.0,
        'humidity' => 85.0,
    ], ['X-Device-Token' => 'test-device-token'])->assertCreated()
        ->assertJsonPath('cycle_id', $cycle->id)
        ->assertJsonPath('alerts_triggered', 1);

    Http::assertSentCount(1);
    expect(AlertLog::first()?->growing_cycle_id)->toBe($cycle->id);
});

test('alert logs sensor filter matches co2 and light alerts', function () {
    $user = User::factory()->create(['role' => 'admin']);

    AlertLog::create([
        'sensor' => 'co2',
        'value_at_alert' => 1250,
        'threshold_exceeded' => 'above 1000 ppm',
        'recipient_number' => '09123456789',
        'message' => 'CO2 high',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    AlertLog::create([
        'sensor' => 'light',
        'value_at_alert' => 50,
        'threshold_exceeded' => 'below 200 lux',
        'recipient_number' => '09123456789',
        'message' => 'Light low',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    $this->actingAs($user)
        ->get('/alerts?sensor=co2')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.sensor', 'co2')
        );

    $this->actingAs($user)
        ->get('/alerts?sensor=light')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.sensor', 'light')
        );
});

test('alert frequency chart counts at most one alert per rolling 15-minute cooldown per sensor', function () {
    $user = User::factory()->create(['role' => 'admin']);
    $base = now()->startOfMinute();

    // Two recipients at 00:00 + retry at 00:10 -> all within 15 mins -> counts as 1
    foreach ([0, 0, 10] as $i => $min) {
        AlertLog::create([
            'sensor' => 'temperature',
            'value_at_alert' => 34.0,
            'threshold_exceeded' => 'above 32°C',
            'recipient_number' => "0912345678{$i}",
            'message' => 'Temp high',
            'status' => $i === 2 ? 'failed' : 'sent',
            'sent_at' => $base->copy()->addMinutes($min),
        ]);
    }

    // 15 mins after 00:00 -> counts as 2
    AlertLog::create([
        'sensor' => 'temperature',
        'value_at_alert' => 34.5,
        'threshold_exceeded' => 'above 32°C',
        'recipient_number' => '09123456780',
        'message' => 'Temp high again',
        'status' => 'sent',
        'sent_at' => $base->copy()->addMinutes(15),
    ]);

    $this->actingAs($user)
        ->get('/alerts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('chartData.0.sensor', 'temperature')
            ->where('chartData.0.count', 2)
        );
});

test('sms cooldown applies after failed attempts so outages do not spam alert logs', function () {
    User::factory()->create(['role' => 'admin', 'contact_number' => '09171234567']);
    config(['services.semaphore.api_key' => 'test-key']);
    Http::fake(['https://api.semaphore.co/*' => Http::response(['error' => 'failed'], 500)]);

    $service = new SmsService;
    $payload = [
        'sensor' => 'temperature',
        'value' => 35.0,
        'threshold' => 'above 32°C',
        'message' => 'Temp high',
    ];

    $service->sendAlert($payload, 15);
    $service->sendAlert($payload, 15);

    expect(AlertLog::count())->toBe(1);
    Http::assertSentCount(1);
});

test('alerts are linked to the active cycle and filterable by growing cycle', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $oldCycle = GrowingCycle::create([
        'name' => 'Batch 1',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(2)->toDateString(),
        'status' => 'completed',
    ]);

    $activeCycle = GrowingCycle::create([
        'name' => 'Batch 2',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    AlertLog::create([
        'growing_cycle_id' => $oldCycle->id,
        'sensor' => 'temperature',
        'value_at_alert' => 35.0,
        'threshold_exceeded' => 'above 32°C',
        'recipient_number' => '09123456789',
        'message' => 'Old batch alert',
        'status' => 'sent',
        'sent_at' => now()->subDays(5),
    ]);

    AlertLog::create([
        'growing_cycle_id' => $activeCycle->id,
        'sensor' => 'humidity',
        'value_at_alert' => 70.0,
        'threshold_exceeded' => 'below 75%',
        'recipient_number' => '09123456789',
        'message' => 'Active batch alert',
        'status' => 'sent',
        'sent_at' => now(),
    ]);

    // Defaults to active cycle when cycle_id is omitted
    $this->actingAs($user)
        ->get('/alerts')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.cycle_id', (string) $activeCycle->id)
            ->has('logs.data', 1)
            ->where('logs.data.0.sensor', 'humidity')
            ->has('chartData', 1)
            ->where('chartData.0.sensor', 'humidity')
        );

    // Explicitly selecting All Cycles returns both
    $this->actingAs($user)
        ->get('/alerts?cycle_id=all')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.cycle_id', 'all')
            ->has('logs.data', 2)
            ->has('chartData', 2)
        );

    // Selecting completed cycle returns only its alerts
    $this->actingAs($user)
        ->get("/alerts?cycle_id={$oldCycle->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.cycle_id', (string) $oldCycle->id)
            ->has('logs.data', 1)
            ->where('logs.data.0.sensor', 'temperature')
        );
});
