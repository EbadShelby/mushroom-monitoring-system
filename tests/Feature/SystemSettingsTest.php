<?php

use App\Models\User;

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
        ->where('thresholdDefaults.threshold_soil_warning', 30)
        ->where('thresholdDefaults.threshold_soil_critical', 20)
    );
});

test('non-admin user gets forbidden on settings page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)->get('/settings');

    $response->assertStatus(403);
});
