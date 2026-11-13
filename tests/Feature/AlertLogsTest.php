<?php

use App\Models\AlertLog;
use App\Models\GrowingCycle;
use App\Models\Setting;
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
        ->where('smsMute.muted', false)
    );
});

test('faculty or admin can pause SMS for a duration and resume early', function () {
    $faculty = User::factory()->create(['role' => 'faculty', 'contact_number' => '09171234567']);
    config(['services.semaphore.api_key' => 'test-key']);
    Http::fake();

    $muteResponse = $this->actingAs($faculty)
        ->postJson('/api/alerts/mute', ['minutes' => 30]);

    $muteResponse->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('smsMute.muted', true)
        ->assertJsonPath('smsMute.indefinite', false);

    $sms = app(SmsService::class);
    expect($sms->isMuted())->toBeTrue();

    $sms->sendAlert([
        'sensor' => 'temperature',
        'value' => 35,
        'threshold' => 32,
        'message' => 'Too hot',
    ]);

    Http::assertNothingSent();

    $unmuteResponse = $this->actingAs($faculty)
        ->postJson('/api/alerts/unmute');

    $unmuteResponse->assertOk()
        ->assertJsonPath('smsMute.muted', false);

    expect($sms->isMuted())->toBeFalse();
});

test('timed SMS mute expires automatically', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/api/alerts/mute', ['minutes' => 15])
        ->assertOk();

    $sms = app(SmsService::class);
    expect($sms->isMuted())->toBeTrue();

    $this->travel(16)->minutes();

    expect($sms->isMuted())->toBeFalse()
        ->and($sms->muteStatus()['muted'])->toBeFalse();
});

test('admin can pause SMS indefinitely', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->postJson('/api/alerts/mute', ['minutes' => null])
        ->assertOk()
        ->assertJsonPath('smsMute.muted', true)
        ->assertJsonPath('smsMute.indefinite', true)
        ->assertJsonPath('smsMute.muted_until', null);

    expect(Setting::get('sms_muted_until'))->toBe('indefinite');
});

test('student cannot pause or resume SMS alerts', function () {
    $student = User::factory()->create(['role' => 'student']);

    $this->actingAs($student)
        ->postJson('/api/alerts/mute', ['minutes' => 15])
        ->assertForbidden();

    $this->actingAs($student)
        ->postJson('/api/alerts/unmute')
        ->assertForbidden();
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
        'growing_stage' => 'fruiting',
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
