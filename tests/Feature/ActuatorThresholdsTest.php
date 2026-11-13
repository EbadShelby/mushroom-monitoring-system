<?php

use App\Models\GrowingCycle;
use App\Models\User;

test('actuator page shows thresholds for the active growing stage', function () {
    GrowingCycle::create([
        'name' => 'Test Cycle',
        'mushroom_variety' => 'Gray Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'growing_stage' => 'colonization',
    ]);

    $this->actingAs(User::factory()->create(['role' => 'admin']))
        ->get('/actuators')
        ->assertInertia(fn ($page) => $page
            ->where('thresholds.humidity_low', 75)
            ->where('thresholds.humidity_high', 95)
            ->where('thresholds.humidifier_on_at', 78)
            ->where('thresholds.humidifier_off_at', 94)
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
