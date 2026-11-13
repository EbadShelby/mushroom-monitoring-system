<?php

use App\Models\User;

test('admin can access settings page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/settings');

    $response->assertStatus(200);
    $response->assertInertia(fn ($page) => $page
        ->component('SystemSettings')
        ->where('thresholdDefaults.threshold_col_temp_max', 32)
        ->where('thresholdDefaults.threshold_col_soil_warning', 30)
        ->where('thresholdDefaults.threshold_col_soil_critical', 20)
        ->where('thresholdDefaults.threshold_fruit_temp_max', 32)
        ->where('thresholdDefaults.threshold_fruit_soil_warning', 30)
        ->where('thresholdDefaults.threshold_fruit_soil_critical', 20)
    );
});

test('non-admin user gets forbidden on settings page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)->get('/settings');

    $response->assertStatus(403);
});
