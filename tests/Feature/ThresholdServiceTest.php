<?php

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Services\FirebaseService;
use App\Services\ThresholdService;

beforeEach(function () {
    $this->firebase = Mockery::mock(FirebaseService::class);
});

test('low humidity turns on humidifier when rested', function () {
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
        'humidifier' => 'off',
    ]);

    $service = new ThresholdService($this->firebase);
    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'growing_stage' => 'fruiting',
    ]);

    $result = $service->evaluate([
        'humidity' => 75.0, // below 85% fruiting min
        'co2_raw' => 1200,   // above 1000 ppm, but below 2000 ppm emergency
        'temperature' => 26.0,
    ], $cycle);

    expect($result['commands']['humidifier'])->toBe('on');
    expect($result['commands'])->not->toHaveKey('fan');
});

test('humidifier continuous runtime limit shuts off after 15 minutes', function () {
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
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

    $service = new ThresholdService($this->firebase);
    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'growing_stage' => 'fruiting',
    ]);

    $result = $service->evaluate([
        'humidity' => 72.0,
        'co2_raw' => 800,
        'temperature' => 26.0,
    ], $cycle);

    expect($result['commands']['humidifier'])->toBe('off');

    $alerts = collect($result['alerts']);
    expect($alerts->contains(fn ($a) => str_contains($a['message'], 'Humidifier ran continuously for 15 mins')))->toBeTrue();
});

test('high temperature and CO2 only raise alerts without actuator commands', function () {
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
        'humidifier' => 'off',
    ]);

    $service = new ThresholdService($this->firebase);
    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'growing_stage' => 'fruiting',
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
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
        'humidifier' => 'on',
    ]);

    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'on',
        'trigger' => 'automatic',
        'triggered_by' => 'system',
        'triggered_at' => now()->subMinutes(6),
    ]);

    $service = new ThresholdService($this->firebase);
    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'growing_stage' => 'fruiting',
    ]);

    $result = $service->evaluate(['humidity' => 70.0, 'temperature' => 30.0], $cycle);

    expect($result['commands']['humidifier'])->toBeNull();
    expect(collect($result['alerts'])->contains(fn ($a) => str_contains($a['message'], 'after 5 mins of misting')))->toBeTrue();
});
