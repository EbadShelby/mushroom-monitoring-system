<?php

use App\Models\GrowingCycle;
use App\Models\User;

test('actuator page shows configured environmental thresholds', function () {
    GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/actuators')
        ->assertInertia(fn ($page) => $page
            ->where('thresholds.temp_min', 26)
            ->where('thresholds.temp_max', 32)
            ->where('thresholds.humidity_low', 75)
            ->where('thresholds.humidity_high', 95)
            ->where('thresholds.humidifier_on_at', 78)
            ->where('thresholds.humidifier_off_at', 93)
            ->where('thresholds.light_min', 200)
            ->where('thresholds.light_max', 800)
            ->where('thresholds.soil_warning', 60)
            ->where('thresholds.soil_critical', 40)
            ->where('thresholds.boost_enabled', true)
            ->where('thresholds.boost_times', ['7:00 AM', '1:00 PM', '7:00 PM'])
            ->where('thresholds.boost_max_start_humidity', 85)
            ->where('thresholds.boost_duration_minutes', 3)
            ->where('thresholds.boost_min_idle_hours', 4)
            ->missing('overrides.fan'));
});

test('admin settings page is not shadowed by the profile redirect', function () {
    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/settings')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('SystemSettings'));

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/settings/profile')
        ->assertOk();
});
