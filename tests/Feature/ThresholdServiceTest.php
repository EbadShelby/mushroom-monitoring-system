<?php

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use App\Services\FirebaseService;
use App\Services\SmsService;
use App\Services\ThresholdService;

/**
 * @param  array<string, string>  $actuators
 */
function thresholdServiceWithActuators(array $actuators): ThresholdService
{
    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->once()->andReturn($actuators);

    return new ThresholdService($firebase);
}

test('low humidity turns on humidifier when rested', function () {
    $service = thresholdServiceWithActuators([
        'humidifier' => 'off',
    ]);
    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $result = $service->evaluate([
        'humidity' => 70.0, // below 75% min
        'co2_raw' => 1200,   // above 1000 ppm, but below 2000 ppm emergency
        'temperature' => 26.0,
    ], $cycle);

    expect($result['commands']['humidifier'])->toBe('on');
    expect($result['commands'])->not->toHaveKey('fan');
});

test('humidifier continuous runtime limit shuts off after 15 minutes', function () {
    $service = thresholdServiceWithActuators([
        'humidifier' => 'on',
    ]);

    // Create an actuator log indicating humidifier has been running for 16 minutes
    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'on',
        'trigger' => 'automatic',
        'triggered_by' => 'system',
        'triggered_at' => now()->subMinutes(16),
    ]);

    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $result = $service->evaluate([
        'humidity' => 72.0,
        'co2_raw' => 800,
        'temperature' => 26.0,
    ], $cycle);

    expect($result['commands']['humidifier'])->toBe('off');

    $alerts = collect($result['alerts']);
    expect($alerts->contains(fn ($a) => str_contains($a['message'], 'Humidifier ran for 15 mins')))->toBeTrue();
});

test('high temperature and CO2 only raise alerts without actuator commands', function () {
    $service = thresholdServiceWithActuators([
        'humidifier' => 'off',
    ]);
    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $result = $service->evaluate([
        'humidity' => 90.0,
        'co2_raw' => 1300,
        'temperature' => 34.0,
    ], $cycle);

    expect($result['commands'])->toBe(['humidifier' => null]);

    $sensors = collect($result['alerts'])->pluck('sensor');
    expect($sensors->contains('co2'))->toBeTrue();
    expect($sensors->contains('temperature'))->toBeTrue();
});

test('humidifier stall alert fires when humidity stays low after 5 minutes of misting', function () {
    $service = thresholdServiceWithActuators([
        'humidifier' => 'on',
    ]);

    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'on',
        'trigger' => 'automatic',
        'triggered_by' => 'system',
        'triggered_at' => now()->subMinutes(6),
    ]);

    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $result = $service->evaluate(['humidity' => 70.0, 'temperature' => 30.0], $cycle);

    expect($result['commands']['humidifier'])->toBeNull();
    expect(collect($result['alerts'])->contains(fn ($a) => str_contains($a['message'], 'after 5 mins of misting')))->toBeTrue();
});

dataset('humidity control band (75-95)', [
    'off, 78 turns on early, no SMS' => ['off', 78.0, 'on', false],
    'off, 79 inside band does nothing' => ['off', 79.0, null, false],
    'off, 74 turns on with SMS' => ['off', 74.0, 'on', true],
    'on, 89 keeps running' => ['on', 89.0, null, false],
    'on, 90 turns off before 95' => ['on', 90.0, 'off', false],
]);

test('humidifier uses an inner control band so humidity stays in range', function (string $state, float $humidity, ?string $command, bool $sendsSms) {
    $service = thresholdServiceWithActuators(['humidifier' => $state]);

    if ($state === 'on') {
        ActuatorLog::create([
            'actuator' => 'humidifier',
            'action' => 'on',
            'trigger' => 'automatic',
            'triggered_by' => 'system',
            'triggered_at' => now()->subMinute(),
        ]);
    }

    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $result = $service->evaluate(['humidity' => $humidity], $cycle);

    expect($result['commands']['humidifier'])->toBe($command)
        ->and(collect($result['alerts'])->contains('sensor', 'humidity'))->toBe($sendsSms);
})->with('humidity control band (75-95)');

test('low light triggers alert during LED daytime schedule', function () {
    $this->travelTo(now()->setTime(12, 0));

    $result = app(ThresholdService::class)->evaluate(['light_level' => 50]);

    expect(collect($result['alerts'])->pluck('sensor'))->toContain('light');
});

test('low light does not trigger alert during LED nighttime schedule', function () {
    $this->travelTo(now()->setTime(20, 0));

    $result = app(ThresholdService::class)->evaluate(['light_level' => 0]);

    expect(collect($result['alerts'])->pluck('sensor'))->not->toContain('light');
});

test('high light still triggers alert during LED nighttime schedule', function () {
    $this->travelTo(now()->setTime(20, 0));

    $result = app(ThresholdService::class)->evaluate(['light_level' => 1200]);

    expect(collect($result['alerts'])->pluck('sensor'))->toContain('light');
});

test('low light is suppressed during the 2-minute LED turn-on grace period at 6am', function () {
    $this->travelTo(now()->setTime(6, 0, 30));

    $duringGrace = app(ThresholdService::class)->evaluate(['light_level' => 50]);
    expect(collect($duringGrace['alerts'])->pluck('sensor'))->not->toContain('light');

    $this->travelTo(now()->setTime(6, 3, 0));

    $afterGrace = app(ThresholdService::class)->evaluate(['light_level' => 50]);
    expect(collect($afterGrace['alerts'])->pluck('sensor'))->toContain('light');
});

