<?php

use App\Models\CameraSnapshot;
use App\Models\GrowingCycle;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard loads successfully with active cycle and latest camera snapshot', function () {
    $user = User::factory()->create();
    $cycle = GrowingCycle::create([
        'name' => 'Cycle Alpha',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
        'growing_stage' => 'colonization',
    ]);

    CameraSnapshot::create([
        'growing_cycle_id' => $cycle->id,
        'flush_number' => 1,
        'captured_date' => now()->subDay()->toDateString(),
        'file_path' => 'snapshots/photo1.jpg',
        'file_name' => 'photo1.jpg',
        'uploaded_by' => $user->id,
    ]);

    $latest = CameraSnapshot::create([
        'growing_cycle_id' => $cycle->id,
        'flush_number' => 1,
        'captured_date' => now()->toDateString(),
        'file_path' => 'snapshots/photo2.jpg',
        'file_name' => 'photo2.jpg',
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});
