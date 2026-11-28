<?php

use App\Models\GrowingCycle;
use App\Models\User;
use App\Services\FirebaseService;
use App\Services\ThresholdService;

test('admin can access settings page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/settings');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('SystemSettings')
        ->where('thresholdDefaults.threshold_temp_min', 26)
        ->where('thresholdDefaults.threshold_temp_max', 32)
        ->where('thresholdDefaults.threshold_humidity_low', 75)
        ->where('thresholdDefaults.threshold_humidity_high', 95)
        ->where('thresholdDefaults.threshold_co2_max', 1000)
        ->where('thresholdDefaults.threshold_light_min', 200)
        ->where('thresholdDefaults.threshold_light_max', 800)
        ->where('thresholdDefaults.threshold_soil_warning', 60)
        ->where('thresholdDefaults.threshold_soil_critical', 40)
        ->where('thresholdDefaults.humidifier_on_at', 78)
        ->where('thresholdDefaults.humidifier_off_at', 93)
    );
});

test('admin can customize humidifier auto on and off levels in settings', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->putJson('/api/settings', [
            'humidifier_on_at' => 76,
            'humidifier_off_at' => 90,
        ])
        ->assertOk();

    $this->actingAs($admin)
        ->get('/actuators')
        ->assertInertia(fn ($page) => $page
            ->where('thresholds.humidifier_on_at', 76)
            ->where('thresholds.humidifier_off_at', 90));

    $cycle = GrowingCycle::create([
        'name' => 'Custom Band Cycle',
        'mushroom_variety' => 'Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $firebase = Mockery::mock(FirebaseService::class);
    $firebase->shouldReceive('getActuators')->andReturn(['humidifier' => 'off']);
    $service = new ThresholdService($firebase);

    expect($service->evaluate(['humidity' => 77.0], $cycle)['commands']['humidifier'])->toBeNull()
        ->and($service->evaluate(['humidity' => 76.0], $cycle)['commands']['humidifier'])->toBe('on');
});

test('humidifier auto off must be at least 2 percent higher than auto on', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $this->actingAs($admin)
        ->putJson('/api/settings', [
            'humidifier_on_at' => 88,
            'humidifier_off_at' => 89,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('humidifier_off_at');
});

test('non-admin user gets forbidden on settings page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)->get('/settings');

    $response->assertStatus(403);
});
