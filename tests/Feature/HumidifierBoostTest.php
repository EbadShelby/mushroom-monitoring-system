<?php

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use App\Models\Setting;
use App\Services\FirebaseService;
use App\Services\ThresholdService;
use Illuminate\Support\Facades\Cache;

function seedActiveCycleAndReading(float $humidity): GrowingCycle
{
    $cycle = GrowingCycle::create([
        'name' => 'Boost Test Cycle',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    SensorReading::create([
        'growing_cycle_id' => $cycle->id,
        'temperature' => 28.0,
        'humidity' => $humidity,
        'co2_raw' => 500,
        'light_level' => 300,
        'soil_moisture' => 50,
        'soil_status' => 'moist',
        'recorded_at' => now(),
    ]);

    return $cycle;
}

test('scheduled boost turns humidifier on when cycle is active and humidity is below 88%', function () {
    seedActiveCycleAndReading(83.0);

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->once()->andReturn(['humidifier' => 'off', 'led' => 'on']);
    $firebase->shouldReceive('setActuator')->once()->with('humidifier', 'on');
    $this->app->instance(FirebaseService::class, $firebase);

    $this->artisan('humidifier:boost')->assertSuccessful();

    expect(Cache::get(ThresholdService::BOOST_UNTIL_KEY))->not->toBeNull();
    $this->assertDatabaseHas('actuator_logs', [
        'actuator' => 'humidifier',
        'action' => 'on',
        'trigger' => 'automatic',
        'triggered_by' => 'scheduled_boost',
    ]);
});

test('scheduled boost skips when humidity is already 88% or higher', function () {
    seedActiveCycleAndReading(89.5);

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->once()->andReturn(['humidifier' => 'off']);
    $firebase->shouldNotReceive('setActuator');
    $this->app->instance(FirebaseService::class, $firebase);

    $this->artisan('humidifier:boost')->assertSuccessful();

    expect(Cache::get(ThresholdService::BOOST_UNTIL_KEY))->toBeNull();
    expect(ActuatorLog::count())->toBe(0);
});

test('scheduled boost skips when manual override is active or no cycle is active', function () {
    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldNotReceive('setActuator');
    $this->app->instance(FirebaseService::class, $firebase);

    // No active cycle
    $this->artisan('humidifier:boost')->assertSuccessful();
    expect(ActuatorLog::count())->toBe(0);

    // Active cycle but override enabled
    seedActiveCycleAndReading(82.0);
    Setting::set('override_humidifier', '1');

    $this->artisan('humidifier:boost')->assertSuccessful();
    expect(ActuatorLog::count())->toBe(0);
});

test('scheduled boost stops after 5 minutes even if humidity has not reached 94%', function () {
    $cycle = seedActiveCycleAndReading(83.0);

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->andReturn(['humidifier' => 'on']);
    $this->app->instance(FirebaseService::class, $firebase);

    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'on',
        'trigger' => 'automatic',
        'triggered_by' => 'scheduled_boost',
        'triggered_at' => now()->subMinutes(5),
    ]);

    // Boost window expires now; humidity is 90% (inside 78..94 band)
    Cache::put(ThresholdService::BOOST_UNTIL_KEY, now()->subSecond()->getTimestamp(), now()->addMinutes(5));

    $result = app(ThresholdService::class)->evaluate(['humidity' => 90.0], $cycle);

    expect($result['commands']['humidifier'])->toBe('off')
        ->and(Cache::get(ThresholdService::BOOST_UNTIL_KEY))->toBeNull();
});

test('scheduled boost stops early when humidity reaches 94%', function () {
    $cycle = seedActiveCycleAndReading(83.0);

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->andReturn(['humidifier' => 'on']);
    $this->app->instance(FirebaseService::class, $firebase);

    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'on',
        'trigger' => 'automatic',
        'triggered_by' => 'scheduled_boost',
        'triggered_at' => now()->subMinutes(2),
    ]);

    // 3 minutes still left in boost, but humidity reached 94%
    Cache::put(ThresholdService::BOOST_UNTIL_KEY, now()->addMinutes(3)->getTimestamp(), now()->addMinutes(5));

    $result = app(ThresholdService::class)->evaluate(['humidity' => 94.0], $cycle);

    expect($result['commands']['humidifier'])->toBe('off')
        ->and(Cache::get(ThresholdService::BOOST_UNTIL_KEY))->toBeNull();
});
