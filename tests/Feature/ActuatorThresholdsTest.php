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
            ->where('thresholds.humidity_low', 70)
            ->where('thresholds.humidity_high', 80)
            ->missing('overrides.fan'));
});
