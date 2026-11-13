<?php

use App\Models\User;

test('authorized user can create growing cycle with single common substrate', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Sawdust',
        'mushroom_variety' => 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'growing_stage' => 'colonization',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('cycle.substrate_type', 'Sawdust');

    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Sawdust',
        'substrate_type' => 'Sawdust',
    ]);
});

test('authorized user can create growing cycle with multiple common substrates', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Mixed',
        'mushroom_variety' => 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
        'substrate_type' => 'Sawdust, Rice Straw, Corn Cobs',
        'start_date' => now()->toDateString(),
        'growing_stage' => 'colonization',
    ]);

    $response->assertSuccessful();
    $response->assertJsonPath('success', true);
    $response->assertJsonPath('cycle.substrate_type', 'Sawdust, Rice Straw, Corn Cobs');

    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Mixed',
        'substrate_type' => 'Sawdust, Rice Straw, Corn Cobs',
    ]);
});

test('authorized user can create growing cycle with custom substrate', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Custom',
        'mushroom_variety' => 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
        'substrate_type' => 'Cotton Waste',
        'start_date' => now()->toDateString(),
        'growing_stage' => 'colonization',
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Custom',
        'substrate_type' => 'Cotton Waste',
    ]);
});

test('authorized user can create growing cycle with combined common and custom substrate', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Enriched',
        'mushroom_variety' => 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
        'substrate_type' => 'Sawdust, Rice Bran (15%), Lime (1%)',
        'start_date' => now()->toDateString(),
        'growing_stage' => 'colonization',
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'Batch 2026-Enriched',
        'substrate_type' => 'Sawdust, Rice Bran (15%), Lime (1%)',
    ]);
});

test('substrate type is required when creating growing cycle', function () {
    $user = User::factory()->create(['role' => 'admin']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-NoSubstrate',
        'mushroom_variety' => 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
        'substrate_type' => '',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('substrate_type');
});

test('student cannot create a growing cycle', function () {
    $student = User::factory()->create(['role' => 'student']);

    $response = $this->actingAs($student)->postJson('/api/cycles', [
        'name' => 'Batch Student',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertStatus(403);
});
