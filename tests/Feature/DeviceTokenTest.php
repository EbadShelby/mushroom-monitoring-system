<?php

use App\Services\FirebaseService;

beforeEach(function () {
    config(['services.device.token' => 'secret-device-token']);
});

test('sensor-data rejects missing or invalid device token', function (?string $token) {
    $headers = $token !== null ? ['X-Device-Token' => $token] : [];

    $this->postJson('/api/sensor-data', ['temperature' => 25.0], $headers)
        ->assertUnauthorized();
})->with([null, 'wrong-token']);

test('actuator-commands rejects missing or invalid device token', function (?string $token) {
    $headers = $token !== null ? ['X-Device-Token' => $token] : [];

    $this->getJson('/api/actuator-commands', $headers)
        ->assertUnauthorized();
})->with([null, 'wrong-token']);

test('actuator-commands succeeds with valid device token', function () {
    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->once()->andReturn(['humidifier' => 'on', 'led' => 'off']);
    $this->app->instance(FirebaseService::class, $firebase);

    $this->getJson('/api/actuator-commands', ['X-Device-Token' => 'secret-device-token'])
        ->assertOk()
        ->assertJson(['humidifier' => 'on', 'led' => 'off']);
});
