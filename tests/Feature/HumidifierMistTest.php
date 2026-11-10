<?php

use App\Models\ActuatorLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\HumidifierMistService;

function humidifierLog(string $action, $at): void
{
    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => $action,
        'trigger' => 'automatic',
        'triggered_at' => $at,
    ]);
}

test('runtime pairs on/off logs and ignores duplicates', function () {
    $this->travelTo(now()->setTime(12, 0));
    humidifierLog('on', now()->subMinutes(30));
    humidifierLog('on', now()->subMinutes(25));
    humidifierLog('off', now()->subMinutes(20));
    humidifierLog('off', now()->subMinutes(15));

    $service = app(HumidifierMistService::class);

    expect($service->runtimeSeconds(now()->subHour(), now()))->toBe(600)
        ->and($service->mlFor(1000))->toBe(52.0)
        ->and($service->tankMlFor(1000))->toBe(13.0);
});

test('a running session counts until now and carries over the window start', function () {
    $this->travelTo(now()->setTime(12, 0));
    humidifierLog('on', now()->subHours(2));

    expect(app(HumidifierMistService::class)->runtimeSeconds(now()->subHour(), now()))->toBe(3600);
});

test('actuator page exposes mist summary and tank state', function () {
    $this->travelTo(now()->setTime(12, 0));
    Setting::set(HumidifierMistService::REFILLED_AT_KEY, now()->subHours(2)->toIso8601String());
    humidifierLog('on', now()->subHour());
    humidifierLog('off', now()->subMinutes(30));

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/actuators')
        ->assertInertia(fn ($page) => $page
            ->where('mist.today.seconds', 1800)
            ->where('mist.today.ml', 93.6)
            ->where('mist.unit_count', 4)
            ->where('mist.running', false)
            ->where('mist.tank.tracking', true)
            ->where('mist.tank.capacity_ml', 300)
            ->where('mist.tank.used_ml', 23.4)
            ->where('mist.tank.remaining_ml', 276.6)
            ->has('mist.week', 7));
});

test('logs include duration and mist for off entries', function () {
    $this->travelTo(now()->setTime(12, 0));
    humidifierLog('on', now()->subMinutes(10));
    humidifierLog('off', now());

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/actuators')
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reloaded) => $reloaded
                ->where('logs.data.0.duration_seconds', 600)
                ->where('logs.data.0.mist_ml', 31.2)
                ->where('logs.data.1.duration_seconds', null)));
});

test('refill resets tank tracking', function () {
    $this->travelTo(now()->setTime(12, 0));
    humidifierLog('on', now()->subHour());
    humidifierLog('off', now()->subMinutes(30));

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->postJson('/api/actuators/humidifier/refill')
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(app(HumidifierMistService::class)->summary()['tank'])
        ->toMatchArray(['tracking' => true, 'used_ml' => 0.0, 'remaining_ml' => 300.0, 'percent' => 100]);
});

test('students cannot log a refill', function () {
    $this->actingAs(User::factory()->create(['role' => 'student']))
        ->postJson('/api/actuators/humidifier/refill')
        ->assertForbidden();
});

test('low tank alert fires only when tracked and at or below 10 percent', function () {
    $this->travelTo(now()->setTime(12, 0));
    $service = app(HumidifierMistService::class);

    expect($service->lowTankAlert())->toBeNull();

    Setting::set(HumidifierMistService::REFILLED_AT_KEY, now()->subHours(7)->toIso8601String());
    humidifierLog('on', now()->subHours(7));
    humidifierLog('off', now()->subMinutes(90));

    expect($service->lowTankAlert())->toBeNull();

    humidifierLog('on', now()->subMinutes(40));

    $alert = $service->lowTankAlert();
    expect($alert)->not->toBeNull()
        ->and($alert['sensor'])->toBe('water_tank');
});
