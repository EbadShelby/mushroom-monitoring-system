<?php

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Services\FirebaseService;
use App\Services\ThresholdService;

beforeEach(function () {
    $this->firebase = Mockery::mock(FirebaseService::class);
});

test('low humidity turns on humidifier when rested and fan is blocked by interlock', function () {
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
        'humidifier' => 'off',
        'fan' => 'off',
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
    expect($result['commands']['fan'])->toBeNull(); // blocked by interlock
});

test('emergency CO2 overrides humidifier interlock and forces ventilation fans on', function () {
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
        'humidifier' => 'on',
        'fan' => 'off',
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
        'humidity' => 70.0,
        'co2_raw' => 2200, // exceeds 2000 ppm emergency ceiling
        'temperature' => 26.0,
    ], $cycle);

    // Humidifier is forced off to avoid wasting mist, fan is forced on
    expect($result['commands']['humidifier'])->toBe('off');
    expect($result['commands']['fan'])->toBe('on');

    $alerts = collect($result['alerts']);
    expect($alerts->contains(fn ($a) => str_contains($a['message'], 'CRITICAL CO₂')))->toBeTrue();
});

test('emergency heat overrides humidifier interlock and forces ventilation fans on', function () {
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
        'humidifier' => 'on',
        'fan' => 'off',
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
        'humidity' => 70.0,
        'co2_raw' => 800,
        'temperature' => 32.5, // exceeds 32.0°C emergency ceiling
    ], $cycle);

    expect($result['commands']['humidifier'])->toBe('off');
    expect($result['commands']['fan'])->toBe('on');

    $alerts = collect($result['alerts']);
    expect($alerts->contains(fn ($a) => str_contains($a['message'], 'CRITICAL HEAT')))->toBeTrue();
});

test('humidifier continuous runtime limit shuts off after 15 minutes', function () {
    $this->firebase->shouldReceive('getActuators')->once()->andReturn([
        'humidifier' => 'on',
        'fan' => 'off',
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

test('reduced 2-minute post-humidifier settling window allows ventilation after 2 minutes', function () {
    // Humidifier is off, fan is off
    $this->firebase->shouldReceive('getActuators')->andReturn([
        'humidifier' => 'off',
        'fan' => 'off',
    ]);

    $cycle = GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'growing_stage' => 'fruiting',
    ]);

    // Case A: Humidifier turned off 1 minute ago (within 2-minute settling window)
    $logA = ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'off',
        'trigger' => 'automatic',
        'triggered_by' => 'system',
        'triggered_at' => now()->subMinute(),
    ]);

    $serviceA = new ThresholdService($this->firebase);
    $resultA = $serviceA->evaluate([
        'humidity' => 90.0,
        'co2_raw' => 1300, // above 1000 ppm
        'temperature' => 26.0,
    ], $cycle);

    expect($resultA['commands']['fan'])->toBeNull(); // blocked by 2m settling window

    // Case B: Humidifier turned off 3 minutes ago (beyond 2-minute settling window)
    $logA->update(['triggered_at' => now()->subMinutes(3)]);

    $serviceB = new ThresholdService($this->firebase);
    $resultB = $serviceB->evaluate([
        'humidity' => 90.0,
        'co2_raw' => 1300, // above 1000 ppm
        'temperature' => 26.0,
    ], $cycle);

    expect($resultB['commands']['fan'])->toBe('on'); // allowed!
});