test('soil alert is suppressed on transient drops to 0 percent', function () {
    $now = now();

    SensorReading::create(['soil_moisture' => 65, 'recorded_at' => $now->copy()->subSeconds(20)]);
    SensorReading::create(['soil_moisture' => 64, 'recorded_at' => $now->copy()->subSeconds(10)]);

    $service = thresholdServiceWithActuators(['humidifier' => 'off']);
    $result = $service->evaluate(['soil_moisture' => 0]);

    expect(collect($result['alerts'])->pluck('sensor'))->not->toContain('soil_moisture');
});

test('soil alert is still suppressed after only two consecutive low readings', function () {
    $now = now();

    SensorReading::create(['soil_moisture' => 65, 'recorded_at' => $now->copy()->subSeconds(20)]);
    SensorReading::create(['soil_moisture' => 0, 'recorded_at' => $now->copy()->subSeconds(10)]);

    $service = thresholdServiceWithActuators(['humidifier' => 'off']);
    $result = $service->evaluate(['soil_moisture' => 0]);

    expect(collect($result['alerts'])->pluck('sensor'))->not->toContain('soil_moisture');
});

test('soil critical alert fires after three consecutive critical readings', function () {
    $now = now();

    SensorReading::create(['soil_moisture' => 38, 'recorded_at' => $now->copy()->subSeconds(20)]);
    SensorReading::create(['soil_moisture' => 35, 'recorded_at' => $now->copy()->subSeconds(10)]);

    $service = thresholdServiceWithActuators(['humidifier' => 'off']);
    $result = $service->evaluate(['soil_moisture' => 32]);

    $soilAlert = collect($result['alerts'])->firstWhere('sensor', 'soil_moisture');
    expect($soilAlert)->not->toBeNull()
        ->and($soilAlert['threshold'])->toContain('below 40% (CRITICAL)')
        ->and($soilAlert['value'])->toBe(32);
});

test('soil warning alert fires after three consecutive dry readings without false critical escalation', function () {
    $now = now();

    SensorReading::create(['soil_moisture' => 58, 'recorded_at' => $now->copy()->subSeconds(20)]);
    SensorReading::create(['soil_moisture' => 55, 'recorded_at' => $now->copy()->subSeconds(10)]);

    $service = thresholdServiceWithActuators(['humidifier' => 'off']);
    $result = $service->evaluate(['soil_moisture' => 0]);

    $soilAlert = collect($result['alerts'])->firstWhere('sensor', 'soil_moisture');
    expect($soilAlert)->not->toBeNull()
        ->and($soilAlert['threshold'])->toContain('below 60% (DRY)')
        ->and($soilAlert['threshold'])->not->toContain('CRITICAL');
});

test('stale low soil readings older than five minutes do not trigger a debounced alert', function () {
    $now = now();

    SensorReading::create(['soil_moisture' => 10, 'recorded_at' => $now->copy()->subMinutes(10)]);
    SensorReading::create(['soil_moisture' => 10, 'recorded_at' => $now->copy()->subMinutes(6)]);

    $service = thresholdServiceWithActuators(['humidifier' => 'off']);
    $result = $service->evaluate(['soil_moisture' => 0]);

    expect(collect($result['alerts'])->pluck('sensor'))->not->toContain('soil_moisture');
});

test('sensor-data endpoint debounces transient soil drops and alerts on third consecutive low reading', function () {
    config(['services.device.token' => 'secret-device-token']);

    GrowingCycle::create([
        'name' => 'Soil Debounce Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('updateSensors')->times(4);
    $firebase->shouldReceive('getActuators')->times(4)->andReturn(['humidifier' => 'off']);
    $this->app->instance(FirebaseService::class, $firebase);

    $sms = Mockery::mock(SmsService::class);
    $sms->shouldReceive('sendAlert')
        ->once()
        ->with(Mockery::on(fn (array $payload): bool => $payload['sensor'] === 'soil_moisture' && $payload['value'] === 32));
    $this->app->instance(SmsService::class, $sms);

    $headers = ['X-Device-Token' => 'secret-device-token'];
    $basePayload = [
        'temperature' => 28.0,
        'humidity' => 85.0,
        'co2_raw' => 600,
        'light_level' => 300.0,
    ];

    // 1st low reading -> no alert
    $this->postJson('/api/sensor-data', [...$basePayload, 'soil_moisture' => 0, 'soil_status' => 'critical'], $headers)
        ->assertCreated()
        ->assertJsonPath('alerts_triggered', 0);

    // Recovers to normal -> resets streak
    $this->postJson('/api/sensor-data', [...$basePayload, 'soil_moisture' => 65, 'soil_status' => 'moist'], $headers)
        ->assertCreated()
        ->assertJsonPath('alerts_triggered', 0);

    // 2nd reading after recovery (only 1st low) -> no alert
    $this->postJson('/api/sensor-data', [...$basePayload, 'soil_moisture' => 35, 'soil_status' => 'critical'], $headers)
        ->assertCreated()
        ->assertJsonPath('alerts_triggered', 0);

    // Seed one more low reading so the next POST is the 3rd consecutive critical reading
    SensorReading::create(['soil_moisture' => 34, 'recorded_at' => now()]);

    // 3rd consecutive critical reading -> fires 1 SMS alert
    $this->postJson('/api/sensor-data', [...$basePayload, 'soil_moisture' => 32, 'soil_status' => 'critical'], $headers)
        ->assertCreated()
        ->assertJsonPath('alerts_triggered', 1);
});
