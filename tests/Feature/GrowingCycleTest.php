<?php

use App\Models\GrowingCycle;
use App\Models\User;

test('authorized user can create growing cycle with single common substrate', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Sawdust',
        'mushroom_variety' => 'Gray Oyster Mushroom (Pleurotus sajor-caju)',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
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

test('cannot create a new growing cycle while an active cycle exists', function () {
    $user = User::factory()->create(['role' => 'admin']);

    GrowingCycle::create([
        'name' => 'Active Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Second Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertStatus(422);
    $this->assertDatabaseMissing('growing_cycles', [
        'name' => 'Second Batch',
    ]);
});

test('can create a new growing cycle when previous cycles are completed or archived', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    GrowingCycle::create([
        'name' => 'Completed Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDay()->toDateString(),
        'status' => 'completed',
    ]);

    GrowingCycle::create([
        'name' => 'Archived Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(5)->toDateString(),
        'status' => 'archived',
    ]);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'New Active Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful();
    $this->assertDatabaseHas('growing_cycles', [
        'name' => 'New Active Batch',
        'status' => 'active',
    ]);
});

test('cannot reactivate a cycle while another cycle is active', function () {
    $user = User::factory()->create(['role' => 'admin']);

    GrowingCycle::create([
        'name' => 'Active Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $completed = GrowingCycle::create([
        'name' => 'Completed Batch',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDays(10)->toDateString(),
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->putJson("/api/cycles/{$completed->id}", [
        'status' => 'active',
    ]);

    $response->assertStatus(422);
    expect($completed->fresh()->status)->toBe('completed');
});

test('growing cycle dayCount starts at day 1 and increments daily', function () {
    $todayCycle = GrowingCycle::create([
        'name' => 'Today Cycle',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $yesterdayCycle = GrowingCycle::create([
        'name' => 'Yesterday Cycle',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->subDay()->toDateString(),
        'status' => 'completed',
        'end_date' => now()->toDateString(),
    ]);

    $futureCycle = GrowingCycle::create([
        'name' => 'Future Cycle',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->addDay()->toDateString(),
        'status' => 'completed',
    ]);

    expect($todayCycle->dayCount())->toBe(1)
        ->and($yesterdayCycle->dayCount())->toBe(2)
        ->and($futureCycle->dayCount())->toBe(1);
});
