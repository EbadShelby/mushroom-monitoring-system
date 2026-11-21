<?php

use App\Models\ActuatorLog;
use App\Models\GrowingCycle;
use App\Models\SensorReading;
use App\Models\User;

test('authorized user can create growing cycle with single common substrate', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Sawdust',
        'mushroom_variety' => 'Gray Oyster Mushroom',
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
        'mushroom_variety' => 'Gray Oyster Mushroom',
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
        'mushroom_variety' => 'Gray Oyster Mushroom',
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
        'mushroom_variety' => 'Gray Oyster Mushroom',
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
        'mushroom_variety' => 'Gray Oyster Mushroom',
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

test('authorized user can create growing cycle with multiple fruiting bag types and counts', function () {
    $user = User::factory()->create(['role' => 'faculty']);

    $response = $this->actingAs($user)->postJson('/api/cycles', [
        'name' => 'Batch 2026-Mixed-Bags',
        'fruiting_bags' => [
            ['variety' => 'Gray Oyster Mushroom', 'count' => 10],
            ['variety' => 'White Oyster Mushroom', 'count' => 1],
        ],
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
    ]);

    $response->assertSuccessful()
        ->assertJsonPath('cycle.mushroom_variety', '10× Gray Oyster Mushroom, 1× White Oyster Mushroom')
        ->assertJsonPath('cycle.fruiting_bags.0.count', 10)
        ->assertJsonPath('cycle.fruiting_bags.1.count', 1);

    $cycle = GrowingCycle::first();
    expect($cycle->fruiting_bags)->toHaveCount(2);
});

test('breach summary counts at most one breach per minute per sensor', function () {
    $cycle = GrowingCycle::create([
        'name' => 'Breach Minute Test',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $base = now()->startOfMinute();

    // Three low-humidity readings in the same minute -> 1 breach
    foreach ([0, 10, 20] as $sec) {
        SensorReading::create([
            'growing_cycle_id' => $cycle->id,
            'temperature' => 28.0,
            'humidity' => 65.0,
            'co2_raw' => 600,
            'light_level' => 300,
            'soil_moisture' => 65,
            'soil_status' => 'moist',
            'recorded_at' => $base->copy()->addSeconds($sec),
        ]);
    }

    // One low-humidity + dry-substrate reading in the next minute -> +1 humidity, +1 soil_moisture breach
    SensorReading::create([
        'growing_cycle_id' => $cycle->id,
        'temperature' => 28.0,
        'humidity' => 65.0,
        'co2_raw' => 600,
        'light_level' => 300,
        'soil_moisture' => 55,
        'soil_status' => 'dry',
        'recorded_at' => $base->copy()->addMinute(),
    ]);

    $summary = $cycle->breachSummary();

    expect($summary['humidity'])->toBe(2)
        ->and($summary['soil_moisture'])->toBe(1)
        ->and($summary['total_readings'])->toBe(4);
});

test('breach summary ignores high humidity within 3 minutes after humidifier shuts off', function () {
    $cycle = GrowingCycle::create([
        'name' => 'Settling Mist Test',
        'mushroom_variety' => 'Gray Oyster Mushroom',
        'substrate_type' => 'Sawdust',
        'start_date' => now()->toDateString(),
        'status' => 'active',
    ]);

    $offAt = now()->startOfMinute();

    ActuatorLog::create([
        'actuator' => 'humidifier',
        'action' => 'off',
        'trigger' => 'automatic',
        'triggered_by' => 'system',
        'triggered_at' => $offAt,
    ]);

    // 1 minute after humidifier OFF -> residual mist settling, should NOT count
    SensorReading::create([
        'growing_cycle_id' => $cycle->id,
        'temperature' => 28.0,
        'humidity' => 96.5,
        'co2_raw' => 600,
        'light_level' => 300,
        'soil_moisture' => 50,
        'soil_status' => 'moist',
        'recorded_at' => $offAt->copy()->addMinute(),
    ]);

    // 5 minutes after humidifier OFF -> past 3-minute grace window, SHOULD count
    SensorReading::create([
        'growing_cycle_id' => $cycle->id,
        'temperature' => 28.0,
        'humidity' => 96.5,
        'co2_raw' => 600,
        'light_level' => 300,
        'soil_moisture' => 50,
        'soil_status' => 'moist',
        'recorded_at' => $offAt->copy()->addMinutes(5),
    ]);

    $summary = $cycle->breachSummary();

    expect($summary['humidity'])->toBe(1)
        ->and($summary['total_readings'])->toBe(2);
});
