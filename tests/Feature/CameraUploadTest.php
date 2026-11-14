<?php

use App\Models\GrowingCycle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('admin or faculty can upload a growth photo', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'faculty']);
    $cycle = GrowingCycle::create([
        'name' => 'Cycle 1',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->postJson('/api/camera/upload', [
        'growing_cycle_id' => $cycle->id,
        'flush_number' => 1,
        'captured_date' => now()->toDateString(),
        'notes' => 'Healthy mycelium',
        'photo' => UploadedFile::fake()->image('growth.jpg', 800, 600),
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    expect($cycle->snapshots()->count())->toBe(1);
});

test('admin or faculty can upload a growth photo without specifying flush number', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'faculty']);
    $cycle = GrowingCycle::create([
        'name' => 'Cycle 1',
        'mushroom_variety' => 'Oyster',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $response = $this->actingAs($user)->postJson('/api/camera/upload', [
        'growing_cycle_id' => $cycle->id,
        'captured_date' => now()->toDateString(),
        'notes' => 'Healthy mycelium',
        'photo' => UploadedFile::fake()->image('growth.jpg', 800, 600),
    ]);

    $response->assertOk()->assertJsonPath('success', true);
    $snapshot = $cycle->snapshots()->first();
    expect($snapshot)->not->toBeNull()
        ->and($snapshot->flush_number)->toBe(1);
});

test('upload fails with clear error when growing cycle is missing', function () {
    Storage::fake('public');

    $user = User::factory()->create(['role' => 'admin']);

    $this->actingAs($user)
        ->postJson('/api/camera/upload', [
            'captured_date' => now()->toDateString(),
            'photo' => UploadedFile::fake()->image('growth.jpg'),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['growing_cycle_id' => 'Please select a growing cycle.']);
});
