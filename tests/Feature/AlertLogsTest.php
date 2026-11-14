<?php

use App\Models\AlertLog;
use App\Models\GrowingCycle;
use App\Models\User;
use App\Services\FirebaseService;
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
    config(['services.semaphore.api_key' => 'test-key']);
    Http::fake();

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('updateSensors')->once();
    $firebase->shouldReceive('getActuators')->andReturn(['humidifier' => 'off', 'led' => 'off']);
    $firebase->shouldReceive('setActuator')->zeroOrMoreTimes();
    $this->app->instance(FirebaseService::class, $firebase);

    $this->postJson('/api/sensor-data', [
        'temperature' => 38.0,
        'humidity' => 50.0,
    ])->assertCreated()
        ->assertJsonPath('cycle_id', null)
        ->assertJsonPath('alerts_triggered', 0);

    Http::assertNothingSent();
});

test('sensor data sends environmental SMS alerts when an active cycle exists', function () {
    User::factory()->create(['role' => 'admin', 'contact_number' => '09171234567']);
    config(['services.semaphore.api_key' => 'test-key']);
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
    ])->assertCreated()
        ->assertJsonPath('cycle_id', $cycle->id)
        ->assertJsonPath('alerts_triggered', 1);

    Http::assertSentCount(1);
});
