<?php

use App\Models\User;

test('admin can access settings page', function () {
    $admin = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($admin)->get('/settings');

    $response->assertStatus(200);
});

test('non-admin user gets forbidden on settings page', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)->get('/settings');

    $response->assertStatus(403);
});
