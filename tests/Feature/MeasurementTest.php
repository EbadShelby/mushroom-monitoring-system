<?php

use App\Models\GrowingCycle;
use App\Models\MushroomMeasurement;
use App\Models\User;

test('faculty or admin can log a measurement without weight or flush number', function () {
    $user = User::factory()->create(['role' => 'faculty']);
    $cycle = GrowingCycle::create([
        'name' => 'Cycle 1',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->postJson('/api/measurements', [
        'growing_cycle_id' => $cycle->id,
        'observed_date' => now()->toDateString(),
        'fruiting_body_count' => 14,
        'height_cm' => 6.5,
        'cap_diameter_cm' => 5.2,
        'notes' => 'Healthy pins',
    ]);

    $response->assertSuccessful()->assertJsonPath('success', true);

    $measurement = MushroomMeasurement::first();
    expect($measurement)->not->toBeNull()
        ->and($measurement->fruiting_body_count)->toBe(14)
        ->and((float) $measurement->height_cm)->toBe(6.5)
        ->and((float) $measurement->cap_diameter_cm)->toBe(5.2);
});

test('legacy measurements url redirects to growing cycles', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $this->actingAs($user)
        ->get('/measurements')
        ->assertRedirect('/cycles');
});
